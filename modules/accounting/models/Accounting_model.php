            $this->increment_next_journal_entry_number();

        $nextNumber = $this->get_next_journal_entry_number();
        return $prefix . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
    /**
     * Get the next journal entry number from options table.
     *
     * @return int
     */
    protected function get_next_journal_entry_number()
    {
        $nextNumber = (int) get_option('next_je_number');

        return $nextNumber > 0 ? $nextNumber : 1;
    }

    /**
     * Increment the next journal entry number in options table.
     *
     * @return void
     */
    protected function increment_next_journal_entry_number()
    {
        $nextNumber = $this->get_next_journal_entry_number();

        update_option('next_je_number', $nextNumber + 1);
    }

                    $this->increment_next_journal_entry_number();

                    $this->increment_next_journal_entry_number();

