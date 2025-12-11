    /**
     * Get the next journal entry number from options, resetting to 1 if missing.
     *
     * @return int
     */
    public function get_next_journal_entry_number()
    {
        $nextNumber = get_option('next_je_number');

        if (!is_numeric($nextNumber) || (int) $nextNumber < 1) {
            $nextNumber = 1;
            update_option('next_je_number', $nextNumber);
        }

        return (int) $nextNumber;
    }

    /**
     * Increment and persist the next journal entry number.
     *
     * @return int
     */
    public function increment_next_journal_entry_number()
    {
        $nextNumber = $this->get_next_journal_entry_number() + 1;

        update_option('next_je_number', $nextNumber);

        return $nextNumber;
    }

