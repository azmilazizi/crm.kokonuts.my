<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-12">

        <?php if (!empty($scanned['confidence']) && strtolower($scanned['confidence']) === 'low'): ?>
        <div class="alert alert-warning alert-dismissible">
          <button type="button" class="close" data-dismiss="alert">&times;</button>
          <i class="fa fa-exclamation-triangle"></i>
          <strong><?php echo _l('low_confidence_warning'); ?></strong>
        </div>
        <?php endif; ?>

        <!-- HEADER -->
        <div class="panel_s">
          <div class="panel-body">
            <div class="row">
              <div class="col-md-6">
                <h4 class="no-margin font-bold">
                  <i class="fa fa-file-text"></i>
                  <?php echo $title; ?>
                </h4>
              </div>
              <div class="col-md-6 text-right" style="padding-top:4px;">
                <span id="draft-saved-notice" class="text-muted" style="display:none;margin-right:10px;line-height:32px;font-size:12px;"></span>
                <button type="button" class="btn btn-default" id="btn-cancel">
                  <?php echo _l('cancel'); ?>
                </button>
                <button type="button" class="btn btn-info" id="btn-save-draft">
                  <i class="fa fa-save"></i> <?php echo _l('save_draft'); ?>
                </button>
                <button type="button" class="btn btn-success" id="btn-create-po">
                  <i class="fa fa-check-circle"></i> <?php echo _l('create_purchase_order'); ?>
                </button>
              </div>
            </div>

            <hr>

            <!-- Standard fields -->
            <div class="row">
              <div class="col-md-4">
                <div class="form-group">
                  <label><?php echo _l('vendor'); ?> <span class="text-danger">*</span></label>
                  <select id="f_vendor_id" name="vendor_id" class="selectpicker" data-live-search="true" data-width="100%" data-none-selected-text="— Select vendor —">
                    <option value=""></option>
                    <?php foreach ($vendors as $v):
                      $v_id  = is_object($v) ? $v->userid  : ($v['userid']  ?? '');
                      $v_co  = is_object($v) ? $v->company : ($v['company'] ?? '');
                      $v_vat = is_object($v) ? ($v->vat ?? '') : ($v['vat'] ?? '');
                    ?>
                    <option value="<?php echo (int)$v_id; ?>"
                      data-code="<?php echo htmlspecialchars($v_vat); ?>"
                      data-name="<?php echo htmlspecialchars($v_co); ?>"
                      <?php $sel_id = !empty($draft['vendor_id']) ? $draft['vendor_id'] : ($draft['vendor_id_suggested'] ?? 0);
                            if ((int)$v_id === (int)$sel_id) echo 'selected'; ?>>
                      <?php echo htmlspecialchars($v_co); ?>
                    </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label><?php echo _l('draft_name'); ?> <span class="text-danger">*</span></label>
                  <input type="text" id="f_order_name" class="form-control"
                    value="<?php echo htmlspecialchars($draft['order_name'] ?? ''); ?>"
                    placeholder="e.g. Bakery Supplies — Jan 2026">
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label><?php echo _l('order_number_lable'); ?></label>
                  <input type="text" id="f_order_number" class="form-control bg-gray" readonly
                    value="<?php echo htmlspecialchars($draft['order_number'] ?? ''); ?>"
                    placeholder="<?php echo _l('order_number_auto'); ?>">
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-4">
                <div class="form-group">
                  <label><?php echo _l('order_date'); ?> <span class="text-danger">*</span></label>
                  <?php
                  $order_date_val = !empty($draft['order_date']) ? _d($draft['order_date']) : _d(date('Y-m-d'));
                  echo render_date_input('order_date', '', $order_date_val);
                  ?>
                </div>
              </div>
            </div>

            <!-- Attachments -->
            <div class="row">
              <div class="col-md-8">
                <label><?php echo _l('draft_attachments'); ?> <small class="text-muted">(PDF, JPG, PNG — upload after saving the draft)</small></label>
                <div id="attach-dropzone" class="well text-center" style="border:2px dashed #d9d9d9;cursor:pointer;padding:18px 10px;border-radius:4px;">
                  <i class="fa fa-cloud-upload fa-2x text-muted"></i>
                  <p class="text-muted mbot0">Click to select files or drag &amp; drop here</p>
                  <input type="file" id="attach-input" multiple accept=".pdf,.jpg,.jpeg,.png,.gif,.webp" style="display:none">
                </div>
                <div id="attach-list" style="margin-top:8px;">
                  <?php if (!empty($draft['attachments'])): ?>
                    <?php foreach ($draft['attachments'] as $att):
                      $fname = $att['file_name'] ?? '';
                      $furl  = base_url('modules/purchase/uploads/pur_order_draft/' . ($draft['id'] ?? '') . '/' . $fname);
                      $ext   = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
                      $is_img = in_array($ext, ['jpg','jpeg','png','gif','webp']);
                      $is_pdf = $ext === 'pdf';
                    ?>
                    <div class="attach-item" data-id="<?php echo $att['id']; ?>" style="margin-bottom:6px;">
                      <?php if ($is_img): ?>
                        <a href="<?php echo $furl; ?>" class="attach-preview-img" data-url="<?php echo $furl; ?>" data-name="<?php echo htmlspecialchars($fname); ?>">
                          <img src="<?php echo $furl; ?>" style="height:48px;width:auto;border-radius:3px;border:1px solid #ddd;margin-right:6px;vertical-align:middle;cursor:pointer;">
                          <?php echo htmlspecialchars($fname); ?>
                        </a>
                      <?php elseif ($is_pdf): ?>
                        <a href="<?php echo $furl; ?>" target="_blank" title="Open PDF">
                          <i class="fa fa-file-pdf-o fa-lg text-danger"></i>
                          <?php echo htmlspecialchars($fname); ?>
                        </a>
                      <?php else: ?>
                        <a href="<?php echo $furl; ?>" target="_blank">
                          <i class="fa fa-file-o"></i>
                          <?php echo htmlspecialchars($fname); ?>
                        </a>
                      <?php endif; ?>
                      <small class="text-muted">(<?php echo number_format(($att['size_bytes'] ?? 0) / 1024, 1); ?> KB)</small>
                    </div>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
              </div>
            </div>

          </div><!-- /panel-body standard -->
        </div><!-- /panel_s standard -->

        <!-- LINE ITEMS -->
        <div class="panel_s">
          <div class="panel-body">
            <h5 class="bold no-margin"><?php echo _l('items'); ?></h5>
            <div class="table-responsive" style="margin-top:10px;">
              <table class="table table-bordered" id="items-table">
                <thead>
                  <tr>
                    <th style="width:22%"><?php echo _l('item'); ?></th>
                    <th><?php echo _l('description'); ?></th>
                    <th style="width:8%;text-align:center"><?php echo _l('qty'); ?></th>
                    <th style="width:11%;text-align:right"><?php echo _l('unit_price'); ?></th>
                    <th style="width:11%;text-align:right"><?php echo _l('subtotal'); ?></th>
                    <th style="width:10%;text-align:right"><?php echo _l('line_discount'); ?></th>
                    <th style="width:38px"></th>
                  </tr>
                </thead>
                <tbody id="items-body"></tbody>
              </table>
            </div>
            <button type="button" class="btn btn-default btn-sm" id="btn-add-item">
              <i class="fa fa-plus"></i> <?php echo _l('pur_add_item'); ?>
            </button>
          </div>
        </div>

        <!-- TOTALS -->
        <div class="panel_s">
          <div class="panel-body">
            <div class="row">
              <div class="col-md-5 col-md-offset-7">
                <table class="table table-condensed no-margin">
                  <tr>
                    <td class="bold"><?php echo _l('subtotal'); ?></td>
                    <td class="text-right" style="width:140px"><span id="disp-subtotal">0.00</span></td>
                  </tr>
                  <tr>
                    <td class="bold">
                      <?php echo _l('order_discount'); ?>
                      <div class="btn-group btn-group-xs" style="margin-left:6px;vertical-align:middle;">
                        <button type="button" class="btn btn-default btn-xs active" id="dc-btn-amount" data-dtype="amount">RM</button>
                        <button type="button" class="btn btn-default btn-xs" id="dc-btn-pct" data-dtype="percentage">%</button>
                      </div>
                    </td>
                    <td class="text-right">
                      <input type="number" id="dc-value" class="form-control input-sm text-right"
                        value="<?php echo (float)($draft['discount_value'] ?? 0); ?>"
                        min="0" step="0.01" style="width:110px;display:inline-block;">
                    </td>
                  </tr>
                  <tr>
                    <td class="bold"><?php echo _l('shipping_tax_fee'); ?></td>
                    <td class="text-right">
                      <input type="number" id="shipping-fee" class="form-control input-sm text-right"
                        value="<?php echo (float)($draft['shipping_fee'] ?? 0); ?>"
                        min="0" step="0.01" style="width:110px;display:inline-block;">
                    </td>
                  </tr>
                  <tr>
                    <td class="bold" style="font-size:15px"><?php echo _l('grand_total'); ?></td>
                    <td class="text-right" style="font-size:15px"><strong><span id="disp-grand-total">0.00</span></strong></td>
                  </tr>
                </table>
                <!-- Hidden form fields carrying computed totals -->
                <input type="hidden" id="h-items-subtotal"  name="items_subtotal"  value="0">
                <input type="hidden" id="h-discount-type"   name="discount_type"   value="<?php echo $draft['discount_type'] ?? 'amount'; ?>">
                <input type="hidden" id="h-discount-value"  name="discount_value"  value="0">
                <input type="hidden" id="h-shipping-fee"    name="shipping_fee"    value="0">
                <input type="hidden" id="h-total-discount"  name="total_discount"  value="0">
                <input type="hidden" id="h-grand-total"     name="grand_total"     value="0">
              </div>
            </div>
          </div>
        </div>

        <!-- ITEMS RECEIVED -->
        <div class="panel_s">
          <div class="panel-body">
            <div class="checkbox checkbox-primary no-margin">
              <input type="checkbox" id="chk-items-received" value="1"
                <?php if (!empty($draft['items_received'])) echo 'checked'; ?>>
              <label for="chk-items-received" class="bold"><?php echo _l('items_received_section'); ?></label>
            </div>
            <div id="sec-items-received" style="margin-top:12px;<?php echo empty($draft['items_received']) ? 'display:none;' : ''; ?>">
              <div class="row">
                <div class="col-md-4">
                  <div class="form-group">
                    <label><?php echo _l('warehouse'); ?></label>
                    <?php if (!empty($warehouses)): ?>
                    <select id="f_warehouse_id" name="warehouse_id" class="selectpicker" data-width="100%" data-none-selected-text="— Select —">
                      <option value=""></option>
                      <?php foreach ($warehouses as $wh):
                        $wh_id   = is_object($wh) ? ($wh->warehouse_id   ?? $wh->id   ?? '') : ($wh['warehouse_id']   ?? $wh['id']   ?? '');
                        $wh_name = is_object($wh) ? ($wh->warehouse_name ?? $wh->name ?? '') : ($wh['warehouse_name'] ?? $wh['name'] ?? '');
                      ?>
                      <option value="<?php echo (int)$wh_id; ?>"
                        <?php if (!empty($draft['warehouse_id']) && $draft['warehouse_id'] == $wh_id) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($wh_name); ?>
                      </option>
                      <?php endforeach; ?>
                    </select>
                    <?php else: ?>
                    <p class="text-muted">No warehouses configured.</p>
                    <input type="hidden" id="f_warehouse_id" name="warehouse_id" value="">
                    <?php endif; ?>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group">
                    <label><?php echo _l('date_received'); ?></label>
                    <?php
                    $dr_val = !empty($draft['order_date']) ? _d($draft['order_date']) : _d(date('Y-m-d'));
                    echo render_date_input('date_received', '', $dr_val);
                    ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- PAYMENT DONE -->
        <div class="panel_s">
          <div class="panel-body">
            <div class="checkbox checkbox-primary no-margin">
              <input type="checkbox" id="chk-payment-done" value="1"
                <?php if (!empty($draft['is_paid'])) echo 'checked'; ?>>
              <label for="chk-payment-done" class="bold"><?php echo _l('payment_done_section'); ?></label>
            </div>
            <div id="sec-payment-done" style="margin-top:12px;<?php echo empty($draft['is_paid']) ? 'display:none;' : ''; ?>">
              <div class="table-responsive">
                <table class="table table-bordered" id="pmts-table">
                  <thead>
                    <tr>
                      <th style="width:160px"><?php echo _l('amount'); ?></th>
                      <th><?php echo _l('payment_mode'); ?></th>
                      <th style="width:160px"><?php echo _l('date'); ?></th>
                      <th style="width:38px"></th>
                    </tr>
                  </thead>
                  <tbody id="pmts-body"></tbody>
                </table>
              </div>
              <button type="button" class="btn btn-default btn-sm" id="btn-add-pmt">
                <i class="fa fa-plus"></i> <?php echo _l('add_payment_row'); ?>
              </button>
            </div>
          </div>
        </div>

        <!-- Bottom actions -->
        <div class="row mbot20">
          <div class="col-md-12 text-right">
            <button type="button" class="btn btn-default" id="btn-cancel-btm"><?php echo _l('cancel'); ?></button>
            <button type="button" class="btn btn-info" id="btn-save-draft-btm">
              <i class="fa fa-save"></i> <?php echo _l('save_draft'); ?>
            </button>
            <button type="button" class="btn btn-success" id="btn-create-po-btm">
              <i class="fa fa-check-circle"></i> <?php echo _l('create_purchase_order'); ?>
            </button>
          </div>
        </div>

      </div><!-- /col -->
    </div><!-- /row -->
  </div><!-- /content -->
