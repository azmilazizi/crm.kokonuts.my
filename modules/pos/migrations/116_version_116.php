<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_116 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        // Print jobs: created the moment a delivery-platform order is accepted (whether the
        // staff tapped "accept" in the Flutter POS, or the merchant accepted it directly in
        // the platform's own app and we found out via webhook). The Flutter POS polls for
        // pending jobs and prints the linked receipt to both the receipt and kitchen printers.
        if (!$CI->db->table_exists(db_prefix() . 'pos_print_jobs')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . 'pos_print_jobs` (
                `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `warehouse_id`  INT(11) NOT NULL,
                `receipt_id`    INT(11) NOT NULL,
                `source`        VARCHAR(20) NOT NULL DEFAULT "GRABFOOD",
                `status`        ENUM("pending","printed","failed") NOT NULL DEFAULT "pending",
                `attempts`      TINYINT UNSIGNED NOT NULL DEFAULT 0,
                `last_error`    TEXT NULL,
                `created_at`    DATETIME NULL,
                `printed_at`    DATETIME NULL,
                KEY `warehouse_status` (`warehouse_id`, `status`),
                KEY `receipt_id` (`receipt_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        }
    }

    public function down()
    {
        $CI = &get_instance();
        $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'pos_print_jobs`');
    }
}
