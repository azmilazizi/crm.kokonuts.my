        $account_history_match = '(' . $history_table . '.account = ' . $accounts_table . '.id'
            . ' OR ' . $history_table . '.account IN (SELECT id FROM ' . $accounts_table
            . ' WHERE parent_account = ' . $accounts_table . '.id))';

            $debit_subquery  = '(SELECT SUM(debit) FROM ' . $history_table . ' WHERE ' . $account_history_match . ' AND ' . $invoice_filter . ')';
            $credit_subquery = '(SELECT SUM(credit) FROM ' . $history_table . ' WHERE ' . $account_history_match . ' AND ' . $invoice_filter . ')';
            $debit_subquery  = '(SELECT SUM(debit) FROM ' . $history_table . ' WHERE ' . $account_history_match . ')';
            $credit_subquery = '(SELECT SUM(credit) FROM ' . $history_table . ' WHERE ' . $account_history_match . ')';
