        $history_has_parent_account = $this->db->field_exists('parent_account', $history_table);

        $history_account_condition = $history_has_parent_account
            ? '(' . $history_table . '.account = ' . $accounts_table . '.id OR ' . $history_table . '.parent_account = ' . $accounts_table . '.id)'
            : $history_table . '.account = ' . $accounts_table . '.id';

            $debit_subquery  = '(SELECT SUM(debit) FROM ' . $history_table . ' WHERE ' . $history_account_condition . ' AND ' . $invoice_filter . ')';
            $credit_subquery = '(SELECT SUM(credit) FROM ' . $history_table . ' WHERE ' . $history_account_condition . ' AND ' . $invoice_filter . ')';
            $debit_subquery  = '(SELECT SUM(debit) FROM ' . $history_table . ' WHERE ' . $history_account_condition . ')';
            $credit_subquery = '(SELECT SUM(credit) FROM ' . $history_table . ' WHERE ' . $history_account_condition . ')';
