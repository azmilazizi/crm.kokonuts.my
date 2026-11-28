<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Configuration proposals for the AI data-entry agent.
 *
 * Every change to the chart of accounts, item accounting mappings or
 * expense/bill categories is a two-step flow: the agent proposes, a human
 * approves (or rejects). Approval re-runs every validation because the
 * chart may have changed since the proposal was made.
 *
 * Proposal storage:
 *  - admin_agent_account_proposals   new chart-of-account accounts
 *  - admin_agent_category_proposals  new expense/bill categories
 *  - admin_agent_config_proposals    item_create, item_mapping_fix, category_edit
 */
class Admin_agent_model extends App_Model
{
    // tblacc_accounts.account_type_id values (Accounting_model::get_account_types()).
    const TYPE_CURRENT_ASSETS = 2;   // inventory accounts live here (detail type 5)
    const TYPE_CASH           = 3;
    const TYPE_FIXED_ASSETS   = 4;
    const TYPE_AP             = 6;
    const TYPE_CREDIT_CARD    = 7;
    const TYPE_CURRENT_LIAB   = 8;
    const TYPE_NONCURR_LIAB   = 9;
    const TYPE_INCOME         = 11;
    const TYPE_OTHER_INCOME   = 12;
    const TYPE_COST_OF_SALES  = 13;
    const TYPE_EXPENSES       = 14;
    const TYPE_OTHER_EXPENSE  = 15;
    const TYPE_BANK           = 16;
    const DETAIL_INVENTORY    = 5;

    // Debit side of an expense/bill category: expense-like or capitalised asset.
    const CATEGORY_DEBIT_TYPES = [4, 13, 14, 15];
    // Credit side of a bill category: a payable.
    const BILL_CREDIT_TYPES = [6, 7, 8];
    // Credit side stored on an expense category mapping: where money comes from.
    const EXPENSE_CREDIT_TYPES = [3, 7, 8, 9, 16];

    private $accountCache = null;

    // ------------------------------------------------------------------
    // Name helpers
    // ------------------------------------------------------------------

