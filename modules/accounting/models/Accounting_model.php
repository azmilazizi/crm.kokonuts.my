                        $node['tax'] = $tax_value;
                        $node['tax'] = $tax_value;
                        $node['tax'] = $tax_value;
                        $node['tax'] = $tax_value;
                        $node['tax'] = $tax_value;
                        $node['tax'] = $tax_value;
                        $node['tax'] = $tax_value;
                        $node['tax'] = $tax_value;
                        $node['tax'] = $tax_value;
                        $node['tax'] = $tax_value;
                        $node['tax'] = $tax_value;
                        $node['tax'] = $tax_value;
            $receipt_currency = property_exists($goods_receipt, 'currency') ? (int) $goods_receipt->currency : 0;
            $receipt_currency_rate = property_exists($goods_receipt, 'currency_exchange_rate') ? (float) $goods_receipt->currency_exchange_rate : 0;

            if($goods_receipt->pr_order_id != 0 && $receipt_currency != 0 && $receipt_currency != $currency->id && $receipt_currency_rate != 0){
                $currency_rate = $receipt_currency_rate;
                $tax_value = isset($value['tax']) && !is_array($value['tax']) ? $value['tax'] : (is_array($value['tax']) ? implode(',', $value['tax']) : 0);
                $tax_money_value = isset($value['tax_money']) && !is_array($value['tax_money']) ? $value['tax_money'] : (is_array($value['tax_money']) ? array_sum(array_map('floatval', $value['tax_money'])) : 0);

                $shipping_fee = isset($value['shipping_fee']) ? (float) $value['shipping_fee'] : 0;
                if(get_option('acc_pur_shipping_automatic_conversion') == 1 && $shipping_fee > 0){
                    $total_shipping = round($shipping_fee / $currency_rate, 2);
                    $node['split'] = $shipping_payment_account;
                    $node['account'] = $shipping_deposit_to;
                    $node['tax'] = 0;
                    $node['debit'] = $total_shipping;
                    $node['rel_type'] = 'purchase_shipping';
                    $node['split'] = $shipping_deposit_to;
                    $node['account'] = $shipping_payment_account;
                    $node['tax'] = 0;
                    $node['credit'] = $total_shipping;
                    $node['rel_type'] = 'purchase_shipping';
                if(get_option('acc_tax_automatic_conversion') == 1 && (float) $tax_value > 0){
                    $total_tax = round($tax_money_value / $currency_rate, 2);
                    $tax_mapping = $this->get_tax_mapping($tax_value);
                        $node['tax'] = $tax_value;
                        $node['tax'] = $tax_value;
                        $node['tax'] = $tax_value;
                        $node['tax'] = $tax_value;
}
