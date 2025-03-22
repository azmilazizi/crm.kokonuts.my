<div class="modal fade" id="staff_contract_modal">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title"><?php echo _l('payslip_detail'); ?></h4>
			</div>

			<div class="modal-body">
				<div class="col-md-6">

					<div  class="bill_to_color">
						<?php echo new_html_entity_decode(format_organization_info()) ?>
					</div>
				</div>
				<div class="col-md-6 text-right">
					<p class="no-mbot">
						<span class="bold"><?php echo _l('ps_pay_slip_number'); ?>: </span>
						<?php echo new_html_entity_decode($payslip_detail->pay_slip_number); ?></p>
					</div>

					<div class="row">
						<div class="col-md-12">
							<table class="table border table-striped table-margin-none">
								<thead>
									<th class="th-color"><?php echo _l('employee_details'); ?></th>
								</thead>
							</table>
						</div>
					</div>
					<div>
						<div class="col-md-8">
							<table class="table border table-striped ">
								<tbody>
									<tr class="project-overview">
										<td class="bold" width="30%" ><?php echo _l('employee_name'); ?></td>
										<td class="text-left"><?php echo new_html_entity_decode($payslip_detail->employee_name); ?></td>
									</tr>
									<tr class="project-overview">
										<td class="bold"><?php echo _l('residential_address'); ?></td>
										<td><?php echo new_html_entity_decode(isset($employee['residential_address']) ? $employee['residential_address'] : '' )?></td>
									</tr>

									<tr class="project-overview">
										<td class="bold"><?php echo _l('employee_number'); ?></td>
										<td><?php echo new_html_entity_decode(isset($payslip_detail) ? $payslip_detail->employee_number : ''); ?></td>
									</tr>

									<tr class="project-overview">
										<td class="bold"><?php echo _l('job_title'); ?></td>
										<td><?php echo new_html_entity_decode(isset($employee['job_title']) ? $employee['job_title'] : '') ?></td>
									</tr>
								</tbody>
							</table>
						</div>
						<div class="col-md-4">
							<table class="table border table-striped ">
								<tbody>
									<tr class="project-overview">
										<td class="bold" width="30%" >Tax Number</td>
										<td><?php echo new_html_entity_decode(isset($employee['income_tax_number']) ? $employee['income_tax_number'] : '') ?></td>
									</tr>
									<tr class="project-overview">
										<td class="bold">Deparment</td>
										<td><?php echo new_html_entity_decode($list_department) ?></td>
									</tr>
								</tbody>
							</table>
						</div>
					</div>

					<div class="row">
						<div class="col-md-12">
							<table class="table border table-striped table-margin-none">
								<thead>
									<th class="th-color"><?php echo _l('monthly_pay_for').' ' .isset($payslip_detail) ? date('m-Y',strtotime($payslip_detail->month)) : '' ; ?></th>
								</thead>
							</table >
						</div>
					</div>

					<?php 

					$hrp_payslip_salary_allowance = hrp_payslip_json_data_decode($payslip_detail->json_data);


					 ?>

					<h5><?php echo _l('hrp_salary').', '._l('hrp_allowance'); ?></h5>
					<div>
						<div class="row">
							<div class="col-md-12">
								<div class="col-md-6">
									<table class="table border table-striped table-margin-none">
										<tbody>
											<tr class="project-overview">
												<td  width="50%" ><b><?php echo _l('hrp_probation_contract'); ?></b></td>
												<td  width="50%" ></td>
											</tr>
											<?php echo isset($hrp_payslip_salary_allowance['probation_contract_list']) ? $hrp_payslip_salary_allowance['probation_contract_list'] : '' ?>

										</tbody>
									</table>
								</div>

								<div class="col-md-6">
									<table class="table border table-striped table-margin-none">
										<tbody>
											<tr class="project-overview">
												<td  width="50%" ><b><?php echo _l('hrp_formal_contract'); ?></b></td>
												<td  width="50%" ></td>

											</tr>
											<?php echo isset($hrp_payslip_salary_allowance['formal_contract_list']) ? $hrp_payslip_salary_allowance['formal_contract_list'] : '' ?>
											

										</tbody>
									</table>
								</div>
							</div>
							<div class="col-md-12">
								<hr class="hr-color">
							</div>

						</div>
					</div>

					<h5><?php echo _l('Earnings'); ?></h5>
					<div>
						<div class="col-md-12">
							<table class="table border table-striped table-margin-none">
								<tbody>
									<tr class="project-overview">
										<td  width="30%" ><?php echo _l('ps_gross_pay'); ?></td>
										<td class="text-left"><?php echo new_html_entity_decode(isset($payslip_detail) ?  currency_converter_value($payslip_detail->gross_pay, $payslip->to_currency_rate, $payslip->to_currency_name ?? '', true) : 0); ?></td>
									</tr>
									<tr class="project-overview">
										<td ><?php echo _l('commission_amount'); ?></td>
										<td><?php echo (isset($payslip_detail) ? currency_converter_value($payslip_detail->commission_amount, $payslip->to_currency_rate, $payslip->to_currency_name ?? '', true) : 0); ?></td>
									</tr>

									<tr class="project-overview">
										<td ><?php echo _l('ps_bonus_kpi'); ?></td>
										<td><?php echo isset($payslip_detail) ? currency_converter_value($payslip_detail->bonus_kpi, $payslip->to_currency_rate, $payslip->to_currency_name ?? '', true) : 0; ?></td>
									</tr>
									<tr class="project-overview">
										<td class="bold" ><?php echo _l('total'); ?></td>
										<td><?php echo isset($payslip_detail) ? currency_converter_value($payslip_detail->gross_pay+$payslip_detail->commission_amount+$payslip_detail->bonus_kpi, $payslip->to_currency_rate, $payslip->to_currency_name ?? '', true) : 0; ?></td>
									</tr>
								</tbody>
							</table>
							<hr class="hr-color">
						</div>

						<h5><?php echo _l('deduction_list'); ?></h5>
						<div class="col-md-12">
							<table class="table border table-striped table-margin-none">
								<tbody>
									<tr class="project-overview">
										<td  width="30%" ><?php echo _l('income_tax'); ?></td>
										<td class="text-left"><?php echo new_html_entity_decode( isset($payslip_detail) ? currency_converter_value($payslip_detail->income_tax_paye, $payslip->to_currency_rate, $payslip->to_currency_name ?? '', true) : ''); ?></td>
									</tr>
									<tr class="project-overview">
										<td ><?php echo _l('hrp_insurrance'); ?></td>
										<td><?php echo isset($payslip_detail) ? currency_converter_value($payslip_detail->total_insurance, $payslip->to_currency_rate, $payslip->to_currency_name ?? '', true) : 0; ?></td>
									</tr>

									<tr class="project-overview">
										<td ><?php echo _l('hrp_deduction_manage'); ?></td>
										<td><?php echo isset($payslip_detail) ? currency_converter_value($payslip_detail->total_deductions, $payslip->to_currency_rate, $payslip->to_currency_name ?? '', true) : 0; ?></td>
									</tr>
									<tr class="project-overview">
										<td class="bold" ><?php echo _l('total'); ?></td>
										<td><?php echo isset($payslip_detail) ? currency_converter_value($payslip_detail->income_tax_paye+$payslip_detail->total_insurance+$payslip_detail->total_deductions, $payslip->to_currency_rate, $payslip->to_currency_name ?? '', true) : 0; ?></td>
									</tr>
								</tbody>
							</table>
							<hr class="hr-color">

						</div>
						<div class="row">
							<div class="col-md-12">
								<table class="table border table-striped table-margin-none">
									<tbody>
										<tr class="project-overview">
											<td class="bold"  width="30%" ><?php echo _l('ps_net_pay'); ?></td>
											<td class="text-left"><?php echo isset($payslip_detail) ?  currency_converter_value($payslip_detail->net_pay, $payslip->to_currency_rate, $payslip->to_currency_name ?? '', true) : 0; ?></td>
										</tr>

									</tbody>
								</table>
							</div>
						</div>

					</div>

				</div>

				<div class="modal-footer">
					<button type="button" class="btn btn-default close_btn" data-dismiss="modal"><?php echo _l('hr_close'); ?></button>
				</div>
			</div>

		</div>
	</div>

