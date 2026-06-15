<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_107 extends App_module_migration
{
    public function up()
    {
        $CI =& get_instance();

        // CFD global settings per warehouse
        if (!$CI->db->table_exists(db_prefix() . 'pos_cfd_settings')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . 'pos_cfd_settings` (
                `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `warehouse_id`   INT(11)      NOT NULL,
                `display_type`   ENUM("static_image","slideshow","video","playlist") NOT NULL DEFAULT "static_image",
                `slide_duration` SMALLINT UNSIGNED NOT NULL DEFAULT 5,
                `updated_at`     DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_cfd_warehouse` (`warehouse_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        }

        // Individual media items (images or videos) per warehouse
        if (!$CI->db->table_exists(db_prefix() . 'pos_cfd_media_items')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . 'pos_cfd_media_items` (
                `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `warehouse_id` INT(11)      NOT NULL,
                `media_type`   ENUM("image","video") NOT NULL DEFAULT "image",
                `file_path`    VARCHAR(500) NOT NULL,
                `sort_order`   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                `duration`     SMALLINT UNSIGNED NULL DEFAULT NULL,
                `created_at`   DATETIME NULL,
                KEY `idx_cfd_media_warehouse` (`warehouse_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        }
    }

    public function down()
    {
        $CI =& get_instance();
        $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'pos_cfd_media_items`');
        $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'pos_cfd_settings`');
    }
}
