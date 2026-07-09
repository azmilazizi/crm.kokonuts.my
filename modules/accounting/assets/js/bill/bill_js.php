<script>
var Input_debit_total = $('#bill-debit-account').children().length;
var Input_credit_total = $('#bill-credit-account').children().length;
var customer_currency = '';
var max_amount = '';
var limit = '';
Dropzone.options.expenseForm = false;
var expenseDropzone;
var timer = null;

(function($) {
  "use strict";
  $('.menu-item-accounting_expenses ').addClass('active');
  $('.menu-item-accounting_expenses ul').addClass('in');
  $('.sub-menu-item-accounting_bills').addClass('active');

  // Advanced Entry toggle
  $('#advanced_entry_toggle').on('change', function() {
    var isAdvanced = $(this).is(':checked');
    $('#advanced_entry_value').val(isAdvanced ? 1 : 0);

    if (isAdvanced) {
      $('#simple-entry-section').addClass('hide');
      $('#advanced-entry-section').removeClass('hide');
      $('#bill_amount_field').prop('disabled', true);
      $('#bill_amount_advanced').prop('disabled', false);
      caculate_total();
    } else {
      $('#simple-entry-section').removeClass('hide');
      $('#advanced-entry-section').addClass('hide');
      $('#bill_amount_field').prop('disabled', false);
      $('#bill_amount_advanced').prop('disabled', true);
    }
  });

  // Simple mode amount currency formatting
  $('#bill_amount_field').on({
    keyup: function() {
      formatCurrency($(this));
    },
    blur: function() {
      formatCurrency($(this), 'blur');
    }
  });

  var debitInput = document.getElementById('debit_amount[0]');
  if (debitInput) {
    debitInput.addEventListener('change', caculate_total);
  }

  $("body").on('click', '.new_debit_template', function() {
    var new_template = $('#bill-debit-account').find('.template_children').eq(0).clone().appendTo('#bill-debit-account');

    for(var i = 0; i <= new_template.find('#template-item').length ; i++){
        if(i > 0){
          new_template.find('#template-item').eq(i).remove();
        }
        new_template.find('#template-item').eq(1).remove();
    }

    new_template.find('.template').attr('value', Input_debit_total);
    new_template.find('button[role="combobox"]').remove();
    new_template.find('select').selectpicker('refresh');

    new_template.find('label[for="debit_account[0]"]').attr('for', 'debit_account[' + Input_debit_total + ']');
    new_template.find('select[name="debit_account[0]"]').attr('name', 'debit_account[' + Input_debit_total + ']');
    new_template.find('select[id="debit_account[0]"]').attr('id', 'debit_account[' + Input_debit_total + ']').selectpicker('refresh');
    new_template.find('input[id="debit_amount[0]"]').attr('name', 'debit_amount['+Input_debit_total+']').val('');
    new_template.find('input[id="debit_amount[0]"]').attr('id', 'debit_amount['+Input_debit_total+']').val('');

    new_template.find('button[name="add_template"] i').removeClass('fa-plus').addClass('fa-minus');
    new_template.find('button[name="add_template"]').removeClass('new_debit_template').addClass('remove_debit_template').removeClass('btn-success').addClass('btn-danger');

    $("input[data-type='currency']").on({
      keyup: function() {
        formatCurrency($(this));
        clearTimeout(timer);
        timer = setTimeout(caculate_total, 1000);
      },
      blur: function() {
        formatCurrency($(this), "blur");
      }
    });

    var newDebitInput = document.getElementById('debit_amount['+Input_debit_total+']');
    if (newDebitInput) {
      newDebitInput.addEventListener('change', caculate_total);
    }

    Input_debit_total++;
  });

  $("body").on('click', '.new_credit_template', function() {
    var new_template = $('#bill-credit-account').find('.template_children').eq(0).clone().appendTo('#bill-credit-account');

    for(var i = 0; i <= new_template.find('#template-item').length ; i++){
        if(i > 0){
          new_template.find('#template-item').eq(i).remove();
        }
        new_template.find('#template-item').eq(1).remove();
    }

    new_template.find('.template').attr('value', Input_credit_total);
    new_template.find('button[role="combobox"]').remove();
    new_template.find('select').selectpicker('refresh');

    new_template.find('label[for="credit_account[0]"]').attr('for', 'credit_account[' + Input_credit_total + ']');
    new_template.find('select[name="credit_account[0]"]').attr('name', 'credit_account[' + Input_credit_total + ']');
    new_template.find('select[id="credit_account[0]"]').attr('id', 'credit_account[' + Input_credit_total + ']').selectpicker('refresh');
    new_template.find('input[id="credit_amount[0]"]').attr('name', 'credit_amount['+Input_credit_total+']').val('');
    new_template.find('input[id="credit_amount[0]"]').attr('id', 'credit_amount['+Input_credit_total+']').val('');

    new_template.find('button[name="add_template"] i').removeClass('fa-plus').addClass('fa-minus');
    new_template.find('button[name="add_template"]').removeClass('new_credit_template').addClass('remove_template').removeClass('btn-success').addClass('btn-danger');

    $("input[data-type='currency']").on({
      keyup: function() {
        formatCurrency($(this));
      },
      blur: function() {
        formatCurrency($(this), "blur");
      }
    });
    Input_credit_total++;
  });

  $("body").on('click', '.remove_debit_template', function() {
    $(this).parents('.template_children').remove();
    caculate_total();
  });

  $("body").on('click', '.remove_template', function() {
    $(this).parents('.template_children').remove();
    caculate_total();
  });

  if ($('#dropzoneDragArea').length > 0) {
    expenseDropzone = new Dropzone("#expense-form", appCreateDropzoneOptions({
      autoProcessQueue: false,
      clickable: '#dropzoneDragArea',
      previewsContainer: '.dropzone-previews',
      addRemoveLinks: true,
      maxFiles: 1,
      success: function(file, response) {
        response = JSON.parse(response);
        if (this.getUploadingFiles().length === 0 && this.getQueuedFiles().length === 0) {
          window.location.assign(response.url);
        }
      },
    }));
  }

  appValidateForm($('#expense-form'), {
    vendor: 'required',
    date: 'required',
  }, expenseSubmitHandler);

  $("input[data-type='currency']").on({
    keyup: function() {
      formatCurrency($(this));
      clearTimeout(timer);
      timer = setTimeout(caculate_total, 1000);
    },
    blur: function() {
      formatCurrency($(this), "blur");
    }
  });

})(jQuery);

