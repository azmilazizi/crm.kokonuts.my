<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_158 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        // wa_pending_sessions was keyed by phone (one pending attachment per
        // phone). Switch to an auto-increment id so a phone can accumulate
        // multiple pending attachments (multi-page receipts) before filing.
        $fields = $CI->db->field_data(db_prefix() . 'wa_pending_sessions');
        $has_id = false;
        foreach ($fields as $field) {
            if ($field->name === 'id') {
                $has_id = true;
                break;
            }
        }

        if (!$has_id) {
            $CI->db->query('ALTER TABLE `' . db_prefix() . 'wa_pending_sessions`
                DROP PRIMARY KEY,
                ADD COLUMN `id` int(11) NOT NULL AUTO_INCREMENT FIRST,
                ADD PRIMARY KEY (`id`),
                ADD KEY `phone` (`phone`)');
        }
    }
}
