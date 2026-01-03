<script>
   var bankStatementRows = [];

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

    var editBtn = '<button type="button" class="btn btn-default btn-icon" title="Edit"><i class="fa fa-edit"></i></button>';
    var deleteBtn = '<button type="button" class="btn btn-danger btn-icon" title="Delete"><i class="fa fa-trash"></i></button>';
    var matchedContent = '';

    if(row.matched){
      matchedContent = ''
        + '<div class="d-flex justify-content-between align-items-center text-left" style="justify-content: space-between;">'
        + '<span class="text-left">'+matchedText+'</span>'
        + '<span>'+editBtn+' '+deleteBtn+'</span>'
        + '</div>';
    }else{
      matchedContent = ''
        + '<div class="d-flex justify-content-end">'
        + '<div class="btn-group">'
        + '<button type="button" class="btn btn-info dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Create <span class="caret"></span></button>'
        + '<ul class="dropdown-menu dropdown-menu-right">'
        + '<li><a class="bank-statement-create-action" href="#" data-create-type="purchase_order">Purchase Order</a></li>'
        + '<li><a class="bank-statement-create-action" href="#" data-create-type="expense">Expense</a></li>'
        + '<li><a class="bank-statement-create-action" href="#" data-create-type="bill">Bill</a></li>'
        + '<li><a class="bank-statement-create-action" href="#" data-create-type="journal_entry">Journal Entry</a></li>'
        + '</ul>'
        + '</div>'
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
  var createType = $trigger.data('createType');
  var modal = $('#create-transaction-modal');
  var labels = {
    purchase_order: 'Purchase Order',
    expense: 'Expense',
    bill: 'Bill',
    journal_entry: 'Journal Entry'
  };
  var urls = {
    purchase_order: admin_url + 'purchase/purchase_order',
    expense: admin_url + 'expenses/expense',
    bill: admin_url + 'purchase/purchase_invoice',
    journal_entry: admin_url + 'accounting/new_journal_entry'
  };

  if(!modal.length){
    return;
  }

  var label = labels[createType] || 'Transaction';
  modal.find('.modal-title').text('Create ' + label);
  modal.find('[data-field="date"]').text($row.data('date') || '');
  modal.find('[data-field="description"]').text($row.data('description') || '');
  modal.find('[data-field="amount"]').text($row.data('spent') || $row.data('received') || '');
  modal.data('rowIndex', $row.data('index'));
  modal.data('createType', createType);
  modal.find('#create-transaction-open-form').attr('href', urls[createType] || '#');

  modal.modal('show');
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
    received: row.received || ''
  }]);
}

function refreshStatementMatches(rows){
  "use strict";

  if(!rows.length){
    return;
  }

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
      bankStatementRows[updated.index].matched_rel_type = updated.matched_rel_type || '';
      bankStatementRows[updated.index].matched_rel_id = updated.matched_rel_id || '';
    });

    render_statement_table(bankStatementRows);
  });
}
</script>
