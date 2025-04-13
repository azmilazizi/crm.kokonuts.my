<script>
	var purchase;

	(function($) {
		"use strict";  


	<?php if(isset($income_tax_rebates)){?>
		var dataObject_pu = <?php echo new_html_entity_decode($income_tax_rebates); ?>;
	<?php }else{ ?>
		var dataObject_pu = [];
	<?php } ?>

	//hansometable for purchase
	var row_global;
	var hotElement1 = document.getElementById('incometax_rebates_hs');

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

		colWidths:  [50, 120],
		rowHeights: 30,
		rowHeaderWidth: [44],
		minSpareRows: 1,
		hiddenColumns: {
			columns: [4],
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
			type: 'numeric',
			data: 'value',
			numericFormat: {
				pattern: '0,00',
			},
		},

		{
			type: 'numeric',
			data: 'total',
			numericFormat: {
				pattern: '0,00',
			},
		},
		{
			type: 'text',
			data: 'id',
		},
		

		],

		colHeaders: [
		'<?php echo _l('rebates_code'); ?>',
		'<?php echo _l('rebates_name'); ?>',
		'<?php echo _l('rebates_value'); ?>',
		'<?php echo _l('rebates_total'); ?>',
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
purchase.addHook('afterChange', function(changes, src) {
	"use strict";

	if(changes !== null){
		changes.forEach(([row, col, prop, oldValue, newValue]) => {

			//update total column when change value column
			if(col == 'value'){
				if(row == 0){
					purchase.setDataAtCell(row,3, purchase.getDataAtCell(row, 2).toFixed(2));
				
				}else{
					purchase.setDataAtCell(row,3, (parseInt(purchase.getDataAtCell(row, 2)) + parseInt(purchase.getDataAtCell(row-1, 3)) ).toFixed(2));
				}
			}

			//update when goback change above column
			if(col == 'total' && (purchase.getDataAtCell(row+1, 3) != '') && (purchase.getDataAtCell(row+1, 3) != 0) && (purchase.getDataAtCell(row+1, 3) != null)){
				purchase.setDataAtCell(row+1,3, (parseInt(purchase.getDataAtCell(row, 3)) + parseInt(purchase.getDataAtCell(row+1, 2)) ).toFixed(2));
			}

		});
	}
});

$('.add_incometax_rebates').on('click', function() {
	'use strict';
	
	var valid_contract = $('#incometax_rebates_hs').find('.htInvalid').html();

	if(valid_contract){
		alert_float('danger', "<?php echo _l('data_must_number') ; ?>");
	}else{

		$('input[name="incometax_rebates_hs"]').val(JSON.stringify(purchase_value.getData()));   
		$('#add_incometax_rebates').submit(); 

	}
});


</script>