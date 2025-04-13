<script>

	$(function(){
		'use strict';
	     var ContractsServerParams = {
	      "memberid": "[name='memberid']",
	      "member_view": "[name='member_view']",
	     };
		var staff_payslip = $('table.table-staff_payslip');
		initDataTable(staff_payslip, admin_url+'hr_payroll/table_staff_payslip', [], [], ContractsServerParams,[2, 'desc']);

		 //hide first column
	    var hidden_columns = [];
	        $('.table-staff_payslip').DataTable().columns(hidden_columns).visible(false, false);
	});

    
    function member_view_payslip(payslip_detail_id) {
      "use strict";

      $("#contract_modal_wrapper").load("<?php echo admin_url('hr_payroll/hr_payroll/view_staff_payslip_modal'); ?>", {
           slug: 'view',
           payslip_detail_id: payslip_detail_id
      }, function() {
           if ($('.modal-backdrop.fade').hasClass('in')) {
                $('.modal-backdrop.fade').remove();
           }
           if ($('#staff_contract_modal').is(':hidden')) {
                $('#staff_contract_modal').modal({
                     show: true
                });
           }
      });

      init_selectpicker();
      $(".selectpicker").selectpicker('refresh');
  }

</script>
