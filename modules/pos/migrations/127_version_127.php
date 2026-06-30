<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_127 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        if ($CI->db->table_exists(db_prefix() . 'pos_crm_promos')) {
            $CI->db->query("ALTER TABLE `" . db_prefix() . "pos_crm_promos`
                MODIFY `type` ENUM('promo','bundle','set') NOT NULL DEFAULT 'promo'");
        }
    }

    public function down()
    {
        $CI = &get_instance();

        if ($CI->db->table_exists(db_prefix() . 'pos_crm_promos')) {
            $CI->db->query("UPDATE `" . db_prefix() . "pos_crm_promos` SET `type` = 'promo' WHERE `type` = 'set'");
            $CI->db->query("ALTER TABLE `" . db_prefix() . "pos_crm_promos`
                MODIFY `type` ENUM('promo','bundle') NOT NULL DEFAULT 'promo'");
        }
    }
}
