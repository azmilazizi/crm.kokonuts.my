<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_114 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        // Extend the shared items table with menu-management fields used by the POS product
        // CRUD and by every delivery-channel push (GrabFood today, FoodPanda/ShopeeFood later).
        $existing = array_column($CI->db->query('SHOW COLUMNS FROM `' . db_prefix() . 'items`')->result_array(), 'Field');
        $alter = [];
        if (!in_array('image', $existing)) {
            $alter[] = 'ADD COLUMN `image` VARCHAR(255) NULL DEFAULT NULL AFTER `description`';
        }
        if (!in_array('out_of_stock', $existing)) {
            $alter[] = 'ADD COLUMN `out_of_stock` TINYINT(1) NOT NULL DEFAULT 0';
        }
        if (!in_array('menu_sort_order', $existing)) {
            $alter[] = 'ADD COLUMN `menu_sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0';
        }
        if (!empty($alter)) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'items` ' . implode(', ', $alter));
        }

        // Top tier of the Sections -> Categories -> Items menu hierarchy (Grab's terminology).
        // Categories are the existing wh_sub_group rows; items are the existing items rows.
        if (!$CI->db->table_exists(db_prefix() . 'pos_menu_sections')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . 'pos_menu_sections` (
                `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `name`       VARCHAR(191) NOT NULL,
                `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                `active`     TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

            $CI->db->insert(db_prefix() . 'pos_menu_sections', [
                'name'       => 'Main Menu',
                'sort_order' => 0,
                'active'     => 1,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        // Which section a category (wh_sub_group) belongs to. Kept as a side table instead of
        // altering wh_sub_group itself, since that table belongs to the Warehouse module.
        // No row, or a NULL section_id, falls back to the lowest-sort_order active section.
        // Category-level ranking reuses wh_sub_group.order (already exists for this purpose).
        if (!$CI->db->table_exists(db_prefix() . 'pos_category_settings')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . 'pos_category_settings` (
                `sub_group_id` INT(11) UNSIGNED NOT NULL PRIMARY KEY,
                `section_id`   INT UNSIGNED NULL DEFAULT NULL,
                KEY `section_id` (`section_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        }
    }

    public function down()
    {
        $CI = &get_instance();
        $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'pos_category_settings`');
        $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'pos_menu_sections`');
        // items columns intentionally left in place — that table is shared core inventory data,
        // not owned by this module, so dropping columns on rollback risks destroying unrelated data.
    }
}
