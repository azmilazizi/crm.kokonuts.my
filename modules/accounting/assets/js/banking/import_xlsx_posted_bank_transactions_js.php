<script>
   var bankStatementRows = [];
   var createTransactionUrls = {
     bill: admin_url + 'purchase/purchase_invoice?dialog=1',
     journal_entry: admin_url + 'accounting/new_journal_entry?dialog=1'
   };
   var purchaseOrderData = {
     orderNumber: <?php echo json_encode($purchase_order_number ?? ''); ?>,
     vendors: <?php echo json_encode($purchase_vendors ?? []); ?>,
     items: <?php echo json_encode($purchase_items ?? []); ?>,
     orders: <?php echo json_encode($purchase_orders ?? []); ?>,
     bills: <?php echo json_encode($purchase_bills ?? []); ?>,
     expenseCategories: <?php echo json_encode($expense_categories ?? []); ?>,
     accounts: <?php echo json_encode($accounting_accounts ?? []); ?>
   };
   var journalEntryNextNumber = <?php echo json_encode($journal_entry_next_number ?? 1); ?>;

   (function($) {
    "use strict";

      appValidateForm($('#import_form'),{file_csv:{required:true,extension: "xlsx|xls|csv"},source:'required',status:'required', bank_account: 'required'});
      // function 

    $(document).on('change', '#bank-statement-select-all', function() {
      var isChecked = $(this).prop('checked');
      $('#bank-statement-table tbody .bank-statement-checkbox').prop('checked', isChecked);
      $(this).prop('indeterminate', false);
      updateSelectedRowCount();
    });

    $(document).on('change', '#bank-statement-table tbody .bank-statement-checkbox', function() {
      updateBankStatementSelectAll();
      updateSelectedRowCount();
    });

    $(document).on('click', '#transaction-type-apply', function() {
      applyTransactionFilter();
    });

    $(document).on('change', '#transaction-type-filter', function() {
      toggleBulkJournalButton();
    });

    $(document).on('click', '#transaction-type-reset', function() {
      resetTransactionFilter();
    });

    $(document).on('click', '#bank-statement-tabs a', function(event) {
      event.preventDefault();
      $('#bank-statement-tabs li').removeClass('active');
      $(this).parent('li').addClass('active');
      applyBankStatementTabFilter();
    });

    $(document).on('click', '#transaction-type-create-bulk', function() {
      openBulkJournalEntryModal();
    });

    $(document).on('click', '.bank-statement-create-action', function(event) {
      event.preventDefault();
      openCreateTransactionModal($(this));
    });

    $(document).on('click', '#create-transaction-refresh', function() {
      refreshCurrentStatementMatch();
    });

  })(jQuery);

function updateBankStatementSelectAll(){
  "use strict";

  var $checkboxes = $('#bank-statement-table tbody .bank-statement-checkbox');
  var $selectAll = $('#bank-statement-select-all');

  if(!$checkboxes.length){
    $selectAll.prop('checked', false).prop('indeterminate', false);
    return;
  }

  var checkedCount = $checkboxes.filter(':checked').length;
  var allChecked = checkedCount === $checkboxes.length;
  var noneChecked = checkedCount === 0;

  $selectAll
    .prop('checked', allChecked)
    .prop('indeterminate', !allChecked && !noneChecked);
}

function toggleBulkJournalButton(){
  "use strict";

  var selectedType = $('#transaction-type-filter').val();
  var selectedRows = getSelectedStatementRows().length;

  $('#transaction-type-create-bulk').prop('disabled', !(selectedType && selectedRows > 0));
}

function uploadfilecsv(){
  "use strict";

    if(($("#file_csv").val() != '') && ($("#file_csv").val().split('.').pop() == 'xlsx' || $("#file_csv").val().split('.').pop() == 'xls' || $("#file_csv").val().split('.').pop() == 'csv')){

    if($('select[name="bank_account"]').val() == ''){
      alert_float('warning', "<?php echo _l('please_select_a_bank_account') ?>");
      
      return false;
    }
    var formData = new FormData();
    formData.append("file_csv", $('#file_csv')[0].files[0]);
    if(<?php echo  acc_check_csrf_protection(); ?>){
        formData.append(csrfData.token_name, csrfData.hash);
    }

    formData.append("leads_import", $('input[name="leads_import"]').val());
    formData.append("bank_account", $('select[name="bank_account"]').val());

    //show box loading
    var html = '';
      html += '<div class="Box">';
      html += '<span>';
      html += '<span></span>';
      html += '</span>';
      html += '</div>';
      $('#box-loading').html(html);
      $('button[id="uploadfile"]').attr( "disabled", "disabled" );

    $.ajax({ 
      url: admin_url + 'accounting/import_file_xlsx_posted_bank_transactions', 
      method: 'post', 
      data: formData, 
      contentType: false, 
      processData: false
      
    }).done(function(response) {
      response = JSON.parse(response);

      //hide boxloading
      $('#box-loading').html('');
      $('button[id="uploadfile"]').removeAttr('disabled');

      $("#file_csv").val(null);
      $("#file_csv").change();
       $(".panel-body").find("#file_upload_response").html();

      if($(".panel-body").find("#file_upload_response").html() != ''){
        $(".panel-body").find("#file_upload_response").empty();
      };

      if(response.total_rows < 1){
        alert_float('warning', response.message);
      }

      $( "#file_upload_response" ).append( "<h4><?php echo _l('_Result') ?></h4><h5><?php echo _l('import_line_number') ?> :"+response.total_rows+" </h5>" );

      render_statement_table(response.rows || []);
    });
    return false;
    }else if($("#file_csv").val() != ''){
      alert_float('warning', "<?php echo _l('_please_select_a_file') ?>");
    }
}

