<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(false); ?>

<div id="wrapper">

	<div class="content">
		<div class="panel_s">
			<?php echo form_open_multipart(admin_url('hr_payroll/view_payslip_detail'),array('id'=>'spreadsheet-test-form'));?>
			<div id="luckysheet"></div>
			<?php 
			$payslip_template_id = '';
			if(isset($id)){
				$payslip_template_id = $id;
			}
			?>
			<input type="hidden" name="id" value="<?php echo new_html_entity_decode($payslip_template_id); ?>">

			<input type="hidden" name="payslip_data">
			<input type="hidden" name="name">
			<input type="hidden" name="id">
			<input type="hidden" name="image_flag">

			<?php echo form_close(); ?>   
		</div>
	</div>
	<!-- box loading -->
	<div id="box-loading"></div>
</div>


</div>
</div>
</div>


<?php init_tail(); ?>

<?php require 'modules/hr_payroll/assets/js/payslips/payslip_view_own_js.php'; ?>
</body>
</html>
