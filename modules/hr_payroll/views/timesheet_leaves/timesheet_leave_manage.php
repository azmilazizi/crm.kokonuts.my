<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<div id="wrapper">
	<div class="content">

		<div class="row">
			<div class="col-md-12">
				<div class="panel_s">
					<div class="panel-body">

						<div class="row mb-5">
							<div class="col-md-12">
								<h4 class="no-margin"><?php echo _l('hr_manage_timesheet_leaves') ?> </h4>
							</div>
							<div class="col-md-12">
								<hr class="hr">
							</div>
						</div>

						<div class="row mb-4">   
							<div class="col-md-12">
								<!-- filter -->
								<div class="row filter_by">

									<div class="col-md-2">
										<?php if($current_month){ ?>
											<?php echo render_input('month_attendance','month', date('Y-m', strtotime($current_month)), 'month'); ?>   
										<?php }else{ ?>
											<?php echo render_input('month_attendance','month',date('Y-m'), 'month'); ?>   
										<?php } ?>
									</div>

									<div class="col-md-3 leads-filter-column pull-left">
										<?php echo render_select('department_attendance',$departments,array('departmentid', 'name'),'department',''); ?>
									</div>

									<div class="col-md-3 leads-filter-column pull-left">
										<div class="form-group">
											<label for="role_attendance" class="control-label"><?php echo _l('role'); ?></label>
											<select name="role_attendance[]" class="form-control selectpicker" multiple="true" id="role_attendance" data-actions-box="true" data-width="100%" data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>" data-live-search="true"> 
												<?php foreach ($roles as $key => $role) { ?>
													<option value="<?php echo new_html_entity_decode($role['roleid']); ?>" ><?php  echo new_html_entity_decode($role['name']); ?></option>
												<?php } ?>
											</select>
										</div>
									</div>

									<div class="col-md-3 leads-filter-column pull-left">

										<div class="form-group">
											<label for="staff_attendance" class="control-label"><?php echo _l('staff'); ?></label>
											<select name="staff_attendance[]" class="form-control selectpicker" multiple="true" id="staff_attendance" data-actions-box="true" data-width="100%" data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>" data-live-search="true"> 
												<?php foreach ($staffs as $key => $staff) { ?>

													<option value="<?php echo new_html_entity_decode($staff['staffid']); ?>" ><?php  echo new_html_entity_decode($staff['firstname'].' '.$staff['lastname']); ?></option>
												<?php } ?>
											</select>
										</div>

									</div>
								

								</div>
								<!-- filter -->
							</div>
							<div class="col-md-12">
								<hr class="hr-color">
							</div>
						</div>

						<div class="row mbot10 no-mtop">
							<div class="col-md-12 timesheet-leave-line-suggestion">
								<button type="button" data-toggle="tooltip" data-placement="top" data-original-title="<?php echo _l('pl_x_timesheet_leave'); ?>" class="btn" >PL</button>
								<button type="button" data-toggle="tooltip" data-placement="top" data-original-title="<?php echo _l('upl_x_timesheet_leave'); ?>" class="btn" >UPL</button>
								<div class="clearfix"></div>
							</div>
						</div>

						<?php echo form_open(admin_url('hr_payroll/add_timesheets_leave'),array('id'=>'add_attendance')); ?>
						<div class="row">
							<div class="col-md-12">
								<small><?php echo _l('handsontable_scroll_horizontally') ?></small><br>
								<small><strong><?php echo _l('hrp_timesheet_leave_doc') ?></strong></small>
							</div>
						</div>
						<div id="total_insurance_histtory" class="col-md-12">
							<div class="row">  
								<div class="hot handsontable htColumnHeaders" id="hrp_employees_value">
								</div>
								<?php echo form_hidden('hrp_attendance_value'); ?>
								<?php echo form_hidden('month', date('m-Y')); ?>
								<?php echo form_hidden('attendance_fill_month'); ?>
								<?php echo form_hidden('department_attendance_filter'); ?>
								<?php echo form_hidden('staff_attendance_filter'); ?>
								<?php echo form_hidden('role_attendance_filter'); ?>

								<!-- rel_type synchronization or update value -->
								<?php echo form_hidden('hrp_attendance_rel_type'); ?>

							</div>
						</div>

						<div class="col-md-12">
							<div class="modal-footer">
								<?php if(has_permission('hrp_attendance', '', 'create') || has_permission('hrp_attendance', '', 'edit')){ ?>

								<button type="button" class="btn btn-info pull-right save_attendance mleft5 " onclick="save_attendance(this); return false;"><?php echo new_html_entity_decode($button_name); ?></button>

								<button type="button" class="btn btn-info pull-right save_synchronized mleft5 " onclick="timesheet_leave_calculation(this); return false;"><?php echo _l('attendance_calculation'); ?></button>

							<?php } ?>
							<a href="<?php echo admin_url('hr_payroll/manage_attendance'); ?>" class=" btn mright5 btn-default pull-right">
								<?php echo _l('hrp_back'); ?>
							</a>
							</div>
						</div>
					</div>
				</div>
			</div>

			<?php echo form_close(); ?>

		</div>

	</div>
</div>

</div>
</div>
</div>


<?php init_tail(); ?>
<?php require 'modules/hr_payroll/assets/js/timesheet_leaves/timesheet_leave_manage_js.php'; ?>

</body>
</html>