function render_statement_table(rows){
  "use strict";

  bankStatementRows = rows.slice();

  var $tableBody = $('#bank-statement-table tbody');
  $tableBody.empty();

  $('#bank-statement-select-all').prop('checked', false).prop('indeterminate', false);

  if(!rows.length){
    updateBankStatementTabCounts([]);
    return;
  }

  rows.forEach(function(row, index){
    var statusIcon = row.matched
      ? '<span class="text-success d-flex align-items-center justify-content-center"><i class="fa fa-check"></i></span>'
      : '<span class="text-danger d-flex align-items-center justify-content-center"><i class="fa fa-exclamation-circle"></i></span>';

    var matchedText = row.matched
      ? ((row.matched_rel_type && row.matched_rel_id) ? (row.matched_rel_type + ' #' + row.matched_rel_id) : 'Matched')
      : 'false';
    var matchedBadge = row.matched_already ? '<span class="label label-warning mleft5">Already matched</span>' : '';

    var editBtn = '<button type="button" class="btn btn-default btn-icon" title="Edit"><i class="fa fa-edit"></i></button>';
    var deleteBtn = '<button type="button" class="btn btn-danger btn-icon" title="Delete"><i class="fa fa-trash"></i></button>';
    var matchedContent = '';

    if(row.matched){
      matchedContent = ''
        + '<div class="d-flex justify-content-between align-items-center text-left" style="justify-content: space-between;">'
        + '<span class="text-left">'+matchedText+matchedBadge+'</span>'
        + '<span>'+editBtn+' '+deleteBtn+'</span>'
        + '</div>';
    }else{
      matchedContent = ''
        + '<div class="d-flex justify-content-end">'
        + '<button class="btn btn-info bank-statement-create-action" type="button" data-index="'+index+'">Create</button>'
        + '</div>';
    }

    var description = row.description || '';

    var rowHtml = ''
      + '<tr data-index="'+index+'" data-description="'+htmlspecialchars(description)+'" data-matched="'+(row.matched ? '1' : '0')+'" data-date="'+(row.date || '')+'" data-spent="'+(row.spent || '')+'" data-received="'+(row.received || '')+'">'
      + '<td class="text-center align-middle"><input type="checkbox" class="bank-statement-checkbox" data-index="'+index+'"></td>'
      + '<td class="align-middle">'+(row.date || '')+'</td>'
      + '<td class="align-middle">'+description+'</td>'
      + '<td class="align-middle statement-amount" style="width: 10%;">'+(row.spent || '')+'</td>'
      + '<td class="align-middle statement-amount" style="width: 10%;">'+(row.received || '')+'</td>'
      + '<td class="align-middle text-left">'+matchedContent+'</td>'
      + '<td class="text-center align-middle" style="align-content: center;">'+statusIcon+'</td>'
      + '</tr>';

    $tableBody.append(rowHtml);
  });

  updateBankStatementSelectAll();
  updateBankStatementTabCounts(rows);
  applyBankStatementTabFilter();
  updateSelectedRowCount();
  toggleBulkJournalButton();
}

function applyTransactionFilter(){
  "use strict";

  var selectedType = $('#transaction-type-filter').val();
  var $rows = $('#bank-statement-table tbody tr');

  if(!$rows.length){
    return;
  }

  if(!selectedType){
    $rows.find('.bank-statement-checkbox').prop('checked', false);
    updateBankStatementSelectAll();
    updateSelectedRowCount();
    return;
  }

  $rows.each(function(){
    var $row = $(this);
    var description = ($row.data('description') || '').toString().trim();
    var isMatch = matchesTransactionType(description, selectedType);
    $row.find('.bank-statement-checkbox').prop('checked', isMatch);
  });

  updateBankStatementSelectAll();
  updateSelectedRowCount();
}

function resetTransactionFilter(){
  "use strict";

  $('#transaction-type-filter').val('');
  $('#transaction-type-filter').trigger('change');
  $('#bank-statement-table tbody .bank-statement-checkbox').prop('checked', false);
  updateBankStatementSelectAll();
  updateSelectedRowCount();
}

function updateBankStatementTabCounts(rows){
  "use strict";

  var total = rows.length;
  var matched = rows.filter(function(row){
    return !!row.matched;
  }).length;
  var notMatched = total - matched;

  $('#bank-statement-count-all').text(total);
  $('#bank-statement-count-matched').text(matched);
  $('#bank-statement-count-not-matched').text(notMatched);
  $('#bank-statement-rows-total').text(total);
  updateSelectedRowCount();
}

function applyBankStatementTabFilter(){
  "use strict";

  var activeFilter = $('#bank-statement-tabs li.active a').data('filter') || 'all';
  var $rows = $('#bank-statement-table tbody tr');

  if(!$rows.length){
    return;
  }

  $rows.each(function(){
    var $row = $(this);
    var isMatched = $row.data('matched') === 1 || $row.data('matched') === '1';
    var shouldShow = activeFilter === 'all'
      || (activeFilter === 'matched' && isMatched)
      || (activeFilter === 'not-matched' && !isMatched);

    $row.toggle(shouldShow);
  });
}

function matchesTransactionType(description, selectedType){
  "use strict";

  switch(selectedType){
    case 'duitnow_qr':
      return /^\d+\s+\d+Q$/.test(description) || description === 'DUITNOW QR-';
    case 'card_sales':
      return description === 'DR/CARD SALES M/N 37';
    case 'grabfood_settlement':
      return /^\d+\s+\d+\s+\d+\s+\d+$/.test(description);
    case 'foodpanda_settlement':
      return description.indexOf('NWYB Pay Adv') !== -1;
    case 'shopeefood_settlement':
      return description.indexOf('ShopeeFood') !== -1;
    default:
      return false;
  }
}

