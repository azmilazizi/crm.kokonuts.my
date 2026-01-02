<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-12">
        <div class="panel_s">
          <div class="panel-body">
            <h4 class="no-margin"><?php echo _l('import_statement'); ?></h4>
            <hr class="hr-panel-heading" />

            <div class="row">
              <div class="col-md-4">
                <?php echo form_open_multipart(admin_url('accounting/import_statement'), ['id' => 'import_statement_form']); ?>
                  <?php echo render_input('file_csv', 'choose_excel_file', '', 'file'); ?>

                  <div class="form-group">
                    <button id="upload_statement_file" type="button" class="btn btn-info import" onclick="return uploadStatementFile();">
                      <?php echo _l('import'); ?>
                    </button>
                  </div>
                <?php echo form_close(); ?>
              </div>
              <div class="col-md-8">
                <div class="form-group" id="statement_file_upload_response"></div>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table table-bordered table-hover" id="statement-preview-table">
                <thead>
                  <tr></tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<div id="box-loading"></div>
<?php init_tail(); ?>

<?php require 'modules/accounting/assets/js/transaction/import_statement_js.php'; ?>
</body>
</html>
