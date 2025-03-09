<script>

$('.add_data_integration').on('click', function() {
	'use strict';
	if (confirm_delete()) {
		$('#data_integration').submit(); 
	}
});

// option-show
$('#integrated_timesheets').on('change', function() {
	'use strict';

	var input_name_status = $('input[id="integrated_timesheets"]').is(":checked");
	if(input_name_status == true){
		$('.option-show').removeClass('hide');
	}else{
		$('.option-show').addClass('hide');
	}
});

//rel type change
$('#integration_actual_workday').on('change', function() {
	'use strict';

	timesheet_integration_type_change();
});

$('#integration_paid_leave').on('change', function() {
	'use strict';

	timesheet_integration_type_change();
});

$('#integration_unpaid_leave').on('change', function() {
	'use strict';

	timesheet_integration_type_change();
});


function timesheet_integration_type_change() {
	'use strict';
	
	var actual_workday = $('select[id="integration_actual_workday"]').val();
	var paid_leave = $('select[id="integration_paid_leave"]').val();
	var unpaid_leave = $('select[id="integration_unpaid_leave"]').val();

	var data={};
		data.actual_workday = actual_workday;
		data.paid_leave = paid_leave;
		data.unpaid_leave = unpaid_leave;

	$.post(admin_url + 'hr_payroll/timesheet_integration_type_change', data).done(function(response) {
		response = JSON.parse(response);

		$('select[id="integration_actual_workday"]').html('');
		$('select[id="integration_actual_workday"]').append(response.actual_workday_v);

		$('select[id="integration_paid_leave"]').html('');
		$('select[id="integration_paid_leave"]').append(response.paid_leave_v);

		$('select[id="integration_unpaid_leave"]').html('');
		$('select[id="integration_unpaid_leave"]').append(response.unpaid_leave_v);
		
		 init_selectpicker();
		 $('.selectpicker').selectpicker('refresh');
	});
}

$('#integrated_hrprofile').on('change', function() {
	'use strict';

	var input_name_status = $('input[id="integrated_hrprofile"]').is(":checked");
	if(input_name_status == true){
		$('.option-show-integra-hr-profile').removeClass('hide');
	}else{
		$('.option-show-integra-hr-profile').addClass('hide');
	}
});

var addMoreVendorsInputKey2 = $('.assets_wrap').children().length + 1;
$("body").on('click', '.new_assets_emp', function() {
	'use strict';
	if ($(this).hasClass('disabled')) { return false; }
	var new_assets_emp = $(this).parents('.assets_wrap').find('#assets_emp').eq(0).clone().appendTo($(this).parents('.assets_wrap'));
	new_assets_emp.find('button[data-toggle="dropdown"]').remove();

	new_assets_emp.find('input[name="asset_name[]"]').val('');       

	new_assets_emp.find('div[name="button_add"]').removeAttr("style");

	new_assets_emp.find('button[name="add_asset"] i').removeClass('fa-plus').addClass('fa-minus');
	new_assets_emp.find('button[name="add_asset"]').removeClass('new_assets_emp').addClass('remove_assets_emp').removeClass('btn-primary').addClass('btn-danger');

	new_assets_emp.find('select').selectpicker('val', '');
	addMoreVendorsInputKey2++;            
});
$("body").on('click', '.remove_assets_emp', function() {
	'use strict';
	$(this).parents('#assets_emp').remove();
}); 

$('#hrp_customize_staff_payslip_column').on('change', function() {
	'use strict';

	var input_name_status = $('input[id="hrp_customize_staff_payslip_column"]').is(":checked");
	if(input_name_status == true){
		$('.option-show-customize-payslip-column').removeClass('hide');
	}else{
		$('.option-show-customize-payslip-column').addClass('hide');
	}
});




</script>