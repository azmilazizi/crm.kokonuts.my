<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_110 extends App_module_migration
{
    public function up()
    {
        $CI =& get_instance();

        if (!$CI->db->table_exists(db_prefix() . 'pos_chip_settings')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . 'pos_chip_settings` (
                `id`         int(11)      NOT NULL AUTO_INCREMENT,
                `brand_id`   varchar(100) NOT NULL COMMENT \'Chip Brand ID\',
                `secret_key` text         NOT NULL COMMENT \'Chip Secret Key\',
                `public_key` text         NOT NULL COMMENT \'Chip Public Key (for webhook verification)\',
                `test_mode`  tinyint(1)   NOT NULL DEFAULT 1 COMMENT \'1=sandbox, 0=production\',
                `active`     tinyint(1)   NOT NULL DEFAULT 1,
                `created_at` datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        }

        if (!$CI->db->table_exists(db_prefix() . 'pos_duitnow_transactions')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . 'pos_duitnow_transactions` (
                `id`               int(11)                                              NOT NULL AUTO_INCREMENT,
                `receipt_id`       int(11)                                              DEFAULT NULL COMMENT \'FK to pos_receipts\',
                `purchase_id`      varchar(100)                                         NOT NULL COMMENT \'Chip Purchase ID\',
                `client_reference` varchar(100)                                         DEFAULT NULL,
                `checkout_url`     text                                                 DEFAULT NULL COMMENT \'Chip checkout page URL\',
                `qr_code_url`      text                                                 DEFAULT NULL COMMENT \'URL shown on Customer Facing Display\',
                `amount`           decimal(15,2)                                        NOT NULL,
                `currency`         varchar(3)                                           NOT NULL DEFAULT \'MYR\',
                `status`           enum(\'pending\',\'paid\',\'failed\',\'cancelled\',\'expired\') NOT NULL DEFAULT \'pending\',
                `paid_at`          datetime                                             DEFAULT NULL,
                `expires_at`       datetime                                             DEFAULT NULL,
                `error_message`    text                                                 DEFAULT NULL,
                `webhook_payload`  longtext                                             DEFAULT NULL COMMENT \'Last webhook payload received\',
                `created_at`       datetime                                             NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`       datetime                                             NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `purchase_id` (`purchase_id`),
                KEY `receipt_id`  (`receipt_id`),
                KEY `status`      (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        }
    }

    public function down()
    {
        $CI =& get_instance();
        $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'pos_chip_settings`');
        $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'pos_duitnow_transactions`');
    }
}
