<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<table class="table">
	<tbody>
		<tr>
			<td width="15%"  class="text_align_center candidate_name_widt_27">
				<?php echo pdf_logo_url(); ?>
			</td>
			<td width="85%" class="text_align_center logo_with" ><?php echo format_organization_info() ?></td>
		</tr>
	</tbody>
</table>

<div class="text_align_center">
	<b><h3><?php echo _l('hrp_payslip_for').' '. date('M-Y', strtotime($payslip_detail['month'])); ?> </h3></b>
</div>



<table border="1" class="width-100-height-55" >
	<tbody>
		<tr class="height-27">
			<td class="width-20-height-27 align_left" ><strong><?php echo _l('employee_name') ; ?></strong></td>
			<td class="width-30-height-27" ><?php echo new_html_entity_decode($payslip_detail['employee_name']); ?></td>
			<td class="width-20-height-27" ><strong><?php echo _l('income_tax_number') ; ?></strong></td>
			<td class="width-30-height-27" ><?php echo new_html_entity_decode(isset($employee['income_tax_number']) ? $employee['income_tax_number'] : '') ?></td>
		</tr>

		<tr class="height-27">
			<td class="width-20-height-27 align_left" ><strong><?php echo _l('job_title') ; ?></strong></td>
			<td class="width-30-height-27" ><?php echo new_html_entity_decode(isset($employee['job_title']) ? $employee['job_title'] : '') ?></td>
			<td class="width-20-height-27" ><strong><?php echo _l('hrp_worked_day') ; ?></strong></td>
			<td class="width-30-height-27" ><?php echo app_format_money((float)$payslip_detail['actual_workday']+(float)$payslip_detail['actual_workday_probation'], '') ?></td>
		</tr>

		<tr class="height-27">
			<td class="width-20-height-27 align_left" ><strong><?php echo _l('staff_departments') ; ?></strong></td>
			<td class="width-30-height-27"><?php echo new_html_entity_decode($list_department) ?></td>
			<td class="width-20-height-27" ><strong><?php echo _l('paid_leave') ; ?></strong></td>
			<td class="width-30-height-27"><?php echo new_html_entity_decode($payslip_detail['paid_leave']); ?></td>
		</tr>
		<tr class="height-27">
			<td class="width-20-height-27 align_left" ><strong><?php echo _l('ps_pay_slip_number') ; ?></strong></td>
			<td class="width-30-height-27" ><?php echo new_html_entity_decode($payslip_detail['pay_slip_number']); ?></td>
			<td class="width-20-height-27" ><strong><?php echo _l('unpaid_leave') ; ?></strong></td>
			<td class="width-30-height-27" ><?php echo new_html_entity_decode($payslip_detail['unpaid_leave']); ?></td>
		</tr>
		
	</tbody>
</table>

<?php 
	$hrp_payslip_salary_allowance = hrp_payslip_json_data_decode($payslip_detail['json_data'], $payslip);
	
 ?>
<div class="row">
	<div class="col-md-6">
		<?php if((float)($payslip_detail['actual_workday_probation']) > 0){ ?>
			<table class="table">
				<tbody>
					<tr>
						<th  class=" thead-dark"><?php echo _l('hrp_probation_contract'); ?></th>
						<th  class=" thead-dark"></th>
					</tr>

					<?php echo isset($hrp_payslip_salary_allowance['probation_contract_list']) ? $hrp_payslip_salary_allowance['probation_contract_list'] : '' ?>
				</tbody>
			</table>
		<?php } ?>
		
		<?php if((float)($payslip_detail['actual_workday']) > 0){ ?>
		<table class="table">
			<tbody>
				<tr>
					<th  class=" thead-dark"><?php echo _l('hrp_formal_contract'); ?></th>
					<th  class=" thead-dark"></th>
				</tr>

				<?php echo isset($hrp_payslip_salary_allowance['formal_contract_list']) ? $hrp_payslip_salary_allowance['formal_contract_list'] : '' ?>
			</tbody>
		</table>
		<?php } ?>


	</div>
