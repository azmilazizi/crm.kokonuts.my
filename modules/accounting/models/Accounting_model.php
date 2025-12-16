    public function automatic_purchase_payment_conversion($payment_id, $shipping_fee = 0){
        if(!$purchase_invoice || $purchase_invoice->pur_order == '' || $purchase_invoice->pur_order == 0){
            return false;
        }

        $purchase_order = $this->purchase_model->get_pur_order($purchase_invoice->pur_order);
        if(!$purchase_order){
            return false;
        }

        $purchase_order_details = $this->purchase_model->get_pur_order_detail($purchase_invoice->pur_order);
        if(count($purchase_order_details) == 0){
            return false;
        }
            $shipping_payment_total = $shipping_fee;
                $shipping_payment_total = round(($currency_rate * $shipping_fee), 2);
                $payment_account = $payment_mode_mapping->expense_payment_account;
                $deposit_to = $payment_mode_mapping->expense_deposit_to;
            }
            $order_subtotal = (float)$purchase_order->subtotal;
            if($order_subtotal == 0){
                foreach($purchase_order_details as $detail){
                    $order_subtotal += (float)$detail['into_money'];
            }

            $order_subtotal_converted = $order_subtotal;
            if($currency_rate != 0){
                $order_subtotal_converted = round($order_subtotal * $currency_rate, 2);
            }

            $payment_allocation_total = get_option('acc_pur_shipping_automatic_conversion') == 1 ? $payment_total - $shipping_payment_total : $payment_total;
            $allocation_rate = 0;
            if($order_subtotal_converted > 0){
                $allocation_rate = max(0, min(1, $payment_allocation_total / $order_subtotal_converted));
            }

            if(get_option('acc_pur_payment_automatic_conversion') == 1){
                foreach ($purchase_order_details as $value) {
                    $item = get_item_hp($value['item_code']);

                    $item_id = 0;
                    if(isset($item->id)){
                        $item_id = $item->id;
                    }

                    $item_total = (float)$value['into_money'];
                    if($currency_rate != 0){
                        $item_total = round($item_total * $currency_rate, 2);
                    }

                    $item_payment_amount = $allocation_rate > 0 ? round($item_total * $allocation_rate, 2) : 0;

                    if($item_payment_amount <= 0){
                        continue;
                    }

                    $item_automatic = $this->get_item_automatic($item_id);
                    $item_account = $deposit_to;

                    if($item_automatic && $item_automatic->expense_account){
                        $item_account = $item_automatic->expense_account;
                    }
                    $node['account'] = $item_account;
                    $node['item'] = $item_id;
                    $node['debit'] = $item_payment_amount;
                    $node['credit'] = 0;
                    $node['tax'] = 0;
                    $node['split'] = $item_account;
                    $node['item'] = $item_id;
                    $node['tax'] = 0;
                    $node['credit'] = $item_payment_amount;
            }
            if(get_option('acc_pur_shipping_automatic_conversion') == 1 && $shipping_payment_total > 0) {
                $node = [];
                $node['split'] = $shipping_payment_account;
                $node['account'] = $shipping_deposit_to;
                $node['debit'] = $shipping_payment_total;
                $node['credit'] = 0;
                $node['date'] = $payment->date;
                $node['description'] = '';
                $node['rel_id'] = $payment_id;
                $node['rel_type'] = 'purchase_shipping';
                $node['datecreated'] = date('Y-m-d H:i:s');
                $node['addedfrom'] = get_staff_user_id();
                $node['currency_rate'] = $currency_rate;
                $data_insert[] = $node;
                $node = [];
                $node['split'] = $shipping_deposit_to;
                $node['account'] = $shipping_payment_account;
                $node['date'] = $payment->date;
                $node['tax'] = 0;
                $node['debit'] = 0;
                $node['credit'] = $shipping_payment_total;
                $node['description'] = '';
                $node['rel_id'] = $payment_id;
                $node['rel_type'] = 'purchase_shipping';
                $node['datecreated'] = date('Y-m-d H:i:s');
                $node['addedfrom'] = get_staff_user_id();
                $node['currency_rate'] = $currency_rate;
                $data_insert[] = $node;
}
