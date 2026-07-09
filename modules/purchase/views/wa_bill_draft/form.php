<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-12">

        <div class="panel_s">
          <div class="panel-body">
            <div class="row">
              <div class="col-md-6">
                <h4 class="no-margin font-bold">
                  <i class="fa fa-file-text-o"></i>
                  <?php echo $title; ?>
                </h4>
              </div>
              <div class="col-md-6 text-right" style="padding-top:4px;">
                <a href="<?php echo admin_url('accounting/bills'); ?>" class="btn btn-default">
                  <?php echo _l('cancel'); ?>
                </a>
                <?php if ($bill): ?>
                <button type="button" class="btn btn-info" id="btn-save-draft">
                  <i class="fa fa-save"></i> Save Draft
                </button>
                <button type="button" class="btn btn-success" id="btn-finalize-bill">
                  <i class="fa fa-check-circle"></i> Finalize Bill
                </button>
                <?php endif; ?>
              </div>
            </div>
            <hr>

            <div class="row">

              <!-- Left column: receipt image -->
              <div class="col-md-4">
                <?php if (!empty($attachment)): ?>
                  <div class="form-group">
                    <label>Receipt Photo</label>
                    <div>
                      <a href="<?php echo admin_url('purchase/wa_bill_draft_attachment/' . $bill['id'] . '/' . $attachment['id']); ?>" target="_blank">
                        <img src="<?php echo admin_url('purchase/wa_bill_draft_attachment/' . $bill['id'] . '/' . $attachment['id']); ?>"
                             class="img-responsive"
                             style="max-height:400px;border:1px solid #ddd;border-radius:4px;padding:4px;">
                      </a>
                    </div>
                  </div>
                <?php else: ?>
                  <div class="text-muted" style="padding-top:20px;">
                    <i class="fa fa-image fa-3x"></i>
                    <p>No image attached.</p>
                  </div>
                <?php endif; ?>
              </div>

              <!-- Right column: editable fields -->
              <div class="col-md-8">

                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                      <label>Vendor / Supplier</label>
                      <input type="text" id="f_vendor_name" class="form-control"
                             value="<?php echo htmlspecialchars($bill['expense_name'] ?? ''); ?>"
                             placeholder="e.g. Tenaga Nasional">
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                      <label>Reference No.</label>
                      <input type="text" id="f_reference_no" class="form-control"
                             value="<?php echo htmlspecialchars($bill['reference_no'] ?? ''); ?>"
                             placeholder="Invoice / bill number">
                    </div>
                  </div>
                </div>

                <div class="row">
                  <div class="col-md-4">
                    <div class="form-group">
                      <label>Bill Date <span class="text-danger">*</span></label>
                      <?php echo render_date_input('date_display', '', $bill['date'] ? _d($bill['date']) : ''); ?>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-group">
                      <label>Due Date</label>
                      <?php echo render_date_input('due_date_display', '', $bill['due_date'] ? _d($bill['due_date']) : ''); ?>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-group">
                      <label>Amount (RM) <span class="text-danger">*</span></label>
                      <input type="number" id="f_amount" class="form-control" step="0.01" min="0"
                             value="<?php echo number_format((float)($bill['amount'] ?? 0), 2, '.', ''); ?>">
                    </div>
                  </div>
                </div>

                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                      <label>Bill Category</label>
                      <select id="f_bill_category_id" class="selectpicker" data-width="100%" data-live-search="true"
                              data-none-selected-text="— Select —">
                        <option value=""></option>
                        <?php foreach ($bill_categories as $cat): ?>
                          <option value="<?php echo (int)$cat['id']; ?>"
                            <?php if ((int)($bill['bill_category_id'] ?? 0) === (int)$cat['id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                  </div>
                </div>

                <div class="form-group">
                  <label>Note</label>
                  <textarea id="f_note" class="form-control" rows="3"
                            placeholder="Optional note"><?php echo htmlspecialchars($bill['note'] ?? ''); ?></textarea>
                </div>

              </div><!-- /col-md-8 -->
            </div><!-- /row -->
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<!-- Hidden form for POST -->
<?php echo form_open(admin_url('purchase/wa_bill_draft_form/' . ($bill['id'] ?? '')), ['id' => 'draft-form']); ?>
  <input type="hidden" name="vendor_name"      id="h_vendor_name">
  <input type="hidden" name="date"             id="h_date">
  <input type="hidden" name="due_date"         id="h_due_date">
  <input type="hidden" name="amount"           id="h_amount">
  <input type="hidden" name="reference_no"     id="h_reference_no">
  <input type="hidden" name="bill_category_id" id="h_bill_category_id">
  <input type="hidden" name="note"             id="h_note">
  <input type="hidden" name="action"           id="h_action" value="save">
<?php echo form_close(); ?>

<?php init_tail(); ?>
<script>
(function($) {
    'use strict';

    function collectFields() {
        $('#h_vendor_name').val($('#f_vendor_name').val());
        $('#h_date').val($('input[name="date_display"]').val());
        $('#h_due_date').val($('input[name="due_date_display"]').val());
        $('#h_amount').val($('#f_amount').val());
        $('#h_reference_no').val($('#f_reference_no').val());
        $('#h_bill_category_id').val($('#f_bill_category_id').val());
        $('#h_note').val($('#f_note').val());
    }

    $('#btn-save-draft').on('click', function() {
        collectFields();
        $('#h_action').val('save');
        $('#draft-form').submit();
    });

    $('#btn-finalize-bill').on('click', function() {
        if (!confirm('Finalize this bill? It will be moved to the bills list as an unpaid bill.')) return;
        collectFields();
        $('#h_action').val('finalize');
        $('#draft-form').submit();
    });

})(jQuery);
</script>
</body>
</html>
