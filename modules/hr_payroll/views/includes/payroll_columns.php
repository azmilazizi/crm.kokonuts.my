<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div> 
	<div class="row">
		<div class="col-md-12">
			<h4 class="h4-color no-margin"><i class="fa fa-database" aria-hidden="true"></i> <?php echo _l('payroll_columns'); ?></h4>
		</div>
	</div>
	<hr class="hr-color">
	
	<div class="row">
		<div class="col-md-4">
			<div class="_buttons">
				<?php if(is_admin() || has_permission('hrp_setting','','create')) {?>
					<a href="#" onclick="new_column_type(); return false;" class="btn btn-info pull-left display-block" >
						<?php echo _l('add'); ?>
					</a>
				<?php } ?>
			</div>
		</div>
	</div>

	<br>

	<div class="clearfix"></div>
	<table class="table dt-table">
		<thead>
			<tr>
				<th><?php echo _l('order'); ?></th>
				<th><?php echo _l('column_name_lable'); ?></th>
				<th><?php echo _l('taking_method_lable'); ?></th>
				<th><?php echo _l('staff_id_created'); ?></th>
				<th><?php echo _l('date_created'); ?></th>
				<th><?php echo _l('actions'); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach($payroll_column_value as $value){ ?>
				<?php 
				$_data = '<a href="' . admin_url('staff/profile/' . $value['staff_id_created']) . '">' . staff_profile_image($value['staff_id_created'], [
					'staff-profile-image-small',
				]) . '</a>';
				$_data .= ' <a href="' . admin_url('staff/profile/' . $value['staff_id_created']) . '">' . get_staff_full_name($value['staff_id_created']) . '</a>';
				?>
				<tr>
					<td><?php echo new_html_entity_decode($value['order_display']); ?></td>
					<td><?php echo new_html_entity_decode($value['column_key']); ?></td>
					<td><?php echo new_html_entity_decode($value['taking_method'] == 'caculator' ? 'formular' : $value['taking_method']); ?></td>
					<td><?php echo new_html_entity_decode($_data); ?></td>
					<td><?php echo new_html_entity_decode(_dt($value['date_created'])); ?></td>
					<td>
						
						<?php if(is_admin() || has_permission('hrp_setting','','edit')) {?>
							<a href="#" onclick="edit_column_type(this,<?php echo new_html_entity_decode($value['id']); ?>); return false"  class="btn btn-default btn-icon" data-toggle="sidebar-right" data-target=".insurance_type_modal-edit-modal"><i class="fa-regular fa-pen-to-square"></i></a>
						<?php } ?>

						<?php if(is_admin() || has_permission('hrp_setting','','delete')) {?>
							<?php if($value['is_edit'] != 'no'){ ?>
								<a href="<?php echo admin_url('hr_payroll/delete_payroll_column_setting/'.$value['id']); ?>" class="btn btn-danger btn-icon _delete"><i class="fa fa-remove"></i></a>
							<?php } ?>

						<?php } ?>
					</td>
				</tr>
			<?php } ?>
		</tbody>
	</table>       
	
	<hr/>

	<!-- add insurance type -->
	<div class="modal" id="insurance_type_modal" tabindex="-1" role="dialog">
		<div class="modal-dialog popup-with">
			<?php echo form_open_multipart(admin_url('hr_payroll/payroll_column'), array('id'=>'add_payroll_column')); ?>

			<div class="modal-content">
				<div class="modal-header">
					<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>

					<h4 class="modal-title">
						<span class="edit-title"><?php echo _l('edit_payroll_column'); ?></span>
						<span class="add-title"><?php echo _l('new_payroll_column'); ?></span>
					</h4>

				</div>

				<div class="modal-body">
					<div id="additional_payroll_column"></div>

					<div class="row">
						<div class="col-md-12">
							<div class="col-md-6"> 

								<div class="form-group">
									<label for="taking_method" class="control-label"><?php echo _l('taking_method_lable'); ?></label>
									<select name="taking_method" id="taking_method" class="selectpicker"  data-live-search="true" data-width="100%" data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>">
										
									</select>
								</div>
							</div>
							<div class="col-md-6">
								<?php echo render_select('function_name',[],[],'function_name_lable','',[],[],'function_name_hide hide'); ?>
							</div>
							
						</div>
					</div>
					<div class="row">
						<div class="col-md-12">
							<div class="col-md-6">
								<?php echo render_input('column_key','column_name_lable','','text'); ?>
							</div>            
							<div class="col-md-6">
								<?php echo render_input('function_name','column_key_lable','','text'); ?>
							</div>            
						</div>
					</div>
					<div class="row">
						<div class="col-md-12">
							<div class="col-md-12">
								<?php echo render_textarea('description','description_lable',''); ?>
							</div>
						</div>
					</div>
					<div class="row">
						<div class="col-md-12">
							<div class="col-md-12">
								<?php echo render_input('order_display', 'order_display_label', $order_display_in_paylip, 'number'); ?>
							</div>
						</div>
					</div>
					<div class="row hide">
						<div class="col-md-12">
							<div class="col-md-12">
								<div class="form-group">
									<div class="checkbox checkbox-primary">
										<input  type="checkbox" id="display_with_staff" name="display_with_staff" value="display_with_staff" checked="true">

										<label for="display_with_staff"><?php echo _l('display_with_staff'); ?><small > </small>
										</label>
									</div>
								</div>
							</div>  
						</div>  
					</div> 
					<?php echo form_hidden('value_related_to'); ?>

				</div>

				<div class="modal-footer">
					<button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
					<?php if(has_permission('hrp_setting','','create') || has_permission('hrp_setting', '', 'edit')){ ?>
						<button type="button" class="btn btn-info payroll_column_submit"><?php echo _l('submit'); ?></button>
					<?php } ?>
				</div>
			</div>
			<?php echo form_close(); ?>
		</div>
	</div> 

</body>
</html>
