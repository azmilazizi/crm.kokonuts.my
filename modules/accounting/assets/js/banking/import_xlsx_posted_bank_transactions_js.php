<script>
   var bankStatementRows = [];
   var createTransactionUrls = {
     bill: admin_url + 'purchase/purchase_invoice?dialog=1',
     journal_entry: admin_url + 'accounting/new_journal_entry?dialog=1'
   };
   var purchaseOrderData = {
     orderNumber: <?php echo json_encode($purchase_order_number ?? ''); ?>,
     orderNumberNext: <?php echo json_encode($purchase_order_next_number ?? 0); ?>,
     vendors: <?php echo json_encode($purchase_vendors ?? []); ?>,
     items: <?php echo json_encode($purchase_items ?? []); ?>,
     orders: <?php echo json_encode($purchase_orders ?? []); ?>,
     bills: <?php echo json_encode($purchase_bills ?? []); ?>,
     expenseCategories: <?php echo json_encode($expense_categories ?? []); ?>,
     accounts: <?php echo json_encode($accounting_accounts ?? []); ?>,
     paymentModes: <?php echo json_encode($payment_modes ?? []); ?>,
     baseCurrencyId: <?php echo json_encode($base_currency_id ?? 0); ?>,
     warehouses: <?php echo json_encode($warehouses ?? []); ?>
   };
   var journalEntryNextNumber = <?php echo json_encode($journal_entry_next_number ?? 1); ?>;
   var journalEntrySimpleEntryTypes = [
     { value: 'cash_deposit', label: 'Cash Deposit', needsOwner: false },
     { value: 'cash_withdrawal', label: 'Cash Withdrawal', needsOwner: false },
     { value: 'owners_draw', label: "Owner's Draw", needsOwner: true },
     { value: 'owners_capital_injection', label: "Owner's Capital Injection", needsOwner: true },
     { value: 'loan_to_owner', label: 'Loan to Owner', needsOwner: true },
     { value: 'owner_loan_repayment', label: 'Owner Loan Repayment', needsOwner: true },
     { value: 'reimburse_owner', label: 'Reimburse Owner', needsOwner: true }
   ];
   var journalEntrySimplePaymentModes = [
     { value: 'cash', label: 'Cash', accountId: 2 },
     { value: 'bank_transfer', label: 'Bank Transfer', accountId: 139 }
   ];
   var journalEntrySimpleOwners = [
     { value: 'azmil', label: 'Azmil', equityAccountId: 203, dueToOwnerAccountId: 141, loanToOwnerAccountId: 206 },
     { value: 'fakrul', label: 'Fakrul', equityAccountId: 204, dueToOwnerAccountId: 142, loanToOwnerAccountId: 207 }
   ];

   (function($) {
    "use strict";

      appValidateForm($('#import_form'),{file_csv:{required:true,extension: "xlsx|xls|csv"},source:'required',status:'required', bank_account: 'required'});
      // function 

    $(document).on('change', '#bank-statement-select-all', function() {
      var isChecked = $(this).prop('checked');
      var includeMatched = shouldIncludeMatchedTransactions();
      var $eligibleRows = includeMatched
        ? $('#bank-statement-table tbody tr')
        : $('#bank-statement-table tbody tr').filter(function() {
            return $(this).data('matched') !== 1 && $(this).data('matched') !== '1';
          });
      var $eligibleCheckboxes = $eligibleRows.find('.bank-statement-checkbox');

      $eligibleCheckboxes.prop('checked', isChecked);
      $(this).prop('indeterminate', false);

      if(isChecked && !$eligibleCheckboxes.filter(':checked').length){
        alert_float('warning', 'No rows able to be selected.');
        $(this).prop('checked', false);
      }

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

    $(document).on('change', '#include-matched-transactions', function() {
      updateMatchedRowSelectionState();
    });

    $(document).on('click', '#transaction-type-create-bulk', function() {
      openBulkJournalEntryModal();
    });

    $(document).on('click', '.bank-statement-create-action', function(event) {
      event.preventDefault();
      openCreateTransactionModal($(this));
    });

    $(document).on('click', '#create-transaction-submit', function() {
      var selectedType = $('#create-transaction-type').val();
      if(selectedType === 'purchase_order'){
        submitPurchaseOrderTransaction();
        return;
      }
      if(selectedType === 'expense'){
        submitExpenseTransaction();
        return;
      }
      if(selectedType === 'bill'){
        submitBillTransaction();
        return;
      }
      if(selectedType === 'journal_entry'){
        submitJournalEntryTransaction();
        return;
      }
      alert_float('warning', 'This transaction type is not supported yet.');
    });

  })(jQuery);

function updateBankStatementSelectAll(){
  "use strict";

  var $checkboxes = $('#bank-statement-table tbody .bank-statement-checkbox:not(:disabled)');
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

function shouldIncludeMatchedTransactions(){
  "use strict";

  return $('#include-matched-transactions').prop('checked');
}

function updateMatchedRowSelectionState(){
  "use strict";

  var includeMatched = shouldIncludeMatchedTransactions();
  var $matchedRows = $('#bank-statement-table tbody tr').filter(function() {
    return $(this).data('matched') === 1 || $(this).data('matched') === '1';
  });
  var $matchedCheckboxes = $matchedRows.find('.bank-statement-checkbox');

  $matchedCheckboxes.prop('disabled', !includeMatched);
  if(!includeMatched){
    $matchedCheckboxes.prop('checked', false);
  }

  updateBankStatementSelectAll();
  updateSelectedRowCount();
}

function setButtonLoading($button, isLoading, loadingLabel){
  "use strict";

  if(!$button || !$button.length){
    return;
  }

  if(isLoading){
    if(!$button.data('original-text')){
      $button.data('original-text', $button.html());
    }
    var label = loadingLabel || $button.text().trim() || 'Loading';
    $button.html('<i class="fa fa-spinner fa-spin mright5"></i>' + label);
    $button.prop('disabled', true).addClass('disabled');
    return;
  }

  var originalText = $button.data('original-text');
  if(originalText){
    $button.html(originalText);
  }
  $button.prop('disabled', false).removeClass('disabled');
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
      setButtonLoading($('button[id="uploadfile"]'), true, 'Importing');

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
      setButtonLoading($('button[id="uploadfile"]'), false);

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
    }).fail(function() {
      alert_float('warning', 'Unable to import file.');
    }).always(function() {
      $('#box-loading').html('');
      setButtonLoading($('button[id="uploadfile"]'), false);
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
        + '<div class="d-flex justify-content-between align-items-start text-left" style="justify-content: space-between;">'
        + '<div class="text-left">'+buildMatchedDetails(row, matchedBadge, matchedText)+'</div>'
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
  updateMatchedRowSelectionState();
  updateBankStatementTabCounts(rows);
  applyBankStatementTabFilter();
  updateSelectedRowCount();
  toggleBulkJournalButton();
}

function applyTransactionFilter(){
  "use strict";

  var selectedType = $('#transaction-type-filter').val();
  var $rows = $('#bank-statement-table tbody tr');
  var includeMatched = shouldIncludeMatchedTransactions();

  if(!$rows.length){
    return;
  }

  if(!selectedType){
    $rows.find('.bank-statement-checkbox').prop('checked', false);
    updateBankStatementSelectAll();
    updateSelectedRowCount();
    return;
  }

  var selectedCount = 0;
  $rows.each(function(){
    var $row = $(this);
    var description = ($row.data('description') || '').toString().trim();
    var spent = ($row.data('spent') || '').toString();
    var received = ($row.data('received') || '').toString();
    var isMatch = matchesTransactionType(description, spent, received, selectedType);
    var isMatchedRow = $row.data('matched') === 1 || $row.data('matched') === '1';
    var shouldSelect = isMatch && (includeMatched || !isMatchedRow);
    $row.find('.bank-statement-checkbox').prop('checked', shouldSelect);
    if(shouldSelect){
      selectedCount += 1;
    }
  });

  updateBankStatementSelectAll();
  updateSelectedRowCount();
  if(selectedCount === 0){
    alert_float('warning', 'No rows able to be selected.');
  }
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

function matchesTransactionType(description, spent, received, selectedType){
  "use strict";

  switch(selectedType){
    case 'duitnow_qr':
      return /^\d+\s+\d+Q$/.test(description) || description === 'DUITNOW QR-';
    case 'card_sales':
      return description === 'DR/CARD SALES M/N 37' && isPositiveAmount(received);
    case 'card_charges':
      return description === 'DR/CARD SALES M/N 37' && isPositiveAmount(spent);
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
  var $bulkButton = $('#transaction-type-create-bulk');

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

  setButtonLoading($bulkButton, true, 'Creating');

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
  }).always(function() {
    setButtonLoading($bulkButton, false);
  });
}

function openCreateTransactionModal($trigger){
  "use strict";

  var $row = $trigger.closest('tr');
  var modal = $('#create-transaction-modal');

  if(!modal.length){
    return;
  }

  setButtonLoading(modal.find('#create-transaction-submit'), false);

  modal.find('.modal-title').text('Create Transaction');
  modal.data('rowIndex', $row.data('index'));
  modal.data('statementDate', $row.data('date') || '');
  modal.data('statementAmount', $row.data('spent') || $row.data('received') || '');
  modal.data('statementPaymentMode', 'Bank Transfer');
  modal.data('statementDescription', $row.data('description') || '');
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
    init_datepicker();
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
    $container.html(buildJournalEntryForm(modal.data('statementDate') || '', modal.data('statementDescription') || ''));
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

  var $paymentModeSelect = $('#create-transaction-form-container').find('select[name="payment_mode_id"]');
  if($paymentModeSelect.length && purchaseOrderData.paymentModes.length){
    var preferredId = purchaseOrderData.paymentModes[0].id || '';
    var preferredName = (modal.data('statementPaymentMode') || '').toString().trim().toLowerCase();
    if(preferredName){
      purchaseOrderData.paymentModes.forEach(function(mode){
        if((mode.name || '').toString().trim().toLowerCase() === preferredName){
          preferredId = mode.id || preferredId;
        }
      });
    }
    $paymentModeSelect.val(preferredId);
  }
}

function isPositiveAmount(amount){
  "use strict";

  if(amount === null || amount === undefined){
    return false;
  }

  var normalized = amount.toString().trim();
  if(!normalized){
    return false;
  }

  var numeric = parseFloat(normalized.replace(/[,RMrm\s]/g, ''));
  return !isNaN(numeric) && numeric > 0;
}

function buildMatchedDetails(row, matchedBadge, matchedFallback){
  "use strict";

  var line1 = row.matched_display_line_1 || '';
  var line2 = row.matched_display_line_2 || '';
  var details = '';

  if(line1){
    details += '<div>'+htmlspecialchars(line1)+matchedBadge+'</div>';
  }

  if(line2){
    details += '<div class="text-muted">'+htmlspecialchars(line2)+'</div>';
  }

  if(!details){
    details = '<div>'+htmlspecialchars(matchedFallback || '')+matchedBadge+'</div>';
  }

  return details;
}

function updateBillPaymentSection(modal){
  "use strict";

  var $container = $('#create-transaction-form-container');
  var statementAmount = modal.data('statementAmount') || '';
  var statementDate = modal.data('statementDate') || '';

  var $paymentAmount = $container.find('input[name="bill_payment_amount"]');
  if($paymentAmount.length){
    $paymentAmount.val(statementAmount);
  }

  var $paymentDate = $container.find('input[name="bill_payment_date"]');
  if($paymentDate.length){
    $paymentDate.val(statementDate);
  }

  var $debitAmount = $container.find('input[name="bill_debit_amount"]');
  if($debitAmount.length && !$debitAmount.val()){
    $debitAmount.val(statementAmount);
  }

  var $creditAmount = $container.find('input[name="bill_credit_amount"]');
  if($creditAmount.length && !$creditAmount.val()){
    $creditAmount.val(statementAmount);
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
    var $receivedDate = $('#create-transaction-form-container').find('input[name="goods_receipt_date"]');
    if($receivedDate.length && !$receivedDate.val()){
      $receivedDate.val(statementDate).trigger('change');
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
  $defaultFooter.show();
  $orderFooter.hide();
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
    var vendorCode = vendor.vendor_code || '';
    vendorOptions += '<option value="' + vendor.userid + '" data-vendor-code="' + htmlspecialchars(vendorCode) + '">' + (vendor.company || '') + '</option>';
  });

  var itemOptions = '<option value="">Select an item</option>';
  purchaseOrderData.items.forEach(function(item){
    var skuCode = item.sku_code || '';
    var skuName = item.sku_name || '';
    var label = skuCode ? (skuCode + '_' + skuName) : skuName;
    itemOptions += '<option value="' + item.id + '" data-description="' + htmlspecialchars(item.long_description || '') + '" data-rate="' + (item.rate || 0) + '" data-label="' + htmlspecialchars(label) + '">' + label + '</option>';
  });

  var paymentModeOptions = '<option value="">Select a payment mode</option>';
  purchaseOrderData.paymentModes.forEach(function(mode){
    paymentModeOptions += '<option value="' + mode.id + '">' + (mode.name || '') + '</option>';
  });

  var warehouseOptions = '<option value="">Select a warehouse</option>';
  purchaseOrderData.warehouses.forEach(function(warehouse){
    warehouseOptions += '<option value="' + warehouse.warehouse_id + '">' + (warehouse.warehouse_name || '') + '</option>';
  });

  return ''
    + '<div class="purchase-order-form">'
    + '  <div class="form-group">'
    + '    <label class="checkbox-inline"><input type="checkbox" id="po-choose-from-order"> Choose from Purchase Order</label>'
    + '  </div>'
    + '  <div class="text-muted small m-b-10" id="po-amount-notice"></div>'
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
    + '    <input type="text" class="form-control datepicker" name="order_date">'
    + '  </div>'
    + '  <div class="form-group">'
    + '    <label>Attachment</label>'
    + '    <input type="file" class="form-control" name="file">'
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
    + '  <div class="form-group">'
    + '    <label class="checkbox-inline"><input type="checkbox" id="po-items-received"> Items Received</label>'
    + '  </div>'
    + '  <div class="panel panel-default goods-receipt-fields" style="display: none;">'
    + '    <div class="panel-heading"><strong>Goods Receipt</strong></div>'
    + '    <div class="panel-body">'
    + '      <div class="row">'
    + '        <div class="col-md-6">'
    + '          <div class="form-group">'
    + '            <label>Warehouse</label>'
    + '            <select class="form-control" name="goods_receipt_warehouse_id">' + warehouseOptions + '</select>'
    + '          </div>'
    + '        </div>'
    + '        <div class="col-md-6">'
    + '          <div class="form-group">'
    + '            <label>Date Received</label>'
    + '            <input type="text" class="form-control datepicker" name="goods_receipt_date">'
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
    + '          <select class="form-control" name="payment_mode_id">' + paymentModeOptions + '</select>'
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
    + '    <input type="number" class="form-control" name="amount" readonly>'
    + '  </div>'
    + '  <div class="form-group">'
    + '    <label>Payment Mode</label>'
    + '    <input type="text" class="form-control" name="payment_mode" readonly>'
    + '  </div>'
    + '  <div class="form-group">'
    + '    <label>Description (optional)</label>'
    + '    <textarea class="form-control" name="description" rows="3"></textarea>'
    + '  </div>'
    + '  <div class="form-group">'
    + '    <label>Expense Receipt</label>'
    + '    <input type="file" class="form-control" name="file">'
    + '  </div>'
    + '</div>';
}

function buildBillForm(){
  "use strict";

  var billOptions = '<option value="">Select a bill</option>';
  purchaseOrderData.bills.forEach(function(bill){
    var billDate = bill.invoice_date || '';
    var vendorName = bill.company || '';
    var expenseName = bill.expense_name || '';
    var amount = bill.total || '';
    var labelParts = [billDate, vendorName];
    if(expenseName){
      labelParts.push(expenseName);
    }
    var label = labelParts.filter(Boolean).join('-');
    if(amount !== ''){
      label += ' (' + format_money(amount) + ')';
    }
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
    + '              <input type="number" class="form-control" name="bill_debit_amount" min="0" step="0.01">'
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
    + '              <input type="number" class="form-control" name="bill_credit_amount" min="0" step="0.01">'
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

function buildJournalEntryForm(statementDate, statementDescription){
  "use strict";

  var entryId = formatJournalEntryNumber(statementDate);
  var accountOptions = buildJournalEntryAccountOptions();
  var entryTypeOptions = buildJournalEntrySimpleEntryTypeOptions();
  var paymentModeOptions = buildJournalEntrySimplePaymentModeOptions();
  var ownerOptions = buildJournalEntrySimpleOwnerOptions();

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
    + '  <div class="form-group">'
    + '    <label>Description</label>'
    + '    <input type="text" class="form-control" id="journal-entry-description" value="' + htmlspecialchars(statementDescription || '') + '">'
    + '  </div>'
    + '  <div class="form-group m-t-15">'
    + '    <label class="checkbox-inline">'
    + '      <input type="checkbox" id="journal-entry-advanced" checked> Advanced Entry'
    + '    </label>'
    + '  </div>'
    + '  <div id="journal-entry-simple" style="display: none;">'
    + '    <div class="form-group">'
    + '      <label>Entry Type</label>'
    + '      <select class="form-control" id="journal-entry-simple-type">' + entryTypeOptions + '</select>'
    + '    </div>'
    + '    <div class="row journal-entry-simple-owner-fields" style="display: none;">'
    + '      <div class="col-md-6">'
    + '        <div class="form-group">'
    + '          <label>Payment Mode</label>'
    + '          <select class="form-control" id="journal-entry-simple-payment-mode">' + paymentModeOptions + '</select>'
    + '        </div>'
    + '      </div>'
    + '      <div class="col-md-6">'
    + '        <div class="form-group">'
    + '          <label>Owner</label>'
    + '          <select class="form-control" id="journal-entry-simple-owner">' + ownerOptions + '</select>'
    + '        </div>'
    + '      </div>'
    + '    </div>'
    + '    <div class="row">'
    + '      <div class="col-md-6">'
    + '        <div class="form-group">'
    + '          <label>Amount</label>'
    + '          <input type="number" class="form-control" id="journal-entry-simple-amount" min="0" step="0.01">'
    + '        </div>'
    + '      </div>'
    + '    </div>'
    + '    <input type="hidden" name="simple_debit_account_id" id="journal-entry-simple-debit-account">'
    + '    <input type="hidden" name="simple_credit_account_id" id="journal-entry-simple-credit-account">'
    + '  </div>'
    + '  <div id="journal-entry-advanced-section">'
    + '    <div class="table-responsive m-t-15">'
    + '      <table class="table table-bordered" id="journal-entry-lines">'
    + '        <thead>'
    + '          <tr>'
    + '            <th width="30%">Account</th>'
    + '            <th width="15%">Debit</th>'
    + '            <th width="15%">Credit</th>'
    + '            <th width="30%">Description</th>'
    + '            <th width="10%"></th>'
    + '          </tr>'
    + '        </thead>'
    + '        <tbody>'
    + buildJournalEntryLineRow(0, accountOptions)
    + '        </tbody>'
    + '      </table>'
    + '    </div>'
    + '    <div class="text-right">'
    + '      <button type="button" class="btn btn-success" id="journal-entry-add-line">'
    + '        <i class="fa fa-plus"></i> Add line'
    + '      </button>'
    + '    </div>'
    + '  </div>'
    + '</div>';
}

function buildJournalEntrySimpleEntryTypeOptions(){
  "use strict";

  var options = '<option value="">Select an entry type</option>';
  journalEntrySimpleEntryTypes.forEach(function(entry){
    options += '<option value="' + entry.value + '" data-needs-owner="' + (entry.needsOwner ? '1' : '0') + '">' + entry.label + '</option>';
  });
  return options;
}

function buildJournalEntrySimplePaymentModeOptions(){
  "use strict";

  var options = '<option value="">Select a payment mode</option>';
  journalEntrySimplePaymentModes.forEach(function(mode){
    options += '<option value="' + mode.value + '" data-account-id="' + mode.accountId + '">' + mode.label + '</option>';
  });
  return options;
}

function buildJournalEntrySimpleOwnerOptions(){
  "use strict";

  var options = '<option value="">Select an owner</option>';
  journalEntrySimpleOwners.forEach(function(owner){
    options += '<option value="' + owner.value + '" data-equity="' + owner.equityAccountId + '" data-due="' + owner.dueToOwnerAccountId + '" data-loan="' + owner.loanToOwnerAccountId + '">' + owner.label + '</option>';
  });
  return options;
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
  $container.off('change.journalEntry');

  $container.on('change.journalEntry', '#journal-entry-advanced', function(){
    var isAdvanced = $(this).prop('checked');
    $container.find('#journal-entry-advanced-section').toggle(isAdvanced);
    $container.find('#journal-entry-simple').toggle(!isAdvanced);
  });

  $container.on('change.journalEntry', '#journal-entry-simple-type, #journal-entry-simple-owner, #journal-entry-simple-payment-mode', function(){
    updateJournalEntrySimpleForm();
  });

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
  updateJournalEntrySimpleForm();
  var statementAmount = $('#create-transaction-modal').data('statementAmount') || '';
  if(statementAmount){
    $container.find('#journal-entry-simple-amount').val(statementAmount);
  }
}

function updateJournalEntrySimpleForm(){
  "use strict";

  var $container = $('#create-transaction-form-container');
  var entryType = $container.find('#journal-entry-simple-type').val();
  var $entryTypeOption = $container.find('#journal-entry-simple-type option:selected');
  var needsOwner = $entryTypeOption.data('needs-owner') === 1 || $entryTypeOption.data('needs-owner') === '1';
  var $ownerFields = $container.find('.journal-entry-simple-owner-fields');

  $ownerFields.toggle(!!entryType && needsOwner);

  var debitAccountId = '';
  var creditAccountId = '';

  if(entryType === 'cash_deposit'){
    debitAccountId = 139;
    creditAccountId = 2;
  }else if(entryType === 'cash_withdrawal'){
    debitAccountId = 2;
    creditAccountId = 139;
  }else if(entryType && needsOwner){
    var paymentAccountId = parseInt($container.find('#journal-entry-simple-payment-mode option:selected').data('account-id'), 10);
    var ownerData = $container.find('#journal-entry-simple-owner option:selected');
    var ownerEquity = parseInt(ownerData.data('equity'), 10);
    var ownerDue = parseInt(ownerData.data('due'), 10);
    var ownerLoan = parseInt(ownerData.data('loan'), 10);

    if(!isNaN(paymentAccountId) && ownerData.length){
      if(entryType === 'owners_draw'){
        debitAccountId = ownerEquity;
        creditAccountId = paymentAccountId;
      }else if(entryType === 'owners_capital_injection'){
        debitAccountId = paymentAccountId;
        creditAccountId = ownerEquity;
      }else if(entryType === 'loan_to_owner'){
        debitAccountId = ownerLoan;
        creditAccountId = paymentAccountId;
      }else if(entryType === 'owner_loan_repayment'){
        debitAccountId = paymentAccountId;
        creditAccountId = ownerLoan;
      }else if(entryType === 'reimburse_owner'){
        debitAccountId = ownerDue;
        creditAccountId = paymentAccountId;
      }
    }
  }

  $container.find('#journal-entry-simple-debit-account').val(debitAccountId || '');
  $container.find('#journal-entry-simple-credit-account').val(creditAccountId || '');
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
  var updatePurchaseOrderFormMode = function(isExisting){
    $container.find('input[name="order_date"]').prop('readonly', isExisting);
    $container.find('select[name="payment_mode_id"]').prop('disabled', !isExisting);
  };

  $container.off('change.purchaseOrder');
  $container.on('change.purchaseOrder', '#po-choose-from-order', function(){
    var isChecked = $(this).prop('checked');
    $container.find('.purchase-order-manual').toggle(!isChecked);
    $container.find('.purchase-order-existing').toggle(isChecked);
    updatePurchaseOrderFormMode(isChecked);
  });

  $container.on('change.purchaseOrder', '#po-existing-selector', function(){
    var $selected = $(this).find('option:selected');
    $container.find('#po-existing-vendor').text($selected.data('vendor') || '');
    $container.find('#po-existing-order-date').text($selected.data('order-date') || '');
    $container.find('#po-existing-subtotal').text($selected.data('subtotal') || '');
    $container.find('#po-existing-total').text($selected.data('total') || '');
  });

  $container.on('change.purchaseOrder', 'select[name="vendor"]', function(){
    updatePurchaseOrderNumber($container.find('input[name="order_date"]').val() || '');
  });

  $container.on('change.purchaseOrder', 'input[name="order_date"]', function(){
    updatePurchaseOrderNumber($(this).val() || '');
  });

  $container.on('change.purchaseOrder', '#po-items-received', function(){
    $container.find('.goods-receipt-fields').toggle($(this).prop('checked'));
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

  updatePurchaseOrderFormMode($container.find('#po-choose-from-order').prop('checked'));
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
      if(parts[0].length === 4){
        return parts[2].padStart(2, '0') + parts[1].padStart(2, '0') + parts[0];
      }
      if(parts[2].length === 4){
        return parts[0].padStart(2, '0') + parts[1].padStart(2, '0') + parts[2];
      }
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

function updatePurchaseOrderNumber(statementDate){
  "use strict";

  var $container = $('#create-transaction-form-container');
  var $orderNumber = $container.find('input[name="order_number"]');
  if(!$orderNumber.length){
    return;
  }
  var dateSuffix = formatStatementDateForOrder(statementDate);
  var baseNumber = purchaseOrderData.orderNumber || '';
  if(!baseNumber && purchaseOrderData.orderNumberNext){
    baseNumber = 'PO-' + String(purchaseOrderData.orderNumberNext).padStart(5, '0');
  }
  var orderNumber = baseNumber + (dateSuffix ? '-' + dateSuffix : '');
  if(!$container.find('#po-choose-from-order').prop('checked')){
    var vendorCode = getSelectedVendorCode($container);
    if(vendorCode){
      orderNumber += '-' + vendorCode;
    }
  }
  $orderNumber.val(orderNumber);
}

function getSelectedVendorCode($container){
  "use strict";

  var $selected = $container.find('select[name="vendor"] option:selected');
  return ($selected.data('vendor-code') || '').toString().trim();
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

function getPurchaseOrderPayload(){
  "use strict";

  var $container = $('#create-transaction-form-container');
  var isExisting = $container.find('#po-choose-from-order').prop('checked');
  var itemsReceived = $container.find('#po-items-received').prop('checked');
  var payload = {
    choose_from_purchase_order: isExisting ? 1 : 0,
    payment_amount: $container.find('input[name="payment_amount"]').val() || '',
    payment_date: $container.find('input[name="payment_date"]').val() || '',
    payment_mode_id: $container.find('select[name="payment_mode_id"]').val() || '',
    payment_mode: $container.find('select[name="payment_mode_id"] option:selected').text() || '',
    items_received: itemsReceived ? 1 : 0,
    goods_receipt_warehouse_id: $container.find('select[name="goods_receipt_warehouse_id"]').val() || '',
    goods_receipt_date: $container.find('input[name="goods_receipt_date"]').val() || ''
  };

  if(isExisting){
    payload.purchase_order_id = $container.find('#po-existing-selector').val() || '';
    return payload;
  }

  payload.vendor = $container.find('select[name="vendor"]').val() || '';
  payload.pur_order_name = $container.find('input[name="order_name"]').val() || '';
  payload.pur_order_number = $container.find('input[name="order_number"]').val() || '';
  payload.number = purchaseOrderData.orderNumberNext || '';
  payload.order_date = $container.find('input[name="order_date"]').val() || '';
  payload.currency = purchaseOrderData.baseCurrencyId || 0;
  payload.add_discount_type = $container.find('#po-discount-type').val() || 'amount';
  payload.order_discount = $container.find('#po-discount').val() || 0;
  payload.shipping_fee = $container.find('#po-shipping-fee').val() || 0;
  payload.dc_total = parseFloat($container.find('#po-total-discount-display').text() || 0) || 0;
  payload.total_mn = parseFloat($container.find('#po-subtotal-display').text() || 0) || 0;
  payload.grand_total = parseFloat($container.find('#po-grand-total-display').text() || 0) || 0;

  var newitems = [];
  $container.find('#po-items-list tbody tr').each(function(index){
    var $row = $(this);
    var qty = parseFloat($row.find('td').eq(2).text()) || 0;
    var unitPrice = parseFloat($row.find('td').eq(3).text()) || 0;
    var discount = parseFloat($row.find('[data-item-discount]').data('item-discount')) || 0;
    var subtotal = parseFloat($row.find('[data-item-subtotal]').data('item-subtotal')) || 0;
    var total = parseFloat($row.find('[data-item-total]').data('item-total')) || 0;

    newitems.push({
      item_code: '',
      unit_id: '',
      unit_price: unitPrice.toFixed(2),
      into_money: subtotal.toFixed(2),
      total: total.toFixed(2),
      tax_value: 0,
      item_name: $row.find('td').eq(0).text(),
      item_description: $row.find('td').eq(1).text(),
      total_money: subtotal.toFixed(2),
      discount_money: discount.toFixed(2),
      discount: discount.toFixed(2),
      quantity: qty.toFixed(2)
    });
  });

  payload.newitems = newitems;

  if(!payload.order_date){
    payload.order_date = ($('#create-transaction-modal').data('statementDate') || '').toString();
  }

  return payload;
}

function validatePurchaseOrderPayload(payload){
  "use strict";

  if(payload.choose_from_purchase_order){
    if(!payload.purchase_order_id){
      alert_float('warning', 'Please select a purchase order.');
      return false;
    }
  }else{
    if(!payload.vendor){
      alert_float('warning', 'Please select a vendor.');
      return false;
    }
    if(!payload.newitems || !payload.newitems.length){
      alert_float('warning', 'Please add at least one item.');
      return false;
    }
  }

  if(payload.items_received){
    if(!payload.goods_receipt_warehouse_id){
      alert_float('warning', 'Please select a warehouse for the goods receipt.');
      return false;
    }
    if(!payload.goods_receipt_date){
      alert_float('warning', 'Please select a received date for the goods receipt.');
      return false;
    }
  }

  if(!payload.payment_amount || !payload.payment_date || !payload.payment_mode_id){
    alert_float('warning', 'Payment amount, mode, and date are required.');
    return false;
  }

  return true;
}

function appendFormData(formData, data, parentKey){
  "use strict";

  if(data === null || data === undefined){
    return;
  }

  if(data instanceof File){
    formData.append(parentKey, data);
    return;
  }

  if(Array.isArray(data)){
    data.forEach(function(value, index){
      appendFormData(formData, value, parentKey + '[' + index + ']');
    });
    return;
  }

  if(typeof data === 'object'){
    Object.keys(data).forEach(function(key){
      var value = data[key];
      var formKey = parentKey ? parentKey + '[' + key + ']' : key;
      appendFormData(formData, value, formKey);
    });
    return;
  }

  formData.append(parentKey, data);
}

function submitPurchaseOrderTransaction(){
  "use strict";

  var payload = getPurchaseOrderPayload();
  if(!validatePurchaseOrderPayload(payload)){
    return;
  }

  var $createButton = $('#create-transaction-submit');

  if(<?php echo acc_check_csrf_protection(); ?>){
    payload[csrfData.token_name] = csrfData.hash;
  }

  var formData = new FormData();
  appendFormData(formData, payload, '');
  var attachmentInput = $('#create-transaction-form-container').find('input[name="file"]')[0];
  if(attachmentInput && attachmentInput.files && attachmentInput.files.length){
    formData.set('file', attachmentInput.files[0]);
  }

  setButtonLoading($createButton, true, 'Creating');

  $.ajax({
    url: admin_url + 'accounting/create_purchase_order_from_bank_transaction',
    method: 'post',
    data: formData,
    processData: false,
    contentType: false
  }).done(function(response){
    response = JSON.parse(response);
    if(response.success){
      alert_float('success', response.message || 'Transaction created.');
      $('#create-transaction-modal').modal('hide');
      refreshCurrentStatementMatch();
    }else{
      alert_float('warning', response.message || 'Unable to create transaction.');
    }
  }).fail(function(){
    alert_float('warning', 'Unable to create transaction.');
  }).always(function(){
    setButtonLoading($createButton, false);
  });
}

function getExpensePayload(){
  "use strict";

  var $container = $('#create-transaction-form-container');
  return {
    vendor: $container.find('select[name="expense_vendor"]').val() || '',
    expense_name: $container.find('input[name="expense_name"]').val() || '',
    category: $container.find('select[name="expense_category"]').val() || '',
    date: $container.find('input[name="date"]').val() || '',
    amount: $container.find('input[name="amount"]').val() || '',
    payment_mode: $container.find('input[name="payment_mode"]').val() || '',
    note: $container.find('textarea[name="description"]').val() || ''
  };
}

function validateExpensePayload(payload){
  "use strict";

  if(!payload.category){
    alert_float('warning', 'Please select an expense category.');
    return false;
  }

  if(!payload.amount || parseFloat(payload.amount) <= 0){
    alert_float('warning', 'Expense amount is required.');
    return false;
  }

  if(!payload.date){
    alert_float('warning', 'Expense date is required.');
    return false;
  }

  if(!payload.payment_mode){
    alert_float('warning', 'Payment mode is required.');
    return false;
  }

  return true;
}

function submitExpenseTransaction(){
  "use strict";

  var payload = getExpensePayload();
  if(!validateExpensePayload(payload)){
    return;
  }

  var $createButton = $('#create-transaction-submit');

  if(<?php echo acc_check_csrf_protection(); ?>){
    payload[csrfData.token_name] = csrfData.hash;
  }

  var formData = new FormData();
  appendFormData(formData, payload, '');
  var receiptInput = $('#create-transaction-form-container').find('input[name="file"]')[0];
  if(receiptInput && receiptInput.files && receiptInput.files.length){
    formData.set('file', receiptInput.files[0]);
  }

  setButtonLoading($createButton, true, 'Creating');

  $.ajax({
    url: admin_url + 'accounting/create_expense_from_bank_transaction',
    method: 'post',
    data: formData,
    processData: false,
    contentType: false
  }).done(function(response){
    response = JSON.parse(response);
    if(response.success){
      alert_float('success', response.message || 'Expense created.');
      $('#create-transaction-modal').modal('hide');
      refreshCurrentStatementMatch();
    }else{
      alert_float('warning', response.message || 'Unable to create expense.');
    }
  }).fail(function(){
    alert_float('warning', 'Unable to create expense.');
  }).always(function(){
    setButtonLoading($createButton, false);
  });
}

function getBillPayload(){
  "use strict";

  var $container = $('#create-transaction-form-container');
  var isExisting = $container.find('#bill-choose-from-bills').prop('checked');
  var paymentAmount = $container.find('input[name="bill_payment_amount"]').val() || '';
  var paymentDate = $container.find('input[name="bill_payment_date"]').val() || '';
  var paymentAccount = $container.find('select[name="bill_payment_account"]').val() || '';
  var depositTo = $container.find('select[name="bill_deposit_to"]').val() || '';

  var payload = {
    choose_from_bills: isExisting ? 1 : 0,
    payment_amount: paymentAmount,
    payment_date: paymentDate,
    payment_account: paymentAccount,
    deposit_to: depositTo
  };

  if(isExisting){
    payload.bill_id = $container.find('#bill-existing-selector').val() || '';
    return payload;
  }

  var debitAmount = $container.find('input[name="bill_debit_amount"]').val() || '';
  var creditAmount = $container.find('input[name="bill_credit_amount"]').val() || '';
  if(debitAmount && !creditAmount){
    creditAmount = debitAmount;
  }
  if(creditAmount && !debitAmount){
    debitAmount = creditAmount;
  }

  payload.vendor = $container.find('select[name="bill_vendor"]').val() || '';
  payload.expense_name = $container.find('input[name="bill_expense_name"]').val() || '';
  payload.bill_date = $container.find('input[name="bill_date"]').val() || '';
  payload.bill_due_date = $container.find('input[name="bill_due_date"]').val() || '';
  payload.debit_account = $container.find('select[name="bill_debit_account"]').val() || '';
  payload.debit_amount = debitAmount;
  payload.credit_account = $container.find('select[name="bill_credit_account"]').val() || '';
  payload.credit_amount = creditAmount;
  payload.amount = debitAmount || creditAmount || paymentAmount || '';

  return payload;
}

function validateBillPayload(payload){
  "use strict";

  if(!payload.payment_amount || parseFloat(payload.payment_amount) <= 0){
    alert_float('warning', 'Payment amount is required.');
    return false;
  }

  if(!payload.payment_date){
    alert_float('warning', 'Payment date is required.');
    return false;
  }

  if(!payload.payment_account || !payload.deposit_to){
    alert_float('warning', 'Payment account and deposit to are required.');
    return false;
  }

  if(payload.choose_from_bills){
    if(!payload.bill_id){
      alert_float('warning', 'Please select a bill.');
      return false;
    }
    return true;
  }

  if(!payload.vendor){
    alert_float('warning', 'Please select a vendor.');
    return false;
  }

  if(!payload.bill_date || !payload.bill_due_date){
    alert_float('warning', 'Bill date and due date are required.');
    return false;
  }

  if(!payload.debit_account || !payload.credit_account){
    alert_float('warning', 'Debit and credit accounts are required.');
    return false;
  }

  if(!payload.debit_amount || !payload.credit_amount){
    alert_float('warning', 'Debit and credit amounts are required.');
    return false;
  }

  if(parseFloat(payload.debit_amount) !== parseFloat(payload.credit_amount)){
    alert_float('warning', 'Debit and credit amounts must match.');
    return false;
  }

  return true;
}

function submitBillTransaction(){
  "use strict";

  var payload = getBillPayload();
  if(!validateBillPayload(payload)){
    return;
  }

  var $createButton = $('#create-transaction-submit');

  if(<?php echo acc_check_csrf_protection(); ?>){
    payload[csrfData.token_name] = csrfData.hash;
  }

  setButtonLoading($createButton, true, 'Creating');

  $.ajax({
    url: admin_url + 'accounting/create_bill_from_bank_transaction',
    method: 'post',
    data: payload
  }).done(function(response){
    response = JSON.parse(response);
    if(response.success){
      alert_float('success', response.message || 'Bill created.');
      $('#create-transaction-modal').modal('hide');
      refreshCurrentStatementMatch();
    }else{
      alert_float('warning', response.message || 'Unable to create bill.');
    }
  }).fail(function(){
    alert_float('warning', 'Unable to create bill.');
  }).always(function(){
    setButtonLoading($createButton, false);
  });
}

function parseJournalEntryAmount(value){
  "use strict";

  if(value === null || value === undefined){
    return 0;
  }

  var parsed = parseFloat(String(value).replace(/,/g, '').replace(/RM/gi, '').trim());
  return isNaN(parsed) ? 0 : parsed;
}

function getJournalEntrySimplePayload(){
  "use strict";

  var $container = $('#create-transaction-form-container');
  var modal = $('#create-transaction-modal');
  var entryType = $container.find('#journal-entry-simple-type').val();
  var entryLabel = $container.find('#journal-entry-simple-type option:selected').text() || '';
  var amount = $container.find('#journal-entry-simple-amount').val() || '';
  var formDescription = $container.find('#journal-entry-description').val() || '';
  var statementDescription = modal.data('statementDescription') || '';

  var headerDescription = formDescription || statementDescription || entryLabel;

  var debitAccountId = $container.find('#journal-entry-simple-debit-account').val() || '';
  var creditAccountId = $container.find('#journal-entry-simple-credit-account').val() || '';

  return {
    entry_type: entryType,
    number: $container.find('input[name="number"]').val() || '',
    journal_date: $container.find('input[name="journal_date"]').val() || '',
    description: headerDescription,
    amount: amount,
    account: [debitAccountId, creditAccountId],
    debit_amount: [amount, 0],
    credit_amount: [0, amount],
    description_detail: [headerDescription, headerDescription]
  };
}

function validateJournalEntrySimplePayload(payload){
  "use strict";

  if(!payload.journal_date){
    alert_float('warning', 'Journal date is required.');
    return false;
  }

  if(!payload.entry_type){
    alert_float('warning', 'Please select an entry type.');
    return false;
  }

  var amountValue = parseJournalEntryAmount(payload.amount);
  if(amountValue <= 0){
    alert_float('warning', 'Amount is required.');
    return false;
  }

  var $container = $('#create-transaction-form-container');
  var $entryTypeOption = $container.find('#journal-entry-simple-type option:selected');
  var needsOwner = $entryTypeOption.data('needs-owner') === 1 || $entryTypeOption.data('needs-owner') === '1';

  if(needsOwner){
    var paymentMode = $container.find('#journal-entry-simple-payment-mode').val();
    var owner = $container.find('#journal-entry-simple-owner').val();
    if(!paymentMode || !owner){
      alert_float('warning', 'Payment mode and owner are required for this entry type.');
      return false;
    }
  }

  if(!payload.account[0] || !payload.account[1]){
    alert_float('warning', 'Debit and credit accounts are required.');
    return false;
  }

  return true;
}

function getJournalEntryAdvancedPayload(){
  "use strict";

  var $container = $('#create-transaction-form-container');
  var modal = $('#create-transaction-modal');
  var statementDescription = modal.data('statementDescription') || '';
  var headerDescription = $container.find('#journal-entry-description').val() || statementDescription;
  var payload = {
    number: $container.find('input[name="number"]').val() || '',
    journal_date: $container.find('input[name="journal_date"]').val() || '',
    description: headerDescription,
    amount: '',
    account: [],
    debit_amount: [],
    credit_amount: [],
    description_detail: []
  };

  $container.find('#journal-entry-lines tbody tr').each(function(){
    var $row = $(this);
    var account = $row.find('select').val() || '';
    var debit = $row.find('input[name^="debit_amount"]').val() || '';
    var credit = $row.find('input[name^="credit_amount"]').val() || '';
    var detail = $row.find('input[name^="description_detail"]').val() || headerDescription || statementDescription || '';

    payload.account.push(account);
    payload.debit_amount.push(debit);
    payload.credit_amount.push(credit);
    payload.description_detail.push(detail);
  });

  return payload;
}

function validateJournalEntryAdvancedPayload(payload){
  "use strict";

  if(!payload.journal_date){
    alert_float('warning', 'Journal date is required.');
    return false;
  }

  var totalDebit = 0;
  var totalCredit = 0;
  var hasLine = false;

  for(var i = 0; i < payload.account.length; i++){
    var account = payload.account[i];
    var debitValue = parseJournalEntryAmount(payload.debit_amount[i]);
    var creditValue = parseJournalEntryAmount(payload.credit_amount[i]);

    if(debitValue > 0 || creditValue > 0 || account){
      hasLine = true;
    }

    if(!account && (debitValue > 0 || creditValue > 0)){
      alert_float('warning', 'Account is required for each line with an amount.');
      return false;
    }

    if(account && debitValue === 0 && creditValue === 0){
      alert_float('warning', 'Debit or credit amount is required for each line.');
      return false;
    }

    if(debitValue > 0 && creditValue > 0){
      alert_float('warning', 'Each line should have either a debit or credit amount.');
      return false;
    }

    totalDebit += debitValue;
    totalCredit += creditValue;
  }

  if(!hasLine){
    alert_float('warning', 'Please enter at least one journal entry line.');
    return false;
  }

  if(totalDebit <= 0 || totalCredit <= 0){
    alert_float('warning', 'Debit and credit totals are required.');
    return false;
  }

  if(totalDebit !== totalCredit){
    alert_float('warning', 'Debit and credit totals must match.');
    return false;
  }

  payload.amount = totalDebit.toFixed(2);
  return true;
}

function submitJournalEntryTransaction(){
  "use strict";

  var $container = $('#create-transaction-form-container');
  var isAdvanced = $container.find('#journal-entry-advanced').prop('checked');
  var payload = isAdvanced ? getJournalEntryAdvancedPayload() : getJournalEntrySimplePayload();
  var isValid = isAdvanced
    ? validateJournalEntryAdvancedPayload(payload)
    : validateJournalEntrySimplePayload(payload);

  if(!isValid){
    return;
  }

  var $createButton = $('#create-transaction-submit');

  if(!isAdvanced){
    payload.amount = parseJournalEntryAmount(payload.amount).toFixed(2);
  }

  if(<?php echo acc_check_csrf_protection(); ?>){
    payload[csrfData.token_name] = csrfData.hash;
  }

  setButtonLoading($createButton, true, 'Creating');

  $.ajax({
    url: admin_url + 'accounting/create_journal_entry_from_bank_transaction',
    method: 'post',
    data: payload
  }).done(function(response){
    response = JSON.parse(response);
    if(response.success){
      alert_float('success', response.message || 'Journal entry created.');
      $('#create-transaction-modal').modal('hide');
      refreshCurrentStatementMatch();
    }else{
      alert_float('warning', response.message || 'Unable to create journal entry.');
    }
  }).fail(function(){
    alert_float('warning', 'Unable to create journal entry.');
  }).always(function(){
    setButtonLoading($createButton, false);
  });
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
      bankStatementRows[updated.index].matched_display_line_1 = updated.matched_display_line_1 || '';
      bankStatementRows[updated.index].matched_display_line_2 = updated.matched_display_line_2 || '';
    });

    render_statement_table(bankStatementRows);
  });
}
</script>
