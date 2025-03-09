<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
	<div class="content">
		<div class="row">
			<div class="col-md-12" id="small-table">
				<div class="panel_s">
					<div class="panel-body">
						<?php echo form_hidden('internal_id',$internal_id); ?>
						<div class="row">
							<div class="col-md-12 ">
								<h4 class="no-margin font-bold"><i class="fa fa-shopping-basket" aria-hidden="true"></i> <?php echo _l($title); ?></h4>
								<hr />
							</div>
						</div>
						<div class="row">    
							<div class="_buttons col-md-3">

								<?php if (has_permission('hrp_payslip_template', '', 'create') || is_admin()) { ?>
									<a href="#" onclick="new_payslip_template(); return false;"class="btn btn-info pull-left mright10 display-block">
										<?php echo _l('_new'); ?>
									</a>
								<?php } ?>

							</div>

						</div>

						<br/>
						<?php render_datatable(array(
							_l('id'),
							_l('templates_name'),
							_l('staff_id_created'),
							_l('date_created'),
						),'payslip_template_table'); ?>
						

					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- add insurance type -->
<div class="modal" id="payslip_template_modal" tabindex="-1" role="dialog">
	<div class="modal-dialog popup-with modal-dialog-with">
		<?php echo form_open_multipart(admin_url('hr_payroll/payslip_template'), array('id'=>'add_payslip_template')); ?>

		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>

				<h4 class="modal-title">
					<span class="edit-title"><?php echo _l('edit_payslip_template'); ?></span>
					<span class="add-title"><?php echo _l('new_payslip_template'); ?></span>
				</h4>

			</div>

			<div class="modal-body">
				<div id="additional_payslip_template"></div>
				<div id="additional_payslip_column"></div>

				<div class="row">
					<div class="col-md-12">
						<div class="col-md-12">
							<label class="payslip-template-lable"><?php echo _l('except_staff_note'); ?></label>
						</div>
					</div>
					<div class="col-md-12">
						<div class="col-md-12">
							<?php echo render_input('templates_name','templates_name','','text'); ?>
						</div>            
					</div>
				</div>
				<div class="row">
					<div class="col-md-12">
						<div class="col-md-6 hide"> 

							<div class="form-group">
								<label for="payslip_id_copy" class="control-label"><?php echo _l('payslip_id_copy_lable'); ?></label>
								<select name="payslip_id_copy" id="payslip_id_copy" class="selectpicker"  data-live-search="true" data-width="100%" data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>">

								</select>
							</div>
						</div>
						<div class="col-md-12">
							<div class="form-group">
								<label for="payslip_columns" class="control-label"><?php echo _l('payslip_columns_lable'); ?></label>
								<select name="payslip_columns[]" id="payslip_columns" class="selectpicker"  data-live-search="true" data-width="100%" multiple="true" data-actions-box="true" data-live-search="true" data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>">

								</select>
							</div>
						</div>
					</div>
				</div>

				<div class="row">
					<div class="col-md-12">
						<div class="col-md-6">
							<div class="form-group">
								<label for="department_id" class="control-label"><?php echo _l('staff_departments'); ?></label>
								<select name="department_id[]" class="form-control selectpicker" multiple="true" id="department_id" data-actions-box="true" data-width="100%" data-none-selected-text="<?php echo _l('dropdown_all_selected_tex'); ?>" data-live-search="true"> 
									<?php foreach ($departments as $department_key => $department) { ?>
										<option value="<?php echo new_html_entity_decode($department['departmentid']); ?>" ><?php  echo new_html_entity_decode($department['name']); ?></option>
									<?php } ?>
								</select>
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-group">
								<label for="role_employees" class="control-label"><?php echo _l('role'); ?></label>
								<select name="role_employees[]" class="form-control selectpicker" multiple="true" id="role_employees" data-actions-box="true" data-width="100%" data-none-selected-text="<?php echo _l('dropdown_all_selected_tex'); ?>" data-live-search="true"> 
									<?php foreach ($roles as $key => $role) { ?>
										<option value="<?php echo new_html_entity_decode($role['roleid']); ?>" ><?php  echo new_html_entity_decode($role['name']); ?></option>
									<?php } ?>
								</select>

							</div>
						</div>
					</div>
				</div>
				<div class="row">
					<div class="col-md-12">
						<div class="col-md-6">
							<div class="form-group">
								<label for="staff_employees" class="control-label"><?php echo _l('staff'); ?></label>
								<select name="staff_employees[]" class="form-control selectpicker" multiple="true" id="staff_employees" data-actions-box="true" data-width="100%" data-none-selected-text="<?php echo _l('dropdown_all_selected_tex'); ?>" data-live-search="true"> 
									<?php foreach ($staffs as $key => $staff) { ?>

										<option value="<?php echo new_html_entity_decode($staff['staffid']); ?>" ><?php  echo new_html_entity_decode($staff['firstname'].' '.$staff['lastname']); ?></option>
									<?php } ?>
								</select>
							</div>
						</div>

						<div class="col-md-6">
							<div class="form-group">
								<label for="except_staff" class="control-label"><?php echo _l('except_staff'); ?></label>
								<select name="except_staff[]" class="form-control selectpicker" multiple="true" id="except_staff" data-actions-box="true" data-width="100%" data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>" data-live-search="true"> 
									<?php foreach ($staffs as $key => $staff) { ?>

										<option value="<?php echo new_html_entity_decode($staff['staffid']); ?>" ><?php  echo new_html_entity_decode($staff['firstname'].' '.$staff['lastname']); ?></option>
									<?php } ?>
								</select>
							</div>
						</div>
					</div>
				</div>

			</div>

			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
				<button type="button" class="btn btn-info payslip_template_checked"><?php echo _l('submit'); ?></button>
			</div>
		</div>
		<?php echo form_close(); ?>
	</div>
</div> 

<?php init_tail(); ?>
<?php require 'modules/hr_payroll/assets/js/payslip_templates/payslip_template_manage_js.php';?>
</body>
</html>
