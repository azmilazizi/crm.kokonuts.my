<?php $omni_show_products_by_department = get_option('omni_show_products_by_department'); ?>
<?php $omni_show_public_page = get_option('omni_show_public_page'); ?>
<?php $omni_sell_the_warehouse_assigned = get_option('omni_sell_the_warehouse_assigned'); ?>
<?php echo form_open(site_url('omni_sales/save_setting/default_setting'),array('id'=>'invoice-form','class'=>'_transaction_form invoice-form')); ?>
<?php 
$bill_header_pos = '';
$data_header_option = get_option('bill_header_pos');
if($data_header_option){
	$bill_header_pos = $data_header_option;      
}
$bill_footer_pos = '';
$data_footer_option = get_option('bill_footer_pos');
if($data_footer_option){
	$bill_footer_pos = $data_footer_option;      
}
?>
<hr>
<fieldset>
	<legend>POS</legend>
	<div class="row">
		<div class="col-md-6">
			<?php echo render_textarea('bill_header_pos','bill_header_pos',$bill_header_pos,array('row'=>3),array(),'','tinymce'); ?>
		</div>
		<div class="col-md-6">
			<?php echo render_textarea('bill_footer_pos','bill_footer_pos',$bill_footer_pos,array('row'=>3),array(),'','tinymce'); ?>
		</div>
	</div>
	<div class="form-group">
		<div class="checkbox checkbox-primary">
			<input type="checkbox" id="omni_show_products_by_department" name="omni_show_products_by_department" 
			<?php echo (($omni_show_products_by_department == 1) ? 'checked' : '') ?> value="1" >
			<label for="omni_show_products_by_department"><?php echo _l('omni_show_products_by_department') ?></label>
		</div>
	</div>	
	<div class="form-group">
		<div class="checkbox checkbox-primary">
			<input type="checkbox" id="omni_show_public_page" name="omni_show_public_page" 
			<?php echo (($omni_show_public_page == 1) ? 'checked' : '') ?> value="1" >
			<label for="omni_show_public_page"><?php echo _l('omni_show_public_page') ?></label>
		</div>
	</div>	
	<div class="form-group">
		<div class="checkbox checkbox-primary">
			<input type="checkbox" id="omni_sell_the_warehouse_assigned" name="omni_sell_the_warehouse_assigned" 
			<?php echo (($omni_sell_the_warehouse_assigned == 1) ? 'checked' : '') ?> value="1" >
			<label for="omni_sell_the_warehouse_assigned"><?php echo _l('omni_On_POS_staff_will_sell_according_to_the_Warehouse_assigned_on_the_Inventory_module') ?></label>
		</div>
	</div>
</fieldset>	 				
<hr>

<button class="btn btn-primary pull-right"><?php echo _l('save'); ?></button>
<?php echo form_close(); ?>
