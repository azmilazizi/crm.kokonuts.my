            // Shipping conversion uses purchase order rel_type to keep REST-created
            // orders consistent with requested accounting tracking.
            if(get_option('acc_pur_shipping_automatic_conversion') == 1 && (float)$purchase_order->shipping_fee > 0){
                $shipping_payment_account = get_option('acc_pur_shipping_payment_account');
                $shipping_deposit_to      = get_option('acc_pur_shipping_deposit_to');

                $shipping_fee = $purchase_order->shipping_fee;
                if($base_currency->name != $currency->name && (float)$purchase_order->currency_rate != 0){
                    $shipping_fee = round(($shipping_fee / $purchase_order->currency_rate), 2);
                }

                $node = [];
                $node['split']       = $shipping_payment_account;
                $node['account']     = $shipping_deposit_to;
                $node['debit']       = $shipping_fee;
                $node['credit']      = 0;
                $node['date']        = $purchase_order->order_date;
                $node['description'] = '';
                $node['rel_id']      = $purchase_order_id;
                $node['rel_type']    = 'purchase_order';
                $node['datecreated'] = date('Y-m-d H:i:s');
                $node['addedfrom']   = get_staff_user_id();
                $node['currency_rate']= $purchase_order->currency_rate;
                $data_insert[] = $node;

                $node = [];
                $node['split']       = $shipping_deposit_to;
                $node['account']     = $shipping_payment_account;
                $node['debit']       = 0;
                $node['credit']      = $shipping_fee;
                $node['date']        = $purchase_order->order_date;
                $node['description'] = '';
                $node['rel_id']      = $purchase_order_id;
                $node['rel_type']    = 'purchase_order';
                $node['datecreated'] = date('Y-m-d H:i:s');
                $node['addedfrom']   = get_staff_user_id();
                $node['currency_rate']= $purchase_order->currency_rate;
                $data_insert[] = $node;
            }

}
