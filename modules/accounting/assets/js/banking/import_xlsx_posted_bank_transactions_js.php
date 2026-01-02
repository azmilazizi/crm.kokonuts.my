<script>
   (function($) {
    "use strict";

      appValidateForm($('#import_form'),{file_csv:{required:true,extension: "xlsx|xls|csv"},source:'required',status:'required', bank_account: 'required'});
      // function 

      if('<?php echo new_html_entity_decode($active_language) ?>' == 'vietnamese')
      {
        $( "#dowload_file_sample" ).append( '<a href="'+ site_url+'modules/accounting/uploads/file_sample/Sample_import_banking_file_vi.xlsx" class="btn btn-primary" ><?php echo _l('download_sample') ?></a><hr>' );

      }else{
        $( "#dowload_file_sample" ).append( '<a href="'+ site_url+'modules/accounting/uploads/file_sample/Sample_import_banking_file_en.xlsx" class="btn btn-primary" ><?php echo _l('download_sample') ?></a><hr>' );
      }

    $(document).on('change', '#bank-statement-select-all', function() {
      var isChecked = $(this).prop('checked');
      $('#bank-statement-table tbody .bank-statement-checkbox').prop('checked', isChecked);
      $(this).prop('indeterminate', false);
    });

    $(document).on('change', '#bank-statement-table tbody .bank-statement-checkbox', function() {
      updateBankStatementSelectAll();
    });

    $(document).on('change', '#transaction-type-filter', function() {
      applyTransactionFilter();
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

  var $tableBody = $('#bank-statement-table tbody');
  $tableBody.empty();

  $('#bank-statement-select-all').prop('checked', false).prop('indeterminate', false);

  if(!rows.length){
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
        + '<button type="button" class="btn btn-info dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Create</button>'
        + '<div class="dropdown-menu">'
        + '<a class="dropdown-item" href="#">Journal Entry</a>'
        + '<a class="dropdown-item" href="#">Expense</a>'
        + '<a class="dropdown-item" href="#">Bill</a>'
        + '<a class="dropdown-item" href="#">Purchase Order</a>'
        + '</div>'
        + '</div>'
        + '</div>';
    }

    var description = row.description || '';

    var rowHtml = ''
      + '<tr data-index="'+index+'" data-description="'+htmlspecialchars(description)+'">'
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
  applyTransactionFilter();
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
    return;
  }

  $rows.each(function(){
    var $row = $(this);
    var description = ($row.data('description') || '').toString().trim();
    var isMatch = matchesTransactionType(description, selectedType);
    $row.find('.bank-statement-checkbox').prop('checked', isMatch);
  });

  updateBankStatementSelectAll();
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
</script>
