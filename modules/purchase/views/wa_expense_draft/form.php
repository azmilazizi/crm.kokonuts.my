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
                <a href="<?php echo admin_url('purchase/wa_expense_drafts'); ?>" class="btn btn-default">
                  <?php echo _l('cancel'); ?>
                </a>
                <?php if ($draft): ?>
                <button type="button" class="btn btn-info" id="btn-save-draft">
                  <i class="fa fa-save"></i> Save Draft
                </button>
                <button type="button" class="btn btn-success" id="btn-create-expense">
                  <i class="fa fa-check-circle"></i> Create Expense
                </button>
                <?php endif; ?>
              </div>
            </div>
            <hr>

            <div class="row">

              <!-- Left column: receipt image -->
              <div class="col-md-4">
                <?php if (!empty($draft['attachments'])): ?>
                  <?php $att = $draft['attachments'][0]; ?>
                  <div class="form-group">
                    <label>Receipt Photo</label>
                    <div>
                      <a href="<?php echo admin_url('purchase/wa_expense_draft_attachment/' . $draft['id'] . '/' . $att['id']); ?>" target="_blank">
                        <img src="<?php echo admin_url('purchase/wa_expense_draft_attachment/' . $draft['id'] . '/' . $att['id']); ?>"
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
                      <label>Vendor / Merchant</label>
                      <input type="text" id="f_vendor_name" class="form-control"
                             value="<?php echo htmlspecialchars($draft['vendor_name'] ?? ''); ?>"
                             placeholder="e.g. Aeon, Speedmart">
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                      <label>Description <span class="text-danger">*</span></label>
                      <input type="text" id="f_expense_name" class="form-control"
                             value="<?php echo htmlspecialchars($draft['expense_name'] ?? ''); ?>"
                             placeholder="e.g. Office snacks">
                    </div>
                  </div>
                </div>

                <div class="row">
                  <div class="col-md-4">
                    <div class="form-group">
                      <label>Date <span class="text-danger">*</span></label>
                      <?php echo render_date_input('date_display', '', $draft['date'] ? _d($draft['date']) : ''); ?>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-group">
                      <label>Amount (RM) <span class="text-danger">*</span></label>
                      <input type="number" id="f_amount" class="form-control" step="0.01" min="0"
                             value="<?php echo number_format((float)($draft['amount'] ?? 0), 2, '.', ''); ?>">
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-group">
                      <label>Category <span class="text-danger">*</span></label>
                      <select id="f_category_id" class="selectpicker" data-width="100%" data-live-search="true"
                              data-none-selected-text="— Select —">
                        <option value=""></option>
                        <?php foreach ($categories as $cat): ?>
                          <option value="<?php echo (int)$cat->id; ?>"
                            <?php if ((int)($draft['category_id'] ?? 0) === (int)$cat->id) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($cat->name); ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                  </div>
                </div>

                <div class="form-group">
                  <label>Note</label>
                  <textarea id="f_note" class="form-control" rows="3"
                            placeholder="Optional note"><?php echo htmlspecialchars($draft['note'] ?? ''); ?></textarea>
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
<?php echo form_open(admin_url('purchase/wa_expense_draft_form/' . ($draft['id'] ?? '')), ['id' => 'draft-form']); ?>
  <input type="hidden" name="vendor_name"  id="h_vendor_name">
  <input type="hidden" name="expense_name" id="h_expense_name">
  <input type="hidden" name="date"         id="h_date">
  <input type="hidden" name="amount"       id="h_amount">
  <input type="hidden" name="category_id"  id="h_category_id">
  <input type="hidden" name="note"         id="h_note">
  <input type="hidden" name="action"       id="h_action" value="save">
<?php echo form_close(); ?>

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

    $('#btn-save-draft').on('click', function() {
        collectFields();
        $('#h_action').val('save');
        $('#draft-form').submit();
    });

    $('#btn-create-expense').on('click', function() {
        if (!$('#f_category_id').val()) {
            alert('Please select a category before creating the expense.');
            return;
        }
        if (!confirm('Create expense record from this draft?')) return;
        collectFields();
        $('#h_action').val('convert');
        $('#draft-form').submit();
    });

})(jQuery);
</script>
</body>
</html>
