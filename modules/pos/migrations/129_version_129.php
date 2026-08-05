<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_129 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        if (!$CI->db->table_exists(db_prefix() . 'pos_import_batches')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . 'pos_import_batches` (
                `id`            INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `source`        VARCHAR(32) NOT NULL COMMENT "WALKIN/GRABFOOD/FOODPANDA/SHOPEEFOOD/etc",
                `warehouse_id`  INT(11) NOT NULL,
                `filename`      VARCHAR(255) NULL DEFAULT NULL,
                `total_rows`    INT(11) NOT NULL DEFAULT 0,
                `imported_rows` INT(11) NOT NULL DEFAULT 0,
                `skipped_rows`  INT(11) NOT NULL DEFAULT 0,
                `error_count`   INT(11) NOT NULL DEFAULT 0,
                `error_log`     MEDIUMTEXT NULL DEFAULT NULL,
                `created_at`    DATETIME NOT NULL,
                `finished_at`   DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `source` (`source`),
                KEY `warehouse_id` (`warehouse_id`),
                KEY `created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        }
    }

    public function down()
    {
        $CI = &get_instance();
        $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'pos_import_batches`');
    }
}
