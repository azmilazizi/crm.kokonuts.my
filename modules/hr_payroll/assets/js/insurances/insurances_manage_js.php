<script>
	
	var purchase;
	
	<?php if(isset($body_value)){ ?>

		var dataObject = <?php echo new_html_entity_decode($body_value) ; ?>;
	<?php }?>

	var hotElement1 = document.querySelector('#hrp_insurances_value');
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
		function insurances_filter (invoker){
			'use strict';

			var data = {};
			data.month = $("#month_insurances").val();
			data.staff  = $('select[name="staff_insurances[]"]').val();
			data.department = $('#department_insurances').val();
			data.role_attendance = $('select[name="role_insurances[]"]').val();

			$.post(admin_url + 'hr_payroll/insurances_filter', data).done(function(response) {
				response = JSON.parse(response);
				dataObject = response.data_object;
				purchase.updateSettings({
					data: dataObject,

				})
				$('input[name="month"]').val(response.month);
				$('.save_manage_insurances').html(response.button_name);
				
			});
		};



		var purchase_value = purchase;



		$('.save_manage_insurances').on('click', function() {
			'use strict';

			var valid_contract = $('#hrp_insurances_value').find('.htInvalid').html();

			if(valid_contract){
				alert_float('danger', "<?php echo _l('data_invalid') ; ?>");
			}else{

				$('input[name="hrp_insurances_value"]').val(JSON.stringify(purchase_value.getData()));   
				$('input[name="insurances_fill_month"]').val($("#month_insurances").val());
				$('input[name="hrp_insurances_rel_type"]').val('update');   
				$('#add_manage_insurances').submit(); 

			}
		});

		$('#department_insurances').on('change', function() {
			'use strict';

			$('input[name="department_insurances_filter"]').val($("#department_insurances").val());  
			insurances_filter();

		});

		$('#staff_insurances').on('change', function() {
			'use strict';

			$('input[name="staff_insurances_filter"]').val($("#staff_insurances").val()); 
			insurances_filter();

		});

		$('#role_insurances').on('change', function() {
			'use strict';
			
			$('input[name="role_insurances_filter"]').val($("#role_insurances").val()); 
			insurances_filter();
			
		});
		

		$('#month_insurances').on('change', function() {
			'use strict';

			insurances_filter();

		});


	</script>