<script>
	(function($) {
		"use strict";  

		init_selectpicker();
		$('.selectpicker').selectpicker('refresh');
		init_datepicker();
		$('#select_warehouse_modal').find('input[name="date_add"]').prop('readonly', true);
		appValidateForm($('#select_warehouse_modal'), {
			warehouse_id: 'required',
			date_add: 'required',
		});

	})(jQuery);
</script>