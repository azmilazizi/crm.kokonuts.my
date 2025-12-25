        $pur_order = null;
        $shipping_fee = 0;
            if (!empty($payment->pur_order)) {
                $this->load->model('purchase/purchase_model');
                $pur_order = $this->purchase_model->get_pur_order($payment->pur_order);
                $shipping_fee = $pur_order ? $pur_order->shipping_fee : 0;
            }

            if (isset($payment->pur_order) && !empty($payment->pur_order)) {
