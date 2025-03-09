<script>
	var purchase;

	(function($) {
		"use strict";  


	<?php if(isset($insurance_list)){?>
		var dataObject_pu = <?php echo new_html_entity_decode($insurance_list); ?>;
	<?php }else{ ?>
		var dataObject_pu = [];
	<?php } ?>

	//hansometable for purchase
	var row_global;
	var hotElement1 = document.getElementById('insurance_list_hs');

	purchase = new Handsontable(hotElement1, {
		licenseKey: 'non-commercial-and-evaluation',

		contextMenu: true,
		manualRowMove: true,
		manualColumnMove: true,
		stretchH: 'all',
		autoWrapRow: true,
		rowHeights: 30,
		defaultRowHeight: 100,
		minRows: 10,
		maxRows: 40,
		width: '100%',

		rowHeaders: true,
		colHeaders: true,
		autoColumnSize: {
			samplingRatio: 23
		},

		filters: true,
		manualRowResize: true,
		manualColumnResize: true,
		allowInsertRow: true,
		allowRemoveRow: true,
		columnHeaderHeight: 40,

		colWidths:  [40, 100, 50],
		rowHeights: 30,
		rowHeaderWidth: [44],
		minSpareRows: 1,
		hiddenColumns: {
			columns: [4],
			indicators: true
		},
		autoWrapCol: true,
		autoWrapRow: true,
		columns: [
				{
			type: 'text',
			data: 'code',
		},
		{
			type: 'text',
			data: 'description',
		},
		{
			type: 'numeric',
			data: 'rate',
			numericFormat: {
				pattern: '0,00',
			},
		},

		{
			type: 'text',
			data: 'basis',
			renderer: customDropdownRenderer,
			editor: "chosen",
			chosenOptions: {
				data: <?php echo json_encode($basis_value); ?>
			}

		},

		{
			type: 'text',
			data: 'id',
		},
		

		],

		colHeaders: [
		'<?php echo _l('salary_deduction_code'); ?>',
		'<?php echo _l('salary_deduction_name'); ?>',
		'<?php echo _l('salary_deduction_rate'); ?>',
		'<?php echo _l('salary_deduction_basis'); ?>',
		'<?php echo _l('id'); ?>',
		],

		data: dataObject_pu,
	});


})(jQuery);

function customDropdownRenderer(instance, td, row, col, prop, value, cellProperties) {
	"use strict";
	var selectedId;
	var optionsList = cellProperties.chosenOptions.data;
	
	if(typeof optionsList === "undefined" || typeof optionsList.length === "undefined" || !optionsList.length) {
		Handsontable.cellTypes.text.renderer(instance, td, row, col, prop, value, cellProperties);
		return td;
	}

	var values = (value + "").split("|");
	value = [];
	for (var index = 0; index < optionsList.length; index++) {

		if (values.indexOf(optionsList[index].id + "") > -1) {
			selectedId = optionsList[index].id;
			value.push(optionsList[index].label);
		}
	}
	value = value.join(", ");

	Handsontable.cellTypes.text.renderer(instance, td, row, col, prop, value, cellProperties);
	return td;
}

var purchase_value = purchase;

$('.add_insurance_list').on('click', function() {
	'use strict';
	
	var valid_contract = $('#insurance_list_hs').find('.htInvalid').html();

	if(valid_contract){
		alert_float('danger', "<?php echo _l('data_must_number') ; ?>");
	}else{

		$('input[name="insurance_list_hs"]').val(JSON.stringify(purchase_value.getData()));   
		$('#add_insurance_list').submit(); 

	}
});


</script>