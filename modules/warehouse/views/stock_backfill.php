<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$groups = [];
foreach ($lines as $l) {
    $groups[$l['commodity_code']]['item'] = $l;
    $groups[$l['commodity_code']]['lines'][] = $l;
}

// "Popcorn Chicken" (POS product) -> "Chicken Popcorn" (inventory item): same words, any order.
$word_key = function ($s) {
    $w = preg_split('/[^a-z0-9]+/', strtolower((string) $s), -1, PREG_SPLIT_NO_EMPTY);
    sort($w);
    return implode(' ', $w);
};
$target_ids = array_column($targets, 'id');
$by_words = [];
foreach ($targets as $t) {
    $by_words[$word_key($t['description'])][] = (int) $t['id'];
}
$suggest_target = function ($it) use ($target_ids, $by_words, $word_key) {
    if (in_array($it['commodity_code'], $target_ids)) {
        return (int) $it['commodity_code'];
    }
    $k = $word_key($it['item_name']);
    return (isset($by_words[$k]) && count($by_words[$k]) === 1) ? $by_words[$k][0] : 0;
};
?>
<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-12">
        <div class="panel_s">
          <div class="panel-body">
            <h4 class="no-margin font-bold"><i class="fa fa-history"></i> Stock backfill</h4>
            <p class="text-muted mtop10">
              These inventory receipts were approved while their item had
              <strong>"Do not update inventory numbers"</strong> ticked, so no stock was added.
              Tick the lines to post, check the <strong>stock quantity</strong> (in the item's stock unit), and pick the
              <strong>inventory item</strong> it should go to. Posting adds that quantity to the warehouse at the receipt's
              cost, dates the stock history to the original receipt, and turns tracking on for the item.
              The receipts are not approved again, so no accounting entries are repeated.
            </p>
            <div class="alert alert-warning">
              <i class="fa fa-exclamation-triangle"></i>
              Stock used since then was <strong>not</strong> deducted either. For older receipts whose stock is
              already used up, leave them unticked. Otherwise stock will be overstated.
            </div>
            <div class="alert alert-info">
              <i class="fa fa-info-circle"></i>
              <strong>Bought against a POS product by mistake?</strong> Pick the correct inventory item under
              <em>Post to item</em>. The receipt line and the purchase order line are moved to that item before
              stock is posted, so the PO, receipt and costing all point at the ingredient.
            </div>

            <?php if (empty($groups)) { ?>
              <p class="text-success bold mtop20"><i class="fa fa-check-circle"></i> Nothing to backfill. Every approved receipt line has posted its stock.</p>
            <?php } else { ?>
            <?php echo form_open(admin_url('warehouse/stock_backfill'), ['id' => 'backfill-form']); ?>
            <div class="table-responsive">
              <table class="table table-bordered">
                <thead>
                  <tr>
                    <th style="width:36px;"><input type="checkbox" id="chk-all" title="Select all"></th>
                    <th>Receipt</th>
                    <th>Purchase order</th>
                    <th>Received</th>
                    <th>Warehouse</th>
                    <th class="text-right">Amount</th>
                    <th class="text-right">Current stock</th>
                    <th style="min-width:240px;">Post to item</th>
                    <th style="min-width:170px;">Stock qty to add</th>
                  </tr>
                </thead>
                <tbody>
                <?php foreach ($groups as $item_id => $g) {
                  $it = $g['item'];
                  $is_inv = $it['can_be_inventory'] === 'can_be_inventory';
                  $default_target = $suggest_target($it);
                ?>
                  <tr class="active">
                    <td><input type="checkbox" class="chk-item" data-item="<?php echo (int) $item_id; ?>"></td>
                    <td colspan="8">
                      <strong><?php echo html_escape($it['item_name']); ?></strong>
                      <?php if ($it['item_code'] !== '') { ?><span class="text-muted">(<?php echo html_escape($it['item_code']); ?>)</span><?php } ?>
                      <?php if (!$is_inv) { ?>
                        <span class="label label-danger mleft5">POS product, not an inventory item</span>
                      <?php } elseif ((int) $it['without_checking_warehouse'] === 1) { ?>
                        <span class="label label-warning mleft5">Not tracked</span>
                      <?php } else { ?>
                        <span class="label label-success mleft5">Tracked now</span>
                      <?php } ?>
                    </td>
                  </tr>
                  <?php foreach ($g['lines'] as $l) { $lid = (int) $l['id']; ?>
                  <tr>
                    <td><input type="checkbox" name="lines[<?php echo $lid; ?>][on]" value="1" class="chk-line" data-item="<?php echo (int) $item_id; ?>"></td>
                    <td><a href="<?php echo admin_url('warehouse/view_purchase/' . (int) $l['goods_receipt_id']); ?>" target="_blank"><?php echo html_escape($l['goods_receipt_code']); ?></a></td>
                    <td><?php echo html_escape($l['pur_order_number'] ?? ''); ?></td>
                    <td><?php echo _d(substr($l['receipt_date'], 0, 10)); ?></td>
                    <td><?php echo html_escape($l['warehouse_name'] ?? ''); ?></td>
                    <td class="text-right"><?php echo app_format_number($l['line_amount']); ?></td>
                    <td class="text-right"><?php echo app_format_number((float) $l['current_stock'], true); ?></td>
                    <td>
                      <select name="lines[<?php echo $lid; ?>][item_id]" class="form-control input-sm target-select">
                        <option value="">— choose inventory item —</option>
                        <?php foreach ($targets as $t) { ?>
                        <option value="<?php echo (int) $t['id']; ?>" data-unit="<?php echo html_escape($t['unit_name'] ?? ''); ?>" <?php if ((int) $t['id'] === $default_target) echo 'selected'; ?>>
                          <?php echo html_escape($t['description'] . ($t['commodity_code'] !== '' ? ' (' . $t['commodity_code'] . ')' : '')); ?>
                        </option>
                        <?php } ?>
                      </select>
                      <?php if ($default_target && $default_target !== (int) $it['commodity_code']) { ?>
                        <small class="text-info">Suggested from the product name. Check it.</small>
                      <?php } ?>
                    </td>
                    <td>
                      <div class="input-group input-group-sm">
                        <input type="number" name="lines[<?php echo $lid; ?>][qty]" class="form-control text-right"
                          min="0" step="any" value="<?php echo (float) $l['suggested_qty']; ?>">
                        <span class="input-group-addon unit-label"></span>
                      </div>
                      <small class="text-muted">
                        Receipt: <?php echo app_format_number($l['recorded_batches'], true); ?> batch(es)
                        <?php if ($l['batch_size'] === null || $l['batch_size'] === '') { ?>
                          × <?php echo app_format_number((float) ($l['po_units_per_batch'] ?: $l['item_units_per_batch'] ?: 1), true); ?> units/batch
                        <?php } ?>
                      </small>
                    </td>
                  </tr>
                  <?php } ?>
                <?php } ?>
                </tbody>
              </table>
            </div>
            <div class="text-right">
              <span class="text-muted mright10" id="sel-count">0 selected</span>
              <button type="submit" class="btn btn-primary" id="btn-backfill" disabled>
                <i class="fa fa-check"></i> Post stock for selected lines
              </button>
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
    var n = $('.chk-line:checked').length;
    $('#sel-count').text(n + ' selected');
    $('#btn-backfill').prop('disabled', n === 0);
    $('.chk-item').each(function () {
      var $l = $('.chk-line[data-item="' + $(this).data('item') + '"]');
      $(this).prop('checked', $l.length && $l.filter(':checked').length === $l.length);
    });
    $('#chk-all').prop('checked', n > 0 && n === $('.chk-line').length);
  }
  function syncUnit($sel) {
    $sel.closest('tr').find('.unit-label').text($sel.find('option:selected').data('unit') || 'units');
  }
  $('.target-select').each(function () { syncUnit($(this)); }).on('change', function () { syncUnit($(this)); });
  $('#chk-all').on('change', function () { $('.chk-line').prop('checked', this.checked); sync(); });
  $('.chk-item').on('change', function () {
    $('.chk-line[data-item="' + $(this).data('item') + '"]').prop('checked', this.checked); sync();
  });
  $('.chk-line').on('change', sync);
  $('#backfill-form').on('submit', function () {
    var missing = $('.chk-line:checked').filter(function () {
      var $r = $(this).closest('tr');
      return !$r.find('.target-select').val() || !(parseFloat($r.find('input[type=number]').val()) > 0);
    }).length;
    if (missing) { alert(missing + ' selected line(s) need an inventory item and a quantity above 0.'); return false; }
    var n = $('.chk-line:checked').length;
    if (!confirm('Post stock for ' + n + ' receipt line(s)? This adds stock and can only be undone with a stock adjustment.')) return false;
    $('#btn-backfill').prop('disabled', true).text('Posting…');
  });
});
</script>
</body>
</html>
