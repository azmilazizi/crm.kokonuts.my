<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<?php init_head(); ?>
<div id="wrapper">
	<div class="content">
		<div class="row">
			<?php 
				$template_id = '';

			 ?>
			<?php if(isset($pdf_payslip_template)){
				$template_id = $pdf_payslip_template->id;
			 ?>
				<div class="member">
					<?php echo form_hidden('isedit'); ?>
					<?php echo form_hidden('contractid',$pdf_payslip_template->id); ?>
				</div>
			<?php } ?>
			
			<?php echo form_open_multipart(admin_url('hr_payroll/payslip_pdf_template/'.$template_id),array('class'=>'pdf-payslip-template-form','autocomplete'=>'off')); ?>


			<div class="col-md-12" >
				<div class="panel_s">
					
					<div class="panel-body">

						<div class="row mb-5">
							<div class="col-md-12">
								<h4 class="no-margin"><?php echo new_html_entity_decode($title) ?></h4>
							</div>
						</div>

						<!-- start tab -->
						<div class="modal-body">

							<div class="tab-content">
								<!-- start general infor -->
								<div class="row">
								<h5 class="h5-color"><?php echo _l('general_info'); ?></h5>
								<hr class="hr-color">

									<?php 

									$name = (isset($pdf_payslip_template) ? $pdf_payslip_template->name : ''); 
									$payslip_template_id = isset($pdf_payslip_template) ? $pdf_payslip_template->payslip_template_id: '';

									?>

									<?php $attrs = (isset($pdf_payslip_template) ? array() : array('autofocus'=>true)); ?>

									<div class="row">
									<div class="col-md-6">
										<?php echo render_input('name','pdf_payslip_template',$name,'text',$attrs); ?>   
									</div>

									<div  class="col-md-6">
										<div class="form-group">
											<label><small class="req text-danger">* </small><?php echo _l('payslip_template'); ?></label>
											<select name="payslip_template_id" id="payslip_template_id" data-live-search="true" class="selectpicker" data-actions-box="true" data-width="100%" data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>">
												<?php foreach($payslip_templates as $payslip_template) { 
													$selected = '';
													if(($payslip_template['id'] == $payslip_template_id)){
														$selected = 'selected';
													}

													?>
													<option value="<?php echo new_html_entity_decode($payslip_template['id']); ?>" <?php echo new_html_entity_decode($selected); ?>><?php echo new_html_entity_decode($payslip_template['templates_name']); ?></option>
												<?php } ?>
											</select>
										</div>
									</div>
									</div>

								</div>

								<div class="row">

									<h5 class="h5-color"><?php echo _l('pdf_payslip_template'); ?></h5>
									<hr class="hr-color">

									<div class="row">

										<div class="col-md-8">
											<?php if(!has_permission('hrp_setting','','edit')) { ?>
												<div class="alert alert-warning contract-edit-permissions">
													<?php echo _l('pdf_payslip_content_permission_edit_warning'); ?>
												</div>
											<?php } ?>
											<div class="tc-content<?php if(has_permission('hrp_setting','','edit')){echo ' editable';} ?>"
												style="border:1px solid #d2d2d2;min-height:70px; border-radius:4px;">
												<?php
												if((!isset($pdf_payslip_template) || empty($pdf_payslip_template->content) ) && has_permission('hrp_setting','','edit')){
													echo hooks()->apply_filters('new_pdf_payslip_default_content', '<span class="text-danger text-uppercase mtop15 editor-add-content-notice"> ' . _l('click_to_add_content') . '</span>');
												} else {
													echo $pdf_payslip_template->content;
												}
												?>
											</div>
										</div>

										<div class="col-md-4">
											<?php if(isset($pdf_payslip_merge_fields)){ ?>
												<p class="bold mtop10 text-right"><a href="#" onclick="slideToggle('.avilable_merge_fields'); return false;"><?php echo _l('available_merge_fields'); ?></a></p>
												<div class=" avilable_merge_fields mtop15 ">
													<ul class="list-group">
														<?php
														foreach($pdf_payslip_merge_fields as $field){

															foreach($field as $f){
																echo '<li class="list-group-item"><b>'.$f['name'].'</b>  <a href="javascript:void(0)" class="pull-right" onclick="insert_merge_field(this); return false">'.$f['key'].'</a></li>';
															}
														}
														?>
													</ul>
												</div>
											<?php } ?>
										</div>

									</div>


								</div>

							</div>
						</div>

						<div class="modal-footer">
							<a href="<?php echo admin_url('hr_payroll/setting?group=pdf_payslip_template'); ?>"  class="btn btn-default mr-2 "><?php echo _l('hr_close'); ?></a>
							<?php if(has_permission('hrp_setting', '', 'create') || has_permission('hrp_setting', '', 'edit')){ ?>
								<button type="submit" class="btn btn-info"><?php echo _l('submit'); ?></button>

							<?php } ?>
						</div>

					</div>
				</div>
			</div>

			<?php echo form_close(); ?>
		</div>

	</div>
	<?php init_tail(); ?>
	<?php 
	require('modules/hr_payroll/assets/js/settings/pdf_payslip_template_js.php');
	?>

</body>
</html>
