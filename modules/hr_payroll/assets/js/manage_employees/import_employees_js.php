<script src="<?php echo base_url('assets/plugins/jquery-validation/additional-methods.min.js'); ?>"></script>
<script>

	function uploadfilecsv(event){
		'use strict';

		if(($("#file_csv").val() != '') && ($("#file_csv").val().split('.').pop() == 'xlsx')){
			var formData = new FormData();
			formData.append("file_csv", $('#file_csv')[0].files[0]);
			formData.append("csrf_token_name", $('input[name="csrf_token_name"]').val());
			formData.append("leads_import", $('input[name="leads_import"]').val());

			//show box loading
			var html = '';
			html += '<div class="Box">';
			html += '<span>';
			html += '<span></span>';
			html += '</span>';
			html += '</div>';
			$('#box-loading').html(html);
			$(event).attr( "disabled", "disabled" );

			$.ajax({ 
				url: admin_url + 'hr_payroll/import_employees_excel', 
				method: 'post', 
				data: formData, 
				contentType: false, 
				processData: false

			}).done(function(response) {
				response = JSON.parse(response);
				//hide boxloading
				$('#box-loading').html('');
				$(event).removeAttr('disabled')

				$("#file_csv").val(null);
				$("#file_csv").change();
				$(".panel-body").find("#file_upload_response").html();

				if($(".panel-body").find("#file_upload_response").html() != ''){
					$(".panel-body").find("#file_upload_response").empty();
				};

				if(response.total_rows){
					$( "#file_upload_response" ).append( "<h4><?php echo _l("_Result") ?></h4><h5><?php echo _l('import_line_number') ?> :"+response.total_rows+" </h5>" );
				}
				if(response.total_row_success){
					$( "#file_upload_response" ).append( "<h5><?php echo _l('import_line_number_success') ?> :"+response.total_row_success+" </h5>" );
				}
				if(response.total_row_false){
					$( "#file_upload_response" ).append( "<h5><?php echo _l('import_line_number_failed') ?> :"+response.total_row_false+" </h5>" );
				}
				if(response.total_row_false > 0)
				{
					$( "#file_upload_response" ).append( '<a href="'+response.site_url+response.filename+'" class="btn btn-warning"  ><?php echo _l('hr_download_file_error') ?></a>' );
				}
				if(response.total_rows < 1){
					alert_float('warning', response.message);
				}
			});
			return false;
		}else if($("#file_csv").val() != ''){
			alert_float('warning', "<?php echo _l('_please_select_a_file') ?>");
		}

	}

	function dowload_contract_excel(){
		'use strict';

		var formData = new FormData();
		formData.append("csrf_token_name", $('input[name="csrf_token_name"]').val());
		formData.append("month_employees", $('input[name="month_employees"]').val());
		$.ajax({ 
			url: admin_url + 'hr_payroll/create_employees_sample_file', 
			method: 'post', 
			data: formData, 
			contentType: false, 
			processData: false
		}).done(function(response) {
			response = JSON.parse(response);
			if(response.success == true){

				alert_float('success', "<?php echo _l("create_attendance_file_success") ?>");

				$('.staff_contract_download').removeClass('hide');
				$('.staff_contract_create').addClass('hide');

				$('.staff_contract_download').attr({target: '_blank', 
					href  : site_url +response.filename});

			}else{
				alert_float('warning', "<?php echo _l("create_attendance_file_false") ?>");
			}
		});
	}

	$('#month_employees').on('change', function() {
		'use strict';

		$('.staff_contract_download').addClass('hide');
		$('.staff_contract_create').removeClass('hide');

	});
</script>