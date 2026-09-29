<?php defined('BASEPATH') or exit('No direct script access allowed');
/*
 * "Change item" popup for PO lines (link rendered per line in
 * pur_order_preview.php). Load once on the page that hosts the preview.
 * Needs quick_create_modals for "+ Create new item".
 */
$CI = &get_instance();
$pci_targets = $CI->db->select('i.id, i.description, i.commodity_code, i.units_per_batch, u.unit_name')
    ->from(db_prefix() . 'items i')
    ->join(db_prefix() . 'ware_unit_type u', 'u.unit_type_id = i.unit_id', 'left')
    ->where('i.can_be_inventory', 'can_be_inventory')
    ->where('i.active', 1)
    ->order_by('i.description', 'asc')
    ->get()->result_array();
?>
<div class="modal fade" id="pci-modal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document" style="max-width:560px;">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title">Change item</h4>
      </div>
      <div class="modal-body">
        <p class="mbot10">Currently: <strong id="pci-from"></strong> <span id="pci-from-pos" class="label label-danger mleft5" style="display:none;">POS product, not an inventory item</span><span id="pci-from-deleted" class="label label-warning mleft5" style="display:none;">Deleted item</span></p>

        <div class="form-group">
          <label>Move to inventory item <span class="text-danger">*</span>
            <a href="#" id="pci-new-item" class="mleft5" style="font-weight:normal;font-size:12px;"><i class="fa fa-plus"></i> Create new item</a>
          </label>
          <select id="pci-to" class="selectpicker" data-live-search="true" data-width="100%" data-none-selected-text="— choose inventory item —">
            <option value=""></option>
            <?php foreach ($pci_targets as $t) { ?>
            <option value="<?php echo (int) $t['id']; ?>" data-unit="<?php echo html_escape($t['unit_name'] ?? ''); ?>" data-upb="<?php echo (float) $t['units_per_batch']; ?>">
              <?php echo html_escape($t['description'] . ($t['commodity_code'] !== '' ? ' (' . $t['commodity_code'] . ')' : '')); ?>
            </option>
            <?php } ?>
          </select>
        </div>

        <div class="form-group">
          <label>Stock units in one purchased batch <span class="text-danger">*</span></label>
          <div class="input-group" style="max-width:260px;">
            <input type="number" id="pci-upb" class="form-control text-right" min="0" step="any">
            <span class="input-group-addon" id="pci-unit">units</span>
          </div>
          <small class="text-muted">e.g. one 800 g pack = 800. Every batch on the moved lines becomes this many stock units.</small>
        </div>

        <div class="form-group">
          <div class="radio radio-primary no-mtop">
            <input type="radio" name="pci-scope" id="pci-scope-po" value="po" checked>
            <label for="pci-scope-po">This purchase order only</label>
          </div>
          <div class="radio radio-primary">
            <input type="radio" name="pci-scope" id="pci-scope-all" value="all">
            <label for="pci-scope-all">Every purchase order with this item (<span id="pci-count"></span> lines)</label>
          </div>
        </div>

        <div class="checkbox checkbox-primary" id="pci-stop-wrap">
          <input type="checkbox" id="pci-stop">
          <label for="pci-stop">Stop offering <strong class="pci-from-name"></strong> on purchase orders</label>
        </div>

        <div id="pci-preview" class="well well-sm mtop10" style="display:none;"></div>
        <div class="alert alert-danger" id="pci-error" style="display:none;margin:10px 0 0;"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('cancel'); ?></button>
        <button type="button" class="btn btn-default" id="pci-btn-preview">Preview</button>
        <button type="button" class="btn btn-primary" id="pci-btn-apply" disabled>Move</button>
      </div>
    </div>
  </div>
</div>

