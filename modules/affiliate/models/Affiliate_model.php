<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Affiliate_model extends App_Model
{
    public function get_products($filters = [])
    {
        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $this->db->group_start()->like('p.name', $search)->or_like('p.item_no', $search)->group_end();
        }
        if (isset($filters['is_active']) && $filters['is_active'] !== '') $this->db->where('p.is_active', (int) $filters['is_active']);
        if (!empty($filters['platform'])) {
            if ($filters['platform'] === 'shopee') $this->db->where('p.shopee_url IS NOT NULL', null, false)->where('p.shopee_url !=', '');
            if ($filters['platform'] === 'tiktok') $this->db->where('p.tiktok_url IS NOT NULL', null, false)->where('p.tiktok_url !=', '');
        }
        if (!empty($filters['category_id'])) $this->db->where('p.category_id', (int) $filters['category_id']);
        if (!empty($filters['expiring'])) $this->db->where('p.tiktok_url IS NOT NULL', null, false)->where('p.tiktok_expires_at <=', date('Y-m-d', strtotime('+7 days')));
        return $this->db->select('p.*, c.name AS category_name')->from(db_prefix() . 'affiliate_products p')->join(db_prefix() . 'affiliate_categories c', 'c.id = p.category_id', 'left')->order_by('p.item_no', 'desc')->get()->result();
    }

    public function get_product($itemNo)
    {
        return $this->db->where('item_no', (int) $itemNo)->get(db_prefix() . 'affiliate_products')->row();
    }

    public function categories()
    {
        return $this->db->order_by('name', 'asc')->get(db_prefix() . 'affiliate_categories')->result();
    }

    public function save_product($data, $itemNo = 0)
    {
        $row = [
            'name' => trim($data['name']), 'image_url' => trim($data['image_url'] ?? '') ?: null,
            'price' => ($data['price'] ?? '') !== '' ? (float) $data['price'] : null,
            'category_id' => !empty($data['category_id']) ? (int) $data['category_id'] : null,
            'notes' => trim($data['notes'] ?? '') ?: null,
            'shopee_url' => trim($data['shopee_url'] ?? '') ?: null,
            'tiktok_url' => trim($data['tiktok_url'] ?? '') ?: null,
            'tiktok_expires_at' => trim($data['tiktok_expires_at'] ?? '') ?: null,
            'is_active' => !empty($data['is_active']) ? 1 : 0, 'updated_at' => date('Y-m-d H:i:s'),
        ];
        if (isset($data['image_path'])) $row['image_path'] = $data['image_path'];
        if (!$itemNo) { $row['created_at'] = date('Y-m-d H:i:s'); $this->db->insert(db_prefix() . 'affiliate_products', $row); return $this->db->insert_id(); }
        $this->db->where('item_no', (int) $itemNo)->update(db_prefix() . 'affiliate_products', $row); return (int) $itemNo;
    }

    public function set_image_path($itemNo, $path)
    {
        return $this->db->where('item_no', (int) $itemNo)->update(db_prefix() . 'affiliate_products', ['image_path' => $path, 'updated_at' => date('Y-m-d H:i:s')]);
    }

    public function save_category($name, $id = 0)
    {
        if ($id) { $this->db->where('id', (int) $id)->update(db_prefix() . 'affiliate_categories', ['name' => trim($name)]); return (int) $id; }
        $this->db->insert(db_prefix() . 'affiliate_categories', ['name' => trim($name), 'created_at' => date('Y-m-d H:i:s')]); return $this->db->insert_id();
    }

    public function delete_category($id)
    {
        $this->db->where('category_id', (int) $id)->update(db_prefix() . 'affiliate_products', ['category_id' => null]);
        return $this->db->where('id', (int) $id)->delete(db_prefix() . 'affiliate_categories');
    }

    public function public_items()
    {
        $products = $this->db->select('p.*, c.name AS category_name')->from(db_prefix() . 'affiliate_products p')->join(db_prefix() . 'affiliate_categories c', 'c.id = p.category_id', 'left')->where('p.is_active', 1)->order_by('p.item_no', 'desc')->get()->result();
        $items = [];
        $categoryIds = [];
        foreach ($products as $p) {
            $tiktok = ($p->tiktok_url && (!$p->tiktok_expires_at || $p->tiktok_expires_at >= date('Y-m-d'))) ? $p->tiktok_url : null;
            $shopee = $p->shopee_url ?: null;
            if (!$shopee && !$tiktok) continue;
            if ($p->category_id) $categoryIds[] = (int) $p->category_id;
            $items[] = ['item_no' => (int) $p->item_no, 'name' => $p->name, 'image_url' => $p->image_url ?: ($p->image_path ? site_url('uploads/affiliate/' . rawurlencode(basename($p->image_path))) : null), 'price' => $p->price !== null ? (float) $p->price : null, 'category_id' => $p->category_id ? (int) $p->category_id : null, 'links' => ['shopee' => $shopee ? site_url('affiliate/go/' . (int) $p->item_no . '?p=shopee') : null, 'tiktok' => $tiktok ? site_url('affiliate/go/' . (int) $p->item_no . '?p=tiktok') : null]];
        }
        $categories = [];
        if ($categoryIds) foreach ($this->db->where_in('id', array_unique($categoryIds))->order_by('name', 'asc')->get(db_prefix() . 'affiliate_categories')->result() as $c) $categories[] = ['id' => (int) $c->id, 'name' => $c->name];
        return ['items' => $items, 'categories' => $categories];
    }

    public function log_click($itemNo, $platform, $campaign = '')
    {
        return $this->db->insert(db_prefix() . 'affiliate_clicks', ['item_no' => (int) $itemNo, 'platform' => $platform, 'clicked_at' => date('Y-m-d H:i:s'), 'referrer' => substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 500), 'campaign' => substr((string) $campaign, 0, 191), 'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500)]);
    }

    public function analytics($from, $to)
    {
        $this->db->select('c.item_no, p.name, c.platform, COUNT(*) AS clicks, DATE(c.clicked_at) AS click_date')->from(db_prefix() . 'affiliate_clicks c')->join(db_prefix() . 'affiliate_products p', 'p.item_no = c.item_no', 'left')->where('c.clicked_at >=', $from . ' 00:00:00')->where('c.clicked_at <=', $to . ' 23:59:59')->group_by(['c.item_no', 'c.platform', 'DATE(c.clicked_at)'])->order_by('click_date', 'desc')->order_by('clicks', 'desc');
        return $this->db->get()->result();
    }
}
