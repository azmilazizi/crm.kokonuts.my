<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$groups = [];
foreach ($lines as $l) {
    $groups[$l['commodity_code']]['item'] = $l;
    $groups[$l['commodity_code']]['lines'][] = $l;
}
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
              Tick the lines you want to post. Posting adds the received quantity to the warehouse at the
              receipt's cost, dates the stock history to the original receipt date, and turns tracking on for the item.
              The receipts are not approved again, so no accounting entries are repeated.
            </p>
            <div class="alert alert-warning">
              <i class="fa fa-exclamation-triangle"></i>
              Stock used since then was <strong>not</strong> deducted either. For older receipts whose stock is
              already used up, leave them unticked. Otherwise stock will be overstated. Check <em>Current stock</em>
              and a physical count before posting.
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
                    <th class="text-right">Qty</th>
                    <th class="text-right">Unit cost</th>
                    <th class="text-right">Amount</th>
                    <th class="text-right">Current stock</th>
                  </tr>
                </thead>
                <tbody>
                <?php foreach ($groups as $item_id => $g) { $it = $g['item']; ?>
                  <tr class="active">
                    <td><input type="checkbox" class="chk-item" data-item="<?php echo (int) $item_id; ?>"></td>
                    <td colspan="8">
                      <strong><?php echo html_escape($it['item_name']); ?></strong>
                      <span class="text-muted">(<?php echo html_escape($it['item_code']); ?>)</span>
                      <?php if ((int) $it['without_checking_warehouse'] === 1) { ?>
                        <span class="label label-warning mleft5">Not tracked</span>
                      <?php } else { ?>
                        <span class="label label-success mleft5">Tracked now</span>
                      <?php } ?>
                    </td>
                  </tr>
                  <?php foreach ($g['lines'] as $l) {
                    $qty  = (float) $l['quantities'];
                    $sub  = (float) $l['sub_total'];
                    $cost = ($qty != 0 && $sub != 0) ? $sub / $qty : (float) $l['unit_price'];
                  ?>
                  <tr>
                    <td><input type="checkbox" name="line_ids[]" value="<?php echo (int) $l['id']; ?>" class="chk-line" data-item="<?php echo (int) $item_id; ?>"></td>
                    <td><a href="<?php echo admin_url('warehouse/view_purchase/' . (int) $l['goods_receipt_id']); ?>" target="_blank"><?php echo html_escape($l['goods_receipt_code']); ?></a></td>
                    <td><?php echo html_escape($l['pur_order_number'] ?? ''); ?></td>
                    <td><?php echo _d(substr($l['receipt_date'], 0, 10)); ?></td>
                    <td><?php echo html_escape($l['warehouse_name'] ?? ''); ?></td>
                    <td class="text-right"><?php echo app_format_number($qty, true); ?></td>
                    <td class="text-right"><?php echo app_format_number($cost, true); ?></td>
                    <td class="text-right"><?php echo app_format_number($qty * $cost); ?></td>
                    <td class="text-right"><?php echo app_format_number((float) $l['current_stock'], true); ?></td>
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
  $('#chk-all').on('change', function () { $('.chk-line').prop('checked', this.checked); sync(); });
  $('.chk-item').on('change', function () {
    $('.chk-line[data-item="' + $(this).data('item') + '"]').prop('checked', this.checked); sync();
  });
  $('.chk-line').on('change', sync);
  $('#backfill-form').on('submit', function () {
    var n = $('.chk-line:checked').length;
    if (!confirm('Post stock for ' + n + ' receipt line(s)? This adds stock and can only be undone with a stock adjustment.')) return false;
    $('#btn-backfill').prop('disabled', true).text('Posting…');
  });
});
</script>
</body>
</html>
