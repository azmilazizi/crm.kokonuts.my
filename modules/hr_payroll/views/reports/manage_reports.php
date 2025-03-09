<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper" >
	<div class="content">
		<div class="row">
			<div class="col-md-12">
				<div class="panel_s">
					<div class="panel-body">
						<div class="row">

							<div class="col-md-4 border-right">
								<h4 class="no-margin font-medium"><i class="fa fa-balance-scale" aria-hidden="true"></i> <?php echo _l('hrp_reports'); ?></h4>
								<hr class="hr-10" />
								<p><a href="#" class="font-medium" onclick="init_report(this,'payslip_report'); return false;"><i class="fa fa-caret-down" aria-hidden="true"></i> <?php echo _l('hr_payslip_report'); ?></a></p>
								<hr class="hr-10" />
								<p><a href="#" class="font-medium" onclick="init_report(this,'income_summary_report'); return false;"><i class="fa fa-caret-down" aria-hidden="true"></i> <?php echo _l('hr_income_summary_report'); ?></a></p>
								<hr class="hr-10" />
								<p><a href="#" class="font-medium" onclick="init_report(this,'insurance_cost_summary_report'); return false;"><i class="fa fa-caret-down" aria-hidden="true"></i> <?php echo _l('hr_insurance_cost_summary_report'); ?></a></p>
								

							</div>

							<div class="col-md-4 border-right">

								<h4 class="no-margin font-medium"><i class="fa fa-area-chart" aria-hidden="true"></i> <?php echo _l('charts_based_report'); ?></h4>
								<hr class="hr-10" />
								<p><a href="#" class="font-medium" onclick="init_report(this,'payslip_chart'); return false;"><i class="fa fa-caret-down" aria-hidden="true"></i> <?php echo _l('payslip_chart'); ?></a></p>
								<hr class="hr-10" />
								<p><a href="#" class="font-medium" onclick="init_report(this,'department_payslip_chart'); return false;"><i class="fa fa-caret-down" aria-hidden="true"></i> <?php echo _l('department_payslip_chart'); ?></a></p>
								<hr class="hr-10" />

							</div>

							<div class="col-md-4">
								<div class="bg-light-gray border-radius-4">
									<div class="p8">

										<div id="currency" class="form-group hide">
											<label for="currency"><i class="fa fa-question-circle" data-toggle="tooltip" title="<?php echo _l('report_sales_base_currency_select_explanation'); ?>"></i> <?php echo _l('currency'); ?></label><br />
											<select class="selectpicker" name="currency" data-width="100%" data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>">

											</select>
										</div>


										<div class="form-group" id="report-time">
											<label for="months-report"><?php echo _l('period_datepicker'); ?></label><br />
											<select class="selectpicker" name="months-report" data-width="100%" data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>">
												<option value=""><?php echo _l('report_sales_months_all_time'); ?></option>
												<option value="this_month"><?php echo _l('this_month'); ?></option>
												<option value="1"><?php echo _l('last_month'); ?></option>
												<option value="this_year"><?php echo _l('this_year'); ?></option>
												<option value="last_year"><?php echo _l('last_year'); ?></option>
												<option value="3" data-subtext="<?php echo _d(date('Y-m-01', strtotime("-2 MONTH"))); ?> - <?php echo _d(date('Y-m-t')); ?>"><?php echo _l('report_sales_months_three_months'); ?></option>
												<option value="6" data-subtext="<?php echo _d(date('Y-m-01', strtotime("-5 MONTH"))); ?> - <?php echo _d(date('Y-m-t')); ?>"><?php echo _l('report_sales_months_six_months'); ?></option>
												<option value="12" data-subtext="<?php echo _d(date('Y-m-01', strtotime("-11 MONTH"))); ?> - <?php echo _d(date('Y-m-t')); ?>"><?php echo _l('report_sales_months_twelve_months'); ?></option>
												<option value="custom"><?php echo _l('period_datepicker'); ?></option>
											</select>
										</div>
										
										<?php $current_year = date('Y');
										$y0 = (int)$current_year;
										$y1 = (int)$current_year - 1;
										$y2 = (int)$current_year - 2;
										$y3 = (int)$current_year - 3;
										?>


										<div class="form-group hide" id="year_requisition">
											<label for="months-report"><?php echo _l('period_datepicker'); ?></label><br />
											<select  name="year_requisition" id="year_requisition"  class="selectpicker"  data-width="100%" data-none-selected-text="<?php echo _l('filter_by').' '._l('year'); ?>">
												<option value="<?php echo new_html_entity_decode($y0) ; ?>" <?php echo 'selected' ?>><?php echo _l('year').' '. $y0 ; ?></option>
												<option value="<?php echo new_html_entity_decode($y1) ; ?>"><?php echo _l('year').' '. $y1 ; ?></option>
												<option value="<?php echo new_html_entity_decode($y2) ; ?>"><?php echo _l('year').' '. $y2 ; ?></option>
												<option value="<?php echo new_html_entity_decode($y3) ; ?>"><?php echo _l('year').' '. $y3 ; ?></option>

											</select>
										</div>


										<div id="date-range" class="hide mbot15">
											<div class="row">
												<div class="col-md-6">
													<label for="report-from" class="control-label"><?php echo _l('report_sales_from_date'); ?></label>
													<div class="input-group date">
														<input type="text" class="form-control datepicker" id="report-from" autocomplete="off" name="report-from">
														<div class="input-group-addon">
															<i class="fa fa-calendar calendar-icon"></i>
														</div>
													</div>
												</div>
												<div class="col-md-6">
													<label for="report-to" class="control-label"><?php echo _l('report_sales_to_date'); ?></label>
													<div class="input-group date">
														<input type="text" class="form-control datepicker" disabled="disabled" autocomplete="off" id="report-to" name="report-to">
														<div class="input-group-addon">
															<i class="fa fa-calendar calendar-icon"></i>
														</div>
													</div>
												</div>
											</div>
										</div>
									</div>
								</div>
							</div>

						</div>
						<div id="report" class="hide">
							<hr class="hr-panel-heading" />
							<hr class="hr-panel-heading" />
							<div class="row d-flex justify-content-center">
								<h5 class="title_table"></h5>                 
							</div>

							<div class="row sorting_table hide">
							
								<div class="col-md-4">
									<div class="form-group">
										<label for="annual_leave"><?php echo _l('staff_departments'); ?></label>
										<select name="department[]" class="selectpicker" data-live-search="true" multiple data-width="100%" data-actions-box="true" data-none-selected-text="<?php echo _l('invoice_status_report_all'); ?>">
											<?php foreach($department as $value){ ?>
												<option value="<?php echo new_html_entity_decode($value['departmentid']); ?>"><?php echo new_html_entity_decode($value['name']); ?></option>
											<?php } ?>
										</select>
									</div>
								</div>
								<div class="col-md-4">
									<div class="form-group">
										<label for="annual_leave"><?php echo _l('staff'); ?></label>
										<select name="staff[]" class="selectpicker" data-live-search="true" multiple data-width="100%" data-actions-box="true" data-none-selected-text="<?php echo _l('invoice_status_report_all'); ?>">
											<?php foreach($staff as $item){ ?>
												<option value="<?php echo new_html_entity_decode($item['staffid']); ?>"><?php echo new_html_entity_decode($item['firstname'].' '.$item['lastname']); ?></option>
											<?php } ?>
										</select>
									</div>
								</div>
								
							</div>

							<div class="row payslip_chart_table hide">
								<div class="col-md-4">
									<div class="form-group">
										<label for="annual_leave"><?php echo _l('staff'); ?></label>
										<select name="staff" class="selectpicker" data-live-search="true" data-width="100%" data-actions-box="true" data-none-selected-text="<?php echo _l('invoice_status_report_all'); ?>">
											<?php foreach($staff as $item){ ?>
												<option value="<?php echo new_html_entity_decode($item['staffid']); ?>"><?php echo new_html_entity_decode($item['firstname'].' '.$item['lastname']); ?></option>
											<?php } ?>
										</select>
									</div>
								</div>
							</div>

                <?php $this->load->view('reports/payslip_report.php'); ?>  
                <?php $this->load->view('reports/income_summary_report.php'); ?>  
                <?php $this->load->view('reports/insurance_cost_summary_report.php'); ?>  
                <?php $this->load->view('reports/payslip_chart.php'); ?>  
                <?php $this->load->view('reports/department_payslip_chart.php'); ?>  


						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
<?php init_tail(); ?>
<?php 
require('modules/hr_payroll/assets/js/reports/report_js.php');
?>
</body>
</html>

