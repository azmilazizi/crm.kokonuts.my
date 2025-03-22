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
								<h4 class="no-margin"><?php echo _l('hrp_commission_manage') ?> </h4>
							</div>
						</div>
						<br>
						<div class="row mb-4">   
							<div class="col-md-12">
								<!-- filter -->
								<div class="row filter_by">

									<div class="col-md-2">
										<?php echo render_input('month_commissions','month',date('Y-m'), 'month'); ?>   
									</div>

									<div class="col-md-3 leads-filter-column pull-left">
										<?php echo render_select('department_commissions',$departments,array('departmentid', 'name'),'department',''); ?>
									</div>

									<div class="col-md-3 leads-filter-column pull-left">
										<div class="form-group">
											<label for="role_commissions" class="control-label"><?php echo _l('role'); ?></label>
											<select name="role_commissions[]" class="form-control selectpicker" multiple="true" id="role_commissions" data-actions-box="true" data-width="100%" data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>" data-live-search="true"> 
												<?php foreach ($roles as $key => $role) { ?>
													<option value="<?php echo new_html_entity_decode($role['roleid']); ?>" ><?php  echo new_html_entity_decode($role['name']); ?></option>
												<?php } ?>
											</select>
										</div>
									</div>

									<div class="col-md-3 leads-filter-column pull-left">

										<div class="form-group">
											<label for="staff_commissions" class="control-label"><?php echo _l('staff'); ?></label>
											<select name="staff_commissions[]" class="form-control selectpicker" multiple="true" id="staff_commissions" data-actions-box="true" data-width="100%" data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>" data-live-search="true"> 
												<?php foreach ($staffs as $key => $staff) { ?>

													<option value="<?php echo new_html_entity_decode($staff['staffid']); ?>" ><?php  echo new_html_entity_decode($staff['firstname'].' '.$staff['lastname']); ?></option>
												<?php } ?>
											</select>
										</div>

									</div>

								</div>
								<!-- filter -->
							</div>
							
						</div>

						<div class="row">
							<div class="col-md-12">
								<hr class="hr-color">
							</div>
						</div>

						<?php echo form_open(admin_url('hr_payroll/add_manage_commissions'),array('id'=>'add_manage_commissions')); ?>
						
						<div class="col-md-12">
							<small><?php echo _l('handsontable_scroll_horizontally') ?></small>
						</div>
						<div id="total_insurance_histtory" class="col-md-12">
							<div class="row">  
								<div id="hrp_commissions_value" class="hot handsontable htColumnHeaders" >
								</div>
								<?php echo form_hidden('hrp_commissions_value'); ?>
								<?php echo form_hidden('month', date('m-Y')); ?>
								<?php echo form_hidden('commissions_fill_month'); ?>
								<?php echo form_hidden('department_commissions_filter'); ?>
								<?php echo form_hidden('staff_commissions_filter'); ?>
								<?php echo form_hidden('role_commissions_filter'); ?>

								<!-- rel_type synchronization or update value -->
								<?php echo form_hidden('hrp_commissions_rel_type'); ?>

							</div>
						</div>

						<div class="col-md-12">
							<div class="modal-footer">
								<?php if(has_permission('hrp_commission', '', 'create') || has_permission('hrp_commission', '', 'edit')){ ?>
									<button type="button" class="btn btn-info pull-right save_manage_commissions mleft5 "><?php echo new_html_entity_decode($button_name); ?></button>
									
									<?php if(hrp_get_commission_status() == 'commission'){ ?>
										<button type="button" class="btn btn-info pull-right save_synchronized mleft5 " onclick="save_synchronized(this); return false;" title="<?php echo _l('synchronized_commission_title'); ?>"><?php echo _l('hrp_synchronized'); ?> <i class="fa fa-question-circle i_tooltip" data-toggle="tooltip" title="" data-original-title="<?php echo _l('synchronized_commission_title'); ?>"></i></button>

									<?php } ?>
									
									<a href="<?php echo admin_url('hr_payroll/import_xlsx_commissions'); ?>" class=" btn mright5 btn-default pull-right">
										<?php echo _l('hrp_import_excel'); ?>
									</a>
								<?php } ?>
								
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
<?php require 'modules/hr_payroll/assets/js/commissions/commissions_manage_js.php'; ?>

</body>
</html>