    /** Collapse runs of whitespace and trim. */
    public function clean_whitespace(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    /**
     * Comparison key for near-duplicate detection: case-insensitive,
     * whitespace-insensitive, "&" == "and", punctuation ignored.
     */
    public function name_key(string $value): string
    {
        $value = mb_strtolower($this->clean_whitespace($value));
        $value = str_replace('&', ' and ', $value);
        $value = preg_replace('/\s+-\s+/u', ' | ', $value);
        $value = preg_replace('/[^\p{L}\p{N}| ]+/u', '', $value);
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    /** Split "A - B - C" on spaced hyphens only, so "T-Bag" stays whole. */
    public function split_account_name(string $name): array
    {
        $name = $this->clean_whitespace($name);
        return array_map([$this, 'clean_whitespace'], preg_split('/\s+-\s+/u', $name));
    }

    // ------------------------------------------------------------------
    // Account helpers
    // ------------------------------------------------------------------

    private function all_accounts(): array
    {
        if ($this->accountCache === null) {
            $rows = $this->db->select('id, name, parent_account, account_type_id, account_detail_type_id, active')
                ->get(db_prefix() . 'acc_accounts')->result_array();
            $this->accountCache = [];
            foreach ($rows as $row) {
                $row['children'] = 0;
                $this->accountCache[(int) $row['id']] = $row;
            }
            foreach ($this->accountCache as $row) {
                $parent = (int) $row['parent_account'];
                if ($parent && isset($this->accountCache[$parent])) {
                    $this->accountCache[$parent]['children']++;
                }
            }
        }
        return $this->accountCache;
    }

    private function forget_accounts(): void
    {
        $this->accountCache = null;
    }

    private function account(?int $id): ?array
    {
        if (!$id) return null;
        $all = $this->all_accounts();
        return $all[$id] ?? null;
    }

    /**
     * Validate an account for posting: it must exist, be active, be a leaf
     * (never a parent) and be one of the allowed types.
     * Returns an error string, or null when valid.
     */
    public function account_posting_error(?int $id, array $allowedTypes, string $label): ?string
    {
        $account = $this->account($id);
        if (!$account) return "$label: account #$id does not exist";
        if ((int) $account['active'] !== 1) return "$label: account #$id \"{$account['name']}\" is inactive";
        if ($account['children'] > 0) return "$label: account #$id \"{$account['name']}\" is a parent account; choose one of its child accounts";
        if ($allowedTypes && !in_array((int) $account['account_type_id'], $allowedTypes, true)) {
            return "$label: account #$id \"{$account['name']}\" has account type {$account['account_type_id']}, expected one of [" . implode(', ', $allowedTypes) . ']';
        }
        return null;
    }

    private function account_summary(?int $id): ?array
    {
        $a = $this->account($id);
        return $a ? ['id' => (int) $a['id'], 'name' => $a['name'], 'account_type_id' => (int) $a['account_type_id']] : null;
    }

    // ------------------------------------------------------------------
    // Accounts
    // ------------------------------------------------------------------

    public function find_accounts(string $query = '', ?int $parentId = null, int $limit = 30): array
    {
        if ($query !== '') {
            $this->db->group_start()->like('a.name', $query)->or_like('a.key_name', $query)->or_like('a.number', $query)->group_end();
        }
        if ($parentId !== null) {
            $this->db->where('a.parent_account', $parentId);
        }
        return $this->db->select('a.id, a.name, a.key_name, a.number, a.parent_account, a.account_type_id, a.account_detail_type_id, a.active, (SELECT COUNT(*) FROM ' . db_prefix() . 'acc_accounts c WHERE c.parent_account = a.id) AS children', false)
            ->from(db_prefix() . 'acc_accounts a')
            ->where('a.active', 1)->order_by('a.name', 'ASC')->limit(max(1, min(100, $limit)))
            ->get()->result_array();
    }

    /**
     * Work out how the children of a parent are named.
     *
     * Returns style "segmented" (Prefix - Segment - Leaf), "flat"
     * (Prefix - Leaf) or "free" (no shared prefix), the prefix the
     * siblings actually use (e.g. "COS" under "Cost of Sales"), and the
     * segments in use with their most common spelling.
     */
    public function sibling_naming(array $parent): array
    {
        $siblings = [];
        foreach ($this->all_accounts() as $row) {
            if ((int) $row['parent_account'] === (int) $parent['id'] && (int) $row['active'] === 1) {
                $siblings[] = $row;
            }
        }
        if (!$siblings) {
            return ['style' => 'flat', 'prefix' => $this->clean_whitespace($parent['name']), 'segments' => [], 'sibling_count' => 0, 'source' => 'parent_name'];
        }

        $prefixVotes = [];
        foreach ($siblings as $s) {
            $parts = $this->split_account_name($s['name']);
            if (count($parts) >= 2) {
                $prefixVotes[$parts[0]] = ($prefixVotes[$parts[0]] ?? 0) + 1;
            }
        }
        if (!$prefixVotes) {
            return ['style' => 'free', 'prefix' => '', 'segments' => [], 'sibling_count' => count($siblings), 'source' => 'siblings'];
        }
        arsort($prefixVotes);
        $prefix = (string) array_key_first($prefixVotes);

        $flat = 0; $segmented = 0; $segmentSpellings = [];
        foreach ($siblings as $s) {
            $parts = $this->split_account_name($s['name']);
            if ($parts[0] !== $prefix || count($parts) < 2) continue;
            if (count($parts) >= 3) {
                $segmented++;
                $key = $this->name_key($parts[1]);
                $segmentSpellings[$key][$parts[1]] = ($segmentSpellings[$key][$parts[1]] ?? 0) + 1;
            } else {
                $flat++;
            }
        }

        $segments = [];
        foreach ($segmentSpellings as $key => $spellings) {
            arsort($spellings);
            $segments[$key] = (string) array_key_first($spellings);
        }
        asort($segments);

        return [
            'style' => $segmented >= $flat && $segmented > 0 ? 'segmented' : 'flat',
            'prefix' => $prefix, 'segments' => $segments,
            'sibling_count' => count($siblings), 'source' => 'siblings',
        ];
    }

    /** Find an existing account whose name is a near-duplicate of $name. */
    public function find_near_duplicate_account(string $name): ?array
    {
        $key = $this->name_key($name);
        foreach ($this->all_accounts() as $row) {
            if ($this->name_key($row['name']) === $key) {
                return $row;
            }
        }
        return null;
    }

    public function propose_account(int $parentId, string $leafName, ?string $segment = null, bool $allowNewSegment = false): array
    {
        $this->forget_accounts();
        $parent = $this->account($parentId);
        if (!$parent || (int) $parent['active'] !== 1) {
            return ['error' => 'Parent account not found'];
        }

        $leafName = $this->clean_whitespace($leafName);
        if ($leafName === '') return ['error' => 'leaf_name is required'];
        if (preg_match('/\s-\s/u', $leafName)) {
            return ['error' => 'leaf_name must be the item name only; pass the segment separately in "segment"'];
        }
        if ($leafName === mb_strtolower($leafName)) {
            $leafName = mb_convert_case($leafName, MB_CASE_TITLE);
        }
        $segment = $segment !== null ? $this->clean_whitespace($segment) : '';

        $naming = $this->sibling_naming($parent);
        $parentSummary = ['id' => (int) $parent['id'], 'name' => $parent['name']];

        if ($naming['style'] === 'segmented') {
            if ($segment === '') {
                return [
                    'error' => 'Accounts under this parent are grouped by segment; specify one of the valid segments',
                    'valid_segments' => array_values($naming['segments']),
                    'example' => $naming['prefix'] . ' - <Segment> - ' . $leafName,
                    'parent' => $parentSummary,
                ];
            }
            $segmentKey = $this->name_key($segment);
            if (isset($naming['segments'][$segmentKey])) {
                $segment = $naming['segments'][$segmentKey];
            } elseif (!$allowNewSegment) {
                return [
                    'error' => "Segment \"$segment\" is not used under this parent. Use one of the valid segments, or pass allow_new_segment=true to start a new one",
                    'valid_segments' => array_values($naming['segments']),
                    'parent' => $parentSummary,
                ];
            }
            $pattern = $naming['prefix'] . ' - ' . $segment . ' - ';
        } elseif ($naming['style'] === 'flat') {
            if ($segment !== '') {
                return ['error' => 'Accounts under this parent do not use segments; omit "segment"', 'example' => $naming['prefix'] . ' - ' . $leafName, 'parent' => $parentSummary];
            }
            $pattern = $naming['prefix'] . ' - ';
        } else {
            if ($segment !== '') {
                return ['error' => 'Accounts under this parent have free-form names without segments; omit "segment"', 'parent' => $parentSummary];
            }
            $pattern = '';
        }

        $proposedName = $this->clean_whitespace($pattern . $leafName);

        $existing = $this->find_near_duplicate_account($proposedName);
        if ($existing) {
            return [
                'status' => 'exists',
                'message' => 'An account with this name (ignoring case, spacing and punctuation) already exists; use it instead of creating a new one',
                'account' => $this->account_summary((int) $existing['id']) + ['parent_account' => (int) $existing['parent_account'], 'active' => (int) $existing['active']],
            ];
        }
        foreach ($this->pending_rows('admin_agent_account_proposals') as $pending) {
            if ($this->name_key($pending['proposed_name']) === $this->name_key($proposedName)) {
                return ['status' => 'already_proposed', 'message' => 'The same account is already waiting for approval', 'proposal_id' => (int) $pending['id'], 'proposed_name' => $pending['proposed_name']];
            }
        }

        $this->db->insert(db_prefix() . 'admin_agent_account_proposals', [
            'parent_account_id' => $parentId, 'proposed_name' => $proposedName,
            'account_type_id' => (int) $parent['account_type_id'],
            'account_detail_type_id' => (int) $parent['account_detail_type_id'],
            'source_pattern' => $pattern, 'status' => 'proposed',
            'request_json' => json_encode(['leaf_name' => $leafName, 'segment' => $segment !== '' ? $segment : null, 'naming' => $naming['style'], 'prefix' => $naming['prefix']]),
            'created_by' => get_staff_user_id(), 'created_at' => date('Y-m-d H:i:s'),
        ]);
        return [
            'proposal_id' => (int) $this->db->insert_id(), 'parent' => $parentSummary,
            'proposed_name' => $proposedName, 'pattern' => $pattern, 'naming_style' => $naming['style'],
            'account_type_id' => (int) $parent['account_type_id'],
            'account_detail_type_id' => (int) $parent['account_detail_type_id'],
            'status' => 'proposed',
        ];
    }

    public function approve_account_proposal(int $proposalId): array
    {
        $this->forget_accounts();
        $proposal = $this->db->where('id', $proposalId)->where('status', 'proposed')
            ->get(db_prefix() . 'admin_agent_account_proposals')->row_array();
        if (!$proposal) return ['error' => 'Account proposal not found or already decided'];
        $parent = $this->account((int) $proposal['parent_account_id']);
        if (!$parent || (int) $parent['active'] !== 1) return ['error' => 'Parent account no longer exists or is inactive'];
        $duplicate = $this->find_near_duplicate_account($proposal['proposed_name']);
        if ($duplicate) return ['error' => 'An account with this name now exists; proposal was not created', 'account' => $this->account_summary((int) $duplicate['id'])];
        $CI = &get_instance();
        $CI->load->model('accounting/accounting_model');
        $accountId = $CI->accounting_model->add_account([
            'name' => $proposal['proposed_name'], 'parent_account' => $proposal['parent_account_id'],
            'account_type_id' => $proposal['account_type_id'], 'account_detail_type_id' => $proposal['account_detail_type_id'],
            'key_name' => null, 'number' => null, 'balance' => 0, 'balance_as_of' => '', 'active' => 1, 'default_account' => 0,
        ]);
        if (!$accountId) return ['error' => 'CRM could not create the account'];
        $this->forget_accounts();
        $this->db->where('id', $proposalId)->update(db_prefix() . 'admin_agent_account_proposals', [
            'status' => 'approved', 'created_account_id' => (int) $accountId,
            'decided_by' => get_staff_user_id() ?: null, 'decided_at' => date('Y-m-d H:i:s'),
        ]);
        return ['proposal_id' => $proposalId, 'account_id' => (int) $accountId, 'name' => $proposal['proposed_name'], 'status' => 'approved'];
    }

    // ------------------------------------------------------------------
    // Categories
    // ------------------------------------------------------------------

    public function find_categories(string $type, string $query = '', int $limit = 30): array
    {
        $limit = max(1, min(100, $limit));
        if ($type === 'bill') {
            if ($query !== '') $this->db->like('name', $query);
            return $this->db->select('id, name, debit_account, credit_account, description, active')
                ->order_by('name', 'ASC')->limit($limit)->get(db_prefix() . 'acc_bill_categories')->result_array();
        }
        if ($query !== '') $this->db->like('c.name', $query);
        return $this->db->select('c.id, c.name, c.description, m.deposit_to AS debit_account, m.payment_account AS credit_account')
            ->from(db_prefix() . 'expenses_categories c')
            ->join(db_prefix() . 'acc_expense_category_mappings m', 'm.category_id = c.id', 'left')
            ->order_by('c.name', 'ASC')->limit($limit)->get()->result_array();
    }

    private function category_table(string $type): string
    {
        return db_prefix() . ($type === 'bill' ? 'acc_bill_categories' : 'expenses_categories');
    }

    /** Category with the same name ignoring case/whitespace, excluding $excludeId. */
    private function find_category_by_name(string $type, string $name, ?int $excludeId = null): ?array
    {
        $key = $this->name_key($name);
        foreach ($this->db->select('id, name')->get($this->category_table($type))->result_array() as $row) {
            if ((int) $row['id'] !== (int) $excludeId && $this->name_key($row['name']) === $key) {
                return $row;
            }
        }
        return null;
    }

    /**
     * Debit/credit rules shared by new categories and category edits.
     * Expense categories: debit (the expense account) is required — it is
     * what tblacc_expense_category_mappings.deposit_to holds and what the
     * expense posting debits. Credit is optional: expense postings take the
     * credit side from the payment mode mapping.
     */
    private function category_account_errors(string $type, ?int $debit, ?int $credit): array
    {
        $errors = [];
        if (!$debit) {
            $errors[] = 'debit_account is required';
        } elseif ($e = $this->account_posting_error($debit, self::CATEGORY_DEBIT_TYPES, 'debit_account')) {
            $errors[] = $e;
        }
        if ($type === 'bill') {
            if (!$credit) $errors[] = 'credit_account is required for bill categories';
            elseif ($e = $this->account_posting_error($credit, self::BILL_CREDIT_TYPES, 'credit_account')) $errors[] = $e;
        } elseif ($credit && ($e = $this->account_posting_error($credit, self::EXPENSE_CREDIT_TYPES, 'credit_account'))) {
            $errors[] = $e;
        }
        return $errors;
    }

    public function propose_category(string $type, string $name, ?int $debit, ?int $credit, string $description = ''): array
    {
        $this->forget_accounts();
        $name = $this->clean_whitespace($name);
        if (!in_array($type, ['expense', 'bill'], true) || $name === '') return ['error' => 'Invalid category type or name'];
        if ($exists = $this->find_category_by_name($type, $name)) return ['status' => 'exists', 'message' => 'Category already exists', 'category' => $exists];
        if ($errors = $this->category_account_errors($type, $debit, $credit)) return ['error' => 'Invalid category accounts', 'details' => $errors];
        foreach ($this->pending_rows('admin_agent_category_proposals') as $pending) {
            if ($pending['category_type'] === $type && $this->name_key($pending['category_name']) === $this->name_key($name)) {
                return ['status' => 'already_proposed', 'proposal_id' => (int) $pending['id']];
            }
        }
        $this->db->insert(db_prefix() . 'admin_agent_category_proposals', [
            'category_name' => $name, 'category_type' => $type, 'debit_account' => $debit,
            'credit_account' => $credit ?: null, 'description' => $description, 'created_by' => get_staff_user_id(), 'created_at' => date('Y-m-d H:i:s'),
        ]);
        return [
            'proposal_id' => (int) $this->db->insert_id(), 'category_type' => $type, 'category_name' => $name,
            'debit_account' => $this->account_summary($debit), 'credit_account' => $this->account_summary($credit), 'status' => 'proposed',
        ];
    }

    /** Create or update the accounting mapping of an expense category. */
    private function save_expense_category_mapping(int $categoryId, int $debit, ?int $credit): void
    {
        $table = db_prefix() . 'acc_expense_category_mappings';
        $existing = $this->db->where('category_id', $categoryId)->get($table)->row_array();
        if ($existing) {
            $update = ['deposit_to' => $debit];
            if ($credit) $update['payment_account'] = $credit;
            $this->db->where('id', $existing['id'])->update($table, $update);
        } else {
            $this->db->insert($table, [
                'category_id' => $categoryId, 'deposit_to' => $debit,
                'payment_account' => $credit ?: 0, 'preferred_payment_method' => 0,
            ]);
        }
    }

    public function approve_category_proposal(int $proposalId): array
    {
        $this->forget_accounts();
        $proposal = $this->db->where('id', $proposalId)->where('status', 'proposed')->get(db_prefix() . 'admin_agent_category_proposals')->row_array();
        if (!$proposal) return ['error' => 'Category proposal not found or already decided'];
        $type = $proposal['category_type'];
        $debit = $proposal['debit_account'] ? (int) $proposal['debit_account'] : null;
        $credit = $proposal['credit_account'] ? (int) $proposal['credit_account'] : null;
        if ($this->find_category_by_name($type, $proposal['category_name'])) return ['error' => 'Category now exists; proposal was not created'];
        if ($errors = $this->category_account_errors($type, $debit, $credit)) {
            return ['error' => 'Proposal accounts are no longer valid; reject it and propose again', 'details' => $errors];
        }

        $this->db->trans_begin();
        $data = ['name' => $proposal['category_name'], 'description' => $proposal['description']];
        if ($type === 'bill') { $data['debit_account'] = $debit; $data['credit_account'] = $credit; $data['active'] = 1; }
        $this->db->insert($this->category_table($type), $data);
        $id = (int) $this->db->insert_id();
        if ($id && $type === 'expense') {
            $this->save_expense_category_mapping($id, $debit, $credit);
        }
        if (!$id || $this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return ['error' => 'CRM could not create the category'];
        }
        $this->db->where('id', $proposalId)->update(db_prefix() . 'admin_agent_category_proposals', [
            'status' => 'approved', 'created_category_id' => $id, 'decided_by' => get_staff_user_id() ?: null, 'decided_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->trans_commit();
        return [
            'proposal_id' => $proposalId, 'category_id' => $id, 'category_type' => $type, 'category_name' => $proposal['category_name'],
            'accounting_mapping' => ['debit_account' => $this->account_summary($debit), 'credit_account' => $this->account_summary($credit)],
            'status' => 'approved',
        ];
    }

    /** Current state of a category, in debit/credit terms. */
    private function category_state(string $type, int $categoryId): ?array
    {
        if ($type === 'bill') {
            $row = $this->db->where('id', $categoryId)->get(db_prefix() . 'acc_bill_categories')->row_array();
            return $row ? ['id' => (int) $row['id'], 'name' => $row['name'], 'debit_account' => (int) $row['debit_account'] ?: null, 'credit_account' => (int) $row['credit_account'] ?: null] : null;
        }
        $row = $this->db->where('id', $categoryId)->get(db_prefix() . 'expenses_categories')->row_array();
        if (!$row) return null;
        $map = $this->db->where('category_id', $categoryId)->get(db_prefix() . 'acc_expense_category_mappings')->row_array();
        return [
            'id' => (int) $row['id'], 'name' => $row['name'],
            'debit_account' => $map ? ((int) $map['deposit_to'] ?: null) : null,
            'credit_account' => $map ? ((int) $map['payment_account'] ?: null) : null,
        ];
    }

    /** Validate a category edit and return the resulting state or errors. */
    private function category_edit_plan(array $payload): array
    {
        $type = $payload['category_type'] ?? '';
        if (!in_array($type, ['expense', 'bill'], true)) return ['error' => 'category_type must be expense or bill'];
        $before = $this->category_state($type, (int) ($payload['category_id'] ?? 0));
        if (!$before) return ['error' => 'Category not found'];

        $after = $before;
        if (isset($payload['name']) && $payload['name'] !== null) {
            $after['name'] = $this->clean_whitespace((string) $payload['name']);
            if ($after['name'] === '') return ['error' => 'name cannot be empty'];
            if ($dup = $this->find_category_by_name($type, $after['name'], $before['id'])) return ['error' => 'Another category already has this name', 'category' => $dup];
        }
        if (!empty($payload['debit_account'])) $after['debit_account'] = (int) $payload['debit_account'];
        if (!empty($payload['credit_account'])) $after['credit_account'] = (int) $payload['credit_account'];
        if ($after == $before) return ['error' => 'Nothing to change; the category already has these values'];

        // A debit is always required and checked so the category can post.
        // An expense category's stored credit is never used for posting (the
        // credit side comes from the payment mode mapping); all existing
        // mappings hold the legacy parent account 1 there. Check it only
        // when this edit sets it, so it does not block unrelated edits.
        // Bill credits are posted, so they are always checked.
        $creditToCheck = $after['credit_account'];
        if ($type === 'expense' && $after['credit_account'] === $before['credit_account']) {
            $creditToCheck = null;
        }
        if ($errors = $this->category_account_errors($type, $after['debit_account'], $creditToCheck)) {
            return ['error' => 'Invalid category accounts', 'details' => $errors];
        }
        return ['type' => $type, 'before' => $before, 'after' => $after];
    }

    public function propose_category_edit(string $type, int $categoryId, ?string $name, ?int $debit, ?int $credit, string $reason = ''): array
    {
        $this->forget_accounts();
        $payload = ['category_type' => $type, 'category_id' => $categoryId, 'name' => $name, 'debit_account' => $debit, 'credit_account' => $credit];
        $plan = $this->category_edit_plan($payload);
        if (isset($plan['error'])) return $plan;
        foreach ($this->pending_rows('admin_agent_config_proposals', 'category_edit') as $pending) {
            $p = json_decode($pending['payload_json'], true);
            if (($p['category_type'] ?? '') === $type && (int) ($p['category_id'] ?? 0) === $categoryId) {
                return ['status' => 'already_proposed', 'message' => 'This category already has a pending edit; approve or reject it first', 'proposal_id' => (int) $pending['id']];
            }
        }
        $id = $this->insert_config_proposal('category_edit', $payload, $plan['before'], $reason);
        return ['proposal_id' => $id, 'proposal_type' => 'category_edit', 'before' => $this->describe_category_state($plan['before']), 'after' => $this->describe_category_state($plan['after']), 'status' => 'proposed'];
    }

    private function describe_category_state(array $state): array
    {
        return ['id' => $state['id'], 'name' => $state['name'], 'debit_account' => $this->account_summary($state['debit_account']), 'credit_account' => $this->account_summary($state['credit_account'])];
    }

    public function approve_category_edit_proposal(int $proposalId): array
    {
        $this->forget_accounts();
        $proposal = $this->get_pending_config_proposal($proposalId, 'category_edit');
        if (!$proposal) return ['error' => 'Category edit proposal not found or already decided'];
        $plan = $this->category_edit_plan(json_decode($proposal['payload_json'], true));
        if (isset($plan['error'])) return ['error' => 'Proposal is no longer valid; reject it and propose again', 'details' => $plan];
        $type = $plan['type']; $after = $plan['after'];

        $this->db->trans_begin();
        if ($type === 'bill') {
            $this->db->where('id', $after['id'])->update(db_prefix() . 'acc_bill_categories', [
                'name' => $after['name'], 'debit_account' => $after['debit_account'], 'credit_account' => $after['credit_account'],
            ]);
        } else {
            $this->db->where('id', $after['id'])->update(db_prefix() . 'expenses_categories', ['name' => $after['name']]);
            $this->save_expense_category_mapping($after['id'], (int) $after['debit_account'], $after['credit_account'] ? (int) $after['credit_account'] : null);
        }
        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return ['error' => 'CRM could not update the category'];
        }
        $this->decide_config_proposal($proposalId, 'approved', null, $after);
        $this->db->trans_commit();
        return ['proposal_id' => $proposalId, 'category_type' => $type, 'before' => $this->describe_category_state($plan['before']), 'after' => $this->describe_category_state($after), 'status' => 'approved'];
    }

    // ------------------------------------------------------------------
    // Items and item accounting mappings
    // ------------------------------------------------------------------

    /**
     * Validate the three accounting slots of an item.
     * - inventory: current-asset inventory account (type 2, detail 5)
     * - cogs:      cost-of-sales account (type 13)
     * - income:    income or other income (type 11/12)
     * All must be active leaf accounts. Inventory and cost-of-sales accounts
     * are item-specific, so neither may be used in any slot of another item.
     */
    public function item_account_errors(?int $inventory, ?int $cogs, ?int $income, ?int $itemId = null): array
    {
        $errors = [];
        if ($e = $this->account_posting_error($inventory, [self::TYPE_CURRENT_ASSETS], 'inventory_account')) $errors[] = $e;
        elseif ((int) $this->account($inventory)['account_detail_type_id'] !== self::DETAIL_INVENTORY) $errors[] = "inventory_account: account #$inventory is not an inventory account";
        if ($e = $this->account_posting_error($cogs, [self::TYPE_COST_OF_SALES], 'cogs_account')) $errors[] = $e;
        if ($e = $this->account_posting_error($income, [self::TYPE_INCOME, self::TYPE_OTHER_INCOME], 'income_account')) $errors[] = $e;
        if ($errors) return $errors;

        $this->db->select('m.item_id, m.inventory_asset_account, m.expense_account, m.income_account, i.description')
            ->from(db_prefix() . 'acc_item_automatics m')
            ->join(db_prefix() . 'items i', 'i.id = m.item_id', 'left')
            ->group_start()
                ->where_in('m.inventory_asset_account', [$inventory, $cogs])
                ->or_where_in('m.expense_account', [$inventory, $cogs])
                ->or_where_in('m.income_account', [$inventory, $cogs])
            ->group_end();
        if ($itemId) $this->db->where('m.item_id !=', $itemId);
        foreach ($this->db->get()->result_array() as $row) {
            foreach (['inventory_account' => $inventory, 'cogs_account' => $cogs] as $label => $id) {
                if (in_array($id, [(int) $row['inventory_asset_account'], (int) $row['expense_account'], (int) $row['income_account']], true)) {
                    $errors[] = "$label: account #$id \"" . $this->account($id)['name'] . "\" is already mapped to item #{$row['item_id']} \"{$row['description']}\"";
                }
            }
        }
        return array_values(array_unique($errors));
    }

    /** Non-blocking hints: account segment/leaf should match the item. */
    private function item_account_warnings(string $description, ?string $groupName, int $inventory, int $cogs): array
    {
        $warnings = [];
        foreach (['inventory_account' => $inventory, 'cogs_account' => $cogs] as $label => $id) {
            $parts = $this->split_account_name($this->account($id)['name']);
            if ($groupName !== null && count($parts) >= 3 && $this->name_key($parts[1]) !== $this->name_key($groupName)) {
                $warnings[] = "$label segment \"{$parts[1]}\" differs from the item group \"$groupName\"";
            }
            if ($this->name_key(end($parts)) !== $this->name_key($description)) {
                $warnings[] = "$label name \"" . end($parts) . "\" differs from the item name \"$description\"";
            }
        }
        return $warnings;
    }

    private function item_create_plan(array $p): array
    {
        $description = $this->clean_whitespace((string) ($p['description'] ?? ''));
        $code = strtoupper(str_replace(' ', '-', $this->clean_whitespace((string) ($p['commodity_code'] ?? ''))));
        $errors = [];
        if ($description === '') $errors[] = 'description is required';
        if ($code === '') $errors[] = 'commodity_code is required';

        $group = $this->db->where('id', (int) ($p['group_id'] ?? 0))->get(db_prefix() . 'items_groups')->row_array();
        if (!$group) $errors[] = 'group_id: item group not found';
        $unit = $this->db->where('unit_type_id', (int) ($p['unit_id'] ?? 0))->get(db_prefix() . 'ware_unit_type')->row_array();
        if (!$unit) $errors[] = 'unit_id: unit not found';

        if ($description !== '' || $code !== '') {
            foreach ($this->db->select('id, description, commodity_code')->where('active', 1)->get(db_prefix() . 'items')->result_array() as $item) {
                if ($description !== '' && $this->name_key($item['description']) === $this->name_key($description)) {
                    $errors[] = "An active item with this name already exists: #{$item['id']} \"{$item['description']}\"";
                }
                if ($code !== '' && strcasecmp(trim((string) $item['commodity_code']), $code) === 0) {
                    $errors[] = "commodity_code $code is already used by item #{$item['id']} \"{$item['description']}\"";
                }
            }
        }
        $inventory = (int) ($p['inventory_account'] ?? 0); $cogs = (int) ($p['cogs_account'] ?? 0); $income = (int) ($p['income_account'] ?? 0);
        $errors = array_merge($errors, $this->item_account_errors($inventory, $cogs, $income));
        if ($errors) return ['error' => 'Item proposal is not valid', 'details' => array_values(array_unique($errors))];

        $price = isset($p['purchase_price']) && $p['purchase_price'] !== null ? round((float) $p['purchase_price'], 2) : 0.0;
        return [
            'item' => [
                'description' => $description, 'long_description' => '', 'rate' => 0, 'tax' => 0, 'tax2' => 0,
                'unit' => $unit['unit_name'], 'unit_id' => (int) $unit['unit_type_id'], 'group_id' => (int) $group['id'],
                'commodity_code' => $code, 'sku_code' => $code, 'sku_name' => $description,
                'purchase_price' => $price, 'active' => 1,
                'can_be_purchased' => 'can_be_purchased', 'can_be_inventory' => 'can_be_inventory',
            ],
            'mapping' => ['inventory_asset_account' => $inventory, 'expense_account' => $cogs, 'income_account' => $income],
            'warnings' => $this->item_account_warnings($description, $group['name'], $inventory, $cogs),
        ];
    }

    private function describe_mapping(array $m): array
    {
        return [
            'inventory_account' => $this->account_summary((int) $m['inventory_asset_account'] ?: null),
            'cogs_account' => $this->account_summary((int) $m['expense_account'] ?: null),
            'income_account' => $this->account_summary((int) $m['income_account'] ?: null),
        ];
    }

    public function propose_item_with_mapping(array $p, string $reason = ''): array
    {
        $this->forget_accounts();
        $payload = [
            'description' => $p['description'] ?? '', 'commodity_code' => $p['commodity_code'] ?? '',
            'group_id' => (int) ($p['group_id'] ?? 0), 'unit_id' => (int) ($p['unit_id'] ?? 0),
            'purchase_price' => $p['purchase_price'] ?? null,
            'inventory_account' => (int) ($p['inventory_account'] ?? 0), 'cogs_account' => (int) ($p['cogs_account'] ?? 0),
            'income_account' => (int) ($p['income_account'] ?? 0),
        ];
        $plan = $this->item_create_plan($payload);
        if (isset($plan['error'])) return $plan;
        foreach ($this->pending_rows('admin_agent_config_proposals', 'item_create') as $pending) {
            $other = json_decode($pending['payload_json'], true);
            $sameName = $this->name_key((string) $other['description']) === $this->name_key($plan['item']['description']);
            $sharedAccount = array_intersect([$payload['inventory_account'], $payload['cogs_account']], [(int) $other['inventory_account'], (int) $other['cogs_account']]);
            if ($sameName || $sharedAccount) {
                return ['status' => 'already_proposed', 'message' => 'A pending item proposal already uses this name or these accounts', 'proposal_id' => (int) $pending['id']];
            }
        }
        $id = $this->insert_config_proposal('item_create', $payload, null, $reason);
        return [
            'proposal_id' => $id, 'proposal_type' => 'item_create', 'item' => $plan['item'],
            'mapping' => $this->describe_mapping($plan['mapping']), 'warnings' => $plan['warnings'], 'status' => 'proposed',
        ];
    }

    private function current_item_mapping(int $itemId): ?array
    {
        return $this->db->where('item_id', $itemId)->order_by('id', 'ASC')->get(db_prefix() . 'acc_item_automatics')->row_array() ?: null;
    }

    private function item_mapping_fix_plan(array $p): array
    {
        $itemId = (int) ($p['item_id'] ?? 0);
        $item = $this->db->select('id, description, group_id')->where('id', $itemId)->get(db_prefix() . 'items')->row_array();
        if (!$item) return ['error' => 'Item not found'];
        $current = $this->current_item_mapping($itemId) ?: ['inventory_asset_account' => 0, 'expense_account' => 0, 'income_account' => 0];
        $after = [
            'inventory_asset_account' => !empty($p['inventory_account']) ? (int) $p['inventory_account'] : (int) $current['inventory_asset_account'],
            'expense_account' => !empty($p['cogs_account']) ? (int) $p['cogs_account'] : (int) $current['expense_account'],
            'income_account' => !empty($p['income_account']) ? (int) $p['income_account'] : (int) $current['income_account'],
        ];
        $before = ['inventory_asset_account' => (int) $current['inventory_asset_account'], 'expense_account' => (int) $current['expense_account'], 'income_account' => (int) $current['income_account']];
        if ($after === $before) return ['error' => 'Nothing to change; the item already has this mapping'];
        // The whole resulting mapping must be valid, including slots that are
        // not being changed, so a fix never leaves a parent or wrong-type
        // account behind.
        if ($errors = $this->item_account_errors($after['inventory_asset_account'] ?: null, $after['expense_account'] ?: null, $after['income_account'] ?: null, $itemId)) {
            return ['error' => 'Resulting mapping is not valid (all three slots are checked)', 'details' => $errors, 'current' => $this->describe_mapping($before)];
        }
        $group = $this->db->where('id', (int) $item['group_id'])->get(db_prefix() . 'items_groups')->row_array();
        return [
            'item' => $item, 'before' => $before, 'after' => $after,
            'warnings' => $this->item_account_warnings($item['description'], $group['name'] ?? null, $after['inventory_asset_account'], $after['expense_account']),
        ];
    }

    public function propose_item_mapping_fix(int $itemId, ?int $inventory, ?int $cogs, ?int $income, string $reason = ''): array
    {
        $this->forget_accounts();
        $payload = ['item_id' => $itemId, 'inventory_account' => $inventory, 'cogs_account' => $cogs, 'income_account' => $income];
        $plan = $this->item_mapping_fix_plan($payload);
        if (isset($plan['error'])) return $plan;
        foreach ($this->pending_rows('admin_agent_config_proposals', 'item_mapping_fix') as $pending) {
            if ((int) (json_decode($pending['payload_json'], true)['item_id'] ?? 0) === $itemId) {
                return ['status' => 'already_proposed', 'message' => 'This item already has a pending mapping fix; approve or reject it first', 'proposal_id' => (int) $pending['id']];
            }
        }
        $id = $this->insert_config_proposal('item_mapping_fix', $payload, $plan['before'], $reason);
        return [
            'proposal_id' => $id, 'proposal_type' => 'item_mapping_fix',
            'item' => ['id' => (int) $plan['item']['id'], 'description' => $plan['item']['description']],
            'before' => $this->describe_mapping($plan['before']), 'after' => $this->describe_mapping($plan['after']),
            'warnings' => $plan['warnings'], 'status' => 'proposed',
        ];
    }

    /** Approves item_create and item_mapping_fix proposals. */
    public function approve_item_proposal(int $proposalId): array
    {
        $this->forget_accounts();
        $proposal = $this->get_pending_config_proposal($proposalId, ['item_create', 'item_mapping_fix']);
        if (!$proposal) return ['error' => 'Item proposal not found or already decided'];
        $payload = json_decode($proposal['payload_json'], true);

        if ($proposal['proposal_type'] === 'item_create') {
            $plan = $this->item_create_plan($payload);
            if (isset($plan['error'])) return ['error' => 'Proposal is no longer valid; reject it and propose again', 'details' => $plan['details'] ?? []];
            $this->db->trans_begin();
            $this->db->insert(db_prefix() . 'items', $plan['item']);
            $itemId = (int) $this->db->insert_id();
            if ($itemId) {
                $this->db->insert(db_prefix() . 'acc_item_automatics', ['item_id' => $itemId] + $plan['mapping']);
            }
            if (!$itemId || $this->db->trans_status() === false) {
                $this->db->trans_rollback();
                return ['error' => 'CRM could not create the item'];
            }
            $this->decide_config_proposal($proposalId, 'approved', null, ['item_id' => $itemId] + $plan['mapping']);
            $this->db->trans_commit();
            return ['proposal_id' => $proposalId, 'item_id' => $itemId, 'description' => $plan['item']['description'], 'mapping' => $this->describe_mapping($plan['mapping']), 'status' => 'approved'];
        }

        $plan = $this->item_mapping_fix_plan($payload);
        if (isset($plan['error'])) return ['error' => 'Proposal is no longer valid; reject it and propose again', 'details' => $plan];
        $itemId = (int) $plan['item']['id'];
        $this->db->trans_begin();
        $existing = $this->current_item_mapping($itemId);
        if ($existing) {
            $this->db->where('id', $existing['id'])->update(db_prefix() . 'acc_item_automatics', $plan['after']);
        } else {
            $this->db->insert(db_prefix() . 'acc_item_automatics', ['item_id' => $itemId] + $plan['after']);
        }
        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return ['error' => 'CRM could not update the item mapping'];
        }
        $this->decide_config_proposal($proposalId, 'approved', null, $plan['after']);
        $this->db->trans_commit();
        return [
            'proposal_id' => $proposalId, 'item_id' => $itemId, 'before' => $this->describe_mapping($plan['before']),
            'after' => $this->describe_mapping($plan['after']), 'status' => 'approved',
            'note' => 'Applies to postings made from now on; existing ledger entries are unchanged.',
        ];
    }

    // ------------------------------------------------------------------
    // Pending list and rejection
    // ------------------------------------------------------------------

    private function pending_rows(string $table, $types = null): array
    {
        $this->db->where('status', 'proposed');
        if ($types !== null) $this->db->where_in('proposal_type', (array) $types);
        return $this->db->order_by('id', 'ASC')->get(db_prefix() . $table)->result_array();
    }

    private function insert_config_proposal(string $type, array $payload, ?array $before, string $reason): int
    {
        $this->db->insert(db_prefix() . 'admin_agent_config_proposals', [
            'proposal_type' => $type, 'payload_json' => json_encode($payload),
            'before_json' => $before !== null ? json_encode($before) : null,
            'reason' => $reason !== '' ? $reason : null, 'status' => 'proposed',
            'created_by' => get_staff_user_id() ?: null, 'created_at' => date('Y-m-d H:i:s'),
        ]);
        return (int) $this->db->insert_id();
    }

    private function get_pending_config_proposal(int $id, $types): ?array
    {
        return $this->db->where('id', $id)->where('status', 'proposed')->where_in('proposal_type', (array) $types)
            ->get(db_prefix() . 'admin_agent_config_proposals')->row_array() ?: null;
    }

    private function decide_config_proposal(int $id, string $status, ?string $note, ?array $result): void
    {
        $this->db->where('id', $id)->update(db_prefix() . 'admin_agent_config_proposals', [
            'status' => $status, 'decision_note' => $note, 'result_json' => $result !== null ? json_encode($result) : null,
            'decided_by' => get_staff_user_id() ?: null, 'decided_at' => date('Y-m-d H:i:s'),
        ]);
    }

    const PROPOSAL_KINDS = [
        'account' => 'admin_agent_account_proposals',
        'category' => 'admin_agent_category_proposals',
        'item' => 'admin_agent_config_proposals',
        'category_edit' => 'admin_agent_config_proposals',
    ];

    public function list_pending_proposals(?string $kind = null): array
    {
        $kinds = $kind ? [$kind] : array_keys(self::PROPOSAL_KINDS);
        $out = [];
        foreach ($kinds as $k) {
            if (!isset(self::PROPOSAL_KINDS[$k])) return ['error' => 'kind must be one of: ' . implode(', ', array_keys(self::PROPOSAL_KINDS))];
            if ($k === 'account') {
                foreach ($this->pending_rows('admin_agent_account_proposals') as $r) {
                    $out[] = ['kind' => 'account', 'proposal_id' => (int) $r['id'], 'summary' => $r['proposed_name'], 'parent_account_id' => (int) $r['parent_account_id'], 'created_at' => $r['created_at'], 'approve_with' => 'approve_account_proposal'];
                }
            } elseif ($k === 'category') {
                foreach ($this->pending_rows('admin_agent_category_proposals') as $r) {
                    $out[] = ['kind' => 'category', 'proposal_id' => (int) $r['id'], 'summary' => "New {$r['category_type']} category \"{$r['category_name']}\"", 'debit_account' => $this->account_summary((int) $r['debit_account'] ?: null), 'credit_account' => $this->account_summary((int) $r['credit_account'] ?: null), 'created_at' => $r['created_at'], 'approve_with' => 'approve_category_proposal'];
                }
            } else {
                $types = $k === 'item' ? ['item_create', 'item_mapping_fix'] : ['category_edit'];
                foreach ($this->pending_rows('admin_agent_config_proposals', $types) as $r) {
                    $p = json_decode($r['payload_json'], true);
                    $summary = $r['proposal_type'] === 'item_create' ? "New item \"{$p['description']}\""
                        : ($r['proposal_type'] === 'item_mapping_fix' ? "Fix accounting mapping of item #{$p['item_id']}" : "Edit {$p['category_type']} category #{$p['category_id']}");
                    $out[] = ['kind' => $k, 'proposal_type' => $r['proposal_type'], 'proposal_id' => (int) $r['id'], 'summary' => $summary, 'payload' => $p, 'reason' => $r['reason'], 'created_at' => $r['created_at'], 'approve_with' => $k === 'item' ? 'approve_item_proposal' : 'approve_category_edit_proposal'];
                }
            }
        }
        return ['pending' => $out, 'count' => count($out)];
    }

    public function reject_proposal(string $kind, int $proposalId, string $reason): array
    {
        if (!isset(self::PROPOSAL_KINDS[$kind])) return ['error' => 'kind must be one of: ' . implode(', ', array_keys(self::PROPOSAL_KINDS))];
        $reason = $this->clean_whitespace($reason);
        if ($reason === '') return ['error' => 'reason is required'];
        $table = db_prefix() . self::PROPOSAL_KINDS[$kind];
        $this->db->where('id', $proposalId)->where('status', 'proposed');
        if ($kind === 'item') $this->db->where_in('proposal_type', ['item_create', 'item_mapping_fix']);
        if ($kind === 'category_edit') $this->db->where('proposal_type', 'category_edit');
        if (!$this->db->get($table)->row()) return ['error' => 'Proposal not found or already decided'];
        $this->db->where('id', $proposalId)->update($table, [
            'status' => 'rejected', 'decision_note' => $reason, 'decided_by' => get_staff_user_id() ?: null, 'decided_at' => date('Y-m-d H:i:s'),
        ]);
        return ['kind' => $kind, 'proposal_id' => $proposalId, 'status' => 'rejected', 'reason' => $reason];
    }
}
