<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Pos extends AdminController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        if (!has_permission('pos', '', 'view')) {
            access_denied('pos');
        }
        redirect(admin_url('pos/dashboard'));
    }

    // =========================================================================
    // Dashboard
    // =========================================================================

    public function dashboard()
    {
        if (!has_permission('pos', '', 'view')) {
            access_denied('pos');
        }
        $this->load->model('pos/pos_model');
        $data['title']      = 'POS Dashboard';
        $data['warehouses'] = $this->db
            ->select('warehouse_id, warehouse_name')
            ->where('display', 1)
            ->order_by('warehouse_name', 'ASC')
            ->get(db_prefix() . 'warehouse')->result_array();
        $this->load->view('pos/admin/dashboard', $data);
    }

    public function ajax_dashboard_data()
    {
        if (!has_permission('pos', '', 'view')) {
            ajax_access_denied();
        }

        // Discard any stray output (notices, debug) that would corrupt JSON
        if (ob_get_level()) {
            ob_end_clean();
        }
        ob_start();

        header('Content-Type: application/json');

        try {
            $this->load->model('pos/pos_model');

            $date_from    = $this->input->post('date_from')    ?: date('Y-m-d');
            $date_to      = $this->input->post('date_to')      ?: date('Y-m-d');
            $prev_from    = $this->input->post('prev_from')    ?: date('Y-m-d', strtotime('-1 day'));
            $prev_to      = $this->input->post('prev_to')      ?: date('Y-m-d', strtotime('-1 day'));
            $warehouse_id = $this->input->post('warehouse_id') ?: null;

            echo json_encode([
                'success'            => true,
                'summary'            => $this->pos_model->get_dashboard_summary($date_from, $date_to, $warehouse_id),
                'previous'           => $this->pos_model->get_dashboard_summary($prev_from, $prev_to, $warehouse_id),
                'daily'              => $this->pos_model->get_dashboard_daily_trend($date_from, $date_to, $warehouse_id),
                'hourly'             => $this->pos_model->get_dashboard_hourly($date_from, $date_to, $warehouse_id),
                'products'           => $this->pos_model->get_dashboard_top_products($date_from, $date_to, $warehouse_id),
                'payments'           => $this->pos_model->get_dashboard_payments($date_from, $date_to, $warehouse_id),
                'shifts'             => $this->pos_model->get_dashboard_recent_shifts($warehouse_id),
                'categories'         => $this->pos_model->get_dashboard_category_breakdown($date_from, $date_to, $warehouse_id),
                'discount_breakdown' => $this->pos_model->get_dashboard_discount_breakdown($date_from, $date_to, $warehouse_id),
                'promotions'         => $this->pos_model->get_dashboard_promotion_performance($date_from, $date_to, $warehouse_id),
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    // =========================================================================
    // Products
    // =========================================================================

    public function products()
    {
        if (!has_permission('pos', '', 'view')) {
            access_denied('pos');
        }

        $items = $this->db
            ->select('i.id, i.sku_name, i.sku_code, i.rate, i.active, i.image, i.fd_available, i.fd_price, i.fd_available_published, i.fd_price_published, g.name as group_name, sg.sub_group_name')
            ->from(db_prefix() . 'items i')
            ->join(db_prefix() . 'items_groups g', 'g.id = i.group_id', 'left')
            ->join(db_prefix() . 'wh_sub_group sg', 'sg.id = i.sub_group', 'left')
            ->where('i.can_be_sold', 'can_be_sold')
            ->where('i.can_be_manufacturing', 'can_be_manufacturing')
            ->where('i.parent_id IS NULL')
            ->order_by('i.menu_sort_order', 'ASC')
            ->order_by('i.sku_name', 'ASC')
            ->get()->result_array();

        $this->load->model('pos/pos_model');

        $wh_rows = $this->db->select('item_id, warehouse_id')->get(db_prefix() . 'pos_item_warehouses')->result_array();
        $item_warehouse_ids = [];
        foreach ($wh_rows as $row) {
            $item_warehouse_ids[(int)$row['item_id']][] = (int)$row['warehouse_id'];
        }

        $item_ids    = array_column($items, 'id');
        $promo_flags = $this->pos_model->get_crm_promo_flags_by_item_ids($item_ids);

        $modifiers = $this->db
            ->select('m.id, m.name as modifier_name, mg.name as group_name')
            ->from(db_prefix() . 'modifiers m')
            ->join(db_prefix() . 'modifier_groups mg', 'mg.id = m.modifier_group_id', 'left')
            ->where('m.active', 1)
            ->order_by('mg.name', 'ASC')
            ->order_by('m.name', 'ASC')
            ->get()->result_array();

        $mgc_raw = $this->db
            ->select('img.pos_item_id, COUNT(*) as cnt')
            ->from(db_prefix() . 'item_modifier_groups img')
            ->join(db_prefix() . 'modifier_groups mg', 'mg.id = img.modifier_group_id')
            ->group_by('img.pos_item_id')
            ->get()->result_array();
        $imc_raw = $this->db
            ->select('pos_item_id, COUNT(*) as cnt')
            ->from(db_prefix() . 'item_modifiers')
            ->where('active', 1)
            ->group_by('pos_item_id')
            ->get()->result_array();

        $data['title']                      = 'POS Products';
        $data['items']                      = $items;
        $data['item_warehouse_ids']         = $item_warehouse_ids;
        $data['inventory_items']            = $this->pos_model->get_inventory_tracking_items();
        $data['promo_flags']                = $promo_flags;
        $data['modifier_groups']            = $this->pos_model->get_modifier_groups();
        $data['modifiers']                  = $modifiers;
        $data['promo_modifier_groups']      = $this->pos_model->get_promo_modifier_groups();
        $data['item_groups']                = $this->pos_model->get_item_groups();
        $data['sub_groups']                 = $this->pos_model->get_sub_groups();
        $data['warehouses']                 = $this->db->select('warehouse_id, warehouse_name')->where('display', 1)->order_by('warehouse_name', 'ASC')->get(db_prefix() . 'warehouse')->result_array();
        $data['modifier_group_counts']      = array_column($mgc_raw, 'cnt', 'pos_item_id');
        $data['individual_modifier_counts'] = array_column($imc_raw, 'cnt', 'pos_item_id');
        $this->load->view('pos/admin/products', $data);
    }

    public function ajax_get_pos_product($id)
    {
        if (!has_permission('pos', '', 'view')) {
            ajax_access_denied();
        }
        $this->load->model('pos/pos_model');
        $product = $this->pos_model->get_pos_product($id);
        if (!$product) {
            echo json_encode(['success' => false, 'message' => 'Product not found']);
            return;
        }
        $product['crm_promo'] = $this->pos_model->get_crm_promo_by_item_id($id);
        echo json_encode(['success' => true, 'data' => $product]);
    }

    public function ajax_get_sub_groups()
    {
        if (!has_permission('pos', '', 'view')) {
            ajax_access_denied();
        }
        $this->load->model('pos/pos_model');
        $group_id = $this->input->get('group_id');
        echo json_encode([
            'success' => true,
            'data'    => $this->pos_model->get_sub_groups($group_id ?: null),
        ]);
    }

    public function ajax_save_pos_product()
    {
        if (!has_permission('pos', '', 'create') && !has_permission('pos', '', 'edit')) {
            ajax_access_denied();
        }
        $this->load->model('pos/pos_model');

        $id       = (int)$this->input->post('id') ?: null;
        $sku_name = trim($this->input->post('sku_name'));
        $rate     = $this->input->post('rate');

        if (empty($sku_name)) {
            echo json_encode(['success' => false, 'message' => 'Product name is required']);
            return;
        }
        if ($rate === '' || $rate === false || !is_numeric($rate)) {
            echo json_encode(['success' => false, 'message' => 'Price is required']);
            return;
        }

        $result = $this->pos_model->save_pos_product([
            'sku_name'     => $sku_name,
            'sku_code'     => trim($this->input->post('sku_code')),
            'description'  => trim($this->input->post('description')),
            'rate'         => $rate,
            'group_id'     => $this->input->post('group_id'),
            'sub_group'    => $this->input->post('sub_group'),
            'active'       => (int)$this->input->post('active'),
            'fd_available' => (int)$this->input->post('fd_available'),
            'fd_price'     => $this->input->post('fd_price'),
            'inventory_rules' => $this->input->post('inventory_rules') ?: [],
        ], $id);

        if ($result) {
            $warehouse_ids = $this->input->post('warehouse_ids') ?: [];
            if (!is_array($warehouse_ids)) $warehouse_ids = [];
            $this->pos_model->set_item_warehouses($result, $warehouse_ids);

            $warehouse_prices = $this->input->post('warehouse_prices') ?: [];
            if (is_array($warehouse_prices)) {
                $this->pos_model->set_item_warehouse_prices($result, $warehouse_prices);
            }

            // CRM promo / bundle flag
            $promo_enabled = (int)$this->input->post('promo_enabled');
            if ($promo_enabled) {
                $promo_type    = $this->input->post('promo_type') ?: 'promo';
                $components    = $this->input->post('promo_components') ?: [];
                $bundle_groups = $this->input->post('bundle_groups') ?: [];
                $existing      = $this->pos_model->get_crm_promo_by_item_id($result);
                $this->pos_model->save_crm_promo([
                    'name'          => trim($this->input->post('promo_name')) ?: $sku_name,
                    'type'          => $promo_type,
                    'pos_item_id'   => $result,
                    'discount_type' => $this->input->post('promo_discount_type') ?: null,
                    'discount_value'=> $this->input->post('promo_discount_value') ?: 0,
                    'active'        => 1,
                    'components'    => is_array($components) ? $components : [],
                    'bundle_groups' => is_array($bundle_groups) ? $bundle_groups : [],
                ], $existing ? $existing['id'] : null);
            } else {
                // Checkbox unchecked — remove promo flag if it existed
                $existing = $this->pos_model->get_crm_promo_by_item_id($result);
                if ($existing) {
                    $this->pos_model->delete_crm_promo($existing['id']);
                }
            }
        }

        echo json_encode([
            'success' => (bool)$result,
            'id'      => $result ?: null,
            'message' => $result ? '' : 'Failed to save product',
        ]);
    }

    public function ajax_delete_pos_product()
    {
        if (!has_permission('pos', '', 'delete')) {
            ajax_access_denied();
        }
        $this->load->model('pos/pos_model');
        $id     = (int)$this->input->post('id');
        $result = $this->pos_model->delete_pos_product($id);
        echo json_encode($result);
    }

    public function ajax_bulk_set_warehouses()
    {
        if (!has_permission('pos', '', 'edit')) {
            ajax_access_denied();
        }
        $this->load->model('pos/pos_model');

        $item_ids      = $this->input->post('item_ids') ?: [];
        $warehouse_ids = $this->input->post('warehouse_ids') ?: [];
        $mode          = $this->input->post('mode') ?: 'replace';

        if (!is_array($item_ids) || empty($item_ids)) {
            echo json_encode(['success' => false, 'message' => 'No products selected']);
            return;
        }

        $item_ids      = array_values(array_filter(array_map('intval', $item_ids)));
        $warehouse_ids = is_array($warehouse_ids) ? array_values(array_unique(array_filter(array_map('intval', $warehouse_ids)))) : [];

        foreach ($item_ids as $item_id) {
            if ($mode === 'add') {
                $current = $this->pos_model->get_item_warehouses($item_id);
                $this->pos_model->set_item_warehouses($item_id, array_unique(array_merge($current, $warehouse_ids)));
            } elseif ($mode === 'remove') {
                $current = $this->pos_model->get_item_warehouses($item_id);
                $this->pos_model->set_item_warehouses($item_id, array_values(array_diff($current, $warehouse_ids)));
            } else {
                $this->pos_model->set_item_warehouses($item_id, $warehouse_ids);
            }
        }

        echo json_encode(['success' => true, 'updated' => count($item_ids)]);
    }

    public function ajax_upload_item_image()
    {
        if (!has_permission('pos', '', 'edit')) {
            ajax_access_denied();
        }
        $this->load->model('pos/pos_model');

        $item_id = (int)$this->input->post('item_id');
        if (!$item_id) {
            echo json_encode(['success' => false, 'message' => 'Save the product before uploading an image']);
            return;
        }

        if (empty($_FILES['image']['name'])) {
            echo json_encode(['success' => false, 'message' => 'No file uploaded']);
            return;
        }

        $tmpFilePath = $_FILES['image']['tmp_name'];
        if (empty($tmpFilePath) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'Upload failed']);
            return;
        }

        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            echo json_encode(['success' => false, 'message' => 'Only JPG, PNG, or WEBP images are supported']);
            return;
        }

        $dir = FCPATH . 'uploads/pos_items/' . $item_id . '/';
        if (!file_exists($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            echo json_encode(['success' => false, 'message' => 'Could not create upload directory. Check server write permissions.']);
            return;
        }

        $filename = 'item-' . $item_id . '-' . time() . '.' . $ext;
        if (!move_uploaded_file($tmpFilePath, $dir . $filename)) {
            echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file.']);
            return;
        }

        $old = $this->db->select('image')->where('id', $item_id)->get(db_prefix() . 'items')->row_array();
        if (!empty($old['image']) && file_exists($dir . $old['image'])) {
            @unlink($dir . $old['image']);
        }

        $this->pos_model->save_item_image($item_id, $filename);

        echo json_encode([
            'success' => true,
            'image'   => $filename,
            'url'     => base_url('uploads/pos_items/' . $item_id . '/' . $filename),
        ]);
    }

    public function ajax_remove_item_image()
    {
        if (!has_permission('pos', '', 'edit')) {
            ajax_access_denied();
        }
        $this->load->model('pos/pos_model');
        $item_id = (int)$this->input->post('item_id');

        $old = $this->db->select('image')->where('id', $item_id)->get(db_prefix() . 'items')->row_array();
        if (!empty($old['image'])) {
            $path = FCPATH . 'uploads/pos_items/' . $item_id . '/' . $old['image'];
            if (file_exists($path)) {
                @unlink($path);
            }
        }

        $this->pos_model->remove_item_image($item_id);
        echo json_encode(['success' => true]);
    }

    // =========================================================================
    // FD Menu Layout — fixed single section -> opt-in categories -> items.
    // Edits here (and the FD availability/price fields on the product modal) are
    // drafts; nothing reaches GrabFood/FoodPanda/ShopeeFood until ajax_sync_fd_menu().
    // =========================================================================

    public function menu_layout()
    {
        if (!has_permission('pos', '', 'view')) {
            access_denied('pos');
        }
        $this->load->model('pos/pos_model');

        $categories = $this->pos_model->get_categories_with_settings();

        $items = $this->db
            ->select('i.id, i.sku_name, i.image, i.rate, i.active, i.fd_available, i.fd_price, i.fd_available_published, i.fd_price_published, i.sub_group')
            ->from(db_prefix() . 'items i')
            ->where('i.can_be_sold', 'can_be_sold')
            ->where('i.can_be_manufacturing', 'can_be_manufacturing')
            ->where('i.parent_id IS NULL', null, false)
            ->order_by('i.sku_name', 'ASC')
            ->get()->result_array();

        $items_by_category = [];
        foreach ($items as $item) {
            $items_by_category[$item['sub_group'] ?: 0][] = $item;
        }

        $data['title']             = 'Food Delivery Menu Layout';
        $data['categories']        = $categories;
        $data['addable_categories'] = $this->pos_model->get_addable_categories();
        $data['items_by_category'] = $items_by_category;
        $data['last_synced_at']    = get_option('pos_fd_last_synced_at');
        $this->load->view('pos/admin/menu_layout', $data);
    }

    public function ajax_add_category()
    {
        if (!has_permission('pos', '', 'create') && !has_permission('pos', '', 'edit')) {
            ajax_access_denied();
        }
        $this->load->model('pos/pos_model');
        $sub_group_id = (int)$this->input->post('sub_group_id');
        if (!$sub_group_id) {
            echo json_encode(['success' => false, 'message' => 'Select a category to add']);
            return;
        }
        echo json_encode(['success' => (bool)$this->pos_model->add_category($sub_group_id)]);
    }

    public function ajax_disable_category_fd()
    {
        if (!has_permission('pos', '', 'edit')) {
            ajax_access_denied();
        }
        $this->load->model('pos/pos_model');
        $sub_group_id = (int)$this->input->post('sub_group_id');
        echo json_encode(['success' => (bool)$this->pos_model->disable_category_for_fd($sub_group_id)]);
    }

    public function ajax_reorder_category()
    {
        if (!has_permission('pos', '', 'edit')) {
            ajax_access_denied();
        }
        $this->load->model('pos/pos_model');
        $sub_group_id = (int)$this->input->post('sub_group_id');
        $direction    = $this->input->post('direction') === 'up' ? 'up' : 'down';
        echo json_encode(['success' => (bool)$this->pos_model->reorder_category($sub_group_id, $direction)]);
    }

    public function ajax_sync_fd_menu()
    {
        if (!has_permission('pos', '', 'edit')) {
            ajax_access_denied();
        }
        $this->load->model('pos/pos_model');
        $this->load->model('pos/pos_grabfood_model');

        $this->pos_model->publish_fd_menu();

        // Best-effort: ask each connected GrabFood store to re-pull now instead of waiting
        // for their own polling schedule. Queued for the background Cron job rather than
        // called inline, since this is an outbound call to a third-party API.
        $queued = 0;
        foreach ($this->pos_grabfood_model->get_all_active_settings() as $settings) {
            $this->db->insert(db_prefix() . 'pos_fd_sync_queue', [
                'warehouse_id' => $settings['warehouse_id'],
                'channel'      => 'grabfood',
                'status'       => 'pending',
                'created_at'   => date('Y-m-d H:i:s'),
            ]);
            $queued++;
        }

        update_option('pos_fd_last_synced_at', date('Y-m-d H:i:s'));

        echo json_encode([
            'success'        => true,
            'queued'         => $queued,
            'last_synced_at' => get_option('pos_fd_last_synced_at'),
        ]);
    }

    public function ajax_get_item_modifiers($item_id)
    {
        if (!has_permission('pos', '', 'view')) {
            ajax_access_denied();
        }
        $this->load->model('pos/pos_model');
        echo json_encode([
            'success' => true,
            'data'    => $this->pos_model->get_item_modifier_groups($item_id),
        ]);
    }

    public function ajax_assign_modifier_group()
    {
        if (!has_permission('pos', '', 'edit')) {
            ajax_access_denied();
        }
        $this->load->model('pos/pos_model');
        $item_id           = $this->input->post('item_id');
        $modifier_group_id = (int)$this->input->post('modifier_group_id');
        $sort_order        = (int)$this->input->post('sort_order');

        if (!$item_id || !$modifier_group_id) {
            echo json_encode(['success' => false, 'message' => 'item_id and modifier_group_id are required']);
            return;
        }
        $result = $this->pos_model->assign_modifier_group($item_id, $modifier_group_id, $sort_order);
        $assigned = $this->pos_model->get_item_modifier_groups($item_id);
        echo json_encode(['success' => $result, 'data' => $assigned]);
    }

    public function ajax_unassign_modifier_group()
    {
        if (!has_permission('pos', '', 'edit')) {
            ajax_access_denied();
        }
        $this->load->model('pos/pos_model');
        $item_id           = $this->input->post('item_id');
        $modifier_group_id = (int)$this->input->post('modifier_group_id');
        $result = $this->pos_model->unassign_modifier_group($item_id, $modifier_group_id);
        $assigned = $this->pos_model->get_item_modifier_groups($item_id);
        echo json_encode(['success' => $result, 'data' => $assigned]);
    }

    public function ajax_get_item_individual_modifiers($item_id)
    {
        if (!has_permission('pos', '', 'view')) {
            ajax_access_denied();
        }
        $this->load->model('pos/pos_model');
        echo json_encode([
            'success' => true,
            'data'    => $this->pos_model->get_item_modifiers($item_id),
        ]);
    }

    public function ajax_save_item_modifier()
    {
        if (!has_permission('pos', '', 'edit')) {
            ajax_access_denied();
        }
        $this->load->model('pos/pos_model');
        $item_id = $this->input->post('item_id');
        $id      = (int)$this->input->post('id') ?: null;
        $name    = trim($this->input->post('name'));

        if (!$item_id || empty($name)) {
            echo json_encode(['success' => false, 'message' => 'item_id and name are required']);
            return;
        }

        $options_raw = $this->input->post('options');
        $options     = is_array($options_raw) ? $options_raw : [];

        $saved_id = $this->pos_model->save_item_modifier($item_id, [
            'name'           => $name,
            'selection_type' => $this->input->post('selection_type'),
            'sort_order'     => $this->input->post('sort_order'),
            'options'        => $options,
        ], $id);

        echo json_encode([
            'success' => (bool)$saved_id,
            'data'    => $this->pos_model->get_item_modifiers($item_id),
        ]);
    }

    public function ajax_delete_item_modifier()
    {
        if (!has_permission('pos', '', 'delete')) {
            ajax_access_denied();
        }
        $this->load->model('pos/pos_model');
        $item_id = $this->input->post('item_id');
        $id      = (int)$this->input->post('id');
        $result  = $this->pos_model->delete_item_modifier($id, $item_id);
        echo json_encode([
            'success' => $result,
            'data'    => $this->pos_model->get_item_modifiers($item_id),
        ]);
    }

    // =========================================================================
    // Modifiers
    // =========================================================================

    public function modifiers()
    {
        if (!has_permission('pos', '', 'view')) {
            access_denied('pos');
        }
        $this->load->model('pos/pos_model');
        $data['title']      = 'Modifiers';
        $data['groups']     = $this->pos_model->get_modifier_groups();
        $data['warehouses'] = $this->db->select('warehouse_id, warehouse_name')->where('display', 1)->order_by('warehouse_name', 'ASC')->get(db_prefix() . 'warehouse')->result_array();
        $this->load->view('pos/admin/modifiers', $data);
    }

    public function modifier_form($id = null)
    {
        if (!has_permission('pos', '', 'view')) {
            access_denied('pos');
        }
        $this->load->model('pos/pos_model');
        $group = $id ? $this->pos_model->get_modifier_group($id) : null;
        if ($id && !$group) {
            show_404();
        }

        $all_items = $this->db
            ->select('i.id, i.sku_name, i.sku_code')
            ->from(db_prefix() . 'items i')
            ->where('i.can_be_sold', 'can_be_sold')
            ->where('i.can_be_manufacturing', 'can_be_manufacturing')
            ->where('i.parent_id IS NULL')
            ->where('i.active', 1)
            ->order_by('i.sku_name', 'ASC')
            ->get()->result_array();

        $data['title']            = $group ? 'Edit Modifier' : 'Add Modifier';
        $data['group']            = $group;
        $data['all_items']        = $all_items;
        $all_modifiers_flat = $this->db
            ->select('m.id, m.name, m.price_adjustment, mg.name as group_name')
            ->from(db_prefix() . 'modifiers m')
            ->join(db_prefix() . 'modifier_groups mg', 'mg.id = m.modifier_group_id', 'left')
            ->where('m.active', 1);
        if ($group) {
            $all_modifiers_flat->where('m.modifier_group_id !=', $group['id']);
        }
        $all_modifiers_flat = $all_modifiers_flat
            ->order_by('mg.name', 'ASC')->order_by('m.name', 'ASC')
            ->get()->result_array();

        $data['linked_items']        = $group ? $this->pos_model->get_modifier_group_items($group['id']) : [];
        $data['warehouses']          = $this->db->select('warehouse_id, warehouse_name')->where('display', 1)->order_by('warehouse_name', 'ASC')->get(db_prefix() . 'warehouse')->result_array();
        $data['assigned_warehouses'] = $group ? $this->pos_model->get_modifier_group_warehouses($group['id']) : [];
        $data['all_modifiers_flat']  = $all_modifiers_flat;
        $data['inventory_items']     = $this->pos_model->get_inventory_tracking_items();
        $data['crm_promos']          = $this->db->table_exists(db_prefix() . 'pos_crm_promos')
            ? $this->db->select('id, name, type')->where('active', 1)->order_by('name', 'ASC')->get(db_prefix() . 'pos_crm_promos')->result_array()
            : [];
        $this->load->view('pos/admin/modifier_form', $data);
    }

    public function ajax_assign_items_to_modifier()
    {
        if (!has_permission('pos', '', 'edit')) {
            ajax_access_denied();
        }
        $this->load->model('pos/pos_model');
        $modifier_group_id = (int)$this->input->post('modifier_group_id');
        $item_ids          = $this->input->post('item_ids');

        if (!$modifier_group_id || empty($item_ids) || !is_array($item_ids)) {
            echo json_encode(['success' => false, 'message' => 'No items selected']);
            return;
        }

        $this->pos_model->assign_items_to_modifier_group($modifier_group_id, $item_ids);
        $linked = $this->pos_model->get_modifier_group_items($modifier_group_id);
        echo json_encode(['success' => true, 'data' => $linked]);
    }

    public function ajax_unassign_item_from_modifier()
    {
        if (!has_permission('pos', '', 'edit')) {
            ajax_access_denied();
        }
        $this->load->model('pos/pos_model');
        $modifier_group_id = (int)$this->input->post('modifier_group_id');
        $item_id           = $this->input->post('item_id');
        $result            = $this->pos_model->unassign_item_from_modifier_group($modifier_group_id, $item_id);
        $linked            = $this->pos_model->get_modifier_group_items($modifier_group_id);
        echo json_encode(['success' => $result, 'data' => $linked]);
    }

    public function ajax_save_modifier_form()
    {
        if (!has_permission('pos', '', 'create') && !has_permission('pos', '', 'edit')) {
            ajax_access_denied();
        }
        $this->load->model('pos/pos_model');

        $id      = (int)$this->input->post('id') ?: null;
        $name    = trim($this->input->post('name'));
        $options = $this->input->post('options') ?: [];

        if (empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Modifier name is required']);
            return;
        }

        $data = [
            'name'              => $name,
            'selection_type'    => $this->input->post('selection_type') ?: 'single',
            'min_selections'    => (int)$this->input->post('min_selections'),
            'max_selections'    => (int)$this->input->post('max_selections') ?: 1,
            'active'            => 1,
            'is_promo_modifier' => (int)(bool)$this->input->post('is_promo_modifier'),
            'options'           => $options,
        ];

        $result = $this->pos_model->save_modifier_with_options($data, $id);

        if ($result) {
            $warehouse_ids = $this->input->post('warehouse_ids') ?: [];
            if (!is_array($warehouse_ids)) $warehouse_ids = [];
            $this->pos_model->set_modifier_group_warehouses($result, $warehouse_ids);
        }

        echo json_encode(['success' => (bool)$result, 'id' => $result]);
    }

    public function ajax_delete_modifier_group($id)
    {
        if (!has_permission('pos', '', 'delete')) {
            ajax_access_denied();
        }
        $this->load->model('pos/pos_model');
        echo json_encode(['success' => $this->pos_model->delete_modifier_group($id)]);
    }

    public function ajax_delete_modifier_groups_bulk()
    {
        if (!has_permission('pos', '', 'delete')) {
            ajax_access_denied();
        }
        $this->load->model('pos/pos_model');
        $ids = $this->input->post('ids');
        if (empty($ids) || !is_array($ids)) {
            echo json_encode(['success' => false, 'message' => 'No items selected']);
            return;
        }
        echo json_encode(['success' => $this->pos_model->delete_modifier_groups_bulk($ids)]);
    }

    // =========================================================================
    // API Tokens
    // =========================================================================

    public function api_tokens()
    {
        if (!has_permission('pos', '', 'view')) {
            access_denied('pos');
        }

        $tokens = $this->db
            ->select('t.*, CONCAT(s.firstname, " ", s.lastname) as staff_name, w.warehouse_name')
            ->from(db_prefix() . 'pos_api_tokens t')
            ->join(db_prefix() . 'staff s', 's.staffid = t.staff_id', 'left')
            ->join(db_prefix() . 'warehouse w', 'w.warehouse_id = t.warehouse_id', 'left')
            ->order_by('t.created_at', 'DESC')
            ->get()->result_array();

        $data['title']      = 'POS API Tokens';
        $data['tokens']     = $tokens;
        $data['warehouses'] = $this->db->select('warehouse_id, warehouse_name, warehouse_code')->where('display', 1)->order_by('warehouse_name', 'ASC')->get(db_prefix() . 'warehouse')->result_array();
        $data['staff']      = $this->db->select('staffid, firstname, lastname, email')->where('active', 1)->order_by('firstname', 'ASC')->get(db_prefix() . 'staff')->result_array();
        $this->load->view('pos/admin/api_tokens', $data);
    }

    public function ajax_generate_token()
    {
        if (!has_permission('pos', '', 'create')) {
            ajax_access_denied();
        }

        $staff_id     = (int) $this->input->post('staff_id');
        $warehouse_id = (int) $this->input->post('warehouse_id');
        $name         = $this->input->post('name');

        if (!$staff_id || !$warehouse_id) {
            echo json_encode(['success' => false, 'message' => 'Staff member and warehouse are required']);
            return;
        }

        $token = bin2hex(random_bytes(32));
        $this->db->insert(db_prefix() . 'pos_api_tokens', [
            'token'        => $token,
            'name'         => $name ?: null,
            'staff_id'     => $staff_id,
            'warehouse_id' => $warehouse_id,
            'active'       => 1,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        echo json_encode(['success' => true, 'token' => $token, 'id' => $this->db->insert_id()]);
    }

    public function ajax_toggle_token($id)
    {
        if (!has_permission('pos', '', 'edit')) {
            ajax_access_denied();
        }
        $row = $this->db->get_where(db_prefix() . 'pos_api_tokens', ['id' => $id])->row();
        if (!$row) {
            echo json_encode(['success' => false]);
            return;
        }
        $new_active = (int) $row->active === 1 ? 0 : 1;
        $this->db->where('id', $id)->update(db_prefix() . 'pos_api_tokens', ['active' => $new_active]);
        echo json_encode(['success' => true, 'active' => $new_active]);
    }

    public function ajax_delete_token($id)
    {
        if (!has_permission('pos', '', 'delete')) {
            ajax_access_denied();
        }
        $this->db->where('id', $id)->delete(db_prefix() . 'pos_api_tokens');
        echo json_encode(['success' => (bool) $this->db->affected_rows()]);
    }

    // =========================================================================
    // Shifts
    // =========================================================================

    public function shifts()
    {
        if (!has_permission('pos', '', 'view')) {
            access_denied('pos');
        }
        $this->load->model('pos/pos_model');

        $warehouses = $this->db
            ->select('warehouse_id as id, warehouse_name as name')
            ->where('display', 1)
            ->order_by('warehouse_name', 'ASC')
            ->get(db_prefix() . 'warehouse')->result_array();

        $filters = [
            'warehouse_id' => $this->input->get('store')     ?: null,
            'status'       => $this->input->get('status')    ?: '',
            'date_from'    => $this->input->get('date_from') ?: date('Y-m-d', strtotime('-30 days')),
            'date_to'      => $this->input->get('date_to')   ?: date('Y-m-d'),
            'page'         => $this->input->get('page')      ?: 1,
            'limit'        => $this->input->get('limit')     ?: 20,
            'sort'         => $this->input->get('sort')      ?: 'opened_at',
            'dir'          => $this->input->get('dir')       ?: 'desc',
        ];

        $result = $this->pos_model->get_shifts($filters);

        $data['title']      = 'Shifts';
        $data['warehouses'] = $warehouses;
        $data['filters']    = $filters;
        $data['result']     = $result;
        $this->load->view('pos/admin/shifts', $data);
    }

    public function shift_detail($id)
    {
        if (!has_permission('pos', '', 'view')) {
            access_denied('pos');
        }
        $this->load->model('pos/pos_model');

        $report = $this->pos_model->get_shift_report((int)$id);
        if (!$report) {
            show_404();
        }

        $data['title']  = 'Shift ' . htmlspecialchars($report['shift']['shift_code']);
        $data['report'] = $report;
        $this->load->view('pos/admin/shift_detail', $data);
    }

    public function ajax_delete_shift()
    {
        if (!has_permission('pos', '', 'delete')) {
            ajax_access_denied();
        }
        $this->load->model('pos/pos_model');
        $id = (int)$this->input->post('id');
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            return;
        }
        echo json_encode(['success' => $this->pos_model->delete_shift($id)]);
    }

    // Transactions
    // =========================================================================

    public function transactions()
    {
        if (!has_permission('pos', '', 'view')) {
            access_denied('pos');
        }
        $this->load->model('pos/pos_model');

        $warehouses = $this->db
            ->select('warehouse_id as id, warehouse_name as name')
            ->where('display', 1)
            ->order_by('warehouse_name', 'ASC')
            ->get(db_prefix() . 'warehouse')->result_array();

        $filters = [
            'warehouse_id' => $this->input->get('store')        ?: null,
            'date_from'    => $this->input->get('date_from')    ?: date('Y-m-d', strtotime('-30 days')),
            'date_to'      => $this->input->get('date_to')      ?: date('Y-m-d'),
            'search'       => $this->input->get('q')            ?: '',
            'payment_mode' => $this->input->get('payment_mode') ?: '',
            'shift_id'     => $this->input->get('shift_id')     ?: null,
            'page'         => $this->input->get('page')         ?: 1,
            'limit'        => $this->input->get('limit')        ?: 20,
            'sort'         => $this->input->get('sort')         ?: 'receipt_date',
            'dir'          => $this->input->get('dir')          ?: 'desc',
        ];

        $result = $this->pos_model->get_transactions($filters);

        $data['title']         = 'Transactions';
        $data['warehouses']    = $warehouses;
        $data['payment_types'] = $this->pos_model->get_payment_types();
        $data['filters']       = $filters;
        $data['result']        = $result;
        $this->load->view('pos/admin/transactions', $data);
    }

    public function transaction($receipt_number)
    {
        if (!has_permission('pos', '', 'view')) {
            access_denied('pos');
        }
        $this->load->model('pos/pos_model');

        $receipt = $this->pos_model->get_receipt($receipt_number);
        if (!$receipt) {
            show_404();
        }

        $data['title']   = 'Transaction ' . $receipt_number;
        $data['receipt'] = $receipt;
        $this->load->view('pos/admin/transaction_detail', $data);
    }

    public function ajax_delete_transaction()
    {
        if (!has_permission('pos', '', 'delete')) {
            ajax_access_denied();
        }
        $this->load->model('pos/pos_model');
        $id = (int)$this->input->post('id');
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            return;
        }
        echo json_encode(['success' => $this->pos_model->delete_transaction($id)]);
    }

    public function import_transactions()
    {
        if (!has_permission('pos', '', 'create')) {
            access_denied('pos');
        }
        $warehouses = $this->db
            ->select('warehouse_id as id, warehouse_name as name')
            ->where('display', 1)
            ->order_by('warehouse_name', 'ASC')
            ->get(db_prefix() . 'warehouse')->result_array();

        $data['title']      = 'Import Transactions';
        $data['warehouses'] = $warehouses;
        $this->load->view('pos/admin/import_transactions', $data);
    }

    public function ajax_import_transactions()
    {
        if (!has_permission('pos', '', 'create')) {
            ajax_access_denied();
        }
        $this->load->model('pos/pos_model');

        $warehouse_id = (int)$this->input->post('warehouse_id');
        if (!$warehouse_id) {
            echo json_encode(['success' => false, 'message' => 'Please select a store.']);
            return;
        }

        $source     = strtoupper(trim($this->input->post('source') ?: 'WALKIN'));
        $allowed    = ['WALKIN','GRABFOOD','FOODPANDA','SHOPEEFOOD'];
        if (!in_array($source, $allowed, true)) {
            echo json_encode(['success' => false, 'message' => 'Invalid sales source selected.']);
            return;
        }

        $file = $_FILES['csv_file'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error.']);
            return;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            echo json_encode(['success' => false, 'message' => 'Only CSV files are supported.']);
            return;
        }
        $filename = $file['name'];

        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            echo json_encode(['success' => false, 'message' => 'Could not open uploaded file.']);
            return;
        }

        // Read header row.
        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            echo json_encode(['success' => false, 'message' => 'CSV file is empty or unreadable.']);
            return;
        }
        // Strip BOM if present.
        $headers[0] = ltrim($headers[0], "\xEF\xBB\xBF");
        $headers = array_map('trim', $headers);

        $rows = [];
        while (($line = fgetcsv($handle)) !== false) {
            if (count($line) < count($headers)) {
                $line = array_pad($line, count($headers), '');
            }
            $rows[] = array_combine($headers, array_slice($line, 0, count($headers)));
        }
        fclose($handle);

        if (empty($rows)) {
            echo json_encode(['success' => false, 'message' => 'No data rows found in CSV.']);
            return;
        }

        if ($source === 'WALKIN') {
            $result = $this->pos_model->import_walk_in_csv($rows, $warehouse_id, $filename);
        } else {
            $result = $this->pos_model->import_platform_csv($rows, $warehouse_id, $source, $filename);
        }
        echo json_encode(['success' => true] + $result);
    }

    public function export_transactions_csv()
    {
        if (!has_permission('pos', '', 'view')) {
            access_denied('pos');
        }
        $this->load->model('pos/pos_model');

        $filters = [
            'warehouse_id' => $this->input->get('store')     ?: null,
            'date_from'    => $this->input->get('date_from') ?: date('Y-m-d', strtotime('-30 days')),
            'date_to'      => $this->input->get('date_to')   ?: date('Y-m-d'),
            'search'       => $this->input->get('q')         ?: '',
            'page'         => 1,
            'limit'        => 5000,
        ];

        $result = $this->pos_model->get_transactions($filters);
        $rows   = $result['data'];

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="transactions_' . date('Ymd_His') . '.csv"');

        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM for Excel
        fputcsv($out, ['Time', 'Receipt No.', 'Shift', 'Store', 'Employee', 'Type', 'Order Type', 'Subtotal', 'Discount', 'Tax', 'Total']);

        foreach ($rows as $r) {
            $type = !empty($r['cancelled_at']) ? 'Cancelled' : (!empty($r['refund_for']) ? 'Return' : 'Sale');
            fputcsv($out, [
                $r['receipt_date'],
                $r['receipt_number'],
                $r['shift_id'] ?: '—',
                $r['warehouse_name'],
                $r['employee_name'] ?: '—',
                $type,
                $r['dining_option'] ?: '—',
                number_format($r['subtotal'], 2),
                number_format($r['total_discount'], 2),
                number_format($r['total_tax'], 2),
                number_format($r['total_money'], 2),
            ]);
        }
        fclose($out);
        exit;
    }

    // =========================================================================
    // Settings
    // =========================================================================

    public function settings($section = 'receipt')
    {
        if (!has_permission('pos', '', 'view')) {
            access_denied('pos');
        }
        $this->load->model('pos/pos_model');

        $warehouses = $this->db
            ->select('warehouse_id as id, warehouse_name as name')
            ->where('display', 1)
            ->order_by('warehouse_name', 'ASC')
            ->get(db_prefix() . 'warehouse')->result_array();

        $data['title']      = 'POS Settings';
        $data['section']    = $section;
        $data['warehouses'] = $warehouses;

        $warehouse_id = (int)($this->input->get('store') ?: ($warehouses[0]['id'] ?? 0));
        $data['warehouse_id'] = $warehouse_id;

        if ($section === 'receipt') {
            $data['receipt_settings']  = $warehouse_id ? $this->pos_model->get_receipt_settings($warehouse_id) : [];
            $data['cfd_settings']      = [];
            $data['cfd_media_items']   = [];
            $data['payment_modes']     = [];
            $data['grabfood_settings'] = [];
        } elseif ($section === 'cfd') {
            $data['receipt_settings']  = [];
            $data['cfd_settings']      = $warehouse_id ? ($this->pos_model->get_cfd_settings($warehouse_id) ?: []) : [];
            $data['cfd_media_items']   = $warehouse_id ? $this->pos_model->get_cfd_media_items($warehouse_id) : [];
            $data['payment_modes']     = [];
            $data['grabfood_settings'] = [];
        } elseif ($section === 'payment_modes') {
            $data['receipt_settings']  = [];
            $data['cfd_settings']      = [];
            $data['cfd_media_items']   = [];
            $data['payment_modes']     = $this->pos_model->get_payment_modes_with_pos_status();
            $data['grabfood_settings'] = [];
        } elseif ($section === 'grabfood') {
            $this->load->model('pos/pos_grabfood_model');
            $data['receipt_settings']    = [];
            $data['cfd_settings']        = [];
            $data['cfd_media_items']     = [];
            $data['payment_modes']       = [];
            $data['grabfood_settings']   = $warehouse_id ? ($this->pos_grabfood_model->get_settings($warehouse_id) ?: []) : [];
            $data['accounting_settings']     = [];
            $data['acc_accounts']            = [];
            $data['payment_types']           = [];
            $data['payment_method_accounts'] = [];
        } elseif ($section === 'accounting') {
            $this->load->model('accounting/accounting_model');
            $data['receipt_settings']          = [];
            $data['cfd_settings']              = [];
            $data['cfd_media_items']           = [];
            $data['payment_modes']             = [];
            $data['grabfood_settings']         = [];
            $data['accounting_settings']       = $this->pos_model->get_accounting_settings();
            $data['acc_accounts']              = $this->accounting_model->get_accounts();
            $data['payment_types']             = $this->pos_model->get_payment_modes_with_pos_status();
            $data['payment_method_accounts']   = $this->pos_model->get_payment_method_accounts();
        } else {
            $data['receipt_settings']          = [];
            $data['cfd_settings']              = [];
            $data['cfd_media_items']           = [];
            $data['payment_modes']             = [];
            $data['grabfood_settings']         = [];
            $data['accounting_settings']       = [];
            $data['acc_accounts']              = [];
            $data['payment_types']             = [];
            $data['payment_method_accounts']   = [];
        }

        $this->load->view('pos/admin/settings', $data);
    }

    public function ajax_save_receipt_settings()
    {
        if (!has_permission('pos', '', 'edit')) {
            ajax_access_denied();
        }
        $this->load->model('pos/pos_model');

        $warehouse_id = (int)$this->input->post('warehouse_id');
        if (!$warehouse_id) {
            echo json_encode(['success' => false, 'message' => 'Store is required']);
            return;
        }

        $result = $this->pos_model->save_receipt_settings($warehouse_id, [
            'company_name'   => $this->input->post('company_name'),
            'company_reg_id' => $this->input->post('company_reg_id'),
            'address'        => $this->input->post('address'),
            'phone'          => $this->input->post('phone'),
            'header'         => $this->input->post('header'),
            'footer'         => $this->input->post('footer'),
        ]);
        echo json_encode(['success' => $result]);
    }

    public function ajax_upload_receipt_logo()
    {
        if (!has_permission('pos', '', 'edit')) {
            ajax_access_denied();
        }

        $warehouse_id = (int)$this->input->post('warehouse_id');
        if (!$warehouse_id) {
            echo json_encode(['success' => false, 'message' => 'Store is required']);
            return;
        }

        if (empty($_FILES['logo']['name'])) {
            echo json_encode(['success' => false, 'message' => 'No file uploaded']);
            return;
        }

        $this->load->model('pos/pos_model');

        $existing = $this->pos_model->get_receipt_settings($warehouse_id);
        if ($existing && !empty($existing['logo'])) {
            $old_path = FCPATH . $existing['logo'];
            if (file_exists($old_path)) {
                unlink($old_path);
            }
        }

        $upload_dir = FCPATH . 'uploads/pos/logos/' . $warehouse_id . '/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $this->load->library('upload', [
            'upload_path'   => $upload_dir,
            'allowed_types' => 'jpg|jpeg|png|gif|webp',
            'max_size'      => 2048,
            'encrypt_name'  => true,
        ]);

        if (!$this->upload->do_upload('logo')) {
            echo json_encode(['success' => false, 'message' => $this->upload->display_errors('', '')]);
            return;
        }

        $info     = $this->upload->data();
        $rel_path = 'uploads/pos/logos/' . $warehouse_id . '/' . $info['file_name'];

        $this->pos_model->save_receipt_settings($warehouse_id, ['logo' => $rel_path]);
        echo json_encode(['success' => true, 'logo_url' => base_url($rel_path)]);
    }

    public function ajax_delete_receipt_logo()
    {
        if (!has_permission('pos', '', 'edit')) {
            ajax_access_denied();
        }

        $warehouse_id = (int)$this->input->post('warehouse_id');
        $this->load->model('pos/pos_model');
        $existing = $this->pos_model->get_receipt_settings($warehouse_id);

        if ($existing && !empty($existing['logo'])) {
            $path = FCPATH . $existing['logo'];
            if (file_exists($path)) {
                unlink($path);
            }
            $this->pos_model->save_receipt_settings($warehouse_id, ['logo' => null]);
        }

        echo json_encode(['success' => true]);
    }

    // =========================================================================
    // CFD Settings
    // =========================================================================

    public function ajax_save_cfd_settings()
    {
        if (!has_permission('pos', '', 'edit')) {
            ajax_access_denied();
        }
        $this->load->model('pos/pos_model');

        $warehouse_id = (int)$this->input->post('warehouse_id');
        if (!$warehouse_id) {
            echo json_encode(['success' => false, 'message' => 'Store is required']);
            return;
        }

        $allowed_types = ['static_image', 'slideshow', 'video', 'playlist'];
        $display_type  = $this->input->post('display_type');
        if (!in_array($display_type, $allowed_types)) {
            echo json_encode(['success' => false, 'message' => 'Invalid display type']);
            return;
        }

        $slide_duration = max(1, (int)$this->input->post('slide_duration'));

        $this->pos_model->save_cfd_settings($warehouse_id, [
            'display_type'   => $display_type,
            'slide_duration' => $slide_duration,
        ]);
        echo json_encode(['success' => true]);
    }

    public function ajax_upload_cfd_media()
    {
        if (!has_permission('pos', '', 'edit')) {
            ajax_access_denied();
        }

        $warehouse_id = (int)$this->input->post('warehouse_id');
        if (!$warehouse_id) {
            echo json_encode(['success' => false, 'message' => 'Store is required']);
            return;
        }

        if (empty($_FILES['media']['name'])) {
            echo json_encode(['success' => false, 'message' => 'No file uploaded']);
            return;
        }

        $this->load->model('pos/pos_model');

        $mime       = $_FILES['media']['type'];
        $is_video   = strpos($mime, 'video/') === 0;
        $media_type = $is_video ? 'video' : 'image';

        $upload_dir = FCPATH . 'uploads/pos/cfd/' . $warehouse_id . '/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $this->load->library('upload', [
            'upload_path'   => $upload_dir,
            'allowed_types' => 'jpg|jpeg|png|gif|webp|mp4|mov|webm',
            'max_size'      => 51200, // 50 MB
            'encrypt_name'  => true,
        ]);

        if (!$this->upload->do_upload('media')) {
            echo json_encode(['success' => false, 'message' => $this->upload->display_errors('', '')]);
            return;
        }

        $info     = $this->upload->data();
        $rel_path = 'uploads/pos/cfd/' . $warehouse_id . '/' . $info['file_name'];

        $insert_id = $this->pos_model->add_cfd_media_item($warehouse_id, [
            'media_type' => $media_type,
            'file_path'  => $rel_path,
        ]);

        echo json_encode([
            'success'    => true,
            'id'         => $insert_id,
            'media_type' => $media_type,
            'url'        => base_url($rel_path),
        ]);
    }

    public function ajax_delete_cfd_media()
    {
        if (!has_permission('pos', '', 'edit')) {
            ajax_access_denied();
        }
        $this->load->model('pos/pos_model');

        $id           = (int)$this->input->post('id');
        $warehouse_id = (int)$this->input->post('warehouse_id');

        $items = $this->pos_model->get_cfd_media_items($warehouse_id);
        foreach ($items as $item) {
            if ((int)$item['id'] === $id) {
                $path = FCPATH . $item['file_path'];
                if (file_exists($path)) {
                    unlink($path);
                }
                break;
            }
        }

        $this->pos_model->delete_cfd_media_item($id, $warehouse_id);
        echo json_encode(['success' => true]);
    }

    public function ajax_reorder_cfd_media()
    {
        if (!has_permission('pos', '', 'edit')) {
            ajax_access_denied();
        }
        $this->load->model('pos/pos_model');

        $warehouse_id = (int)$this->input->post('warehouse_id');
        $ids          = $this->input->post('ids');
        if (!is_array($ids)) {
            echo json_encode(['success' => false]);
            return;
        }

        $this->pos_model->reorder_cfd_media_items($warehouse_id, $ids);
        echo json_encode(['success' => true]);
    }

    public function ajax_toggle_payment_mode()
    {
        if (!has_permission('pos', '', 'edit')) {
            ajax_access_denied();
        }
        $this->load->model('pos/pos_model');

        $payment_mode_id = (int)$this->input->post('payment_mode_id');
        $enabled         = $this->input->post('enabled');

        if (!$payment_mode_id) {
            echo json_encode(['success' => false, 'message' => 'payment_mode_id is required']);
            return;
        }

        $this->pos_model->toggle_payment_mode_for_pos($payment_mode_id, $enabled);
        echo json_encode(['success' => true]);
    }

    // =========================================================================
    // Chip-in DuitNow QR Settings
    // =========================================================================

    public function chip_settings()
    {
        if (!has_permission('pos', '', 'view')) {
            access_denied('pos');
        }
        $this->load->model('pos/pos_model');
        $data['title']    = 'DuitNow QR (Chip-in) Settings';
        $data['settings'] = $this->pos_model->get_chip_settings();
        $this->load->view('pos/admin/chip_settings', $data);
    }

    public function ajax_save_chip_settings()
    {
        if (!has_permission('pos', '', 'edit')) {
            ajax_access_denied();
        }
        $this->load->model('pos/pos_model');

        $brand_id   = trim($this->input->post('brand_id'));
        $secret_key = trim($this->input->post('secret_key'));
        $public_key = trim($this->input->post('public_key'));

        if (empty($brand_id) || empty($secret_key) || empty($public_key)) {
            echo json_encode(['success' => false, 'message' => 'Brand ID, Secret Key and Public Key are required']);
            return;
        }

        $result = $this->pos_model->save_chip_settings([
            'brand_id'   => $brand_id,
            'secret_key' => $secret_key,
            'public_key' => $public_key,
            'test_mode'  => $this->input->post('test_mode') ? 1 : 0,
            'active'     => $this->input->post('active') ? 1 : 0,
        ]);

        echo json_encode(['success' => $result]);
    }

    public function ajax_test_chip_connection()
    {
        if (!has_permission('pos', '', 'edit')) {
            ajax_access_denied();
        }
        $this->load->model('pos/pos_model');

        $settings = $this->pos_model->get_chip_settings();
        if (empty($settings)) {
            echo json_encode(['success' => false, 'message' => 'No settings configured']);
            return;
        }

        $base_url = 'https://gate.chip-in.asia/api/v1/';
        $ch = curl_init($base_url . 'brands/' . $settings['brand_id'] . '/');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $settings['secret_key']],
            CURLOPT_TIMEOUT        => 10,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code === 200) {
            echo json_encode(['success' => true]);
        } else {
            $body = json_decode($resp, true);
            echo json_encode(['success' => false, 'message' => $body['detail'] ?? 'HTTP ' . $code]);
        }
    }

    // =========================================================================
    // Import Modifier Groups
    // =========================================================================

    public function import_modifier_groups()
    {
        if (!has_permission('pos', '', 'create')) {
            access_denied('pos');
        }
        $data['title'] = 'Import Modifier Groups';
        $this->load->view('pos/admin/import_modifier_groups', $data);
    }

    public function download_modifier_groups_sample()
    {
        if (!has_permission('pos', '', 'view')) {
            access_denied('pos');
        }
        require_once(module_dir_path('warehouse') . '/assets/plugins/XLSXWriter/xlsxwriter.class.php');

        $writer = new XLSXWriter();

        $header = [
            '(*)Group Name'          => 'string',
            '(*)Selection Type'      => 'string',
            'Min Selections'         => 'integer',
            'Max Selections'         => 'integer',
            'Option 1 Name'          => 'string',
            'Option 1 Price'         => 'price',
            'Option 2 Name'          => 'string',
            'Option 2 Price'         => 'price',
            'Option 3 Name'          => 'string',
            'Option 3 Price'         => 'price',
            'Option 4 Name'          => 'string',
            'Option 4 Price'         => 'price',
            'Option 5 Name'          => 'string',
            'Option 5 Price'         => 'price',
            'Option 6 Name'          => 'string',
            'Option 6 Price'         => 'price',
            'Option 7 Name'          => 'string',
            'Option 7 Price'         => 'price',
            'Option 8 Name'          => 'string',
            'Option 8 Price'         => 'price',
            'Option 9 Name'          => 'string',
            'Option 9 Price'         => 'price',
            'Option 10 Name'         => 'string',
            'Option 10 Price'        => 'price',
            'Linked Item SKU 1'      => 'string',
            'Linked Item SKU 2'      => 'string',
            'Linked Item SKU 3'      => 'string',
            'Linked Item SKU 4'      => 'string',
            'Linked Item SKU 5'      => 'string',
        ];

        $widths = array_fill(0, count($header), 22);

        $col_style = array_keys(array_fill(0, count($header), 0));
        $header_style = [
            'widths'       => $widths,
            'fill'         => '#1e88e5',
            'font-style'   => 'bold',
            'color'        => '#ffffff',
            'border'       => 'left,right,top,bottom',
            'border-color' => '#0a0a0a',
            'font-size'    => 12,
        ];

        $writer->writeSheetHeader_v2('Modifier Groups', $header, ['widths' => $widths], $col_style, $header_style);

        // Sample rows
        $writer->writeSheetRow('Modifier Groups', [
            'Sugar Level', 'single', 0, 1,
            'No Sugar', 0, 'Less Sugar', 0, 'Normal', 0, 'Extra Sugar', 0,
            '', 0, '', 0, '', 0, '', 0, '', 0, '', 0,
            'ITEM-001', 'ITEM-002', '', '', '',
        ]);
        $writer->writeSheetRow('Modifier Groups', [
            'Add-ons', 'multiple', 0, 3,
            'Cheese', 1.50, 'Bacon', 2.00, 'Egg', 1.00, 'Mushroom', 1.50,
            '', 0, '', 0, '', 0, '', 0, '', 0, '', 0,
            'ITEM-001', '', '', '', '',
        ]);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="modifier_groups_template.xlsx"');
        header('Cache-Control: max-age=0');
        $writer->writeToStdOut();
        exit;
    }

    public function import_file_modifier_groups()
    {
        if (ob_get_level()) {
            ob_end_clean();
        }
        ob_start();

        header('Content-Type: application/json');

        if (!has_permission('pos', '', 'create')) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            return;
        }

        if (!class_exists('XLSXReader_fin')) {
            require_once(module_dir_path('warehouse') . '/assets/plugins/XLSXReader/XLSXReader.php');
        }

        $this->load->model('pos/pos_model');

        if (empty($_FILES['file_xlsx']['name'])) {
            echo json_encode(['success' => false, 'message' => 'No file uploaded']);
            return;
        }

        $tmpFilePath = $_FILES['file_xlsx']['tmp_name'];
        if (empty($tmpFilePath)) {
            echo json_encode(['success' => false, 'message' => 'Upload failed']);
            return;
        }

        $ext = strtolower(pathinfo($_FILES['file_xlsx']['name'], PATHINFO_EXTENSION));
        if ($ext !== 'xlsx') {
            echo json_encode(['success' => false, 'message' => 'Only .xlsx files are supported']);
            return;
        }

        $tmpDir = TEMP_FOLDER . time() . uniqid() . '/';
        if (!file_exists(TEMP_FOLDER)) {
            mkdir(TEMP_FOLDER, 0755, true);
        }
        if (!mkdir($tmpDir, 0755, true) && !is_dir($tmpDir)) {
            echo json_encode(['success' => false, 'message' => 'Could not create temp directory. Check server write permissions.']);
            return;
        }
        $newFilePath = $tmpDir . basename($_FILES['file_xlsx']['name']);
        if (!move_uploaded_file($tmpFilePath, $newFilePath)) {
            @rmdir($tmpDir);
            echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file. Check server write permissions on the temp folder.']);
            return;
        }

        try {
            $xlsx       = new XLSXReader_fin($newFilePath);
            $sheetNames = $xlsx->getSheetNames();
            if (empty($sheetNames)) {
                throw new Exception('The Excel file contains no sheets.');
            }
            $cells = $xlsx->getSheet(reset($sheetNames))->getData();
        } catch (Exception $e) {
            @unlink($newFilePath);
            @rmdir($tmpDir);
            echo json_encode(['success' => false, 'message' => 'Could not read the Excel file: ' . $e->getMessage()]);
            return;
        }

        $update_existing = (int)$this->input->post('update_existing');
        $total   = 0;
        $success = 0;
        $errors  = [];

        // Row 0 is the header; data starts at row 1
        foreach ($cells as $row_index => $row) {
            if ($row_index === 0) continue;

            $group_name = isset($row[0]) ? trim((string)$row[0]) : '';
            if ($group_name === '') continue;

            $total++;

            $selection_type  = isset($row[1]) ? strtolower(trim((string)$row[1])) : 'single';
            if (!in_array($selection_type, ['single', 'multiple'])) {
                $selection_type = 'single';
            }
            $min_selections  = isset($row[2]) ? max(0, (int)$row[2]) : 0;
            $max_selections  = isset($row[3]) ? max(1, (int)$row[3]) : 1;

            // Collect options: columns 4-5, 6-7, 8-9, ..., 22-23 (10 option pairs)
            $options = [];
            for ($i = 0; $i < 10; $i++) {
                $name_col  = 4 + ($i * 2);
                $price_col = 5 + ($i * 2);
                $opt_name  = isset($row[$name_col]) ? trim((string)$row[$name_col]) : '';
                if ($opt_name === '') continue;
                $options[] = [
                    'name'             => $opt_name,
                    'price_adjustment' => isset($row[$price_col]) ? (float)$row[$price_col] : 0,
                ];
            }

            if (empty($options)) {
                $errors[] = "Row " . ($row_index + 1) . " – \"$group_name\": must have at least one option.";
                continue;
            }

            // Check if a group with this name already exists
            $existing = $this->db
                ->where('name', $group_name)
                ->get(db_prefix() . 'modifier_groups')
                ->row_array();

            if ($existing && !$update_existing) {
                $errors[] = "Row " . ($row_index + 1) . " – \"$group_name\": already exists (skipped).";
                continue;
            }

            $data = [
                'name'           => $group_name,
                'selection_type' => $selection_type,
                'min_selections' => $min_selections,
                'max_selections' => $max_selections,
                'active'         => 1,
                'options'        => $options,
            ];

            // Collect linked item SKUs: columns 24–28
            $linked_skus = [];
            for ($i = 0; $i < 5; $i++) {
                $sku = isset($row[24 + $i]) ? trim((string)$row[24 + $i]) : '';
                if ($sku !== '') {
                    $linked_skus[] = $sku;
                }
            }

            $existing_id = $existing ? $existing['id'] : null;
            $result = $this->pos_model->save_modifier_with_options($data, $existing_id);

            if ($result) {
                $success++;

                if (!empty($linked_skus)) {
                    $item_ids = [];
                    foreach ($linked_skus as $sku) {
                        $item = $this->db
                            ->where('sku_code', $sku)
                            ->get(db_prefix() . 'items')
                            ->row_array();
                        if ($item) {
                            $item_ids[] = $item['id'];
                        } else {
                            $errors[] = "Row " . ($row_index + 1) . " – \"$group_name\": item SKU \"$sku\" not found (skipped).";
                        }
                    }
                    if (!empty($item_ids)) {
                        $this->pos_model->assign_items_to_modifier_group($result, $item_ids);
                    }
                }
            } else {
                $errors[] = "Row " . ($row_index + 1) . " – \"$group_name\": failed to save.";
            }
        }

        @unlink($newFilePath);
        @rmdir($tmpDir);

        echo json_encode([
            'success' => true,
            'total'   => $total,
            'saved'   => $success,
            'failed'  => count($errors),
            'errors'  => $errors,
        ]);
    }

    // =========================================================================
    // Accounting Settings
    // =========================================================================

    public function ajax_save_accounting_settings()
    {
        if (!has_permission('pos', '', 'edit')) {
            ajax_access_denied();
        }
        $this->load->model('pos/pos_model');

        $mappings = json_decode($this->input->post('mappings') ?? '{}', true) ?: [];
        $result   = $this->pos_model->save_accounting_settings([
            'enabled'                 => $this->input->post('enabled'),
            'payment_method_accounts' => $mappings,
        ]);
        echo json_encode(['success' => $result]);
    }

    /**
     * Sync a single past shift to accounting.
     * POST: shift_id
     */
    public function ajax_sync_shift_accounting()
    {
        if (!has_permission('pos', '', 'edit')) {
            ajax_access_denied();
        }
        $this->load->model('pos/pos_model');

        $shift_id = (int)$this->input->post('shift_id');
        if (!$shift_id) {
            echo json_encode(['success' => false, 'message' => 'shift_id required']);
            return;
        }

        $journal_id = $this->pos_model->create_shift_accounting_entry($shift_id);
        if ($journal_id) {
            echo json_encode(['success' => true, 'journal_entry_id' => $journal_id]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Shift already synced, accounting disabled, or missing account mapping.']);
        }
    }

    /**
     * Bulk-sync all closed shifts that have no journal entry yet.
     */
    public function ajax_sync_all_shifts_accounting()
    {
        if (!has_permission('pos', '', 'edit')) {
            ajax_access_denied();
        }
        $this->load->model('pos/pos_model');

        $unsynced = $this->pos_model->get_unsynced_shifts();
        $synced   = 0;
        $failed   = 0;

        foreach ($unsynced as $shift) {
            $result = $this->pos_model->create_shift_accounting_entry($shift['id']);
            $result ? $synced++ : $failed++;
        }

        echo json_encode([
            'success' => true,
            'total'   => count($unsynced),
            'synced'  => $synced,
            'failed'  => $failed,
        ]);
    }

    // =========================================================================
    // Reports
    // =========================================================================

    private function _report_warehouses()
    {
        return $this->db
            ->select('warehouse_id, warehouse_name')
            ->where('display', 1)
            ->order_by('warehouse_name', 'ASC')
            ->get(db_prefix() . 'warehouse')->result_array();
    }

    // =========================================================================
    // CRM Promos & Bundles
    // =========================================================================

    public function promos()
    {
        redirect(admin_url('pos/products'));
    }

    public function promo_form($id = null)
    {
        redirect(admin_url('pos/products'));
    }

    public function ajax_save_promo()
    {
        if (!has_permission('pos', '', 'create') && !has_permission('pos', '', 'edit')) {
            ajax_access_denied();
        }
        $this->load->model('pos/pos_model');

        $id          = (int)$this->input->post('id') ?: null;
        $pos_item_id = (int)($this->input->post('pos_item_id') ?: 0);
        $name        = trim($this->input->post('name') ?: '');

        if (!$name && $pos_item_id) {
            $item = $this->db->select('sku_name')->get_where(db_prefix() . 'items', ['id' => $pos_item_id])->row_array();
            $name = $item ? $item['sku_name'] : '';
        }
        if (!$name) {
            echo json_encode(['success' => false, 'message' => 'A linked product or name is required']);
            return;
        }

        $components    = $this->input->post('components') ?: [];
        $bundle_groups = $this->input->post('bundle_groups') ?: [];

        $data = [
            'name'          => $name,
            'type'          => $this->input->post('type') ?: 'promo',
            'pos_item_id'   => $pos_item_id ?: null,
            'description'   => $this->input->post('description') ?: null,
            'discount_type' => $this->input->post('discount_type') ?: null,
            'discount_value'=> $this->input->post('discount_value') ?: 0,
            'active'        => (int)(bool)$this->input->post('active'),
            'components'    => is_array($components) ? $components : [],
            'bundle_groups' => is_array($bundle_groups) ? $bundle_groups : [],
        ];

        $result = $this->pos_model->save_crm_promo($data, $id);
        echo json_encode(['success' => (bool)$result, 'id' => $result]);
    }

    public function ajax_get_promo($id)
    {
        if (!has_permission('pos', '', 'view')) {
            ajax_access_denied();
        }
        $this->load->model('pos/pos_model');
        $promo = $this->pos_model->get_crm_promo($id);
        echo json_encode(['success' => (bool)$promo, 'data' => $promo]);
    }

    public function ajax_delete_promo($id)
    {
        if (!has_permission('pos', '', 'delete')) {
            ajax_access_denied();
        }
        $this->load->model('pos/pos_model');
        echo json_encode(['success' => $this->pos_model->delete_crm_promo($id)]);
    }

    public function ajax_delete_promos_bulk()
    {
        if (!has_permission('pos', '', 'delete')) {
            ajax_access_denied();
        }
        $this->load->model('pos/pos_model');
        $ids = $this->input->post('ids');
        if (empty($ids) || !is_array($ids)) {
            echo json_encode(['success' => false, 'message' => 'No items selected']);
            return;
        }
        echo json_encode(['success' => $this->pos_model->delete_crm_promos_bulk($ids)]);
    }

    // =========================================================================
    // Reports
    // =========================================================================

    public function reports()
    {
        if (!has_permission('pos', '', 'view')) {
            access_denied('pos');
        }
        redirect(admin_url('pos/reports/sales'));
    }

    public function reports_sales()
    {
        if (!has_permission('pos', '', 'view')) {
            access_denied('pos');
        }
        $this->load->model('pos/pos_model');
        $data['title']      = 'Reports — Sales';
        $data['active_tab'] = 'sales';
        $data['warehouses'] = $this->_report_warehouses();
        $this->load->view('pos/admin/reports/sales', $data);
    }

    public function reports_products()
    {
        if (!has_permission('pos', '', 'view')) {
            access_denied('pos');
        }
        $this->load->model('pos/pos_model');
        $data['title']               = 'Reports — Products';
        $data['active_tab']          = 'products';
        $data['warehouses']          = $this->_report_warehouses();
        $data['product_categories']  = $this->pos_model->get_sub_groups();
        $this->load->view('pos/admin/reports/products', $data);
    }

    public function reports_payments()
    {
        if (!has_permission('pos', '', 'view')) {
            access_denied('pos');
        }
        $this->load->model('pos/pos_model');
        $data['title']      = 'Reports — Payment Modes';
        $data['active_tab'] = 'payments';
        $data['warehouses'] = $this->_report_warehouses();
        $this->load->view('pos/admin/reports/payments', $data);
    }

    public function reports_txn_types()
    {
        if (!has_permission('pos', '', 'view')) {
            access_denied('pos');
        }
        $this->load->model('pos/pos_model');
        $data['title']      = 'Reports — Transaction Types';
        $data['active_tab'] = 'txn_types';
        $data['warehouses'] = $this->_report_warehouses();
        $this->load->view('pos/admin/reports/txn_types', $data);
    }

    public function reports_modifiers()
    {
        if (!has_permission('pos', '', 'view')) {
            access_denied('pos');
        }
        $this->load->model('pos/pos_model');
        $data['title']      = 'Reports — Modifiers';
        $data['active_tab'] = 'modifiers';
        $data['warehouses'] = $this->_report_warehouses();
        $this->load->view('pos/admin/reports/modifiers', $data);
    }

    public function reports_promos()
    {
        if (!has_permission('pos', '', 'view')) {
            access_denied('pos');
        }
        $this->load->model('pos/pos_model');
        $data['title']      = 'Reports — Promo & Bundles';
        $data['active_tab'] = 'promos';
        $data['warehouses'] = $this->_report_warehouses();
        $this->load->view('pos/admin/reports/promos', $data);
    }

    public function reports_cost_profit()
    {
        if (!has_permission('pos', '', 'view')) {
            access_denied('pos');
        }
        $this->load->model('pos/pos_model');
        $data['title']               = 'Reports — Cost & Profit';
        $data['active_tab']          = 'cost_profit';
        $data['warehouses']          = $this->_report_warehouses();
        $data['product_categories']  = $this->pos_model->get_sub_groups();
        $this->load->view('pos/admin/reports/cost_profit', $data);
    }

    // =========================================================================
    // Recipe & Costing
    // =========================================================================

    public function costing_products()
    {
        if (!has_permission('pos', '', 'view')) {
            access_denied('pos');
        }
        $this->load->model('pos/pos_model');

        $filters = [
            'category_id'            => $this->input->get('category_id'),
            'search'                 => $this->input->get('search'),
            'item_type'              => $this->input->get('item_type'),
            'purchase_inventory_only'=> true,
            'exclude_packaging'      => true,
        ];

        $data['title']       = 'Individual Ingredients Cost';
        $data['items']       = $this->pos_model->get_items_for_costing($filters);
        $data['item_groups'] = $this->pos_model->get_item_groups();
        $this->load->view('pos/admin/costing/products', $data);
    }

    private function _costing_tabs($active)
    {
        return [
            'active_tab' => $active,
            '_tabs'      => [
                'product'     => ['label' => 'Product Cost Profit',        'href' => admin_url('pos/costing_product_cost_profit?tab=product')],
                'ingredients' => ['label' => 'Individual Ingredients Cost','href' => admin_url('pos/costing_product_cost_profit?tab=ingredients')],
                'mixed'       => ['label' => 'Mixed Ingredients Cost',     'href' => admin_url('pos/costing_product_cost_profit?tab=mixed')],
                'packaging'   => ['label' => 'Packaging Cost',             'href' => admin_url('pos/costing_product_cost_profit?tab=packaging')],
                'yield'       => ['label' => 'Ingredient Yield',           'href' => admin_url('pos/costing_product_cost_profit?tab=yield')],
                'history'     => ['label' => 'Cost History',               'href' => admin_url('pos/costing_snapshots')],
            ],
        ];
    }

    public function costing_product_cost_profit()
    {
        if (!has_permission('pos', '', 'view')) {
            access_denied('pos');
        }
        $this->load->model('pos/pos_model');

        $tab = (string)$this->input->get('tab');
        if (!in_array($tab, ['product', 'ingredients', 'mixed', 'packaging', 'yield'], true)) {
            $tab = 'product';
        }

        $data = $this->_costing_tabs($tab);

        if ($tab === 'product') {
            $filters = [
                'category_id' => $this->input->get('category_id'),
                'search'      => $this->input->get('search'),
            ];
            $data['title']            = 'Product Cost Profit';
            // Loaded before the summary query below so any ingredient/packaging cost
            // changes picked up here (see get_items_for_costing()) are already
            // persisted and reflected in the products' BOM totals on this same render.
            $data['mixed_items']      = $this->_costing_option_list('mixed');
            $data['ingredient_items'] = $this->_costing_option_list('ingredients');
            $data['packaging_items']  = $this->_costing_option_list('packaging');
            $data['items']            = $this->pos_model->get_product_cost_profit_summary($filters);
            $data['sub_groups']       = $this->pos_model->get_sub_groups();
            $data['product_list']     = $this->db
                ->select('id, sku_code, sku_name')
                ->from(db_prefix() . 'items')
                ->where('parent_id IS NULL', null, false)
                ->where('active', 1)
                ->where('can_be_sold', 'can_be_sold')
                ->order_by('sku_name', 'ASC')
                ->get()->result_array();
            $this->load->view('pos/admin/costing/product_cost_profit', $data);
            return;
        }

        if ($tab === 'ingredients') {
            $filters = [
                'category_id'            => $this->input->get('category_id'),
                'search'                 => $this->input->get('search'),
                'item_type'              => $this->input->get('item_type'),
                'purchase_inventory_only'=> true,
                'exclude_packaging'      => true,
            ];
            $data['title']       = 'Individual Ingredients Cost';
            $data['items']       = $this->pos_model->get_items_for_costing($filters);
            $data['item_groups'] = $this->pos_model->get_item_groups();
            $this->load->view('pos/admin/costing/products', $data);
            return;
        }

        if ($tab === 'mixed') {
            $data['title']            = 'Mixed Ingredients Cost';
            $data['mixed']            = $this->pos_model->get_mixed_cost_summary([
                'search' => $this->input->get('search'),
            ]);
            $data['ingredient_items'] = $this->_costing_option_list('ingredients');
            $data['uoms']             = $this->pos_model->get_uoms();
            $this->load->view('pos/admin/costing/mixed', $data);
            return;
        }

        if ($tab === 'yield') {
            $data['title']            = 'Ingredient Yield';
            $data['yields']           = $this->pos_model->get_item_yield_summary([
                'search' => $this->input->get('search'),
            ]);
            $data['ingredient_items'] = $this->_costing_option_list('ingredients');
            $this->load->view('pos/admin/costing/yield', $data);
            return;
        }

        $filters = [
            'category_id'       => $this->input->get('category_id'),
            'search'            => $this->input->get('search'),
            'packaging_only'    => true,
        ];
        $data['title']                 = 'Packaging Cost';
        $data['items']                 = $this->pos_model->get_items_for_costing($filters);
        $data['force_category_label']  = 'Packaging';
        $data['hide_category_filter']  = true;
        $this->load->view('pos/admin/costing/products', $data);
    }

    private function _costing_option_list($section)
    {
        if ($section === 'mixed') {
            $rows = $this->pos_model->get_mixed_cost_summary();
            return array_map(function ($r) {
                return [
                    'id'            => (int)$r['item_id'],
                    'sku_code'      => (string)($r['sku_code'] ?? ''),
                    'sku_name'      => (string)($r['sku_name'] ?? ''),
                    'cost_per_unit' => (float)($r['cost_per_unit'] ?? 0),
                ];
            }, $rows);
        }

        $filters = $section === 'packaging'
            ? ['packaging_only' => true]
            : ['purchase_inventory_only' => true, 'exclude_packaging' => true];

        $rows = $this->pos_model->get_items_for_costing($filters);
        return array_map(function ($r) {
            return [
                'id'            => (int)$r['id'],
                'sku_code'      => (string)($r['sku_code'] ?? ''),
                'sku_name'      => (string)($r['sku_name'] ?? ''),
                'cost_per_unit' => (float)($r['cost_per_unit_fallback'] ?? 0),
            ];
        }, $rows);
    }

    public function costing_snapshots()
    {
        if (!has_permission('pos', '', 'view')) {
            access_denied('pos');
        }
        $data = $this->_costing_tabs('history');
        $data['title']      = 'Cost History';
        $data['snapshots']  = $this->db
            ->order_by('snapshot_date', 'DESC')
            ->order_by('id', 'DESC')
            ->get(db_prefix() . 'pos_cost_snapshots')
            ->result_array();
        $this->load->view('pos/admin/costing/snapshots', $data);
    }

    public function costing_mixed()
    {
        if (!has_permission('pos', '', 'view')) {
            access_denied('pos');
        }
        $this->load->model('pos/pos_model');
        $data['title']            = 'Mixed Ingredients Cost';
        $data['mixed']            = $this->pos_model->get_mixed_cost_summary([
            'search' => $this->input->get('search'),
        ]);
        $data['ingredient_items'] = $this->_costing_option_list('ingredients');
        $data['uoms']             = $this->pos_model->get_uoms();
        $this->load->view('pos/admin/costing/mixed', $data);
    }

    public function costing_packaging()
    {
        if (!has_permission('pos', '', 'view')) {
            access_denied('pos');
        }
        $this->load->model('pos/pos_model');
        $filters = [
            'category_id'       => $this->input->get('category_id'),
            'search'            => $this->input->get('search'),
            'packaging_only'    => true,
        ];
        $data['title']                = 'Packaging Cost';
        $data['items']                = $this->pos_model->get_items_for_costing($filters);
        $data['force_category_label'] = 'Packaging';
        $data['hide_category_filter'] = true;
        $this->load->view('pos/admin/costing/products', $data);
    }

    public function ajax_recalc_costs()
    {
        if (!has_permission('pos', '', 'edit')) {
            ajax_access_denied();
        }
        if (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        try {
            $create_snap = (bool)$this->input->post('create_snapshot');
            $snap_name   = trim($this->input->post('snapshot_name') ?: '');
            $this->load->model('pos/pos_model');
            $result = $this->pos_model->recalculate_all_costs([
                'create_snapshot' => $create_snap,
                'snapshot_name'   => $snap_name,
            ]);
            echo json_encode(['success' => true, 'result' => $result]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function ajax_save_item_cost()
    {
        if (!has_permission('pos', '', 'edit')) {
            ajax_access_denied();
        }
        if (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        try {
            $item_id         = (int)$this->input->post('item_id');
            $purchase_price  = $this->input->post('purchase_price');
            $batch_size      = $this->input->post('batch_size');
            $units_per_batch = $this->input->post('units_per_batch');
            $unit_uom        = $this->input->post('unit_uom');
            $batch_uom       = $this->input->post('batch_uom');
            $item_type       = $this->input->post('item_type');

            if (!$item_id) {
                echo json_encode(['success' => false, 'message' => 'Invalid item ID']);
                return;
            }

            $update = [];
            if ($purchase_price !== null)  $update['purchase_price']  = $purchase_price;
            if ($batch_size !== null)      $update['batch_size']      = $batch_size;
            if ($units_per_batch !== null) $update['units_per_batch'] = $units_per_batch;
            if ($unit_uom !== null)        $update['unit_uom']        = $unit_uom;
            if ($batch_uom !== null)       $update['batch_uom']       = $batch_uom;
            if ($item_type !== null)       $update['item_type']       = $item_type;

            if (!empty($update)) {
                $this->db->where('id', $item_id)->update(db_prefix() . 'items', $update);
            }

            $this->load->model('pos/pos_model');
            $prev_row = $this->db->select('cached_cost_per_unit')->where('id', $item_id)->get(db_prefix() . 'items')->row_array();
            $prev_cost = ($prev_row && $prev_row['cached_cost_per_unit'] !== null) ? round((float) $prev_row['cached_cost_per_unit'], 4) : null;

            $unit_cost = $this->pos_model->get_item_unit_cost($item_id, true);

            if ($prev_cost === null || abs($prev_cost - round((float) $unit_cost, 4)) > 0.00005) {
                $this->pos_model->propagate_cost_change($item_id);
            }

            echo json_encode([
                'success'       => true,
                'cost_per_unit' => $unit_cost,
                'cached_cost'   => $unit_cost,
            ]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage(), 'trace' => $e->getFile() . ':' . $e->getLine()]);
        }
    }

    public function ajax_get_product_cost_profit_detail()
    {
        if (!has_permission('pos', '', 'view')) {
            ajax_access_denied();
        }
        if (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        try {
            $item_id = (int)$this->input->post('item_id');
            $this->load->model('pos/pos_model');
            echo json_encode([
                'success' => true,
                'data'    => $this->pos_model->get_product_cost_profit_detail($item_id),
            ]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function ajax_save_product_cost_profit_detail()
    {
        if (!has_permission('pos', '', 'edit')) {
            ajax_access_denied();
        }
        if (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        try {
            $item_id = (int)$this->input->post('item_id');
            $sections = $this->input->post('sections');
            if (!is_array($sections)) {
                $sections = json_decode((string)$sections, true);
            }
            if (!is_array($sections)) {
                $sections = [];
            }
            $instructions = $this->input->post('instructions');
            $this->load->model('pos/pos_model');
            $data = $this->pos_model->save_product_cost_profit_detail($item_id, $sections, $instructions);
            echo json_encode(['success' => true, 'data' => $data]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function ajax_get_mixed_cost_detail()
    {
        if (!has_permission('pos', '', 'view')) {
            ajax_access_denied();
        }
        if (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        try {
            $mixed_id = (int)$this->input->post('mixed_id');
            $this->load->model('pos/pos_model');
            echo json_encode([
                'success' => true,
                'data'    => $this->pos_model->get_mixed_cost_detail($mixed_id),
            ]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function ajax_save_mixed_cost_detail()
    {
        if (!has_permission('pos', '', 'edit')) {
            ajax_access_denied();
        }
        if (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        try {
            $mixed_id = (int)$this->input->post('mixed_id');
            // XSS filtering must be skipped here: this field is a JSON blob that embeds
            // TinyMCE HTML (instructions), and CI's global xss_clean() mangles tags/quotes
            // inside it, corrupting the JSON so json_decode() silently returns null and
            // every field (including item_name) reads back empty.
            $payload  = $this->input->post('payload', false);
            if (!is_array($payload)) {
                $payload = json_decode((string)$payload, true);
            }
            if (!is_array($payload)) {
                $payload = [];
            }
            $this->load->model('pos/pos_model');
            $data = $this->pos_model->save_mixed_cost_detail($mixed_id, $payload);
            echo json_encode(['success' => true, 'data' => $data]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    // Powers the "Yield" tab on the Inventory item edit modal (modules/warehouse) —
    // lets one purchased item (e.g. "Coconut Fruit") be broken down into derived
    // items (e.g. "Coconut Juice", "Coconut Meat") whose cost/unit is computed from
    // the source's cost instead of their own purchase price.
    public function ajax_get_item_yields()
    {
        if (!has_permission('pos', '', 'view')) {
            ajax_access_denied();
        }
        if (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        try {
            $item_id = (int)$this->input->post('item_id');
            $this->load->model('pos/pos_model');
            $data = $this->pos_model->get_item_yields($item_id);
            // Deliberately no 'purchase_inventory_only' filter here (unlike Mixed
            // Ingredient's dropdown): a yield output just needs to be a regular active
            // item, and bulk-imported items often never get the can_be_purchased /
            // can_be_inventory flags the manual "Add Item" form always sets.
            $candidates = $this->pos_model->get_items_for_costing();
            $data['candidate_items'] = array_values(array_map(function ($r) {
                return [
                    'id'       => (int)$r['id'],
                    'sku_code' => (string)($r['sku_code'] ?? ''),
                    'sku_name' => (string)($r['sku_name'] ?? ''),
                    'unit_uom' => (string)($r['unit_uom'] ?? ''),
                ];
            }, array_filter($candidates, function ($r) use ($item_id) {
                return (int)$r['id'] !== $item_id;
            })));
            echo json_encode(['success' => true, 'data' => $data]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function ajax_save_item_yields()
    {
        if (!has_permission('pos', '', 'edit')) {
            ajax_access_denied();
        }
        if (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        try {
            $item_id = (int)$this->input->post('item_id');
            $enabled = (bool)$this->input->post('has_yield_breakdown');
            // Must skip XSS filtering here: this field is a JSON blob, and CI's global
            // xss_clean() can mangle quotes/structure inside it, silently corrupting
            // the JSON so it decodes to null and the save looks like it "succeeded"
            // with zero rows — the same class of bug already hit the Mixed Ingredient
            // "payload" field (see ajax_save_mixed_cost_detail()).
            $rawRows = $this->input->post('item_yields', false);
            $rows    = $rawRows;
            if (!is_array($rows)) {
                $rows = json_decode((string)$rows, true);
            }
            if (!is_array($rows)) {
                if (trim((string)$rawRows) !== '' && trim((string)$rawRows) !== '[]') {
                    throw new Exception('Could not read the derived items list — please try saving again.');
                }
                $rows = [];
            }
            $this->load->model('pos/pos_model');
            $data = $this->pos_model->save_item_yields($item_id, $enabled, $rows);
            echo json_encode(['success' => true, 'data' => $data]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function ajax_save_mixed_ingredient()
    {
        if (!has_permission('pos', '', 'create') && !has_permission('pos', '', 'edit')) {
            ajax_access_denied();
        }
        if (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        try {
            $id            = (int)$this->input->post('id') ?: null;
            $item_id       = (int)$this->input->post('item_id');
            $yield         = $this->input->post('yield');
            $yield_uom     = $this->input->post('yield_uom');
            $prep_min      = $this->input->post('prep_minutes');
            $instructions  = trim($this->input->post('instructions') ?: '');
            $components    = $this->input->post('components') ?: [];

            if (!$item_id) {
                echo json_encode(['success' => false, 'message' => 'Item is required']);
                return;
            }
            if (!is_array($components)) {
                $components = [];
            }

            $mixed_data = [
                'item_id'      => $item_id,
                'yield'        => $yield ?: 1,
                'yield_uom'    => $yield_uom ?: null,
                'prep_minutes' => $prep_min ?: 0,
                'instructions' => $instructions,
            ];

            if ($id) {
                $this->db->where('id', $id)->update(db_prefix() . 'pos_mixed_ingredients', $mixed_data);
            } else {
                $mixed_data['created_at'] = date('Y-m-d H:i:s');
                $this->db->insert(db_prefix() . 'pos_mixed_ingredients', $mixed_data);
                $id = $this->db->insert_id();
            }

            $this->db->where('mixed_ingredient_id', $id)
                ->delete(db_prefix() . 'pos_mixed_components');

            foreach ($components as $idx => $comp) {
                $comp_item_id = (int)($comp['component_item_id'] ?? 0);
                $comp_type    = trim($comp['component_type'] ?? 'item');
                $comp_qty     = $comp['quantity'] ?? 0;
                $comp_uom     = $comp['uom'] ?? null;
                $comp_note    = trim($comp['note'] ?? '');
                if ($comp_item_id > 0) {
                    $this->db->insert(db_prefix() . 'pos_mixed_components', [
                        'mixed_ingredient_id' => $id,
                        'component_type'      => $comp_type,
                        'component_item_id'   => $comp_item_id,
                        'quantity'            => $comp_qty,
                        'uom'                 => $comp_uom,
                        'note'                => $comp_note,
                        'sort_order'          => $idx,
                    ]);
                }
            }

            $this->load->model('pos/pos_model');
            $new_cost = $this->pos_model->calc_mixed_ingredient_cost($id);

            echo json_encode([
                'success'          => true,
                'id'               => $id,
                'new_cost_per_unit'=> $new_cost,
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function ajax_report_data()
    {
        if (!has_permission('pos', '', 'view')) {
            ajax_access_denied();
        }
        if (ob_get_level()) ob_end_clean();
        ob_start();
        header('Content-Type: application/json');

        try {
            $this->load->model('pos/pos_model');

            $section      = $this->input->post('section')      ?: 'sales';
            $date_from    = $this->input->post('date_from')    ?: date('Y-m-d');
            $date_to      = $this->input->post('date_to')      ?: date('Y-m-d');
            $warehouse_id = $this->input->post('warehouse_id') ?: null;
            $group_by     = in_array($this->input->post('group_by'), ['daily','hourly','hourly_by_day','dow','weekly','monthly'])
                            ? $this->input->post('group_by') : 'daily';

            $out = ['success' => true, 'section' => $section, 'group_by' => $group_by,
                    'date_from' => $date_from, 'date_to' => $date_to];

            switch ($section) {
                case 'sales':
                    $out['summary'] = $this->pos_model->get_report_sales_summary($date_from, $date_to, $warehouse_id);
                    $out['trend']   = $this->pos_model->get_report_sales_trend($date_from, $date_to, $warehouse_id, $group_by);
                    $out['dow']     = $this->pos_model->get_report_sales_dow($date_from, $date_to, $warehouse_id);
                    // Previous period — same length, immediately before the current range
                    $days = (int)max(1, round((strtotime($date_to) - strtotime($date_from)) / 86400) + 1);
                    $prev_to   = date('Y-m-d', strtotime($date_from . ' -1 day'));
                    $prev_from = date('Y-m-d', strtotime($date_from . ' -' . $days . ' days'));
                    $out['prev_summary']   = $this->pos_model->get_report_sales_summary($prev_from, $prev_to, $warehouse_id);
                    $out['prev_date_from'] = $prev_from;
                    $out['prev_date_to']   = $prev_to;
                    break;
                case 'products':
                    $filters = [
                        'category_id'    => $this->input->post('category_id'),
                        'product_search' => $this->input->post('product_search'),
                    ];
                    $out['product_trend']     = $this->pos_model->get_report_products_top_trend($date_from, $date_to, $warehouse_id, $group_by, 10, $filters);
                    $out['product_trend_all'] = $this->pos_model->get_report_products_all_trend($date_from, $date_to, $warehouse_id, $group_by, $filters);
                    $out['category_trend']    = $this->pos_model->get_report_products_category_trend($date_from, $date_to, $warehouse_id, $group_by, $filters);
                    $out['top_by_revenue']    = $this->pos_model->get_report_products_top($date_from, $date_to, $warehouse_id, 50, $filters);
                    $out['by_category']       = $this->pos_model->get_report_products_by_category($date_from, $date_to, $warehouse_id, $filters);
                    $out['receipt_count']     = $this->pos_model->get_report_products_receipt_count($date_from, $date_to, $warehouse_id, $filters);
                    // Previous period for comparison
                    $p_days     = (int)max(1, round((strtotime($date_to) - strtotime($date_from)) / 86400) + 1);
                    $p_prev_to  = date('Y-m-d', strtotime($date_from . ' -1 day'));
                    $p_prev_from = date('Y-m-d', strtotime($date_from . ' -' . $p_days . ' days'));
                    $out['prev_by_category']   = $this->pos_model->get_report_products_by_category($p_prev_from, $p_prev_to, $warehouse_id, $filters);
                    $out['prev_receipt_count'] = $this->pos_model->get_report_products_receipt_count($p_prev_from, $p_prev_to, $warehouse_id, $filters);
                    $out['prev_date_from']     = $p_prev_from;
                    $out['prev_date_to']       = $p_prev_to;
                    break;
                case 'payments':
                    $out['trend']     = $this->pos_model->get_report_payments_trend($date_from, $date_to, $warehouse_id, $group_by);
                    $out['breakdown'] = $this->pos_model->get_report_payments_breakdown($date_from, $date_to, $warehouse_id);
                    break;
                case 'txn_types':
                    $out['by_type'] = $this->pos_model->get_report_txn_types($date_from, $date_to, $warehouse_id);
                    $out['trend']   = $this->pos_model->get_report_txn_types_trend($date_from, $date_to, $warehouse_id, $group_by);
                    break;
                case 'modifiers':
                    $out['summary']       = $this->pos_model->get_report_modifiers_summary($date_from, $date_to, $warehouse_id);
                    $out['top_modifiers'] = $this->pos_model->get_report_modifiers_top($date_from, $date_to, $warehouse_id);
                    $out['by_group']      = $this->pos_model->get_report_modifier_groups($date_from, $date_to, $warehouse_id);
                    break;
                case 'item_detail':
                    $item_id = (int)$this->input->post('item_id');
                    if (!$item_id) { $out = ['success' => false, 'error' => 'item_id is required']; break; }
                    $out['transactions']   = $this->pos_model->get_item_transaction_detail($item_id, $date_from, $date_to, $warehouse_id);
                    $out['modifier_usage'] = $this->pos_model->get_item_modifier_usage($item_id, $date_from, $date_to, $warehouse_id);
                    break;
                case 'modifier_detail':
                    $modifier_id = (int)$this->input->post('modifier_id');
                    if (!$modifier_id) { $out = ['success' => false, 'error' => 'modifier_id is required']; break; }
                    $out['transactions'] = $this->pos_model->get_modifier_transaction_detail($modifier_id, $date_from, $date_to, $warehouse_id);
                    $out['co_items']     = $this->pos_model->get_modifier_co_items($modifier_id, $date_from, $date_to, $warehouse_id);
                    break;
                case 'promos':
                    $out['summary'] = $this->pos_model->get_report_crm_promos_summary($date_from, $date_to, $warehouse_id);
                    $detail         = $this->pos_model->get_report_crm_promos_detail($date_from, $date_to, $warehouse_id);
                    $feasibility    = $this->pos_model->get_report_crm_promo_feasibility($date_from, $date_to, $warehouse_id);
                    $feas_map       = [];
                    foreach ($feasibility as $f) { $feas_map[(int)$f['id']] = $f; }
                    foreach ($detail as &$d) {
                        $f = $feas_map[(int)$d['promo_id']] ?? [];
                        $d['selling_price']  = $f['selling_price']  ?? 0;
                        $d['alacarte_min']   = $f['alacarte_min']   ?? 0;
                        $d['alacarte_max']   = $f['alacarte_max']   ?? 0;
                        $d['alacarte_value'] = $f['alacarte_value'] ?? 0;
                        $d['savings_per_use']= $f['savings_per_use']?? 0;
                        $d['savings_pct']    = $f['savings_pct']    ?? 0;
                        $d['total_savings']  = $f['total_savings']  ?? 0;
                    }
                    unset($d);
                    $out['detail'] = $detail;
                    $out['trend']  = $this->pos_model->get_report_crm_promo_trend($date_from, $date_to, $warehouse_id, $group_by);
                    break;
                case 'promo_detail':
                    $promo_id = (int)$this->input->post('promo_id');
                    if (!$promo_id) { $out = ['success' => false, 'error' => 'promo_id is required']; break; }
                    $out = array_merge($out, $this->pos_model->get_report_crm_promo_components_usage($promo_id, $date_from, $date_to, $warehouse_id));
                    break;
                case 'cost_profit':
                    $category_id    = $this->input->post('category_id');
                    $product_search = $this->input->post('product_search');
                    $summary = $this->pos_model->get_profit_report_summary($date_from, $date_to, $warehouse_id, $category_id, $product_search, $group_by);
                    $out['by_product']       = $summary['by_product']       ?? [];
                    $out['by_category']      = $summary['by_category']      ?? [];
                    $out['product_trend_all']= $summary['product_trend_all']?? [];
                    $out['grand_totals']     = $summary['grand_totals']     ?? [];
                    $out['receipt_count']    = $summary['receipt_count']    ?? 0;
                    $p_days     = (int)max(1, round((strtotime($date_to) - strtotime($date_from)) / 86400) + 1);
                    $p_prev_to  = date('Y-m-d', strtotime($date_from . ' -1 day'));
                    $p_prev_from = date('Y-m-d', strtotime($date_from . ' -' . $p_days . ' days'));
                    $prevSummary = $this->pos_model->get_profit_report_summary($p_prev_from, $p_prev_to, $warehouse_id, $category_id, $product_search, $group_by);
                    $out['prev_grand_totals']= $prevSummary['grand_totals'] ?? [];
                    $out['prev_by_product']  = $prevSummary['by_product']   ?? [];
                    $out['prev_date_from']   = $p_prev_from;
                    $out['prev_date_to']     = $p_prev_to;
                    $out['catalog_now'] = $this->db->select('id, sku_name, sku_code, rate AS selling_price, cached_cost_per_unit AS current_cost_per_unit, item_type, purchase_price, units_per_batch, last_cost_update')->from(db_prefix().'items')->where_in('item_type', ['finished_product','combo','mixed_ingredient'])->where('parent_id IS NULL')->order_by('sku_name')->get()->result_array();
                    break;
                default:
                    $out = ['success' => false, 'error' => 'Unknown report section'];
            }

            echo json_encode($out);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    // =========================================================================
    // AI Assistant
    // =========================================================================

    public function ai_chat()
    {
        if (!has_permission('pos', '', 'view')) { show_404(); }
        $this->load->model('loyalty/loyalty_model');
        $data['gemini_key'] = get_option('gemini_api_key');
        $this->load->view('pos/admin/ai_chat', $data);
    }

    public function ajax_save_ai_settings()
    {
        if (!has_permission('pos', '', 'edit')) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            return;
        }
        if ($this->input->server('REQUEST_METHOD') !== 'POST') { show_404(); }

        $key = trim($this->input->post('gemini_api_key') ?: '');
        update_option('gemini_api_key', $key);
        echo json_encode(['success' => true]);
    }

    public function ajax_ai_chat()
    {
        if (!has_permission('pos', '', 'view')) {
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            return;
        }
        if ($this->input->server('REQUEST_METHOD') !== 'POST') { show_404(); }
        if (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');

        $api_key = get_option('gemini_api_key');
        if (!$api_key) {
            echo json_encode(['success' => false, 'error' => 'Gemini API key not configured.']);
            return;
        }

        $message = trim($this->input->post('message') ?: '');
        $history = json_decode($this->input->post('history') ?: '[]', true);
        $context = trim($this->input->post('context') ?: '');

        if ($message === '') {
            echo json_encode(['success' => false, 'error' => 'Empty message.']);
            return;
        }

        $this->load->model('pos/pos_model');
        $this->load->model('loyalty/loyalty_model');

        $contents = [];
        foreach ((array)$history as $turn) {
            $role = $turn['role'] === 'model' ? 'model' : 'user';
            $contents[] = ['role' => $role, 'parts' => [['text' => $turn['text']]]];
        }
        $contents[] = ['role' => 'user', 'parts' => [['text' => $message]]];

        $system_text = $this->_ai_system_prompt($context);
        $tools       = $this->_ai_tool_definitions();

        $tool_calls  = [];
        $final_text  = null;

        for ($i = 0; $i < 6; $i++) {
            $resp = $this->_gemini_request($api_key, $contents, $tools, $system_text);

            if (isset($resp['error'])) {
                $err_msg = $resp['error']['message'] ?? 'Gemini API error';
                $status  = $resp['error']['status'] ?? '';
                if ($status === 'RESOURCE_EXHAUSTED' || stripos($err_msg, 'quota') !== false) {
                    $err_msg = 'Gemini quota exhausted. Please enable billing at aistudio.google.com to continue using the AI Assistant.';
                }
                echo json_encode(['success' => false, 'error' => $err_msg]);
                return;
            }

            $all_parts = $resp['candidates'][0]['content']['parts'] ?? [];
            if (empty($all_parts)) {
                echo json_encode(['success' => false, 'error' => 'Empty response from Gemini.']);
                return;
            }

            // Scan all parts — thinking models emit a thought part before the functionCall
            $fn_part   = null;
            $text_part = null;
            foreach ($all_parts as $p) {
                if (!$fn_part   && isset($p['functionCall'])) { $fn_part   = $p; }
                if (!$text_part && isset($p['text']))         { $text_part = $p; }
            }

            if ($text_part && !$fn_part) {
                $final_text = $text_part['text'];
                break;
            }

            if ($fn_part) {
                $fn_name   = $fn_part['functionCall']['name'];
                $fn_args   = $fn_part['functionCall']['args'] ?? [];
                $fn_result = $this->_execute_ai_tool($fn_name, $fn_args);
                $tool_calls[] = $fn_name;

                // Echo back ALL parts (includes thought_signature required by thinking models)
                $contents[] = [
                    'role'  => 'model',
                    'parts' => $all_parts,
                ];
                $contents[] = [
                    'role'  => 'user',
                    'parts' => [['functionResponse' => [
                        'name'     => $fn_name,
                        'response' => ['result' => $fn_result],
                    ]]],
                ];
                continue;
            }

            break;
        }

        if ($final_text === null) {
            echo json_encode(['success' => false, 'error' => 'Could not get a text response from Gemini.']);
            return;
        }

        echo json_encode([
            'success'    => true,
            'reply'      => $final_text,
            'tool_calls' => $tool_calls,
        ]);
    }

    private function _ai_system_prompt($user_context = '')
    {
        $today    = date('l, d F Y');
        $ctx_line = $user_context ? "\n\nUser-provided context about upcoming events or conditions:\n{$user_context}" : '';

        return "You are a smart business intelligence assistant for a Malaysian F&B brand running a loyalty and POS system. "
             . "Today is {$today}. Currency is Malaysian Ringgit (RM). "
             . "You have access to real-time sales and loyalty data via tools — always call the relevant tool(s) before answering questions about numbers, trends, or performance. "
             . "When forecasting or giving recommendations, factor in Malaysian calendar context: Ramadan, Hari Raya, Chinese New Year, Deepavali, school holidays, and public holidays. "
             . "Keep responses concise, use bullet points for clarity, and end with one actionable recommendation when relevant."
             . $ctx_line;
    }

    private function _ai_tool_definitions()
    {
        return [[
            'function_declarations' => [
                [
                    'name'        => 'get_sales_summary',
                    'description' => 'Returns overall sales metrics for a date range: gross/net sales, discounts, tax, refunds, transaction count, average transaction value, items sold, loyalty points earned/redeemed.',
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
            ],
        ]];
    }

    private function _execute_ai_tool($name, $args)
    {
        $from = $args['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
        $to   = $args['date_to']   ?? date('Y-m-d');
        $wh   = !empty($args['warehouse_id']) ? (int)$args['warehouse_id'] : null;

        switch ($name) {
            case 'get_sales_summary':
                return $this->pos_model->get_report_sales_summary($from, $to, $wh);

            case 'get_sales_trend':
                $group = in_array($args['group_by'] ?? '', ['daily','weekly','monthly'])
                    ? $args['group_by'] : 'daily';
                $rows = $this->pos_model->get_report_sales_trend($from, $to, $wh, $group);
                return array_slice($rows, 0, 60);

            case 'get_top_products':
                $limit = min(15, max(1, (int)($args['limit'] ?? 10)));
                return $this->pos_model->get_report_products_top($from, $to, $wh, $limit);

            case 'get_customer_summary':
                return $this->pos_model->get_report_customers_summary($from, $to, $wh);

            case 'get_customer_retention':
                return $this->pos_model->get_report_customer_retention($from, $to, $wh);

            case 'get_top_customers':
                return $this->pos_model->get_report_customers_top($from, $to, $wh, 10);

            case 'get_payment_breakdown':
                return $this->pos_model->get_report_payments_breakdown($from, $to, $wh);

            case 'get_loyalty_activity':
                $rows = $this->pos_model->get_report_loyalty_activity($from, $to, $wh);
                return array_slice($rows, 0, 60);

            case 'get_promotion_performance':
                return $this->pos_model->get_report_promotions($from, $to, $wh);

            case 'get_blast_conversion':
                return $this->loyalty_model->get_report_blast_conversion($from, $to);

            case 'get_voucher_performance':
                return $this->loyalty_model->get_report_vouchers($from, $to);

            default:
                return ['error' => 'Unknown tool'];
        }
    }

    private function _gemini_request($api_key, $contents, $tools, $system_text)
    {
        $payload = [
            'system_instruction' => ['parts' => [['text' => $system_text]]],
            'contents'           => $contents,
            'tools'              => $tools,
            'generationConfig'   => ['temperature' => 0.7, 'maxOutputTokens' => 1500],
        ];

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite:generateContent?key=' . urlencode($api_key);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 30,
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);

        return json_decode($raw, true) ?: ['error' => ['message' => 'Invalid response from Gemini']];
    }

    public function costing_download_template()
    {
        if (!has_permission('pos', '', 'view')) {
            access_denied('pos');
        }
        require_once(module_dir_path('warehouse') . '/assets/plugins/XLSXWriter/xlsxwriter.class.php');

        $writer = new XLSXWriter();

        $ing_pkg_header = [
            'SKU' => 'string',
            'Item Name' => 'string',
            'Batch Size' => 'price',
            'Batch UOM' => 'string',
            'Units Per Batch' => 'price',
            'Unit UOM' => 'string',
            'Cost Per Batch (MYR)' => 'price',
            'Description' => 'string',
        ];

        $ing_pkg_widths = [14, 28, 12, 12, 16, 12, 20, 30];
        $col_style = array_keys(array_fill(0, count($ing_pkg_header), 0));
        $header_style = [
            'widths' => $ing_pkg_widths,
            'fill' => '#1e88e5',
            'font-style' => 'bold',
            'color' => '#ffffff',
            'border' => 'left,right,top,bottom',
            'border-color' => '#0a0a0a',
            'font-size' => 12,
        ];

        $writer->writeSheetHeader_v2('Ingredients', $ing_pkg_header, ['widths' => $ing_pkg_widths], $col_style, $header_style);
        $writer->writeSheetRow('Ingredients', [
            'FLOUR-001', 'All-Purpose Flour', 25.00, 'kg', 25.00, 'kg', 120.00, 'Premium bakery flour',
        ]);
        $writer->writeSheetRow('Ingredients', [
            'SUGAR-001', 'Granulated Sugar', 50.00, 'kg', 50.00, 'kg', 80.00, '',
        ]);

        $writer->writeSheetHeader_v2('Packaging', $ing_pkg_header, ['widths' => $ing_pkg_widths], $col_style, $header_style);
        $writer->writeSheetRow('Packaging', [
            'BOX-001', 'Cake Box Medium', 100.00, 'pcs', 100.00, 'pcs', 250.00, '10x10x5 inch',
        ]);
        $writer->writeSheetRow('Packaging', [
            'CUP-001', 'Paper Cup 12oz', 500.00, 'pcs', 500.00, 'pcs', 450.00, '',
        ]);

        $mixed_header = [
            'SKU' => 'string',
            'Mixed Ingredient Name' => 'string',
            'Batch Yield (Total Units)' => 'price',
            'Yield UOM' => 'string',
            'Prep Minutes' => 'integer',
            'Active (1/0)' => 'integer',
        ];
        $mixed_widths = [14, 28, 24, 12, 14, 12];
        $mixed_col_style = array_keys(array_fill(0, count($mixed_header), 0));
        $mixed_header_style = $header_style;
        $mixed_header_style['widths'] = $mixed_widths;

        $writer->writeSheetHeader_v2('Mixed Ingredients Summary', $mixed_header, ['widths' => $mixed_widths], $mixed_col_style, $mixed_header_style);
        $writer->writeSheetRow('Mixed Ingredients Summary', [
            'MIX-DOUGH', 'Cake Dough Base', 10.00, 'kg', 30, 1,
        ]);

        $prod_header = [
            'SKU' => 'string',
            'Product Name' => 'string',
            'Type (finished_product/combo)' => 'string',
            'Selling Price' => 'price',
            'Existing Purchase Price (Current)' => 'price',
            'Batch Size' => 'price',
            'Units Per Batch' => 'price',
        ];
        $prod_widths = [14, 28, 28, 14, 28, 12, 16];
        $prod_col_style = array_keys(array_fill(0, count($prod_header), 0));
        $prod_header_style = $header_style;
        $prod_header_style['widths'] = $prod_widths;

        $writer->writeSheetHeader_v2('Products Summary', $prod_header, ['widths' => $prod_widths], $prod_col_style, $prod_header_style);
        $writer->writeSheetRow('Products Summary', [
            'CAKE-001', 'Chocolate Cake', 'finished_product', 45.00, 0.00, 1.00, 1.00,
        ]);

        $bom_mixed_header = [
            'Sheet Name' => 'string',
            'Component Type (raw/packaging/mixed)' => 'string',
            'Component SKU' => 'string',
            'Component Name' => 'string',
            'Quantity' => 'price',
            'UOM' => 'string',
            'Note' => 'string',
        ];
        $bom_mixed_widths = [20, 32, 18, 24, 12, 12, 30];
        $bom_mixed_col_style = array_keys(array_fill(0, count($bom_mixed_header), 0));
        $bom_mixed_header_style = $header_style;
        $bom_mixed_header_style['widths'] = $bom_mixed_widths;

        $writer->writeSheetHeader_v2('BOM - Mixed Ingredients', $bom_mixed_header, ['widths' => $bom_mixed_widths], $bom_mixed_col_style, $bom_mixed_header_style);
        $writer->writeSheetRow('BOM - Mixed Ingredients', [
            'MIX-DOUGH', 'raw_ingredient', 'FLOUR-001', 'All-Purpose Flour', 3.00, 'kg', 'Sifted before mixing',
        ]);
        $writer->writeSheetRow('BOM - Mixed Ingredients', [
            'MIX-DOUGH', 'raw_ingredient', 'SUGAR-001', 'Granulated Sugar', 1.50, 'kg', '',
        ]);

        $bom_prod_header = [
            'Product SKU' => 'string',
            'Product Name' => 'string',
            'Section (mixed_ingredient/raw_ingredient/packaging)' => 'string',
            'Component Type' => 'string',
            'Component SKU' => 'string',
            'Component Name' => 'string',
            'Quantity Per Serving' => 'price',
            'UOM' => 'string',
            'Note' => 'string',
        ];
        $bom_prod_widths = [14, 24, 36, 20, 18, 24, 20, 12, 30];
        $bom_prod_col_style = array_keys(array_fill(0, count($bom_prod_header), 0));
        $bom_prod_header_style = $header_style;
        $bom_prod_header_style['widths'] = $bom_prod_widths;

        $writer->writeSheetHeader_v2('BOM - Products', $bom_prod_header, ['widths' => $bom_prod_widths], $bom_prod_col_style, $bom_prod_header_style);
        $writer->writeSheetRow('BOM - Products', [
            'CAKE-001', 'Chocolate Cake', 'mixed_ingredient', 'mixed_ingredient', 'MIX-DOUGH', 'Cake Dough Base', 0.50, 'kg', '',
        ]);
        $writer->writeSheetRow('BOM - Products', [
            'CAKE-001', 'Chocolate Cake', 'packaging', 'packaging', 'BOX-001', 'Cake Box Medium', 1.00, 'pcs', '',
        ]);

        $filename = 'Cost_Profit_Template_' . date('Ymd') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        $writer->writeToStdOut();
        exit;
    }

    public function costing_upload_excel()
    {
        if (ob_get_level()) {
            ob_end_clean();
        }
        ob_start();

        header('Content-Type: application/json');

        if (!has_permission('pos', '', 'create')) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            return;
        }

        if (!class_exists('XLSXReader_fin')) {
            require_once(module_dir_path('warehouse') . '/assets/plugins/XLSXReader/XLSXReader.php');
        }

        $this->load->model('pos/pos_model');

        if (empty($_FILES['file_xlsx']['name'])) {
            echo json_encode(['success' => false, 'message' => 'No file uploaded']);
            return;
        }

        $tmpFilePath = $_FILES['file_xlsx']['tmp_name'];
        if (empty($tmpFilePath)) {
            echo json_encode(['success' => false, 'message' => 'Upload failed']);
            return;
        }

        $ext = strtolower(pathinfo($_FILES['file_xlsx']['name'], PATHINFO_EXTENSION));
        if ($ext !== 'xlsx') {
            echo json_encode(['success' => false, 'message' => 'Only .xlsx files are supported']);
            return;
        }

        $tmpDir = TEMP_FOLDER . time() . uniqid() . '/';
        if (!file_exists(TEMP_FOLDER)) {
            mkdir(TEMP_FOLDER, 0755, true);
        }
        if (!mkdir($tmpDir, 0755, true) && !is_dir($tmpDir)) {
            echo json_encode(['success' => false, 'message' => 'Could not create temp directory. Check server write permissions.']);
            return;
        }
        $newFilePath = $tmpDir . basename($_FILES['file_xlsx']['name']);
        if (!move_uploaded_file($tmpFilePath, $newFilePath)) {
            @rmdir($tmpDir);
            echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file. Check server write permissions on the temp folder.']);
            return;
        }

        try {
            $xlsx = new XLSXReader_fin($newFilePath);
            $sheetNames = $xlsx->getSheetNames();
            if (empty($sheetNames)) {
                throw new Exception('The Excel file contains no sheets.');
            }
        } catch (Exception $e) {
            @unlink($newFilePath);
            @rmdir($tmpDir);
            echo json_encode(['success' => false, 'message' => 'Could not read the Excel file: ' . $e->getMessage()]);
            return;
        }

        $preview = [];
        $target_sheets = ['Ingredients', 'Packaging', 'Mixed Ingredients Summary', 'Products Summary', 'BOM - Mixed Ingredients', 'BOM - Products'];
        foreach ($target_sheets as $ts) {
            foreach ($sheetNames as $sn) {
                if (strcasecmp(trim($sn), trim($ts)) === 0) {
                    try {
                        $sheet = $xlsx->getSheet($sn);
                        if ($sheet) {
                            $rows = $sheet->getData();
                            $preview[$ts] = array_slice($rows, 0, 5);
                        }
                    } catch (Exception $e) {
                        $preview[$ts] = [];
                    }
                    break;
                }
            }
            if (!isset($preview[$ts])) {
                $preview[$ts] = [];
            }
        }

        $this->session->set_userdata('costing_import_temp_file', $newFilePath);
        $this->session->set_userdata('costing_import_temp_dir', $tmpDir);

        echo json_encode([
            'success' => true,
            'sheets_found' => $sheetNames,
            'preview' => $preview,
            'temp_file' => $newFilePath,
        ]);
    }

    public function costing_apply_import()
    {
        if (ob_get_level()) {
            ob_end_clean();
        }
        ob_start();

        header('Content-Type: application/json');

        if (!has_permission('pos', '', 'create')) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            return;
        }

        if (!class_exists('XLSXReader_fin')) {
            require_once(module_dir_path('warehouse') . '/assets/plugins/XLSXReader/XLSXReader.php');
        }

        $this->load->model('pos/pos_model');

        $tempFile = $this->session->userdata('costing_import_temp_file');
        $tempDir = $this->session->userdata('costing_import_temp_dir');

        if (empty($tempFile) || !file_exists($tempFile)) {
            echo json_encode(['success' => false, 'message' => 'Temporary file not found. Please upload again.']);
            return;
        }

        try {
            $xlsx = new XLSXReader_fin($tempFile);
            $sheetNames = $xlsx->getSheetNames();
        } catch (Exception $e) {
            @unlink($tempFile);
            if ($tempDir && is_dir($tempDir)) {
                @rmdir($tempDir);
            }
            $this->session->unset_userdata('costing_import_temp_file');
            $this->session->unset_userdata('costing_import_temp_dir');
            echo json_encode(['success' => false, 'message' => 'Could not read the Excel file: ' . $e->getMessage()]);
            return;
        }

        $sheet_map = [];
        foreach ($sheetNames as $sn) {
            $sheet_map[trim($sn)] = $sn;
        }

        $find_sheet = function ($names) use ($sheet_map) {
            foreach ((array)$names as $n) {
                $trimmed = trim($n);
                if (isset($sheet_map[$trimmed])) {
                    return $sheet_map[$trimmed];
                }
                foreach ($sheet_map as $key => $orig) {
                    if (strcasecmp($key, $trimmed) === 0) {
                        return $orig;
                    }
                }
            }
            return null;
        };

        $read_sheet_rows = function ($sheet_name) use ($xlsx) {
            if ($sheet_name === null) {
                return [];
            }
            try {
                $sheet = $xlsx->getSheet($sheet_name);
                if (!$sheet) {
                    return [];
                }
                return $sheet->getData();
            } catch (Exception $e) {
                return [];
            }
        };

        $rows_to_assoc = function (array $rows) {
            if (empty($rows)) {
                return [];
            }
            $header = array_values(array_map(function ($h) {
                return is_string($h) ? trim($h) : $h;
            }, $rows[0]));
            $result = [];
            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                $assoc = [];
                $is_empty = true;
                foreach ($header as $col_idx => $col_name) {
                    $val = $row[$col_idx] ?? null;
                    if ($val !== null && $val !== '') {
                        $is_empty = false;
                    }
                    $assoc[$col_name] = $val;
                }
                if (!$is_empty) {
                    $result[] = $assoc;
                }
            }
            return $result;
        };

        $stats = [];

        $ing_sheet = $find_sheet(['Ingredients']);
        $ing_rows_raw = $read_sheet_rows($ing_sheet);
        $ing_rows_assoc = $rows_to_assoc($ing_rows_raw);
        $stats['Ingredients'] = $this->pos_model->import_cost_ingredients_sheet($ing_rows_assoc);

        $pkg_sheet = $find_sheet(['Packaging']);
        $pkg_rows_raw = $read_sheet_rows($pkg_sheet);
        $pkg_rows_assoc = $rows_to_assoc($pkg_rows_raw);
        $stats['Packaging'] = $this->pos_model->import_cost_packaging_sheet($pkg_rows_assoc);

        $mixed_summary_sheet = $find_sheet(['Mixed Ingredients Summary']);
        $mixed_summary_rows_raw = $read_sheet_rows($mixed_summary_sheet);
        $mixed_summary_assoc = $rows_to_assoc($mixed_summary_rows_raw);
        $stats['Mixed Ingredients Summary'] = [
            'rows' => count($mixed_summary_assoc),
            'processed' => 0,
            'errors' => [],
        ];

        $bom_mixed_sheet = $find_sheet(['BOM - Mixed Ingredients']);
        $bom_mixed_raw = $read_sheet_rows($bom_mixed_sheet);
        $bom_mixed_assoc = $rows_to_assoc($bom_mixed_raw);

        $mixed_groups = [];
        foreach ($bom_mixed_assoc as $bmr) {
            $bmr_lc = array_change_key_case($bmr, CASE_LOWER);
            $sheet_key = trim((string)($bmr_lc['sheet name'] ?? $bmr_lc['sheet_name'] ?? ''));
            if ($sheet_key === '') {
                continue;
            }
            $comp_type_raw = strtolower(trim((string)($bmr_lc['component type (raw/packaging/mixed)'] ?? $bmr_lc['component type'] ?? $bmr_lc['component_type'] ?? '')));
            if (strpos($comp_type_raw, 'mixed') !== false) {
                $mixed_groups[$sheet_key]['mixed'][] = $bmr;
            } else {
                $mixed_groups[$sheet_key]['ingredients'][] = $bmr;
            }
        }

        $mixed_header_by_name = [];
        foreach ($mixed_summary_assoc as $msa) {
            $msa_lc = array_change_key_case($msa, CASE_LOWER);
            $sku = trim((string)($msa_lc['sku'] ?? ''));
            $name = trim((string)($msa_lc['mixed ingredient name'] ?? $msa_lc['name'] ?? ''));
            $key = $sku !== '' ? $sku : $name;
            if ($key === '') {
                continue;
            }
            $mixed_header_by_name[$key] = [
                'total_units' => (float)($msa_lc['batch yield (total units)'] ?? $msa_lc['batch yield'] ?? $msa_lc['total_units'] ?? 1),
                'yield_uom' => trim((string)($msa_lc['yield uom'] ?? $msa_lc['yield_uom'] ?? '')),
                'prep_minutes' => (int)($msa_lc['prep minutes'] ?? $msa_lc['prep_minutes'] ?? 0),
                'active' => (int)($msa_lc['active (1/0)'] ?? $msa_lc['active'] ?? 1),
            ];
            if ($sku !== '') {
                $mixed_header_by_name[$sku] = $mixed_header_by_name[$key];
            }
            if ($name !== '') {
                $mixed_header_by_name[$name] = $mixed_header_by_name[$key];
            }
        }

        $mixed_processed = 0;
        $mixed_errors = [];
        foreach ($mixed_groups as $sheet_name => $group_data) {
            $header = $mixed_header_by_name[$sheet_name] ?? [
                'total_units' => 1,
                'yield_uom' => null,
                'prep_minutes' => null,
                'active' => 1,
            ];
            $ing_rows = $group_data['ingredients'] ?? [];
            $mix_rows = $group_data['mixed'] ?? [];
            $res = $this->pos_model->import_cost_mixed_sheet($sheet_name, $header, $ing_rows, $mix_rows);
            $mixed_processed++;
            if (!empty($res['errors'])) {
                $mixed_errors = array_merge($mixed_errors, $res['errors']);
            }
        }
        $stats['Mixed Ingredients BOM'] = [
            'sheets_processed' => $mixed_processed,
            'errors' => $mixed_errors,
        ];

        $snapshot_name = 'Post Excel Import ' . date('Y-m-d H:i');
        $recalc_result = $this->pos_model->recalculate_all_costs(['create_snapshot' => true, 'snapshot_name' => $snapshot_name]);
        $created_snapshot_id = isset($recalc_result['snapshot_id']) ? $recalc_result['snapshot_id'] : null;

        @unlink($tempFile);
        if ($tempDir && is_dir($tempDir)) {
            @rmdir($tempDir);
        }
        $this->session->unset_userdata('costing_import_temp_file');
        $this->session->unset_userdata('costing_import_temp_dir');

        echo json_encode([
            'success' => true,
            'stats' => $stats,
            'recalc' => [
                'raw_count' => $recalc_result['raw_count'] ?? 0,
                'mixed_count' => $recalc_result['mixed_count'] ?? 0,
                'product_count' => $recalc_result['product_count'] ?? 0,
                'combo_count' => $recalc_result['combo_count'] ?? 0,
            ],
            'snapshot_id' => $created_snapshot_id,
            'snapshot_name' => $snapshot_name,
        ]);
    }

    public function ajax_get_snapshot_values()
    {
        if (ob_get_level()) {
            ob_end_clean();
        }
        ob_start();

        header('Content-Type: application/json');

        if (!has_permission('pos', '', 'view')) {
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            return;
        }

        try {
            $this->load->model('pos/pos_model');

            $snapshot_id = (int)$this->input->post('snapshot_id');
            if (!$snapshot_id) {
                echo json_encode(['success' => false, 'error' => 'snapshot_id is required']);
                return;
            }

            $values = $this->pos_model->get_snapshot_values($snapshot_id);
            echo json_encode([
                'success'      => true,
                'values'       => $values,
                'items_total'  => count($values),
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function ajax_compare_snapshots()
    {
        if (ob_get_level()) {
            ob_end_clean();
        }
        ob_start();

        header('Content-Type: application/json');

        if (!has_permission('pos', '', 'view')) {
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            return;
        }

        try {
            $this->load->model('pos/pos_model');

            $snapshot_a_id = (int)$this->input->post('snapshot_a_id');
            $snapshot_b_id = (int)$this->input->post('snapshot_b_id');

            if (!$snapshot_a_id || !$snapshot_b_id) {
                echo json_encode(['success' => false, 'error' => 'snapshot_a_id and snapshot_b_id are required']);
                return;
            }

            $comparison = $this->pos_model->get_snapshot_comparison($snapshot_a_id, $snapshot_b_id);
            echo json_encode([
                'success' => true,
                'items'   => isset($comparison['items']) ? $comparison['items'] : [],
                'meta'    => isset($comparison['meta']) ? $comparison['meta'] : [],
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}
