<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Franchise_model extends App_Model
{
    // =========================================================================
    // Franchisees
    // =========================================================================

    public function get_franchisees($active_only = false)
    {
        $this->db->select('f.*,
                (SELECT COUNT(*) FROM `' . db_prefix() . 'warehouse` w WHERE w.franchisee_id = f.id AND w.display = 1) AS outlet_count')
            ->from(db_prefix() . 'franchise_franchisees f');
        if ($active_only) {
            $this->db->where('f.is_active', 1);
        }
        return $this->db->order_by('f.name', 'ASC')->get()->result_array();
    }

    public function get_franchisee($id)
    {
        return $this->db->get_where(db_prefix() . 'franchise_franchisees', ['id' => (int)$id])->row_array();
    }

    public function create_franchisee($data)
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert(db_prefix() . 'franchise_franchisees', $data);
        return $this->db->insert_id();
    }

    public function update_franchisee($id, $data)
    {
        $this->db->where('id', (int)$id)->update(db_prefix() . 'franchise_franchisees', $data);
        return $this->db->affected_rows() > 0;
    }

    public function delete_franchisee($id)
    {
        $id = (int)$id;
        $outlets = (int)$this->db->where('franchisee_id', $id)->count_all_results(db_prefix() . 'warehouse');
        if ($outlets > 0) {
            return false;
        }
        $this->db->where('id', $id)->delete(db_prefix() . 'franchise_franchisees');
        return true;
    }

    // =========================================================================
    // Outlet Ownership
    // =========================================================================

    public function get_stores_with_franchisee()
    {
        return $this->db
            ->select('w.warehouse_id AS id, w.warehouse_name AS name, w.franchisee_id, w.warehouse_type, f.name AS franchisee_name')
            ->from(db_prefix() . 'warehouse w')
            ->join(db_prefix() . 'franchise_franchisees f', 'f.id = w.franchisee_id', 'left')
            ->where('w.display', 1)
            ->order_by('w.warehouse_name', 'ASC')
            ->get()->result_array();
    }

    public function set_store_franchisee($warehouse_id, $franchisee_id)
    {
        $this->db->where('warehouse_id', (int)$warehouse_id)
            ->update(db_prefix() . 'warehouse', ['franchisee_id' => $franchisee_id ? (int)$franchisee_id : null]);
        return $this->db->affected_rows() >= 0;
    }

    /**
     * Distinguishes the HQ/production warehouse(s) from ordinary retail
     * outlets. Independent of franchisee_id (a warehouse can be HQ-owned
     * retail, franchisee-owned retail, or HQ production — three different
     * things, only the last of which is warehouse_type='hq').
     */
    public function set_warehouse_type($warehouse_id, $type)
    {
        $type = $type === 'hq' ? 'hq' : 'outlet';
        $this->db->where('warehouse_id', (int)$warehouse_id)
            ->update(db_prefix() . 'warehouse', ['warehouse_type' => $type]);
        return $this->db->affected_rows() >= 0;
    }

    public function get_hq_warehouses()
    {
        return $this->db->select('warehouse_id AS id, warehouse_name AS name')
            ->where('warehouse_type', 'hq')
            ->where('display', 1)
            ->order_by('warehouse_name', 'ASC')
            ->get(db_prefix() . 'warehouse')->result_array();
    }

    // =========================================================================
    // Franchisee <-> Client linking
    // =========================================================================

    /**
     * Links a franchisee to a core CRM client so they can be billed with real
     * Estimates/Invoices, and tags that client under a "Franchisee" customer
     * group (created once, reused after) so they stay filterable in
     * /admin/clients without losing any of their existing group memberships.
     */
    public function link_client($franchisee_id, $client_id)
    {
        $franchisee_id = (int)$franchisee_id;
        $client_id     = (int)$client_id;
        if (!$franchisee_id || !$client_id) {
            return false;
        }

        $this->db->where('id', $franchisee_id)
            ->update(db_prefix() . 'franchise_franchisees', ['client_id' => $client_id]);

        $this->load->model('client_groups_model');
        $group_id = $this->_get_or_create_franchisee_group();

        $current_group_ids = array_map(function ($g) {
            return (int)$g['groupid'];
        }, $this->client_groups_model->get_customer_groups($client_id));

        if (!in_array($group_id, $current_group_ids, true)) {
            $current_group_ids[] = $group_id;
            $this->client_groups_model->sync_customer_groups($client_id, $current_group_ids);
        }

        return true;
    }

    public function unlink_client($franchisee_id)
    {
        $this->db->where('id', (int)$franchisee_id)
            ->update(db_prefix() . 'franchise_franchisees', ['client_id' => null]);
        return $this->db->affected_rows() >= 0;
    }

    public function get_linked_client_name($client_id)
    {
        if (!$client_id) {
            return null;
        }
        $row = $this->db->select('company')->where('userid', (int)$client_id)
            ->get(db_prefix() . 'clients')->row_array();
        return $row['company'] ?? null;
    }

    private function _get_or_create_franchisee_group()
    {
        foreach ($this->client_groups_model->get_groups() as $group) {
            if ($group['name'] === 'Franchisee') {
                return (int)$group['id'];
            }
        }
        return (int)$this->client_groups_model->add(['name' => 'Franchisee']);
    }

    private function _franchisee_outlet_ids($franchisee_id)
    {
        $rows = $this->db->select('warehouse_id')->where('franchisee_id', (int)$franchisee_id)
            ->get(db_prefix() . 'warehouse')->result_array();
        return array_map(function ($r) { return (int)$r['warehouse_id']; }, $rows);
    }

    // =========================================================================
    // Cashback Settlement
    // =========================================================================

    public function get_franchisee_outstanding($franchisee_id)
    {
        $outlet_ids = $this->_franchisee_outlet_ids($franchisee_id);
        if (empty($outlet_ids)) {
            return 0.0;
        }

        $pfx = db_prefix();
        $ids = implode(',', $outlet_ids);
        $row = $this->db->query("
            SELECT COALESCE(SUM(t.points), 0) AS s
            FROM `{$pfx}pos_loyalty_transactions` t
            INNER JOIN `{$pfx}pos_receipts` r ON r.id = t.receipt_id
            WHERE t.type = 'redeem'
              AND t.franchise_transfer_id IS NULL
              AND t.warehouse_id IN ({$ids})
              AND r.cancelled_at IS NULL
        ")->row_array();

        return (float)($row['s'] ?? 0);
    }

    public function get_franchisees_summary()
    {
        $franchisees = $this->get_franchisees();
        foreach ($franchisees as &$f) {
            $f['outstanding'] = $this->get_franchisee_outstanding($f['id']);
            $f['lifetime_transferred'] = (float)($this->db->select('SUM(amount) as s')
                ->where('franchisee_id', $f['id'])
                ->get(db_prefix() . 'franchise_transfers')->row()->s ?? 0);
        }
        return $franchisees;
    }

    public function record_franchise_transfer($franchisee_id, $data)
    {
        $franchisee_id = (int)$franchisee_id;
        $outlet_ids    = $this->_franchisee_outlet_ids($franchisee_id);
        if (empty($outlet_ids)) {
            return false;
        }

        $this->db->trans_start();

        $this->db->insert(db_prefix() . 'franchise_transfers', [
            'franchisee_id'  => $franchisee_id,
            'amount'         => (float)$data['amount'],
            'reference_no'   => $data['reference_no'] ?? null,
            'method'         => $data['method'] ?? null,
            'note'           => $data['note'] ?? null,
            'transferred_at' => $data['transferred_at'] ?? date('Y-m-d H:i:s'),
            'staff_id'       => $data['staff_id'] ?? null,
            'created_at'     => date('Y-m-d H:i:s'),
        ]);
        $transfer_id = $this->db->insert_id();

        $pfx = db_prefix();
        $ids = implode(',', $outlet_ids);
        $this->db->query("
            UPDATE `{$pfx}pos_loyalty_transactions` t
            INNER JOIN `{$pfx}pos_receipts` r ON r.id = t.receipt_id
            SET t.franchise_transfer_id = {$transfer_id}
            WHERE t.type = 'redeem'
              AND t.franchise_transfer_id IS NULL
              AND t.warehouse_id IN ({$ids})
              AND r.cancelled_at IS NULL
        ");

        $this->db->trans_complete();
        if ($this->db->trans_status() === false) {
            return false;
        }
        return $transfer_id;
    }

    public function delete_franchise_transfer($id)
    {
        $id = (int)$id;
        $this->db->trans_start();
        $this->db->where('franchise_transfer_id', $id)
            ->update(db_prefix() . 'pos_loyalty_transactions', ['franchise_transfer_id' => null]);
        $this->db->where('id', $id)->delete(db_prefix() . 'franchise_transfers');
        $this->db->trans_complete();
        return $this->db->trans_status() !== false;
    }

    public function count_franchise_transfers($franchisee_id)
    {
        return (int)$this->db->where('franchisee_id', (int)$franchisee_id)
            ->count_all_results(db_prefix() . 'franchise_transfers');
    }

    public function get_franchise_transfers($franchisee_id, $page = 1, $per_page = 20)
    {
        $offset = ((int)$page - 1) * (int)$per_page;
        return $this->db->select('t.*, s.firstname, s.lastname')
            ->from(db_prefix() . 'franchise_transfers t')
            ->join(db_prefix() . 'staff s', 's.staffid = t.staff_id', 'left')
            ->where('t.franchisee_id', (int)$franchisee_id)
            ->order_by('t.transferred_at', 'DESC')
            ->limit($per_page, $offset)
            ->get()->result_array();
    }

    public function get_franchisee_redeem_transactions($franchisee_id, $settled = null, $page = 1, $per_page = 20)
    {
        $outlet_ids = $this->_franchisee_outlet_ids($franchisee_id);
        if (empty($outlet_ids)) {
            return [];
        }
        $offset = ((int)$page - 1) * (int)$per_page;

        $this->db->select('t.*, r.receipt_number, w.warehouse_name, c.name AS customer_name, c.phone AS customer_phone')
            ->from(db_prefix() . 'pos_loyalty_transactions t')
            ->join(db_prefix() . 'pos_receipts r', 'r.id = t.receipt_id', 'left')
            ->join(db_prefix() . 'warehouse w', 'w.warehouse_id = t.warehouse_id', 'left')
            ->join(db_prefix() . 'pos_loyalty_customers c', 'c.id = t.customer_id', 'left')
            ->where('t.type', 'redeem')
            ->where_in('t.warehouse_id', $outlet_ids)
            ->where('r.cancelled_at IS NULL');

        if ($settled === true) {
            $this->db->where('t.franchise_transfer_id IS NOT NULL');
        } elseif ($settled === false) {
            $this->db->where('t.franchise_transfer_id IS NULL');
        }

        return $this->db->order_by('t.created_at', 'DESC')->limit($per_page, $offset)->get()->result_array();
    }

    // =========================================================================
    // Sales / Billing — Quotation -> Invoice -> paid -> Delivery
    // =========================================================================

    /**
     * A franchisee can own more than one outlet (_franchisee_outlet_ids can
     * return several warehouse_ids) — Franchise Sales needs to know which one
     * a given delivery should land in.
     */
    public function get_franchisee_outlets($franchisee_id)
    {
        return $this->db->select('warehouse_id AS id, warehouse_name AS name')
            ->where('franchisee_id', (int)$franchisee_id)
            ->where('display', 1)
            ->order_by('warehouse_name', 'ASC')
            ->get(db_prefix() . 'warehouse')->result_array();
    }

    public function get_markup_percent($buyer_type)
    {
        $option = $buyer_type === 'franchisee' ? 'franchise_markup_percent' : 'franchise_client_markup_percent';
        return (float)get_option($option);
    }

    public function set_markup_percent($buyer_type, $pct)
    {
        $option = $buyer_type === 'franchisee' ? 'franchise_markup_percent' : 'franchise_client_markup_percent';
        return update_option($option, (float)$pct);
    }

    /**
     * Same flat-markup-over-HQ-cost formula for both buyer types, just a
     * different percentage setting per type (decision: two independent
     * markups, not one shared number).
     */
    public function price_sale_order_items(array $item_lines, $buyer_type)
    {
        $this->load->model('pos/pos_model');
        $markup_pct = $this->get_markup_percent($buyer_type);

        $priced = [];
        foreach ($item_lines as $line) {
            $item_id  = (int)($line['item_id'] ?? 0);
            $quantity = (float)($line['quantity'] ?? 0);
            if (!$item_id || $quantity <= 0) {
                continue;
            }

            $cost = $this->pos_model->get_item_unit_cost($item_id);
            $priced[] = [
                'item_id'      => $item_id,
                'quantity'     => $quantity,
                'hq_unit_cost' => round($cost, 4),
                'unit_price'   => round($cost * (1 + $markup_pct / 100), 4),
            ];
        }

        return $priced;
    }

    public function create_sale_order($staff_id, $buyer_type, $franchisee_id, $client_id, $source_warehouse_id, $destination_warehouse_id, array $item_lines)
    {
        $staff_id             = (int)$staff_id;
        $buyer_type           = $buyer_type === 'franchisee' ? 'franchisee' : 'client';
        $client_id            = (int)$client_id;
        $source_warehouse_id  = (int)$source_warehouse_id;

        if (!$staff_id || !$client_id || !$source_warehouse_id) {
            return false;
        }

        $priced_items = $this->price_sale_order_items($item_lines, $buyer_type);
        if (empty($priced_items)) {
            return false;
        }

        $this->db->trans_start();

        $this->db->insert(db_prefix() . 'franchise_sale_orders', [
            'buyer_type'               => $buyer_type,
            'franchisee_id'            => $buyer_type === 'franchisee' ? (int)$franchisee_id : null,
            'client_id'                => $client_id,
            'source_warehouse_id'      => $source_warehouse_id,
            'destination_warehouse_id' => $destination_warehouse_id ? (int)$destination_warehouse_id : null,
            'markup_pct_snapshot'      => $this->get_markup_percent($buyer_type),
            'status'                   => 'draft',
            'staff_id'                 => $staff_id,
            'created_at'               => date('Y-m-d H:i:s'),
        ]);
        $sale_order_id = $this->db->insert_id();

        foreach ($priced_items as $line) {
            $line['sale_order_id'] = $sale_order_id;
            $this->db->insert(db_prefix() . 'franchise_sale_order_items', $line);
        }

        $this->db->trans_complete();
        if ($this->db->trans_status() === false) {
            return false;
        }

        return $this->get_sale_order($sale_order_id);
    }

    public function get_sale_order($id)
    {
        $id = (int)$id;
        $order = $this->db->where('id', $id)->get(db_prefix() . 'franchise_sale_orders')->row_array();
        if (!$order) {
            return null;
        }

        $order['items'] = $this->db->select('soi.*, i.sku_name, i.unit_uom')
            ->from(db_prefix() . 'franchise_sale_order_items soi')
            ->join(db_prefix() . 'items i', 'i.id = soi.item_id', 'left')
            ->where('soi.sale_order_id', $id)
            ->get()->result_array();

        $order['buyer_name'] = $this->db->select('company')->where('userid', $order['client_id'])
            ->get(db_prefix() . 'clients')->row_array()['company'] ?? '';

        $order['destination_name'] = null;
        if (!empty($order['destination_warehouse_id'])) {
            $wh = $this->db->select('warehouse_name')->where('warehouse_id', (int)$order['destination_warehouse_id'])
                ->get(db_prefix() . 'warehouse')->row_array();
            $order['destination_name'] = $wh['warehouse_name'] ?? null;
        }

        return $order;
    }

    /**
     * Enriches each row via get_sale_order() (items, buyer/destination names)
     * — N+1, but this is a low-volume HQ admin list, not a retail hot path.
     */
    public function get_sale_orders($warehouse_id = null, $page = 1, $per_page = 20)
    {
        $offset = (max(1, (int)$page) - 1) * (int)$per_page;

        if ($warehouse_id) {
            $this->db->where('source_warehouse_id', (int)$warehouse_id);
        }

        $rows = $this->db->select('id')->order_by('id', 'DESC')->limit((int)$per_page, $offset)
            ->get(db_prefix() . 'franchise_sale_orders')->result_array();

        return array_values(array_filter(array_map(function ($row) {
            return $this->get_sale_order((int)$row['id']);
        }, $rows)));
    }

    /**
     * Franchisees with a linked CRM client (only these are sellable through
     * the Franchise Sales flow), plus a name search over plain clients for
     * the buyer_type='client' path.
     */
    public function get_sale_buyers($q = '')
    {
        $franchisees = $this->db->select('f.id AS franchisee_id, f.client_id, c.company AS name')
            ->from(db_prefix() . 'franchise_franchisees f')
            ->join(db_prefix() . 'clients c', 'c.userid = f.client_id')
            ->where('f.is_active', 1)
            ->where('f.client_id IS NOT NULL', null, false)
            ->get()->result_array();
        foreach ($franchisees as &$f) {
            $f['buyer_type'] = 'franchisee';
        }
        unset($f);

        $this->db->select('userid AS client_id, company AS name')->from(db_prefix() . 'clients');
        if ($q !== '') {
            $this->db->like('company', $q);
        }
        $clients = $this->db->order_by('company', 'ASC')->limit(50)->get()->result_array();
        foreach ($clients as &$c) {
            $c['buyer_type']    = 'client';
            $c['franchisee_id'] = null;
        }
        unset($c);

        return array_merge($franchisees, $clients);
    }

    public function create_quotation_for_sale_order($sale_order_id)
    {
        $order = $this->get_sale_order($sale_order_id);
        if (!$order || empty($order['items'])) {
            return false;
        }

        $this->load->model('estimates_model');
        $this->load->model('currencies_model');
        $base_currency = $this->currencies_model->get_base_currency();

        $newitems = [];
        $key = 1;
        foreach ($order['items'] as $item) {
            $newitems[$key] = [
                'description'      => $item['sku_name'],
                'long_description' => '',
                'qty'               => $item['quantity'],
                'unit'              => $item['unit_uom'] ?: '',
                'rate'              => $item['unit_price'],
                'taxname'           => [],
                'order'             => $key,
            ];
            $key++;
        }

        $estimate_id = $this->estimates_model->add([
            'clientid'          => $order['client_id'],
            'date'               => date('Y-m-d'),
            'expirydate'         => date('Y-m-d', strtotime('+7 days')),
            'currency'           => $base_currency->id,
            'status'             => 1, // Draft — converted to invoice directly by staff, no client-portal acceptance step
            'billing_street'    => '',
            'discount_percent'  => 0,
            'discount_total'    => 0,
            'discount_type'     => '',
            'adjustment'        => 0,
            'show_quantity_as'  => 1,
            'sale_agent'        => 0,
            'newitems'           => $newitems,
        ]);

        if (!$estimate_id) {
            return false;
        }

        $this->db->where('id', $sale_order_id)->update(db_prefix() . 'franchise_sale_orders', [
            'estimate_id' => $estimate_id,
            'status'      => 'quoted',
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        return $this->get_sale_order($sale_order_id);
    }

    public function convert_sale_order_to_invoice($sale_order_id)
    {
        $order = $this->get_sale_order($sale_order_id);
        if (!$order || empty($order['estimate_id'])) {
            return false;
        }

        $this->load->model('estimates_model');
        $invoice_id = $this->estimates_model->convert_to_invoice($order['estimate_id']);
        if (!$invoice_id) {
            return false;
        }

        $this->db->where('id', $sale_order_id)->update(db_prefix() . 'franchise_sale_orders', [
            'invoice_id' => $invoice_id,
            'status'     => 'invoiced',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->get_sale_order($sale_order_id);
    }

    /**
     * Lets modules/warehouse/warehouse.php's after_invoice_added hook tell
     * whether an invoice belongs to this flow, so it can skip its own
     * unconditional auto-Goods-Delivery creation for it — delivery for these
     * is only ever created by deliver_sale_order() below, gated on payment.
     */
    public function is_franchise_sale_invoice($invoice_id)
    {
        return (bool)$this->db->where('invoice_id', (int)$invoice_id)
            ->count_all_results(db_prefix() . 'franchise_sale_orders');
    }

    /**
     * Bookkeeping only — does NOT trigger delivery. Called from the
     * after_payment_added hook (see franchise.php) every time a payment is
     * recorded against any invoice; a no-op unless that invoice belongs to a
     * tracked sale order and is now fully paid.
     */
    public function sync_sale_order_status_from_invoice($invoice_id)
    {
        $invoice_id = (int)$invoice_id;
        $order = $this->db->where('invoice_id', $invoice_id)
            ->get(db_prefix() . 'franchise_sale_orders')->row_array();
        if (!$order || $order['status'] !== 'invoiced') {
            return false;
        }

        $this->load->model('invoices_model');
        $invoice = $this->invoices_model->get($invoice_id);
        if (!$invoice || (int)$invoice->status !== Invoices_model::STATUS_PAID) {
            return false;
        }

        $this->db->where('id', $order['id'])->update(db_prefix() . 'franchise_sale_orders', [
            'status'     => 'paid',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    /**
     * The payment-gated delivery step. Franchisee buyers get an Internal
     * Delivery Note (stock stays tracked, just moves to their warehouse);
     * plain clients get the existing invoice-linked Goods Delivery flow
     * (stock leaves the business) via auto_create_goods_delivery_with_invoice(),
     * called directly here instead of from the unconditional hook.
     */
    public function deliver_sale_order($sale_order_id, $staff_id)
    {
        $order = $this->get_sale_order($sale_order_id);
        if (!$order || empty($order['invoice_id'])) {
            return $this->_set_delivery_error('Sale order has no invoice yet.');
        }

        // Re-derive paid status in case a payment landed without the hook firing.
        $this->load->helper('invoices');
        update_invoice_status($order['invoice_id']);
        $this->sync_sale_order_status_from_invoice($order['invoice_id']);
        $order = $this->get_sale_order($sale_order_id);

        if ($order['status'] !== 'paid') {
            return $this->_set_delivery_error('Invoice is not fully paid yet.');
        }

        $this->load->model('warehouse/warehouse_model');

        if ($order['buyer_type'] === 'franchisee') {
            if (empty($order['destination_warehouse_id'])) {
                return $this->_set_delivery_error('Franchisee has no destination warehouse assigned.');
            }

            $newitems     = [];
            $total_amount = 0;
            foreach ($order['items'] as $item) {
                $available  = $this->_warehouse_stock_total((int)$order['source_warehouse_id'], (int)$item['item_id']);
                $into_money = round((float)$item['quantity'] * (float)$item['unit_price'], 2);
                $total_amount += $into_money;

                $newitems[] = [
                    'commodity_code'     => $item['item_id'],
                    'commodity_name'     => $item['sku_name'],
                    'from_stock_name'    => $order['source_warehouse_id'],
                    'to_stock_name'      => $order['destination_warehouse_id'],
                    'unit_id'            => '',
                    'available_quantity' => $available,
                    'quantities'         => $item['quantity'],
                    'unit_price'         => $item['unit_price'],
                    'into_money'         => $into_money,
                    'note'               => null,
                ];
            }

            $internal_delivery_id = $this->warehouse_model->add_internal_delivery([
                'internal_delivery_name' => 'Franchise Sale #' . $sale_order_id,
                'description'            => 'Franchise sale delivery for sale order #' . $sale_order_id,
                'staff_id'               => $staff_id,
                'date_c'                 => date('Y-m-d'),
                'date_add'               => date('Y-m-d'),
                'total_amount'           => $total_amount,
                'newitems'               => $newitems,
            ]);

            if (!$internal_delivery_id) {
                return $this->_set_delivery_error('Failed to create the internal delivery note.');
            }

            $this->db->where('id', $sale_order_id)->update(db_prefix() . 'franchise_sale_orders', [
                'internal_delivery_note_id' => $internal_delivery_id,
                'status'                    => 'delivered',
                'updated_at'                => date('Y-m-d H:i:s'),
            ]);
        } else {
            $ok = $this->warehouse_model->auto_create_goods_delivery_with_invoice($order['invoice_id']);
            if (!$ok) {
                return $this->_set_delivery_error('Failed to create the goods delivery.');
            }

            $deliveries = $this->warehouse_model->get_goods_delivery_from_invoice($order['invoice_id']);
            $goods_delivery_id = !empty($deliveries) ? (int)end($deliveries)['id'] : null;

            $this->db->where('id', $sale_order_id)->update(db_prefix() . 'franchise_sale_orders', [
                'goods_delivery_id' => $goods_delivery_id,
                'status'            => 'delivered',
                'updated_at'        => date('Y-m-d H:i:s'),
            ]);
        }

        return $this->get_sale_order($sale_order_id);
    }

    private function _warehouse_stock_total($warehouse_id, $item_id)
    {
        $row = $this->db->select('COALESCE(SUM(CAST(inventory_number AS DECIMAL(15,3))), 0) AS qty', false)
            ->where('warehouse_id', $warehouse_id)
            ->where('commodity_id', $item_id)
            ->get(db_prefix() . 'inventory_manage')->row_array();
        return round((float)($row['qty'] ?? 0), 3);
    }

    private $last_delivery_error = null;

    public function get_last_delivery_error()
    {
        return $this->last_delivery_error;
    }

    private function _set_delivery_error($message)
    {
        $this->last_delivery_error = $message;
        return false;
    }
}
