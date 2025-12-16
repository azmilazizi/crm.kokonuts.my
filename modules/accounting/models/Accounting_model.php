        $this->db->where('((select count(*) from ' . db_prefix() . 'acc_account_history where ' . db_prefix() . 'acc_account_history.rel_id = ' . db_prefix() . 'pur_invoice_payment.id and ' . db_prefix() . 'acc_account_history.rel_type IN ("purchase_payment","purchase_order","purchase_shipping")) = 0) AND (' . db_prefix() . 'pur_invoices.pur_order is not null) and ' . db_prefix() . 'pur_invoice_payment.approval_status = 2 '.$where_currency);
        $this->db->where_in('rel_type', ['purchase_payment', 'purchase_order', 'purchase_shipping']);
                $node['rel_type'] = 'purchase_order';
                $node['rel_type'] = 'purchase_order';
                    $node['rel_type'] = 'purchase_order';
                    $node['rel_type'] = 'purchase_order';
}
