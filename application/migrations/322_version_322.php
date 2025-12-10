<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_322 extends CI_Migration
{
    public function up()
    {
        $tableName   = db_prefix() . 'journal_entries';
        $dbCharset   = $this->db->char_set;
        $dbCollation = $this->db->dbcollat;

        if ($this->db->table_exists($tableName)) {
            return;
        }

        $this->db->query(
            'CREATE TABLE `' . $tableName . "` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `title` VARCHAR(191) CHARACTER SET ' . $dbCharset . ' COLLATE ' . $dbCollation . ' NOT NULL,
                `content` MEDIUMTEXT CHARACTER SET ' . $dbCharset . ' COLLATE ' . $dbCollation . ' NULL,
                `entry_date` DATE NOT NULL,
                `created_by` INT(11) NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                INDEX `entry_date` (`entry_date`),
                INDEX `created_by` (`created_by`)
            ) ENGINE=InnoDB DEFAULT CHARSET=' . $dbCharset . ' COLLATE=' . $dbCollation . ';'
        );
    }
}
