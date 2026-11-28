<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * POS sales -> ledger, through drafts that a person approves.
 *
 * One draft per business day per outlet:
 *   Dr  payment-method account   (from the payment mode's accounting mapping, "deposit_to")
 *   Cr  Sales Income - <channel> (by receipt source: POS = Walk In, GRABFOOD = Grabfood, ...)
 * Refunds are posted on the refund date as the reverse (Dr Sales Income,
 * Cr payment-method account). A sale whose draft was already posted and is
 * later cancelled gets a reversal on the cancellation date.
 *
 * Re-running never duplicates: each sale, refund and reversal is linked to
 * exactly one live draft (acc_pos_ledger_links has a unique key), and drafts
 * that are still waiting for approval are rebuilt in place.
 *
 * Nothing here writes to the ledger except post_draft(), which only runs on
 * an explicit approval and re-validates everything first.
 */
class Pos_ledger_model extends App_Model
{
    const SOURCE = 'pos_daily';

    private $modeAccounts = null;
    private $accounts = null;

    // ------------------------------------------------------------------
    // Configuration
    // ------------------------------------------------------------------

    public function start_date(): string
    {
        $d = (string) get_option('acc_pos_ledger_start_date');
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? $d : '2026-06-01';
    }

    private function channel_accounts(): array
    {
        $map = json_decode((string) get_option('acc_pos_ledger_channel_accounts'), true);
        return is_array($map) && $map ? array_change_key_case($map, CASE_UPPER) : ['POS' => 217, 'GRABFOOD' => 214, 'FOODPANDA' => 215, 'SHOPEEFOOD' => 216];
    }

    private function accounts(): array
    {
        if ($this->accounts === null) {
            $this->accounts = [];
            foreach ($this->db->select('id, name, parent_account, active')->get(db_prefix() . 'acc_accounts')->result_array() as $a) {
                $this->accounts[(int) $a['id']] = $a + ['children' => 0];
            }
            foreach ($this->accounts as $a) {
                if ((int) $a['parent_account'] && isset($this->accounts[(int) $a['parent_account']])) {
                    $this->accounts[(int) $a['parent_account']]['children']++;
                }
            }
        }
        return $this->accounts;
    }

    /** null when the account may be posted to, otherwise the reason. */
    private function account_problem(int $id): ?string
    {
        $a = $this->accounts()[$id] ?? null;
        if (!$a) return "account #$id does not exist";
        if ((int) $a['active'] !== 1) return "account #$id \"{$a['name']}\" is inactive";
        if ($a['children'] > 0) return "account #$id \"{$a['name']}\" is a parent account";
        return null;
    }

    private function account_name(int $id): string
    {
        return $this->accounts()[$id]['name'] ?? "#$id";
    }

    private function name_key(string $s): string
    {
        return strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', ' ', $s)));
    }

    /**
     * POS payment name -> debit account, via the CRM payment mode of the
     * same name and its accounting mapping. POS payment_type_id is not used:
     * several different methods share the same id.
     */
    private function payment_account(string $paymentName): array
    {
        if ($this->modeAccounts === null) {
            $this->modeAccounts = [];
            $rows = $this->db->select('pm.id, pm.name, m.deposit_to')
                ->from(db_prefix() . 'payment_modes pm')
                ->join(db_prefix() . 'acc_payment_mode_mappings m', 'm.payment_mode_id = pm.id', 'left')
                ->get()->result_array();
            foreach ($rows as $r) {
                $this->modeAccounts[$this->name_key($r['name'])] = $r;
            }
        }
        $mode = $this->modeAccounts[$this->name_key($paymentName)] ?? null;
        if (!$mode) return ['error' => "POS payment method \"$paymentName\" has no CRM payment mode with the same name"];
        if (!(int) $mode['deposit_to']) return ['error' => "Payment mode \"{$mode['name']}\" has no accounting mapping (deposit to)"];
        return ['account_id' => (int) $mode['deposit_to'], 'payment_mode_id' => (int) $mode['id']];
    }

    // ------------------------------------------------------------------
    // Building drafts
    // ------------------------------------------------------------------

    /**
     * Build or refresh drafts for business days $from..$to (inclusive).
     * Days are capped to [start date, yesterday]: today is still trading.
     */
    public function build_drafts(?string $from = null, ?string $to = null): array
    {
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $from = $from && strtotime($from) ? date('Y-m-d', strtotime($from)) : $yesterday;
        $to = $to && strtotime($to) ? date('Y-m-d', strtotime($to)) : $yesterday;
        $notes = [];
        if ($from < $this->start_date()) { $notes[] = "from moved to the POS ledger start date {$this->start_date()}"; $from = $this->start_date(); }
        if ($to > $yesterday) { $notes[] = "to capped at $yesterday (today is still trading)"; $to = $yesterday; }
        if ($from > $to) return ['error' => 'Empty date range', 'from' => $from, 'to' => $to, 'notes' => $notes];

        $this->accounts = null; $this->modeAccounts = null;
        $p = db_prefix();
        $this->db->trans_begin();

        // 1. Drafts still waiting for approval in this range are rebuilt in
        //    place: release their links so their receipts are picked up again.
        $open = $this->db->where('source', self::SOURCE)->where('status', 'draft')
            ->where('journal_date >=', $from)->where('journal_date <=', $to)
            ->get($p . 'acc_ledger_drafts')->result_array();
        $openByKey = [];
        foreach ($open as $d) {
            $this->db->where('draft_id', $d['id'])->delete($p . 'acc_pos_ledger_links');
            $this->db->where('draft_id', $d['id'])->delete($p . 'acc_ledger_draft_lines');
            $openByKey[$d['source_key']] = $d;
        }

        // 2. Collect unlinked events, grouped by day and outlet.
        $groups = [];
        $add = function (string $date, int $warehouseId, array $event) use (&$groups) {
            $groups[$date . '|' . $warehouseId][] = $event;
        };

        $excluded = "SELECT receipt_id FROM {$p}acc_pos_ledger_exclusions";
        $sales = $this->db->query("SELECT r.id, r.receipt_number, r.warehouse_id, r.source, DATE(r.receipt_date) d, r.total_money
            FROM {$p}pos_receipts r
            WHERE r.receipt_type IN ('SALE','REFUNDED') AND r.cancelled_at IS NULL
              AND DATE(r.receipt_date) BETWEEN ? AND ?
              AND r.id NOT IN ($excluded)
              AND NOT EXISTS (SELECT 1 FROM {$p}acc_pos_ledger_links l WHERE l.link_type = 'sale' AND l.ref_id = r.id)", [$from, $to])->result_array();
        foreach ($sales as $r) {
            $add($r['d'], (int) $r['warehouse_id'], ['type' => 'sale', 'ref_id' => (int) $r['id'], 'receipt' => $r]);
        }

        $refunds = $this->db->query("SELECT f.id, f.amount, DATE(f.refunded_at) d, r.id receipt_id, r.receipt_number, r.warehouse_id, r.source, r.total_money
            FROM {$p}pos_refunds f JOIN {$p}pos_receipts r ON r.id = f.receipt_id
            WHERE r.cancelled_at IS NULL AND DATE(f.refunded_at) BETWEEN ? AND ?
              AND DATE(r.receipt_date) >= ?
              AND r.id NOT IN ($excluded)
              AND NOT EXISTS (SELECT 1 FROM {$p}acc_pos_ledger_links l WHERE l.link_type = 'refund' AND l.ref_id = f.id)", [$from, $to, $this->start_date()])->result_array();
        foreach ($refunds as $f) {
            $add($f['d'], (int) $f['warehouse_id'], ['type' => 'refund', 'ref_id' => (int) $f['id'], 'receipt' => ['id' => $f['receipt_id'], 'receipt_number' => $f['receipt_number'], 'source' => $f['source'], 'total_money' => $f['total_money']], 'amount' => (float) $f['amount']]);
        }

        // Sales already posted, then cancelled: reverse on the cancellation date.
        $cancelled = $this->db->query("SELECT r.id, r.receipt_number, r.warehouse_id, r.source, DATE(r.cancelled_at) d, r.total_money
            FROM {$p}pos_receipts r
            JOIN {$p}acc_pos_ledger_links l ON l.link_type = 'sale' AND l.ref_id = r.id
            JOIN {$p}acc_ledger_drafts dr ON dr.id = l.draft_id AND dr.status = 'posted'
            WHERE r.cancelled_at IS NOT NULL AND DATE(r.cancelled_at) BETWEEN ? AND ?
              AND NOT EXISTS (SELECT 1 FROM {$p}acc_pos_ledger_links x WHERE x.link_type = 'reversal' AND x.ref_id = r.id)", [$from, $to])->result_array();
        foreach ($cancelled as $r) {
            $add($r['d'], (int) $r['warehouse_id'], ['type' => 'reversal', 'ref_id' => (int) $r['id'], 'receipt' => $r]);
        }

        // 3. One draft per group.
        $warehouses = [];
        foreach ($this->db->select('warehouse_id, warehouse_code, warehouse_name')->get($p . 'warehouse')->result_array() as $w) {
            $warehouses[(int) $w['warehouse_id']] = $w;
        }
        $built = [];
        ksort($groups);
        foreach ($groups as $key => $events) {
            [$date, $warehouseId] = explode('|', $key);
            $built[] = $this->write_group_draft($date, (int) $warehouseId, $events, $warehouses[(int) $warehouseId] ?? null, $openByKey);
        }

        // 4. Waiting drafts that no longer have any receipt are removed.
        $removed = [];
        foreach ($openByKey as $d) {
            if (empty($d['_reused'])) {
                $this->db->where('id', $d['id'])->delete($p . 'acc_ledger_drafts');
                $removed[] = (int) $d['id'];
            }
        }

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return ['error' => 'Database error while building drafts; nothing was changed'];
        }
        $this->db->trans_commit();

        $summary = ['created' => 0, 'refreshed' => 0, 'blocked' => 0];
        foreach ($built as $b) { $summary[$b['action']]++; if ($b['errors']) $summary['blocked']++; }
        return ['from' => $from, 'to' => $to, 'notes' => $notes, 'summary' => $summary, 'removed_empty_drafts' => $removed, 'drafts' => $built,
            'next_step' => 'Review with get_ledger_draft, then approve_ledger_draft (posts to the ledger) or reject_ledger_draft. Nothing has been posted.'];
    }

    /** Split $cents across weights so the parts add up exactly. */
    private function allocate(int $cents, array $weights): array
    {
        $total = array_sum($weights);
        $out = []; $used = 0; $keys = array_keys($weights); $last = end($keys);
        foreach ($weights as $k => $w) {
            $part = $k === $last ? $cents - $used : (int) round($total ? $cents * $w / $total : 0);
            $out[$k] = $part; $used += $part;
        }
        return $out;
    }

    private function write_group_draft(string $date, int $warehouseId, array $events, ?array $warehouse, array &$openByKey): array
    {
        $p = db_prefix();
        $channels = $this->channel_accounts();
        $receiptIds = array_values(array_unique(array_map(function ($e) { return (int) $e['receipt']['id']; }, $events)));
        $payments = [];
        foreach ($this->db->where_in('receipt_id', $receiptIds ?: [0])->order_by('id', 'ASC')->get($p . 'pos_receipt_payments')->result_array() as $pay) {
            $payments[(int) $pay['receipt_id']][] = $pay;
        }

        $lines = [];   // "account|side|kind" => [account, side, kind, cents, count]
        $errors = [];  $warnings = []; $links = [];
        $counts = ['sales' => 0, 'refunds' => 0, 'reversals' => 0];
        $put = function (int $account, string $side, string $kind, int $cents) use (&$lines) {
            if ($cents === 0) return;
            $k = "$account|$side|$kind";
            if (!isset($lines[$k])) $lines[$k] = ['account' => $account, 'side' => $side, 'kind' => $kind, 'cents' => 0, 'count' => 0];
            $lines[$k]['cents'] += $cents; $lines[$k]['count']++;
        };

        foreach ($events as $e) {
            $r = $e['receipt'];
            $channel = strtoupper((string) $r['source']);
            $channelAccount = (int) ($channels[$channel] ?? 0);
            if (!$channelAccount) { $errors[] = "Receipt {$r['receipt_number']}: no Sales Income account configured for channel \"$channel\""; continue; }

            // Per payment method, net of change given (cash_back).
            $methods = [];
            foreach ($payments[(int) $r['id']] ?? [] as $pay) {
                $cents = (int) round(((float) $pay['money_amount'] - (float) $pay['cash_back']) * 100);
                $acc = $this->payment_account((string) $pay['payment_name']);
                if (isset($acc['error'])) { $errors[] = "Receipt {$r['receipt_number']}: " . $acc['error']; continue 2; }
                $methods[$acc['account_id']] = ($methods[$acc['account_id']] ?? 0) + $cents;
            }
            $paid = array_sum($methods);
            if ($paid === 0 && (int) round((float) $r['total_money'] * 100) === 0 && $e['type'] === 'sale') {
                // Free item (e.g. voucher): nothing to post, but record it as
                // handled so it is not picked up again.
                $warnings[] = "Receipt {$r['receipt_number']}: RM 0.00 receipt, nothing to post";
                $links[] = ['link_type' => 'sale', 'ref_id' => $e['ref_id'], 'receipt_id' => (int) $r['id'], 'amount' => 0];
                $counts['sales']++;
                continue;
            }
            if (!$methods || $paid <= 0) { $errors[] = "Receipt {$r['receipt_number']}: no payment recorded"; continue; }
            if (abs($paid - (int) round((float) $r['total_money'] * 100)) > 0) {
                $warnings[] = sprintf('Receipt %s: payments RM %.2f differ from receipt total RM %.2f; payments were used', $r['receipt_number'], $paid / 100, $r['total_money']);
            }

            if ($e['type'] === 'sale') {
                foreach ($methods as $acc => $cents) { $put($acc, 'debit', 'sale', $cents); }
                $put($channelAccount, 'credit', 'sale', $paid);
                $amount = $paid; $counts['sales']++;
            } elseif ($e['type'] === 'reversal') {
                foreach ($methods as $acc => $cents) { $put($acc, 'credit', 'reversal', $cents); }
                $put($channelAccount, 'debit', 'reversal', $paid);
                $amount = $paid; $counts['reversals']++;
            } else {
                $refund = (int) round($e['amount'] * 100);
                if ($refund <= 0) { $warnings[] = "Refund #{$e['ref_id']} on {$r['receipt_number']} has no amount; skipped"; continue; }
                if ($refund > $paid) { $errors[] = sprintf('Refund #%d on %s (RM %.2f) is more than was paid (RM %.2f)', $e['ref_id'], $r['receipt_number'], $refund / 100, $paid / 100); continue; }
                foreach ($this->allocate($refund, $methods) as $acc => $cents) { $put($acc, 'credit', 'refund', $cents); }
                $put($channelAccount, 'debit', 'refund', $refund);
                $amount = $refund; $counts['refunds']++;
            }
            $links[] = ['link_type' => $e['type'], 'ref_id' => $e['ref_id'], 'receipt_id' => (int) $r['id'], 'amount' => $amount / 100];
        }

        foreach ($lines as $l) {
            if ($problem = $this->account_problem($l['account'])) $errors[] = 'Cannot post to ' . $problem;
        }
        $debit = 0; $credit = 0;
        foreach ($lines as $l) { if ($l['side'] === 'debit') $debit += $l['cents']; else $credit += $l['cents']; }
        if ($debit !== $credit) $errors[] = sprintf('Journal does not balance: debit RM %.2f, credit RM %.2f', $debit / 100, $credit / 100);
        if (!$lines && !$errors) $errors[] = 'No postable amounts';
        $errors = array_values(array_unique($errors));

        // Draft identity: day + outlet. If that day's draft was already
        // posted, late receipts go into a numbered supplementary draft.
        $baseKey = 'pos:' . $date . ':' . $warehouseId;
        $draft = null;
        foreach ($openByKey as $k => $d) {
            if (empty($d['_reused']) && ($k === $baseKey || strpos($k, $baseKey . '#') === 0)) { $draft = $d; $openByKey[$k]['_reused'] = true; break; }
        }
        $sourceKey = $draft['source_key'] ?? null;
        if (!$sourceKey) {
            $sourceKey = $baseKey; $n = 1;
            while ($this->db->where('source', self::SOURCE)->where('source_key', $sourceKey)->where('status !=', 'rejected')->count_all_results(db_prefix() . 'acc_ledger_drafts')) {
                $sourceKey = $baseKey . '#' . (++$n);
            }
        }

        $outlet = $warehouse ? $warehouse['warehouse_name'] : "warehouse #$warehouseId";
        $description = "POS sales $date - $outlet" . (strpos($sourceKey, '#') ? ' (late receipts ' . substr($sourceKey, strpos($sourceKey, '#')) . ')' : '');
        $header = [
            'journal_date' => $date, 'warehouse_id' => $warehouseId, 'description' => $description,
            'total_debit' => $debit / 100, 'total_credit' => $credit / 100,
            'details_json' => json_encode(['counts' => $counts, 'errors' => $errors, 'warnings' => $warnings, 'warehouse_code' => $warehouse['warehouse_code'] ?? null]),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($draft) {
            $this->db->where('id', $draft['id'])->update(db_prefix() . 'acc_ledger_drafts', $header);
            $draftId = (int) $draft['id']; $action = 'refreshed';
        } else {
            $this->db->insert(db_prefix() . 'acc_ledger_drafts', $header + [
                'source' => self::SOURCE, 'source_key' => $sourceKey, 'live_key' => self::SOURCE . ':' . $sourceKey,
                'status' => 'draft', 'created_by' => get_staff_user_id() ?: null, 'created_at' => date('Y-m-d H:i:s'),
            ]);
            $draftId = (int) $this->db->insert_id(); $action = 'created';
        }

        $kindLabel = ['sale' => 'sales', 'refund' => 'refunds', 'reversal' => 'cancelled after posting'];
        uasort($lines, function ($a, $b) { return [$a['kind'], $a['side'] === 'credit', $a['account']] <=> [$b['kind'], $b['side'] === 'credit', $b['account']]; });
        foreach ($lines as $l) {
            $this->db->insert(db_prefix() . 'acc_ledger_draft_lines', [
                'draft_id' => $draftId, 'account_id' => $l['account'],
                'debit' => $l['side'] === 'debit' ? $l['cents'] / 100 : 0, 'credit' => $l['side'] === 'credit' ? $l['cents'] / 100 : 0,
                'description' => "POS {$kindLabel[$l['kind']]} $date - $outlet",
                'meta_json' => json_encode(['kind' => $l['kind'], 'entries' => $l['count']]),
            ]);
        }
        foreach ($links as $link) {
            $this->db->insert(db_prefix() . 'acc_pos_ledger_links', $link + ['draft_id' => $draftId, 'created_at' => date('Y-m-d H:i:s')]);
        }

        return ['draft_id' => $draftId, 'action' => $action, 'journal_date' => $date, 'outlet' => $outlet, 'source_key' => $sourceKey,
            'total' => $debit / 100, 'counts' => $counts, 'errors' => $errors, 'warnings' => $warnings];
    }

    // ------------------------------------------------------------------
    // Reading
    // ------------------------------------------------------------------

    public function list_drafts(array $f = []): array
    {
        $p = db_prefix();
        $this->db->select('d.id, d.source, d.source_key, d.journal_date, d.warehouse_id, d.description, d.status, d.total_debit, d.total_credit, d.details_json, d.journal_entry_id, d.decided_at')
            ->from($p . 'acc_ledger_drafts d');
        $this->db->where('d.status', $f['status'] ?? 'draft');
        if (!empty($f['source'])) $this->db->where('d.source', $f['source']);
        if (!empty($f['from'])) $this->db->where('d.journal_date >=', $f['from']);
        if (!empty($f['to'])) $this->db->where('d.journal_date <=', $f['to']);
        if (!empty($f['warehouse_id'])) $this->db->where('d.warehouse_id', (int) $f['warehouse_id']);
        $limit = max(1, min(500, (int) ($f['limit'] ?? 200)));
        $rows = $this->db->order_by('d.journal_date', 'ASC')->order_by('d.warehouse_id', 'ASC')->limit($limit + 1)->get()->result_array();
        $more = count($rows) > $limit;
        $rows = array_slice($rows, 0, $limit);
        $total = 0; $blocked = 0;
        foreach ($rows as &$r) {
            $details = json_decode((string) $r['details_json'], true) ?: [];
            unset($r['details_json']);
            $r['receipt_counts'] = $details['counts'] ?? null;
            $r['blocking_errors'] = count($details['errors'] ?? []);
            $r['warnings'] = count($details['warnings'] ?? []);
            $total += (float) $r['total_debit']; if ($r['blocking_errors']) $blocked++;
        }
        return ['drafts' => $rows, 'count' => count($rows), 'more' => $more, 'total_amount' => round($total, 2), 'blocked' => $blocked];
    }

    public function get_draft(int $id): array
    {
        $p = db_prefix();
        $d = $this->db->where('id', $id)->get($p . 'acc_ledger_drafts')->row_array();
        if (!$d) return ['error' => 'Draft not found'];
        $d['details'] = json_decode((string) $d['details_json'], true); unset($d['details_json']);
        $d['lines'] = $this->db->select('l.account_id, a.name account_name, l.debit, l.credit, l.description, l.meta_json')
            ->from($p . 'acc_ledger_draft_lines l')->join($p . 'acc_accounts a', 'a.id = l.account_id', 'left')
            ->where('l.draft_id', $id)->order_by('l.id', 'ASC')->get()->result_array();
        if ($d['source'] === self::SOURCE) {
            $d['linked'] = $this->db->select('link_type, COUNT(*) n, SUM(amount) amount', false)->where('draft_id', $id)
                ->group_by('link_type')->get($p . 'acc_pos_ledger_links')->result_array();
        }
        return $d;
    }

    // ------------------------------------------------------------------
    // Approval
    // ------------------------------------------------------------------

    /** Checks that apply to every draft before it may be posted. */
    public function posting_errors(array $draft, array $lines): array
    {
        $errors = [];
        $details = json_decode((string) $draft['details_json'], true) ?: [];
        foreach ($details['errors'] ?? [] as $e) $errors[] = $e;
        $this->accounts = null;
        $debit = 0; $credit = 0;
        foreach ($lines as $l) {
            $debit += (int) round($l['debit'] * 100); $credit += (int) round($l['credit'] * 100);
            if ($problem = $this->account_problem((int) $l['account_id'])) $errors[] = 'Cannot post to ' . $problem;
            if ((float) $l['debit'] < 0 || (float) $l['credit'] < 0 || ((float) $l['debit'] > 0 && (float) $l['credit'] > 0)) $errors[] = 'Invalid line amounts';
        }
        if (!$lines || $debit === 0) $errors[] = 'Draft has no amounts';
        if ($debit !== $credit) $errors[] = sprintf('Journal does not balance: debit RM %.2f, credit RM %.2f', $debit / 100, $credit / 100);
        if (abs($debit / 100 - (float) $draft['total_debit']) > 0.001) $errors[] = 'Draft lines changed since the draft was built';
        if (get_option('acc_close_the_books') == 1 && get_option('acc_closing_date') && strtotime($draft['journal_date']) <= strtotime(get_option('acc_closing_date'))) {
            $errors[] = 'The books are closed up to ' . get_option('acc_closing_date');
        }
        return array_values(array_unique($errors));
    }

    /** POS-specific: every linked sale/refund must still be as it was. */
    private function pos_link_errors(int $draftId): array
    {
        $p = db_prefix();
        $errors = [];
        $changed = $this->db->query("SELECT r.receipt_number FROM {$p}acc_pos_ledger_links l JOIN {$p}pos_receipts r ON r.id = l.receipt_id
            WHERE l.draft_id = ? AND l.link_type = 'sale' AND (r.cancelled_at IS NOT NULL OR r.id IN (SELECT receipt_id FROM {$p}acc_pos_ledger_exclusions))", [$draftId])->result_array();
        foreach ($changed as $c) $errors[] = "Receipt {$c['receipt_number']} was cancelled or excluded after this draft was built; run build_pos_ledger_drafts for this day again";
        $gone = $this->db->query("SELECT l.ref_id FROM {$p}acc_pos_ledger_links l LEFT JOIN {$p}pos_refunds f ON f.id = l.ref_id
            WHERE l.draft_id = ? AND l.link_type = 'refund' AND f.id IS NULL", [$draftId])->result_array();
        foreach ($gone as $g) $errors[] = "Refund #{$g['ref_id']} no longer exists; rebuild this day";
        return $errors;
    }

    public function approve_draft(int $id): array
    {
        $p = db_prefix();
        $draft = $this->db->where('id', $id)->get($p . 'acc_ledger_drafts')->row_array();
        if (!$draft) return ['error' => 'Draft not found'];
        if ($draft['status'] !== 'draft') return ['error' => "Draft is already {$draft['status']}", 'journal_entry_id' => $draft['journal_entry_id']];
        $lines = $this->db->where('draft_id', $id)->order_by('id', 'ASC')->get($p . 'acc_ledger_draft_lines')->result_array();
        $errors = $this->posting_errors($draft, $lines);
        if ($draft['source'] === self::SOURCE) $errors = array_merge($errors, $this->pos_link_errors($id));
        if ($errors) return ['error' => 'Draft cannot be posted', 'details' => array_values(array_unique($errors))];

        $details = json_decode((string) $draft['details_json'], true) ?: [];
        $suffix = strpos($draft['source_key'], '#') !== false ? '-' . substr($draft['source_key'], strpos($draft['source_key'], '#') + 1) : '';
        $number = $draft['source'] === self::SOURCE
            ? 'POS-' . str_replace('-', '', $draft['journal_date']) . '-' . ($details['warehouse_code'] ?? ('W' . $draft['warehouse_id'])) . $suffix
            : strtoupper($draft['source']) . '-' . $id;
        $now = date('Y-m-d H:i:s'); $staff = get_staff_user_id() ?: 0;

        $this->db->trans_begin();
        // Lock the draft row so two approvals cannot both post.
        $locked = $this->db->query("SELECT status FROM {$p}acc_ledger_drafts WHERE id = ? FOR UPDATE", [$id])->row();
        if (!$locked || $locked->status !== 'draft') { $this->db->trans_rollback(); return ['error' => 'Draft was decided by someone else']; }
        $this->db->insert($p . 'acc_journal_entries', [
            'number' => substr($number, 0, 45), 'description' => $draft['description'], 'journal_date' => $draft['journal_date'],
            'amount' => $draft['total_debit'], 'datecreated' => $now, 'addedfrom' => $staff, 'reference' => 'ledger_draft:' . $id, 'recurring' => 0,
        ]);
        $journalId = (int) $this->db->insert_id();
        foreach ($lines as $l) {
            $this->db->insert($p . 'acc_account_history', [
                'account' => (int) $l['account_id'], 'debit' => $l['debit'], 'credit' => $l['credit'], 'description' => $l['description'],
                'rel_id' => $journalId, 'rel_type' => 'journal_entry', 'date' => $draft['journal_date'], 'datecreated' => $now, 'addedfrom' => $staff,
            ]);
        }
        $this->db->where('id', $id)->update($p . 'acc_ledger_drafts', [
            'status' => 'posted', 'journal_entry_id' => $journalId, 'decided_by' => $staff ?: null, 'decided_at' => $now,
        ]);
        if (!$journalId || $this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return ['error' => 'Database error; nothing was posted'];
        }
        $this->db->trans_commit();
        return ['draft_id' => $id, 'status' => 'posted', 'journal_entry_id' => $journalId, 'number' => $number, 'journal_date' => $draft['journal_date'], 'amount' => (float) $draft['total_debit']];
    }

    /**
     * Reject a waiting draft. Its receipts are released so a later build
     * can produce a corrected draft (after the mapping is fixed).
     */
    public function reject_draft(int $id, string $reason): array
    {
        $p = db_prefix();
        $reason = trim($reason);
        if ($reason === '') return ['error' => 'reason is required'];
        $draft = $this->db->where('id', $id)->get($p . 'acc_ledger_drafts')->row_array();
        if (!$draft) return ['error' => 'Draft not found'];
        if ($draft['status'] !== 'draft') return ['error' => "Draft is already {$draft['status']}"];
        $this->db->trans_begin();
        $this->db->where('draft_id', $id)->delete($p . 'acc_pos_ledger_links');
        $this->db->where('id', $id)->update($p . 'acc_ledger_drafts', [
            'status' => 'rejected', 'live_key' => null, 'decision_note' => $reason,
            'decided_by' => get_staff_user_id() ?: null, 'decided_at' => date('Y-m-d H:i:s'),
        ]);
        if ($this->db->trans_status() === false) { $this->db->trans_rollback(); return ['error' => 'Database error']; }
        $this->db->trans_commit();
        return ['draft_id' => $id, 'status' => 'rejected', 'reason' => $reason, 'note' => 'Receipts were released; the next build for this day creates a new draft.'];
    }
}
