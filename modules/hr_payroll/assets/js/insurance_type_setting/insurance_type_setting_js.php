<script>      

  function new_insurance_type(){
    "use strict";

    $('#insurance_type_modal').modal('show');
    $('.edit-title').addClass('hide');
    $('.add-title').removeClass('hide');
    $('#additional_insurance_type').html('');
    $('#insurance_type_modal input[name="from_month"]').val('<?php echo date('Y-m') ?>').change();

  }

  function edit_insurance_type(invoker,id){
    "use strict";

    $('#additional_insurance_type').html('');
    
    $('#additional_insurance_type').append(hidden_input('id',id));
    $('#insurance_type_modal input[name="from_month"]').val($(invoker).data('from_month')).change();
    $('#insurance_type_modal').find('select').selectpicker('refresh');
    $('#insurance_type_modal input[name="social_company"]').val($(invoker).data('social_company'));
    $('#insurance_type_modal input[name="social_staff"]').val($(invoker).data('social_staff'));
    $('#insurance_type_modal input[name="labor_accident_company"]').val($(invoker).data('labor_accident_company'));
    $('#insurance_type_modal input[name="labor_accident_staff"]').val($(invoker).data('labor_accident_staff'));
    $('#insurance_type_modal input[name="health_company"]').val($(invoker).data('health_company'));
    $('#insurance_type_modal input[name="health_staff"]').val($(invoker).data('health_staff'));
    $('#insurance_type_modal input[name="unemployment_company"]').val($(invoker).data('unemployment_company'));
    $('#insurance_type_modal input[name="unemployment_staff"]').val($(invoker).data('unemployment_staff'));

    /*update title social_staff*/
    $('#social_staff_title').html('');
    var social_company = $(invoker).data('social_company');
    var social_staff = $(invoker).data('social_staff');
    var total1 = parseFloat(social_company) + parseFloat(social_staff);
    var new_html1 = "<?php echo _l('social_insurance'); ?>" + '(' + total1 + '%' + ')' ;
    $('#social_staff_title').html(new_html1);
    
    /*update title labor_accident*/
    $('#labor_accident_insurance_title').html('');
    var labor_accident_company = $(invoker).data('labor_accident_company');
    var labor_accident_staff = $(invoker).data('labor_accident_staff');
    var total2 = parseFloat(labor_accident_company) + parseFloat(labor_accident_staff);
    var new_html2 = "<?php echo _l('labor_accident_insurance'); ?>" + '(' + total2 + '%' + ')' ;
    $('#labor_accident_insurance_title').html(new_html2);

    /*update title health_insurance*/
    $('#health_insurance_title').html('');
    var health_company = $(invoker).data('health_company');
    var health_staff = $(invoker).data('health_staff');
    var total3 = parseFloat(health_company) + parseFloat(health_staff);
    var new_html3 = "<?php echo _l('health_insurance'); ?>" + '(' + total3 + '%' + ')' ;
    $('#health_insurance_title').html(new_html3);

    /*update title health_insurance*/
    $('#unemployment_insurance_title').html('');
    var unemployment_company = $(invoker).data('unemployment_company');
    var unemployment_staff = $(invoker).data('unemployment_staff');
    var total4 = parseFloat(unemployment_company) + parseFloat(unemployment_staff);
    var new_html4 = "<?php echo _l('unemployment_insurance'); ?>" + '(' + total4 + '%' + ')' ;
    $('#unemployment_insurance_title').html(new_html4);

    $('#insurance_type_modal').modal('show');
    $('.add-title').addClass('hide');
    $('.edit-title').removeClass('hide');
  }


  function social_staff_change(invoker){
      "use strict";

    $('#social_staff_title').html('');
    var social_company = $('input[name="social_company"]').val();
    var social_staff = $('input[name="social_staff"]').val();
    var total = parseFloat(social_company) + parseFloat(social_staff);
    var new_html = "<?php echo _l('social_insurance'); ?>" + '(' + total + '%' + ')' ;

    $('#social_staff_title').html(new_html);

  }

  function labor_accident_staff_change(invoker){
    "use strict";

    $('#labor_accident_insurance_title').html('');
    var labor_accident_company = $('input[name="labor_accident_company"]').val();
    var labor_accident_staff = $('input[name="labor_accident_staff"]').val();
    var total = parseFloat(labor_accident_company) + parseFloat(labor_accident_staff);
    var new_html = "<?php echo _l('health_insurance'); ?>" + '(' + total + '%' + ')' ;

    $('#labor_accident_insurance_title').html(new_html);

  }

  function health_staff_change(invoker){
    "use strict";

    $('#health_insurance_title').html('');
    var health_company = $('input[name="health_company"]').val();
    var health_staff = $('input[name="health_staff"]').val();
    var total = parseFloat(health_company) + parseFloat(health_staff);
    var new_html = "<?php echo _l('labor_accident_insurance'); ?>" + '(' + total + '%' + ')' ;

    $('#health_insurance_title').html(new_html);

  }
  
  function unemployment_staff_change(invoker){
    "use strict";

    $('#unemployment_insurance_title').html('');
    var unemployment_company = $('input[name="unemployment_company"]').val();
    var unemployment_staff = $('input[name="unemployment_staff"]').val();
    var total = parseFloat(unemployment_company) + parseFloat(unemployment_staff);
    var new_html = "<?php echo _l('unemployment_insurance'); ?>" + '(' + total + '%' + ')' ;

    $('#unemployment_insurance_title').html(new_html);

  }

  $(".insurance_type_submit").on('click', function(event) {
    
    "use strict";

    var data={};
    /*update*/
    var check_id = $('#additional_insurance_type').html();
    if(check_id){
      data.id = $('input[name="id"]').val();
    }else{
      data.id = '';
    }
    data.from_month = $('input[name="from_month"]').val();

        $('.insurance_type_submit').attr('disabled', 'disabled');
        $.post(admin_url + 'hr_payroll/check_insurance_type_exist', data).done(function(response){
            response = JSON.parse(response); 
            if (response.status == 'true') {
                $('#add_insurance_type').submit();
            }else{
                alert_float('danger', response.message);
                $('.insurance_type_submit').removeAttr('disabled', 'disabled');

            }
        });

  });

  
</script>