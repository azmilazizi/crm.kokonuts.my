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
                      <option value="card_sales">Credit Card/Debit Card Sales</option>
                      <option value="card_charges">Credit Card/Debit Card Charges</option>
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
                  <div class="text-muted small">
                    Rows available: <span id="bank-statement-rows-total">0</span>,
                    Rows selected: <span id="bank-statement-rows-selected">0</span>
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
<!-- box loading -->
<div id="box-loading"></div>

<div class="modal fade" id="create-transaction-modal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
        <h4 class="modal-title">Create Transaction</h4>
        <div class="text-muted small" id="create-transaction-statement-meta" style="margin-top: 6px; display: none;">
          <span class="statement-date"></span>
          <span class="m-l-10 statement-amount"></span>
        </div>
      </div>
      <div class="modal-body create-transaction-modal-body">
        <div class="row m-t-15">
          <div class="col-md-12">
            <div class="form-group">
              <label for="create-transaction-type">Transaction Type</label>
              <select class="form-control" id="create-transaction-type">
                <option value="purchase_order">Purchase Order</option>
                <option value="expense">Expense</option>
                <option value="bill">Bill</option>
                <option value="journal_entry">Journal Entry</option>
              </select>
            </div>
          </div>
        </div>
        <div class="m-t-15" id="create-transaction-form-container"></div>
      </div>
      <div class="modal-footer create-transaction-modal-footer">
        <div class="create-transaction-footer-default">
          <button type="button" class="btn btn-link" data-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-info" id="create-transaction-submit">Create</button>
        </div>
      </div>
    </div>
  </div>
</div>
<style>
  .create-transaction-modal-body {
    max-height: calc(100vh - 220px);
    overflow-y: auto;
  }
  .create-transaction-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
  }
  .create-transaction-footer-default {
    margin-right: auto;
  }
  .create-transaction-footer-order {
    margin-left: auto;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
  }
  .purchase-order-details-panel {
    border: 1px solid #e2e2e2;
    border-radius: 4px;
    padding: 15px;
    margin-bottom: 15px;
  }
  .purchase-order-add-item-btn {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }
  .purchase-order-totals .total-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
  }
  .purchase-order-totals .total-row strong {
    font-weight: 600;
  }
</style>
<?php init_tail(); ?>

<?php require 'modules/accounting/assets/js/banking/import_xlsx_posted_bank_transactions_js.php';?>
</body>
</html>
