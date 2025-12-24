        if(!isset($data['acc_invoice_discount_automatic_conversion'])){
            $data['acc_invoice_discount_automatic_conversion'] = 0;
        }

            if(get_option('acc_invoice_discount_automatic_conversion') == 1 && $invoice->discount_total > 0){
