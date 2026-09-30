<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$CI = &get_instance();
$by_name = ['inventory' => [], 'cos' => []];
foreach (['inventory', 'cos'] as $k) {
    foreach ($accounts[$k] as $a) { $by_name[$k][$a['name']] = (int) $a['id']; }
}
// Preselect: the item's current mapping, else an account already named by
// convention, else "create".
$pick = function ($kind, $c) use ($by_name, $CI) {
    $mapped = $kind === 'cos' ? $c['expense_account'] : $c['inventory_asset_account'];
    if (!empty($mapped)) { return (int) $mapped; }
    $name = $CI->accounting_model->item_account_backfill_name($kind, $c);
    return $by_name[$kind][$name] ?? 'new';
};
?>
<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-12">
        <div class="panel_s">
          <div class="panel-body">
            <h4 class="no-margin font-bold"><i class="fa fa-history"></i> Item Account Backfill</h4>
            <p class="text-muted mtop10">
              These items had no account mapping when their inventory receipts were posted, so the receipts were
              debited to <strong><?php echo html_escape($generic_name); ?></strong> instead of the item's own
              inventory account. Tick the items to fix and pick (or create) their accounts. This will:
            </p>
            <ul class="text-muted">
              <li>save the item's <strong>Inventory Asset</strong> and <strong>Cost of Sales</strong> accounts, so future receipts, deliveries and adjustments use them;</li>
              <li>move those past receipt entries from <?php echo html_escape($generic_name); ?> to the item's inventory account. Amounts, dates and the other side of each entry stay the same.</li>
            </ul>
            <p class="text-muted">"Create" makes accounts named like your existing ones, e.g. <em>Inventory Assets - Toppings - Kinder Glaze</em> and <em>COS - Toppings - Kinder Glaze</em>. If one with that name already exists, it's reused.</p>

            <?php if (empty($candidates)) { ?>
              <p class="text-success bold mtop20"><i class="fa fa-check-circle"></i> Nothing to backfill. Every item's receipt entries are on its own inventory account.</p>
            <?php } else { ?>
            <?php echo form_open(admin_url('accounting/item_account_backfill'), ['id' => 'iab-form']); ?>
            <div class="table-responsive">
              <table class="table table-bordered">
                <thead>
                  <tr>
                    <th style="width:36px;"><input type="checkbox" id="iab-all" title="Select all"></th>
                    <th>Item</th>
                    <th class="text-right">Receipts</th>
                    <th class="text-right">Amount</th>
                    <th>Period</th>
                    <th style="min-width:260px;">Inventory Asset account</th>
                    <th style="min-width:260px;">Cost of Sales account</th>
                  </tr>
                </thead>
                <tbody>
                <?php foreach ($candidates as $c) {
                  $id = (int) $c['item_id'];
                  $inv_sel = $pick('inventory', $c);
                  $cos_sel = $pick('cos', $c);
                ?>
                  <tr>
                    <td><input type="checkbox" class="iab-row" name="rows[<?php echo $id; ?>][on]" value="1"></td>
                    <td>
                      <strong><?php echo html_escape($c['description']); ?></strong>
                      <span class="text-muted">(<?php echo html_escape($c['commodity_code']); ?><?php echo $c['group_name'] ? ' · ' . html_escape($c['group_name']) : ''; ?>)</span>
                      <?php if (!empty($c['inventory_asset_account'])) { ?>
                        <br><span class="label label-info">Mapped since, entries not moved yet</span>
                      <?php } else { ?>
                        <br><span class="label label-warning">Not mapped</span>
                      <?php } ?>
                    </td>
                    <td class="text-right"><?php echo (int) $c['receipts']; ?></td>
                    <td class="text-right"><?php echo app_format_number($c['amount']); ?></td>
                    <td><?php echo _d($c['first_date']); ?><?php echo $c['first_date'] !== $c['last_date'] ? ' – ' . _d($c['last_date']) : ''; ?></td>
                    <?php foreach (['inventory', 'cos'] as $kind) { $sel = $kind === 'cos' ? $cos_sel : $inv_sel; ?>
                    <td>
                      <select name="rows[<?php echo $id; ?>][<?php echo $kind; ?>]" class="form-control input-sm">
                        <option value="new" <?php echo $sel === 'new' ? 'selected' : ''; ?>>+ Create "<?php echo html_escape($CI->accounting_model->item_account_backfill_name($kind, $c)); ?>"</option>
                        <?php foreach ($accounts[$kind] as $a) { ?>
                        <option value="<?php echo (int) $a['id']; ?>" <?php echo $sel === (int) $a['id'] ? 'selected' : ''; ?>><?php echo html_escape($a['name']); ?></option>
                        <?php } ?>
                      </select>
                    </td>
                    <?php } ?>
                  </tr>
                <?php } ?>
                </tbody>
              </table>
            </div>

            <?php if ($closed) { ?>
            <div class="checkbox checkbox-primary">
              <input type="checkbox" id="iab-closed" name="include_closed" value="1">
              <label for="iab-closed">Also move entries dated on or before the closing date (<?php echo _d($closed); ?>). Leave unticked to keep closed periods unchanged.</label>
            </div>
            <?php } ?>

            <div class="text-right">
              <span class="text-muted mright10" id="iab-count">0 selected</span>
              <button type="submit" class="btn btn-primary" id="iab-submit" disabled><i class="fa fa-check"></i> Map and move entries</button>
            </div>
            <?php echo form_close(); ?>
            <?php } ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php init_tail(); ?>
<script>
$(function () {
  'use strict';
  function sync() {
    var n = $('.iab-row:checked').length;
    $('#iab-count').text(n + ' selected');
    $('#iab-submit').prop('disabled', n === 0);
    $('#iab-all').prop('checked', n > 0 && n === $('.iab-row').length);
  }
  $('#iab-all').on('change', function () { $('.iab-row').prop('checked', this.checked); sync(); });
  $('.iab-row').on('change', sync);
  $('#iab-form').on('submit', function () {
    var n = $('.iab-row:checked').length;
    if (!confirm('Map ' + n + ' item(s) and move their receipt entries to the chosen inventory accounts?')) { return false; }
    $('#iab-submit').prop('disabled', true).text('Working…');
  });
});
</script>
</body>
</html>
