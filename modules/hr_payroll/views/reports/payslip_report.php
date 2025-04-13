<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div id="payslip_report" class="hide reports">
	<table class="table table-payslip_report scroll-responsive">
		<thead>
			<tr>
				<th>ID #</th>
			
				<th><?php echo _l('month'); ?></th>
				<th><?php echo _l('ps_payment_run_date'); ?></th>
				<th><?php echo _l('ps_pay_slip_number'); ?></th>
				<th><?php echo _l('employee_name'); ?></th>
				<th><?php echo _l('ps_gross_pay'); ?></th>
				<th><?php echo _l('ps_total_deductions'); ?></th>
				<th><?php echo _l('ps_income_tax_paye'); ?></th>
				<th><?php echo _l('ps_it_rebate_value'); ?></th>
				<th><?php echo _l('commission_amount'); ?></th>
				<th><?php echo _l('ps_bonus_kpi'); ?></th>
				<th><?php echo _l('ps_total_insurance'); ?></th>
				<th><?php echo _l('ps_net_pay'); ?></th>
				<th><?php echo _l('ps_total_cost'); ?></th>

			</tr>
		</thead>
		<tbody></tbody>
		<tfoot>
			<td></td>
			<td></td>
			<td></td>
			<td></td>
			<td></td>
			<td></td>
			<td></td>
			<td></td>         
			<td></td>         
			<td></td>         
			<td></td>         
			<td></td>         
			<td></td>         
		</tfoot>
	</table>
</div>
