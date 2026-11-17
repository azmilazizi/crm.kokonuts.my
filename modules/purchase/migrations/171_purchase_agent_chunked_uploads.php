<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Purchase_agent_chunked_uploads extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        $table = db_prefix() . 'purchase_agent_uploads';
        $columns = [
            'upload_source' => "VARCHAR(20) NULL",
            'base64_data' => "LONGTEXT NULL",
            'next_chunk' => "INT(11) NOT NULL DEFAULT 0",
            'total_chunks' => "INT(11) NULL",
        ];
        foreach ($columns as $name => $definition) {
            if (!$CI->db->field_exists($name, $table)) $CI->db->query("ALTER TABLE `{$table}` ADD COLUMN `{$name}` {$definition}");
        }
    }

    public function down() {}
}
