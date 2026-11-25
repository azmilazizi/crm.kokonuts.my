<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Affiliate_public extends App_Controller
{
    public function items()
    {
        $this->headers();
        $this->load->model('affiliate/affiliate_model');
        echo json_encode($this->affiliate_model->public_items(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function settings()
    {
        $this->headers();
        echo json_encode([
            'title' => get_option('affiliate_public_title') ?: 'Kokonuts Affiliate Products',
            'description' => get_option('affiliate_public_description') ?: 'Find the products from our videos by entering the item number below.',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function go($itemNo = 0)
    {
        $this->load->model('affiliate/affiliate_model');
        $product = $this->affiliate_model->get_product($itemNo);
        $requested = strtolower((string) $this->input->get('p'));
        $tiktokValid = $product && $product->tiktok_url && (!$product->tiktok_expires_at || $product->tiktok_expires_at >= date('Y-m-d'));
        $platform = $requested === 'tiktok' ? 'tiktok' : 'shopee';
        $url = null;
        if ($product && (int) $product->is_active) {
            if ($requested === 'tiktok' && $tiktokValid) $url = $product->tiktok_url;
            elseif ($requested === 'shopee' && $product->shopee_url) $url = $product->shopee_url;
            elseif (!$requested && $product->shopee_url) { $url = $product->shopee_url; $platform = 'shopee'; }
            elseif (!$requested && $tiktokValid) { $url = $product->tiktok_url; $platform = 'tiktok'; }
        }
        if ($url) {
            $this->affiliate_model->log_click($product->item_no, $platform, $this->input->get('utm_campaign') ?: $this->input->get('campaign'));
            redirect($url, 'location', 302);
        }
        redirect('https://affiliate.kokonuts.my/?q=' . rawurlencode((string) $itemNo), 'location', 302);
    }

    private function headers()
    {
        header('Content-Type: application/json; charset=UTF-8'); header('Cache-Control: public, max-age=60'); header('Access-Control-Allow-Origin: *');
    }
}
