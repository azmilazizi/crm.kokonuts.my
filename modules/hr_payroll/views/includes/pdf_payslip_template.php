<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div>
	<div class="_buttons">
		<?php if(is_admin() || has_permission('hrp_setting','','create')){ ?>
			
			<a href="<?php echo admin_url('hr_payroll/payslip_pdf_template'); ?>" class="btn btn-info pull-left display-block mright5"><?php echo _l('new_pdf_payslip_template'); ?></a>

		<?php } ?>
	</div>
	<div class="clearfix"></div>
	<br>
	<table class="table dt-table">
		<thead>
			<th width="30%"><?php echo _l('name'); ?></th>
			<th><?php echo _l('payslip_template'); ?></th>
			<th><?php echo _l('options'); ?></th>
		</thead>
		<tbody>
			<?php foreach($pdf_payslip_templates as $pdf_payslip_template){ ?>

				<?php 

				$data_position_names = get_payslip_template_name($pdf_payslip_template['payslip_template_id']);

				?>

				<tr>
					
					<td><?php echo new_html_entity_decode($pdf_payslip_template['name']); ?></td>
					<td><?php echo new_html_entity_decode($data_position_names); ?></td>
					<td>
						<?php if(is_admin() || has_permission('hrp_setting','','edit')){ ?>
							<a href="<?php echo admin_url('hr_payroll/payslip_pdf_template/'.$pdf_payslip_template['id']); ?>"  class="btn btn-default btn-icon"><i class="fa-regular fa-pen-to-square"></i></a>
						<?php } ?>

						<?php if(is_admin() || has_permission('hrp_setting','','delete')){ ?>
							<a href="<?php echo admin_url('hr_payroll/delete_payslip_pdf_template_/'.$pdf_payslip_template['id']); ?>" class="btn btn-danger btn-icon _delete"><i class="fa fa-remove"></i></a>
						<?php } ?>

					</td>
				</tr>
			<?php } ?>
		</tbody>
	</table>       
	
</div>
</body>
</html>
