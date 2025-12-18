        $purchase_order = null;
        if ((int) ($goods_receipt->pr_order_id ?? 0) > 0) {
            $this->load->model('purchase/purchase_model');
            $purchase_order = $this->purchase_model->get_pur_order((int) $goods_receipt->pr_order_id);
        }

            if ($purchase_order && get_option('acc_pur_shipping_automatic_conversion') == 1) {
                $shipping_fee = (float) ($purchase_order->shipping_fee ?? 0);
                if ($shipping_fee != 0) {
                    $shipping_amount = round($shipping_fee / $currency_rate, 2);

                    if ($shipping_amount != 0) {
                        $this->delete_convert((int) $purchase_order->id, 'purchase_shipping');

                        $node = [];
                        $node['split'] = $shipping_payment_account;
                        $node['account'] = $shipping_deposit_to;
                        $node['debit'] = $shipping_amount;
                        $node['credit'] = 0;
                        $node['date'] = $goods_receipt->date_c;
                        $node['description'] = '';
                        $node['rel_id'] = (int) $purchase_order->id;
                        $node['rel_type'] = 'purchase_shipping';
                        $node['datecreated'] = date('Y-m-d H:i:s');
                        $node['addedfrom'] = get_staff_user_id();
                        $data_insert[] = $node;

                        $node = [];
                        $node['split'] = $shipping_deposit_to;
                        $node['account'] = $shipping_payment_account;
                        $node['date'] = $goods_receipt->date_c;
                        $node['tax'] = 0;
                        $node['debit'] = 0;
                        $node['credit'] = $shipping_amount;
                        $node['description'] = '';
                        $node['rel_id'] = (int) $purchase_order->id;
                        $node['rel_type'] = 'purchase_shipping';
                        $node['datecreated'] = date('Y-m-d H:i:s');
                        $node['addedfrom'] = get_staff_user_id();
                        $data_insert[] = $node;
                    }
                }
            }

}
