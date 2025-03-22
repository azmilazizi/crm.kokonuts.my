
<script>

    "use strict";

var InvoiceServerParams={};

var payslip_table = $('.table-payslip_table');
initDataTable(payslip_table, admin_url+'hr_payroll/payslip_table',[0],[0], InvoiceServerParams, [0 ,'desc']);

$('#date_add').on('change', function() {
    payslip_table.DataTable().ajax.reload().columns.adjust().responsive.recalc();
});


var hidden_columns = [0];
$('.table-payslip_table').DataTable().columns(hidden_columns).visible(false, false);

appValidateForm($("body").find('#add_payslip'), {
    'payslip_month': 'required',
    'payslip_name': 'required',
    'payslip_template_id': 'required',
});  

appValidateForm($("body").find('#edit_payslip'), {

    'payslip_name': 'required',
});

function new_payslip(){
    "use strict";

    $('#payslip_template_modal').modal('show');
    $('.edit-title').addClass('hide');
    $('.add-title').removeClass('hide');
    $('#additional_payslip_template').html('');
    $('#additional_payslip_column').html('');

    $('#add_payslip_template input[name="templates_name"]').val('');

    var id = '';
    requestGetJSON('hr_payroll/get_payslip_template/' + id).done(function (response) {
    	$("select[id='payslip_template_id']").html('');
        $("select[id='payslip_template_id']").append(response.payslip_template_selected).selectpicker('refresh');

    });

}

function edit_payslip(invoker,id, payslip_template_id){
    "use strict";

    $('#additional_edit_payslip_id').html('');

    requestGetJSON('hr_payroll/get_pdf_payslip_template/'+id+ '/' + payslip_template_id).done(function (response) {
        $(event).removeAttr('disabled')
        $('#additional_edit_payslip_id').append(hidden_input('id',id));
        
        $("select[id='pdf_template_id']").html('');
        $("select[id='pdf_template_id']").append(response.pdf_payslip_template_selected).selectpicker('refresh');
        $("input[id='payslip_name']").val(response.payslip_name);

        if(response.to_currency_id != 0){
            $("#edit_payslip select[id='from_currency_id']").val(response.from_currency_id).selectpicker('refresh');
            $("#edit_payslip select[id='to_currency_id']").val(response.to_currency_id).selectpicker('refresh');
            if(response.from_currency_id !== response.to_currency_id){
                $('#edit_payslip #convert_str').html(response.from_currency_name+' => '+response.to_currency_name + ': ' + response.to_currency_rate);
            }else{
                $('#edit_payslip #convert_str').html('');
            }
        }else{

            $("#edit_payslip select[id='from_currency_id']").val(response.base_currency_id).selectpicker('refresh');
            $("#edit_payslip select[id='to_currency_id']").val(response.base_currency_id).selectpicker('refresh');
            $('#edit_payslip #convert_str').html('');
        }

    });

    $('#edit_payslip_template_modal').modal('show');
    $('.add-title').addClass('hide');
    $('.edit-title').removeClass('hide');
}

$('.payslip_checked').on('click', function(event) {
    "use strict";

    var payslip_month = $("body").find(' input[name="payslip_month"]').val();
    var payslip_name = $("body").find(' input[name="payslip_name"]').val();
    var payslip_template_id = $("body").find(' select[id="payslip_template_id"]').val();

    if (payslip_name !== '' && payslip_template_id.length > 0 && payslip_month != '' ) {
        var data={};
        data.payslip_month = payslip_month;
        data.payslip_name = payslip_name;
        data.payslip_template_id = payslip_template_id;

        $(event).attr( "disabled", "disabled" );
        $.post(admin_url + 'hr_payroll/payslip_checked', data).done(function(response) {
            response = JSON.parse(response);

            if (response.status === true || response.status == 'true') {
                $('#add_payslip').submit()
            } else {
                $(event).removeAttr('disabled')
                alert_float('warning', response.message, 5000);
            }
        });
    }

});


var payslip_range_obj = $('input[name="payslip_range"]');
if(payslip_range_obj.val() == ''){
    payslip_range_obj.daterangepicker({
        dateLimit: {
            'months': 1,
            'days': -1
        },
        singleDatePicker: false,
        linkedCalendars: false,
      // autoUpdateInput: false,
      locale: {
          cancelLabel: 'Clear'
      }
  });
}
else{
    payslip_range_obj.daterangepicker({
      autoUpdateInput: false,
      locale: {
          cancelLabel: 'Clear'
      }
  });    
}

$('select[name="payslip_template_id"]').on('change', function(event) {
    "use strict";

    var payslip_template_id = $("body").find(' select[name="payslip_template_id"]').val();
    var id = 0;

    if (payslip_template_id !== '') {

        $(event).attr( "disabled", "disabled" );

        requestGetJSON('hr_payroll/get_pdf_payslip_template/'+id+ '/' + payslip_template_id).done(function (response) {
            $(event).removeAttr('disabled')

            $("select[id='pdf_template_id']").html('');
            $("select[id='pdf_template_id']").append(response.pdf_payslip_template_selected).selectpicker('refresh');

        });
     
    }else{
        $("select[id='pdf_template_id']").html('');
            $("select[id='pdf_template_id']").selectpicker('refresh');

    }

});

$("body").on('change', 'select[name="to_currency_id"]', function () {

  var currency_id = $(this).val();
  if(currency_id != ''){
    $.post(admin_url + 'hr_payroll/get_currency_rate/'+currency_id).done(function(response){
      response = JSON.parse(response);
      if(response.currency_rate != 1){
        // $('#currency_rate_div').removeClass('hide');

        $('input[name="currency_rate"]').val(response.currency_rate).change();

        $('#convert_str').html(response.convert_str + ': ' + response.currency_rate);
        $('#edit_payslip #convert_str').html(response.convert_str + ': ' + response.currency_rate);
        $('.th_currency').html(response.currency_name);
      }else{
        $('input[name="currency_rate"]').val(response.currency_rate).change();
        // $('#currency_rate_div').addClass('hide');
        $('#convert_str').html(response.convert_str + ': ' + response.currency_rate);
        $('#edit_payslip #convert_str').html(response.convert_str + ': ' + response.currency_rate);
        $('.th_currency').html(response.currency_name);

      }

    });
  }else{
    alert_float('warning', "<?php echo _l('please_select_currency'); ?>" )
  }

});

</script>