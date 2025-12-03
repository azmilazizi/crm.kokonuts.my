    /**
     * Gets an item by identifier.
     *
     * @param  int $item_id
     * @return object|null
     */
    public function get_item_by_id($item_id)
    {
        $this->db->where('id', $item_id);

        return $this->db->get(db_prefix() . 'items')->row();
    }

    /**
     * Upserts the mapping between an item and its accounts.
     *
     * @param  int      $itemId
     * @param  int      $inventoryAssetAccount
     * @param  int      $expenseAccount
     * @param  int|null $incomeAccount
     * @return array|false
     */
    public function upsert_item_account_mapping($itemId, $inventoryAssetAccount, $expenseAccount, $incomeAccount = null)
    {
        $existing = $this->get_item_automatic($itemId);
        $data     = [
            'item_id'                => $itemId,
            'inventory_asset_account'=> $inventoryAssetAccount,
            'expense_account'        => $expenseAccount,
        ];

        if ($incomeAccount !== null) {
            $data['income_account'] = $incomeAccount;
        }

        if ($existing) {
            $this->db->where('id', $existing->id);
            $this->db->update(db_prefix() . 'acc_item_automatics', $data);
            $mappingId = $existing->id;
        } else {
            $this->db->insert(db_prefix() . 'acc_item_automatics', $data);
            $mappingId = $this->db->insert_id();
        }

        if (!$mappingId) {
            return false;
        }

        return (array) $this->db
            ->where('id', $mappingId)
            ->get(db_prefix() . 'acc_item_automatics')
            ->row();
    }

    /**
     * Deletes the mapping for an item.
     *
     * @param  int $itemId
     * @return void
     */
    public function delete_item_account_mapping($itemId)
    {
        $this->db->where('item_id', $itemId);
        $this->db->delete(db_prefix() . 'acc_item_automatics');
    }

                $node['bill_item'] = $credit_account['id'];
                $amount = isset($credit_amount[$key]) ? str_replace(',', '', $credit_amount[$key]) : '';
