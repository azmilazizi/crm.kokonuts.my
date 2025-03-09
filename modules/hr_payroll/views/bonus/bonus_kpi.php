<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<script src="https://cdn.jsdelivr.net/npm/handsontable/dist/handsontable.full.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/handsontable/dist/handsontable.full.min.css" rel="stylesheet">

<div id="wrapper">
	<div class="content">

		
		<div class="row">
			<div class="col-md-12">
				<div class="panel_s">
					<div class="panel-body">

						<div class="row mb-5">
							<div class="col-md-12">
								<h4 class="no-margin" ><?php echo _l('hr_bonus_kpi') ?> </h4>
							</div>
							<div class="col-md-12">
								<hr class="hr-panel-heading">
							</div>
						</div>

						<div class="row mb-4">   
							<div class="col-md-12">
								<!-- filter -->
								<div class="row filter_by">

									<div class="col-md-2">
										<?php echo render_input('month_timesheets','month',date('Y-m'), 'month'); ?>   
									</div>

									<div class="col-md-3 leads-filter-column pull-left">
										<?php echo render_select('department_timesheets',$departments,array('departmentid', 'name'),'department',''); ?>
									</div>
									
									<div class="col-md-3 leads-filter-column pull-left">
										<div class="form-group">
											<label for="staff_timesheets" class="control-label"><?php echo _l('staff'); ?></label>
											<select name="staff_timesheets[]" class="form-control selectpicker" multiple="true" id="staff_timesheets" data-actions-box="true" data-width="100%" data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>" data-live-search="true"> 
												<?php foreach ($staffs as $key => $staff) { ?>

													<option value="<?php echo new_html_entity_decode($staff['staffid']); ?>" ><?php  echo new_html_entity_decode($staff['firstname'].' '.$staff['lastname']); ?></option>
												<?php } ?>
											</select>
										</div>
									</div>
									
								</div>
								<!-- filter -->
							</div>
							<hr class="hr-panel-heading">
						</div>
						<?php echo form_open(admin_url('hr_payroll/add_bonus_kpi'),array('id'=>'add_bonus_kpi')); ?>
						
						<div id="total_insurance_histtory" class="col-md-12">
							<div class="row">  
								<div class="hot handsontable htColumnHeaders" id="example">
								</div>
								<?php echo form_hidden('bonus_kpi_value'); ?>
								<?php echo form_hidden('month', date('m-Y')); ?>
								<?php echo form_hidden('allowance_commodity_fill_month'); ?>
								<?php echo form_hidden('latch'); ?>
								
							</div>
						</div>

						<div class="col-md-12">
							<div class="modal-footer">
								<?php if(has_permission('hrp_bonus_kpi', '', 'create') || has_permission('hrp_bonus_kpi', '', 'edit')){ ?>
									<button type="button" class="btn btn-info pull-right save_bonus_kpi mleft5 " onclick="save_bonus_kpi(this); return false;"><?php echo new_html_entity_decode($button_name); ?></button>
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
<?php require 'modules/hr_payroll/assets/js/bonus/bonus_js.php'; ?>

</body>
</html>
