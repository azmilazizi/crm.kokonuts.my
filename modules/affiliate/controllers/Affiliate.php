<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Affiliate extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('affiliate/affiliate_model');
    }

    public function index()
    {
        $this->require_permission('view');
        $data['title'] = 'Affiliate Products';
        $data['products'] = $this->affiliate_model->get_products($this->input->get());
        $data['categories'] = $this->affiliate_model->categories();
        $data['edit_product'] = null;
        $this->load->view('affiliate/admin/products', $data);
    }

    public function edit($itemNo = 0)
    {
        $this->require_permission('view');
        $data['title'] = $itemNo ? 'Edit Affiliate Product #' . (int) $itemNo : 'Add Affiliate Product';
        $data['products'] = $this->affiliate_model->get_products();
        $data['categories'] = $this->affiliate_model->categories();
        $data['edit_product'] = $itemNo ? $this->affiliate_model->get_product($itemNo) : null;
        if ($itemNo && !$data['edit_product']) show_404();
        $this->load->view('affiliate/admin/products', $data);
    }

    public function save($itemNo = 0)
    {
        $this->require_permission($itemNo ? 'edit' : 'create');
        $post = $this->input->post();
        $errors = [];
        if (trim($post['name'] ?? '') === '') $errors[] = 'Name is required.';
        foreach (['shopee_url' => 'Shopee', 'tiktok_url' => 'TikTok Shop'] as $field => $label) {
            if (!empty($post[$field]) && !$this->valid_platform_url($post[$field], $field === 'shopee_url' ? 'shopee' : 'tiktok')) $errors[] = $label . ' link is not a recognized platform link.';
        }
        if (empty($post['shopee_url']) && empty($post['tiktok_url'])) $errors[] = 'Add at least one Shopee or TikTok Shop link.';
        if (!empty($post['tiktok_url']) && empty($post['tiktok_expires_at'])) $post['tiktok_expires_at'] = date('Y-m-d', strtotime('+100 days'));
        if ($errors) { set_alert('danger', implode(' ', $errors)); redirect(admin_url('affiliate/edit' . ($itemNo ? '/' . $itemNo : ''))); }

        $wasNew = !$itemNo;
        $saved = $this->affiliate_model->save_product(array_merge($post, ['is_active' => isset($post['is_active']) ? 1 : 0]), $itemNo);
        if (!$itemNo) $itemNo = $saved;
        if (!empty($_FILES['image_file']['name'])) {
            $config = ['upload_path' => FCPATH . 'uploads/affiliate/', 'allowed_types' => 'jpg|jpeg|png|webp', 'max_size' => 5120, 'file_name' => 'affiliate_' . (int) $itemNo . '_' . time()];
            $this->load->library('upload', $config);
            if ($this->upload->do_upload('image_file')) $this->affiliate_model->set_image_path($itemNo, $this->upload->data('file_name'));
            else set_alert('warning', 'Product saved, but image upload failed: ' . strip_tags($this->upload->display_errors('', '')));
        }
        set_alert('success', 'Item #' . (int) $itemNo . ($wasNew ? ' created.' : ' updated.'));
        redirect(admin_url('affiliate/edit/' . (int) $itemNo));
    }

    public function categories()
    {
        $this->require_permission('view');
        if ($this->input->post()) {
            $this->require_permission('edit');
            $name = trim($this->input->post('name'));
            if ($name !== '') $this->affiliate_model->save_category($name, (int) $this->input->post('id'));
            redirect(admin_url('affiliate/categories'));
        }
        $this->load->view('affiliate/admin/categories', ['title' => 'Affiliate Categories', 'categories' => $this->affiliate_model->categories()]);
    }

    public function delete_category($id)
    {
        $this->require_permission('delete');
        $this->affiliate_model->delete_category($id);
        redirect(admin_url('affiliate/categories'));
    }

    public function analytics()
    {
        $this->require_permission('view');
        $from = $this->input->get('from') ?: date('Y-m-d', strtotime('-30 days'));
        $to = $this->input->get('to') ?: date('Y-m-d');
        $this->load->view('affiliate/admin/analytics', ['title' => 'Affiliate Click Analytics', 'rows' => $this->affiliate_model->analytics($from, $to), 'from' => $from, 'to' => $to]);
    }

    public function analytics_csv()
    {
        $this->require_permission('view');
        $from = $this->input->get('from') ?: date('Y-m-d', strtotime('-30 days')); $to = $this->input->get('to') ?: date('Y-m-d');
        $rows = $this->affiliate_model->analytics($from, $to);
        header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="affiliate-clicks-' . $from . '-to-' . $to . '.csv"');
        $out = fopen('php://output', 'w'); fputcsv($out, ['Item No', 'Name', 'Platform', 'Date', 'Clicks']);
        foreach ($rows as $row) fputcsv($out, [$row->item_no, $row->name, $row->platform, $row->click_date, $row->clicks]);
        fclose($out);
    }

    private function require_permission($capability)
    {
        if (!has_permission('affiliate_products', '', $capability)) access_denied('affiliate_products');
    }

    private function valid_platform_url($url, $platform)
    {
        $host = strtolower((string) parse_url(trim($url), PHP_URL_HOST));
        if (!$host) return false;
        if ($platform === 'shopee') return $host === 'shopee.com.my' || endsWith($host, '.shopee.com.my') || $host === 'shope.ee' || endsWith($host, '.shopee.co.th');
        return $host === 'tiktok.com' || endsWith($host, '.tiktok.com');
    }
}
