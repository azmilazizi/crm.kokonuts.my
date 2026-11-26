<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_102 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        $table = db_prefix() . 'affiliate_products';
        foreach ([
            'discount_percent' => "DECIMAL(5,2) NULL AFTER `price`",
            'sold_count' => "INT UNSIGNED NULL AFTER `discount_percent`",
            'video_url' => "TEXT NULL AFTER `sold_count`",
            'metadata_fetched_at' => "DATETIME NULL AFTER `video_url`",
        ] as $column => $definition) {
            $exists = $CI->db->query("SHOW COLUMNS FROM `{$table}` LIKE " . $CI->db->escape($column))->num_rows() > 0;
            if (!$exists) $CI->db->query("ALTER TABLE `{$table}` ADD `{$column}` {$definition}");
        }
    }

    public function down()
    {
        $CI = &get_instance();
        $table = db_prefix() . 'affiliate_products';
        foreach (['discount_percent', 'sold_count', 'video_url', 'metadata_fetched_at'] as $column) {
            $CI->db->query("ALTER TABLE `{$table}` DROP `{$column}`");
        }
    }
}
