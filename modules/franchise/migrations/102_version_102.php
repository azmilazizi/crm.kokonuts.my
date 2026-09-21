<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_102 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        // Quotation -> Invoice -> payment-confirmed -> delivery, for HQ selling
        // manufactured goods to franchisees (at a markup, supply-chain model)
        // and to plain (non-franchisee) clients. Line items are kept here
        // (rather than re-derived from invoice description text, the way
        // auto_create_goods_delivery_with_invoice() does) so the delivery step
        // never depends on fragile name-matching.
        if (!$CI->db->table_exists(db_prefix() . 'franchise_sale_orders')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . 'franchise_sale_orders` (
                `id`                        INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `buyer_type`                ENUM("franchisee","client") NOT NULL,
                `franchisee_id`             INT(11) NULL DEFAULT NULL,
                `client_id`                 INT(11) NOT NULL,
                `source_warehouse_id`       INT(11) NOT NULL,
                `destination_warehouse_id`  INT(11) NULL DEFAULT NULL,
                `markup_pct_snapshot`       DECIMAL(5,2) NULL DEFAULT NULL,
                `estimate_id`               INT(11) NULL DEFAULT NULL,
                `invoice_id`                INT(11) NULL DEFAULT NULL,
                `internal_delivery_note_id` INT(11) NULL DEFAULT NULL,
                `goods_delivery_id`         INT(11) NULL DEFAULT NULL,
                `status`                    ENUM("draft","quoted","invoiced","paid","delivered","cancelled") NOT NULL DEFAULT "draft",
                `staff_id`                  INT(11) NOT NULL,
                `created_at`                DATETIME NOT NULL,
                `updated_at`                DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `invoice_id` (`invoice_id`),
                KEY `franchisee_id` (`franchisee_id`),
                KEY `client_id` (`client_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        }

        if (!$CI->db->table_exists(db_prefix() . 'franchise_sale_order_items')) {
            $CI->db->query('CREATE TABLE `' . db_prefix() . 'franchise_sale_order_items` (
                `id`             INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `sale_order_id`  INT(11) NOT NULL,
                `item_id`        INT(11) NOT NULL,
                `quantity`       DECIMAL(15,3) NOT NULL,
                `hq_unit_cost`   DECIMAL(15,4) NOT NULL,
                `unit_price`     DECIMAL(15,4) NOT NULL,
                PRIMARY KEY (`id`),
                KEY `sale_order_id` (`sale_order_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        }

        // Two independent flat markup percentages over HQ's item cost — one
        // for franchisees, one for plain (non-franchisee) clients.
        add_option('franchise_markup_percent', 20, 1);
        add_option('franchise_client_markup_percent', 20, 1);
    }

    public function down()
    {
        $CI = &get_instance();

        $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'franchise_sale_order_items`');
        $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'franchise_sale_orders`');

        $CI->db->where('name', 'franchise_markup_percent')->delete(db_prefix() . 'options');
        $CI->db->where('name', 'franchise_client_markup_percent')->delete(db_prefix() . 'options');
    }
}
