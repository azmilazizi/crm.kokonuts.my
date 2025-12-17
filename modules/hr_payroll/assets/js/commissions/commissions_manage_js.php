<script>
		
		var purchase;
		
		<?php if(isset($body_value)){ ?>

			var dataObject = <?php echo new_html_entity_decode($body_value) ; ?>;
		<?php }?>

	var hotElement1 = document.querySelector('#hrp_commissions_value');
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
	function commissions_filter (invoker){
		'use strict';

		var data = {};
		data.month = $("#month_commissions").val();
		data.staff  = $('select[name="staff_commissions[]"]').val();
		data.department = $('#department_commissions').val();
		data.role_attendance = $('select[name="role_commissions[]"]').val();

		$.post(admin_url + 'hr_payroll/commissions_filter', data).done(function(response) {
			response = JSON.parse(response);
			dataObject = response.data_object;
			purchase.updateSettings({
				data: dataObject,

			})
			$('input[name="month"]').val(response.month);
			$('.save_manage_commissions').html(response.button_name);
			
		});
	};



	var purchase_value = purchase;



	$('.save_manage_commissions').on('click', function() {
		'use strict';

		var valid_contract = $('#hrp_commissions_value').find('.htInvalid').html();

		if(valid_contract){
			alert_float('danger', "<?php echo _l('data_invalid') ; ?>");
		}else{

			$('input[name="hrp_commissions_value"]').val(JSON.stringify(purchase_value.getData()));   
			$('input[name="commissions_fill_month"]').val($("#month_commissions").val());
			$('input[name="hrp_commissions_rel_type"]').val('update');   
			$('#add_manage_commissions').submit(); 

		}
	});


	function save_synchronized(event){
		'use strict';

		var valid_employees_value = $('#hrp_commissions_value').find('.htInvalid').html();

		if(valid_employees_value){
			alert_float('danger', "<?php echo _l('data_invalid') ; ?>");
		}else{
      $(event).addClass('disabled');

			$('input[name="hrp_commissions_value"]').val(JSON.stringify(purchase_value.getData()));   
			$('input[name="commissions_fill_month"]').val($("#month_commissions").val());
			$('input[name="hrp_commissions_rel_type"]').val('synchronization');   
			$( "#add_manage_commissions" ).submit();
		}

	};
	
	$('#department_commissions').on('change', function() {
		'use strict';

		$('input[name="department_commissions_filter"]').val($("#department_commissions").val());  
		commissions_filter();

	});

	$('#staff_commissions').on('change', function() {
		'use strict';

		$('input[name="staff_commissions_filter"]').val($("#staff_commissions").val()); 
		commissions_filter();

	});

	$('#role_commissions').on('change', function() {
		'use strict';
		
		$('input[name="role_commissions_filter"]').val($("#role_commissions").val()); 
		commissions_filter();
		  
	});
	

	$('#month_commissions').on('change', function() {
		'use strict';

		commissions_filter();

	});


</script>