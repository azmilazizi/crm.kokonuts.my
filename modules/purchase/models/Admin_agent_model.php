<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Admin_agent_model extends App_Model
{
    public function find_accounts(string $query = '', ?int $parentId = null, int $limit = 30): array
    {
        if ($query !== '') {
            $this->db->group_start()->like('name', $query)->or_like('key_name', $query)->or_like('number', $query)->group_end();
        }
        if ($parentId !== null) {
            $this->db->where('parent_account', $parentId);
        }
        return $this->db->select('id, name, key_name, number, parent_account, account_type_id, account_detail_type_id, active')
            ->where('active', 1)->order_by('name', 'ASC')->limit(max(1, min(100, $limit)))
            ->get(db_prefix() . 'acc_accounts')->result_array();
    }

    public function propose_account(int $parentId, string $leafName, ?string $segment = null): array
    {
        $parent = $this->db->where('id', $parentId)->where('active', 1)->get(db_prefix() . 'acc_accounts')->row_array();
        if (!$parent) {
            return ['error' => 'Parent account not found'];
        }
        $siblings = $this->db->where('parent_account', $parentId)->where('active', 1)
            ->order_by('id', 'ASC')->limit(50)->get(db_prefix() . 'acc_accounts')->result_array();
        $prefix = trim($parent['name']);
        $pattern = $prefix . ' - ' . ($segment ? trim($segment) . ' - ' : '');
        // An explicit segment is authoritative. A parent may contain several
        // valid segments, so never let the first database row silently replace
        // the requested segment (for example Main vs Packaging).
        if (!$segment) {
            foreach ($siblings as $sibling) {
                $name = trim($sibling['name']);
                if (strpos($name, $prefix . ' - ') === 0) {
                    $parts = explode(' - ', $name);
                    if (count($parts) >= 3) {
                        $pattern = implode(' - ', array_slice($parts, 0, -1)) . ' - ';
                        break;
                    }
                }
            }
        }
        $proposedName = trim($pattern . trim($leafName));
        $duplicate = $this->db->where('name', $proposedName)->get(db_prefix() . 'acc_accounts')->row_array();
        if ($duplicate) {
            return ['error' => 'An account with this generated name already exists', 'account' => $duplicate];
        }
        $this->db->insert(db_prefix() . 'admin_agent_account_proposals', [
            'parent_account_id' => $parentId, 'proposed_name' => $proposedName,
            'account_type_id' => (int) $parent['account_type_id'],
            'account_detail_type_id' => (int) $parent['account_detail_type_id'],
            'source_pattern' => $pattern, 'status' => 'proposed',
            'request_json' => json_encode(['leaf_name' => $leafName, 'segment' => $segment]),
            'created_by' => get_staff_user_id(), 'created_at' => date('Y-m-d H:i:s'),
        ]);
        return [
            'proposal_id' => (int) $this->db->insert_id(), 'parent' => $parent,
            'proposed_name' => $proposedName, 'pattern' => $pattern,
            'account_type_id' => (int) $parent['account_type_id'],
            'account_detail_type_id' => (int) $parent['account_detail_type_id'],
            'status' => 'proposed',
        ];
    }

    public function approve_account_proposal(int $proposalId): array
    {
        $proposal = $this->db->where('id', $proposalId)->where('status', 'proposed')
            ->get(db_prefix() . 'admin_agent_account_proposals')->row_array();
        if (!$proposal) return ['error' => 'Account proposal not found or already decided'];
        $duplicate = $this->db->where('name', $proposal['proposed_name'])->get(db_prefix() . 'acc_accounts')->row_array();
        if ($duplicate) return ['error' => 'Account name now exists; proposal was not created'];
        $CI = &get_instance();
        $CI->load->model('accounting/accounting_model');
        $accountId = $CI->accounting_model->add_account([
            'name' => $proposal['proposed_name'], 'parent_account' => $proposal['parent_account_id'],
            'account_type_id' => $proposal['account_type_id'], 'account_detail_type_id' => $proposal['account_detail_type_id'],
            'key_name' => null, 'number' => null, 'balance' => 0, 'balance_as_of' => '', 'active' => 1, 'default_account' => 0,
        ]);
        if (!$accountId) return ['error' => 'CRM could not create the account'];
        $this->db->where('id', $proposalId)->update(db_prefix() . 'admin_agent_account_proposals', [
            'status' => 'approved', 'created_account_id' => (int) $accountId,
            'created_by' => get_staff_user_id(), 'decided_at' => date('Y-m-d H:i:s'),
        ]);
        return ['proposal_id' => $proposalId, 'account_id' => (int) $accountId, 'name' => $proposal['proposed_name'], 'status' => 'approved'];
    }

    public function find_categories(string $type, string $query = '', int $limit = 30): array
    {
        $table = $type === 'bill' ? 'acc_bill_categories' : 'expenses_categories';
        if ($query !== '') $this->db->like('name', $query);
        $this->db->limit(max(1, min(100, $limit)))->order_by('name', 'ASC');
        if ($type === 'bill') $this->db->select('id, name, debit_account, credit_account, description, active');
        else $this->db->select('id, name, description');
        return $this->db->get(db_prefix() . $table)->result_array();
    }

    public function propose_category(string $type, string $name, ?int $debit, ?int $credit, string $description = ''): array
    {
        if (!in_array($type, ['expense', 'bill'], true) || trim($name) === '') return ['error' => 'Invalid category type or name'];
        $table = $type === 'bill' ? 'acc_bill_categories' : 'expenses_categories';
        $exists = $this->db->where('name', trim($name))->get(db_prefix() . $table)->row_array();
        if ($exists) return ['error' => 'Category already exists', 'category' => $exists];
        if ($type === 'bill' && (!$debit || !$credit)) return ['error' => 'Bill categories require debit and credit accounts'];
        foreach ([$debit, $credit] as $accountId) {
            if ($accountId && !$this->db->where('id', $accountId)->where('active', 1)->get(db_prefix() . 'acc_accounts')->row()) return ['error' => 'One of the proposed accounts is not active'];
        }
        $this->db->insert(db_prefix() . 'admin_agent_category_proposals', [
            'category_name' => trim($name), 'category_type' => $type, 'debit_account' => $debit,
            'credit_account' => $credit, 'description' => $description, 'created_by' => get_staff_user_id(), 'created_at' => date('Y-m-d H:i:s'),
        ]);
        return ['proposal_id' => (int) $this->db->insert_id(), 'category_type' => $type, 'category_name' => trim($name), 'debit_account' => $debit, 'credit_account' => $credit, 'status' => 'proposed'];
    }

    public function approve_category_proposal(int $proposalId): array
    {
        $proposal = $this->db->where('id', $proposalId)->where('status', 'proposed')->get(db_prefix() . 'admin_agent_category_proposals')->row_array();
        if (!$proposal) return ['error' => 'Category proposal not found or already decided'];
        $table = $proposal['category_type'] === 'bill' ? 'acc_bill_categories' : 'expenses_categories';
        if ($this->db->where('name', $proposal['category_name'])->get(db_prefix() . $table)->row()) return ['error' => 'Category now exists; proposal was not created'];
        $data = ['name' => $proposal['category_name'], 'description' => $proposal['description']];
        if ($proposal['category_type'] === 'bill') { $data['debit_account'] = $proposal['debit_account']; $data['credit_account'] = $proposal['credit_account']; $data['active'] = 1; }
        $this->db->insert(db_prefix() . $table, $data);
        $id = (int) $this->db->insert_id();
        if (!$id) return ['error' => 'CRM could not create the category'];
        $this->db->where('id', $proposalId)->update(db_prefix() . 'admin_agent_category_proposals', ['status' => 'approved', 'decided_at' => date('Y-m-d H:i:s')]);
        return ['proposal_id' => $proposalId, 'category_id' => $id, 'category_type' => $proposal['category_type'], 'category_name' => $proposal['category_name'], 'status' => 'approved'];
    }
}
