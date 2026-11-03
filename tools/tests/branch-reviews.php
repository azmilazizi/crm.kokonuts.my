<?php
// Standalone regression test: php tools/tests/branch-reviews.php
// Does not boot CRM or connect to its configured database.
define('BASEPATH', __DIR__);
function db_prefix() { return 'test_'; }
class App_Model { public $db; public $load; }
require dirname(__DIR__, 2) . '/application/helpers/google_review_helper.php';
require dirname(__DIR__, 2) . '/modules/loyalty/models/Loyalty_model.php';

function check($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}
class Rows {
    private $row;
    public function __construct($row) { $this->row = $row; }
    public function row_array() { return $this->row; }
}
class MemoryDb {
    public $tables = [];
    public $hasUrlColumn = true;
    private $where = [];
    private $from;
    public function select($fields) { return $this; }
    public function from($table) { $this->from = $table; return $this; }
    public function where($key, $value = null) {
        $this->where = array_merge($this->where, is_array($key) ? $key : [$key => $value]);
        return $this;
    }
    public function table_exists($table) { return array_key_exists($table, $this->tables); }
    public function field_exists($field, $table) { return $field !== 'google_review_url' || $this->hasUrlColumn; }
    public function get_where($table, $where) { $this->where($where); return $this->get($table); }
    private function matches($row, $where) {
        foreach ($where as $key => $value) if (($row[$key] ?? null) != $value) return false;
        return true;
    }
    public function get($table = null) {
        $table = $table ?: $this->from;
        $where = $this->where;
        $this->where = [];
        foreach ($this->tables[$table] ?? [] as $row) {
            if ($this->matches($row, $where)) {
                if (!$this->hasUrlColumn) unset($row['google_review_url']);
                return new Rows($row);
            }
        }
        return new Rows(null);
    }
    public function query($sql, $args) {
        check(strpos($sql, 'INSERT IGNORE INTO `test_loyalty_branch_reviews`') === 0, 'Unexpected SQL');
        foreach ($this->tables['test_loyalty_branch_reviews'] as $row) {
            if ($row['customer_id'] == $args[0] && $row['warehouse_id'] == $args[1]) return true;
        }
        $this->tables['test_loyalty_branch_reviews'][] = [
            'customer_id' => $args[0], 'warehouse_id' => $args[1], 'prompted_at' => $args[2], 'opened_at' => null];
        return true;
    }
    public function update($table, $values) {
        $where = $this->where;
        $this->where = [];
        foreach ($this->tables[$table] as &$row) {
            if ($this->matches($row, $where)) $row = array_merge($row, $values);
        }
        return true;
    }
}
$db = new MemoryDb();
$db->tables = [
    'test_warehouse' => [['warehouse_id' => 1, 'warehouse_name' => 'Branch A'], ['warehouse_id' => 2, 'warehouse_name' => 'Branch B']],
    'test_pos_receipt_settings' => [
        ['warehouse_id' => 1, 'address' => 'Address A', 'google_review_url' => 'https://g.page/r/branch-a/review'],
        ['warehouse_id' => 2, 'address' => 'Address B', 'google_review_url' => 'https://g.page/r/branch-b/review']],
    'test_loyalty_branch_reviews' => [],
    'test_pos_loyalty_customers' => [['id' => 7, 'phone' => '60123456789']],
    'test_pos_receipts' => [[
        'id' => 99, 'receipt_number' => 'R99', 'receipt_type' => 'SALE', 'receipt_date' => date('Y-m-d H:i:s'),
        'total_money' => 20, 'warehouse_id' => 2, 'cashback_qr_token' => 'claim-b',
        'loyalty_customer_id' => 7, 'cashback_claimed_at' => date('Y-m-d H:i:s'), 'cancelled_at' => null]],
];
$model = new Loyalty_model();
$model->db = $db;
$model->load = new class { public function helper($name) {} };

foreach (['https://g.page/r/abc/review', 'https://maps.app.goo.gl/abc', 'https://search.google.com/local/writereview?placeid=abc'] as $url) {
    check(kokonuts_google_review_url($url) === $url, 'Valid Google link rejected');
}
foreach (['javascript:alert(1)', 'http://g.page/r/a', 'https://g.page.evil.test/a', 'https://g.page@evil.test/a', 'https://g.page:444/a', "https://g.page/r/a\nb", ['not a URL']] as $url) {
    check(kokonuts_google_review_url($url) === false, 'Unsafe URL accepted');
}
check(kokonuts_google_review_url('  ') === '', 'Blank URL should disable invitation');
$branchA = $model->get_claim_branch_context(1);
$branchB = $model->get_claim_branch_context(2);
check($branchA['google_review_url'] !== $branchB['google_review_url'], 'Branch destinations mixed');
check($branchB['branch_name'] === 'Branch B' && $branchB['store_address'] === 'Address B', 'Wrong branch details');
$claim = $model->get_receipt_for_claim('claim-b');
check($claim['warehouse_id'] === 2 && $claim['google_review_url'] === $branchB['google_review_url'], 'Claim did not use its stored branch');
check($model->get_claim_branch_context(999)['google_review_url'] === null, 'Unknown branch must not fall back');
check($model->prepare_branch_review(7, $branchA), 'First branch should prompt');
check($model->prepare_branch_review(7, $branchB), 'Second branch should prompt independently');
check(!$model->confirm_branch_review('wrong-phone', 'claim-b'), 'Wrong customer may not mark a review opening');
check(!$model->confirm_branch_review('60123456789', 'wrong-token'), 'Wrong receipt token accepted');
check($model->confirm_branch_review('60123456789', 'claim-b'), 'Valid opening failed');
check($model->confirm_branch_review('60123456789', 'claim-b'), 'Repeated opening must be idempotent');
check(!$model->prepare_branch_review(7, $branchB), 'Opened branch should not prompt again');
check($model->prepare_branch_review(7, $branchA), 'Opening branch B must not suppress branch A');
check(count($db->tables['test_loyalty_branch_reviews']) === 2, 'Repeated claims created duplicate prompt records');
$db->tables['test_pos_receipt_settings'][1]['google_review_url'] = '';
check(!$model->prepare_branch_review(8, $model->get_claim_branch_context(2)), 'Missing link must skip invitation');
$db->hasUrlColumn = false;
check($model->get_claim_branch_context(1)['google_review_url'] === null, 'Pre-migration schema should not return a link');
unset($db->tables['test_loyalty_branch_reviews']);
check(!$model->prepare_branch_review(7, $branchA), 'Missing tracking table must not block cashback');
echo "Branch review regression checks passed.\n";