</div><!-- /wrapper -->

<!-- Image preview modal -->
<style>
  #modal-img-preview { text-align: center; }
  #modal-img-preview .modal-dialog { display: inline-block; text-align: left; max-width: 90vw; width: auto; }
</style>
<div class="modal fade" id="modal-img-preview" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header" style="padding:8px 15px;">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title" id="img-preview-title"></h4>
      </div>
      <div class="modal-body text-center" style="padding:10px;">
        <img id="img-preview-src" src="" style="max-width:100%;max-height:80vh;border-radius:4px;">
      </div>
    </div>
  </div>
</div>

<!-- Cancel confirmation modal -->
<div class="modal fade" id="modal-cancel" tabindex="-1">
  <div class="modal-dialog" style="width:320px">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title"><?php echo _l('cancel_confirm_msg'); ?></h4>
      </div>
      <div class="modal-body" style="padding:10px 15px;">
        <div class="btn-group-vertical" style="width:100%">
          <button type="button" class="btn btn-danger" id="mc-discard"><?php echo _l('discard_changes'); ?></button>
          <button type="button" class="btn btn-default mbot5 mtop5" id="mc-keep" style="margin-top:5px;margin-bottom:5px;"><?php echo _l('keep_editing'); ?></button>
          <button type="button" class="btn btn-info" id="mc-save"><?php echo _l('save_draft'); ?></button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Hidden PO creation form (submitted normally, not via AJAX) -->
