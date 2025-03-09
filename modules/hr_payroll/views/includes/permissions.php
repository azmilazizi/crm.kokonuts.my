<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<div class="row">
	<div class="col-md-12">
		<h4 class="h4-color no-margin"><i class="fa fa-unlock-alt" aria-hidden="true"></i> <?php echo _l('hrp_permissions'); ?></h4>
	</div>
</div>
<hr class="hr-color">

<?php if(is_admin()){ ?>
<a href="#" onclick="hr_payroll_permissions_update(0,0,' hide'); return false;" class="btn btn-info mbot10"><?php echo _l('_new'); ?></a>
<?php } ?>
<table class="table table-hr-profile-permission">
  <thead>
    <th><?php echo _l('hrp_staff_name'); ?></th>
    <th><?php echo _l('role'); ?></th>
    <th><?php echo _l('staff_dt_email'); ?></th>
    <th><?php echo _l('hrp_phone'); ?></th>
    <th><?php echo _l('options'); ?></th>
  </thead>
  <tbody>
  </tbody>
</table>
<div id="modal_wrapper"></div>

