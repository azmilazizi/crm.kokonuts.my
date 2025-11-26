        $parent_account_exists = $this->db->field_exists('parent_account', $history_table);
        $account_match_clause  = $parent_account_exists
            ? '(' . $history_table . '.account = ' . $accounts_table . '.id OR ' . $history_table . '.parent_account = ' . $accounts_table . '.id)'
            : $history_table . '.account = ' . $accounts_table . '.id;

            $debit_subquery  = '(SELECT SUM(debit) FROM ' . $history_table . ' WHERE ' . $account_match_clause . ' AND ' . $invoice_filter . ')';
            $credit_subquery = '(SELECT SUM(credit) FROM ' . $history_table . ' WHERE ' . $account_match_clause . ' AND ' . $invoice_filter . ')';
            $debit_subquery  = '(SELECT SUM(debit) FROM ' . $history_table . ' WHERE ' . $account_match_clause . ')';
            $credit_subquery = '(SELECT SUM(credit) FROM ' . $history_table . ' WHERE ' . $account_match_clause . ')';
