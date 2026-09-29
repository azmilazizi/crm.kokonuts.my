<?php defined('BASEPATH') or exit('No direct script access allowed');
/*
 * "Create new" vendor / item popups shared by the Purchase Order and the
 * Purchase Order Draft forms. Exposes window.PurQuickCreate:
 *
 *   PurQuickCreate.vendor({ name: 'prefill' }, function (vendor) { ... });
 *   PurQuickCreate.item({ name: 'prefill', vendor_id: 3 }, function (item) { ... });
 *
 * The callback receives the JSON returned by purchase/quick_add_vendor or
 * purchase/quick_add_item.
 */
$CI = &get_instance();
$qc_units  = $CI->purchase_model->get_units();
$qc_groups = $CI->purchase_model->get_commodity_group_add_commodity();
?>
<div class="modal fade" id="qc-vendor-modal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document" style="max-width:460px;">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title">New vendor</h4>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label>Vendor name <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="qc-vendor-company" autocomplete="off">
        </div>
        <div class="row">
          <div class="col-sm-6 form-group">
            <label>Vendor code / VAT</label>
            <input type="text" class="form-control" id="qc-vendor-vat" autocomplete="off">
          </div>
          <div class="col-sm-6 form-group">
            <label>Phone</label>
            <input type="text" class="form-control" id="qc-vendor-phone" autocomplete="off">
          </div>
        </div>
        <p class="text-muted no-margin" style="font-size:12px;">Add address, contacts and other details later from the vendor profile.</p>
        <div class="alert alert-danger qc-error" style="display:none;margin:10px 0 0;"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('cancel'); ?></button>
        <button type="button" class="btn btn-primary" id="qc-vendor-save">Create vendor</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="qc-item-modal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document" style="max-width:520px;">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title">New item</h4>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label>Item name <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="qc-item-name" autocomplete="off">
        </div>
        <div class="row">
          <div class="col-sm-6 form-group">
            <label>Item code</label>
            <input type="text" class="form-control" id="qc-item-code" autocomplete="off" placeholder="Generated from name">
          </div>
          <div class="col-sm-6 form-group">
            <label>Group</label>
            <select class="form-control" id="qc-item-group">
              <option value="0">—</option>
              <?php foreach ($qc_groups as $g) { ?>
              <option value="<?php echo (int) $g['id']; ?>"><?php echo html_escape($g['name']); ?></option>
              <?php } ?>
            </select>
          </div>
        </div>
        <div class="row">
          <div class="col-sm-4 form-group">
            <label>Unit</label>
            <select class="form-control" id="qc-item-unit">
              <option value="">—</option>
              <?php foreach ($qc_units as $u) { ?>
              <option value="<?php echo (int) $u['id']; ?>"><?php echo html_escape($u['label']); ?></option>
              <?php } ?>
            </select>
          </div>
          <div class="col-sm-4 form-group">
            <label>Units/Batch</label>
            <input type="number" class="form-control text-right" id="qc-item-upb" min="0" step="0.0001">
          </div>
          <div class="col-sm-4 form-group">
            <label>Batch price</label>
            <input type="number" class="form-control text-right" id="qc-item-price" min="0" step="0.01">
          </div>
        </div>
        <p class="text-muted no-margin qc-item-vendor-note" style="font-size:12px;display:none;">Will also be linked to the selected vendor.</p>
        <div class="alert alert-danger qc-error" style="display:none;margin:10px 0 0;"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('cancel'); ?></button>
        <button type="button" class="btn btn-primary" id="qc-item-save">Create item</button>
      </div>
    </div>
  </div>
</div>

<script>
(function ($) {
  'use strict';
  var vendorCb = null, itemCb = null, itemVendor = 0;

  function showError($m, msg) { $m.find('.qc-error').text(msg).show(); }

  function post(url, data, $modal, $btn, cb) {
    $modal.find('.qc-error').hide();
    $btn.prop('disabled', true);
    $.post(admin_url + url, data).done(function (r) {
      try { r = typeof r === 'string' ? JSON.parse(r) : r; } catch (e) { r = { success: false, message: 'Unexpected response.' }; }
      if (!r.success) { showError($modal, r.message || 'Could not save.'); return; }
      $modal.modal('hide');
      if (typeof cb === 'function') cb(r);
    }).fail(function () {
      showError($modal, 'Network error. Please try again.');
    }).always(function () { $btn.prop('disabled', false); });
  }

  window.PurQuickCreate = {
    vendor: function (opts, cb) {
      opts = opts || {};
      vendorCb = cb;
      var $m = $('#qc-vendor-modal');
      $m.find('input').val('');
      $m.find('.qc-error').hide();
      $('#qc-vendor-company').val(opts.name || '');
      $m.modal('show');
    },
    item: function (opts, cb) {
      opts = opts || {};
      itemCb = cb;
      itemVendor = parseInt(opts.vendor_id, 10) || 0;
      var $m = $('#qc-item-modal');
      $m.find('input').val('');
      $m.find('select').val(function () { return $(this).find('option:first').val(); });
      $m.find('.qc-error').hide();
      $m.find('.qc-item-vendor-note').toggle(itemVendor > 0);
      $('#qc-item-name').val(opts.name || '');
      if (opts.units_per_batch) $('#qc-item-upb').val(opts.units_per_batch);
      if (opts.price) $('#qc-item-price').val(opts.price);
      $m.modal('show');
    }
  };

  $(function () {
    $('#qc-vendor-modal').on('shown.bs.modal', function () { $('#qc-vendor-company').trigger('focus'); });
    $('#qc-item-modal').on('shown.bs.modal', function () { $('#qc-item-name').trigger('focus'); });

    $('#qc-vendor-save').on('click', function () {
      var $m = $('#qc-vendor-modal');
      var name = $.trim($('#qc-vendor-company').val());
      if (!name) { showError($m, 'Vendor name is required.'); return; }
      post('purchase/quick_add_vendor', {
        company: name,
        vat: $.trim($('#qc-vendor-vat').val()),
        phonenumber: $.trim($('#qc-vendor-phone').val())
      }, $m, $(this), vendorCb);
    });

    $('#qc-item-save').on('click', function () {
      var $m = $('#qc-item-modal');
      var name = $.trim($('#qc-item-name').val());
      if (!name) { showError($m, 'Item name is required.'); return; }
      post('purchase/quick_add_item', {
        description: name,
        commodity_code: $.trim($('#qc-item-code').val()),
        group_id: $('#qc-item-group').val(),
        unit_id: $('#qc-item-unit').val(),
        units_per_batch: $('#qc-item-upb').val(),
        purchase_price: $('#qc-item-price').val(),
        vendor_id: itemVendor
      }, $m, $(this), itemCb);
    });

    $('#qc-vendor-modal input, #qc-item-modal input').on('keydown', function (e) {
      if (e.which === 13) { e.preventDefault(); $(this).closest('.modal').find('.btn-primary').trigger('click'); }
    });
  });
}(jQuery));
</script>
