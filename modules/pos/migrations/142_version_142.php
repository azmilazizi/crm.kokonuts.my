<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_142 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        // Opening/Closing SOP templates are authored as free text instead of
        // the tri-state group/item builder (which remains for Equipment
        // templates only) — the admin can insert an outlet's equipment item
        // names as {{placeholder}} text while writing it.
        if (!$CI->db->field_exists('sop_text', db_prefix() . 'pos_checklist_templates')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'pos_checklist_templates` ADD COLUMN `sop_text` LONGTEXT NULL AFTER `name`');
        }
    }

    public function down()
    {
        $CI = &get_instance();

        if ($CI->db->field_exists('sop_text', db_prefix() . 'pos_checklist_templates')) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'pos_checklist_templates` DROP COLUMN `sop_text`');
        }
    }
}
