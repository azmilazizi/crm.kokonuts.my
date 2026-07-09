<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-12">

        <?php echo form_open(admin_url('purchase/wa_bill_draft_form/' . ($bill['id'] ?? '')), ['id' => 'draft-form']); ?>
        <input type="hidden" name="vendor_id"        id="h_vendor_id">
        <input type="hidden" name="expense_name"     id="h_expense_name">
        <input type="hidden" name="date"             id="h_date">
        <input type="hidden" name="due_date"         id="h_due_date">
        <input type="hidden" name="amount"           id="h_amount">
        <input type="hidden" name="reference_no"     id="h_reference_no">
        <input type="hidden" name="bill_category_id" id="h_bill_category_id">
        <input type="hidden" name="note"             id="h_note">
        <input type="hidden" name="action"           id="h_action" value="save">

        <div class="panel_s">
          <div class="panel-body">

            <div class="row">
              <div class="col-md-10">
                <h4 class="no-margin"><?php echo $title; ?></h4>
              </div>
              <div class="col-md-2 text-right">
                <a href="<?php echo admin_url('accounting/bills'); ?>" class="text-muted"><i class="fa fa-times fa-lg"></i></a>
              </div>
            </div>
            <hr class="hr-panel-heading" />

            <!-- Vendor / Supplier | Expense Name -->
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label><?php echo _l('acc_vendor'); ?> / Supplier</label>
                  <select id="f_vendor_id" class="selectpicker" data-width="100%" data-live-search="true"
                          data-none-selected-text="— Select vendor —">
                    <option value=""></option>
                    <?php foreach ($vendors as $v): ?>
                      <option value="<?php echo (int)$v['userid']; ?>"
                        <?php if ((int)($bill['vendor'] ?? 0) === (int)$v['userid']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($v['company']); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label><?php echo _l('expense_name'); ?></label>
                  <input type="text" id="f_expense_name" class="form-control"
                         value="<?php echo htmlspecialchars($bill['expense_name'] ?? ''); ?>"
                         placeholder="e.g. Monthly electricity bill">
                </div>
              </div>
            </div>

            <!-- Reference No. -->
            <div class="row mtop10">
              <div class="col-md-6">
                <div class="form-group">
                  <label>Reference No.</label>
                  <input type="text" id="f_reference_no" class="form-control"
                         value="<?php echo htmlspecialchars($bill['reference_no'] ?? ''); ?>"
                         placeholder="Invoice / bill number">
                </div>
              </div>
            </div>

            <!-- Bill Date | Due Date | Amount -->
            <div class="row mtop15">
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

            <!-- Receipt Attachment -->
            <div class="row mtop20">
              <div class="col-md-12">
                <label>Receipt / Attachment</label>
                <?php if (!empty($attachment)): ?>
                  <?php
                    $attach_url = admin_url('purchase/wa_bill_draft_attachment/' . $bill['id'] . '/' . $attachment['id']);
                    $ext        = strtolower(pathinfo($attachment['file_name'] ?? '', PATHINFO_EXTENSION));
                    $is_pdf     = $ext === 'pdf';
                  ?>
                  <div class="input-group">
                    <select class="form-control" id="f_attachment_select">
                      <option value="<?php echo htmlspecialchars($attach_url); ?>"
                              data-type="<?php echo $is_pdf ? 'pdf' : 'image'; ?>">
                        <?php echo htmlspecialchars($attachment['file_name']); ?>
                      </option>
                    </select>
                    <span class="input-group-btn">
                      <button type="button" class="btn btn-default" id="btn-preview-attachment">
                        <i class="fa fa-eye mright5"></i>Preview
                      </button>
                    </span>
                  </div>
                <?php else: ?>
                  <div class="input-group">
                    <select class="form-control" id="f_attachment_select" disabled>
                      <option value="">No attachment</option>
                    </select>
                    <span class="input-group-btn">
                      <button type="button" class="btn btn-default" disabled>
                        <i class="fa fa-eye mright5"></i>Preview
                      </button>
                    </span>
                  </div>
                <?php endif; ?>
              </div>
            </div>

            <!-- Bill Category -->
            <div class="row mtop20">
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

            <!-- Note -->
            <div class="form-group">
              <label>Note</label>
              <textarea id="f_note" class="form-control" rows="3"
                        placeholder="Optional note"><?php echo htmlspecialchars($bill['note'] ?? ''); ?></textarea>
            </div>

            <!-- Action buttons -->
            <div class="row mtop20">
              <div class="col-md-12 text-right">
                <a href="<?php echo admin_url('accounting/bills'); ?>" class="btn btn-default mright10">Cancel</a>
                <?php if ($bill): ?>
                <button type="button" class="btn btn-info mright5" id="btn-save-draft">
                  <i class="fa fa-save"></i> Save Draft
                </button>
                <button type="button" class="btn btn-success" id="btn-finalize-bill">
                  <i class="fa fa-check-circle"></i> Finalize Bill
                </button>
                <?php endif; ?>
              </div>
            </div>

          </div><!-- /panel-body -->
        </div><!-- /panel_s -->

        <?php echo form_close(); ?>
        <div class="btn-bottom-pusher"></div>
      </div>
    </div>
  </div>
</div>

<!-- Attachment preview modal -->
<div class="modal fade" id="attachment-preview-modal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
        <h4 class="modal-title" id="attachment-modal-title">Attachment Preview</h4>
      </div>
      <div class="modal-body" style="padding:0;min-height:400px;" id="attachment-preview-body">
        <!-- Content injected by JS -->
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
        <a href="#" id="attachment-open-link" target="_blank" class="btn btn-info">
          <i class="fa fa-external-link mright5"></i>Open in new tab
        </a>
      </div>
    </div>
  </div>
</div>

<?php init_tail(); ?>
<script>
(function($) {
    'use strict';

    function collectFields() {
        $('#h_vendor_id').val($('#f_vendor_id').val());
        $('#h_expense_name').val($('#f_expense_name').val());
        $('#h_date').val($('input[name="date_display"]').val());
        $('#h_due_date').val($('input[name="due_date_display"]').val());
        $('#h_amount').val($('#f_amount').val());
        $('#h_reference_no').val($('#f_reference_no').val());
        $('#h_bill_category_id').val($('#f_bill_category_id').val());
        $('#h_note').val($('#f_note').val());
    }

    $('#btn-preview-attachment').on('click', function() {
        var $select  = $('#f_attachment_select');
        var url      = $select.val();
        var fileType = $select.find('option:selected').data('type');
        var fileName = $select.find('option:selected').text().trim();

        if (!url) return;

        $('#attachment-modal-title').text(fileName || 'Attachment Preview');
        $('#attachment-open-link').attr('href', url);

        var $body = $('#attachment-preview-body');
        $body.empty();

        if (fileType === 'pdf') {
            $body.html('<embed src="' + url + '" type="application/pdf" width="100%" height="600px" style="display:block;">');
        } else {
            $body.html('<div style="text-align:center;padding:10px;"><img src="' + url + '" class="img-responsive" style="max-width:100%;margin:0 auto;"></div>');
        }

        $('#attachment-preview-modal').modal('show');
    });

    $('#btn-save-draft').on('click', function() {
        collectFields();
        $('#h_action').val('save');
        $('#draft-form').submit();
    });

    $('#btn-finalize-bill').on('click', function() {
        if (!confirm('Finalize this bill? It will be added as an Unpaid bill.')) return;
        collectFields();
        $('#h_action').val('finalize');
        $('#draft-form').submit();
    });

})(jQuery);
</script>
</body>
</html>
