<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_147 extends App_module_migration
{
    // Lets a modifier option (tblmodifiers, e.g. "Medium" under the "Cup Size"
    // modifier) be flagged as the pre-selected default for its group — always
    // optional, a group can have none. Consumed by get_modifiers()/the POS
    // terminal's modifiers() API as-is (SELECT * already includes it).
    public function up()
    {
        $CI = &get_instance();

        if ($CI->db->table_exists(db_prefix() . 'modifiers') && !$CI->db->field_exists('is_default', db_prefix() . 'modifiers')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'modifiers`
                ADD COLUMN `is_default` TINYINT(1) NOT NULL DEFAULT 0 COMMENT \'Pre-selected option for this modifier group at POS; optional, none required\'');
        }
    }

    public function down()
    {
        $CI = &get_instance();

        if ($CI->db->table_exists(db_prefix() . 'modifiers') && $CI->db->field_exists('is_default', db_prefix() . 'modifiers')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'modifiers` DROP COLUMN `is_default`');
        }
    }
}
