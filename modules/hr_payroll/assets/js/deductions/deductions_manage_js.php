<script>
		
		var purchase;
		
		<?php if(isset($body_value)){ ?>

			var dataObject = <?php echo new_html_entity_decode($body_value) ; ?>;
		<?php }?>

	var hotElement1 = document.querySelector('#hrp_deductions_value');
	 var purchase = new Handsontable(hotElement1, {

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
		    columns: [0,1,2],
		    indicators: false
		  },
		multiColumnSorting: {
				indicator: true
			}, 
		fixedColumnsLeft: 5,

		filters: true,
		manualRowResub_group: true,
		manualColumnResub_group: true,
		allowInsertRow: false,
		allowRemoveRow: false,
		columnHeaderHeight: 40,

		rowHeights: 40,
		rowHeaderWidth: [44],


		columns: <?php echo new_html_entity_decode($columns) ?>,

		colHeaders: <?php echo new_html_entity_decode($col_header); ?>,

		data: dataObject,

	});

		//filter
	function deductions_filter (invoker){
		'use strict';

		var data = {};
		data.month = $("#month_deductions").val();
		data.staff  = $('select[name="staff_deductions[]"]').val();
		data.department = $('#department_deductions').val();
		data.role_attendance = $('select[name="role_deductions[]"]').val();

		$.post(admin_url + 'hr_payroll/deductions_filter', data).done(function(response) {
			response = JSON.parse(response);
			dataObject = response.data_object;
			purchase.updateSettings({
				data: dataObject,

			})
			$('input[name="month"]').val(response.month);
			$('.save_manage_deductions').html(response.button_name);
			
		});
	};



	var purchase_value = purchase;



	$('.save_manage_deductions').on('click', function() {
		'use strict';

		var valid_contract = $('#hrp_deductions_value').find('.htInvalid').html();

		if(valid_contract){
			alert_float('danger', "<?php echo _l('data_invalid') ; ?>");
		}else{

			$('input[name="hrp_deductions_value"]').val(JSON.stringify(purchase_value.getData()));   
			$('input[name="deductions_fill_month"]').val($("#month_deductions").val());
			$('input[name="hrp_deductions_rel_type"]').val('update');   
			$('#add_manage_deductions').submit(); 

		}
	});

	$('#department_deductions').on('change', function() {
		'use strict';

		$('input[name="department_deductions_filter"]').val($("#department_deductions").val());  
		deductions_filter();

	});

	$('#staff_deductions').on('change', function() {
		'use strict';

		$('input[name="staff_deductions_filter"]').val($("#staff_deductions").val()); 
		deductions_filter();

	});

	$('#role_deductions').on('change', function() {
		'use strict';
		
		$('input[name="role_deductions_filter"]').val($("#role_deductions").val()); 
		deductions_filter();
		  
	});
	

	$('#month_deductions').on('change', function() {
		'use strict';

		deductions_filter();

	});


</script>