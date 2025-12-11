    public function get_journal_entry_next_number($journalDate = null, $increment = true)
        if ($increment) {
            $this->increment_next_journal_entry_number();
        }
