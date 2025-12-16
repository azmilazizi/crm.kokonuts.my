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
    /**
     * Automatic purchase order payment conversion
     *
     * @param  integer $payment_id
     * @return boolean
     */
    public function automatic_purchase_order_payment_conversion($payment_id){
        $this->db->where('rel_id', $payment_id);
        $this->db->where_in('rel_type', ['purchase_payment', 'purchase_shipping']);
        $count = $this->db->count_all_results(db_prefix() . 'acc_account_history');

        if($count > 0){
            return false;
        }

        $this->load->model('currencies_model');
        $currency = $this->currencies_model->get_base_currency();

        $this->load->model('purchase/purchase_model');
        $this->db->where('id', $payment_id);
        $payment = $this->db->get(db_prefix().'pur_order_payment')->row();

        if(!$payment){
            return false;
        }

        $purchase_order = $this->purchase_model->get_pur_order($payment->pur_order);

        if(!$purchase_order || $purchase_order->approve_status != 2){
            return false;
        }

        $purchase_order_details = $this->purchase_model->get_pur_order_detail($payment->pur_order);

        if(count($purchase_order_details) == 0){
            return false;
        }

        $base_currency = get_base_currency_pur();
        if($purchase_order->currency != 0){
            $base_currency = pur_get_currency_by_id($purchase_order->currency);
        }

        $payment_account = get_option('acc_pur_payment_payment_account');
        $deposit_to = get_option('acc_pur_payment_deposit_to');

        $shipping_payment_account = get_option('acc_pur_shipping_payment_account');
        $shipping_deposit_to = get_option('acc_pur_shipping_deposit_to');

        $affectedRows = 0;
        $data_insert = [];

        if(get_option('acc_close_the_books') == 1){
            if(strtotime($payment->date) <= strtotime(get_option('acc_closing_date')) && strtotime(date('Y-m-d')) > strtotime(get_option('acc_closing_date'))){
                return false;
            }
        }

        $currency_rate = 0;
        $payment_total = (float)$payment->amount;
        $shipping_payment_total = 0;

        if(get_option('acc_pur_shipping_automatic_conversion') == 1 && isset($purchase_order->shipping_fee)){
            $shipping_payment_total = (float)$purchase_order->shipping_fee;
        }

        if($base_currency->name != $currency->name){
            $currency_rate = (float)$purchase_order->currency_rate != 0 ? $purchase_order->currency_rate : acc_get_currency_rate($base_currency->name, $currency->name);
            $payment_total = round(($payment_total / $currency_rate), 2);
            $shipping_payment_total = round(($shipping_payment_total / $currency_rate), 2);
        }

        $payment_mode_mapping = $this->get_payment_mode_mapping($payment->paymentmode);

        if($payment_mode_mapping && get_option('acc_active_payment_mode_mapping') == 1){
            $payment_account = $payment_mode_mapping->expense_payment_account;
            $deposit_to = $payment_mode_mapping->expense_deposit_to;
        }

        $order_subtotal = (float)$purchase_order->subtotal;
        if($order_subtotal == 0){
            foreach($purchase_order_details as $detail){
                $order_subtotal += (float)$detail['into_money'];
            }
        }

        $order_subtotal_converted = $order_subtotal;
        if($currency_rate != 0){
            $order_subtotal_converted = round($order_subtotal / $currency_rate, 2);
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
                    $item_total = round($item_total / $currency_rate, 2);
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

                $node = [];
                $node['split'] = $payment_account;
                $node['account'] = $item_account;
                $node['date'] = $payment->date;
                $node['item'] = $item_id;
                $node['debit'] = $item_payment_amount;
                $node['credit'] = 0;
                $node['tax'] = 0;
                $node['description'] = '';
                $node['rel_id'] = $payment_id;
                $node['rel_type'] = 'purchase_payment';
                $node['datecreated'] = date('Y-m-d H:i:s');
                $node['addedfrom'] = get_staff_user_id();
                $node['currency_rate'] = $currency_rate;
                $data_insert[] = $node;

                $node = [];
                $node['split'] = $item_account;
                $node['account'] = $payment_account;
                $node['date'] = $payment->date;
                $node['item'] = $item_id;
                $node['tax'] = 0;
                $node['debit'] = 0;
                $node['credit'] = $item_payment_amount;
                $node['description'] = '';
                $node['rel_id'] = $payment_id;
                $node['rel_type'] = 'purchase_payment';
                $node['datecreated'] = date('Y-m-d H:i:s');
                $node['addedfrom'] = get_staff_user_id();
                $node['currency_rate'] = $currency_rate;
                $data_insert[] = $node;
            }
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

        if($data_insert != []){
            $affectedRows = $this->db->insert_batch(db_prefix().'acc_account_history', $data_insert);
        }

        if ($affectedRows > 0) {
            return true;
        }

        return false;
    }