function expenseSubmitHandler(form) {
  var isAdvanced = $('#advanced_entry_value').val() == '1';

  if (isAdvanced) {
    var debit_amount = 0;
    $('input[name^="debit_amount"]').each(function() {
      if ($(this).val() != '') {
        debit_amount += parseFloat(unFormatNumber($(this).val()));
      }
    });

    var credit_amount = 0;
    $('input[name^="credit_amount"]').each(function() {
      if ($(this).val() != '') {
        credit_amount += parseFloat(unFormatNumber($(this).val()));
      }
    });

    if (debit_amount != credit_amount) {
      alert('<?php echo _l('please_balance_debits_and_credits'); ?>');
      return false;
    }

    var bill_amount = parseFloat($('#bill_amount_advanced').val()) || 0;
    if (bill_amount <= 0) {
      alert('<?php echo _l('the_total_bill_must_be_greater_than_0'); ?>');
      return false;
    }
  } else {
    var simple_val = $('#bill_amount_field').val();
    var bill_amount = parseFloat(unFormatNumber(simple_val)) || 0;
    if (bill_amount <= 0) {
      alert('<?php echo _l('the_total_bill_must_be_greater_than_0'); ?>');
      return false;
    }
  }

  $('input[name="date"]').prop('disabled', false);

  $.post(form.action, $(form).serialize()).done(function(response) {
    response = JSON.parse(response);
    if (response.billid) {
      if (typeof(expenseDropzone) !== 'undefined') {
        if (expenseDropzone.getQueuedFiles().length > 0) {
          expenseDropzone.options.url = admin_url + 'accounting/add_bill_attachment/' + response.billid;
          expenseDropzone.processQueue();
        } else {
          window.location.assign(response.url);
        }
      } else {
        window.location.assign(response.url);
      }
    } else {
      if (response.message) {
        alert_float('warning', response.message);
      }
      if (response.url) {
        window.location.assign(response.url);
      }
    }
  });
  return false;
}

function debit_account_change() {
  var debit_amount = 0;
  $('input[name^="debit_amount"]').each(function() {
    debit_amount += parseFloat(unFormatNumber($(this).val()) || 0);
  });
  $('#bill_amount_advanced').val(debit_amount);
  $('#bill-total').html(format_money(debit_amount));
}

function caculate_total() {
  if ($('#advanced_entry_value').val() != '1') {
    return;
  }

  var debit_amount = 0;
  $('input[name^="debit_amount"]').each(function() {
    if ($(this).val() != '') {
      debit_amount += parseFloat(unFormatNumber($(this).val()));
    }
  });

  var bill_total = debit_amount;

  $('#bill_amount_advanced').val(bill_total);
  $('#bill-total').html(format_money(bill_total));
}

function formatNumber(n) {
  "use strict";
  return n.replace(/\D/g, "").replace(/\B(?=(\d{3})+(?!\d))/g, ",")
}

function unFormatNumber(n) {
  "use strict";
  return n.replace(/([,])+/g, "");
}

function formatCurrency(input, blur) {
  "use strict";
  var input_val = input.val();
  if (input_val === "") { return; }

  var original_len = input_val.length;
  var caret_pos = input.prop("selectionStart");

  if (input_val.indexOf(".") >= 0) {
    var decimal_pos = input_val.indexOf(".");
    var minus = input_val.substring(0, 1);
    if (minus != '-') { minus = ''; }

    var left_side = input_val.substring(0, decimal_pos);
    var right_side = input_val.substring(decimal_pos);
    left_side = formatNumber(left_side);
    right_side = formatNumber(right_side);
    right_side = right_side.substring(0, 2);
    input_val = minus + left_side + "." + right_side;
  } else {
    var minus = input_val.substring(0, 1);
    if (minus != '-') { minus = ''; }
    input_val = formatNumber(input_val);
    input_val = minus + input_val;
  }

  input.val(input_val);

  var updated_len = input_val.length;
  caret_pos = updated_len - original_len + caret_pos;
}
</script>
