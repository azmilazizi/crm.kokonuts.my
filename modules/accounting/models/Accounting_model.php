        if(!$purchase_order){
            return false;
        }

        $payments = $this->purchase_model->get_payment_purchase_order($purchase_order_id);
        $total_paid = 0;
        $payment_mode = null;
        $latest_payment_date = $purchase_order->order_date;

        foreach ($payments as $payment) {
            $total_paid += (float) $payment['amount'];
            if ($payment_mode === null && isset($payment['paymentmode']) && $payment['paymentmode'] !== '') {
                $payment_mode = $payment['paymentmode'];
            }

            if (!empty($payment['date'])) {
                $latest_payment_date = $payment['date'];
            }
        }
        $payment_account = get_option('acc_pur_payment_payment_account');
        $deposit_to = get_option('acc_pur_payment_deposit_to');
            $currency_rate = 0;
            $order_total = $purchase_order->total;
            if($base_currency->name != $currency->name){
                $currency_rate = (float) $purchase_order->currency_rate != 0 ? $purchase_order->currency_rate : 1;
                $order_total = round(($purchase_order->total / $currency_rate), 2);
                $total_paid = round(($total_paid / $currency_rate), 2);
            }
            if($total_paid <= 0 || $total_paid < $order_total){
                return false;
            }
            $payment_mode_mapping = null;
            if(is_numeric($payment_mode)){
                $payment_mode_mapping = $this->get_payment_mode_mapping($payment_mode);
            }
            if($payment_mode_mapping && get_option('acc_active_payment_mode_mapping') == 1){
                $payment_account = $payment_mode_mapping->expense_payment_account;
            }
            $this->delete_convert($purchase_order_id, 'purchase_order');
            $data_insert = [];
            $node = [];
            $node['split'] = $payment_account;
            $node['account'] = $deposit_to;
            $node['date'] = $latest_payment_date;
            $node['debit'] = $total_paid;
            $node['tax'] = 0;
            $node['credit'] = 0;
            $node['description'] = '';
            $node['rel_id'] = $purchase_order_id;
            $node['rel_type'] = 'purchase_order';
            $node['datecreated'] = date('Y-m-d H:i:s');
            $node['addedfrom'] = get_staff_user_id();
            $node['currency_rate'] = $currency_rate;
            $data_insert[] = $node;
            $node = [];
            $node['split'] = $deposit_to;
            $node['account'] = $payment_account;
            $node['date'] = $latest_payment_date;
            $node['tax'] = 0;
            $node['debit'] = 0;
            $node['credit'] = $total_paid;
            $node['description'] = '';
            $node['rel_id'] = $purchase_order_id;
            $node['rel_type'] = 'purchase_order';
            $node['datecreated'] = date('Y-m-d H:i:s');
            $node['addedfrom'] = get_staff_user_id();
            $node['currency_rate'] = $currency_rate;
            $data_insert[] = $node;
            $affectedRows = 0;
                $item_deposit_account = $deposit_to;
                if($item_automatic && $item_automatic->inventory_asset_account){
                    $item_deposit_account = $item_automatic->inventory_asset_account;
                $node['account'] = $item_deposit_account;
                $node['split'] = $item_deposit_account;
}
