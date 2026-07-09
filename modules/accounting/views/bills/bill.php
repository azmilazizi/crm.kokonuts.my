<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
   <div class="content">
      <div class="row">
         <div class="col-md-12">
            <?php
            $is_edit     = isset($bill);
            $is_advanced = $is_edit && empty($bill->bill_category_id) && (!empty($bill->debit_account) || !empty($bill->credit_account));
            $date_attrs  = [];
            if ($is_edit && $bill->recurring > 0 && $bill->last_recurring_date != null) {
                $date_attrs['disabled'] = true;
            }
            ?>
            <?php echo form_open_multipart($this->uri->uri_string(), ['id' => 'expense-form', 'class' => 'dropzone dropzone-manual']); ?>
            <?php echo form_hidden('type', $type); ?>
            <?php if ($is_edit) echo form_hidden('is_edit', 'true'); ?>

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

                  <!-- Vendor | Expense Name -->
                  <div class="row">
                     <div class="col-md-6">
                        <label for="vendor"><?php echo _l('acc_vendor'); ?></label>
                        <?php $vendor = $is_edit ? $bill->vendor : ''; ?>
                        <?php echo render_select('vendor', $list_vendor, ['userid', 'company'], '', $vendor); ?>
                     </div>
                     <div class="col-md-6">
                        <label for="expense_name"><?php echo _l('expense_name'); ?></label>
                        <?php $value = $is_edit ? $bill->expense_name : ''; ?>
                        <?php echo render_input('expense_name', '', $value); ?>
                     </div>
                  </div>

                  <!-- Bill date | Due date -->
                  <div class="row mtop15">
                     <div class="col-md-6">
                        <label for="date"><?php echo _l('bill_date'); ?></label>
                        <?php $value = $is_edit ? _d($bill->date) : _d(date('Y-m-d')); ?>
                        <?php echo render_date_input('date', '', $value, $date_attrs); ?>
                     </div>
                     <div class="col-md-6">
                        <label for="due_date"><?php echo _l('acc_due_date'); ?></label>
                        <?php $due_date = $is_edit ? _d($bill->due_date) : _d(date('Y-m-d')); ?>
                        <?php echo render_date_input('due_date', '', $due_date, $date_attrs); ?>
                     </div>
                  </div>

                  <!-- Attachment -->
                  <div class="row mtop20">
                     <div class="col-md-12">
                        <label><?php echo _l('acc_attachment'); ?></label>
                        <?php if ($is_edit && $bill->attachment !== ''): ?>
                           <div class="row">
                              <div class="col-md-10">
                                 <i class="<?php echo get_mime_class($bill->filetype); ?>"></i>
                                 <a href="<?php echo admin_url('accounting/download_file/bill/' . $bill->id); ?>"><?php echo $bill->attachment; ?></a>
                              </div>
                              <?php if ($bill->attachment_added_from == get_staff_user_id() || is_admin()): ?>
                              <div class="col-md-2 text-right">
                                 <a href="<?php echo admin_url('accounting/delete_bill_attachment/' . $bill->id); ?>" class="text-danger _delete"><i class="fa fa-times"></i></a>
                              </div>
                              <?php endif; ?>
                           </div>
                        <?php else: ?>
                           <div id="dropzoneDragArea" class="bill-attachment-dropzone">
                              <div class="bill-attachment-inner">
                                 <i class="fa fa-paperclip text-warning fa-2x mbottom10"></i>
                                 <p class="no-margin bold">Drag and drop supporting files here, or click to browse for uploads.</p>
                                 <p class="text-muted small no-margin">No files selected. Drag and drop here or tap to choose.</p>
                                 <button type="button" class="btn btn-default mtop10"><i class="fa fa-folder-open text-warning mright5"></i> Browse files</button>
                              </div>
                           </div>
                           <div class="dropzone-previews"></div>
                        <?php endif; ?>
                     </div>
                  </div>

                  <!-- Expenses section header with Advanced Entry toggle -->
                  <div class="row mtop25">
                     <div class="col-md-6">
                        <h5 class="no-margin bold"><?php echo _l('expenses'); ?></h5>
                     </div>
                     <div class="col-md-6 text-right">
                        <div class="checkbox checkbox-primary no-margin" style="display:inline-block;">
                           <input type="checkbox" id="advanced_entry_toggle" <?php echo $is_advanced ? 'checked' : ''; ?>>
                           <label for="advanced_entry_toggle">Advanced Entry</label>
                        </div>
                        <input type="hidden" name="advanced_entry" id="advanced_entry_value" value="<?php echo $is_advanced ? 1 : 0; ?>">
                     </div>
                  </div>
                  <hr class="mtop10 mbottom15"/>

                  <!-- Simple entry: Bill category + Amount -->
                  <div id="simple-entry-section"<?php echo $is_advanced ? ' class="hide"' : ''; ?>>
                     <div class="row">
                        <div class="col-md-6">
                           <label for="bill_category_id_simple">Bill category</label>
                           <?php $selected_cat = ($is_edit && !$is_advanced) ? $bill->bill_category_id : ''; ?>
                           <?php echo render_select('bill_category_id_simple', $bill_categories, ['id', 'name'], '', $selected_cat); ?>
                        </div>
                        <div class="col-md-6">
                           <label for="bill_amount_field"><?php echo _l('amount'); ?></label>
                           <?php $simple_amount = (!$is_advanced && $is_edit) ? number_format($bill->amount, 2) : ''; ?>
                           <input type="text" class="form-control" id="bill_amount_field" name="amount" value="<?php echo new_html_entity_decode($simple_amount); ?>" placeholder="0.00" data-type="currency" <?php echo $is_advanced ? 'disabled' : ''; ?>>
                        </div>
                     </div>
                  </div>

                  <!-- Advanced entry: Debit + Credit tables -->
                  <div id="advanced-entry-section"<?php echo !$is_advanced ? ' class="hide"' : ''; ?>>
                     <input type="hidden" name="amount" id="bill_amount_advanced" value="<?php echo $is_edit ? $bill->amount : 0; ?>" <?php echo !$is_advanced ? 'disabled' : ''; ?>>

                     <table id="bill-debit-account" class="table invoice-mapping-table items">
                        <thead>
                           <tr>
                              <th width="50%"><?php echo _l('debit_account'); ?></th>
                              <th width="40%"><?php echo _l('amount'); ?></th>
                              <th width="10%"></th>
                           </tr>
                        </thead>
                        <?php if ($is_edit): $i = 0; foreach ($bill->debit_account as $debit_account): ?>
                        <tr class="bill-debit-account-<?php echo $i; ?> template_children">
                           <td><?php echo render_select('debit_account[' . $i . ']', $list_debit_account, ['id', 'name'], '', $debit_account['account'], []); ?></td>
                           <td><?php echo render_input('debit_amount[' . $i . ']', '', number_format($debit_account['amount'], 2), 'text', ['data-type' => 'currency']); ?></td>
                           <td><button name="add_template" class="btn <?php echo $i == 0 ? 'new_debit_template btn-success' : 'remove_debit_template btn-danger'; ?>" type="button"><i class="fa <?php echo $i == 0 ? 'fa-plus' : 'fa-minus'; ?>"></i></button></td>
                        </tr>
                        <?php $i++; endforeach; else: ?>
                        <tr class="bill-debit-account-0 bill-item-id-0 template_children">
                           <td><?php echo render_select('debit_account[0]', $list_debit_account, ['id', 'name'], '', $acc_bill_deposit_to, []); ?></td>
                           <td><?php echo render_input('debit_amount[0]', '', '', 'text', ['data-type' => 'currency']); ?></td>
                           <td><button name="add_template" class="btn new_debit_template btn-success" type="button"><i class="fa fa-plus"></i></button></td>
                        </tr>
                        <?php endif; ?>
                     </table>

                     <table id="bill-credit-account" class="table invoice-mapping-table items">
                        <thead>
                           <tr>
                              <th width="50%"><?php echo _l('credit_account'); ?></th>
                              <th width="40%"><?php echo _l('amount'); ?></th>
                              <th width="10%"><i class="fa fa-cog"></i></th>
                           </tr>
                        </thead>
                        <tbody id="body-bill-credit-account">
                        <?php if ($is_edit): $i = 0; foreach ($bill->credit_account as $credit_account): ?>
                        <tr class="bill-credit-account-<?php echo $i; ?> template_children">
                           <td><?php echo render_select('credit_account[' . $i . ']', $list_credit_account, ['id', 'name'], '', $credit_account['account'], []); ?></td>
                           <td><?php echo render_input('credit_amount[' . $i . ']', '', number_format($credit_account['amount'], 2), 'text', ['data-type' => 'currency']); ?></td>
                           <td><button name="add_template" class="btn <?php echo $i == 0 ? 'new_credit_template btn-success' : 'remove_template btn-danger'; ?>" type="button"><i class="fa <?php echo $i == 0 ? 'fa-plus' : 'fa-minus'; ?>"></i></button></td>
                        </tr>
                        <?php $i++; endforeach; else: ?>
                        <tr class="bill-credit-account-0 bill-item-id-0 template_children">
                           <td><?php echo render_select('credit_account[0]', $list_credit_account, ['id', 'name'], '', $acc_bill_deposit_to, []); ?></td>
                           <td><?php echo render_input('credit_amount[0]', '', '', 'text', ['data-type' => 'currency']); ?></td>
                           <td><button name="add_template" class="btn new_credit_template btn-success" type="button"><i class="fa fa-plus"></i></button></td>
                        </tr>
                        <?php endif; ?>
                        </tbody>
                     </table>

                     <!-- Total display (advanced mode) -->
                     <div class="col-md-5 col-md-offset-7">
                        <table class="table text-right bold">
                           <tbody>
                              <tr>
                                 <td><span class="bold"><?php echo _l('invoice_total'); ?></span></td>
                                 <td id="bill-total" class="text-danger">
                                    <?php echo app_format_money($is_edit ? $bill->amount : 0, $currency->name); ?>
                                 </td>
                              </tr>
                           </tbody>
                        </table>
                     </div>
                  </div>

                  <!-- Hidden fields needed by model -->
                  <div class="hide">
                     <?php $selected = $is_edit ? $bill->paymentmode : ''; ?>
                     <?php echo render_select('paymentmode', $payment_modes, ['id', 'name'], 'payment_mode', $selected); ?>
                  </div>

                  <!-- Buttons -->
                  <div class="row mtop20">
                     <div class="col-md-12 text-right">
                        <a href="<?php echo admin_url('accounting/bills'); ?>" class="btn btn-default mright10"><?php echo _l('close'); ?></a>
                        <button type="submit" class="btn btn-warning"><?php echo _l('submit'); ?></button>
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

<style>
.bill-attachment-dropzone {
   border: 2px dashed #f0ad4e;
   border-radius: 6px;
   padding: 30px 20px;
   text-align: center;
   cursor: pointer;
   background: rgba(240,173,78,0.04);
   transition: background 0.2s;
}
.bill-attachment-dropzone:hover {
   background: rgba(240,173,78,0.08);
}
.bill-attachment-inner p {
   margin-bottom: 5px;
}
</style>

<?php $this->load->view('admin/expenses/expense_category'); ?>
<?php init_tail(); ?>
<?php require 'modules/accounting/assets/js/bill/bill_js.php'; ?>
</body>
</html>