function htmlspecialchars(text) {
  "use strict";

  return text
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function openBulkJournalEntryModal(){
  "use strict";

  var selectedType = $('#transaction-type-filter').val();
  var selectedRows = getSelectedStatementRows();
  var bankAccount = $('select[name="bank_account"]').val();

  if(!bankAccount){
    alert_float('warning', "<?php echo _l('please_select_a_bank_account') ?>");
    return;
  }

  if(!selectedType){
    alert_float('warning', 'Please select a transaction type.');
    return;
  }

  if(!selectedRows.length){
    alert_float('warning', 'Please select at least one transaction row.');
    return;
  }

  var confirmation = confirm('Create bulk journal entries for ' + selectedRows.length + ' selected rows?');
  if(!confirmation){
    return;
  }

  submitBulkJournalEntry();
}

function getSelectedStatementRows(){
  "use strict";

  var rows = [];

  $('#bank-statement-table tbody .bank-statement-checkbox:checked').each(function(){
    var $row = $(this).closest('tr');
    rows.push({
      index: $row.data('index'),
      date: ($row.data('date') || '').toString(),
      spent: ($row.data('spent') || '').toString(),
      received: ($row.data('received') || '').toString()
    });
  });

  return rows;
}

function updateSelectedRowCount(){
  "use strict";

  var selectedCount = $('#bank-statement-table tbody .bank-statement-checkbox:checked').length;
  $('#bank-statement-rows-selected').text(selectedCount);
  toggleBulkJournalButton();
}

function submitBulkJournalEntry(){
  "use strict";

  var selectedType = $('#transaction-type-filter').val();
  var rows = getSelectedStatementRows();
  var bankAccount = $('select[name="bank_account"]').val();

  if(!selectedType){
    alert_float('warning', 'Please select a transaction type.');
    return;
  }

  if(!rows.length){
    alert_float('warning', 'Please select at least one transaction row.');
    return;
  }

  var payload = {
    transaction_type: selectedType,
    bank_account: bankAccount,
    rows: rows
  };

  if(<?php echo acc_check_csrf_protection(); ?>){
    payload[csrfData.token_name] = csrfData.hash;
  }

  $.ajax({
    url: admin_url + 'accounting/create_bulk_journal_entries_from_bank_transactions',
    method: 'post',
    data: payload
  }).done(function(response) {
    response = JSON.parse(response);

    if(response.success){
      alert_float('success', response.message || 'Bulk journal entries created.');
      $('#bank-statement-table tbody .bank-statement-checkbox:checked').prop('checked', false);
      updateBankStatementSelectAll();
      refreshStatementMatches(rows);
    }else{
      alert_float('warning', response.message || 'Unable to create bulk journal entries.');
    }
  }).fail(function() {
    alert_float('warning', 'Unable to create bulk journal entries.');
  });
}

function openCreateTransactionModal($trigger){
  "use strict";

  var $row = $trigger.closest('tr');
  var modal = $('#create-transaction-modal');

  if(!modal.length){
    return;
  }

  modal.find('.modal-title').text('Create Transaction');
  modal.data('rowIndex', $row.data('index'));
  modal.data('statementDate', $row.data('date') || '');
  modal.data('statementAmount', $row.data('spent') || $row.data('received') || '');
  modal.data('statementPaymentMode', 'Bank Transfer');
  updateCreateTransactionHeader(modal);
  modal.find('#create-transaction-type').val('purchase_order');
  loadCreateTransactionForm('purchase_order');

  modal.off('change.createTransactionType').on('change.createTransactionType', '#create-transaction-type', function(){
    var selectedType = $(this).val();
    loadCreateTransactionForm(selectedType);
  });

  modal.modal('show');
}

function loadCreateTransactionForm(selectedType){
  "use strict";

  var modal = $('#create-transaction-modal');
  var $container = $('#create-transaction-form-container');

  toggleCreateTransactionFooter(selectedType);

  if(selectedType === 'purchase_order'){
    $container.html(buildPurchaseOrderForm());
    bindPurchaseOrderForm();
    applyStatementDateToForm(modal.data('statementDate') || '', selectedType);
    updateTransactionAmountNotice(modal.data('statementAmount') || '');
    updatePaymentSection(modal);
    return;
  }

  if(selectedType === 'expense'){
    $container.html(buildExpenseForm());
    applyStatementDateToForm(modal.data('statementDate') || '', selectedType);
    updateTransactionAmountNotice(modal.data('statementAmount') || '');
    updatePaymentSection(modal);
    return;
  }

  if(selectedType === 'bill'){
    $container.html(buildBillForm());
    bindBillForm();
    init_datepicker();
    applyStatementDateToForm(modal.data('statementDate') || '', selectedType);
    updateBillPaymentSection(modal);
    return;
  }

  if(selectedType === 'journal_entry'){
    $container.html(buildJournalEntryForm(modal.data('statementDate') || ''));
    bindJournalEntryForm();
    return;
  }

  $container.html('<div class="text-center m-t-15"><i class="fa fa-spinner fa-spin"></i></div>');

  var url = createTransactionUrls[selectedType];
  if(!url){
    $container.html('');
    return;
  }

  $.get(url, function(response){
    var $response = $('<div>').html(response);
    var $scripts = $response.find('script');
    $scripts.remove();
    $container.html($response.html());
    $scripts.each(function(){
      $.globalEval(this.text || this.textContent || this.innerHTML || '');
    });
    applyStatementDateToForm(modal.data('statementDate') || '', selectedType);
    updatePaymentSection(modal);
  });
}

function updatePaymentSection(modal){
  "use strict";

  var $paymentAmount = $('#create-transaction-form-container').find('input[name="payment_amount"]');
  if($paymentAmount.length){
    $paymentAmount.val(modal.data('statementAmount') || '');
  }

  var $expenseAmount = $('#create-transaction-form-container').find('input[name="amount"]');
  if($expenseAmount.length){
    $expenseAmount.val(modal.data('statementAmount') || '');
  }

  var $paymentDate = $('#create-transaction-form-container').find('input[name="payment_date"]');
  if($paymentDate.length){
    $paymentDate.val(modal.data('statementDate') || '');
  }

  var $paymentMode = $('#create-transaction-form-container').find('input[name="payment_mode"]');
  if($paymentMode.length){
    $paymentMode.val(modal.data('statementPaymentMode') || '');
  }
}

function updateBillPaymentSection(modal){
  "use strict";

  var $container = $('#create-transaction-form-container');
  var statementAmount = modal.data('statementAmount') || '';

  var $paymentAmount = $container.find('input[name="bill_payment_amount"]');
  if($paymentAmount.length){
    $paymentAmount.val(statementAmount);
  }

  var $paymentDate = $container.find('input[name="bill_payment_date"]');
  if($paymentDate.length){
    $paymentDate.val(statementAmount);
  }
}

function updateCreateTransactionHeader(modal){
  "use strict";

  var statementDate = modal.data('statementDate') || '';
  var statementAmount = modal.data('statementAmount') || '';
  var $meta = modal.find('#create-transaction-statement-meta');

  if(!$meta.length){
    return;
  }

  if(!statementDate && !statementAmount){
    $meta.hide();
    return;
  }

  $meta.find('.statement-date').text(statementDate ? 'Date: ' + statementDate : '');
  $meta.find('.statement-amount').text(statementAmount ? 'Transaction Amount: ' + statementAmount : '');
  $meta.show();
}

function applyStatementDateToForm(statementDate, selectedType){
  "use strict";

  if(!statementDate){
    return;
  }

  if(selectedType === 'purchase_order'){
    var $orderDate = $('#create-transaction-form-container').find('input[name="order_date"]');
    if($orderDate.length){
      $orderDate.val(statementDate).trigger('change');
    }
    updatePurchaseOrderNumber(statementDate);
  }

  if(selectedType === 'expense'){
    var $expenseDate = $('#create-transaction-form-container').find('input[name="date"]');
    if($expenseDate.length){
      $expenseDate.val(statementDate).trigger('change');
    }
  }

  if(selectedType === 'bill'){
    var $billDate = $('#create-transaction-form-container').find('input[name="bill_date"]');
    if($billDate.length){
      $billDate.val(statementDate).trigger('change');
    }
  }
}

function toggleCreateTransactionFooter(selectedType){
  "use strict";

  var $modal = $('#create-transaction-modal');
  var $defaultFooter = $modal.find('.create-transaction-footer-default');
  var $orderFooter = $modal.find('.create-transaction-footer-order');

  if(selectedType === 'purchase_order' || selectedType === 'expense'){
    $defaultFooter.hide();
    $orderFooter.show();
  }else{
    $defaultFooter.show();
    $orderFooter.hide();
  }
}

function buildPurchaseOrderForm(){
  "use strict";

  var orderOptions = '<option value="">Select a purchase order</option>';
  purchaseOrderData.orders.forEach(function(order){
    var label = (order.pur_order_number || '') + '_' + (order.pur_order_name || '');
    orderOptions += '<option value="' + order.id + '" data-vendor="' + htmlspecialchars(order.company || '') + '" data-order-date="' + (order.order_date || '') + '" data-subtotal="' + (order.subtotal || '') + '" data-total="' + (order.total || '') + '">' + htmlspecialchars(label) + '</option>';
  });

  var vendorOptions = '<option value="">Select an option</option>';
  purchaseOrderData.vendors.forEach(function(vendor){
    vendorOptions += '<option value="' + vendor.userid + '">' + (vendor.company || '') + '</option>';
  });

  var itemOptions = '<option value="">Select an item</option>';
  purchaseOrderData.items.forEach(function(item){
    var label = item.sku_name || '';
    itemOptions += '<option value="' + item.id + '" data-description="' + htmlspecialchars(item.long_description || '') + '" data-rate="' + (item.rate || 0) + '" data-label="' + htmlspecialchars(label) + '">' + label + '</option>';
  });

  return ''
    + '<div class="purchase-order-form">'
    + '  <div class="form-group">'
    + '    <label class="checkbox-inline"><input type="checkbox" id="po-choose-from-order"> Choose from Purchase Order</label>'
    + '  </div>'
    + '  <div class="purchase-order-manual">'
    + '  <div class="form-group">'
    + '    <label>Vendor name</label>'
    + '    <select class="form-control" name="vendor">' + vendorOptions + '</select>'
    + '  </div>'
    + '  <div class="form-group">'
    + '    <label>Order name</label>'
    + '    <input type="text" class="form-control" name="order_name">'
    + '  </div>'
    + '  <div class="form-group">'
    + '    <label>Order number</label>'
    + '    <input type="text" class="form-control" name="order_number" readonly value="' + htmlspecialchars(purchaseOrderData.orderNumber || '') + '">'
    + '  </div>'
    + '  <div class="form-group">'
    + '    <label>Order date</label>'
    + '    <input type="text" class="form-control" name="order_date" readonly>'
    + '  </div>'
    + '  <div class="form-group">'
    + '    <label>Items</label>'
    + '    <select class="form-control" id="po-item-selector">' + itemOptions + '</select>'
    + '  </div>'
    + '  <div class="purchase-order-details-panel">'
    + '    <div class="row">'
    + '      <div class="col-md-6">'
    + '        <div class="form-group">'
    + '          <label>Item name</label>'
    + '          <input type="text" class="form-control" id="po-item-name" placeholder="Select an item from the dropdown above">'
    + '        </div>'
    + '      </div>'
    + '      <div class="col-md-6">'
    + '        <div class="form-group">'
    + '          <label>Description (optional)</label>'
    + '          <textarea class="form-control" id="po-item-description" rows="2"></textarea>'
    + '        </div>'
    + '      </div>'
    + '    </div>'
    + '    <div class="row">'
    + '      <div class="col-md-2">'
    + '        <div class="form-group">'
    + '          <label>Quantity</label>'
    + '          <input type="number" class="form-control" id="po-item-qty" min="0" step="1" value="1">'
    + '        </div>'
    + '      </div>'
    + '      <div class="col-md-2">'
    + '        <div class="form-group">'
    + '          <label>Subtotal (RM)</label>'
    + '          <input type="number" class="form-control" id="po-item-subtotal" min="0" step="0.01" value="0">'
    + '        </div>'
    + '      </div>'
    + '      <div class="col-md-2">'
    + '        <div class="form-group">'
    + '          <label>Discount (RM)</label>'
    + '          <input type="number" class="form-control" id="po-item-discount" min="0" step="0.01" value="0">'
    + '        </div>'
    + '      </div>'
    + '      <div class="col-md-2">'
    + '        <div class="form-group">'
    + '          <label>Unit price (RM)</label>'
    + '          <input type="number" class="form-control" id="po-item-unit-price" readonly>'
    + '        </div>'
    + '      </div>'
    + '      <div class="col-md-2">'
    + '        <div class="form-group">'
    + '          <label>Total (RM)</label>'
    + '          <input type="number" class="form-control" id="po-item-total" readonly>'
    + '        </div>'
    + '      </div>'
    + '      <div class="col-md-2 text-right">'
    + '        <label class="block">&nbsp;</label>'
    + '        <button type="button" class="btn btn-success purchase-order-add-item-btn" id="po-add-item"><i class="fa fa-check"></i></button>'
    + '      </div>'
    + '    </div>'
    + '  </div>'
    + '  <table class="table table-bordered" id="po-items-list">'
    + '    <thead>'
    + '      <tr>'
    + '        <th>Item name</th>'
    + '        <th>Description</th>'
    + '        <th class="text-right">Qty</th>'
    + '        <th class="text-right">Unit price</th>'
    + '        <th class="text-right">Discount</th>'
    + '        <th class="text-right">Total</th>'
    + '        <th class="text-right">Action</th>'
    + '      </tr>'
    + '    </thead>'
    + '    <tbody></tbody>'
    + '  </table>'
    + '  <div class="purchase-order-totals m-t-20">'
    + '    <div class="total-row">'
    + '      <span>Subtotal</span>'
    + '      <span id="po-subtotal-display">0.00</span>'
    + '    </div>'
    + '    <div class="total-row">'
    + '      <span>Discount</span>'
    + '      <span style="display: flex; align-items: center; gap: 8px;">'
    + '        <input type="number" class="form-control input-sm" id="po-discount" value="0" min="0" step="0.01" style="max-width: 140px;">'
    + '        <select class="form-control input-sm" id="po-discount-type" style="max-width: 90px;">'
    + '          <option value="amount">Amount</option>'
    + '          <option value="percent">%</option>'
    + '        </select>'
    + '        <span class="text-muted" id="po-discount-value-display">0.00</span>'
    + '      </span>'
    + '    </div>'
    + '    <div class="total-row">'
    + '      <span>Total Discount</span>'
    + '      <span id="po-total-discount-display">0.00</span>'
    + '    </div>'
    + '    <div class="total-row">'
    + '      <span>Shipping Fee</span>'
    + '      <span><input type="number" class="form-control input-sm" id="po-shipping-fee" value="0" min="0" step="0.01" style="max-width: 140px;"></span>'
    + '    </div>'
    + '    <div class="total-row">'
    + '      <strong>Grand Total</strong>'
    + '      <strong id="po-grand-total-display">0.00</strong>'
    + '    </div>'
    + '  </div>'
    + '  </div>'
    + '  <div class="purchase-order-existing" style="display: none;">'
    + '    <div class="form-group">'
    + '      <label>Purchase Order</label>'
    + '      <select class="form-control" id="po-existing-selector">' + orderOptions + '</select>'
    + '    </div>'
    + '    <div class="purchase-order-details-panel">'
    + '      <div class="row">'
    + '        <div class="col-md-3">'
    + '          <div class="form-group">'
    + '            <label>Vendor</label>'
    + '            <p class="form-control-static" id="po-existing-vendor"></p>'
    + '          </div>'
    + '        </div>'
    + '        <div class="col-md-3">'
    + '          <div class="form-group">'
    + '            <label>Order date</label>'
    + '            <p class="form-control-static" id="po-existing-order-date"></p>'
    + '          </div>'
    + '        </div>'
    + '        <div class="col-md-3">'
    + '          <div class="form-group">'
    + '            <label>Subtotal</label>'
    + '            <p class="form-control-static" id="po-existing-subtotal"></p>'
    + '          </div>'
    + '        </div>'
    + '        <div class="col-md-3">'
    + '          <div class="form-group">'
    + '            <label>Total</label>'
    + '            <p class="form-control-static" id="po-existing-total"></p>'
    + '          </div>'
    + '        </div>'
    + '      </div>'
    + '    </div>'
    + '  </div>'
    + '  <div class="panel panel-default payment-section-card">'
    + '    <div class="panel-heading"><strong>Payment</strong></div>'
    + '    <div class="panel-body">'
    + '    <div class="row">'
    + '      <div class="col-md-4">'
    + '        <div class="form-group">'
    + '          <label>Amount</label>'
    + '          <input type="text" class="form-control" name="payment_amount" readonly>'
    + '        </div>'
    + '      </div>'
    + '      <div class="col-md-4">'
    + '        <div class="form-group">'
    + '          <label>Payment Mode</label>'
    + '          <input type="text" class="form-control" name="payment_mode" readonly>'
    + '        </div>'
    + '      </div>'
    + '      <div class="col-md-4">'
    + '        <div class="form-group">'
    + '          <label>Date</label>'
    + '          <input type="text" class="form-control" name="payment_date" readonly>'
    + '        </div>'
    + '      </div>'
    + '    </div>'
    + '    </div>'
    + '  </div>'
    + '</div>';
}

function buildExpenseForm(){
  "use strict";

  var vendorOptions = '<option value="">Select an option</option>';
  purchaseOrderData.vendors.forEach(function(vendor){
    vendorOptions += '<option value="' + vendor.userid + '">' + (vendor.company || '') + '</option>';
  });

  var expenseCategoryOptions = '<option value="">Select an option</option>';
  purchaseOrderData.expenseCategories.forEach(function(category){
    expenseCategoryOptions += '<option value="' + category.id + '">' + (category.name || '') + '</option>';
  });

  return ''
    + '<div class="expense-form">'
    + '  <div class="form-group">'
    + '    <label>Vendor</label>'
    + '    <select class="form-control" name="expense_vendor">' + vendorOptions + '</select>'
    + '  </div>'
    + '  <div class="form-group">'
    + '    <label>Expense name</label>'
    + '    <input type="text" class="form-control" name="expense_name">'
    + '  </div>'
    + '  <div class="form-group">'
    + '    <label>Expense Category</label>'
    + '    <select class="form-control" name="expense_category">' + expenseCategoryOptions + '</select>'
    + '  </div>'
    + '  <div class="form-group">'
    + '    <label>Expense Date</label>'
    + '    <input type="text" class="form-control" name="date" readonly>'
    + '  </div>'
    + '  <div class="form-group">'
    + '    <label>Amount</label>'
    + '    <input type="text" class="form-control" name="amount" readonly>'
    + '  </div>'
    + '  <div class="form-group">'
    + '    <label>Payment Mode</label>'
    + '    <input type="text" class="form-control" name="payment_mode" readonly>'
    + '  </div>'
    + '  <div class="form-group">'
    + '    <label>Description (optional)</label>'
    + '    <textarea class="form-control" name="description" rows="3"></textarea>'
    + '  </div>'
    + '</div>';
}

function buildBillForm(){
  "use strict";

  var billOptions = '<option value="">Select a bill</option>';
  purchaseOrderData.bills.forEach(function(bill){
    var billNumber = bill.invoice_number || ('Bill #' + bill.id);
    var vendorName = bill.company || '';
    var label = billNumber + (vendorName ? ' - ' + vendorName : '');
    billOptions += '<option value="' + bill.id + '">' + htmlspecialchars(label) + '</option>';
  });

  var vendorOptions = '<option value="">Select an option</option>';
  purchaseOrderData.vendors.forEach(function(vendor){
    vendorOptions += '<option value="' + vendor.userid + '">' + (vendor.company || '') + '</option>';
  });

  var accountOptions = '<option value="">Select an account</option>';
  purchaseOrderData.accounts.forEach(function(account){
    accountOptions += '<option value="' + account.id + '">' + (account.name || '') + '</option>';
  });

  return ''
    + '<div class="bill-form">'
    + '  <div class="form-group">'
    + '    <label class="checkbox-inline"><input type="checkbox" id="bill-choose-from-bills"> Choose from Bills</label>'
    + '  </div>'
    + '  <div class="bill-existing" style="display: none;">'
    + '    <div class="form-group">'
    + '      <label>Bill</label>'
    + '      <select class="form-control" id="bill-existing-selector">' + billOptions + '</select>'
    + '    </div>'
    + '  </div>'
    + '  <div class="bill-manual">'
    + '    <div class="form-group">'
    + '      <label>Vendor</label>'
    + '      <select class="form-control" name="bill_vendor">' + vendorOptions + '</select>'
    + '    </div>'
    + '    <div class="form-group">'
    + '      <label>Expense Name</label>'
    + '      <input type="text" class="form-control" name="bill_expense_name">'
    + '    </div>'
    + '    <div class="row">'
    + '      <div class="col-md-6">'
    + '        <div class="form-group">'
    + '          <label>Bill Date</label>'
    + '          <input type="text" class="form-control datepicker" name="bill_date">'
    + '        </div>'
    + '      </div>'
    + '      <div class="col-md-6">'
    + '        <div class="form-group">'
    + '          <label>Due Date</label>'
    + '          <input type="text" class="form-control datepicker" name="bill_due_date">'
    + '        </div>'
    + '      </div>'
    + '    </div>'
    + '    <div class="panel panel-default">'
    + '      <div class="panel-heading"><strong>Expenses</strong></div>'
    + '      <div class="panel-body">'
    + '        <div class="row">'
    + '          <div class="col-md-8">'
    + '            <div class="form-group">'
    + '              <label>Debit Account</label>'
    + '              <select class="form-control" name="bill_debit_account">' + accountOptions + '</select>'
    + '            </div>'
    + '          </div>'
    + '          <div class="col-md-4">'
    + '            <div class="form-group">'
    + '              <label>Amount</label>'
    + '              <input type="text" class="form-control" name="bill_debit_amount">'
    + '            </div>'
    + '          </div>'
    + '        </div>'
    + '        <div class="row">'
    + '          <div class="col-md-8">'
    + '            <div class="form-group">'
    + '              <label>Credit Account</label>'
    + '              <select class="form-control" name="bill_credit_account">' + accountOptions + '</select>'
    + '            </div>'
    + '          </div>'
    + '          <div class="col-md-4">'
    + '            <div class="form-group">'
    + '              <label>Amount</label>'
    + '              <input type="text" class="form-control" name="bill_credit_amount">'
    + '            </div>'
    + '          </div>'
    + '        </div>'
    + '      </div>'
    + '    </div>'
    + '  </div>'
    + '  <div class="panel panel-default payment-section-card">'
    + '    <div class="panel-heading"><strong>Payment</strong></div>'
    + '    <div class="panel-body">'
    + '      <div class="row">'
    + '        <div class="col-md-3">'
    + '          <div class="form-group">'
    + '            <label>Amount</label>'
    + '            <input type="text" class="form-control" name="bill_payment_amount" readonly>'
    + '          </div>'
    + '        </div>'
    + '        <div class="col-md-3">'
    + '          <div class="form-group">'
    + '            <label>Payment Date</label>'
    + '            <input type="text" class="form-control" name="bill_payment_date" readonly>'
    + '          </div>'
    + '        </div>'
    + '        <div class="col-md-3">'
    + '          <div class="form-group">'
    + '            <label>Payment Account</label>'
    + '            <select class="form-control" name="bill_payment_account">' + accountOptions + '</select>'
    + '          </div>'
    + '        </div>'
    + '        <div class="col-md-3">'
    + '          <div class="form-group">'
    + '            <label>Deposit To</label>'
    + '            <select class="form-control" name="bill_deposit_to">' + accountOptions + '</select>'
    + '          </div>'
    + '        </div>'
    + '      </div>'
    + '    </div>'
    + '  </div>'
    + '</div>';
}

function buildJournalEntryForm(statementDate){
  "use strict";

  var entryId = formatJournalEntryNumber(statementDate);
  var accountOptions = buildJournalEntryAccountOptions();

  return ''
    + '<div class="journal-entry-form">'
    + '  <div class="row">'
    + '    <div class="col-md-6">'
    + '      <div class="form-group">'
    + '        <label>Entry ID</label>'
    + '        <input type="text" class="form-control" name="number" readonly value="' + htmlspecialchars(entryId) + '">'
    + '      </div>'
    + '    </div>'
    + '    <div class="col-md-6">'
    + '      <div class="form-group">'
    + '        <label>Journal Date</label>'
    + '        <input type="text" class="form-control" name="journal_date" readonly value="' + htmlspecialchars(statementDate || '') + '">'
    + '      </div>'
    + '    </div>'
    + '  </div>'
    + '  <div class="table-responsive m-t-15">'
    + '    <table class="table table-bordered" id="journal-entry-lines">'
    + '      <thead>'
    + '        <tr>'
    + '          <th width="30%">Account</th>'
    + '          <th width="15%">Debit</th>'
    + '          <th width="15%">Credit</th>'
    + '          <th width="30%">Description</th>'
    + '          <th width="10%"></th>'
    + '        </tr>'
    + '      </thead>'
    + '      <tbody>'
    + buildJournalEntryLineRow(0, accountOptions)
    + '      </tbody>'
    + '    </table>'
    + '  </div>'
    + '  <div class="text-right">'
    + '    <button type="button" class="btn btn-success" id="journal-entry-add-line">'
    + '      <i class="fa fa-plus"></i> Add line'
    + '    </button>'
    + '  </div>'
    + '</div>';
}

function buildJournalEntryAccountOptions(){
  "use strict";

  var options = '<option value="">Select an account</option>';
  purchaseOrderData.accounts.forEach(function(account){
    var label = account.name || '';
    if(account.account_type_name){
      label += ' (' + account.account_type_name + ')';
    }
    options += '<option value="' + account.id + '">' + htmlspecialchars(label) + '</option>';
  });

  return options;
}

function buildJournalEntryLineRow(index, accountOptions){
  "use strict";

  return ''
    + '<tr data-index="' + index + '">'
    + '  <td>'
    + '    <select class="form-control" name="account[' + index + ']">' + accountOptions + '</select>'
    + '  </td>'
    + '  <td>'
    + '    <input type="number" class="form-control" name="debit_amount[' + index + ']" step="0.01" min="0">'
    + '  </td>'
    + '  <td>'
    + '    <input type="number" class="form-control" name="credit_amount[' + index + ']" step="0.01" min="0">'
    + '  </td>'
    + '  <td>'
    + '    <input type="text" class="form-control" name="description_detail[' + index + ']">'
    + '  </td>'
    + '  <td class="text-center">'
    + '    <button type="button" class="btn btn-danger journal-entry-remove-line">'
    + '      <i class="fa fa-minus"></i>'
    + '    </button>'
    + '  </td>'
    + '</tr>';
}

function bindJournalEntryForm(){
  "use strict";

  var $container = $('#create-transaction-form-container');

  $container.off('click.journalEntry');
  $container.on('click.journalEntry', '#journal-entry-add-line', function(){
    var $tbody = $container.find('#journal-entry-lines tbody');
    var nextIndex = getNextJournalEntryIndex($tbody);
    $tbody.append(buildJournalEntryLineRow(nextIndex, buildJournalEntryAccountOptions()));
    updateJournalEntryRemoveState($container);
  });

  $container.on('click.journalEntry', '.journal-entry-remove-line', function(){
    $(this).closest('tr').remove();
    updateJournalEntryRemoveState($container);
  });

  updateJournalEntryRemoveState($container);
}

function getNextJournalEntryIndex($tbody){
  "use strict";

  var maxIndex = -1;
  $tbody.find('tr').each(function(){
    var index = parseInt($(this).data('index'), 10);
    if(!isNaN(index)){
      maxIndex = Math.max(maxIndex, index);
    }
  });

  return maxIndex + 1;
}

function updateJournalEntryRemoveState($container){
  "use strict";

  var $rows = $container.find('#journal-entry-lines tbody tr');
  var disableRemove = $rows.length <= 1;
  $rows.find('.journal-entry-remove-line').prop('disabled', disableRemove);
}

function bindPurchaseOrderForm(){
  "use strict";

  var $container = $('#create-transaction-form-container');

  $container.off('change.purchaseOrder');
  $container.on('change.purchaseOrder', '#po-choose-from-order', function(){
    var isChecked = $(this).prop('checked');
    $container.find('.purchase-order-manual').toggle(!isChecked);
    $container.find('.purchase-order-existing').toggle(isChecked);
  });

  $container.on('change.purchaseOrder', '#po-existing-selector', function(){
    var $selected = $(this).find('option:selected');
    $container.find('#po-existing-vendor').text($selected.data('vendor') || '');
    $container.find('#po-existing-order-date').text($selected.data('order-date') || '');
    $container.find('#po-existing-subtotal').text($selected.data('subtotal') || '');
    $container.find('#po-existing-total').text($selected.data('total') || '');
  });

  $container.on('change.purchaseOrder', '#po-item-selector', function(){
    var $selected = $(this).find('option:selected');
    var label = $selected.data('label') || '';
    var description = $selected.data('description') || '';
    var rate = parseFloat($selected.data('rate') || 0);
    var qty = parseFloat($container.find('#po-item-qty').val()) || 0;

    $container.find('#po-item-name').val(label);
    $container.find('#po-item-description').val(description);
    $container.find('#po-item-subtotal').val((rate * qty).toFixed(2));
    updatePurchaseOrderItemTotals();
  });

  $container.on('input.purchaseOrder', '#po-item-qty, #po-item-discount', function(){
    updatePurchaseOrderItemTotals();
  });

  $container.on('input.purchaseOrder', '#po-item-subtotal', function(){
    updatePurchaseOrderItemTotals();
  });

  $container.on('input.purchaseOrder change.purchaseOrder', '#po-discount, #po-shipping-fee, #po-discount-type', function(){
    updatePurchaseOrderTotals();
  });

  $container.on('click.purchaseOrder', '#po-add-item', function(){
    var itemName = ($container.find('#po-item-name').val() || '').trim();
    var description = ($container.find('#po-item-description').val() || '').trim();
    var qty = parseFloat($container.find('#po-item-qty').val()) || 0;
    var subtotal = parseFloat($container.find('#po-item-subtotal').val()) || 0;
    var discount = parseFloat($container.find('#po-item-discount').val()) || 0;
    var total = Math.max(subtotal - discount, 0);
    var unitPrice = qty ? (total / qty) : 0;

    if(!itemName){
      alert_float('warning', 'Please select an item.');
      return;
    }

    var rowHtml = ''
      + '<tr>'
      + '<td>' + htmlspecialchars(itemName) + '</td>'
      + '<td>' + htmlspecialchars(description) + '</td>'
      + '<td class="text-right">' + qty.toFixed(2) + '</td>'
      + '<td class="text-right">' + unitPrice.toFixed(2) + '</td>'
      + '<td class="text-right">' + discount.toFixed(2) + '</td>'
      + '<td class="text-right" data-item-subtotal="' + subtotal.toFixed(2) + '" data-item-total="' + total.toFixed(2) + '" data-item-discount="' + discount.toFixed(2) + '">' + total.toFixed(2) + '</td>'
      + '<td class="text-right"><button type="button" class="btn btn-default btn-xs po-remove-item"><i class="fa fa-times"></i></button></td>'
      + '</tr>';

    $container.find('#po-items-list tbody').append(rowHtml);
    updatePurchaseOrderTotals();
  });

  $container.on('click.purchaseOrder', '.po-remove-item', function(){
    $(this).closest('tr').remove();
    updatePurchaseOrderTotals();
  });

  updatePurchaseOrderItemTotals();
  updatePurchaseOrderTotals();
}

function bindBillForm(){
  "use strict";

  var $container = $('#create-transaction-form-container');

  $container.off('change.billForm');
  $container.on('change.billForm', '#bill-choose-from-bills', function(){
    var isChecked = $(this).prop('checked');
    $container.find('.bill-manual').toggle(!isChecked);
    $container.find('.bill-existing').toggle(isChecked);
  });
}

function updatePurchaseOrderItemTotals(){
  "use strict";

  var $container = $('#create-transaction-form-container');
  var qty = parseFloat($container.find('#po-item-qty').val()) || 0;
  var subtotal = parseFloat($container.find('#po-item-subtotal').val()) || 0;
  var discount = parseFloat($container.find('#po-item-discount').val()) || 0;
  var total = Math.max(subtotal - discount, 0);
  var unitPrice = qty ? (total / qty) : 0;

  $container.find('#po-item-total').val(total.toFixed(2));
  $container.find('#po-item-unit-price').val(unitPrice.toFixed(2));
}

function updatePurchaseOrderTotals(){
  "use strict";

  var $container = $('#create-transaction-form-container');
  var subtotal = 0;
  var totalDiscount = 0;

  $container.find('#po-items-list tbody tr').each(function(){
    var $row = $(this);
    subtotal += parseFloat($row.find('[data-item-subtotal]').data('item-subtotal')) || 0;
    totalDiscount += parseFloat($row.find('[data-item-discount]').data('item-discount')) || 0;
  });

  var extraDiscountInput = parseFloat($container.find('#po-discount').val()) || 0;
  var discountType = $container.find('#po-discount-type').val();
  var extraDiscount = discountType === 'percent'
    ? (subtotal * (extraDiscountInput / 100))
    : extraDiscountInput;
  var shippingFee = parseFloat($container.find('#po-shipping-fee').val()) || 0;
  var grandTotal = Math.max(subtotal - (totalDiscount + extraDiscount) + shippingFee, 0);

  $container.find('#po-subtotal-display').text(subtotal.toFixed(2));
  $container.find('#po-discount-value-display').text(extraDiscount.toFixed(2));
  $container.find('#po-total-discount-display').text((totalDiscount + extraDiscount).toFixed(2));
  $container.find('#po-grand-total-display').text(grandTotal.toFixed(2));
}

function formatJournalEntryNumber(statementDate){
  "use strict";

  var dateSuffix = formatJournalEntryDatePart(statementDate);
  var paddedNumber = String(journalEntryNextNumber || 0).padStart(5, '0');
  return '#JE-' + paddedNumber + (dateSuffix ? '-' + dateSuffix : '');
}

function formatJournalEntryDatePart(statementDate){
  "use strict";

  if(!statementDate){
    return '';
  }

  var cleanDate = statementDate.toString().trim();

  if(cleanDate.indexOf('-') !== -1){
    var dashParts = cleanDate.split('-');
    if(dashParts.length === 3){
      if(dashParts[0].length === 4){
        return dashParts[2].padStart(2, '0') + dashParts[1].padStart(2, '0') + dashParts[0];
      }
      return dashParts[0].padStart(2, '0') + dashParts[1].padStart(2, '0') + dashParts[2];
    }
  }

  if(cleanDate.indexOf('/') !== -1){
    var slashParts = cleanDate.split('/');
    if(slashParts.length === 3){
      if(slashParts[2].length === 4){
        return slashParts[0].padStart(2, '0') + slashParts[1].padStart(2, '0') + slashParts[2];
      }
      if(slashParts[0].length === 4){
        return slashParts[2].padStart(2, '0') + slashParts[1].padStart(2, '0') + slashParts[0];
      }
    }
  }

  return cleanDate.replace(/\D/g, '');
}

function formatStatementDateForOrder(statementDate){
  if(!statementDate){
    return '';
  }

  var cleanDate = statementDate.toString().trim();
  if(cleanDate.indexOf('-') !== -1){
    var parts = cleanDate.split('-');
    if(parts.length === 3){
      return parts[2] + parts[1] + parts[0];
    }
  }

  if(cleanDate.indexOf('/') !== -1){
    var slashParts = cleanDate.split('/');
    if(slashParts.length === 3){
      return slashParts[0].padStart(2, '0') + slashParts[1].padStart(2, '0') + slashParts[2];
    }
  }

  return cleanDate.replace(/\D/g, '');
}

function updatePurchaseOrderNumber(statementDate){
  "use strict";

  var $container = $('#create-transaction-form-container');
  var $orderNumber = $container.find('input[name="order_number"]');
  if(!$orderNumber.length){
    return;
  }
  var dateSuffix = formatStatementDateForOrder(statementDate);
  var baseNumber = purchaseOrderData.orderNumber || '';
  $orderNumber.val(baseNumber + (dateSuffix ? '-' + dateSuffix : ''));
}

function updateTransactionAmountNotice(statementAmount){
  "use strict";

  var $notice = $('#create-transaction-form-container').find('#po-amount-notice');
  if(!$notice.length){
    return;
  }
  var amountText = statementAmount ? ' Transaction Row amount: ' + statementAmount : '';
  $notice.text('Check if the Grand Total amount is tally with the Transaction Row amount.' + amountText);
}

function refreshCurrentStatementMatch(){
  "use strict";

  var modal = $('#create-transaction-modal');
  var rowIndex = modal.data('rowIndex');

  if(rowIndex === undefined || rowIndex === null){
    return;
  }

  var row = bankStatementRows[rowIndex];
  if(!row){
    return;
  }

  refreshStatementMatches([{
    index: rowIndex,
    date: row.date || '',
    spent: row.spent || '',
    received: row.received || '',
    bank_account: $('select[name="bank_account"]').val() || ''
  }]);
}

function refreshStatementMatches(rows){
  "use strict";

  if(!rows.length){
    return;
  }

  var bankAccount = $('select[name="bank_account"]').val() || '';
  rows = rows.map(function(row){
    if(!row.bank_account){
      row.bank_account = bankAccount;
    }
    return row;
  });

  var payload = {
    rows: rows
  };

  if(<?php echo acc_check_csrf_protection(); ?>){
    payload[csrfData.token_name] = csrfData.hash;
  }

  $.ajax({
    url: admin_url + 'accounting/refresh_imported_bank_transaction_matches',
    method: 'post',
    data: payload
  }).done(function(response) {
    response = JSON.parse(response);

    if(!response.success || !response.rows){
      return;
    }

    response.rows.forEach(function(updated){
      if(updated.index === undefined || !bankStatementRows[updated.index]){
        return;
      }

      bankStatementRows[updated.index].matched = updated.matched;
      bankStatementRows[updated.index].matched_already = updated.matched_already;
      bankStatementRows[updated.index].matched_rel_type = updated.matched_rel_type || '';
      bankStatementRows[updated.index].matched_rel_id = updated.matched_rel_id || '';
    });

    render_statement_table(bankStatementRows);
  });
}
</script>
