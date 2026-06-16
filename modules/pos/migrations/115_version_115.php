<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_115 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        $items_table = db_prefix() . 'items';
        $cats_table  = db_prefix() . 'pos_category_settings';

        // -------------------------------------------------------------------
        // Items: replace the simple out_of_stock toggle with a richer
        // "available on Food Delivery platforms" + optional FD price override,
        // each with a "_published" twin. Draft fields are what the admin edits
        // on the Products / Menu Layout pages; *_published is what GrabFood (and
        // future FoodPanda/ShopeeFood) actually sees, updated only by "Sync".
        // -------------------------------------------------------------------
        $existing = array_column($CI->db->query('SHOW COLUMNS FROM `' . $items_table . '`')->result_array(), 'Field');

        $alter = [];
        if (!in_array('fd_available', $existing)) {
            $alter[] = 'ADD COLUMN `fd_available` TINYINT(1) NOT NULL DEFAULT 1';
        }
        if (!in_array('fd_price', $existing)) {
            $alter[] = 'ADD COLUMN `fd_price` DECIMAL(15,2) NULL DEFAULT NULL';
        }
        if (!in_array('fd_available_published', $existing)) {
            $alter[] = 'ADD COLUMN `fd_available_published` TINYINT(1) NULL DEFAULT NULL';
        }
        if (!in_array('fd_price_published', $existing)) {
            $alter[] = 'ADD COLUMN `fd_price_published` DECIMAL(15,2) NULL DEFAULT NULL';
        }
        if (!empty($alter)) {
            $CI->db->query('ALTER TABLE `' . $items_table . '` ' . implode(', ', $alter));
        }

        if (in_array('out_of_stock', $existing)) {
            // Carry over the old flag so existing GrabFood listings don't vanish on sync —
            // already-live items stay live (published) until the admin changes something.
            $CI->db->query(
                'UPDATE `' . $items_table . '` SET
                    `fd_available`           = IF(`out_of_stock` = 1, 0, 1),
                    `fd_available_published` = IF(`out_of_stock` = 1, 0, 1)'
            );
            $CI->db->query('ALTER TABLE `' . $items_table . '` DROP COLUMN `out_of_stock`');
        }

        // -------------------------------------------------------------------
        // Category settings: add draft/published sort order + a published flag.
        // With only one fixed section, presence in this table = "added to the FD
        // menu"; sort_order/published mirror the items table's draft/live split.
        // -------------------------------------------------------------------
        $cat_existing = array_column($CI->db->query('SHOW COLUMNS FROM `' . $cats_table . '`')->result_array(), 'Field');
        $cat_alter = [];
        if (!in_array('sort_order', $cat_existing)) {
            $cat_alter[] = 'ADD COLUMN `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0';
        }
        if (!in_array('published', $cat_existing)) {
            $cat_alter[] = 'ADD COLUMN `published` TINYINT(1) NOT NULL DEFAULT 0';
        }
        if (!in_array('sort_order_published', $cat_existing)) {
            $cat_alter[] = 'ADD COLUMN `sort_order_published` SMALLINT UNSIGNED NULL DEFAULT NULL';
        }
        if (!empty($cat_alter)) {
            $CI->db->query('ALTER TABLE `' . $cats_table . '` ' . implode(', ', $cat_alter));
        }

        // Carry over wh_sub_group.order as the starting sort_order for rows that
        // predate this column, and mark already-existing rows as published so
        // the live GrabFood menu doesn't go blank the moment this migration runs.
        $CI->db->query(
            'UPDATE `' . $cats_table . '` cs
             JOIN `' . db_prefix() . 'wh_sub_group` sg ON sg.id = cs.sub_group_id
             SET cs.sort_order = COALESCE(sg.order, 0),
                 cs.published = 1,
                 cs.sort_order_published = COALESCE(sg.order, 0)
             WHERE cs.published = 0'
        );

        // Every wh_sub_group that already has POS products was implicitly part of the
        // menu under the old "no row = default section" behavior. Auto-add (and
        // publish) those now so they keep appearing on GrabFood after this migration.
        $default_section_id = $CI->db->select('id')->order_by('sort_order', 'ASC')->limit(1)
            ->get(db_prefix() . 'pos_menu_sections')->row_array()['id'] ?? null;

        if ($default_section_id !== null) {
            $missing = $CI->db
                ->select('sg.id, sg.order')
                ->from(db_prefix() . 'wh_sub_group sg')
                ->where('EXISTS (SELECT 1 FROM `' . $items_table . '` i WHERE i.sub_group = sg.id AND i.can_be_sold = "can_be_sold")', null, false)
                ->where('NOT EXISTS (SELECT 1 FROM `' . $cats_table . '` cs WHERE cs.sub_group_id = sg.id)', null, false)
                ->get()->result_array();

            foreach ($missing as $row) {
                $CI->db->insert($cats_table, [
                    'sub_group_id'         => $row['id'],
                    'section_id'           => $default_section_id,
                    'sort_order'           => (int) ($row['order'] ?? 0),
                    'published'            => 1,
                    'sort_order_published' => (int) ($row['order'] ?? 0),
                ]);
            }
        }

        // -------------------------------------------------------------------
        // Outbound sync queue: the Sync button enqueues a "notify GrabFood" job
        // per connected store; the existing app-wide Cron job (after_cron_run)
        // drains it in the background instead of blocking the admin's click.
        // -------------------------------------------------------------------
        if (!$CI->db->table_exists(db_prefix() . 'pos_fd_sync_queue')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . 'pos_fd_sync_queue` (
                `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `warehouse_id`  INT(11) NOT NULL,
                `channel`       VARCHAR(20) NOT NULL DEFAULT "grabfood",
                `status`        ENUM("pending","done","failed") NOT NULL DEFAULT "pending",
                `attempts`      TINYINT UNSIGNED NOT NULL DEFAULT 0,
                `last_error`    TEXT NULL,
                `created_at`    DATETIME NULL,
                `processed_at`  DATETIME NULL,
                KEY `status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        }
    }

    public function down()
    {
        $CI = &get_instance();
        $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'pos_fd_sync_queue`');
        // items/pos_category_settings columns intentionally left in place — see migration 114.
    }
}