</div>

<div class="row">
	<div class="col-md-6">

		<table class="table">
			<tbody>
				<tr>
					<th  class=" thead-dark"><?php echo _l('Earnings'); ?></th>
					<th  class=" thead-dark"><?php echo _l('hrp_amount'); ?></th>
				</tr>

				<tr class="project-overview">
					<td  width="30%" ><?php echo _l('ps_gross_pay'); ?></td>
					<td class="text-left"><?php echo new_html_entity_decode(isset($payslip_detail) ?  currency_converter_value($payslip_detail['gross_pay'], $payslip->to_currency_rate, $payslip->to_currency_name ?? '', true) : 0); ?></td>
				</tr>
				<tr class="project-overview">
					<td ><?php echo _l('commission_amount'); ?></td>
					<td><?php echo (isset($payslip_detail) ? currency_converter_value($payslip_detail['commission_amount'], $payslip->to_currency_rate, $payslip->to_currency_name ?? '', true) : 0); ?></td>
				</tr>

				<tr class="project-overview">
					<td ><?php echo _l('ps_bonus_kpi'); ?></td>
					<td><?php echo isset($payslip_detail) ? currency_converter_value($payslip_detail['bonus_kpi'], $payslip->to_currency_rate, $payslip->to_currency_name ?? '', true) : 0; ?></td>
				</tr>
				<tr class="project-overview">
					<td class="bold" ><?php echo _l('total'); ?></td>
					<td><?php echo isset($payslip_detail) ? currency_converter_value($payslip_detail['gross_pay']+$payslip_detail['commission_amount']+$payslip_detail['bonus_kpi'], $payslip->to_currency_rate, $payslip->to_currency_name ?? '', true) : 0; ?></td>
				</tr>

			</tbody>
		</table>
		
		<table class="table">
			<tbody>
				<tr>
					<th  class=" thead-dark"><?php echo _l('deduction_list'); ?></th>
					<th  class=" thead-dark"><?php echo _l('hrp_amount'); ?></th>
				</tr>

				<tr class="project-overview">
					<td  width="30%" ><?php echo _l('income_tax'); ?></td>
					<td class="text-left"><?php echo new_html_entity_decode( isset($payslip_detail) ? currency_converter_value($payslip_detail['income_tax_paye'], $payslip->to_currency_rate, $payslip->to_currency_name ?? '', true) : ''); ?></td>
				</tr>
				<tr class="project-overview">
					<td ><?php echo _l('hrp_insurrance'); ?></td>
					<td><?php echo isset($payslip_detail) ? currency_converter_value($payslip_detail['total_insurance'], $payslip->to_currency_rate, $payslip->to_currency_name ?? '', true) : 0; ?></td>
				</tr>

				<tr class="project-overview">
					<td ><?php echo _l('hrp_deduction_manage'); ?></td>
					<td><?php echo isset($payslip_detail) ? currency_converter_value($payslip_detail['total_deductions'], $payslip->to_currency_rate, $payslip->to_currency_name ?? '', true) : 0; ?></td>
				</tr>
				<tr class="project-overview">
					<td class="bold" ><?php echo _l('total'); ?></td>
					<td><?php echo isset($payslip_detail) ? currency_converter_value($payslip_detail['income_tax_paye']+$payslip_detail['total_insurance']+$payslip_detail['total_deductions'], $payslip->to_currency_rate, $payslip->to_currency_name ?? '', true) : 0; ?></td>
				</tr>
			</tbody>
		</table>
		

	</div>

	<div class="col-md-6">
		<table class="table">
			<tbody>
				<tr class="project-overview">
					<td ><?php echo _l('ps_net_pay'); ?></td>
					<td><?php echo isset($payslip_detail) ? currency_converter_value($payslip_detail['net_pay'], $payslip->to_currency_rate, $payslip->to_currency_name ?? '', true) : 0; ?></td>
				</tr>
				
			</tbody>
		</table>
		
	</div>

</div>
