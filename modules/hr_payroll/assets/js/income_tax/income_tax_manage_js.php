<script>
		
		var purchase;
		
		<?php if(isset($body_value)){ ?>

			var dataObject = <?php echo new_html_entity_decode($body_value) ; ?>;
		<?php }?>

	var hotElement1 = document.querySelector('#hrp_income_taxs_value');
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
		    columns: [0,1,2,3],
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
	function income_taxs_filter (invoker){
		'use strict';

		var data = {};
		data.month = $("#month_income_taxs").val();
		data.staff  = $('select[name="staff_income_taxs[]"]').val();
		data.department = $('#department_income_taxs').val();
		data.role_attendance = $('select[name="role_income_taxs[]"]').val();

		$.post(admin_url + 'hr_payroll/income_taxs_filter', data).done(function(response) {
			response = JSON.parse(response);
			dataObject = response.data_object;
			purchase.updateSettings({
				data: dataObject,

			})
			$('input[name="month"]').val(response.month);
			
		});
	};



	var purchase_value = purchase;



	$('.save_manage_income_taxs').on('click', function() {
		'use strict';

		var valid_contract = $('#hrp_income_taxs_value').find('.htInvalid').html();

		if(valid_contract){
			alert_float('danger', "<?php echo _l('data_invalid') ; ?>");
		}else{

			$('input[name="hrp_income_taxs_value"]').val(JSON.stringify(purchase_value.getData()));   
			$('input[name="income_taxs_fill_month"]').val($("#month_income_taxs").val());
			$('input[name="hrp_income_taxs_rel_type"]').val('update');   
			$('#add_manage_income_taxs').submit(); 

		}
	});

	
	$('#department_income_taxs').on('change', function() {
		'use strict';

		$('input[name="department_income_taxs_filter"]').val($("#department_income_taxs").val());  
		income_taxs_filter();

	});

	$('#staff_income_taxs').on('change', function() {
		'use strict';

		$('input[name="staff_income_taxs_filter"]').val($("#staff_income_taxs").val()); 
		income_taxs_filter();

	});

	$('#role_income_taxs').on('change', function() {
		'use strict';
		
		$('input[name="role_income_taxs_filter"]').val($("#role_income_taxs").val()); 
		income_taxs_filter();
		  
	});
	

	$('#month_income_taxs').on('change', function() {
		'use strict';

		income_taxs_filter();

	});


</script>