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
								<h4 ><?php echo _l('hrp_income_tax') ?> </h4>
							</div>
						</div>
						<br>
						<div class="row mb-4">   
							<div class="col-md-12">
								<!-- filter -->
								<div class="row filter_by">

									<div class="col-md-2">
										<?php echo render_input('month_income_taxs','month',date('Y-m'), 'month'); ?>   
									</div>

									<div class="col-md-3 leads-filter-column pull-left">
										<?php echo render_select('department_income_taxs',$departments,array('departmentid', 'name'),'department',''); ?>
									</div>

									<div class="col-md-3 leads-filter-column pull-left">
										<div class="form-group">
											<label for="role_income_taxs" class="control-label"><?php echo _l('role'); ?></label>
											<select name="role_income_taxs[]" class="form-control selectpicker" multiple="true" id="role_income_taxs" data-actions-box="true" data-width="100%" data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>" data-live-search="true"> 
												<?php foreach ($roles as $key => $role) { ?>
													<option value="<?php echo new_html_entity_decode($role['roleid']); ?>" ><?php  echo new_html_entity_decode($role['name']); ?></option>
												<?php } ?>
											</select>
										</div>
									</div>

									<div class="col-md-3 leads-filter-column pull-left">

										<div class="form-group">
											<label for="staff_income_taxs" class="control-label"><?php echo _l('staff'); ?></label>
											<select name="staff_income_taxs[]" class="form-control selectpicker" multiple="true" id="staff_income_taxs" data-actions-box="true" data-width="100%" data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>" data-live-search="true"> 
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

						<?php echo form_open(admin_url('hr_payroll/add_manage_income_taxs'),array('id'=>'add_manage_income_taxs')); ?>
						
						<div class="col-md-12">
							<small><?php echo _l('handsontable_scroll_horizontally') ?></small>
						</div>
						<div id="total_insurance_histtory" class="col-md-12">
							<div class="row">  
								<div id="hrp_income_taxs_value" class="hot handsontable htColumnHeaders" >
								</div>
								<?php echo form_hidden('hrp_income_taxs_value'); ?>
								<?php echo form_hidden('month', date('m-Y')); ?>
								<?php echo form_hidden('income_taxs_fill_month'); ?>
								<?php echo form_hidden('department_income_taxs_filter'); ?>
								<?php echo form_hidden('staff_income_taxs_filter'); ?>
								<?php echo form_hidden('role_income_taxs_filter'); ?>

								<!-- rel_type synchronization or update value -->
								<?php echo form_hidden('hrp_income_taxs_rel_type'); ?>

							</div>
						</div>

						<div class="col-md-12">
							<div class="modal-footer">
								<?php if(has_permission('hr_payroll', '', 'create') || has_permission('hr_payroll', '', 'edit')){ ?>
									
									<a href="<?php echo admin_url('hr_payroll/import_xlsx_income_taxs'); ?>" class="hide btn mright5 btn-default pull-right">
										<?php echo _l('hr_job_p_export_excel'); ?>
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
<?php require 'modules/hr_payroll/assets/js/income_tax/income_tax_manage_js.php'; ?>

</body>
</html>
