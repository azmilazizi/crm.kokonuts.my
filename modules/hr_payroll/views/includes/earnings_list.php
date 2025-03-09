<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div> 

	<?php echo form_open_multipart(admin_url('hr_payroll/setting_earnings_list'), array('id'=>'add_earnings_list')); ?>

	<div class="row">
		<div class="col-md-12">
				<h4 class="h4-color no-margin"><i class="fa fa-dollar" aria-hidden="true"></i> <?php echo _l('earnings_list'); ?></h4>
		</div>
	</div>
	<hr class="hr-color">
	<div class="form"> 
		<div id="earnings_list_hs" class="hot handsontable htColumnHeaders">
		</div>
		<?php echo form_hidden('earnings_list_hs'); ?>
	</div>
	<div class="row">
		<div class="col-md-12">
			<div class="modal-footer">
				<?php if(has_permission('hrp_setting', '', 'create') || has_permission('hrp_setting', '', 'edit')){ ?>
					<a href="#"class="btn btn-info pull-right display-block add_earnings_list" ><?php echo _l('submit'); ?></a>
				<?php } ?>
			</div>
		</div>
	</div>
	<?php echo form_close(); ?>

	<div class="clearfix"></div>

</body>
</html>
