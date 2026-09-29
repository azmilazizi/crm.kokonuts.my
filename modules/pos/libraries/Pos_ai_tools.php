<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Business-data tools shared by the in-POS AI Assistant (Gemini function
 * calling) and the MCP connector (application/controllers/Mcp.php), so both
 * answer from the same definitions and the same code.
 *
 * definitions() returns plain JSON-Schema tool specs:
 *   [['name' => ..., 'description' => ..., 'parameters' => [...]], ...]
 */
class Pos_ai_tools
{
    /** @var CI_Controller */
    private $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->load->model('pos/pos_model');
        $this->CI->load->model('loyalty/loyalty_model');
    }

    public function definitions()
    {
        return [
            [
                'name'        => 'get_sales_summary',
                'description' => 'Returns overall sales AND profit metrics for a date range: gross/net sales, total cost, gross profit, profit margin %, discounts, tax, refunds, transaction count, average transaction value, items sold, loyalty points redeemed. Cost/profit is computed live from actual items and modifiers sold, not an estimate — use this directly for "how much profit did we make" style questions, no need to say profit isn\'t available.',
                'parameters'  => ['type' => 'object', 'properties' => [
                    'date_from'    => ['type' => 'string', 'description' => 'Start date YYYY-MM-DD'],
                    'date_to'      => ['type' => 'string', 'description' => 'End date YYYY-MM-DD'],
                    'warehouse_id' => ['type' => 'string', 'description' => 'Optional outlet ID'],
                ], 'required' => ['date_from', 'date_to']],
            ],
            [
                'name'        => 'get_sales_trend',
                'description' => 'Returns sales trend data grouped by day, week, or month. Use for spotting patterns, comparing periods, or charting revenue over time.',
                'parameters'  => ['type' => 'object', 'properties' => [
                    'date_from'    => ['type' => 'string', 'description' => 'Start date YYYY-MM-DD'],
                    'date_to'      => ['type' => 'string', 'description' => 'End date YYYY-MM-DD'],
                    'group_by'     => ['type' => 'string', 'enum' => ['daily', 'weekly', 'monthly'], 'description' => 'Grouping period'],
                    'warehouse_id' => ['type' => 'string', 'description' => 'Optional outlet ID'],
                ], 'required' => ['date_from', 'date_to', 'group_by']],
            ],
            [
                'name'        => 'get_top_products',
                'description' => 'Returns top-selling products by revenue for a date range: quantity sold, gross/net revenue, average unit price.',
                'parameters'  => ['type' => 'object', 'properties' => [
                    'date_from'    => ['type' => 'string', 'description' => 'Start date YYYY-MM-DD'],
                    'date_to'      => ['type' => 'string', 'description' => 'End date YYYY-MM-DD'],
                    'limit'        => ['type' => 'integer', 'description' => 'Number of products (max 15, default 10)'],
                    'warehouse_id' => ['type' => 'string', 'description' => 'Optional outlet ID'],
                ], 'required' => ['date_from', 'date_to']],
            ],
            [
                'name'        => 'get_customer_summary',
                'description' => 'Returns loyalty member metrics: new members, active loyalty customers with sales, total points earned/redeemed, earning and redeeming customer counts.',
                'parameters'  => ['type' => 'object', 'properties' => [
                    'date_from'    => ['type' => 'string', 'description' => 'Start date YYYY-MM-DD'],
                    'date_to'      => ['type' => 'string', 'description' => 'End date YYYY-MM-DD'],
                    'warehouse_id' => ['type' => 'string', 'description' => 'Optional outlet ID'],
                ], 'required' => ['date_from', 'date_to']],
            ],
            [
                'name'        => 'get_customer_retention',
                'description' => 'Returns member retention and churn metrics comparing the selected period to the equal-length period before it: retained, new/returning, lapsed counts, retention rate, churn rate.',
                'parameters'  => ['type' => 'object', 'properties' => [
                    'date_from'    => ['type' => 'string', 'description' => 'Start date YYYY-MM-DD'],
                    'date_to'      => ['type' => 'string', 'description' => 'End date YYYY-MM-DD'],
                    'warehouse_id' => ['type' => 'string', 'description' => 'Optional outlet ID'],
                ], 'required' => ['date_from', 'date_to']],
            ],
            [
                'name'        => 'get_top_customers',
                'description' => 'Returns top loyalty members by total spend: visit count, total spent, points earned/redeemed.',
                'parameters'  => ['type' => 'object', 'properties' => [
                    'date_from'    => ['type' => 'string', 'description' => 'Start date YYYY-MM-DD'],
                    'date_to'      => ['type' => 'string', 'description' => 'End date YYYY-MM-DD'],
                    'warehouse_id' => ['type' => 'string', 'description' => 'Optional outlet ID'],
                ], 'required' => ['date_from', 'date_to']],
            ],
            [
                'name'        => 'get_payment_breakdown',
                'description' => 'Returns payment method breakdown: count, amount, and percentage per payment type (cash, card, e-wallet, etc.).',
                'parameters'  => ['type' => 'object', 'properties' => [
                    'date_from'    => ['type' => 'string', 'description' => 'Start date YYYY-MM-DD'],
                    'date_to'      => ['type' => 'string', 'description' => 'End date YYYY-MM-DD'],
                    'warehouse_id' => ['type' => 'string', 'description' => 'Optional outlet ID'],
                ], 'required' => ['date_from', 'date_to']],
            ],
            [
                'name'        => 'get_loyalty_activity',
                'description' => 'Returns daily loyalty points activity: points earned vs redeemed per day, earn and redeem transaction counts.',
                'parameters'  => ['type' => 'object', 'properties' => [
                    'date_from'    => ['type' => 'string', 'description' => 'Start date YYYY-MM-DD'],
                    'date_to'      => ['type' => 'string', 'description' => 'End date YYYY-MM-DD'],
                    'warehouse_id' => ['type' => 'string', 'description' => 'Optional outlet ID'],
                ], 'required' => ['date_from', 'date_to']],
            ],
            [
                'name'        => 'get_promotion_performance',
                'description' => 'Returns POS promotion performance: receipts using each promo, total discount given, items sold in promo.',
                'parameters'  => ['type' => 'object', 'properties' => [
                    'date_from'    => ['type' => 'string', 'description' => 'Start date YYYY-MM-DD'],
                    'date_to'      => ['type' => 'string', 'description' => 'End date YYYY-MM-DD'],
                    'warehouse_id' => ['type' => 'string', 'description' => 'Optional outlet ID'],
                ], 'required' => ['date_from', 'date_to']],
            ],
            [
                'name'        => 'get_blast_conversion',
                'description' => 'Returns SMS/push blast performance for voucher-linked blasts: recipients, redemptions, conversion rate, time-to-redemption (24h/48h/7d), average hours to redeem.',
                'parameters'  => ['type' => 'object', 'properties' => [
                    'date_from' => ['type' => 'string', 'description' => 'Start date YYYY-MM-DD'],
                    'date_to'   => ['type' => 'string', 'description' => 'End date YYYY-MM-DD'],
                ], 'required' => ['date_from', 'date_to']],
            ],
            [
                'name'        => 'get_voucher_performance',
                'description' => 'Returns voucher program performance: instances issued, redemptions, redemption rate per voucher.',
                'parameters'  => ['type' => 'object', 'properties' => [
                    'date_from' => ['type' => 'string', 'description' => 'Start date YYYY-MM-DD'],
                    'date_to'   => ['type' => 'string', 'description' => 'End date YYYY-MM-DD'],
                ], 'required' => ['date_from', 'date_to']],
            ],
            [
                'name'        => 'get_product_cost_profit',
                'description' => 'Returns cost, profit, and margin across products: selling price, total ingredient cost, profit, profit margin %. Use for "most/least profitable", "highest cost", or margin questions across the menu — not for one specific product\'s recipe (use get_product_cost_detail for that).',
                'parameters'  => ['type' => 'object', 'properties' => [
                    'search'  => ['type' => 'string', 'description' => 'Optional product name or SKU filter'],
                    'sort_by' => ['type' => 'string', 'enum' => ['margin_asc', 'margin_desc', 'profit_asc', 'profit_desc', 'cost_desc'], 'description' => 'margin_asc = least profitable first, margin_desc = most profitable first, cost_desc = costliest first'],
                    'limit'   => ['type' => 'integer', 'description' => 'Number of products to return (max 30, default 10)'],
                ], 'required' => []],
            ],
            [
                'name'        => 'get_product_cost_detail',
                'description' => 'Returns the full ingredient/packaging recipe and cost breakdown for ONE specific product by name: every mixed ingredient, ingredient, and packaging line with quantity and cost. Use when asked what a product is made of, or why it costs what it does.',
                'parameters'  => ['type' => 'object', 'properties' => [
                    'product_name' => ['type' => 'string', 'description' => 'The product name or SKU to look up'],
                ], 'required' => ['product_name']],
            ],
            [
                'name'        => 'get_ingredient_costs',
                'description' => 'Returns current unit cost for raw ingredients or packaging materials — not finished products. Use for "how much does X cost per kg/unit" about a component, not a menu item.',
                'parameters'  => ['type' => 'object', 'properties' => [
                    'search' => ['type' => 'string', 'description' => 'Ingredient or packaging name/SKU to filter by'],
                    'type'   => ['type' => 'string', 'enum' => ['ingredient', 'packaging', 'all'], 'description' => 'Restrict to raw ingredients, packaging, or both (default all)'],
                    'limit'  => ['type' => 'integer', 'description' => 'Max results (default 20, max 50)'],
                ], 'required' => []],
            ],
        ];
    }

    public function names()
    {
        return array_column($this->definitions(), 'name');
    }

    public function execute($name, $args)
    {
        $args = is_array($args) ? $args : [];
        $from = $args['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
        $to   = $args['date_to']   ?? date('Y-m-d');
        $wh   = !empty($args['warehouse_id']) ? (int)$args['warehouse_id'] : null;

        switch ($name) {
            case 'get_sales_summary':
                return $this->CI->pos_model->get_report_sales_summary($from, $to, $wh);

            case 'get_sales_trend':
                $group = in_array($args['group_by'] ?? '', ['daily','weekly','monthly'])
                    ? $args['group_by'] : 'daily';
                $rows = $this->CI->pos_model->get_report_sales_trend($from, $to, $wh, $group);
                return array_slice($rows, 0, 60);

            case 'get_top_products':
                $limit = min(15, max(1, (int)($args['limit'] ?? 10)));
                return $this->CI->pos_model->get_report_products_top($from, $to, $wh, $limit);

            case 'get_customer_summary':
                return $this->CI->pos_model->get_report_customers_summary($from, $to, $wh);

            case 'get_customer_retention':
                return $this->CI->pos_model->get_report_customer_retention($from, $to, $wh);

            case 'get_top_customers':
                return $this->CI->pos_model->get_report_customers_top($from, $to, $wh, 10);

            case 'get_payment_breakdown':
                return $this->CI->pos_model->get_report_payments_breakdown($from, $to, $wh);

            case 'get_loyalty_activity':
                $rows = $this->CI->pos_model->get_report_loyalty_activity($from, $to, $wh);
                return array_slice($rows, 0, 60);

            case 'get_promotion_performance':
                return $this->CI->pos_model->get_report_promotions($from, $to, $wh);

            case 'get_blast_conversion':
                return $this->CI->loyalty_model->get_report_blast_conversion($from, $to);

            case 'get_voucher_performance':
                return $this->CI->loyalty_model->get_report_vouchers($from, $to);

            case 'get_product_cost_profit':
                $rows = $this->CI->pos_model->get_product_cost_profit_summary(['search' => $args['search'] ?? '']);
                $sort = $args['sort_by'] ?? 'margin_asc';
                usort($rows, function ($a, $b) use ($sort) {
                    switch ($sort) {
                        case 'margin_desc': return $b['margin_pct'] <=> $a['margin_pct'];
                        case 'profit_asc':  return $a['profit_per_unit'] <=> $b['profit_per_unit'];
                        case 'profit_desc': return $b['profit_per_unit'] <=> $a['profit_per_unit'];
                        case 'cost_desc':   return $b['total_cost'] <=> $a['total_cost'];
                        default:            return $a['margin_pct'] <=> $b['margin_pct'];
                    }
                });
                $limit = min(30, max(1, (int)($args['limit'] ?? 10)));
                return array_map(function ($r) {
                    return [
                        'sku_code'      => $r['sku_code'],
                        'sku_name'      => $r['sku_name'],
                        'category'      => $r['sub_category_name'] ?: $r['category_name'],
                        'selling_price' => (float)$r['selling_price'],
                        'total_cost'    => (float)$r['total_cost'],
                        'is_range'      => !empty($r['is_range']),
                        'profit'        => (float)$r['profit_per_unit'],
                        'margin_pct'    => (float)$r['margin_pct'],
                    ];
                }, array_slice($rows, 0, $limit));

            case 'get_product_cost_detail':
                $name_arg = trim($args['product_name'] ?? '');
                if ($name_arg === '') {
                    return ['error' => 'product_name is required'];
                }
                $matches = $this->CI->pos_model->search_pos_items_by_name($name_arg, 5);
                if (empty($matches)) {
                    return ['error' => 'No product found matching "' . $name_arg . '"'];
                }
                if (count($matches) > 1) {
                    return [
                        'ambiguous'  => true,
                        'candidates' => array_map(function ($m) { return $m['sku_code'] . ' — ' . $m['sku_name']; }, $matches),
                        'note'       => 'Multiple products matched — ask the user which one they meant, then call this tool again with the exact name.',
                    ];
                }
                $detail = $this->CI->pos_model->get_product_cost_profit_detail((int)$matches[0]['id']);
                if (empty($detail)) {
                    return ['error' => 'Could not load cost detail for this product'];
                }
                return ['item' => $detail['item'], 'sections' => $detail['sections']];

            case 'get_ingredient_costs':
                $type = $args['type'] ?? 'all';
                $rows = [];
                if ($type === 'ingredient' || $type === 'all') {
                    $rows = array_merge($rows, $this->CI->pos_model->get_items_for_costing([
                        'purchase_inventory_only' => true,
                        'exclude_packaging'       => true,
                    ]));
                }
                if ($type === 'packaging' || $type === 'all') {
                    $rows = array_merge($rows, $this->CI->pos_model->get_items_for_costing([]));
                }
                $search = trim($args['search'] ?? '');
                if ($search !== '') {
                    $rows = array_values(array_filter($rows, function ($r) use ($search) {
                        return stripos($r['sku_name'] ?? '', $search) !== false || stripos($r['sku_code'] ?? '', $search) !== false;
                    }));
                }
                $limit = min(50, max(1, (int)($args['limit'] ?? 20)));
                return array_map(function ($r) {
                    $cost = (float)($r['cached_cost_per_unit'] ?? 0);
                    if ($cost <= 0) {
                        $units = (float)($r['units_per_batch'] ?? 0);
                        $purchase = (float)($r['purchase_price'] ?? 0);
                        $cost = $units > 0 ? ($purchase / $units) : $purchase;
                    }
                    return [
                        'sku_code'      => $r['sku_code'],
                        'sku_name'      => $r['sku_name'],
                        'category'      => $r['category_name'] ?? '',
                        'cost_per_unit' => round($cost, 4),
                        'unit'          => $r['unit_uom'] ?: ($r['item_unit_name'] ?? ''),
                    ];
                }, array_slice($rows, 0, $limit));

            default:
                return ['error' => 'Unknown tool'];
        }
    }
}
