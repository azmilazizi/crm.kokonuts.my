<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Loyalty_promo_type_fix extends App_module_migration
{
    public function up()
    {
        $CI    = &get_instance();
        $table = db_prefix() . 'pos_loyalty_promotions';

        if (!$CI->db->table_exists($table)) return;

        // Expand ENUM to include 'promotion' and set it as the default
        $CI->db->query("ALTER TABLE `{$table}`
            MODIFY COLUMN `type` ENUM('discount','event','announcement','promotion') NOT NULL DEFAULT 'promotion'");

        // Backfill rows where MySQL stored '' (invalid ENUM value) — treat as promotion
        $CI->db->query("UPDATE `{$table}` SET `type` = 'promotion' WHERE `type` = '' OR `type` IS NULL");
    }

    public function down()
    {
        $CI    = &get_instance();
        $table = db_prefix() . 'pos_loyalty_promotions';

        if (!$CI->db->table_exists($table)) return;

        $CI->db->query("UPDATE `{$table}` SET `type` = 'announcement' WHERE `type` = 'promotion'");
        $CI->db->query("ALTER TABLE `{$table}`
            MODIFY COLUMN `type` ENUM('discount','event','announcement') NOT NULL DEFAULT 'announcement'");
    }
}
