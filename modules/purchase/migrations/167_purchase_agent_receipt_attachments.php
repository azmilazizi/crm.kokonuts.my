<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Purchase_agent_receipt_attachments extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        $table = db_prefix() . 'purchase_agent_captures';

        $columns = [
            'receipt_file_name' => "VARCHAR(255) NULL",
            'receipt_mime_type' => "VARCHAR(100) NULL",
            'receipt_blob'      => "LONGBLOB NULL",
        ];

        foreach ($columns as $name => $definition) {
            if (!$CI->db->field_exists($name, $table)) {
                $CI->db->query("ALTER TABLE `{$table}` ADD COLUMN `{$name}` {$definition}");
            }
        }
    }

    public function down() {}
}
