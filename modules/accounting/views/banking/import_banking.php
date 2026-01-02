<?php defined('BASEPATH') or exit('No direct script access allowed'); 
?>
<?php
  $file_header = array();
  $file_header[] = _l('invoice_payments_table_date_heading');
  $file_header[] = _l('description');
  $file_header[] = _l('withdrawals');
  $file_header[] = _l('deposits');
  $file_header[] = 'Matched Transaction';
  $file_header[] = '';

?>

<?php init_head(); ?>
<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-12">
        <div class="panel_s">
          <div class="panel-body">
            <?php if(!isset($simulate)) { ?>
            <ul>
              <li class="text-danger">1. <?php echo _l('file_xlsx_banking'); ?></li>
              <li class="text-danger">2. <?php echo _l('file_xlsx_format'); ?></li>
            </ul>
            <?php } ?>
            
            <div class="row">
              <div class="col-md-4">
               <?php echo form_open_multipart(admin_url('accounting/import_xlsx_banking'),array('id'=>'import_form')) ;?>
                    <?php echo form_hidden('leads_import','true'); ?>
                    <?php echo render_select('bank_account',$bank_accounts,array('id','name', 'account_type_name'),'bank_account', $bank_id); ?>
                    <?php echo render_input('file_csv','choose_excel_file','','file'); ?> 

                    <div class="form-group">
                      <button id="uploadfile" type="button" class="btn btn-info import" onclick="return uploadfilecsv();" ><?php echo _l('import'); ?></button>
                    </div>
                  <?php echo form_close(); ?>
              </div>
              <div class="col-md-8">
                <div class="form-group" id="file_upload_response"></div>
              </div>
            </div>
            <div class="table-responsive no-dt">
              <div class="row m-b-15 align-items-end">
                <div class="col-md-4">
                  <div class="form-group">
                    <label for="transaction-type-filter">Transaction type</label>
                    <select id="transaction-type-filter" class="form-control">
                      <option value="">Select transactions</option>
                      <option value="duitnow_qr">DuitnowQR</option>
                      <option value="card_sales">Credit Card/Debit Card</option>
                      <option value="grabfood_settlement">Grabfood Settlement</option>
                      <option value="foodpanda_settlement">Foodpanda Settlement</option>
                      <option value="shopeefood_settlement">Shopeefood Settlement</option>
                    </select>
                  </div>
                </div>
                <div class="col-md-8">
                  <div class="form-group d-flex flex-column flex-sm-row align-items-sm-end" style="gap: 10px; margin-top: 25px;">
                    <button type="button" class="btn btn-info" id="transaction-type-apply">Select</button>
                    <button type="button" class="btn btn-default" id="transaction-type-reset">Reset</button>
                    <button type="button" class="btn btn-success" id="transaction-type-create-bulk" disabled>Create Bulk Journal Entry</button>
                  </div>
                </div>
              </div>
              <ul class="nav nav-tabs m-b-15" id="bank-statement-tabs">
                <li class="active">
                  <a href="#" data-filter="all">All <span class="badge" id="bank-statement-count-all">0</span></a>
                </li>
                <li>
                  <a href="#" data-filter="matched">Matched <span class="badge" id="bank-statement-count-matched">0</span></a>
                </li>
                <li>
                  <a href="#" data-filter="not-matched">Not Matched <span class="badge" id="bank-statement-count-not-matched">0</span></a>
                </li>
              </ul>
              <table class="table table-hover table-bordered" id="bank-statement-table">
                <thead>
                  <tr>
                    <th class="text-center">
                      <input type="checkbox" id="bank-statement-select-all" aria-label="<?php echo _l('select_all'); ?>">
                    </th>
                    <?php
                      for($i=0;$i<count($file_header);$i++){
                        $extra_class = '';
                        $extra_style = '';
                        if($file_header[$i] === _l('withdrawals') || $file_header[$i] === _l('deposits')){
                          $extra_class = 'statement-amount';
                          $extra_style = 'style="width: 10%;"';
                        }
                        ?>
                        <th class="bold <?php echo $extra_class; ?>" <?php echo $extra_style; ?>>
                          <?php echo new_html_entity_decode($file_header[$i]) ?>
                        </th>
                        <?php
                      }
                    ?>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
            <hr>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="bulk-journal-entry-modal" tabindex="-1" role="dialog" aria-labelledby="bulkJournalEntryModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="bulkJournalEntryModalLabel">Create Bulk Journal Entry</h4>
        <button type="button" class="close" data-dismiss="modal" aria-label="<?php echo _l('close'); ?>">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label for="bulk-journal-entry-transaction">Select Transaction</label>
          <select id="bulk-journal-entry-transaction" class="form-control">
            <option value="">Select transactions</option>
            <option value="duitnow_qr">DuitnowQR</option>
            <option value="card_sales">Credit Card/Debit Card</option>
            <option value="grabfood_settlement">Grabfood Settlement</option>
            <option value="foodpanda_settlement">Foodpanda Settlement</option>
            <option value="shopeefood_settlement">Shopeefood Settlement</option>
          </select>
        </div>
        <p class="text-muted m-b-0">Selected rows: <span id="bulk-journal-entry-count">0</span></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
        <button type="button" class="btn btn-success" id="bulk-journal-entry-submit">Create Bulk Journal Entry</button>
      </div>
    </div>
  </div>
</div>
<!-- box loading -->
<div id="box-loading"></div>
<?php init_tail(); ?>

<?php require 'modules/accounting/assets/js/banking/import_xlsx_posted_bank_transactions_js.php';?>
</body>
</html>
