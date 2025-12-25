            $data_insert = [];
            $use_payment_mapping = false;
            if (get_option('acc_active_payment_mode_mapping') == 1) {
                if ($payment_mode_mapping) {
                    $payment_account = $payment_mode_mapping->payment_account;
                    $deposit_to = $payment_mode_mapping->deposit_to;
                    $use_payment_mapping = true;
            }
            if ($use_payment_mapping || get_option('acc_payment_automatic_conversion') == 1) {
                $node = [];
                $node['split'] = $payment_account;
                $node['account'] = $deposit_to;
                $node['customer'] = $invoice->clientid;
                $node['debit'] = $payment_total;
                $node['credit'] = 0;
                $node['date'] = $payment->date;
                $node['description'] = '';
                $node['rel_id'] = $payment_id;
                $node['rel_type'] = 'payment';
                $node['datecreated'] = date('Y-m-d H:i:s');
                $node['addedfrom'] = get_staff_user_id();
                $node['currency_rate'] = $currency_rate;
                $data_insert[] = $node;

                $node = [];
                $node['split'] = $deposit_to;
                $node['customer'] = $invoice->clientid;
                $node['account'] = $payment_account;
                $node['date'] = $payment->date;
                $node['debit'] = 0;
                $node['credit'] = $payment_total;
                $node['description'] = '';
                $node['rel_id'] = $payment_id;
                $node['rel_type'] = 'payment';
                $node['datecreated'] = date('Y-m-d H:i:s');
                $node['addedfrom'] = get_staff_user_id();
                $node['currency_rate'] = $currency_rate;
                $data_insert[] = $node;
