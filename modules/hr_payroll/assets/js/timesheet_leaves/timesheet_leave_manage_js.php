<script>
	
		<?php if(isset($data_object_kpi)){ ?>

			var dataObject = <?php echo json_encode($data_object_kpi) ; ?>;
		<?php }?>
		var dataCellBackground = <?php echo ($cell_background); ?>;
		

	var hotElement1 = document.querySelector('#hrp_employees_value');
	
	 var commodity_fill = new Handsontable(hotElement1, {

		contextMenu: true,
		manualRowMove: true,
		manualColumnMove: true,
		stretchH: 'all',
		autoWrapRow: true,

		cells: function(row, col, prop) {
			var cellProperties = {};
			if (col > 1) {
				cellProperties.renderer = firstRowRenderer;
				cellProperties.className = 'htCenter htMiddle';
			}
			return cellProperties;
		},

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
		    columns: [0,1,2,3],
		    indicators: false
		  },
		multiColumnSorting: {
				indicator: true
			}, 
		fixedColumnsLeft: 6,
		filters: true,
		manualRowResub_group: true,
		manualColumnResub_group: true,
		allowInsertRow: true,
		allowRemoveRow: false,
		columnHeaderHeight: 40,

		rowHeights: 30,
		rowHeaderWidth: [44],


		 columns: <?php echo new_html_entity_decode($columns) ?>,

    colHeaders: <?php echo new_html_entity_decode($col_header); ?>,

		data: dataObject,

	});

	function firstRowRenderer(instance, td, row, col, prop, value, cellProperties) {
		"use strict";
		Handsontable.renderers.TextRenderer.apply(this, arguments);
		td.style.background = dataCellBackground[row][prop];
		td.style.color = 'black';
	}

	//filter
	function attendance_filter(invoker){
		'use strict';

		var data = {};
		data.month = $("#month_attendance").val();
		data.staff = $('select[name="staff_attendance[]"]').val();
		data.department = $('#department_attendance').val();
		data.role_attendance = $('select[name="role_attendance[]"]').val();

		$.post(admin_url + 'hr_payroll/timesheets_leave_filter', data).done(function(response) {
			response = JSON.parse(response);

			dataCellBackground = response.cell_background;

			dataObject = response.data_object;
			commodity_fill.updateSettings({
				columns: response.columns,
				colHeaders: response.col_header,
				data: dataObject,

				cells: function(row, col, prop) {
					var cellProperties = {};
					if (col > 1) {
						cellProperties.renderer = firstRowRenderer;
						cellProperties.className = 'htCenter htMiddle';
					}
					return cellProperties;
				},


			})
			$('input[name="month"]').val(response.month);
			$('.save_attendance').html(response.button_name);
			
		});
	};


	//save_attendance
	function save_attendance(event){
		'use strict';

		var valid_employees_value = $('#hrp_employees_value').find('.htInvalid').html();

		if(valid_employees_value){
			alert_float('danger', "<?php echo _l('data_invalid') ; ?>");
		}else{
      $(event).addClass('disabled');

			$('input[name="hrp_attendance_value"]').val(JSON.stringify(commodity_fill.getData()));   
			$('input[name="attendance_fill_month"]').val($("#month_attendance").val());
      $('input[name="hrp_attendance_rel_type"]').val('update');   
			$( "#add_attendance" ).submit();
		}

	};

	function save_synchronized(event){
		'use strict';

		var valid_employees_value = $('#hrp_employees_value').find('.htInvalid').html();

		if(valid_employees_value){
			alert_float('danger', "<?php echo _l('data_invalid') ; ?>");
		}else{
      $(event).addClass('disabled');

			$('input[name="hrp_attendance_value"]').val(JSON.stringify(commodity_fill.getData()));   
			$('input[name="attendance_fill_month"]').val($("#month_attendance").val());
			$('input[name="hrp_attendance_rel_type"]').val('synchronization');   
			$( "#add_attendance" ).submit();
		}

	};
	

	$('#department_attendance').on('change', function() {
		'use strict';

		$('input[name="department_attendance_filter"]').val($("#department_attendance").val());  
		attendance_filter();

	});

	$('#staff_attendance').on('change', function() {
		'use strict';

		$('input[name="staff_attendance_filter"]').val($("#staff_attendance").val());  
		attendance_filter();

	});

	$('#role_attendance').on('change', function() {
		'use strict';
		
		$('input[name="role_attendance_filter"]').val($("#role_attendance").val());  
		attendance_filter();
		 
	});

	$('#month_attendance').on('change', function() {
		'use strict';

		attendance_filter();

	});


	function timesheet_leave_calculation(event) {
		'use strict';
			
		 $(event).attr( "disabled", "disabled" );
		var data = {};
		data.month = $("#month_attendance").val();
		$.post(admin_url + 'hr_payroll/timesheet_leave_calculation', data).done(function(response) {
			response = JSON.parse(response);
			
			$(event).removeAttr('disabled')
			alert_float('success', "<?php echo _l('updated_successfully') ; ?>");
			attendance_filter();
		});

	}
	

</script>