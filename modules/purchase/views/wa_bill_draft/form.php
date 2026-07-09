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

                <!-- Hidden file input -->
                <input type="file" id="f_attachment_file" accept="image/*,.pdf" style="display:none;">

                <div class="input-group" id="attachment-picker-group">
                  <select class="form-control" id="f_attachment_select">
                    <?php if (empty($attachments)): ?>
                      <option value="" data-type="">No attachment — use Browse to add one</option>
                    <?php else: ?>
                      <?php foreach ($attachments as $att): ?>
                        <?php
                          $att_url  = admin_url('purchase/wa_bill_draft_attachment/' . $bill['id'] . '/' . $att['id']);
                          $att_ext  = strtolower(pathinfo($att['file_name'] ?? '', PATHINFO_EXTENSION));
                          $att_type = ($att_ext === 'pdf') ? 'pdf' : 'image';
                        ?>
                        <option value="<?php echo htmlspecialchars($att_url); ?>"
                                data-id="<?php echo htmlspecialchars($att['id']); ?>"
                                data-type="<?php echo $att_type; ?>">
                          <?php echo htmlspecialchars($att['file_name']); ?>
                        </option>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </select>
                  <span class="input-group-btn">
                    <button type="button" class="btn btn-default" id="btn-preview-attachment">
                      <i class="fa fa-eye mright5"></i>Preview
                    </button>
                    <button type="button" class="btn btn-default" id="btn-browse-attachment"
                            title="Upload a file from your computer">
                      <i class="fa fa-paperclip mright5"></i>Browse
                    </button>
                    <button type="button" class="btn btn-danger" id="btn-remove-attachment"
                            title="Remove selected attachment" style="display:none;">
                      <i class="fa fa-trash"></i>
                    </button>
                  </span>
                </div>

                <div id="attachment-upload-progress" class="mtop5" style="display:none;">
                  <div class="progress progress-striped active" style="margin-bottom:0;">
                    <div class="progress-bar" style="width:100%;"></div>
                  </div>
                  <small class="text-muted">Uploading…</small>
                </div>
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

    var UPLOAD_URL = '<?php echo admin_url("purchase/upload_wa_bill_draft_attachment/" . $bill["id"]); ?>';
    var DELETE_URL = '<?php echo admin_url("purchase/delete_wa_bill_draft_attachment/" . $bill["id"]); ?>/';

    /* ---- helpers ---- */

    function hasRealSelection() {
        var $sel = $('#f_attachment_select');
        return $sel.find('option:selected').val() !== '';
    }

    function syncRemoveBtn() {
        if (hasRealSelection()) {
            $('#btn-remove-attachment').show();
            $('#btn-preview-attachment').prop('disabled', false);
        } else {
            $('#btn-remove-attachment').hide();
            $('#btn-preview-attachment').prop('disabled', true);
        }
    }

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

    /* ---- initial state ---- */
    syncRemoveBtn();

    /* ---- browse button triggers file input ---- */
    $('#btn-browse-attachment').on('click', function() {
        $('#f_attachment_file').val('').trigger('click');
    });

    /* ---- file selected → AJAX upload ---- */
    $('#f_attachment_file').on('change', function() {
        var file = this.files[0];
        if (!file) return;

        var fd = new FormData();
        fd.append('file', file);

        $('#attachment-upload-progress').show();
        $('#btn-browse-attachment, #btn-save-draft, #btn-finalize-bill').prop('disabled', true);

        $.ajax({
            url: UPLOAD_URL,
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false,
        }).done(function(res) {
            if (res.success) {
                /* Remove placeholder "no attachment" option if present */
                $('#f_attachment_select option[value=""]').remove();

                /* Add new option and select it */
                var $opt = $('<option>')
                    .val(res.url)
                    .attr('data-id', res.id)
                    .attr('data-type', res.type)
                    .text(res.file_name);
                $('#f_attachment_select').append($opt).val(res.url);

                syncRemoveBtn();
                alert_float('success', 'File uploaded: ' + res.file_name);
            } else {
                alert_float('warning', res.message || 'Upload failed.');
            }
        }).fail(function() {
            alert_float('danger', 'Upload request failed. Please try again.');
        }).always(function() {
            $('#attachment-upload-progress').hide();
            $('#btn-browse-attachment, #btn-save-draft, #btn-finalize-bill').prop('disabled', false);
        });
    });

    /* ---- remove selected attachment ---- */
    $('#btn-remove-attachment').on('click', function() {
        var $sel   = $('#f_attachment_select');
        var $opt   = $sel.find('option:selected');
        var attachId = $opt.data('id');

        if (!attachId) return;
        if (!confirm('Remove this attachment?')) return;

        $.get(DELETE_URL + attachId, function(res) {
            if (res.success) {
                $opt.remove();
                /* Re-add placeholder if list is now empty */
                if ($('#f_attachment_select option').length === 0) {
                    $('#f_attachment_select').append(
                        $('<option>').val('').attr('data-type', '').text('No attachment — use Browse to add one')
                    );
                }
                syncRemoveBtn();
                alert_float('success', 'Attachment removed.');
            } else {
                alert_float('danger', 'Could not remove attachment.');
            }
        });
    });

    /* ---- dropdown change ---- */
    $('#f_attachment_select').on('change', syncRemoveBtn);

    /* ---- preview button ---- */
    $('#btn-preview-attachment').on('click', function() {
        var $opt     = $('#f_attachment_select option:selected');
        var url      = $opt.val();
        var fileType = $opt.data('type');
        var fileName = $opt.text().trim();

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

    /* ---- save / finalize ---- */
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
