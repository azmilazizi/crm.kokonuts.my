<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<?php echo form_open_multipart(admin_url('hr_payroll/data_integration'), array('id'=>'data_integration')); ?>
<div class="row">
	<div class="col-md-12">
		<h4 class="no-margin font-bold h4-color" ><i class="fa fa-chain-broken" aria-hidden="true"></i> <?php echo _l('data_integration')?></h4>
		<hr class="hr-color" >
	</div>
</div>

<div class="row">
	<div class="col-md-12">
		<div class="form-group">
			<div class="checkbox checkbox-primary">
				<input  type="checkbox" id="integrated_hrprofile" name="integrated_hrprofile" <?php if(get_hr_payroll_option('integrated_hrprofile') == 1 ){ echo 'checked';} ?> value="integrated_hrprofile" <?php if($hr_profile_active == false){echo ' disabled';} ?>>
				<label for="integrated_hrprofile"><?php echo _l('integrated_hrprofile'); ?>

				<a href="#" class="pull-right display-block input_method"><i class="fa fa-question-circle i_tooltip" data-toggle="tooltip" title="" data-original-title="<?php echo new_html_entity_decode($hr_profile_title); ?>"></i></a>
			</label>
		</div>
	</div>
</div>

<div class="col-md-12 option-show-integra-hr-profile <?php if(get_hr_payroll_option('integrated_hrprofile') == 1){ echo '';}else{ echo 'hide';}  ?>">
	<div class="row">
		<div class="col-md-4">
			<div class="form-group">
				<div class="checkbox checkbox-primary">
					<input  type="checkbox" id="hrp_customize_staff_payslip_column" name="hrp_customize_staff_payslip_column" <?php if(get_hr_payroll_option('hrp_customize_staff_payslip_column') == 1 ){ echo 'checked';} ?> value="hrp_customize_staff_payslip_column" <?php if($hr_profile_active == false){echo ' disabled';} ?>>
					<label for="hrp_customize_staff_payslip_column"><?php echo _l('hrp_customize_staff_payslip_column'); ?>

					<a href="#" class="pull-right display-block input_method"><i class="fa fa-question-circle i_tooltip" data-toggle="tooltip" title="" data-original-title="<?php echo _l('hr_customize_staff_payslip_column_title'); ?>"></i></a>
				</label>
			</div>
		</div>
		</div>
	</div>

	<div class="col-md-12 option-show-customize-payslip-column <?php if(get_hr_payroll_option('hrp_customize_staff_payslip_column') == 1){ echo '';}else{ echo 'hide';}  ?>">
		<!-- 	Add assets    -->
		<div class="col-md-12 assets_wrap">
			<?php 
			$hrp_customize_staff_payslip_columns = hrp_customize_staff_payslip_columns();
			 ?>
			<?php if(isset($get_customize_payslip_columns) && count($get_customize_payslip_columns) > 0){
				foreach ($get_customize_payslip_columns as $p_key => $payslip_columns) {              
					?>
					<div id ="assets_emp" class="row">                            
						
						<div class="col-md-9 pt-2">
							<?php echo render_select('column_name[]', $hrp_customize_staff_payslip_columns, ['name', 'label'], '', $payslip_columns['column_name'], ['placeholder' => _l('column_name'), 'data-none-selected-text' => _l('column_name') ]); ?>
						</div>
						<div class="col-md-2 mt-2">
							<?php echo render_input('order_number[]', '', $payslip_columns['order_number'], 'number', ['placeholder' => _l('order_number'), 'data-none-selected-text' => _l('order_number')]); ?>
						</div>

						<div class="col-md-1 pl-0 pt-0" name="button_add">
							<button name="add_asset" class="btn mt-1 <?php if($p_key == 0){ echo 'new_assets_emp btn-primary' ;}else{echo 'remove_assets_emp btn-danger' ;} ?>  " data-ticket="true" type="button"><i class="fa <?php if($p_key == 0){ echo 'fa-plus' ;}else{ echo 'fa-minus' ;} ?> "></i></button>
						</div>
					</div>
				<?php } ?>
			<?php }else{ ?>
				<div id ="assets_emp" class="row">                           
					<div class="col-md-9 pt-2">
						<?php echo render_select('column_name[]', $hrp_customize_staff_payslip_columns, ['name', 'label'], '', '', ['placeholder' => _l('column_name'), 'data-none-selected-text' => _l('column_name') ]); ?>
					</div>
					<div class="col-md-2 mt-2">
						<?php echo render_input('order_number[]', '', '', 'number', ['placeholder' => _l('order_number'), 'data-none-selected-text' => _l('order_number')]); ?>
					</div>
					
					<div class="col-md-1 pl-0 pt-2" name="button_add">
						<button name="add_asset" class="btn new_assets_emp btn-primary mt-1" data-ticket="true" type="button"><i class="fa fa-plus"></i></button>
					</div>
				</div>
			<?php } ?>
		</div>
		<!-- 	End add assets    -->
	</div>

	</div>

</div>


<div class="row">
	<div class="col-md-12">
		<div class="form-group">
			<div class="checkbox checkbox-primary">
				<input type="checkbox" id="integrated_timesheets" name="integrated_timesheets" <?php if(get_hr_payroll_option('integrated_timesheets') == 1 ){ echo 'checked';} ?> value="integrated_timesheets" <?php if($timesheets_active == false){echo ' disabled';} ?>>
				<label for="integrated_timesheets"><?php echo _l('integrated_timesheets'); ?>

				<a href="#" class="pull-right display-block input_method"><i class="fa fa-question-circle i_tooltip" data-toggle="tooltip" title="" data-original-title="<?php echo new_html_entity_decode($timesheets_title); ?>"></i></a>
			</label>
		</div>
	</div>
