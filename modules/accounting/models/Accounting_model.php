    public function get_accounts($id = '', $where = [], $show_account_numbers = true)
    {
        if (is_numeric($id)) {
            $this->db->where('id', $id);
            return $this->db->get(db_prefix() . 'acc_accounts')->row();
        }
        return $accounts;
    }

    public function get_accounts_with_balances($where = [], $show_account_numbers = true)
    {
        $accounts_table = db_prefix() . 'acc_accounts';
        $history_table  = db_prefix() . 'acc_account_history';

        $this->db->from($accounts_table);
        $this->db->select($accounts_table . '.*');
        $this->db->where($where);
        $this->db->where($accounts_table . '.active', 1);
        $this->db->order_by('account_type_id,account_detail_type_id', 'desc');

        $accounting_method = get_option('acc_accounting_method');

        if ($accounting_method === 'cash') {
            $invoice_filter = '((' . $history_table . '.rel_type = "invoice" AND ' . $history_table . '.paid = 1) OR ' . $history_table . '.rel_type != "invoice")';
            $debit_subquery  = '(SELECT SUM(debit) FROM ' . $history_table . ' WHERE (' . $history_table . '.account = ' . $accounts_table . '.id OR ' . $history_table . '.parent_account = ' . $accounts_table . '.id) AND ' . $invoice_filter . ')';
            $credit_subquery = '(SELECT SUM(credit) FROM ' . $history_table . ' WHERE (' . $history_table . '.account = ' . $accounts_table . '.id OR ' . $history_table . '.parent_account = ' . $accounts_table . '.id) AND ' . $invoice_filter . ')';
        } else {
            $debit_subquery  = '(SELECT SUM(debit) FROM ' . $history_table . ' WHERE ' . $history_table . '.account = ' . $accounts_table . '.id OR ' . $history_table . '.parent_account = ' . $accounts_table . '.id)';
            $credit_subquery = '(SELECT SUM(credit) FROM ' . $history_table . ' WHERE ' . $history_table . '.account = ' . $accounts_table . '.id OR ' . $history_table . '.parent_account = ' . $accounts_table . '.id)';
        }

        $this->db->select($debit_subquery . ' as debit', false);
        $this->db->select($credit_subquery . ' as credit', false);

        $accounts = $this->db->get()->result_array();

        if (!$accounts) {
            return [];
        }

        $acc_show_account_numbers = 0;
        if ($show_account_numbers) {
            $acc_show_account_numbers = get_option('acc_show_account_numbers');
        }

        $account_types = $this->get_account_types();
        $detail_types  = $this->get_account_type_details();

        $account_type_name = [];
        $detail_type_name  = [];

        foreach ($account_types as $value) {
            $account_type_name[$value['id']] = $value['name'];
        }

        foreach ($detail_types as $value) {
            $detail_type_name[$value['id']] = $value['name'];
        }

        foreach ($accounts as $key => $value) {
            if ($acc_show_account_numbers == 1 && $value['number'] != '') {
                $accounts[$key]['name'] = $value['name'] != '' ? $value['number'] . ' - ' . $value['name'] : $value['number'] . ' - ' . _l($value['key_name']);
            } else {
                $accounts[$key]['name'] = $value['name'] != '' ? $value['name'] : _l($value['key_name']);
            }

            $_account_type_name = isset($account_type_name[$value['account_type_id']]) ? $account_type_name[$value['account_type_id']] : '';
            $_detail_type_name  = isset($detail_type_name[$value['account_detail_type_id']]) ? $detail_type_name[$value['account_detail_type_id']] : '';

            $accounts[$key]['account_type_name'] = $_account_type_name;
            $accounts[$key]['detail_type_name']  = $_detail_type_name;

            $debit_total  = (float) ($value['debit'] ?? 0);
            $credit_total = (float) ($value['credit'] ?? 0);

            $accounts[$key]['debit_total']  = $debit_total;
            $accounts[$key]['credit_total'] = $credit_total;

            $balance_behavior = $this->check_account_debit_credit_plus_minus((object) $value);

            if ($balance_behavior === 'credit') {
                $accounts[$key]['primary_balance'] = $credit_total - $debit_total;
            } else {
                $accounts[$key]['primary_balance'] = $debit_total - $credit_total;
            }
        }

        return $accounts;
    }
