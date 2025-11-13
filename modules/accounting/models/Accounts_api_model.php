<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Accounts_api_model extends App_Model
{
    /** @var string */
    private $table;

    public function __construct()
    {
        parent::__construct();

        $this->table = db_prefix() . 'acc_accounts';
    }

    /**
     * Fetch a paginated list of accounts using the supplied filters.
     *
     * @param array<string,mixed> $filters
     * @return array{total:int,accounts:array<int,array<string,mixed>>}
     */
    public function list_accounts(array $filters, int $limit, int $offset): array
    {
        $limit  = max(0, $limit);
        $offset = max(0, $offset);

        $this->db->start_cache();
        $this->db->from($this->table);
        $this->apply_filters($filters);
        $this->db->stop_cache();

        $total = (int) $this->db->count_all_results();

        if ($limit > 0) {
            $this->db->limit($limit, $offset);
        }

        $this->db->order_by('account_type_id', 'ASC');
        $this->db->order_by('account_detail_type_id', 'ASC');
        $this->db->order_by('name', 'ASC');

        $accounts = $this->db->get()->result_array();

        $this->db->flush_cache();

        return [
            'total'    => $total,
            'accounts' => $accounts ?: [],
        ];
    }

    /**
     * Retrieve a single account by id.
     *
     * @return array<string,mixed>|null
     */
    public function get_account(int $id): ?array
    {
        $account = $this->db->where('id', $id)->get($this->table)->row_array();

        return $account ?: null;
    }

    /**
     * Create a new account and return the new identifier.
     *
     * @param array<string,mixed> $data
     * @return int|false
     */
    public function create_account(array $data)
    {
        $payload = $this->filter_account_payload($data);

        if ($payload === []) {
            return false;
        }

        $inserted = $this->db->insert($this->table, $payload);

        if (!$inserted) {
            return false;
        }

        return (int) $this->db->insert_id();
    }

    /**
     * Update an existing account.
     *
     * @param array<string,mixed> $data
     */
    public function update_account(int $id, array $data): bool
    {
        $payload = $this->filter_account_payload($data, true);

        if ($payload === []) {
            return false;
        }

        $this->db->where('id', $id);
        $this->db->update($this->table, $payload);

        return $this->db->affected_rows() > 0;
    }

    /**
     * Delete an account when possible.
     *
     * @return bool|string True on success, a string identifier on constraint failure.
     */
    public function delete_account(int $id)
    {
        $account = $this->get_account($id);

        if (!$account) {
            return false;
        }

        if (!empty($account['default_account'])) {
            return 'default_account';
        }

        $childCount = $this->db->where('parent_account', $id)->count_all_results($this->table);
        if ($childCount > 0) {
            return 'has_children';
        }

        $historyTable = db_prefix() . 'acc_account_history';

        if ($this->db->table_exists($historyTable)) {
            $this->db->from($historyTable);
            $this->db->group_start();
            $this->db->where('account', $id);
            if ($this->db->field_exists('parent_account', $historyTable)) {
                $this->db->or_where('parent_account', $id);
            }
            $this->db->group_end();
            $historyCount = $this->db->count_all_results();

            if ($historyCount > 0) {
                return 'have_transaction';
            }
        }

        $this->db->where('id', $id)->delete($this->table);

        return $this->db->affected_rows() > 0;
    }

    /**
     * @param array<string,mixed> $filters
     */
    private function apply_filters(array $filters): void
    {
        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            if ($search !== '') {
                $this->db->group_start();
                $this->db->like('name', $search);
                $this->db->or_like('number', $search);
                $this->db->or_like('description', $search);
                $this->db->group_end();
            }
        }

        if (!empty($filters['account_type_id'])) {
            $this->db->where('account_type_id', (int) $filters['account_type_id']);
        }

        if (!empty($filters['account_detail_type_id'])) {
            $this->db->where('account_detail_type_id', (int) $filters['account_detail_type_id']);
        }

        if (!empty($filters['parent_account'])) {
            $this->db->where('parent_account', (int) $filters['parent_account']);
        }

        if (array_key_exists('active', $filters) && $filters['active'] !== null) {
            $this->db->where('active', (int) $filters['active']);
        }
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function filter_account_payload(array $data, bool $isUpdate = false): array
    {
        $allowed = [
            'name',
            'number',
            'account_type_id',
            'account_detail_type_id',
            'description',
            'balance',
            'balance_as_of',
            'parent_account',
            'bank_name',
            'bank_account',
            'bank_routing',
            'address_line_1',
            'address_line_2',
            'currency',
            'update_balance',
            'active',
        ];

        $payload = [];

        foreach ($allowed as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }

            $value = $data[$key];

            switch ($key) {
                case 'account_type_id':
                case 'account_detail_type_id':
                case 'parent_account':
                case 'currency':
                    if ($value === '' || $value === null) {
                        $payload[$key] = null;
                    } elseif (is_numeric($value)) {
                        $payload[$key] = (int) $value;
                    }
                    break;
                case 'balance':
                    if ($value === '' || $value === null) {
                        $payload[$key] = 0;
                    } else {
                        $payload[$key] = $this->format_decimal($value);
                    }
                    break;
                case 'balance_as_of':
                    $date = $this->normalize_date($value);
                    if ($date !== null) {
                        $payload[$key] = $date;
                    } elseif (!$isUpdate) {
                        $payload[$key] = null;
                    }
                    break;
                case 'update_balance':
                case 'active':
                    $payload[$key] = $this->to_bool_int($value);
                    break;
                default:
                    $payload[$key] = is_string($value) ? trim($value) : $value;
                    break;
            }
        }

        if (!$isUpdate) {
            if (!isset($payload['active'])) {
                $payload['active'] = 1;
            }
        }

        return $payload;
    }

    private function normalize_date($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_string($value)) {
            $timestamp = strtotime($value);
            if ($timestamp !== false) {
                return date('Y-m-d', $timestamp);
            }
        }

        return null;
    }

    private function format_decimal($value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function to_bool_int($value): int
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (is_numeric($value)) {
            return (int) ((float) $value > 0);
        }

        if (is_string($value)) {
            $normalized = strtolower($value);
            if (in_array($normalized, ['1', 'true', 'yes'], true)) {
                return 1;
            }
            if (in_array($normalized, ['0', 'false', 'no'], true)) {
                return 0;
            }
        }

        return 0;
    }
}