</div>
</div>

<?php 
$attendance_types = hrp_attendance_type();
$actual_workday   = new_explode(',', get_hr_payroll_option('integration_actual_workday'));
$paid_leave       = new_explode(',', get_hr_payroll_option('integration_paid_leave'));
$unpaid_leave     = new_explode(',', get_hr_payroll_option('integration_unpaid_leave'));
?>

	<div class="row">
		<div class="col-md-4">
			<div class="form-group">
				<label for="standard_working_time" class="control-label clearfix"><small class="req text-danger">* </small><?php echo _l('standard_working_time_of_month'); ?><a href="#" class="pull-right display-block input_method"><i class="fa fa-question-circle i_tooltip" data-toggle="tooltip" title="" data-original-title="<?php echo _l('tooltip_standard_working_time'); ?>"></i></a></label>
				<input type="number" min="0" max="1000" id="standard_working_time" name="standard_working_time" class="form-control" value="<?php echo get_hr_payroll_option('standard_working_time'); ?>" required>
			</div>
		</div>
	</div>

<div class="col-md-12 option-show <?php if(get_hr_payroll_option('integrated_timesheets') == 1){ echo '';}else{ echo 'hide';}  ?>">


	<div class="row">
		<div class="col-md-4">
			<div class="form-group select-placeholder ">
				<label for="integration_actual_workday" class="control-label"><small class="req text-danger">* </small><?php echo _l('integration_actual_workday'); ?><a href="#" class="pull-right display-block input_method"><i class="fa fa-question-circle i_tooltip" data-toggle="tooltip" title="" data-original-title="<?php echo _l('tooltip_actual_workday'); ?>"></i></a></label>
				<select name="integration_actual_workday[]" id="integration_actual_workday" multiple="true" class="form-control selectpicker" data-actions-box="true" data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>" required>
					<?php foreach ($actual_workday_type as $key => $value) { ?>

						<?php 
						$selected ='';
						if(in_array($key, $actual_workday)){
							$selected .= ' selected';
						}
						?>
						<option value="<?php echo new_html_entity_decode($key); ?>" <?php echo  new_html_entity_decode($selected)?>><?php  echo new_html_entity_decode($value); ?></option>

					<?php } ?>
				</select>
			</div>
		</div>
	</div>


	<div class="row">
		<div class="col-md-4">
			<div class="form-group">
				<label for="integration_paid_leave" class="control-label"><small class="req text-danger">* </small><?php echo _l('integration_paid_leave'); ?><a href="#" class="pull-right display-block input_method"><i class="fa fa-question-circle i_tooltip" data-toggle="tooltip" title="" data-original-title="<?php echo _l('tooltip_paid_leave'); ?>"></i></a></label>
				<select name="integration_paid_leave[]" class="form-control selectpicker" multiple="true" id="integration_paid_leave" data-actions-box="true" data-width="100%" data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>" required> 
					<?php foreach ($paid_leave_type as $key => $value) { ?>

						<?php 
						$selected ='';
						if(in_array($key, $paid_leave)){
							$selected .= ' selected';
						}
						?>
						<option value="<?php echo new_html_entity_decode($key); ?>" <?php echo new_html_entity_decode($selected); ?>><?php  echo new_html_entity_decode($value); ?></option>
					<?php } ?>
				</select>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-4">
			<div class="form-group">
				<label for="integration_unpaid_leave" class="control-label"><small class="req text-danger">* </small><?php echo _l('integration_unpaid_leave'); ?><a href="#" class="pull-right display-block input_method"><i class="fa fa-question-circle i_tooltip" data-toggle="tooltip" title="" data-original-title="<?php echo _l('tooltip_unpaid_leave'); ?>"></i></a></label>
				<select name="integration_unpaid_leave[]" class="form-control selectpicker" multiple="true" id="integration_unpaid_leave" data-actions-box="true" data-width="100%" data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>" required> 
					<?php foreach ($unpaid_leave_type as $key => $value) { ?>

						<?php 
						$selected ='';
						if(in_array($key, $unpaid_leave)){
							$selected .= ' selected';
						}
						?>
						<option value="<?php echo new_html_entity_decode($key); ?>" <?php echo  $selected?>><?php echo new_html_entity_decode($value); ?></option>

					<?php } ?>
				</select>
			</div>
		</div>
	</div>
</div>


<div class="row">
	<div class="col-md-12">
		<div class="form-group">
			<div class="checkbox checkbox-primary">
				<input type="checkbox" id="integrated_commissions" name="integrated_commissions" <?php if(get_hr_payroll_option('integrated_commissions') == 1 ){ echo 'checked';} ?> value="integrated_commissions" <?php if($commissions_active == false){echo ' disabled';} ?>>
				<label for="integrated_commissions"><?php echo _l('integrated_commissions'); ?>

				<a href="#" class="pull-right display-block input_method"><i class="fa fa-question-circle i_tooltip" data-toggle="tooltip" title="" data-original-title="<?php echo new_html_entity_decode($commissions_title); ?>"></i></a>
			</label>
		</div>
	</div>
</div>
</div>

<div class="row">
	<div class="col-md-12">
		<div class="modal-footer">
			<?php if(is_admin()){ ?>
				<button type="submit" class="btn btn-info"><?php echo _l('submit'); ?></button>
			<?php } ?>
		</div>
	</div>
</div>
<?php echo form_close(); ?>


<div class="clearfix"></div>

</body>
</html>


