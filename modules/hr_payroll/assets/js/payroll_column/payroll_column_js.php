<script>  

    appValidateForm($("body").find('#add_payroll_column'), {
      'column_key': 'required',
      'taking_method': 'required',
  });    

  function new_column_type(){
    "use strict";

    $('#insurance_type_modal').modal('show');
    $('.edit-title').addClass('hide');
    $('.add-title').removeClass('hide');
    $('#additional_payroll_column').html('');

    $(".function_name_hide").addClass("hide"); 
    $('#add_payroll_column input[name="display_with_staff"]').prop('checked',true); 
    $('#add_payroll_column input[name="value_related_to"]').val('');
    $('#add_payroll_column input[name="column_key"]').val('');
    $('#add_payroll_column textarea[name="description"]').val('');
    $('add_payroll_column input[name="column_key"]' ).val('');
    $("select[name='taking_method']").removeAttr( "disabled", "disabled" );
    $("select[name='function_name']").removeAttr( "disabled", "disabled" );




    $.post(admin_url + 'hr_payroll/get_payroll_column_method_html_add').done(function(response) {
      response = JSON.parse(response);

        $('#add_payroll_column input[name="order_display"]').val(response.order_display);
        //taking method
        $("select[id='taking_method']").html('');
        $("select[id='taking_method']").append(response.method_option).selectpicker('refresh');

        init_selectpicker(); 
        $(".selectpicker").selectpicker('refresh');

    });

  }

  function edit_column_type(invoker,id){
    "use strict";

    $('#additional_payroll_column').html('');

    requestGetJSON('hr_payroll/get_payroll_column/' + id).done(function (response) {
        
        if(response.payroll_column.is_edit == 'no'){
            //function name hide
            $(".function_name_hide").removeClass("hide"); 
            $("input[name='function_name']").attr( "disabled", "disabled" );
            
            $("select[name='taking_method']").attr( "disabled", "disabled" );
            $("select[name='function_name']").attr( "disabled", "disabled" );
        }else{
            $(".function_name_hide").addClass("hide"); 

            $("select[name='taking_method']").removeAttr( "disabled", "disabled" );
            $("select[name='function_name']").removeAttr( "disabled", "disabled" );
        }

        $('#additional_payroll_column').append(hidden_input('id',response.payroll_column.id));
        $('#add_payroll_column input[name="column_key"]').val(response.payroll_column.column_key);
        $('#add_payroll_column textarea[name="description"]').val(response.payroll_column.description);
        $('#add_payroll_column input[name="value_related_to"]').val(response.payroll_column.value_related_to);
        $('#add_payroll_column input[name="order_display"]').val(response.payroll_column.order_display);

        //taking method
        $("#add_payroll_column select[name='taking_method']").html('');
        $("#add_payroll_column select[name='taking_method']").append(response.method_option.method_option).selectpicker('refresh');

        //function name
        $("#add_payroll_column select[name='function_name']").html('');
        $("#add_payroll_column select[name='function_name']").append(response.function_name.method_option).selectpicker('refresh');

        if(response.payroll_column.taking_method == 'system'){
            $(".function_name_hide").removeClass("hide"); 
        }

        if(response.payroll_column.display_with_staff == 'true'){
            $('#add_payroll_column input[name="display_with_staff"]').prop('checked',true); 
        }else{
            $('#add_payroll_column input[name="display_with_staff"]').prop("checked", false);
        }
         



        $( 'input[name="function_name"]' ).val(response.payroll_column.function_name);
    });


    $('#insurance_type_modal').modal('show');
    $('.add-title').addClass('hide');
    $('.edit-title').removeClass('hide');
  }



  //save onclick, check validation
    $(".payroll_column_submit").on('click', function(event) {

    "use strict";

    var function_name_value = $('select[name="function_name"]').val();
    var taking_method_value = $('select[name="taking_method"]').val();

    var $flag_submit = true;
    if(taking_method_value == 'system'){
        if(function_name_value == ''){
            $flag_submit = false;
        }
    }

    if($flag_submit == true){
        var data={};

        /*update*/
        var check_id = $('#additional_payroll_column').html();
        if(check_id){
          data.id = $('input[name="id"]').val();
        }else{
          data.id = '';
        }
        data.from_month = $('input[name="from_month"]').val();

        $('#add_payroll_column').submit();
          
    }else{
        if(taking_method_value == 'system'){
            alert_float('warning', '<?php echo _l("please_select_function_name") ?>')
        }
    }

  });


  //get function name related on taking method
  $('select[name="taking_method"]').on('change', function(event) {

    "use strict";
    $("input[name='function_name']").val('');

    var taking_method_value = $('select[name="taking_method"]').val();
    if(taking_method_value == 'system'){
        $.post(admin_url + 'hr_payroll/get_payroll_column_function_name_html').done(function(response){
            response = JSON.parse(response); 
            
            //function name
            $("select[name='function_name']").html('');
            $("select[name='function_name']").append(response.method_option).selectpicker('refresh');

            //function name hide
            $(".function_name_hide").removeClass("hide"); 
            init_selectpicker(); 
            $(".selectpicker").selectpicker('refresh');

            $("input[name='function_name']").attr( "disabled", "disabled" );


        });
    }else{
        $(".function_name_hide").addClass("hide"); 
        $("input[name='function_name']").removeAttr( "disabled", "disabled" );

    }
  });


//check function name related to salary, allowance, timesheet, ...
$('select[name="function_name"]').on('change', function(event) {

    "use strict";

    $("input[name='column_key']").val($('#function_name option:selected').html());
    var function_name = $('select[name="function_name"]').val();

    $("input[name='function_name']").val(function_name);

});


$('input[name="column_key"]').on('change', function() {
    "use strict";
    
    var taking_method = $('select[name="taking_method"]').val();

    if($("input[name='id']").val() == undefined && taking_method != 'system'){
        var column_key = $('input[name="column_key"]' ).val().replace(/\s+/g, '_');
        $( 'input[name="function_name"]' ).val(column_key);
    }else{

      if($( 'input[name="function_name"]' ).val() == ''){
            var column_key = $('input[name="column_key"]' ).val().replace(/\s+/g, '_');
            $( 'input[name="function_name"]' ).val(column_key);
        }
    }

});

  
</script>