<form id="po-form" method="POST" action="<?php echo admin_url('purchase/create_po_from_draft/' . ($draft['id'] ?? '')); ?>" style="display:none">
  <?php echo form_hidden($this->security->get_csrf_token_name(), $this->security->get_csrf_hash()); ?>
  <div id="po-form-fields"></div>
</form>

<?php init_tail(); ?>

<script>
(function ($) {
  'use strict';

  /* ── Data from PHP ─────────────────────────────────── */
  var DRAFT_ID    = '<?php echo addslashes($draft['id'] ?? ''); ?>';
  var SAVE_URL    = admin_url + 'purchase/save_pur_order_draft/' + DRAFT_ID;
  var LIST_URL    = admin_url + 'purchase/purchase_order_drafts';
  // Convert PHP date format string (from app.options.date_format) → Bootstrap datepicker format
  var DATE_FORMAT = (function () {
    var f = app.options.date_format || 'd/m/Y';
    return f.replace('dd', '__DD__').replace('mm', '__MM__')
             .replace('d', 'dd').replace('m', 'mm')
             .replace('Y', 'yyyy').replace('y', 'yy')
             .replace('__DD__', 'dd').replace('__MM__', 'mm');
  }());
  var TODAY_SQL   = '<?php echo date('Y-m-d'); ?>';
  var CURRENCY    = '<?php echo addslashes($currency->symbol ?? 'RM'); ?>';

  var PAYMENT_MODES = <?php
    $pm_json = [];
    foreach ($payment_modes as $pm) {
        $pm_id   = is_object($pm) ? $pm->id   : ($pm['id'] ?? '');
        $pm_name = is_object($pm) ? $pm->name : ($pm['name'] ?? '');
        $pm_json[] = ['id' => $pm_id, 'name' => $pm_name];
    }
    echo json_encode($pm_json);
  ?>;

  var VENDORS = <?php
    $v_json = [];
    foreach ($vendors as $v) {
        $v_id  = is_object($v) ? $v->userid  : ($v['userid']  ?? '');
        $v_co  = is_object($v) ? $v->company : ($v['company'] ?? '');
        $v_vat = is_object($v) ? ($v->vat ?? '') : ($v['vat'] ?? '');
        $v_json[] = ['id' => (int)$v_id, 'name' => $v_co, 'code' => $v_vat];
    }
    echo json_encode($v_json);
  ?>;

  var INV_ITEMS = <?php echo json_encode($inv_items); ?>;

  var EXISTING_DRAFT = <?php echo !empty($draft) ? json_encode($draft) : 'null'; ?>;

  /* scanned = confidence/vendor metadata from WA scan URL param */
  var SCANNED = <?php echo !empty($scanned) ? json_encode($scanned) : 'null'; ?>;

  var isDirty  = false;
  var rowIdx   = 0;
  var pmtIdx   = 0;

  /* ── Init ─────────────────────────────────────────── */
  $(document).ready(function () {
    // Set initial discount type from existing draft
    var initDtype = '<?php echo addslashes($draft['discount_type'] ?? 'amount'); ?>';
    setDiscountType(initDtype, false);

    if (EXISTING_DRAFT && EXISTING_DRAFT.items && EXISTING_DRAFT.items.length > 0) {
      loadDraftItems(EXISTING_DRAFT.items);
    } else if (SCANNED && !EXISTING_DRAFT) {
      prefillFromScan(SCANNED);
    } else {
      addItemRow();
    }

    if (EXISTING_DRAFT && EXISTING_DRAFT.payments && EXISTING_DRAFT.payments.length > 0) {
      loadPayments(EXISTING_DRAFT.payments);
    }

    // If draft has vendor_name but no vendor_id, try pre-select via suggestion
    <?php if (!empty($draft['vendor_id_suggested'])): ?>
    $('#f_vendor_id').selectpicker('val', '<?php echo (int)$draft['vendor_id_suggested']; ?>');
    <?php endif; ?>

    recalculate();

    // Start dirty tracking after a short delay so init changes don't flag dirty
    setTimeout(function () {
      isDirty = false;
      bindDirtyTracking();
    }, 600);

    bindEvents();
  });

  /* ── Item rows ─────────────────────────────────────── */
  function itemRowHtml(idx) {
    var itemOpts = '<option value=""></option>';
    INV_ITEMS.forEach(function (it) {
      itemOpts += '<option value="' + escHtml(String(it.id)) + '">' + escHtml(it.name) + '</option>';
    });

    var pmodeOpts = paymentModeOptions();

    return [
      '<tr data-row="' + idx + '">',
      '  <td>',
      '    <select name="items[' + idx + '][inventory_item_id]" class="selectpicker item-select" data-live-search="true" data-width="100%" data-none-selected-text="—">',
      '      ' + itemOpts,
      '    </select>',
      '    <input type="hidden" name="items[' + idx + '][id]"                  class="item-h-id"   value="">',
      '    <input type="hidden" name="items[' + idx + '][inventory_item_name]" class="item-h-name" value="">',
      '  </td>',
      '  <td><input type="text"   name="items[' + idx + '][description]" class="form-control item-desc"        placeholder="Description"></td>',
      '  <td><input type="number" name="items[' + idx + '][quantity]"    class="form-control item-qty text-right"  value="1"  min="0" step="0.001"></td>',
      '  <td><input type="number" name="items[' + idx + '][unit_price]"  class="form-control item-up  text-right bg-gray" value="0" readonly tabindex="-1"></td>',
      '  <td><input type="number" name="items[' + idx + '][subtotal]"    class="form-control item-sub text-right"  value="0"  min="0" step="0.01"></td>',
      '  <td><input type="number" name="items[' + idx + '][discount]"    class="form-control item-disc text-right" value="0"  min="0" step="0.01"></td>',
      '  <td class="text-center" style="vertical-align:middle;">',
      '    <a href="#" class="btn btn-danger btn-xs rm-item"><i class="fa fa-trash"></i></a>',
      '  </td>',
      '</tr>',
    ].join('\n');
  }

  function addItemRow(data) {
    rowIdx++;
    var $row = $(itemRowHtml(rowIdx));
    $('#items-body').append($row);
    $row.find('.item-select').selectpicker({ container: 'body' });

    if (data) {
      if (data.id) $row.find('.item-h-id').val(data.id);
      $row.find('.item-desc').val(data.description || '');
      var qty  = parseFloat(data.quantity) || 1;
      var sub  = parseFloat(data.subtotal) || 0;
      var disc = parseFloat(data.discount) || 0;
      var up   = qty > 0 ? round4((sub - disc) / qty) : 0;
      $row.find('.item-qty').val(qty);
      $row.find('.item-sub').val(round2(sub));
      $row.find('.item-up').val(up);
      $row.find('.item-disc').val(round2(parseFloat(data.discount) || 0));
      if (data.inventory_item_id) {
        $row.find('.item-select').selectpicker('val', String(data.inventory_item_id));
        $row.find('.item-h-name').val(data.inventory_item_name || '');
      }
    }

    bindItemRowEvents($row);
    recalculate();
    return $row;
  }

  function loadDraftItems(items) {
    items.forEach(function (it) { addItemRow(it); });
  }

  function bindItemRowEvents($row) {
    $row.find('.item-qty, .item-sub, .item-disc').on('input change', function () {
      var qty  = parseFloat($row.find('.item-qty').val())  || 0;
      var sub  = parseFloat($row.find('.item-sub').val())  || 0;
      var disc = parseFloat($row.find('.item-disc').val()) || 0;
      var up   = qty > 0 ? round4((sub - disc) / qty) : 0;
      $row.find('.item-up').val(up);
      recalculate();
    });
    $row.find('.item-select').on('changed.bs.select', function () {
      var name = $(this).find('option:selected').text().trim();
      $row.find('.item-h-name').val(name);
      if (!$row.find('.item-desc').val()) {
        $row.find('.item-desc').val(name);
      }
    });
  }

  /* ── Payment rows ──────────────────────────────────── */
  function paymentModeOptions() {
    var html = '<option value="">— Select —</option>';
    PAYMENT_MODES.forEach(function (m) {
      html += '<option value="' + escHtml(String(m.id)) + '">' + escHtml(m.name) + '</option>';
    });
    return html;
  }

  function pmtRowHtml(idx) {
    return [
      '<tr data-pmt="' + idx + '">',
      '  <td>',
      '    <input type="number" name="payments[' + idx + '][amount]"          class="form-control pmt-amount text-right" min="0" step="0.01" value="0">',
      '    <input type="hidden" name="payments[' + idx + '][id]"              class="pmt-h-id" value="">',
      '    <input type="hidden" name="payments[' + idx + '][payment_mode_label]" class="pmt-h-label" value="">',
      '    <input type="hidden" class="pmt-h-mode-id" value="">',
      '  </td>',
      '  <td>',
      '    <select name="payments[' + idx + '][payment_mode_id]" class="selectpicker pmt-mode" data-width="100%">',
      '      ' + paymentModeOptions(),
      '    </select>',
      '  </td>',
      '  <td><input type="text" name="payments[' + idx + '][payment_date]" class="form-control pmt-date datepicker" value="' + ($('#order_date').val() || sqlToDisplay(TODAY_SQL)) + '"></td>',
      '  <td class="text-center" style="vertical-align:middle;"><a href="#" class="btn btn-danger btn-xs rm-pmt"><i class="fa fa-trash"></i></a></td>',
      '</tr>',
    ].join('\n');
  }

  function addPaymentRow(data) {
    pmtIdx++;
    var $row = $(pmtRowHtml(pmtIdx));
    $('#pmts-body').append($row);
    $row.find('.selectpicker').selectpicker({ container: 'body' });
    $row.find('.datepicker').datepicker({ format: DATE_FORMAT, autoclose: true, todayHighlight: true });

    if (data) {
      $row.find('.pmt-h-id').val(data.id || '');
      $row.find('.pmt-amount').val(round2(parseFloat(data.amount) || 0));
      if (data.payment_date) $row.find('.pmt-date').val(sqlToDisplay(data.payment_date));
      if (data.payment_mode_id) {
        $row.find('.pmt-mode').selectpicker('val', String(data.payment_mode_id));
        $row.find('.pmt-h-mode-id').val(String(data.payment_mode_id));
      }
    } else {
      var remaining = parseFloat($('#h-grand-total').val()) || 0;
      $row.find('.pmt-amount').val(round2(remaining));
    }

    $row.find('.pmt-mode').on('changed.bs.select', function () {
      $row.find('.pmt-h-label').val($(this).find('option:selected').text().trim());
      $row.find('.pmt-h-mode-id').val($(this).val() || '');
    });

    return $row;
  }

  function loadPayments(pmts) {
    pmts.forEach(function (p) { addPaymentRow(p); });
  }

  /* ── Scan pre-fill ─────────────────────────────────── */
  function prefillFromScan(scan) {
    if (scan.vendor) {
      var match = fuzzyVendor(scan.vendor);
      if (match) {
        $('#f_vendor_id').selectpicker('val', String(match.id));
      }
    }
    if (scan.date) {
      $('#order_date').val(sqlToDisplay(scan.date));
    }
    if (scan.tax) {
      $('#shipping-fee').val(round2(parseFloat(scan.tax)));
    }
    if (scan.items && scan.items.length) {
      scan.items.forEach(function (it) {
        var qty = parseFloat(it.qty) || 1;
        var up  = parseFloat(it.unit_price) || 0;
        addItemRow({ description: it.description || '', quantity: qty, unit_price: up, subtotal: round2(qty * up), discount: 0 });
      });
    } else {
      addItemRow();
    }
    var name = buildOrderName(scan);
    if (name) { $('#f_order_name').val(name); }
    recalculate();
  }

  function buildOrderName(scan) {
    var descs = (scan.items || []).map(function (it) { return it.description || ''; }).filter(Boolean);
    if (descs.length) return descs.map(toPascalCase).join(', ');
    if (scan.vendor) return scan.vendor;
    if (scan.receipt_number) return 'Receipt ' + scan.receipt_number;
    return '';
  }

  function toPascalCase(s) {
    return s.replace(/\w\S*/g, function (w) {
      return w.charAt(0).toUpperCase() + w.slice(1).toLowerCase();
    });
  }

  function fuzzyVendor(name) {
    if (!name) return null;
    var nl = name.toLowerCase();
    // exact
    for (var i = 0; i < VENDORS.length; i++) {
      if (VENDORS[i].name.toLowerCase() === nl) return VENDORS[i];
    }
    // substring
    for (var i = 0; i < VENDORS.length; i++) {
      var vl = VENDORS[i].name.toLowerCase();
      if (vl.indexOf(nl) !== -1 || nl.indexOf(vl) !== -1) return VENDORS[i];
    }
    // word overlap (≥3-char words, skip common legal suffixes)
    var stop = ['BHD','SDN','PTE','LTD','INC','CORP','CO','AND','THE','OF'];
    var words = nl.split(/[\s,\.&\/()]+/).filter(function (w) {
      return w.length >= 3 && stop.indexOf(w.toUpperCase()) === -1;
    });
    var best = null, bestScore = 0;
    VENDORS.forEach(function (v) {
      var vl = v.name.toLowerCase();
      var sc = words.filter(function (w) { return vl.indexOf(w) !== -1; }).length;
      if (sc > bestScore) { bestScore = sc; best = v; }
    });
    return bestScore >= 1 ? best : null;
  }

  /* ── Totals calculation ────────────────────────────── */
  function recalculate() {
    var subtotal = 0;
    $('#items-body tr').each(function () {
      var s = parseFloat($(this).find('.item-sub').val())  || 0;
      var d = parseFloat($(this).find('.item-disc').val()) || 0;
      subtotal += Math.max(0, s - d);
    });

    var dtype  = $('#h-discount-type').val();
    var dval   = parseFloat($('#dc-value').val()) || 0;
    var ship   = parseFloat($('#shipping-fee').val()) || 0;

    var totalDisc = dtype === 'percentage'
      ? Math.max(0, subtotal * dval / 100)
      : Math.max(0, dval);
    var grand = Math.max(0, subtotal - totalDisc + ship);

    $('#disp-subtotal').text(fmt(subtotal));
    $('#disp-grand-total').text(fmt(grand));

    $('#h-items-subtotal').val(round2(subtotal));
    $('#h-discount-value').val(round2(dval));
    $('#h-shipping-fee').val(round2(ship));
    $('#h-total-discount').val(round2(totalDisc));
    $('#h-grand-total').val(round2(grand));
  }

  function setDiscountType(dtype, recalc) {
    $('#h-discount-type').val(dtype);
    if (dtype === 'percentage') {
      $('#dc-btn-pct').addClass('active');
      $('#dc-btn-amount').removeClass('active');
    } else {
      $('#dc-btn-amount').addClass('active');
      $('#dc-btn-pct').removeClass('active');
    }
    if (recalc !== false) recalculate();
  }

  /* ── Events ────────────────────────────────────────── */
  function bindEvents() {
    $('#btn-add-item').on('click', function () { addItemRow(); isDirty = true; });

    $(document).on('click', '.rm-item', function (e) {
      e.preventDefault();
      $(this).closest('tr').remove();
      recalculate();
      isDirty = true;
    });

    $('#dc-btn-amount, #dc-btn-pct').on('click', function () {
      setDiscountType($(this).data('dtype'));
      isDirty = true;
    });

    $('#dc-value, #shipping-fee').on('input change', function () {
      recalculate(); isDirty = true;
    });

    $('#chk-items-received').on('change', function () {
      $(this).is(':checked') ? $('#sec-items-received').slideDown(200) : $('#sec-items-received').slideUp(200);
      isDirty = true;
    });

    $('#chk-payment-done').on('change', function () {
      if ($(this).is(':checked')) {
        $('#sec-payment-done').slideDown(200);
        if ($('#pmts-body tr').length === 0) addPaymentRow();
      } else {
        $('#sec-payment-done').slideUp(200);
      }
      isDirty = true;
    });

    $('#btn-add-pmt').on('click', function () { addPaymentRow(); isDirty = true; });
    $(document).on('click', '.rm-pmt', function (e) {
      e.preventDefault();
      $(this).closest('tr').remove();
      isDirty = true;
    });

    $('#btn-save-draft, #btn-save-draft-btm').on('click', function () { saveDraft(); });
    $('#btn-create-po, #btn-create-po-btm').on('click', function () { createPO(); });

    function doCancel() {
      if (isDirty) { $('#modal-cancel').modal('show'); }
      else { window.location.href = LIST_URL; }
    }
    $('#btn-cancel, #btn-cancel-btm').on('click', doCancel);
    $('#mc-discard').on('click', function () { window.location.href = LIST_URL; });
    $('#mc-keep').on('click', function () { $('#modal-cancel').modal('hide'); });
    $('#mc-save').on('click', function () {
      $('#modal-cancel').modal('hide');
      saveDraft(function () { window.location.href = LIST_URL; });
    });

    // Image preview
    $(document).on('click', '.attach-preview-img', function (e) {
      e.preventDefault();
      $('#img-preview-src').attr('src', $(this).data('url'));
      $('#img-preview-title').text($(this).data('name'));
      $('#modal-img-preview').modal('show');
    });

    // Attachment zone
    $('#attach-dropzone').on('click', function () { $('#attach-input').trigger('click'); });
    $('#attach-dropzone').on('dragover', function (e) {
      e.preventDefault(); $(this).css('border-color','#337ab7');
    }).on('dragleave drop', function () {
      $(this).css('border-color','#d9d9d9');
    });
  }

  function bindDirtyTracking() {
    $(document).on('input change', '#f_vendor_id, #f_order_name, #order_date, #f_warehouse_id, #shipping-fee, #dc-value', function () {
      isDirty = true;
    });
  }

  /* ── Save draft (AJAX) ─────────────────────────────── */
  function saveDraft(cb) {
    var data = collectFormData();
    var err  = validateBasic(data);
    if (err) { alert(err); return; }

    $('#btn-save-draft, #btn-save-draft-btm').prop('disabled', true).text('Saving…');

    $.ajax({
      url: SAVE_URL, method: 'POST', data: data,
      success: function (resp) {
        $('#btn-save-draft, #btn-save-draft-btm').prop('disabled', false).html('<i class="fa fa-save"></i> <?php echo addslashes(_l('save_draft')); ?>');
        if (resp.success) {
          isDirty = false;
          $('#draft-saved-notice').text('<?php echo addslashes(_l('draft_saved_at')); ?> ' + resp.saved_at).show();
          setTimeout(function () { $('#draft-saved-notice').fadeOut(); }, 5000);
          if (!DRAFT_ID && resp.id) {
            DRAFT_ID = resp.id;
            SAVE_URL = admin_url + 'purchase/save_pur_order_draft/' + DRAFT_ID;
            $('#po-form').attr('action', admin_url + 'purchase/create_po_from_draft/' + DRAFT_ID);
            history.replaceState(null, '', admin_url + 'purchase/pur_order_draft_form/' + DRAFT_ID);
          }
          if (typeof cb === 'function') cb();
        } else {
          alert(resp.message || 'Failed to save draft.');
        }
      },
      error: function () {
        $('#btn-save-draft, #btn-save-draft-btm').prop('disabled', false).html('<i class="fa fa-save"></i> <?php echo addslashes(_l('save_draft')); ?>');
        alert('Network error. Please try again.');
      }
    });
  }

  /* ── Create PO (form submit) ───────────────────────── */
  function createPO() {
    var data = collectFormData();
    var err  = validateBasic(data);
    if (err) { alert(err); return; }

    var hasItem = false;
    data.forEach(function (f) {
      if (/^items\[\d+\]\[description\]$/.test(f.name) && f.value.trim()) hasItem = true;
      if (/^items\[\d+\]\[inventory_item_id\]$/.test(f.name) && f.value) hasItem = true;
    });
    if (!hasItem) { alert('<?php echo addslashes(_l('item_required_for_po')); ?>'); return; }

    var $fields = $('#po-form-fields').empty();
    data.forEach(function (f) {
      $fields.append($('<input type="hidden">').attr('name', f.name).val(f.value));
    });
    $('#po-form').submit();
  }

  /* ── Collect form data ─────────────────────────────── */
  function collectFormData() {
    var data = [];
    function p(n, v) { data.push({ name: n, value: v }); }

    p('vendor_id',     $('#f_vendor_id').val() || '');
    p('order_name',    $('#f_order_name').val());
    p('order_date',    $('#order_date').val());
    p('items_subtotal', $('#h-items-subtotal').val());
    p('discount_type',  $('#h-discount-type').val());
    p('discount_value', $('#h-discount-value').val());
    p('shipping_fee',   $('#h-shipping-fee').val());
    p('total_discount', $('#h-total-discount').val());
    p('grand_total',    $('#h-grand-total').val());

    var ii = 0;
    $('#items-body tr').each(function () {
      var $r = $(this);
      p('items[' + ii + '][id]',                  $r.find('.item-h-id').val());
      p('items[' + ii + '][inventory_item_id]',   $r.find('.item-select').val() || '');
      p('items[' + ii + '][inventory_item_name]', $r.find('.item-h-name').val());
      p('items[' + ii + '][description]',         $r.find('.item-desc').val());
      p('items[' + ii + '][quantity]',            $r.find('.item-qty').val());
      p('items[' + ii + '][unit_price]',          $r.find('.item-up').val());
      p('items[' + ii + '][subtotal]',            $r.find('.item-sub').val());
      p('items[' + ii + '][discount]',            $r.find('.item-disc').val());
      ii++;
    });

    if ($('#chk-items-received').is(':checked')) {
      p('items_received_enabled', '1');
      p('warehouse_id', $('#f_warehouse_id').val() || '');
      p('date_received', $('#date_received').val());
    }

    if ($('#chk-payment-done').is(':checked')) {
      p('payment_done_enabled', '1');
      var pi = 0;
      $('#pmts-body tr').each(function () {
        var $r = $(this);
        p('payments[' + pi + '][id]',               $r.find('.pmt-h-id').val());
        p('payments[' + pi + '][amount]',            $r.find('.pmt-amount').val());
        p('payments[' + pi + '][payment_mode_id]',  $r.find('.pmt-h-mode-id').val() || $r.find('.pmt-mode').val() || '');
        p('payments[' + pi + '][payment_mode_label]', $r.find('.pmt-h-label').val());
        p('payments[' + pi + '][payment_date]',      $r.find('.pmt-date').val());
        pi++;
      });
    }

    return data;
  }

  function validateBasic(data) {
    function get(name) {
      var found = data.filter(function (f) { return f.name === name; });
      return found.length ? found[0].value : '';
    }
    if (!get('vendor_id'))  return '<?php echo addslashes(_l('vendor_required')); ?>';
    if (!get('order_name')) return '<?php echo addslashes(_l('order_name_required')); ?>';
    if (!get('order_date')) return '<?php echo addslashes(_l('order_date_required')); ?>';
    return null;
  }

  /* ── Helpers ───────────────────────────────────────── */
  function round2(n) { return Math.round(n * 100) / 100; }
  function round4(n) { return Math.round(n * 10000) / 10000; }
  function fmt(n)    {
    return parseFloat(n || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  }
  function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }
  function sqlToDisplay(d) {
    if (!d) return '';
    // d is YYYY-MM-DD; map to the CRM's display format
    var parts = String(d).split('-');
    if (parts.length !== 3) return d;
    var Y = parts[0], M = parts[1], D = parts[2];
    var fmt = app.options.date_format || 'd/m/Y';
    return fmt.replace('d', D).replace('m', M).replace('Y', Y).replace('y', Y.slice(-2));
  }

}(jQuery));
</script>
</body>
</html>