<script>
(function ($) {
  'use strict';
  var ctx = null;

  function fmt(n) { return (Math.round(n * 1000) / 1000).toLocaleString(); }
  function unit() { return $('#pci-to option:selected').data('unit') || 'units'; }
  function payload() {
    return {
      detail_id: ctx.detailId,
      to_item: $('#pci-to').val(),
      units_per_batch: $('#pci-upb').val(),
      scope: $('input[name="pci-scope"]:checked').val(),
      stop_purchasing: $('#pci-stop').is(':checked') ? 1 : ''
    };
  }
  function invalidate() { $('#pci-preview').hide(); $('#pci-btn-apply').prop('disabled', true); $('#pci-error').hide(); }
  function err(msg) { $('#pci-error').text(msg).show(); }

  function render(r) {
    var u = unit();
    var pos = {};
    r.po_lines.forEach(function (l) { pos[l.po] = true; });
    var html = '<strong>This will:</strong><ul style="margin:6px 0 0 18px;padding:0;">'
      + '<li>Move ' + r.po_lines.length + ' PO line(s) on ' + Object.keys(pos).length + ' PO(s) to the new item</li>'
      + (r.invoice_lines ? '<li>Move ' + r.invoice_lines + ' purchase invoice line(s)</li>' : '')
      + '<li>Move ' + r.receipt_lines + ' receipt line(s), converted to ' + u + '</li>'
      + '<li>Remove ' + fmt(r.stock_out) + ' from <em>' + $('<i>').text(ctx.itemName).html() + '</em> stock (it has ' + fmt(r.from_stock) + ' now)</li>'
      + '<li>Add <strong>' + fmt(r.stock_in) + ' ' + u + '</strong> to the new item\'s stock</li>'
      + '</ul>';
    if (r.stock_short > 0) {
      html += '<p class="text-warning mtop5 no-mbot"><i class="fa fa-exclamation-triangle"></i> '
        + fmt(r.stock_short) + ' of what was received has already left the old item\'s stock, so only what\'s there is removed.</p>';
    }
    if (r.receipts && r.receipts.length) {
      html += '<div style="max-height:220px;overflow:auto;margin-top:8px;"><table class="table table-condensed no-margin" style="font-size:12px;background:#fff;">'
        + '<thead><tr><th>Receipt</th><th>Date</th><th class="text-right">Recorded</th><th class="text-right">Becomes (' + u + ')</th><th class="text-right">Amount</th></tr></thead><tbody>';
      r.receipts.forEach(function (x) {
        html += '<tr' + (x.in_units ? ' class="warning"' : '') + '><td>' + $('<i>').text(x.code).html() + (x.approved ? '' : ' <span class="text-muted">(not approved)</span>') + '</td>'
          + '<td>' + x.date + '</td><td class="text-right">' + fmt(x.recorded) + '</td>'
          + '<td class="text-right">' + fmt(x.units) + (x.in_units ? ' <i class="fa fa-info-circle" title="Price per batch was about 1/1000 (or 1/100…) of the others, so the recorded quantity was scaled back to batches first."></i>' : '') + '</td>'
          + '<td class="text-right">' + (Math.round(x.amount * 100) / 100).toFixed(2) + '</td></tr>';
      });
      html += '</tbody></table></div>';
      if (r.receipts.some(function (x) { return x.in_units; })) {
        html += '<p class="text-warning mtop5 no-mbot" style="font-size:12px;"><i class="fa fa-info-circle"></i> Highlighted rows were keyed in small units (e.g. grams instead of packs) and were scaled back to batches before converting.</p>';
      }
    }
    html += '<p class="text-muted mtop5 no-mbot" style="font-size:12px;">Amounts, payments and accounting don\'t change. POS sales history stays on the old item.</p>';
    $('#pci-preview').html(html).show();
  }

  $(document).on('click', '.po-change-item', function (e) {
    e.preventDefault();
    var $a = $(this);
    ctx = {
      detailId: $a.data('detail-id'),
      itemId: $a.data('item-id'),
      itemName: String($a.data('item-name')),
      isInventory: String($a.data('is-inventory')) === '1',
      deleted: String($a.data('deleted')) === '1',
      upb: parseFloat($a.data('upb')) || 0
    };
    $('#pci-from, .pci-from-name').text(ctx.itemName);
    $('#pci-from-pos').toggle(!ctx.isInventory && !ctx.deleted);
    $('#pci-from-deleted').toggle(ctx.deleted);
    $('#pci-stop-wrap').toggle(!ctx.deleted);
    $('#pci-count').text($a.data('other-lines'));
    $('#pci-scope-po').prop('checked', true);
    $('#pci-stop').prop('checked', !ctx.isInventory && !ctx.deleted);
    $('#pci-to').selectpicker('val', '');
    $('#pci-to option').prop('disabled', false);
    $('#pci-to option[value="' + ctx.itemId + '"]').prop('disabled', true);
    $('#pci-to').selectpicker('refresh');
    $('#pci-upb').val(ctx.upb > 1 ? ctx.upb : '');
    $('#pci-unit').text('units');
    invalidate();
    $('#pci-modal').modal('show');
  });

  $('#pci-to').on('changed.bs.select', function () {
    var $o = $(this).find('option:selected');
    $('#pci-unit').text(unit());
    if (!(ctx.upb > 1) && parseFloat($o.data('upb')) > 0) { $('#pci-upb').val($o.data('upb')); }
    invalidate();
  });
  $('#pci-upb, input[name="pci-scope"]').on('input change', invalidate);

  $('#pci-new-item').on('click', function (e) {
    e.preventDefault();
    PurQuickCreate.item({ name: ctx ? ctx.itemName : '', units_per_batch: $('#pci-upb').val() }, function (it) {
      $('#pci-to').append($('<option>').val(it.id).text(it.name).attr('data-upb', it.units_per_batch || '').attr('data-unit', ''))
        .selectpicker('refresh').selectpicker('val', String(it.id)).trigger('changed.bs.select');
    });
  });

  $('#pci-btn-preview').on('click', function () {
    invalidate();
    if (!$('#pci-to').val()) { err('Choose the inventory item to move to.'); return; }
    if (!(parseFloat($('#pci-upb').val()) > 0)) { err('Enter the stock units in one purchased batch.'); return; }
    var $b = $(this).prop('disabled', true);
    $.post(admin_url + 'purchase/po_change_item', payload()).done(function (r) {
      r = typeof r === 'string' ? JSON.parse(r) : r;
      if (!r.ok) { err(r.error || 'Could not preview.'); return; }
      render(r);
      $('#pci-btn-apply').prop('disabled', false);
    }).fail(function () { err('Network error. Please try again.'); })
      .always(function () { $b.prop('disabled', false); });
  });

  $('#pci-btn-apply').on('click', function () {
    var $b = $(this).prop('disabled', true).text('Moving…');
    $.post(admin_url + 'purchase/po_change_item/apply', payload()).done(function (r) {
      r = typeof r === 'string' ? JSON.parse(r) : r;
      if (!r.ok) { err(r.error || 'Could not move.'); $b.prop('disabled', false); return; }
      $('#pci-modal').modal('hide');
      alert_float('success', 'Moved ' + r.po_lines.length + ' PO line(s) and ' + r.receipt_lines + ' receipt line(s). Added ' + fmt(r.stock_in) + ' ' + unit() + ' to stock.');
      setTimeout(function () { location.reload(); }, 900);
    }).fail(function () { err('Network error. Nothing was confirmed; refresh and check before retrying.'); $b.prop('disabled', false); })
      .always(function () { $b.text('Move'); });
  });
}(jQuery));
</script>
