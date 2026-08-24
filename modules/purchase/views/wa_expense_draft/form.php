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
                  <i class="fa fa-shopping-bag"></i>
                  <?php echo $title; ?>
                </h4>
              </div>
              <div class="col-md-6 text-right" style="padding-top:4px;">
                <a href="<?php echo admin_url('expenses/list_expenses'); ?>" class="btn btn-default">
                  <?php echo _l('cancel'); ?>
                </a>
                <?php if ($expense): ?>
                <button type="button" class="btn btn-info" id="btn-save-draft">
                  <i class="fa fa-save"></i> Save Draft
                </button>
                <button type="button" class="btn btn-success" id="btn-finalize-expense">
                  <i class="fa fa-check-circle"></i> Finalize Expense
                </button>
                <?php endif; ?>
              </div>
            </div>
            <hr>

            <div class="row">

              <!-- Left column: receipt attachment(s) -->
              <div class="col-md-4">
                <?php if (!empty($attachments)): ?>
                  <div class="form-group">
                    <label>Receipt / Attachment</label>
                    <div class="input-group">
                      <select class="form-control" id="f_attachment_select">
                        <?php foreach ($attachments as $att): ?>
                          <?php
                            $att_url  = admin_url('purchase/wa_expense_draft_attachment/' . $expense['id'] . '/' . $att['id']);
                            $att_ext  = strtolower(pathinfo($att['file_name'] ?? '', PATHINFO_EXTENSION));
                            $att_type = ($att_ext === 'pdf') ? 'pdf' : 'image';
                          ?>
                          <option value="<?php echo htmlspecialchars($att_url); ?>"
                                  data-type="<?php echo $att_type; ?>">
                            <?php echo htmlspecialchars($att['file_name']); ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                      <span class="input-group-btn">
                        <button type="button" class="btn btn-default" id="btn-preview-attachment">
                          <i class="fa fa-eye mright5"></i>Preview
                        </button>
                      </span>
                    </div>
                    <?php if (count($attachments) > 1): ?>
                      <p class="text-muted mtop5"><small><?php echo count($attachments); ?> pages/attachments — select one to preview.</small></p>
                    <?php endif; ?>
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
                             value="<?php echo htmlspecialchars($expense['expense_name'] ?? ''); ?>"
                             placeholder="e.g. Vendor name">
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                      <label>Expense Name</label>
                      <input type="text" id="f_expense_name" class="form-control"
                             value="<?php echo htmlspecialchars($expense['expense_name'] ?? ''); ?>"
                             placeholder="e.g. Office supplies">
                    </div>
                  </div>
                </div>

                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                      <label>Date <span class="text-danger">*</span></label>
                      <?php echo render_date_input('date_display', '', $expense['date'] ? _d($expense['date']) : ''); ?>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                      <label>Amount (RM) <span class="text-danger">*</span></label>
                      <input type="number" id="f_amount" class="form-control" step="0.01" min="0"
                             value="<?php echo number_format((float)($expense['amount'] ?? 0), 2, '.', ''); ?>">
                    </div>
                  </div>
                </div>

                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                      <label>Category <span class="text-danger">*</span></label>
                      <select id="f_category_id" class="selectpicker" data-width="100%" data-live-search="true"
                              data-none-selected-text="— Select —">
                        <option value=""></option>
                        <?php foreach ($categories as $cat): ?>
                          <option value="<?php echo (int)$cat['id']; ?>"
                            <?php if ((int)($expense['category'] ?? 0) === (int)$cat['id']) echo 'selected'; ?>>
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
                            placeholder="Optional note"><?php echo htmlspecialchars($expense['note'] ?? ''); ?></textarea>
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
<?php echo form_open(admin_url('purchase/wa_expense_draft_form/' . ($expense['id'] ?? '')), ['id' => 'draft-form']); ?>
  <input type="hidden" name="vendor_name"  id="h_vendor_name">
  <input type="hidden" name="expense_name" id="h_expense_name">
  <input type="hidden" name="date"         id="h_date">
  <input type="hidden" name="amount"       id="h_amount">
  <input type="hidden" name="category_id"  id="h_category_id">
  <input type="hidden" name="note"         id="h_note">
  <input type="hidden" name="action"       id="h_action" value="save">
<?php echo form_close(); ?>

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
        $('#h_vendor_name').val($('#f_vendor_name').val());
        $('#h_expense_name').val($('#f_expense_name').val());
        $('#h_date').val($('input[name="date_display"]').val());
        $('#h_amount').val($('#f_amount').val());
        $('#h_category_id').val($('#f_category_id').val());
        $('#h_note').val($('#f_note').val());
    }

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

    $('#btn-save-draft').on('click', function() {
        collectFields();
        $('#h_action').val('save');
        $('#draft-form').submit();
    });

    $('#btn-finalize-expense').on('click', function() {
        if (!confirm('Finalize this expense? It will be moved to the expenses list.')) return;
        collectFields();
        $('#h_action').val('finalize');
        $('#draft-form').submit();
    });

})(jQuery);
</script>
</body>
</html>
