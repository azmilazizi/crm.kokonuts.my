<script>
	
	<?php if(isset($data_object_kpi)){ ?>

		var dataObject = <?php echo json_encode($data_object_kpi) ; ?>;
	<?php }?>

	var hotElement1 = document.querySelector('#example');
	var commodity_fill = new Handsontable(hotElement1, {

		contextMenu: true,
		manualRowMove: true,
		manualColumnMove: true,
		stretchH: 'all',
		autoWrapRow: true,

		rowHeights: 20,
		defaultRowHeight: 10,
		minHeight:'100%',
		width: '100%',
		height:600,

		licenseKey: 'non-commercial-and-evaluation',
		rowHeaders: true,
		autoColumnsub_group: {
			samplingRatio: 23
		},
		dropdownMenu: true,
		hiddenColumns: {
			columns: [0,3],
			indicators: false
		},
		multiColumnSorting: {
			indicator: true
		}, 
		filters: true,
		manualRowResub_group: true,
		manualColumnResub_group: true,
		allowInsertRow: false,
		allowRemoveRow: false,
		columnHeaderHeight: 40,

		colWidths: [50, 60, 100],
		rowHeights: 30,
		rowHeaderWidth: [44],

		columns: [
		{
			type: 'text',
			data: 'staffid'
		},
		{
			type: 'text',
			data: 'hr_code'
		},
		{
			type: 'text',
			data: 'staff_name',
		},
		{
			type: 'text',
			data: 'job_position',
		},
		{
			type: 'text',
			data: 'staff_departments',
		},
		{
			type: 'numeric',
			data: 'bonus_kpi',
			numericFormat: {
				pattern: '0,0'
			},
		},

		],

		colHeaders: [
		"<?php echo _l('staffid') ?>",
		"<?php echo _l('hr_code') ?>",
		"<?php echo _l('staff_name') ?>",
		"<?php echo _l('job_position') ?>",
		"<?php echo _l('department') ?>",
		"<?php echo _l('hr_bonus_kpi') ?>",
		],

		data: dataObject,

	});

	//filter
	function bonus_filter(invoker){
		"use strict";

		var data = {};
		data.month = $("#month_timesheets").val();
		data.staff = $('select[name="staff_timesheets[]"]').val();
		data.department = $('#department_timesheets').val();
		data.job_position = $('#job_position_timesheets').val();

		$.post(admin_url + 'hr_payroll/bonus_kpi_filter', data).done(function(response) {
			response = JSON.parse(response);
			dataObject = response.data_object;
			commodity_fill.updateSettings({
				data: dataObject,

			})
			$('input[name="month"]').val(response.month);
			$('.save_bonus_kpi').html(response.button_name);
			
		});
	};


	$('#month_timesheets').on('change', function() {
		'use strict';

		bonus_filter();
	});

	$('#department_timesheets').on('change', function() {
		'use strict';

		bonus_filter();
	});

	$('#staff_timesheets').on('change', function() {
		'use strict';

		bonus_filter();
	});
	



	function save_bonus_kpi(invoker){
		"use strict";

		$('input[name="bonus_kpi_value"]').val(commodity_fill.getData());   
		$('input[name="allowance_commodity_fill_month"]').val($("#month_timesheets").val());
		$( "#add_bonus_kpi" ).submit();
	};

	/*get jobposition in department by staff in department*/
	var job_position = {};

	function department_change(invoker){
		"use strict";

		var data_select = {};
		data_select.department_id = $('select[name="department_timesheets[]"]').val();
		data_select.status = 'true';
		if((data_select.department_id).length == 0){
			data_select.status = 'false';
		}

		$.post(admin_url + 'hrm/get_jobposition_fill_data',data_select).done(function(response){
			response = JSON.parse(response);
			$("select[name='job_position_timesheets[]']").html('');

			$("select[name='job_position_timesheets[]']").append(response.job_position);
			$("select[name='job_position_timesheets[]']").selectpicker('refresh');
			$('#manage_interview select[name="job_position_timesheets[]"]').val(job_position).change();

		});

	}


</script>