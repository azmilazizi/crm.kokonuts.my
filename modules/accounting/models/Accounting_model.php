        $data['number'] = $this->format_journal_entry_number($data['journal_date'], $data['number'] ?? null);

            return $insert_id;
        $data['number'] = $this->format_journal_entry_number($data['journal_date'], $data['number'] ?? null);

    public function get_journal_entry_next_number($journalDate = null)
        $journalDate = $journalDate ? to_sql_date($journalDate) : date('Y-m-d');
        $timestamp   = strtotime($journalDate) ?: time();
        $monthYear   = date('mY', $timestamp);
        $prefix      = '#JE-' . $monthYear . '-';
        $this->db->select('number');
        $this->db->like('number', $prefix, 'after');

        $numbers = $this->db->get(db_prefix() . 'acc_journal_entries')->result_array();
        $max     = 0;

        foreach ($numbers as $row) {
            $number = $row['number'] ?? '';

            if (preg_match('/^#JE-' . $monthYear . '-(\d+)/', $number, $matches)) {
                $max = max($max, (int) $matches[1]);
            }
        $next = $max + 1;

        return $prefix . str_pad($next, 5, '0', STR_PAD_LEFT);
    }

    /**
     * format journal entry number based on journal date and optional provided number
     * @param string|null $journalDate
     * @param string|null $providedNumber
     * @return string
     */
    public function format_journal_entry_number($journalDate = null, $providedNumber = null)
    {
        $journalDate = $journalDate ? to_sql_date($journalDate) : date('Y-m-d');
        $timestamp   = strtotime($journalDate) ?: time();
        $monthYear   = date('mY', $timestamp);

        if (is_string($providedNumber)) {
            $providedNumber = trim($providedNumber);

            if (preg_match('/^#JE-' . $monthYear . '-\d{5}$/', $providedNumber)) {
                return $providedNumber;
            }
        }

        return $this->get_journal_entry_next_number($journalDate);
                $new_journal_entry_data['number']   = $this->format_journal_entry_number($re_create_at);
                $new_journal_entry_data['number']   = $this->format_journal_entry_number($re_create_at);
