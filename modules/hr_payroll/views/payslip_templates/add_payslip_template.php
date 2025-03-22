<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(false); ?>

<div id="wrapper">
	<div class="content">
		<div class="row">
			<div class="panel_s">

					<?php echo form_open_multipart(admin_url('hr_payroll/view_payslip_templates_detail'),array('id'=>'spreadsheet-test-form'));?>
				<div class="col-md-12">
					<div id="luckysheet" ></div>
				</div> 
					<?php 
					$payslip_template_id = '';
					if(isset($id)){
						$payslip_template_id = $id;
					}
					?>
					<input type="hidden" name="id" value="<?php echo new_html_entity_decode($payslip_template_id); ?>">

					<?php echo form_close(); ?>  
			</div>
		</div>

	</div>
</div>


</div>
</div>
</div>


<?php init_tail(); ?>
<?php require 'modules/hr_payroll/assets/js/payslip_templates/add_payslip_template_js.php'; ?>
</body>
</html>
