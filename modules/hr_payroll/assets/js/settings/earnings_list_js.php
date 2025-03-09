<script>
	var purchase;

	(function($) {
		"use strict";  


	<?php if(isset($earnings_list)){?>
		var dataObject_pu = <?php echo new_html_entity_decode($earnings_list); ?>;
	<?php }else{ ?>
		var dataObject_pu = [];
	<?php } ?>

	//hansometable for purchase
	var row_global;
	var hotElement1 = document.getElementById('earnings_list_hs');

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

		colWidths:  [40, 100, 50, 40],
		rowHeights: 30,
		rowHeaderWidth: [44],
		minSpareRows: 1,
		hiddenColumns: {
			columns: [3, 4, 5],
			indicators: true
		},

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
			type: 'text',
			data: 'short_name',
		},
		
		{
			type: 'numeric',
			data: 'taxable',
			numericFormat: {
				pattern: '0,00',
			},
		},

		{
			type: 'text',
			data: 'basis_type',
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
		'<?php echo _l('earning_code'); ?>',
		'<?php echo _l('earning_name'); ?>',
		'<?php echo _l('short_name'); ?>',
		'<?php echo _l('taxable').' %'; ?>',
		'<?php echo _l('earning_basis'); ?>',
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

$('.add_earnings_list').on('click', function() {
	'use strict';
	
	var valid_contract = $('#earnings_list_hs').find('.htInvalid').html();

	if(valid_contract){
		alert_float('danger', "<?php echo _l('data_must_number') ; ?>");
	}else{

		$('input[name="earnings_list_hs"]').val(JSON.stringify(purchase_value.getData()));   
		$('#add_earnings_list').submit(); 

	}
});


</script>