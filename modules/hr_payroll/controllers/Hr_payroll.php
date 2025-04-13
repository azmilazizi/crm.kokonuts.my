<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * hr payroll controler
 */
class hr_payroll extends AdminController {

	public function __construct() {
		parent::__construct();
		$this->load->model('hr_payroll_model');
		hooks()->do_action('hr_payroll_init'); 
	}

	/**
	 * setting
	 * @return view
	 */
	public function setting() {
		if (!has_permission('hrp_setting', '', 'view') && !has_permission('hrp_setting', '', 'edit') && !is_admin() && !has_permission('hrp_setting', '', 'create')) {
			access_denied('hrp_settting');
		}

		$data['group'] = $this->input->get('group');
        $data['unit_tab'] = $this->input->get('tab');

		$data['title'] = _l('setting');

		$data['tab'][] = 'income_tax_rates';
		$data['tab'][] = 'income_tax_rebates';
		if (hr_payroll_get_status_modules('hr_profile') && (get_hr_payroll_option('integrated_hrprofile') == 1)) {
			$data['tab'][] = 'hr_records_earnings_list';
		} else {
			$data['tab'][] = 'earnings_list';
		}
		$data['tab'][] = 'salary_deductions_list';
		$data['tab'][] = 'insurance_list';
		$data['tab'][] = 'payroll_columns';
		$data['tab'][] = 'pdf_payslip_template';
		$data['tab'][] = 'data_integration';
        $data['tab'][] = 'currency_rates';

		if (is_admin()) {
			$data['tab'][] = 'permissions';
			$data['tab'][] = 'reset_data';
		}

		if ($data['group'] == '') {
			$data['group'] = 'payroll_columns';
			$data['payroll_column_value'] = $this->hr_payroll_model->get_hrp_payroll_columns();
			$data['order_display_in_paylip'] = $this->hr_payroll_model->count_payroll_column();
		} elseif ($data['group'] == 'payroll_columns') {
			$data['payroll_column_value'] = $this->hr_payroll_model->get_hrp_payroll_columns();
			$data['order_display_in_paylip'] = $this->hr_payroll_model->count_payroll_column();

		} elseif ($data['group'] == 'income_tax_rates') {
			$data['title'] = _l('income_tax_rates');
			$data['income_tax_rates'] = json_encode($this->hr_payroll_model->get_income_tax_rate());
		} elseif ($data['group'] == 'income_tax_rebates') {
			$data['title'] = _l('income_tax_rebates');
			$data['income_tax_rebates'] = json_encode($this->hr_payroll_model->get_income_tax_rebates());
		} elseif ($data['group'] == 'earnings_list') {

			$earnings_value = [];
			$earnings_value[] = [
				'id' => 'monthly',
				'label' => _l('monthly'),
			];
			$earnings_value[] = [
				'id' => 'annual',
				'label' => _l('annual'),
			];

			$data['title'] = _l('earnings_list');
			$data['basis_value'] = $earnings_value;
			$data['earnings_list'] = json_encode($this->hr_payroll_model->get_earnings_list());
		} elseif ($data['group'] == 'salary_deductions_list') {
			$earn_inclusion_value = [];
			$earn_inclusion_value[] = [
				'id' => 'fullvalue',
				'label' => _l('fullvalue'),
			];
			$earn_inclusion_value[] = [
				'id' => 'taxable',
				'label' => _l('taxable'),
			];

			$basis_value = [];
			$basis_value[] = [
				'id' => 'gross',
				'label' => _l('gross'),
			];
			$basis_value[] = [
				'id' => 'fixed_amount',
				'label' => _l('fixed_amount'),
			];

			if (hr_payroll_get_status_modules('hr_profile') && (get_hr_payroll_option('integrated_hrprofile') == 1)) {
				$earnings_list = $this->hr_payroll_model->hr_records_get_earnings_list();

				foreach ($earnings_list as $value) {
					switch ($value['rel_type']) {
						case 'salary':
						
						$basis_value[] = [
							'id' => 'st_'.$value['rel_id'],
							'label' => $value['description'],
						];
						break;

						case 'allowance':
						$basis_value[] = [
							'id' => 'al_'.$value['rel_id'],
							'label' => $value['description'],
						];
						
						break;

						default:
						# code...
						break;
					}

				}


			} else {
				$earnings_list = $this->hr_payroll_model->get_earnings_list();

				foreach ($earnings_list as $value) {
					$basis_value[] = [
						'id' => 'earning_'.$value['id'],
						'label' => $value['description'],
					];
				}
			}

			$data['title'] = _l('salary_deductions_list');
			$data['basis_value'] = $basis_value;
			$data['earn_inclusion'] = $earn_inclusion_value;
			$data['salary_deductions_list'] = json_encode($this->hr_payroll_model->get_salary_deductions_list());

		} elseif ($data['group'] == 'insurance_list') {
			$basis_value = [];
			$basis_value[] = [
				'id' => 'gross',
				'label' => _l('gross'),
			];
			$basis_value[] = [
				'id' => 'fixed_amount',
				'label' => _l('fixed_amount'),
			];

			$data['title'] = _l('insurance_list');
			$data['basis_value'] = $basis_value;
			$data['insurance_list'] = json_encode($this->hr_payroll_model->get_insurance_list());

		} elseif ($data['group'] == 'company_contributions_list') {
			$earn_inclusion_value = [];
			$earn_inclusion_value[] = [
				'id' => 'fullvalue',
				'label' => _l('fullvalue'),
			];
			$earn_inclusion_value[] = [
				'id' => 'taxable',
				'label' => _l('taxable'),
			];
			$earn_inclusion_value[] = [
				'id' => 'none',
				'label' => _l('none'),
			];

			$data['title'] = _l('company_contributions_list');
			$data['earn_inclusion'] = $earn_inclusion_value;
			$data['company_contributions_list'] = json_encode($this->hr_payroll_model->get_company_contributions_list());
		} elseif ($data['group'] == 'data_integration') {
			$data['hr_profile_active'] = hr_payroll_get_status_modules('hr_profile');
			$data['timesheets_active'] = hr_payroll_get_status_modules('timesheets');
			$data['commissions_active'] = hr_payroll_get_status_modules('commission');

			$hr_profile_title = '';
			$timesheets_title = '';
			//title
			if ($data['hr_profile_active'] == false) {
				$hr_profile_title = _l('active_hr_profile_to_integration');
			} else {
				$hr_profile_title = _l('hr_profile_integration_data');
			}

			if ($data['timesheets_active'] == false) {
				$timesheets_title = _l('active_timesheets_to_integration');
			} else {
				$timesheets_title = _l('timesheets_to_integration');
			}

			if ($data['commissions_active'] == false) {
				$commissions_title = _l('active_commissions_to_integration');
			} else {
				$commissions_title = _l('commissions_to_integration');
			}

			$data['hr_profile_title'] = $hr_profile_title;
			$data['timesheets_title'] = $timesheets_title;
			$data['commissions_title'] = $commissions_title;

			//get data each type
			$get_attendance_type = $this->hr_payroll_model->setting_get_attendance_type();

			$data['actual_workday_type'] = $get_attendance_type['actual_workday'];
			$data['paid_leave_type'] = $get_attendance_type['paid_leave'];
			$data['unpaid_leave_type'] = $get_attendance_type['unpaid_leave'];
			$data['get_customize_payslip_columns'] = $this->hr_payroll_model->get_customize_payslip_columns();

		} elseif ($data['group'] == 'hr_records_earnings_list') {
			$earnings_value = [];
			$earnings_value[] = [
				'id' => 'monthly',
				'label' => _l('monthly'),
			];
			$earnings_value[] = [
				'id' => 'annual',
				'label' => _l('annual'),
			];

			$data['title'] = _l('earnings_list');
			$data['basis_value'] = $earnings_value;
			$data['earnings_list_hr_records'] = json_encode($this->hr_payroll_model->hr_records_get_earnings_list());
		} elseif($data['group'] == 'pdf_payslip_template'){
			$data['pdf_payslip_templates'] = $this->hr_payroll_model->get_pdf_payslip_template();

		} elseif($data['group'] == 'currency_rates'){
            $this->load->model('currencies_model');
            $this->hr_payroll_model->check_auto_create_currency_rate();

            $data['currencies'] = $this->currencies_model->get();
            if($data['unit_tab'] == ''){
                $data['unit_tab'] = 'general';
            }
        }

		$data['tabs']['view'] = 'includes/' . $data['group'];

		$this->load->view('includes/manage_setting', $data);
	}

	/**
	 * setting incometax rates
	 * @return [type]
	 */
	public function setting_incometax_rates() {
		if ($this->input->post()) {

			$data = $this->input->post();
			if (!$this->input->post('id')) {

				$mess = $this->hr_payroll_model->update_income_tax_rates($data);
				if ($mess) {
					set_alert('success', _l('hrp_updated_successfully'));

				} else {
					set_alert('success', _l('hrp_updated_successfully'));
				}

				redirect(admin_url('hr_payroll/setting?group=income_tax_rates'));

			}

		}
	}

	/**
	 * setting incometax rebates
	 * @return [type]
	 */
	public function setting_incometax_rebates() {
		if ($this->input->post()) {

			$data = $this->input->post();
			if (!$this->input->post('id')) {

				$mess = $this->hr_payroll_model->update_income_tax_rebates($data);
				if ($mess) {
					set_alert('success', _l('hrp_updated_successfully'));

				} else {
					set_alert('success', _l('hrp_updated_successfully'));
				}

				redirect(admin_url('hr_payroll/setting?group=income_tax_rebates'));

			}

		}
	}

	/**
	 * setting earnings list
	 * @return [type]
	 */
	public function setting_earnings_list() {
		if ($this->input->post()) {

			$data = $this->input->post();
			if (!$this->input->post('id')) {

				$mess = $this->hr_payroll_model->update_earnings_list($data);
				if ($mess) {
					set_alert('success', _l('hrp_updated_successfully'));

				} else {
					set_alert('success', _l('hrp_updated_successfully'));
				}

				redirect(admin_url('hr_payroll/setting?group=earnings_list'));

			}

		}
	}

	/**
	 * setting salary deductions list
	 * @return [type]
	 */
	public function setting_salary_deductions_list() {
		if ($this->input->post()) {

			$data = $this->input->post();
			if (!$this->input->post('id')) {

				$mess = $this->hr_payroll_model->update_salary_deductions_list($data);
				if ($mess) {
					set_alert('success', _l('hrp_updated_successfully'));

				} else {
					set_alert('success', _l('hrp_updated_successfully'));
				}

				redirect(admin_url('hr_payroll/setting?group=salary_deductions_list'));

			}

		}
	}

	/**
	 * setting insurance list
	 * @return [type]
	 */
	public function setting_insurance_list() {
		if ($this->input->post()) {

			$data = $this->input->post();
			if (!$this->input->post('id')) {

				$mess = $this->hr_payroll_model->update_insurance_list($data);
				if ($mess) {
					set_alert('success', _l('hrp_updated_successfully'));

				} else {
					set_alert('success', _l('hrp_updated_successfully'));
				}

				redirect(admin_url('hr_payroll/setting?group=insurance_list'));

			}

		}
	}

	/**
	 * setting company contributions list
	 * @return [type]
	 */
	public function setting_company_contributions_list() {
		if ($this->input->post()) {

			$data = $this->input->post();
			if (!$this->input->post('id')) {

				$mess = $this->hr_payroll_model->update_company_contributions_list($data);
				if ($mess) {
					set_alert('success', _l('hrp_updated_successfully'));

				} else {
					set_alert('warning', _l('hrp_updated_failed'));
				}

				redirect(admin_url('hr_payroll/setting?group=company_contributions_list'));

			}

		}
	}

	/**
	 * data integration
	 * @return [type]
	 */
	public function data_integration() {
		if (!is_admin()) {
			access_denied('hr_payroll');
		}

		$data = $this->input->post();

		$mess = $this->hr_payroll_model->update_data_integration($data);
		if ($mess) {
			set_alert('success', _l('hrp_updated_successfully'));

		} else {
			set_alert('warning', _l('hrp_updated_failed'));
		}

		redirect(admin_url('hr_payroll/setting?group=data_integration'));

	}

	/**
	 * timesheet integration type change
	 * @return [type]
	 */
	public function timesheet_integration_type_change() {
		if ($this->input->post()) {
			$data = $this->input->post();

			$results = $this->hr_payroll_model->get_timesheet_type_for_setting($data);

			echo json_encode([
				'actual_workday_v' => $results['actual_workday'],
				'paid_leave_v' => $results['paid_leave'],
				'unpaid_leave_v' => $results['unpaid_leave'],
			]);
			die;
		}
	}

	/**
	 * setting earnings list hr records
	 * @return [type]
	 */
	public function setting_earnings_list_hr_records() {
		if ($this->input->post()) {

			$data = $this->input->post();
			if (!$this->input->post('id')) {

				$mess = $this->hr_payroll_model->earnings_list_synchronization($data);
				set_alert('success', _l('hrp_successful_data_synchronization'));
				if ($mess) {
					set_alert('success', _l('hrp_updated_successfully'));

				} else {
					set_alert('success', _l('hrp_updated_successfully'));
				}

				redirect(admin_url('hr_payroll/setting?group=hr_records_earnings_list'));
			}
		}
	}

	/**
	 * hr payroll permission table
	 * @return [type]
	 */
	public function hr_payroll_permission_table() {
		if ($this->input->is_ajax_request()) {

			$select = [
				'staffid',
				'CONCAT(firstname," ",lastname) as full_name',
				'firstname', //for role name
				'email',
				'phonenumber',
			];
			$where = [];
			$where[] = 'AND ' . db_prefix() . 'staff.admin != 1';

			$arr_staff_id = hr_payroll_get_staff_id_hr_permissions();

			if (count($arr_staff_id) > 0) {
				$where[] = 'AND ' . db_prefix() . 'staff.staffid IN (' . implode(', ', $arr_staff_id) . ')';
			} else {
				$where[] = 'AND ' . db_prefix() . 'staff.staffid IN ("")';
			}

			$aColumns = $select;
			$sIndexColumn = 'staffid';
			$sTable = db_prefix() . 'staff';
			$join = ['LEFT JOIN ' . db_prefix() . 'roles ON ' . db_prefix() . 'roles.roleid = ' . db_prefix() . 'staff.role'];

			$result = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, [db_prefix() . 'roles.name as role_name', db_prefix() . 'staff.role']);

			$output = $result['output'];
			$rResult = $result['rResult'];

			$not_hide = '';

			foreach ($rResult as $aRow) {
				$row = [];

				$row[] = '<a href="' . admin_url('staff/member/' . $aRow['staffid']) . '">' . $aRow['full_name'] . '</a>';

				$row[] = $aRow['role_name'];
				$row[] = $aRow['email'];
				$row[] = $aRow['phonenumber'];

				$options = '';

				if (has_permission('hrm_setting', '', 'edit')) {
					$options = icon_btn('#', 'fa-regular fa-pen-to-square', 'btn-default', [
						'title' => _l('hr_edit'),
						'onclick' => 'hr_payroll_permissions_update(' . $aRow['staffid'] . ', ' . $aRow['role'] . ', ' . $not_hide . '); return false;',
					]);
				}

				if (has_permission('hrm_setting', '', 'delete')) {
					$options .= icon_btn('hr_payroll/delete_hr_payroll_permission/' . $aRow['staffid'], 'fa fa-remove', 'btn-danger _delete', ['title' => _l('delete')]);
				}

				$row[] = $options;

				$output['aaData'][] = $row;
			}

			echo json_encode($output);
			die();
		}
	}

	/**
	 * permission modal
	 * @return [type]
	 */
	public function permission_modal() {
		if (!$this->input->is_ajax_request()) {
			show_404();
		}
		$this->load->model('staff_model');

		if ($this->input->post('slug') === 'update') {
			$staff_id = $this->input->post('staff_id');
			$role_id = $this->input->post('role_id');

			$data = ['funcData' => ['staff_id' => isset($staff_id) ? $staff_id : null]];

			if (isset($staff_id)) {
				$data['member'] = $this->staff_model->get($staff_id);
			}

			$data['roles_value'] = $this->roles_model->get();
			$data['staffs'] = hr_payroll_get_staff_id_dont_permissions();
			$add_new = $this->input->post('add_new');

			if ($add_new == ' hide') {
				$data['add_new'] = ' hide';
				$data['display_staff'] = '';
			} else {
				$data['add_new'] = '';
				$data['display_staff'] = ' hide';
			}

			$this->load->view('includes/permission_modal', $data);
		}
	}

	/**
	 * hr payroll update permissions
	 * @param  string $id
	 * @return [type]
	 */
	public function hr_payroll_update_permissions($id = '') {
		if (!is_admin()) {
			access_denied('hr_payroll');
		}
		$data = $this->input->post();

		if (!isset($id) || $id == '') {
			$id = $data['staff_id'];
		}

		if (isset($id) && $id != '') {

			$data = hooks()->apply_filters('before_update_staff_member', $data, $id);

			if (is_admin()) {
				if (isset($data['administrator'])) {
					$data['admin'] = 1;
					unset($data['administrator']);
				} else {
					if ($id != get_staff_user_id()) {
						if ($id == 1) {
							return [
								'cant_remove_main_admin' => true,
							];
						}
					} else {
						return [
							'cant_remove_yourself_from_admin' => true,
						];
					}
					$data['admin'] = 0;
				}
			}

			$this->db->where('staffid', $id);
			$this->db->update(db_prefix() . 'staff', [
				'role' => $data['role'],
			]);

			$response = $this->staff_model->update_permissions((isset($data['admin']) && $data['admin'] == 1 ? [] : $data['permissions']), $id);
		} else {
			$this->load->model('roles_model');

			$role_id = $data['role'];
			unset($data['role']);
			unset($data['staff_id']);

			$data['update_staff_permissions'] = true;

			$response = $this->roles_model->update($data, $role_id);
		}

		if (is_array($response)) {
			if (isset($response['cant_remove_main_admin'])) {
				set_alert('warning', _l('staff_cant_remove_main_admin'));
			} elseif (isset($response['cant_remove_yourself_from_admin'])) {
				set_alert('warning', _l('staff_cant_remove_yourself_from_admin'));
			}
		} elseif ($response == true) {
			set_alert('success', _l('updated_successfully', _l('staff_member')));
		}
		redirect(admin_url('hr_payroll/setting?group=permissions'));

	}

	/**
	 * staff id changed
	 * @param  [type] $staff_id
	 * @return [type]
	 */
	public function staff_id_changed($staff_id) {
		$role_id = '';
		$status = 'false';
		$r_permission = [];

		$staff = $this->staff_model->get($staff_id);

		if ($staff) {
			if (count($staff->permissions) > 0) {
				foreach ($staff->permissions as $permission) {
					$r_permission[$permission['feature']][] = $permission['capability'];
				}
			}

			$role_id = $staff->role;
			$status = 'true';

		}

		if (count($r_permission) > 0) {
			$data = ['role_id' => $role_id, 'status' => $status, 'permission' => 'true', 'r_permission' => $r_permission];
		} else {
			$data = ['role_id' => $role_id, 'status' => $status, 'permission' => 'false', 'r_permission' => $r_permission];
		}

		echo json_encode($data);
		die;
	}

	/**
	 * delete hr payroll permission
	 * @param  [type] $id
	 * @return [type]
	 */
	public function delete_hr_payroll_permission($id) {
		if (!is_admin()) {
			access_denied('hr_profile');
		}

		$response = $this->hr_payroll_model->delete_hr_payroll_permission($id);

		if (is_array($response) && isset($response['referenced'])) {
			set_alert('warning', _l('hr_is_referenced', _l('department_lowercase')));
		} elseif ($response == true) {
			set_alert('success', _l('deleted', _l('hr_department')));
		} else {
			set_alert('warning', _l('problem_deleting', _l('department_lowercase')));
		}
		redirect(admin_url('hr_payroll/setting?group=permissions'));

	}

	/**
	 * manage employees
	 * @return [type]
	 */
	public function manage_employees() {
		if (!has_permission('hrp_employee', '', 'view') && !has_permission('hrp_employee', '', 'view_own') && !is_admin()) {
			access_denied('hrp_employee');
		}

		$this->load->model('staff_model');
		$this->load->model('departments_model');

		$rel_type = hrp_get_hr_profile_status();

		//get current month
		$current_month = date('Y-m-d', strtotime(date('Y-m') . '-01'));
		$employees_data = $this->hr_payroll_model->get_employees_data($current_month, $rel_type);
		$employees_value = [];
		foreach ($employees_data as $key => $value) {
			$employees_value[$value['staff_id'] . '_' . $value['month']] = $value;
		}
		//get employee data for the first
		$format_employees_value = $this->hr_payroll_model->get_format_employees_data($rel_type);

		//load staff
		if (!is_admin() && !has_permission('hrp_employee', '', 'view')) {
			//View own
			$staffs = $this->hr_payroll_model->get_staff_timekeeping_applicable_object(get_staffid_by_permission());
		} else {
			//admin or view global
			$staffs = $this->hr_payroll_model->get_staff_timekeeping_applicable_object();
		}

		//get current month

		$data_object_kpi = [];

		foreach ($staffs as $staff_key => $staff_value) {
			/*check value from database*/
			$data_object_kpi[$staff_key]['staff_id'] = $staff_value['staffid'];

			$staff_i = $this->hr_payroll_model->get_staff_info($staff_value['staffid']);
			if ($staff_i) {

				if ($rel_type == 'hr_records') {
					$data_object_kpi[$staff_key]['employee_number'] = $staff_i->staff_identifi;
				} else {
					$data_object_kpi[$staff_key]['employee_number'] = $this->hr_payroll_model->hrp_format_code('EXS', $staff_i->staffid, 5);
				}

				$data_object_kpi[$staff_key]['employee_name'] = $staff_i->firstname . ' ' . $staff_i->lastname;

				$arr_department = $this->hr_payroll_model->get_staff_departments($staff_i->staffid, true);

				$list_department = '';
				if (count($arr_department) > 0) {

					foreach ($arr_department as $key => $department) {
						$department_value = $this->departments_model->get($department);

						if ($department_value) {
							if (new_strlen($list_department) != 0) {
								$list_department .= ', ' . $department_value->name;
							} else {
								$list_department .= $department_value->name;
							}
						}
					}
				}

				$data_object_kpi[$staff_key]['department_name'] = $list_department;

			} else {
				$data_object_kpi[$staff_key]['employee_number'] = '';
				$data_object_kpi[$staff_key]['employee_name'] = '';
				$data_object_kpi[$staff_key]['department_name'] = '';
			}

			if ($rel_type == 'hr_records') {
				$data_object_kpi[$staff_key]['job_title'] = $staff_value['position_name'];
				$data_object_kpi[$staff_key]['income_tax_number'] = $staff_value['Personal_tax_code'];
				$data_object_kpi[$staff_key]['residential_address'] = $staff_value['resident'];
				$data_object_kpi[$staff_key]['bank_name'] = $staff_value['issue_bank'];
				$data_object_kpi[$staff_key]['account_number'] = $staff_value['account_number'];
				$data_object_kpi[$staff_key]['epf_no'] = $staff_value['epf_no'];
				$data_object_kpi[$staff_key]['social_security_no'] = $staff_value['social_security_no'];
			} else {
				if (isset($employees_value[$staff_value['staffid'] . '_' . $current_month])) {
					$data_object_kpi[$staff_key]['job_title'] = $employees_value[$staff_value['staffid'] . '_' . $current_month]['job_title'];
					$data_object_kpi[$staff_key]['income_tax_number'] = $employees_value[$staff_value['staffid'] . '_' . $current_month]['income_tax_number'];
					$data_object_kpi[$staff_key]['residential_address'] = $employees_value[$staff_value['staffid'] . '_' . $current_month]['residential_address'];
					$data_object_kpi[$staff_key]['bank_name'] = $employees_value[$staff_value['staffid'] . '_' . $current_month]['bank_name'];
					$data_object_kpi[$staff_key]['account_number'] = $employees_value[$staff_value['staffid'] . '_' . $current_month]['account_number'];
					$data_object_kpi[$staff_key]['epf_no'] = $employees_value[$staff_value['staffid'] . '_' . $current_month]['epf_no'];
					$data_object_kpi[$staff_key]['social_security_no'] = $employees_value[$staff_value['staffid'] . '_' . $current_month]['social_security_no'];

				} else {
					$data_object_kpi[$staff_key]['job_title'] = '';
					$data_object_kpi[$staff_key]['income_tax_number'] = '';
					$data_object_kpi[$staff_key]['residential_address'] = '';
					$data_object_kpi[$staff_key]['bank_name'] = '';
					$data_object_kpi[$staff_key]['account_number'] = '';
					$data_object_kpi[$staff_key]['epf_no'] = '';
					$data_object_kpi[$staff_key]['social_security_no'] = '';
				}
			}

			if (isset($employees_value[$staff_value['staffid'] . '_' . $current_month])) {

				$data_object_kpi[$staff_key]['income_rebate_code'] = $employees_value[$staff_value['staffid'] . '_' . $current_month]['income_rebate_code'];
				$data_object_kpi[$staff_key]['income_tax_rate'] = $employees_value[$staff_value['staffid'] . '_' . $current_month]['income_tax_rate'];

				// array merge: staff information + earning list (probationary contract) + earning list (formal)
				if (isset($employees_value[$staff_value['staffid'] . '_' . $current_month]['contract_value'])) {

					$data_object_kpi[$staff_key] = array_merge($data_object_kpi[$staff_key], $employees_value[$staff_value['staffid'] . '_' . $current_month]['contract_value']);
				} else {
					$data_object_kpi[$staff_key] = array_merge($data_object_kpi[$staff_key], $format_employees_value['probationary'], $format_employees_value['formal']);
				}

				$data_object_kpi[$staff_key]['probationary_effective'] = $employees_value[$staff_value['staffid'] . '_' . $current_month]['probationary_effective'];
				$data_object_kpi[$staff_key]['probationary_expiration'] = $employees_value[$staff_value['staffid'] . '_' . $current_month]['probationary_expiration'];
				$data_object_kpi[$staff_key]['primary_effective'] = $employees_value[$staff_value['staffid'] . '_' . $current_month]['primary_effective'];
				$data_object_kpi[$staff_key]['primary_expiration'] = $employees_value[$staff_value['staffid'] . '_' . $current_month]['primary_expiration'];

				$data_object_kpi[$staff_key]['id'] = $employees_value[$staff_value['staffid'] . '_' . $current_month]['id'];


			} else {
				$data_object_kpi[$staff_key]['income_rebate_code'] = 'A';
				$data_object_kpi[$staff_key]['income_tax_rate'] = 'A';

				// array merge: staff information + earning list (probationary contract) + earning list (formal)
				$data_object_kpi[$staff_key] = array_merge($data_object_kpi[$staff_key], $format_employees_value['probationary'], $format_employees_value['formal']);

				$data_object_kpi[$staff_key]['probationary_effective'] = '';
				$data_object_kpi[$staff_key]['probationary_expiration'] = '';
				$data_object_kpi[$staff_key]['primary_effective'] = '';
				$data_object_kpi[$staff_key]['primary_expiration'] = '';

				$data_object_kpi[$staff_key]['id'] = 0;

			}

			$data_object_kpi[$staff_key]['rel_type'] = $rel_type;
		}
		//check is add new or update data
		if (count($employees_value) > 0) {
			$data['button_name'] = _l('hrp_update');
		} else {
			$data['button_name'] = _l('submit');
		}

		$data['departments'] = $this->departments_model->get();
		$data['roles'] = $this->roles_model->get();
		$data['staffs'] = $staffs;

		$data['body_value'] = json_encode($data_object_kpi);
		$data['columns'] = json_encode($format_employees_value['column_format']);
		$data['col_header'] = json_encode($format_employees_value['header']);

		$this->load->view('employees/employees_manage', $data);
	}

	/**
	 * employees filter
	 * @return [type]
	 */
	public function employees_filter() {
		$this->load->model('departments_model');
		$data = $this->input->post();

		$rel_type = hrp_get_hr_profile_status();

		$months_filter = $data['month'];
		$department = $data['department'];
		$staff = '';
		if (isset($data['staff'])) {
			$staff = $data['staff'];
		}
		$role_attendance = '';
		if (isset($data['role_attendance'])) {
			$role_attendance = $data['role_attendance'];
		}

		$newquerystring = $this->render_filter_query($months_filter, $staff, $department, $role_attendance);

		//get current month
		$month_filter = date('Y-m-d', strtotime($data['month'] . '-01'));
		$employees_data = $this->hr_payroll_model->get_employees_data($month_filter, $rel_type);
		$employees_value = [];
		foreach ($employees_data as $key => $value) {
			$employees_value[$value['staff_id'] . '_' . $value['month']] = $value;
		}

		//get employee data for the first
		$format_employees_value = $this->hr_payroll_model->get_format_employees_data($rel_type);

		// data return
		$data_object_kpi = [];
		$index_data_object = 0;
		if ($newquerystring != '') {

			//load deparment by manager
			if (!is_admin() && !has_permission('hrp_employee', '', 'view')) {
				//View own
				$staffs = $this->hr_payroll_model->get_staff_timekeeping_applicable_object(get_staffid_by_permission($newquerystring));
			} else {
				//admin or view global
				$staffs = $this->hr_payroll_model->get_staff_timekeeping_applicable_object($newquerystring);
			}

			$data_object_kpi = [];

			foreach ($staffs as $staff_key => $staff_value) {
				/*check value from database*/
				$data_object_kpi[$staff_key]['staff_id'] = $staff_value['staffid'];

				$staff_i = $this->hr_payroll_model->get_staff_info($staff_value['staffid']);
				if ($staff_i) {

					if ($rel_type == 'hr_records') {
						$data_object_kpi[$staff_key]['employee_number'] = $staff_i->staff_identifi;
					} else {
						$data_object_kpi[$staff_key]['employee_number'] = $this->hr_payroll_model->hrp_format_code('EXS', $staff_i->staffid, 5);
					}

					$data_object_kpi[$staff_key]['employee_name'] = $staff_i->firstname . ' ' . $staff_i->lastname;

					$arr_department = $this->hr_payroll_model->get_staff_departments($staff_i->staffid, true);

					$list_department = '';
					if (count($arr_department) > 0) {

						foreach ($arr_department as $key => $department) {
							$department_value = $this->departments_model->get($department);

							if ($department_value) {
								if (new_strlen($list_department) != 0) {
									$list_department .= ', ' . $department_value->name;
								} else {
									$list_department .= $department_value->name;
								}
							}
						}
					}

					$data_object_kpi[$staff_key]['department_name'] = $list_department;

				} else {
					$data_object_kpi[$staff_key]['employee_number'] = '';
					$data_object_kpi[$staff_key]['employee_name'] = '';
					$data_object_kpi[$staff_key]['department_name'] = '';
				}

				if ($rel_type == 'hr_records') {
					$data_object_kpi[$staff_key]['job_title'] = $staff_value['position_name'];
					$data_object_kpi[$staff_key]['income_tax_number'] = $staff_value['Personal_tax_code'];
					$data_object_kpi[$staff_key]['residential_address'] = $staff_value['resident'];
					$data_object_kpi[$staff_key]['bank_name'] = $staff_value['issue_bank'];
					$data_object_kpi[$staff_key]['account_number'] = $staff_value['account_number'];
					$data_object_kpi[$staff_key]['epf_no'] = $staff_value['epf_no'];
					$data_object_kpi[$staff_key]['social_security_no'] = $staff_value['social_security_no'];
				} else {
					if (isset($employees_value[$staff_value['staffid'] . '_' . $month_filter])) {
						$data_object_kpi[$staff_key]['job_title'] = $employees_value[$staff_value['staffid'] . '_' . $month_filter]['job_title'];
						$data_object_kpi[$staff_key]['income_tax_number'] = $employees_value[$staff_value['staffid'] . '_' . $month_filter]['income_tax_number'];
						$data_object_kpi[$staff_key]['residential_address'] = $employees_value[$staff_value['staffid'] . '_' . $month_filter]['residential_address'];
						$data_object_kpi[$staff_key]['bank_name'] = $employees_value[$staff_value['staffid'] . '_' . $month_filter]['bank_name'];
						$data_object_kpi[$staff_key]['account_number'] = $employees_value[$staff_value['staffid'] . '_' . $month_filter]['account_number'];
						$data_object_kpi[$staff_key]['epf_no'] = $employees_value[$staff_value['staffid'] . '_' . $month_filter]['epf_no'];
						$data_object_kpi[$staff_key]['social_security_no'] = $employees_value[$staff_value['staffid'] . '_' . $month_filter]['social_security_no'];

					} else {
						$data_object_kpi[$staff_key]['job_title'] = '';
						$data_object_kpi[$staff_key]['income_tax_number'] = '';
						$data_object_kpi[$staff_key]['residential_address'] = '';
						$data_object_kpi[$staff_key]['bank_name'] = '';
						$data_object_kpi[$staff_key]['account_number'] = '';
						$data_object_kpi[$staff_key]['epf_no'] = '';
						$data_object_kpi[$staff_key]['social_security_no'] = '';
					}
				}

				if (isset($employees_value[$staff_value['staffid'] . '_' . $month_filter])) {

					$data_object_kpi[$staff_key]['income_rebate_code'] = $employees_value[$staff_value['staffid'] . '_' . $month_filter]['income_rebate_code'];
					$data_object_kpi[$staff_key]['income_tax_rate'] = $employees_value[$staff_value['staffid'] . '_' . $month_filter]['income_tax_rate'];

					$data_object_kpi[$staff_key]['probationary_effective'] = $employees_value[$staff_value['staffid'] . '_' . $month_filter]['probationary_effective'];
					$data_object_kpi[$staff_key]['probationary_expiration'] = $employees_value[$staff_value['staffid'] . '_' . $month_filter]['probationary_expiration'];
					$data_object_kpi[$staff_key]['primary_effective'] = $employees_value[$staff_value['staffid'] . '_' . $month_filter]['primary_effective'];
					$data_object_kpi[$staff_key]['primary_expiration'] = $employees_value[$staff_value['staffid'] . '_' . $month_filter]['primary_expiration'];

					// array merge: staff information + earning list (probationary contract) + earning list (formal)
					if (isset($employees_value[$staff_value['staffid'] . '_' . $month_filter]['contract_value'])) {

						$data_object_kpi[$staff_key] = array_merge($data_object_kpi[$staff_key], $employees_value[$staff_value['staffid'] . '_' . $month_filter]['contract_value']);
					} else {
						$data_object_kpi[$staff_key] = array_merge($data_object_kpi[$staff_key], $format_employees_value['probationary'], $format_employees_value['formal']);
					}

					$data_object_kpi[$staff_key]['id'] = $employees_value[$staff_value['staffid'] . '_' . $month_filter]['id'];


				} else {
					$data_object_kpi[$staff_key]['income_rebate_code'] = 'A';
					$data_object_kpi[$staff_key]['income_tax_rate'] = 'A';

					// array merge: staff information + earning list (probationary contract) + earning list (formal)
					$data_object_kpi[$staff_key] = array_merge($data_object_kpi[$staff_key], $format_employees_value['probationary'], $format_employees_value['formal']);

					$data_object_kpi[$staff_key]['id'] = 0;

				}

				$data_object_kpi[$staff_key]['rel_type'] = $rel_type;
			}

		}

		//check is add new or update data
		if (count($employees_value) > 0) {
			$button_name = _l('hrp_update');
		} else {
			$button_name = _l('submit');
		}

		echo json_encode([
			'data_object' => $data_object_kpi,
			'button_name' => $button_name,
		]);
		die;
	}

	/**
	 * add manage employees
	 */
	public function add_manage_employees() {
		if (!has_permission('hrp_employee', '', 'create') && !has_permission('hrp_employee', '', 'edit') && !is_admin()) {
			access_denied('hrp_employee');
		}

		if ($this->input->post()) {
			$data = $this->input->post();
			if ($data['hrp_employees_rel_type'] == 'synchronization') {
				//synchronization
				$success = $this->hr_payroll_model->employees_synchronization($data);
			} elseif ($data['hrp_employees_rel_type'] == 'update') {
				// update data
				$success = $this->hr_payroll_model->employees_update($data);
			} else {
				$success = false;
			}

			if ($success) {
				set_alert('success', _l('updated_successfully'));
			} else {
				set_alert('success', _l('updated_successfully'));
			}

			redirect(admin_url('hr_payroll/manage_employees'));
		}

	}

	/**
	 * render filter query
	 * @param  [type] $data_month
	 * @param  [type] $data_staff
	 * @param  [type] $data_department
	 * @param  [type] $data_role_attendance
	 * @return [type]
	 */
	public function render_filter_query($data_month, $data_staff, $data_department, $data_role_attendance) {

		$months_filter = $data_month;
		$querystring = ' active=1';
		$department = $data_department;

		$staff = '';
		if (isset($data_staff)) {
			$staff = $data_staff;
		}
		$staff_querystring = '';
		$department_querystring = '';
		$role_querystring = '';

		if ($department != '') {
			$arrdepartment = $this->staff_model->get('', 'staffid in (select tblstaff_departments.staffid from tblstaff_departments where departmentid = ' . $department . ')');
			$temp = '';
			foreach ($arrdepartment as $value) {
				$temp = $temp . $value['staffid'] . ',';
			}
			$temp = rtrim($temp, ",");
			$department_querystring = 'FIND_IN_SET(staffid, "' . $temp . '")';
		}

		if ($staff != '') {
			$temp = '';
			$araylengh = count($staff);
			for ($i = 0; $i < $araylengh; $i++) {
				$temp = $temp . $staff[$i];
				if ($i != $araylengh - 1) {
					$temp = $temp . ',';
				}
			}
			$staff_querystring = 'FIND_IN_SET(staffid, "' . $temp . '")';
		}

		if (isset($data_role_attendance) && $data_role_attendance != '') {
			$temp = '';
			$araylengh = count($data_role_attendance);
			for ($i = 0; $i < $araylengh; $i++) {
				$temp = $temp . $data_role_attendance[$i];
				if ($i != $araylengh - 1) {
					$temp = $temp . ',';
				}
			}
			$role_querystring = 'FIND_IN_SET(role, "' . $temp . '")';
		}

		$arrQuery = array($staff_querystring, $department_querystring, $querystring, $role_querystring);

		$newquerystring = '';
		foreach ($arrQuery as $string) {
			if ($string != '') {
				$newquerystring = $newquerystring . $string . ' AND ';
			}
		}

		$newquerystring = rtrim($newquerystring, "AND ");
		if ($newquerystring == '') {
			$newquerystring = [];
		}

		return $newquerystring;
	}

	/**
	 * manage attendance
	 * @return [type]
	 */
	public function manage_attendance() {
		if (!has_permission('hrp_attendance', '', 'view') && !has_permission('hrp_attendance', '', 'view_own') && !is_admin()) {
			access_denied('hrp_attendance');
		}

		$this->load->model('staff_model');
		$this->load->model('departments_model');

		$rel_type = hrp_get_timesheets_status();

		//get current month
		$current_month = date('Y-m-d', strtotime(date('Y-m') . '-01'));

		//get day header in month
		$days_header_in_month = $this->hr_payroll_model->get_day_header_in_month($current_month, $rel_type);

		$attendances = $this->hr_payroll_model->get_hrp_attendance($current_month);
		$attendances_value = [];

		foreach ($attendances as $key => $value) {
			$attendances_value[$value['staff_id'] . '_' . $value['month']] = $value;
		}

		//load deparment by manager
		if (!is_admin() && !has_permission('hrp_employee', '', 'view')) {
			//View own
			$staffs = $this->hr_payroll_model->get_staff_timekeeping_applicable_object(get_staffid_by_permission());
		} else {
			//admin or view global
			$staffs = $this->hr_payroll_model->get_staff_timekeeping_applicable_object();
		}

		$data_object_kpi = [];

		foreach ($staffs as $staff_key => $staff_value) {
			/*check value from database*/

			$staff_i = $this->hr_payroll_model->get_staff_info($staff_value['staffid']);
			if ($staff_i) {

				if (isset($staff_i->staff_identifi)) {
					$data_object_kpi[$staff_key]['hr_code'] = $staff_i->staff_identifi;
				} else {
					$data_object_kpi[$staff_key]['hr_code'] = $this->hr_payroll_model->hrp_format_code('EXS', $staff_i->staffid, 5);
				}

				$data_object_kpi[$staff_key]['staff_name'] = $staff_i->firstname . ' ' . $staff_i->lastname;

				$arr_department = $this->hr_payroll_model->get_staff_departments($staff_i->staffid, true);

				$list_department = '';
				if (count($arr_department) > 0) {

					foreach ($arr_department as $key => $department) {
						$department_value = $this->departments_model->get($department);

						if ($department_value) {
							if (new_strlen($list_department) != 0) {
								$list_department .= ', ' . $department_value->name;
							} else {
								$list_department .= $department_value->name;
							}
						}

					}
				}

				$data_object_kpi[$staff_key]['staff_departments'] = $list_department;

			} else {
				$data_object_kpi[$staff_key]['hr_code'] = '';
				$data_object_kpi[$staff_key]['staff_name'] = '';
				$data_object_kpi[$staff_key]['staff_departments'] = '';

			}

			if (isset($attendances_value[$staff_value['staffid'] . '_' . $current_month])) {

				$data_object_kpi[$staff_key]['standard_workday'] = $attendances_value[$staff_value['staffid'] . '_' . $current_month]['standard_workday'];
				$data_object_kpi[$staff_key]['actual_workday'] = $attendances_value[$staff_value['staffid'] . '_' . $current_month]['actual_workday'];
				$data_object_kpi[$staff_key]['actual_workday_probation'] = $attendances_value[$staff_value['staffid'] . '_' . $current_month]['actual_workday_probation'];
				$data_object_kpi[$staff_key]['paid_leave'] = $attendances_value[$staff_value['staffid'] . '_' . $current_month]['paid_leave'];
				$data_object_kpi[$staff_key]['unpaid_leave'] = $attendances_value[$staff_value['staffid'] . '_' . $current_month]['unpaid_leave'];
				$data_object_kpi[$staff_key]['id'] = $attendances_value[$staff_value['staffid'] . '_' . $current_month]['id'];

				$data_object_kpi[$staff_key] = array_merge($data_object_kpi[$staff_key], $attendances_value[$staff_value['staffid'] . '_' . $current_month]);

			} else {
				$data_object_kpi[$staff_key]['standard_workday'] = get_hr_payroll_option('standard_working_time');
				$data_object_kpi[$staff_key]['actual_workday_probation'] = 0;
				$data_object_kpi[$staff_key]['actual_workday'] = 0;
				$data_object_kpi[$staff_key]['paid_leave'] = 0;
				$data_object_kpi[$staff_key]['unpaid_leave'] = 0;
				$data_object_kpi[$staff_key]['id'] = 0;
				$data_object_kpi[$staff_key] = array_merge($data_object_kpi[$staff_key], $days_header_in_month['days_header']);

			}
			$data_object_kpi[$staff_key]['rel_type'] = $rel_type;
			$data_object_kpi[$staff_key]['month'] = $current_month;
			$data_object_kpi[$staff_key]['staff_id'] = $staff_value['staffid'];

		}

		//check is add new or update data
		if (count($attendances_value) > 0) {
			$data['button_name'] = _l('hrp_update');
		} else {
			$data['button_name'] = _l('submit');
		}

		$data['departments'] = $this->departments_model->get();
		$data['roles'] = $this->roles_model->get();
		$data['staffs'] = $staffs;
		$data['data_object_kpi'] = $data_object_kpi;

		$data['body_value'] = json_encode($data_object_kpi);
		$data['columns'] = json_encode($days_header_in_month['columns_type']);
		$data['col_header'] = json_encode($days_header_in_month['headers']);

		$this->load->view('attendances/attendance_manage', $data);
	}

	/**
	 * add attendance
	 */
	public function add_attendance() {
		if (!has_permission('hrp_attendance', '', 'create') && !has_permission('hrp_attendance', '', 'edit') && !is_admin()) {
			access_denied('hrp_attendance');
		}

		if ($this->input->post()) {
			$data = $this->input->post();
			if (isset($data)) {

				if ($data['hrp_attendance_rel_type'] == 'update') {
					$success = $this->hr_payroll_model->add_update_attendance($data);
				} elseif ($data['hrp_attendance_rel_type'] == 'synchronization') {
					$success = $this->hr_payroll_model->synchronization_attendance($data);
				} else {
					$success = false;
				}

				if ($success) {
					set_alert('success', _l('hrp_updated_successfully'));
				} else {
					set_alert('success', _l('hrp_updated_successfully'));
				}
				redirect(admin_url('hr_payroll/manage_attendance'));
			}

		}
	}

	/**
	 * import xlsx employees
	 * @return [type]
	 */
	public function import_xlsx_employees() {
		if (!has_permission('hrp_employee', '', 'create') && !has_permission('hrp_employee', '', 'edit') && !is_admin()) {
			access_denied('hrp_employee');
		}

		$this->load->model('staff_model');
		$data_staff = $this->staff_model->get(get_staff_user_id());
		/*get language active*/
		if ($data_staff) {
			if ($data_staff->default_language != '') {
				$data['active_language'] = $data_staff->default_language;
			} else {
				$data['active_language'] = get_option('active_language');
			}

		} else {
			$data['active_language'] = get_option('active_language');
		}

		$this->load->view('hr_payroll/employees/import_employees', $data);
	}

	/**
	 * create employees sample file
	 * @return [type]
	 */
	public function create_employees_sample_file() {
		if (!has_permission('hrp_employee', '', 'create') && !has_permission('hrp_employee', '', 'edit') && !is_admin()) {
			access_denied('hrp_employee');
		}

		$this->load->model('staff_model');
		$this->load->model('departments_model');

		$month_employees = $this->input->post('month_employees');

		if (!class_exists('XLSXReader_fin')) {
			require_once module_dir_path(HR_PAYROLL_MODULE_NAME) . '/assets/plugins/XLSXReader/XLSXReader.php';
		}
		require_once module_dir_path(HR_PAYROLL_MODULE_NAME) . '/assets/plugins/XLSXWriter/xlsxwriter.class.php';

		$this->delete_error_file_day_before('1', HR_PAYROLL_CREATE_EMPLOYEES_SAMPLE);

		//get current month
		$rel_type = hrp_get_hr_profile_status();
		$month_filter = date('Y-m-d', strtotime($month_employees . '-01'));

		$employees_data = $this->hr_payroll_model->get_employees_data($month_filter, $rel_type);
		$employees_value = [];
		foreach ($employees_data as $key => $value) {
			$employees_value[$value['staff_id'] . '_' . $value['month']] = $value;
		}

		//get employee data for the first
		$format_employees_value = $this->hr_payroll_model->get_format_employees_data($rel_type);
		$staff_information_key = $format_employees_value['staff_information'];
		$probationary_key = $format_employees_value['probationary_key'];
		$primary_key = $format_employees_value['primary_key'];
		$staff_probationary_key = array_keys($format_employees_value['probationary']);
		$staff_formal_key = array_keys($format_employees_value['formal']);

		$header_key = array_merge($staff_information_key, $staff_probationary_key, $probationary_key, $staff_formal_key, $primary_key);

		//Writer file
		//create header value
		$writer_header = [];
		$widths = [];

		$writer_header[_l('month')] = 'string';
		$widths[] = 30;

		foreach ($format_employees_value['header'] as $header_value) {
			$writer_header[$header_value] = 'string';
			$widths[] = 30;
		}

		$writer = new XLSXWriter();

		$col_style1 = [0, 1, 2, 3, 4, 5, 7];
		$style1 = ['widths' => $widths, 'fill' => '#ff9800', 'font-style' => 'bold', 'color' => '#0a0a0a', 'border' => 'left,right,top,bottom', 'border-color' => '#0a0a0a', 'font-size' => 13];

		$writer->writeSheetHeader_v2('Sheet1', $writer_header, $col_options = ['widths' => $widths, 'fill' => '#03a9f46b', 'font-style' => 'bold', 'color' => '#0a0a0a', 'border' => 'left,right,top,bottom', 'border-color' => '#0a0a0a', 'font-size' => 13],
			$col_style1, $style1);

		//load deparment by manager
		if (!is_admin() && !has_permission('hrp_employee', '', 'view')) {
			//View own
			$staffs = $this->hr_payroll_model->get_staff_timekeeping_applicable_object(get_staffid_by_permission());
		} else {
			//admin or view global
			$staffs = $this->hr_payroll_model->get_staff_timekeeping_applicable_object();
		}

		foreach ($staffs as $staff_key => $staff_value) {
			$data_object_kpi = [];

			/*check value from database*/
			$data_object_kpi['staff_id'] = $staff_value['staffid'];

			if ($rel_type == 'hr_records') {
				$data_object_kpi['employee_number'] = $staff_value['staff_identifi'];
			} else {
				$data_object_kpi['employee_number'] = $this->hr_payroll_model->hrp_format_code('EXS', $staff_value['staffid'], 5);
			}

			$data_object_kpi['employee_name'] = $staff_value['firstname'] . ' ' . $staff_value['lastname'];

			$arr_department = $this->hr_payroll_model->get_staff_departments($staff_value['staffid'], true);

			$list_department = '';
			if (count($arr_department) > 0) {

				foreach ($arr_department as $key => $department) {
					$department_value = $this->departments_model->get($department);

					if ($department_value) {
						if (new_strlen($list_department) != 0) {
							$list_department .= ', ' . $department_value->name;
						} else {
							$list_department .= $department_value->name;
						}
					}
				}
			}

			$data_object_kpi['department_name'] = $list_department;

			if ($rel_type == 'hr_records') {
				$data_object_kpi['job_title'] = $staff_value['position_name'];
				$data_object_kpi['income_tax_number'] = $staff_value['Personal_tax_code'];
				$data_object_kpi['residential_address'] = $staff_value['resident'];
			} else {
				if (isset($employees_value[$staff_value['staffid'] . '_' . $month_filter])) {
					$data_object_kpi['job_title'] = $employees_value[$staff_value['staffid'] . '_' . $month_filter]['job_title'];
					$data_object_kpi['income_tax_number'] = $employees_value[$staff_value['staffid'] . '_' . $month_filter]['income_tax_number'];
					$data_object_kpi['residential_address'] = $employees_value[$staff_value['staffid'] . '_' . $month_filter]['residential_address'];

				} else {
					$data_object_kpi['job_title'] = '';
					$data_object_kpi['income_tax_number'] = '';
					$data_object_kpi['residential_address'] = '';
				}
			}

			if (isset($employees_value[$staff_value['staffid'] . '_' . $month_filter])) {

				$data_object_kpi['income_rebate_code'] = $employees_value[$staff_value['staffid'] . '_' . $month_filter]['income_rebate_code'];
				$data_object_kpi['income_tax_rate'] = $employees_value[$staff_value['staffid'] . '_' . $month_filter]['income_tax_rate'];
				$data_object_kpi['bank_name'] = $employees_value[$staff_value['staffid'] . '_' . $month_filter]['bank_name'];
				$data_object_kpi['account_number'] = $employees_value[$staff_value['staffid'] . '_' . $month_filter]['account_number'];

				// array merge: staff information + earning list (probationary contract) + earning list (formal)
				if (isset($employees_value[$staff_value['staffid'] . '_' . $month_filter]['contract_value'])) {
					$data_object_kpi = array_merge($data_object_kpi, $employees_value[$staff_value['staffid'] . '_' . $month_filter]['contract_value']);
				}

				$data_object_kpi['probationary_effective'] = $employees_value[$staff_value['staffid'] . '_' . $month_filter]['probationary_effective'];
				$data_object_kpi['probationary_expiration'] = $employees_value[$staff_value['staffid'] . '_' . $month_filter]['probationary_expiration'];
				$data_object_kpi['primary_effective'] = $employees_value[$staff_value['staffid'] . '_' . $month_filter]['primary_effective'];
				$data_object_kpi['primary_expiration'] = $employees_value[$staff_value['staffid'] . '_' . $month_filter]['primary_expiration'];

				$data_object_kpi['id'] = $employees_value[$staff_value['staffid'] . '_' . $month_filter]['id'];

			} else {
				$data_object_kpi['income_rebate_code'] = 'A';
				$data_object_kpi['income_tax_rate'] = 'A';
				$data_object_kpi['bank_name'] = '';
				$data_object_kpi['account_number'] = '';

				// array merge: staff information + earning list (probationary contract) + earning list (formal)
				$data_object_kpi = array_merge($data_object_kpi, $format_employees_value['probationary'], $format_employees_value['formal']);

				$data_object_kpi['probationary_effective'] = '';
				$data_object_kpi['probationary_expiration'] = '';
				$data_object_kpi['primary_effective'] = '';
				$data_object_kpi['primary_expiration'] = '';

				$data_object_kpi['id'] = 0;

			}

			$data_object_kpi['rel_type'] = $rel_type;

			$data_object = array_values($data_object_kpi);
			$temp = [];
			$temp['month'] = $month_filter;
			foreach ($header_key as $_key) {
				$temp[] = isset($data_object_kpi[$_key]) ? $data_object_kpi[$_key] : '';
			}

			if ($staff_key == 0) {
				$writer->writeSheetRow('Sheet1', array_merge([0 => 'month'], $header_key));
			}
			$writer->writeSheetRow('Sheet1', $temp);

		}

		$filename = 'employees_sample_file' . get_staff_user_id() . '_' . strtotime(date('Y-m-d H:i:s')) . '.xlsx';
		$writer->writeToFile(new_str_replace($filename, HR_PAYROLL_CREATE_EMPLOYEES_SAMPLE . $filename, $filename));

		echo json_encode([
			'success' => true,
			'site_url' => site_url(),
			'staff_id' => get_staff_user_id(),
			'filename' => HR_PAYROLL_CREATE_EMPLOYEES_SAMPLE . $filename,
		]);

	}

	/**
	 * import employees excel
	 * @return [type]
	 */
	public function import_employees_excel() {
		if (!has_permission('hrp_employee', '', 'create') && !has_permission('hrp_employee', '', 'edit') && !is_admin()) {
			access_denied('hrp_employee');
		}

		if (!class_exists('XLSXReader_fin')) {
			require_once module_dir_path(HR_PAYROLL_MODULE_NAME) . '/assets/plugins/XLSXReader/XLSXReader.php';
		}
		require_once module_dir_path(HR_PAYROLL_MODULE_NAME) . '/assets/plugins/XLSXWriter/xlsxwriter.class.php';

		$filename = '';
		if ($this->input->post()) {
			if (isset($_FILES['file_csv']['name']) && $_FILES['file_csv']['name'] != '') {

				$this->delete_error_file_day_before();
				$rel_type = hrp_get_hr_profile_status();

				// Get the temp file path
				$tmpFilePath = $_FILES['file_csv']['tmp_name'];
				// Make sure we have a filepath
				if (!empty($tmpFilePath) && $tmpFilePath != '') {
					$rows = [];
					$arr_insert = [];

					$tmpDir = TEMP_FOLDER . '/' . time() . uniqid() . '/';

					if (!file_exists(TEMP_FOLDER)) {
						mkdir(TEMP_FOLDER, 0755);
					}

					if (!file_exists($tmpDir)) {
						mkdir($tmpDir, 0755);
					}

					// Setup our new file path
					$newFilePath = $tmpDir . $_FILES['file_csv']['name'];

					if (move_uploaded_file($tmpFilePath, $newFilePath)) {
						//Writer file
						$writer_header = array(
							_l('staffid') => 'string',
							_l('id') => 'string',
							_l('hr_code') => 'string',
							_l('staff_name') => 'string',
							_l('department') => 'string',
							_l('integration_actual_workday') => 'string',
							_l('integration_paid_leave') => 'string',
							_l('integration_unpaid_leave') => 'string',
							_l('standard_working_time_of_month') => 'string',
							_l('month') => 'string',
							_l('error') => 'string',
						);

						$writer = new XLSXWriter();
						$writer->writeSheetHeader('Sheet1', $writer_header, $col_options = ['widths' => [40, 40, 40, 50, 40, 40, 40, 40, 50, 50]]);

						//Reader file
						$xlsx = new XLSXReader_fin($newFilePath);
						$sheetNames = $xlsx->getSheetNames();
						$data = $xlsx->getSheetData($sheetNames[1]);
						$arr_header = [];

						$arr_header['staff_id'] = 0;
						$arr_header['id'] = 1;
						$arr_header['hr_code'] = 2;
						$arr_header['staff_name'] = 3;
						$arr_header['staff_departments'] = 4;
						$arr_header['actual_workday'] = 5;
						$arr_header['paid_leave'] = 6;
						$arr_header['unpaid_leave'] = 7;
						$arr_header['standard_workday'] = 8;
						$arr_header['month'] = 9;

						$total_rows = 0;
						$total_row_false = 0;

						$column_key = $data[1];

						for ($row = 2; $row < count($data); $row++) {

							$total_rows++;

							$rd = array();
							$flag = 0;
							$flag2 = 0;

							$string_error = '';

							$flag_staff_id = 0;

							if (($flag == 1) || $flag2 == 1) {
								//write error file
								$writer->writeSheetRow('Sheet1', [

								]);

								$total_row_false++;
							}

							if ($flag == 0 && $flag2 == 0) {

								$rd = array_combine($column_key, $data[$row]);
								unset($rd['employee_number']);
								unset($rd['employee_name']);
								unset($rd['department_name']);

								array_push($arr_insert, $rd);

							}

						}

						//insert batch
						if (count($arr_insert) > 0) {
							$this->hr_payroll_model->import_employees_data($arr_insert);
						}

						$total_rows = $total_rows;
						$total_row_success = isset($rows) ? count($rows) : 0;
						$dataerror = '';
						$message = 'Not enought rows for importing';

						if ($total_row_false != 0) {
							$filename = 'Import_attendance_error_' . get_staff_user_id() . '_' . strtotime(date('Y-m-d H:i:s')) . '.xlsx';
							$writer->writeToFile(new_str_replace($filename, HR_PAYROLL_ERROR . $filename, $filename));
						}

					}
				}
			}
		}

		if (file_exists($newFilePath)) {
			@unlink($newFilePath);
		}

		echo json_encode([
			'message' => $message,
			'total_row_success' => $total_row_success,
			'total_row_false' => $total_row_false,
			'total_rows' => $total_rows,
			'site_url' => site_url(),
			'staff_id' => get_staff_user_id(),
			'filename' => HR_PAYROLL_ERROR . $filename,
		]);
	}

	/**
	 * attendance filter
	 * @return [type]
	 */
	public function attendance_filter() {
		$this->load->model('departments_model');
		$data = $this->input->post();

		$rel_type = hrp_get_timesheets_status();

		$months_filter = $data['month'];

		$querystring = ' active=1';
		$department = $data['department'];

		$staff = '';
		if (isset($data['staff'])) {
			$staff = $data['staff'];
		}
		$staff_querystring = '';
		$department_querystring = '';
		$role_querystring = '';

		if ($department != '') {
			$arrdepartment = $this->staff_model->get('', 'staffid in (select tblstaff_departments.staffid from tblstaff_departments where departmentid = ' . $department . ')');
			$temp = '';
			foreach ($arrdepartment as $value) {
				$temp = $temp . $value['staffid'] . ',';
			}
			$temp = rtrim($temp, ",");
			$department_querystring = 'FIND_IN_SET(staffid, "' . $temp . '")';
		}

		if ($staff != '') {
			$temp = '';
			$araylengh = count($staff);
			for ($i = 0; $i < $araylengh; $i++) {
				$temp = $temp . $staff[$i];
				if ($i != $araylengh - 1) {
					$temp = $temp . ',';
				}
			}
			$staff_querystring = 'FIND_IN_SET(staffid, "' . $temp . '")';
		}

		if (isset($data['role_attendance'])) {
			$temp = '';
			$araylengh = count($data['role_attendance']);
			for ($i = 0; $i < $araylengh; $i++) {
				$temp = $temp . $data['role_attendance'][$i];
				if ($i != $araylengh - 1) {
					$temp = $temp . ',';
				}
			}
			$role_querystring = 'FIND_IN_SET(role, "' . $temp . '")';
		}

		$arrQuery = array($staff_querystring, $department_querystring, $querystring, $role_querystring);

		$newquerystring = '';
		foreach ($arrQuery as $string) {
			if ($string != '') {
				$newquerystring = $newquerystring . $string . ' AND ';
			}
		}

		$newquerystring = rtrim($newquerystring, "AND ");
		if ($newquerystring == '') {
			$newquerystring = [];
		}

		$month_filter = date('Y-m-d', strtotime($data['month'] . '-01'));
		//get day header in month
		$days_header_in_month = $this->hr_payroll_model->get_day_header_in_month($month_filter, $rel_type);

		$attendances = $this->hr_payroll_model->get_hrp_attendance($month_filter);
		$attendances_value = [];
		foreach ($attendances as $key => $value) {
			$attendances_value[$value['staff_id'] . '_' . $value['month']] = $value;
		}

		// data return
		$data_object_kpi = [];
		$index_data_object = 0;
		if ($newquerystring != '') {

			//load staff
			if (!is_admin() && !has_permission('hrp_employee', '', 'view')) {
				//View own
				$staffs = $this->hr_payroll_model->get_staff_timekeeping_applicable_object(get_staffid_by_permission($newquerystring));
			} else {
				//admin or view global
				$staffs = $this->hr_payroll_model->get_staff_timekeeping_applicable_object($newquerystring);
			}

			foreach ($staffs as $staff_key => $staff_value) {

				/*check value from database*/
				$data_object_kpi[$staff_key]['staff_id'] = $staff_value['staffid'];

				$staff_i = $this->hr_payroll_model->get_staff_info($staff_value['staffid']);
				if ($staff_i) {

					if (isset($staff_i->staff_identifi)) {
						$data_object_kpi[$staff_key]['hr_code'] = $staff_i->staff_identifi;
					} else {
						$data_object_kpi[$staff_key]['hr_code'] = $this->hr_payroll_model->hrp_format_code('EXS', $staff_i->staffid, 5);
					}

					$data_object_kpi[$staff_key]['staff_name'] = $staff_i->firstname . ' ' . $staff_i->lastname;

					$arr_department = $this->hr_payroll_model->get_staff_departments($staff_i->staffid, true);

					$list_department = '';
					if (count($arr_department) > 0) {

						foreach ($arr_department as $key => $department) {
							$department_value = $this->departments_model->get($department);

							if ($department_value) {
								if (new_strlen($list_department) != 0) {
									$list_department .= ', ' . $department_value->name;
								} else {
									$list_department .= $department_value->name;
								}
							}

						}
					}

					$data_object_kpi[$staff_key]['staff_departments'] = $list_department;

				} else {
					$data_object_kpi[$staff_key]['hr_code'] = '';
					$data_object_kpi[$staff_key]['staff_name'] = '';
					$data_object_kpi[$staff_key]['staff_departments'] = '';

				}

				if (isset($attendances_value[$staff_value['staffid'] . '_' . $month_filter])) {

					$data_object_kpi[$staff_key]['standard_workday'] = $attendances_value[$staff_value['staffid'] . '_' . $month_filter]['standard_workday'];
					$data_object_kpi[$staff_key]['actual_workday_probation'] = $attendances_value[$staff_value['staffid'] . '_' . $month_filter]['actual_workday_probation'];
					$data_object_kpi[$staff_key]['actual_workday'] = $attendances_value[$staff_value['staffid'] . '_' . $month_filter]['actual_workday'];
					$data_object_kpi[$staff_key]['paid_leave'] = $attendances_value[$staff_value['staffid'] . '_' . $month_filter]['paid_leave'];
					$data_object_kpi[$staff_key]['unpaid_leave'] = $attendances_value[$staff_value['staffid'] . '_' . $month_filter]['unpaid_leave'];
					$data_object_kpi[$staff_key]['id'] = $attendances_value[$staff_value['staffid'] . '_' . $month_filter]['id'];
					$data_object_kpi[$staff_key] = array_merge($data_object_kpi[$staff_key], $attendances_value[$staff_value['staffid'] . '_' . $month_filter]);

				} else {
					$data_object_kpi[$staff_key]['standard_workday'] = get_hr_payroll_option('standard_working_time');
					$data_object_kpi[$staff_key]['actual_workday_probation'] = 0;
					$data_object_kpi[$staff_key]['actual_workday'] = 0;
					$data_object_kpi[$staff_key]['paid_leave'] = 0;
					$data_object_kpi[$staff_key]['unpaid_leave'] = 0;
					$data_object_kpi[$staff_key]['id'] = 0;
					$data_object_kpi[$staff_key] = array_merge($data_object_kpi[$staff_key], $days_header_in_month['days_header']);

				}

				$data_object_kpi[$staff_key]['rel_type'] = $rel_type;
				$data_object_kpi[$staff_key]['month'] = $month_filter;

			}

		}

		//check is add new or update data
		if (count($attendances_value) > 0) {
			$button_name = _l('hrp_update');
		} else {
			$button_name = _l('submit');
		}

		echo json_encode([
			'data_object' => $data_object_kpi,
			'columns' => $days_header_in_month['columns_type'],
			'col_header' => $days_header_in_month['headers'],
			'button_name' => $button_name,
		]);
		die;
	}

	/**
	 * import xlsx attendance
	 * @return [type]
	 */
	public function import_xlsx_attendance() {
		$this->load->model('staff_model');
		$data_staff = $this->staff_model->get(get_staff_user_id());
		/*get language active*/
		if ($data_staff) {
			if ($data_staff->default_language != '') {
				$data['active_language'] = $data_staff->default_language;
			} else {
				$data['active_language'] = get_option('active_language');
			}

		} else {
			$data['active_language'] = get_option('active_language');
		}

		$this->load->view('hr_payroll/attendances/import_attendance', $data);
	}

	/**
	 * create attendance sample file
	 * @return [type]
	 */
	public function create_attendance_sample_file() {
		$this->load->model('staff_model');
		$this->load->model('departments_model');

		$month_attendance = $this->input->post('month_attendance');

		if (!class_exists('XLSXReader_fin')) {
			require_once module_dir_path(HR_PAYROLL_MODULE_NAME) . '/assets/plugins/XLSXReader/XLSXReader.php';
		}
		require_once module_dir_path(HR_PAYROLL_MODULE_NAME) . '/assets/plugins/XLSXWriter/xlsxwriter.class.php';

		$this->delete_error_file_day_before('1', HR_PAYROLL_CREATE_ATTENDANCE_SAMPLE);

		$rel_type = hrp_get_timesheets_status();
		//get attendance data
		$current_month = date('Y-m-d', strtotime($month_attendance . '-01'));
		//get day header in month
		$days_header_in_month = $this->hr_payroll_model->get_day_header_in_month($current_month, $rel_type);
		$header_key = array_merge($days_header_in_month['staff_key'], $days_header_in_month['days_key'], $days_header_in_month['attendance_key']);

		$attendances = $this->hr_payroll_model->get_hrp_attendance($current_month);
		$attendances_value = [];
		foreach ($attendances as $key => $value) {
			$attendances_value[$value['staff_id'] . '_' . $value['month']] = $value;
		}

		//load staff
		if (!is_admin() && !has_permission('hrp_employee', '', 'view')) {
			//View own
			$staffs = $this->hr_payroll_model->get_staff_timekeeping_applicable_object(get_staffid_by_permission());
		} else {
			//admin or view global
			$staffs = $this->hr_payroll_model->get_staff_timekeeping_applicable_object();
		}

		//Writer file
		$writer_header = [];
		$widths = [];
		foreach ($days_header_in_month['headers'] as $value) {
			$writer_header[$value] = 'string';
			$widths[] = 30;
		}

		$writer = new XLSXWriter();

		$col_style1 = [0, 1, 2, 3, 4, 5, 6];
		$style1 = ['widths' => $widths, 'fill' => '#ff9800', 'font-style' => 'bold', 'color' => '#0a0a0a', 'border' => 'left,right,top,bottom', 'border-color' => '#0a0a0a', 'font-size' => 13];

		$writer->writeSheetHeader_v2('Sheet1', $writer_header, $col_options = ['widths' => $widths, 'fill' => '#03a9f46b', 'font-style' => 'bold', 'color' => '#0a0a0a', 'border' => 'left,right,top,bottom', 'border-color' => '#0a0a0a', 'font-size' => 13],
			$col_style1, $style1);

		$data_object_kpi = [];
		foreach ($staffs as $staff_key => $staff_value) {
			$data_object_kpi = [];
			$staffid = 0;
			$hr_code = '';
			$id = 0;
			$staff_name = '';
			$staff_departments = '';
			$actual_workday_probation = 0;
			$actual_workday = 0;
			$paid_leave = 0;
			$unpaid_leave = 0;
			$standard_workday = 0;

			/*check value from database*/
			$staffid = $staff_value['staffid'];

			/*check value from database*/
			$staff_i = $this->hr_payroll_model->get_staff_info($staff_value['staffid']);
			if ($staff_i) {

				if (isset($staff_i->staff_identifi)) {
					$data_object_kpi['hr_code'] = $staff_i->staff_identifi;
				} else {
					$data_object_kpi['hr_code'] = $this->hr_payroll_model->hrp_format_code('EXS', $staff_i->staffid, 5);
				}

				$data_object_kpi['staff_name'] = $staff_i->firstname . ' ' . $staff_i->lastname;

				$arr_department = $this->hr_payroll_model->get_staff_departments($staff_i->staffid, true);

				$list_department = '';
				if (count($arr_department) > 0) {

					foreach ($arr_department as $key => $department) {
						$department_value = $this->departments_model->get($department);

						if ($department_value) {
							if (new_strlen($list_department) != 0) {
								$list_department .= ', ' . $department_value->name;
							} else {
								$list_department .= $department_value->name;
							}
						}

					}
				}

				$data_object_kpi['staff_departments'] = $list_department;

			} else {
				$data_object_kpi['hr_code'] = '';
				$data_object_kpi['staff_name'] = '';
				$data_object_kpi['staff_departments'] = '';

			}

			if (isset($attendances_value[$staff_value['staffid'] . '_' . $current_month])) {

				$data_object_kpi['standard_workday'] = $attendances_value[$staff_value['staffid'] . '_' . $current_month]['standard_workday'];
				$data_object_kpi['actual_workday_probation'] = $attendances_value[$staff_value['staffid'] . '_' . $current_month]['actual_workday_probation'];
				$data_object_kpi['actual_workday'] = $attendances_value[$staff_value['staffid'] . '_' . $current_month]['actual_workday'];
				$data_object_kpi['paid_leave'] = $attendances_value[$staff_value['staffid'] . '_' . $current_month]['paid_leave'];
				$data_object_kpi['unpaid_leave'] = $attendances_value[$staff_value['staffid'] . '_' . $current_month]['unpaid_leave'];
				$data_object_kpi['id'] = $attendances_value[$staff_value['staffid'] . '_' . $current_month]['id'];

				$data_object_kpi = array_merge($data_object_kpi, $attendances_value[$staff_value['staffid'] . '_' . $current_month]);

			} else {
				$data_object_kpi['standard_workday'] = get_hr_payroll_option('standard_working_time');
				$data_object_kpi['actual_workday_probation'] = 0;
				$data_object_kpi['actual_workday'] = 0;
				$data_object_kpi['paid_leave'] = 0;
				$data_object_kpi['unpaid_leave'] = 0;
				$data_object_kpi['id'] = 0;
				$data_object_kpi = array_merge($data_object_kpi, $days_header_in_month['days_header']);

			}
			$data_object_kpi['rel_type'] = $rel_type;
			$data_object_kpi['month'] = $current_month;
			$data_object_kpi['staff_id'] = $staff_value['staffid'];

			if ($staff_key == 0) {
				$writer->writeSheetRow('Sheet1', $header_key);
			}

			$get_values_for_keys = $this->get_values_for_keys($data_object_kpi, $header_key);
			$writer->writeSheetRow('Sheet1', $get_values_for_keys);

		}

		$filename = 'attendance_sample_file' . get_staff_user_id() . '_' . strtotime(date('Y-m-d H:i:s')) . '.xlsx';
		$writer->writeToFile(new_str_replace($filename, HR_PAYROLL_CREATE_ATTENDANCE_SAMPLE . $filename, $filename));

		echo json_encode([
			'success' => true,
			'site_url' => site_url(),
			'staff_id' => get_staff_user_id(),
			'filename' => HR_PAYROLL_CREATE_ATTENDANCE_SAMPLE . $filename,
		]);

	}

	/**
	 * get values for keys
	 * @param  [type] $mapping
	 * @param  [type] $keys
	 * @return [type]
	 */
	function get_values_for_keys($mapping, $keys) {
		foreach ($keys as $key) {
			$output_arr[] = $mapping[$key];
		}
		return $output_arr;
	}

	/**
	 * import attendance excel
	 * @return [type]
	 */
	public function import_attendance_excel() {
		if (!has_permission('hrp_employee', '', 'create') && !has_permission('hrp_employee', '', 'edit') && !is_admin()) {
			access_denied('hrp_employee');
		}

		if (!class_exists('XLSXReader_fin')) {
			require_once module_dir_path(HR_PAYROLL_MODULE_NAME) . '/assets/plugins/XLSXReader/XLSXReader.php';
		}
		require_once module_dir_path(HR_PAYROLL_MODULE_NAME) . '/assets/plugins/XLSXWriter/xlsxwriter.class.php';

		$filename = '';
		if ($this->input->post()) {
			if (isset($_FILES['file_csv']['name']) && $_FILES['file_csv']['name'] != '') {

				$this->delete_error_file_day_before();
				$rel_type = hrp_get_timesheets_status();

				// Get the temp file path
				$tmpFilePath = $_FILES['file_csv']['tmp_name'];
				// Make sure we have a filepath
				if (!empty($tmpFilePath) && $tmpFilePath != '') {
					$rows = [];
					$arr_insert = [];

					$tmpDir = TEMP_FOLDER . '/' . time() . uniqid() . '/';

					if (!file_exists(TEMP_FOLDER)) {
						mkdir(TEMP_FOLDER, 0755);
					}

					if (!file_exists($tmpDir)) {
						mkdir($tmpDir, 0755);
					}

					// Setup our new file path
					$newFilePath = $tmpDir . $_FILES['file_csv']['name'];

					if (move_uploaded_file($tmpFilePath, $newFilePath)) {
						//Writer file
						$writer_header = array(
							_l('staffid') => 'string',
							_l('id') => 'string',
							_l('hr_code') => 'string',
							_l('staff_name') => 'string',
							_l('department') => 'string',
							_l('integration_actual_workday') => 'string',
							_l('integration_paid_leave') => 'string',
							_l('integration_unpaid_leave') => 'string',
							_l('standard_working_time_of_month') => 'string',
							_l('month') => 'string',
							_l('error') => 'string',
						);

						$writer = new XLSXWriter();
						$writer->writeSheetHeader('Sheet1', $writer_header, $col_options = ['widths' => [40, 40, 40, 50, 40, 40, 40, 40, 50, 50]]);

						//Reader file
						$xlsx = new XLSXReader_fin($newFilePath);
						$sheetNames = $xlsx->getSheetNames();
						$data = $xlsx->getSheetData($sheetNames[1]);

						$arr_header = [];

						$arr_header['staff_id'] = 0;
						$arr_header['id'] = 1;
						$arr_header['hr_code'] = 2;
						$arr_header['staff_name'] = 3;
						$arr_header['staff_departments'] = 4;
						$arr_header['actual_workday'] = 5;
						$arr_header['paid_leave'] = 6;
						$arr_header['unpaid_leave'] = 7;
						$arr_header['standard_workday'] = 8;
						$arr_header['month'] = 9;

						$total_rows = 0;
						$total_row_false = 0;

						$column_key = $data[1];
						for ($row = 1; $row < count($data); $row++) {

							$total_rows++;

							$rd = array();
							$flag = 0;
							$flag2 = 0;

							$string_error = '';
							$flag_position_group;
							$flag_department = null;

							$flag_staff_id = 0;

							if (($flag == 1) || $flag2 == 1) {
								//write error file
								$writer->writeSheetRow('Sheet1', [
									$value_staffid,
									$value_dependent_name,
									$value_relationship,
									$value_dependent_bir,
									$value_dependent_iden,
									$value_reason,
									$value_start_month,
									$value_end_month,
									$value_status,
									$string_error,
								]);

								$total_row_false++;
							}

							if ($flag == 0 && $flag2 == 0) {

								$rd = array_combine($column_key, $data[$row]);
								unset($rd['employee_number']);
								unset($rd['employee_name']);
								unset($rd['department_name']);
								unset($rd['hr_code']);
								unset($rd['staff_name']);
								unset($rd['staff_departments']);

								$rows[] = $rd;
								array_push($arr_insert, $rd);

							}

						}

						//insert batch
						if (count($arr_insert) > 0) {
							$this->hr_payroll_model->import_attendance_data($arr_insert);
						}

						$total_rows = $total_rows;
						$total_row_success = isset($rows) ? count($rows) : 0;
						$dataerror = '';
						$message = 'Not enought rows for importing';

						if ($total_row_false != 0) {
							$filename = 'Import_attendance_error_' . get_staff_user_id() . '_' . strtotime(date('Y-m-d H:i:s')) . '.xlsx';
							$writer->writeToFile(new_str_replace($filename, HR_PAYROLL_ERROR . $filename, $filename));
						}

					}
				}
			}
		}

		if (file_exists($newFilePath)) {
			@unlink($newFilePath);
		}

		echo json_encode([
			'message' => $message,
			'total_row_success' => $total_row_success,
			'total_row_false' => $total_row_false,
			'total_rows' => $total_rows,
			'site_url' => site_url(),
			'staff_id' => get_staff_user_id(),
			'filename' => HR_PAYROLL_ERROR . $filename,
		]);
	}

	/**
	 * attendance calculation
	 * @return [type]
	 */
	public function attendance_calculation() {
		if (!has_permission('hrp_employee', '', 'edit') && !is_admin()) {
			access_denied('hrp_employee');
		}

		$data = $this->input->post();
		$this->hr_payroll_model->attendance_calculation($data);
		$message = _l('updated_successfully');
		echo json_encode([
			'message' => $message,
		]);
	}

	/**
	 * manage deductions
	 * @return [type]
	 */
	public function manage_deductions() {
		if (!has_permission('hrp_deduction', '', 'view') && !has_permission('hrp_deduction', '', 'view_own') && !is_admin()) {
			access_denied('hrp_deduction');
		}

		$this->load->model('staff_model');
		$this->load->model('departments_model');

		$rel_type = hrp_get_hr_profile_status();

		//get current month
		$current_month = date('Y-m-d', strtotime(date('Y-m') . '-01'));
		$deductions_data = $this->hr_payroll_model->get_deductions_data($current_month);
		$deductions_value = [];
		if (count($deductions_data) > 0) {
			foreach ($deductions_data as $key => $value) {
				$deductions_value[$value['staff_id'] . '_' . $value['month']] = $value;
			}
		}

		//get deduction data for the first
		$format_deduction_value = $this->hr_payroll_model->get_format_deduction_data();

		//load staff
		if (!is_admin() && !has_permission('hrp_employee', '', 'view')) {
			//View own
			$staffs = $this->hr_payroll_model->get_staff_timekeeping_applicable_object(get_staffid_by_permission());
		} else {
			//admin or view global
			$staffs = $this->hr_payroll_model->get_staff_timekeeping_applicable_object();
		}

		$data_object_kpi = [];
		foreach ($staffs as $staff_key => $staff_value) {
			/*check value from database*/
			$data_object_kpi[$staff_key]['staff_id'] = $staff_value['staffid'];

			if ($rel_type == 'hr_records') {
				$data_object_kpi[$staff_key]['employee_number'] = $staff_value['staff_identifi'];
			} else {
				$data_object_kpi[$staff_key]['employee_number'] = $this->hr_payroll_model->hrp_format_code('EXS', $staff_value['staffid'], 5);
			}

			$data_object_kpi[$staff_key]['employee_name'] = $staff_value['firstname'] . ' ' . $staff_value['lastname'];

			$arr_department = $this->hr_payroll_model->get_staff_departments($staff_value['staffid'], true);

			$list_department = '';
			if (count($arr_department) > 0) {

				foreach ($arr_department as $key => $department) {
					$department_value = $this->departments_model->get($department);

					if ($department_value) {
						if (new_strlen($list_department) != 0) {
							$list_department .= ', ' . $department_value->name;
						} else {
							$list_department .= $department_value->name;
						}
					}
				}
			}

			$data_object_kpi[$staff_key]['department_name'] = $list_department;

			if (isset($deductions_value[$staff_value['staffid'] . '_' . $current_month])) {

				// array merge: staff information + earning list (probationary contract) + earning list (formal)
				if (isset($deductions_value[$staff_value['staffid'] . '_' . $current_month]['deduction_value'])) {

					$data_object_kpi[$staff_key] = array_merge($data_object_kpi[$staff_key], $deductions_value[$staff_value['staffid'] . '_' . $current_month]['deduction_value']);
				} else {
					$data_object_kpi[$staff_key] = array_merge($data_object_kpi[$staff_key], $format_deduction_value['array_deduction']);
				}

				$data_object_kpi[$staff_key]['id'] = $deductions_value[$staff_value['staffid'] . '_' . $current_month]['id'];

			} else {

				// array merge: staff information + earning list (probationary contract) + earning list (formal)
				$data_object_kpi[$staff_key] = array_merge($data_object_kpi[$staff_key], $format_deduction_value['array_deduction']);

				$data_object_kpi[$staff_key]['id'] = 0;

			}
			$data_object_kpi[$staff_key]['month'] = $current_month;

		}

		//check is add new or update data
		if (count($deductions_value) > 0) {
			$data['button_name'] = _l('hrp_update');
		} else {
			$data['button_name'] = _l('submit');
		}

		$data['departments'] = $this->departments_model->get();
		$data['roles'] = $this->roles_model->get();
		$data['staffs'] = $staffs;

		$data['body_value'] = json_encode($data_object_kpi);
		$data['columns'] = json_encode($format_deduction_value['column_format']);
		$data['col_header'] = json_encode($format_deduction_value['header']);

		$this->load->view('deductions/deductions_manage', $data);
	}

	/**
	 * add manage deductions
	 */
	public function add_manage_deductions() {
		if (!has_permission('hrp_deduction', '', 'create') && !has_permission('hrp_deduction', '', 'edit') && !is_admin()) {
			access_denied('hrp_deduction');
		}

		if ($this->input->post()) {
			$data = $this->input->post();

			if ($data['hrp_deductions_rel_type'] == 'update') {
				// update data
				$success = $this->hr_payroll_model->deductions_update($data);
			} else {
				$success = false;
			}

			if ($success) {
				set_alert('success', _l('updated_successfully'));
			} else {
				set_alert('success', _l('hrp_updated_successfully'));
			}

			redirect(admin_url('hr_payroll/manage_deductions'));
		}

	}

	/**
	 * deductions filter
	 * @return [type]
	 */
	public function deductions_filter() {
		$this->load->model('departments_model');
		$data = $this->input->post();

		$rel_type = hrp_get_hr_profile_status();

		$months_filter = $data['month'];
		$department = $data['department'];
		$staff = '';
		if (isset($data['staff'])) {
			$staff = $data['staff'];
		}
		$role_attendance = '';
		if (isset($data['role_attendance'])) {
			$role_attendance = $data['role_attendance'];
		}

		$newquerystring = $this->render_filter_query($months_filter, $staff, $department, $role_attendance);
		//get current month
		$month_filter = date('Y-m-d', strtotime($data['month'] . '-01'));
		$deductions_data = $this->hr_payroll_model->get_deductions_data($month_filter);
		$deductions_value = [];
		foreach ($deductions_data as $key => $value) {
			$deductions_value[$value['staff_id'] . '_' . $value['month']] = $value;
		}

		//get employee data for the first
		$format_deduction_value = $this->hr_payroll_model->get_format_deduction_data();

		// data return
		$data_object_kpi = [];
		$index_data_object = 0;
		if ($newquerystring != '') {

			//load staff
			if (!is_admin() && !has_permission('hrp_employee', '', 'view')) {
				//View own
				$staffs = $this->hr_payroll_model->get_staff_timekeeping_applicable_object(get_staffid_by_permission($newquerystring));
			} else {
				//admin or view global
				$staffs = $this->hr_payroll_model->get_staff_timekeeping_applicable_object($newquerystring);
			}

			$data_object_kpi = [];

			foreach ($staffs as $staff_key => $staff_value) {
				/*check value from database*/
				$data_object_kpi[$staff_key]['staff_id'] = $staff_value['staffid'];

				if ($rel_type == 'hr_records') {
					$data_object_kpi[$staff_key]['employee_number'] = $staff_value['staff_identifi'];
				} else {
					$data_object_kpi[$staff_key]['employee_number'] = $this->hr_payroll_model->hrp_format_code('EXS', $staff_value['staffid'], 5);
				}

				$data_object_kpi[$staff_key]['employee_name'] = $staff_value['firstname'] . ' ' . $staff_value['lastname'];

				$arr_department = $this->hr_payroll_model->get_staff_departments($staff_value['staffid'], true);

				$list_department = '';
				if (count($arr_department) > 0) {

					foreach ($arr_department as $key => $department) {
						$department_value = $this->departments_model->get($department);

						if ($department_value) {
							if (new_strlen($list_department) != 0) {
								$list_department .= ', ' . $department_value->name;
							} else {
								$list_department .= $department_value->name;
							}
						}
					}
				}

				$data_object_kpi[$staff_key]['department_name'] = $list_department;

				if (isset($deductions_value[$staff_value['staffid'] . '_' . $month_filter])) {

					// array merge: staff information + earning list (probationary contract) + earning list (formal)
					if (isset($deductions_value[$staff_value['staffid'] . '_' . $month_filter]['deduction_value'])) {

						$data_object_kpi[$staff_key] = array_merge($data_object_kpi[$staff_key], $deductions_value[$staff_value['staffid'] . '_' . $month_filter]['deduction_value']);
					} else {
						$data_object_kpi[$staff_key] = array_merge($data_object_kpi[$staff_key], $format_deduction_value['array_deduction']);
					}

					$data_object_kpi[$staff_key]['id'] = $deductions_value[$staff_value['staffid'] . '_' . $month_filter]['id'];

				} else {

					// array merge: staff information + earning list (probationary contract) + earning list (formal)
					$data_object_kpi[$staff_key] = array_merge($data_object_kpi[$staff_key], $format_deduction_value['array_deduction']);

					$data_object_kpi[$staff_key]['id'] = 0;

				}
				$data_object_kpi[$staff_key]['month'] = $month_filter;
			}

		}

		//check is add new or update data
		if (count($deductions_value) > 0) {
			$button_name = _l('hrp_update');
		} else {
			$button_name = _l('submit');
		}

		echo json_encode([
			'data_object' => $data_object_kpi,
			'button_name' => $button_name,
		]);
		die;
	}

	/**
	 * manage commissions
	 * @return [type]
	 */
	public function manage_commissions() {
		if (!has_permission('hrp_commission', '', 'view') && !has_permission('hrp_commission', '', 'view_own') && !is_admin()) {
			access_denied('hrp_commission');
		}

		$this->load->model('staff_model');
		$this->load->model('departments_model');

		$rel_type = hrp_get_hr_profile_status();
		$commission_type = hrp_get_commission_status();

		//get current month
		$current_month = date('Y-m-d', strtotime(date('Y-m') . '-01'));
		$commissions_data = $this->hr_payroll_model->get_commissions_data($current_month);
		$commissions_value = [];
		if (count($commissions_data) > 0) {
			foreach ($commissions_data as $key => $value) {
				$commissions_value[$value['staff_id'] . '_' . $value['month']] = $value;
			}
		}

		//get deduction data for the first
		$format_commission_value = $this->hr_payroll_model->get_format_commission_data();

		//load staff
		if (!is_admin() && !has_permission('hrp_employee', '', 'view')) {
			//View own
			$staffs = $this->hr_payroll_model->get_staff_timekeeping_applicable_object(get_staffid_by_permission());
		} else {
			//admin or view global
			$staffs = $this->hr_payroll_model->get_staff_timekeeping_applicable_object();
		}

		$data_object_kpi = [];
		foreach ($staffs as $staff_key => $staff_value) {
			/*check value from database*/
			$data_object_kpi[$staff_key]['staff_id'] = $staff_value['staffid'];

			if ($rel_type == 'hr_records') {
				$data_object_kpi[$staff_key]['employee_number'] = $staff_value['staff_identifi'];
			} else {
				$data_object_kpi[$staff_key]['employee_number'] = $this->hr_payroll_model->hrp_format_code('EXS', $staff_value['staffid'], 5);
			}

			$data_object_kpi[$staff_key]['employee_name'] = $staff_value['firstname'] . ' ' . $staff_value['lastname'];

			$arr_department = $this->hr_payroll_model->get_staff_departments($staff_value['staffid'], true);

			$list_department = '';
			if (count($arr_department) > 0) {

				foreach ($arr_department as $key => $department) {
					$department_value = $this->departments_model->get($department);

					if ($department_value) {
						if (new_strlen($list_department) != 0) {
							$list_department .= ', ' . $department_value->name;
						} else {
							$list_department .= $department_value->name;
						}
					}
				}
			}

			$data_object_kpi[$staff_key]['department_name'] = $list_department;

			if (isset($commissions_value[$staff_value['staffid'] . '_' . $current_month])) {

				$data_object_kpi[$staff_key]['commission_amount'] = $commissions_value[$staff_value['staffid'] . '_' . $current_month]['commission_amount'];
				$data_object_kpi[$staff_key]['id'] = $commissions_value[$staff_value['staffid'] . '_' . $current_month]['id'];

			} else {

				$data_object_kpi[$staff_key]['commission_amount'] = 0;
				$data_object_kpi[$staff_key]['id'] = 0;

			}
			$data_object_kpi[$staff_key]['month'] = $current_month;
			$data_object_kpi[$staff_key]['rel_type'] = $commission_type;

		}

		//check is add new or update data
		if (count($commissions_value) > 0) {
			$data['button_name'] = _l('hrp_update');
		} else {
			$data['button_name'] = _l('submit');
		}

		$data['departments'] = $this->departments_model->get();
		$data['roles'] = $this->roles_model->get();
		$data['staffs'] = $staffs;

		$data['body_value'] = json_encode($data_object_kpi);
		$data['columns'] = json_encode($format_commission_value['column_format']);
		$data['col_header'] = json_encode($format_commission_value['headers']);

		$this->load->view('commissions/commissions_manage', $data);
	}

	/**
	 * add manage commissions
	 */
	public function add_manage_commissions() {
		if (!has_permission('hrp_commission', '', 'create') && !has_permission('hrp_commission', '', 'edit') && !is_admin()) {
			access_denied('hrp_commission');
		}

		if ($this->input->post()) {
			$data = $this->input->post();

			if ($data['hrp_commissions_rel_type'] == 'update') {
				// update data
				$success = $this->hr_payroll_model->commissions_update($data);
			} elseif ($data['hrp_commissions_rel_type'] == 'synchronization') {
				//synchronization
				$success = $this->hr_payroll_model->commissions_synchronization($data);

			} else {
				$success = false;
			}

			if ($success) {
				set_alert('success', _l('updated_successfully'));
			} else {
				set_alert('success', _l('hrp_updated_successfully'));
			}

			redirect(admin_url('hr_payroll/manage_commissions'));
		}

	}

	/**
	 * commissions filter
	 * @return [type]
	 */
	public function commissions_filter() {
		$this->load->model('departments_model');
		$data = $this->input->post();

		$rel_type = hrp_get_hr_profile_status();
		$commission_type = hrp_get_commission_status();

		$months_filter = $data['month'];
		$department = $data['department'];
		$staff = '';
		if (isset($data['staff'])) {
			$staff = $data['staff'];
		}
		$role_attendance = '';
		if (isset($data['role_attendance'])) {
			$role_attendance = $data['role_attendance'];
		}

		$newquerystring = $this->render_filter_query($months_filter, $staff, $department, $role_attendance);

		//get current month
		$month_filter = date('Y-m-d', strtotime($data['month'] . '-01'));
		$commissions_data = $this->hr_payroll_model->get_commissions_data($month_filter);
		$commissions_value = [];
		if (count($commissions_data) > 0) {
			foreach ($commissions_data as $key => $value) {
				$commissions_value[$value['staff_id'] . '_' . $value['month']] = $value;
			}
		}

		// data return
		$data_object_kpi = [];
		$index_data_object = 0;
		if ($newquerystring != '') {

			//load staff
			if (!is_admin() && !has_permission('hrp_employee', '', 'view')) {
				//View own
				$staffs = $this->hr_payroll_model->get_staff_timekeeping_applicable_object(get_staffid_by_permission($newquerystring));
			} else {
				//admin or view global
				$staffs = $this->hr_payroll_model->get_staff_timekeeping_applicable_object($newquerystring);
			}

			$data_object_kpi = [];

			foreach ($staffs as $staff_key => $staff_value) {
				/*check value from database*/
				$data_object_kpi[$staff_key]['staff_id'] = $staff_value['staffid'];

				if ($rel_type == 'hr_records') {
					$data_object_kpi[$staff_key]['employee_number'] = $staff_value['staff_identifi'];
				} else {
					$data_object_kpi[$staff_key]['employee_number'] = $this->hr_payroll_model->hrp_format_code('EXS', $staff_value['staffid'], 5);
				}

				$data_object_kpi[$staff_key]['employee_name'] = $staff_value['firstname'] . ' ' . $staff_value['lastname'];

				$arr_department = $this->hr_payroll_model->get_staff_departments($staff_value['staffid'], true);

				$list_department = '';
				if (count($arr_department) > 0) {

					foreach ($arr_department as $key => $department) {
						$department_value = $this->departments_model->get($department);

						if ($department_value) {
							if (new_strlen($list_department) != 0) {
								$list_department .= ', ' . $department_value->name;
							} else {
								$list_department .= $department_value->name;
							}
						}
					}
				}

				$data_object_kpi[$staff_key]['department_name'] = $list_department;

				if (isset($commissions_value[$staff_value['staffid'] . '_' . $month_filter])) {

					$data_object_kpi[$staff_key]['commission_amount'] = $commissions_value[$staff_value['staffid'] . '_' . $month_filter]['commission_amount'];
					$data_object_kpi[$staff_key]['id'] = $commissions_value[$staff_value['staffid'] . '_' . $month_filter]['id'];

				} else {

					$data_object_kpi[$staff_key]['commission_amount'] = 0;
					$data_object_kpi[$staff_key]['id'] = 0;

				}
				$data_object_kpi[$staff_key]['month'] = $month_filter;
				$data_object_kpi[$staff_key]['rel_type'] = $commission_type;
			}

		}

		//check is add new or update data
		if (count($commissions_value) > 0) {
			$button_name = _l('hrp_update');
		} else {
			$button_name = _l('submit');
		}

		echo json_encode([
			'data_object' => $data_object_kpi,
			'button_name' => $button_name,
		]);
		die;
	}

	/**
	 * [import_xlsx_commissions
	 * @return [type]
	 */
	public function import_xlsx_commissions() {
		$this->load->model('staff_model');
		$data_staff = $this->staff_model->get(get_staff_user_id());
		/*get language active*/
		if ($data_staff) {
			if ($data_staff->default_language != '') {
				$data['active_language'] = $data_staff->default_language;
			} else {
				$data['active_language'] = get_option('active_language');
			}

		} else {
			$data['active_language'] = get_option('active_language');
		}

		$this->load->view('hr_payroll/commissions/import_commissions', $data);
	}

	/**
	 * create commissions sample file
	 * @return [type]
	 */
	public function create_commissions_sample_file() {
		if (!has_permission('hrp_commission', '', 'create') && !has_permission('hrp_commission', '', 'edit') && !is_admin()) {
			access_denied('hrp_commission');

		}
		$this->load->model('staff_model');
		$this->load->model('departments_model');

		$month_commission = $this->input->post('month_commissions');

		if (!class_exists('XLSXReader_fin')) {
			require_once module_dir_path(HR_PAYROLL_MODULE_NAME) . '/assets/plugins/XLSXReader/XLSXReader.php';
		}
		require_once module_dir_path(HR_PAYROLL_MODULE_NAME) . '/assets/plugins/XLSXWriter/xlsxwriter.class.php';

		$this->delete_error_file_day_before('1', HR_PAYROLL_CREATE_COMMISSIONS_SAMPLE);

		$rel_type = hrp_get_commission_status();
		//get commission data
		$current_month = date('Y-m-d', strtotime($month_commission . '-01'));
		//get day header in month
		$format_commission_data = $this->hr_payroll_model->get_format_commission_data($current_month, $rel_type);
		$header_key = $format_commission_data['staff_information'];

		$commissions = $this->hr_payroll_model->get_commissions_data($current_month);
		$commissions_value = [];
		foreach ($commissions as $key => $value) {
			$commissions_value[$value['staff_id'] . '_' . $value['month']] = $value;
		}

		//load staff
		if (!is_admin() && !has_permission('hrp_employee', '', 'view')) {
			//View own
			$staffs = $this->hr_payroll_model->get_staff_timekeeping_applicable_object(get_staffid_by_permission());
		} else {
			//admin or view global
			$staffs = $this->hr_payroll_model->get_staff_timekeeping_applicable_object();
		}

		//Writer file
		$writer_header = [];
		$widths = [];
		foreach ($format_commission_data['headers'] as $value) {
			$writer_header[$value] = 'string';
			$widths[] = 30;
		}

		$writer = new XLSXWriter();

		$col_style1 = [0, 1, 2, 3, 4, 5, 6];
		$style1 = ['widths' => $widths, 'fill' => '#ff9800', 'font-style' => 'bold', 'color' => '#0a0a0a', 'border' => 'left,right,top,bottom', 'border-color' => '#0a0a0a', 'font-size' => 13];

		$writer->writeSheetHeader_v2('Sheet1', $writer_header, $col_options = ['widths' => $widths, 'fill' => '#03a9f46b', 'font-style' => 'bold', 'color' => '#0a0a0a', 'border' => 'left,right,top,bottom', 'border-color' => '#0a0a0a', 'font-size' => 13],
			$col_style1, $style1);

		$data_object_kpi = [];
		foreach ($staffs as $staff_key => $staff_value) {
			$staffid = 0;
			$id = 0;
			$staff_name = '';
			$staff_departments = '';
			$commissions_amount = 0;

			/*check value from database*/
			$staffid = $staff_value['staffid'];

			/*check value from database*/
			$staff_i = $this->hr_payroll_model->get_staff_info($staff_value['staffid']);
			if ($staff_i) {

				if (isset($staff_i->staff_identifi)) {
					$data_object_kpi['employee_number'] = $staff_i->staff_identifi;
				} else {
					$data_object_kpi['employee_number'] = $this->hr_payroll_model->hrp_format_code('EXS', $staff_i->staffid, 5);
				}

				$data_object_kpi['employee_name'] = $staff_i->firstname . ' ' . $staff_i->lastname;

				$arr_department = $this->hr_payroll_model->get_staff_departments($staff_i->staffid, true);

				$list_department = '';
				if (count($arr_department) > 0) {

					foreach ($arr_department as $key => $department) {
						$department_value = $this->departments_model->get($department);

						if ($department_value) {
							if (new_strlen($list_department) != 0) {
								$list_department .= ', ' . $department_value->name;
							} else {
								$list_department .= $department_value->name;
							}
						}

					}
				}

				$data_object_kpi['department_name'] = $list_department;

			} else {
				$data_object_kpi['employee_number'] = '';
				$data_object_kpi['employee_name'] = '';
				$data_object_kpi['department_name'] = '';

			}

			if (isset($commissions_value[$staff_value['staffid'] . '_' . $current_month])) {

				$data_object_kpi['commission_amount'] = $commissions_value[$staff_value['staffid'] . '_' . $current_month]['commission_amount'];
				$data_object_kpi['id'] = $commissions_value[$staff_value['staffid'] . '_' . $current_month]['id'];

			} else {
				$data_object_kpi['commission_amount'] = 0;
				$data_object_kpi['id'] = 0;

			}
			$data_object_kpi['rel_type'] = $rel_type;
			$data_object_kpi['month'] = $current_month;
			$data_object_kpi['staff_id'] = $staff_value['staffid'];

			if ($staff_key == 0) {
				$writer->writeSheetRow('Sheet1', $header_key);
			}
			$get_values_for_keys = $this->get_values_for_keys($data_object_kpi, $header_key);

			$writer->writeSheetRow('Sheet1', $get_values_for_keys);

		}

		$filename = 'commission_sample_file' . get_staff_user_id() . '_' . strtotime(date('Y-m-d H:i:s')) . '.xlsx';
		$writer->writeToFile(new_str_replace($filename, HR_PAYROLL_CREATE_COMMISSIONS_SAMPLE . $filename, $filename));

		echo json_encode([
			'success' => true,
			'site_url' => site_url(),
			'staff_id' => get_staff_user_id(),
			'filename' => HR_PAYROLL_CREATE_COMMISSIONS_SAMPLE . $filename,
		]);

	}

	/**
	 * import commissions excel
	 * @return [type]
	 */
	public function import_commissions_excel() {
		if (!has_permission('hrp_commission', '', 'create') && !has_permission('hrp_commission', '', 'edit') && !is_admin()) {
			access_denied('hrp_commission');
		}

		if (!class_exists('XLSXReader_fin')) {
			require_once module_dir_path(HR_PAYROLL_MODULE_NAME) . '/assets/plugins/XLSXReader/XLSXReader.php';
		}
		require_once module_dir_path(HR_PAYROLL_MODULE_NAME) . '/assets/plugins/XLSXWriter/xlsxwriter.class.php';

		$filename = '';
		if ($this->input->post()) {
			if (isset($_FILES['file_csv']['name']) && $_FILES['file_csv']['name'] != '') {

				$this->delete_error_file_day_before();
				$rel_type = hrp_get_timesheets_status();

				// Get the temp file path
				$tmpFilePath = $_FILES['file_csv']['tmp_name'];
				// Make sure we have a filepath
				if (!empty($tmpFilePath) && $tmpFilePath != '') {
					$rows = [];
					$arr_insert = [];

					$tmpDir = TEMP_FOLDER . '/' . time() . uniqid() . '/';

					if (!file_exists(TEMP_FOLDER)) {
						mkdir(TEMP_FOLDER, 0755);
					}

					if (!file_exists($tmpDir)) {
						mkdir($tmpDir, 0755);
					}

					// Setup our new file path
					$newFilePath = $tmpDir . $_FILES['file_csv']['name'];

					if (move_uploaded_file($tmpFilePath, $newFilePath)) {
						//Writer file
						$writer_header = array(
							_l('staffid') => 'string',
							_l('id') => 'string',
							_l('hr_code') => 'string',
							_l('staff_name') => 'string',
							_l('department') => 'string',
							_l('integration_actual_workday') => 'string',
							_l('integration_paid_leave') => 'string',
							_l('integration_unpaid_leave') => 'string',
							_l('standard_working_time_of_month') => 'string',
							_l('month') => 'string',
							_l('error') => 'string',
						);

						$writer = new XLSXWriter();
						$writer->writeSheetHeader('Sheet1', $writer_header, $col_options = ['widths' => [40, 40, 40, 50, 40, 40, 40, 40, 50, 50]]);

						//Reader file
						$xlsx = new XLSXReader_fin($newFilePath);
						$sheetNames = $xlsx->getSheetNames();
						$data = $xlsx->getSheetData($sheetNames[1]);

						$arr_header = [];

						$arr_header['staff_id'] = 0;
						$arr_header['id'] = 1;
						$arr_header['hr_code'] = 2;
						$arr_header['staff_name'] = 3;
						$arr_header['staff_departments'] = 4;
						$arr_header['actual_workday'] = 5;
						$arr_header['paid_leave'] = 6;
						$arr_header['unpaid_leave'] = 7;
						$arr_header['standard_workday'] = 8;
						$arr_header['month'] = 9;

						$total_rows = 0;
						$total_row_false = 0;

						$column_key = $data[1];
						for ($row = 2; $row < count($data); $row++) {

							$total_rows++;

							$rd = array();
							$flag = 0;
							$flag2 = 0;

							$string_error = '';
							$flag_position_group;
							$flag_department = null;

							$flag_staff_id = 0;

							if (($flag == 1) || $flag2 == 1) {
								//write error file
								$writer->writeSheetRow('Sheet1', [
									$value_staffid,
									$value_dependent_name,
									$value_relationship,
									$value_dependent_bir,
									$value_dependent_iden,
									$value_reason,
									$value_start_month,
									$value_end_month,
									$value_status,
									$string_error,
								]);

								$total_row_false++;
							}

							if ($flag == 0 && $flag2 == 0) {

								$rd = array_combine($column_key, $data[$row]);
								unset($rd['employee_number']);
								unset($rd['employee_name']);
								unset($rd['department_name']);
								unset($rd['hr_code']);
								unset($rd['staff_name']);
								unset($rd['staff_departments']);

								$rows[] = $rd;
								array_push($arr_insert, $rd);

							}

						}

						//insert batch
						if (count($arr_insert) > 0) {
							$this->hr_payroll_model->import_commissions_data($arr_insert);
						}

						$total_rows = $total_rows;
						$total_row_success = isset($rows) ? count($rows) : 0;
						$dataerror = '';
						$message = 'Not enought rows for importing';

						if ($total_row_false != 0) {
							$filename = 'Import_commissions_error_' . get_staff_user_id() . '_' . strtotime(date('Y-m-d H:i:s')) . '.xlsx';
							$writer->writeToFile(new_str_replace($filename, HR_PAYROLL_ERROR . $filename, $filename));
						}

					}
				}
			}
		}

		if (file_exists($newFilePath)) {
			@unlink($newFilePath);
		}

		echo json_encode([
			'message' => $message,
			'total_row_success' => $total_row_success,
			'total_row_false' => $total_row_false,
			'total_rows' => $total_rows,
			'site_url' => site_url(),
			'staff_id' => get_staff_user_id(),
			'filename' => HR_PAYROLL_ERROR . $filename,
		]);
	}

	/**
	 * manage income taxs
	 * @return [type]
	 */
	public function income_taxs_manage() {
		if (!has_permission('hrp_income_tax', '', 'view') && !has_permission('hrp_income_tax', '', 'view_own') && !is_admin()) {
			access_denied('hrp_income_tax');
		}
		$this->load->model('staff_model');
		$this->load->model('departments_model');

		$rel_type = hrp_get_hr_profile_status();

		//get current month
		$current_month = date('Y-m-d', strtotime(date('Y-m') . '-01'));
		$income_taxs_data = $this->hr_payroll_model->get_income_tax_data($current_month);
		$income_taxs_value = [];
		if (count($income_taxs_data) > 0) {
			foreach ($income_taxs_data as $key => $value) {
				$income_taxs_value[$value['staff_id'] . '_' . $value['month']] = $value;
			}
		}

		//get tax for year
		$total_income_tax_in_year = $this->hr_payroll_model->get_total_income_tax_in_year($current_month);
		$tax_in_year = [];
		foreach ($total_income_tax_in_year as $t_key => $t_value) {
			$tax_in_year[$t_value['staff_id']] = $t_value;
		}

		//get deduction data for the first
		$format_income_tax_value = $this->hr_payroll_model->get_format_income_tax_data();

		//load staff
		if (!is_admin() && !has_permission('hrp_employee', '', 'view')) {
			//View own
			$staffs = $this->hr_payroll_model->get_staff_timekeeping_applicable_object(get_staffid_by_permission());
		} else {
			//admin or view global
			$staffs = $this->hr_payroll_model->get_staff_timekeeping_applicable_object();
		}

		$data_object_kpi = [];
		foreach ($staffs as $staff_key => $staff_value) {
			/*check value from database*/
			$data_object_kpi[$staff_key]['staff_id'] = $staff_value['staffid'];

			if ($rel_type == 'hr_records') {
				$data_object_kpi[$staff_key]['employee_number'] = $staff_value['staff_identifi'];
			} else {
				$data_object_kpi[$staff_key]['employee_number'] = $this->hr_payroll_model->hrp_format_code('EXS', $staff_value['staffid'], 5);
			}

			$data_object_kpi[$staff_key]['employee_name'] = $staff_value['firstname'] . ' ' . $staff_value['lastname'];

			$arr_department = $this->hr_payroll_model->get_staff_departments($staff_value['staffid'], true);

			$list_department = '';
			if (count($arr_department) > 0) {

				foreach ($arr_department as $key => $department) {
					$department_value = $this->departments_model->get($department);

					if ($department_value) {
						if (new_strlen($list_department) != 0) {
							$list_department .= ', ' . $department_value->name;
						} else {
							$list_department .= $department_value->name;
						}
					}
				}
			}

			$data_object_kpi[$staff_key]['department_name'] = $list_department;

			if (isset($income_taxs_value[$staff_value['staffid'] . '_' . $current_month])) {

				$data_object_kpi[$staff_key]['income_tax'] = $income_taxs_value[$staff_value['staffid'] . '_' . $current_month]['income_tax'];
				$data_object_kpi[$staff_key]['id'] = $income_taxs_value[$staff_value['staffid'] . '_' . $current_month]['id'];

			} else {

				$data_object_kpi[$staff_key]['income_tax'] = 0;
				$data_object_kpi[$staff_key]['id'] = 0;

			}
			$data_object_kpi[$staff_key]['month'] = $current_month;

			if (isset($tax_in_year[$staff_value['staffid']])) {
				$data_object_kpi[$staff_key]['tax_for_year'] = $tax_in_year[$staff_value['staffid']]['tax_for_year'];
			} else {
				$data_object_kpi[$staff_key]['tax_for_year'] = 0;
			}
		}

		$data['departments'] = $this->departments_model->get();
		$data['roles'] = $this->roles_model->get();
		$data['staffs'] = $staffs;

		$data['body_value'] = json_encode($data_object_kpi);
		$data['columns'] = json_encode($format_income_tax_value['column_format']);
		$data['col_header'] = json_encode($format_income_tax_value['headers']);

		$this->load->view('income_tax/income_tax_manage', $data);
	}

	/**
	 * income taxs filter
	 * @return [type]
	 */
	public function income_taxs_filter() {
		$this->load->model('departments_model');
		$data = $this->input->post();

		$rel_type = hrp_get_hr_profile_status();
		$commission_type = hrp_get_commission_status();

		$months_filter = $data['month'];
		$department = $data['department'];
		$staff = '';
		if (isset($data['staff'])) {
			$staff = $data['staff'];
		}
		$role_attendance = '';
		if (isset($data['role_attendance'])) {
			$role_attendance = $data['role_attendance'];
		}

		$newquerystring = $this->render_filter_query($months_filter, $staff, $department, $role_attendance);

		//get current month
		$current_month = date('Y-m-d', strtotime($data['month'] . '-01'));
		$income_taxs_data = $this->hr_payroll_model->get_income_tax_data($current_month);
		$income_taxs_value = [];
		if (count($income_taxs_data) > 0) {
			foreach ($income_taxs_data as $key => $value) {
				$income_taxs_value[$value['staff_id'] . '_' . $value['month']] = $value;
			}
		}

		//get tax for year
		$total_income_tax_in_year = $this->hr_payroll_model->get_total_income_tax_in_year($current_month);
		$tax_in_year = [];
		foreach ($total_income_tax_in_year as $t_key => $t_value) {
			$tax_in_year[$t_value['staff_id']] = $t_value;
		}

		// data return
		$data_object_kpi = [];
		$index_data_object = 0;
		if ($newquerystring != '') {

			//load staff
			if (!is_admin() && !has_permission('hrp_employee', '', 'view')) {
				//View own
				$staffs = $this->hr_payroll_model->get_staff_timekeeping_applicable_object(get_staffid_by_permission($newquerystring));
			} else {
				//admin or view global
				$staffs = $this->hr_payroll_model->get_staff_timekeeping_applicable_object($newquerystring);
			}

			$data_object_kpi = [];

			foreach ($staffs as $staff_key => $staff_value) {
				/*check value from database*/
				$data_object_kpi[$staff_key]['staff_id'] = $staff_value['staffid'];

				if ($rel_type == 'hr_records') {
					$data_object_kpi[$staff_key]['employee_number'] = $staff_value['staff_identifi'];
				} else {
					$data_object_kpi[$staff_key]['employee_number'] = $this->hr_payroll_model->hrp_format_code('EXS', $staff_value['staffid'], 5);
				}

				$data_object_kpi[$staff_key]['employee_name'] = $staff_value['firstname'] . ' ' . $staff_value['lastname'];

				$arr_department = $this->hr_payroll_model->get_staff_departments($staff_value['staffid'], true);

				$list_department = '';
				if (count($arr_department) > 0) {

					foreach ($arr_department as $key => $department) {
						$department_value = $this->departments_model->get($department);

						if ($department_value) {
							if (new_strlen($list_department) != 0) {
								$list_department .= ', ' . $department_value->name;
							} else {
								$list_department .= $department_value->name;
							}
						}
					}
				}

				$data_object_kpi[$staff_key]['department_name'] = $list_department;

				if (isset($income_taxs_value[$staff_value['staffid'] . '_' . $current_month])) {

					$data_object_kpi[$staff_key]['income_tax'] = $income_taxs_value[$staff_value['staffid'] . '_' . $current_month]['income_tax'];
					$data_object_kpi[$staff_key]['id'] = $income_taxs_value[$staff_value['staffid'] . '_' . $current_month]['id'];

				} else {

					$data_object_kpi[$staff_key]['income_tax'] = 0;
					$data_object_kpi[$staff_key]['id'] = 0;

				}
				$data_object_kpi[$staff_key]['month'] = $current_month;
				if (isset($tax_in_year[$staff_value['staffid']])) {
					$data_object_kpi[$staff_key]['tax_for_year'] = $tax_in_year[$staff_value['staffid']]['tax_for_year'];
				} else {
					$data_object_kpi[$staff_key]['tax_for_year'] = 0;
				}
			}

		}

		echo json_encode([
			'data_object' => $data_object_kpi,
		]);
		die;
	}

	/**
	 * manage insurances
	 * @return [type]
	 */
	public function manage_insurances() {
		if (!has_permission('hrp_insurrance', '', 'view') && !has_permission('hrp_insurrance', '', 'view_own') && !is_admin()) {
			access_denied('hrp_insurrance');
		}

		$this->load->model('staff_model');
		$this->load->model('departments_model');

		$rel_type = hrp_get_hr_profile_status();

		//get current month
		$current_month = date('Y-m-d', strtotime(date('Y-m') . '-01'));
		$insurances_data = $this->hr_payroll_model->get_insurances_data($current_month);
		$insurances_value = [];
		if (count($insurances_data) > 0) {
			foreach ($insurances_data as $key => $value) {
				$insurances_value[$value['staff_id'] . '_' . $value['month']] = $value;
			}
		}

		//get insurance data for the first
		$format_insurance_value = $this->hr_payroll_model->get_format_insurance_data();

		//load staff
		if (!is_admin() && !has_permission('hrp_employee', '', 'view')) {
			//View own
			$staffs = $this->hr_payroll_model->get_staff_timekeeping_applicable_object(get_staffid_by_permission());
		} else {
			//admin or view global
			$staffs = $this->hr_payroll_model->get_staff_timekeeping_applicable_object();
		}

		$data_object_kpi = [];
		foreach ($staffs as $staff_key => $staff_value) {
			/*check value from database*/
			$data_object_kpi[$staff_key]['staff_id'] = $staff_value['staffid'];

			if ($rel_type == 'hr_records') {
				$data_object_kpi[$staff_key]['employee_number'] = $staff_value['staff_identifi'];
			} else {
				$data_object_kpi[$staff_key]['employee_number'] = $this->hr_payroll_model->hrp_format_code('EXS', $staff_value['staffid'], 5);
			}

			$data_object_kpi[$staff_key]['employee_name'] = $staff_value['firstname'] . ' ' . $staff_value['lastname'];

			$arr_department = $this->hr_payroll_model->get_staff_departments($staff_value['staffid'], true);

			$list_department = '';
			if (count($arr_department) > 0) {

				foreach ($arr_department as $key => $department) {
					$department_value = $this->departments_model->get($department);

					if ($department_value) {
						if (new_strlen($list_department) != 0) {
							$list_department .= ', ' . $department_value->name;
						} else {
							$list_department .= $department_value->name;
						}
					}
				}
			}

			$data_object_kpi[$staff_key]['department_name'] = $list_department;

			if (isset($insurances_value[$staff_value['staffid'] . '_' . $current_month])) {

				// array merge: staff information + earning list (probationary contract) + earning list (formal)
				if (isset($insurances_value[$staff_value['staffid'] . '_' . $current_month]['insurance_value'])) {
					$data_object_kpi[$staff_key] = array_merge($data_object_kpi[$staff_key], $insurances_value[$staff_value['staffid'] . '_' . $current_month]['insurance_value']);
				} else {
					$data_object_kpi[$staff_key] = array_merge($data_object_kpi[$staff_key], $format_insurance_value['array_insurance']);
				}

				$data_object_kpi[$staff_key]['id'] = $insurances_value[$staff_value['staffid'] . '_' . $current_month]['id'];

			} else {

				// array merge: staff information + earning list (probationary contract) + earning list (formal)
				$data_object_kpi[$staff_key] = array_merge($data_object_kpi[$staff_key], $format_insurance_value['array_insurance']);

				$data_object_kpi[$staff_key]['id'] = 0;

			}
			$data_object_kpi[$staff_key]['month'] = $current_month;

		}

		//check is add new or update data
		if (count($insurances_value) > 0) {
			$data['button_name'] = _l('hrp_update');
		} else {
			$data['button_name'] = _l('submit');
		}

		$data['departments'] = $this->departments_model->get();
		$data['roles'] = $this->roles_model->get();
		$data['staffs'] = $staffs;

		$data['body_value'] = json_encode($data_object_kpi);
		$data['columns'] = json_encode($format_insurance_value['column_format']);
		$data['col_header'] = json_encode($format_insurance_value['header']);

		$this->load->view('insurances/insurances_manage', $data);
	}

	/**
	 * add manage insurances
	 */
	public function add_manage_insurances() {
		if (!has_permission('hrp_insurrance', '', 'create') && !has_permission('hrp_insurrance', '', 'edit') && !is_admin()) {
			access_denied('hrp_insurrance');
		}

		if ($this->input->post()) {
			$data = $this->input->post();

			if ($data['hrp_insurances_rel_type'] == 'update') {
				// update data
				$success = $this->hr_payroll_model->insurances_update($data);
			} else {
				$success = false;
			}

			if ($success) {
				set_alert('success', _l('updated_successfully'));
			} else {
				set_alert('success', _l('hrp_updated_successfully'));
			}

			redirect(admin_url('hr_payroll/manage_insurances'));
		}

	}

	/**
	 * insurances filter
	 * @return [type]
	 */
	public function insurances_filter() {
		$this->load->model('departments_model');
		$data = $this->input->post();

		$rel_type = hrp_get_hr_profile_status();

		$months_filter = $data['month'];
		$department = $data['department'];
		$staff = '';
		if (isset($data['staff'])) {
			$staff = $data['staff'];
		}
		$role_attendance = '';
		if (isset($data['role_attendance'])) {
			$role_attendance = $data['role_attendance'];
		}

		$newquerystring = $this->render_filter_query($months_filter, $staff, $department, $role_attendance);

		//get current month
		$month_filter = date('Y-m-d', strtotime($data['month'] . '-01'));
		$insurances_data = $this->hr_payroll_model->get_insurances_data($month_filter);
		$insurances_value = [];
		foreach ($insurances_data as $key => $value) {
			$insurances_value[$value['staff_id'] . '_' . $value['month']] = $value;
		}

		//get employee data for the first
		$format_insurance_value = $this->hr_payroll_model->get_format_insurance_data();

		// data return
		$data_object_kpi = [];
		$index_data_object = 0;
		if ($newquerystring != '') {

			//load staff
			if (!is_admin() && !has_permission('hrp_employee', '', 'view')) {
				//View own
				$staffs = $this->hr_payroll_model->get_staff_timekeeping_applicable_object(get_staffid_by_permission($newquerystring));
			} else {
				//admin or view global
				$staffs = $this->hr_payroll_model->get_staff_timekeeping_applicable_object($newquerystring);
			}

			$data_object_kpi = [];

			foreach ($staffs as $staff_key => $staff_value) {
				/*check value from database*/
				$data_object_kpi[$staff_key]['staff_id'] = $staff_value['staffid'];

				if ($rel_type == 'hr_records') {
					$data_object_kpi[$staff_key]['employee_number'] = $staff_value['staff_identifi'];
				} else {
					$data_object_kpi[$staff_key]['employee_number'] = $this->hr_payroll_model->hrp_format_code('EXS', $staff_value['staffid'], 5);
				}

				$data_object_kpi[$staff_key]['employee_name'] = $staff_value['firstname'] . ' ' . $staff_value['lastname'];

				$arr_department = $this->hr_payroll_model->get_staff_departments($staff_value['staffid'], true);

				$list_department = '';
				if (count($arr_department) > 0) {

					foreach ($arr_department as $key => $department) {
						$department_value = $this->departments_model->get($department);

						if ($department_value) {
							if (new_strlen($list_department) != 0) {
								$list_department .= ', ' . $department_value->name;
							} else {
								$list_department .= $department_value->name;
							}
						}
					}
				}

				$data_object_kpi[$staff_key]['department_name'] = $list_department;

				if (isset($insurances_value[$staff_value['staffid'] . '_' . $month_filter])) {

					// array merge: staff information + earning list (probationary contract) + earning list (formal)
					if (isset($insurances_value[$staff_value['staffid'] . '_' . $month_filter]['insurance_value'])) {
						$data_object_kpi[$staff_key] = array_merge($data_object_kpi[$staff_key], $insurances_value[$staff_value['staffid'] . '_' . $month_filter]['insurance_value']);
					} else {
						$data_object_kpi[$staff_key] = array_merge($data_object_kpi[$staff_key], $format_insurance_value['array_insurance']);
					}

					$data_object_kpi[$staff_key]['id'] = $insurances_value[$staff_value['staffid'] . '_' . $month_filter]['id'];

				} else {

					// array merge: staff information + earning list (probationary contract) + earning list (formal)
					$data_object_kpi[$staff_key] = array_merge($data_object_kpi[$staff_key], $format_insurance_value['array_insurance']);

					$data_object_kpi[$staff_key]['id'] = 0;

				}
				$data_object_kpi[$staff_key]['month'] = $month_filter;
			}

		}

		//check is add new or update data
		if (count($insurances_value) > 0) {
			$button_name = _l('hrp_update');
		} else {
			$button_name = _l('submit');
		}

		echo json_encode([
			'data_object' => $data_object_kpi,
			'button_name' => $button_name,
		]);
		die;
	}

	/**
	 * delete_error file day before
	 * @return [type]
	 */
	public function delete_error_file_day_before($before_day = '', $folder_name = '') {
		if ($before_day != '') {
			$day = $before_day;
		} else {
			$day = '7';
		}

		if ($folder_name != '') {
			$folder = $folder_name;
		} else {
			$folder = HR_PAYROLL_ERROR;
		}

		//Delete old file before 7 day
		$date = date_create(date('Y-m-d H:i:s'));
		date_sub($date, date_interval_create_from_date_string($day . " days"));
		$before_7_day = strtotime(date_format($date, "Y-m-d H:i:s"));

		foreach (glob($folder . '*') as $file) {

			$file_arr = new_explode("/", $file);
			$filename = array_pop($file_arr);

			if (file_exists($file)) {
				//don't delete index.html file
				if ($filename != 'index.html') {
					$file_name_arr = new_explode("_", $filename);
					$date_create_file = array_pop($file_name_arr);
					$date_create_file = new_str_replace('.xlsx', '', $date_create_file);

					if ((float) $date_create_file <= (float) $before_7_day) {
						unlink($folder . $filename);
					}
				}
			}
		}
		return true;
	}

	/**
	 * payslip manage
	 * @param  string $id
	 * @return [type]
	 */
	public function payslip_manage($id = '') {
		if (!has_permission('hrp_payslip', '', 'view') && !has_permission('hrp_payslip', '', 'view_own') && !is_admin()) {
			access_denied('hrp_payslip');
		}
		$data['internal_id'] = $id;
		$data['title'] = _l('hr_pay_slips');
		$data['staffs'] = $this->staff_model->get();
        $base_currency = get_base_currency();
        $base_currency_id = 0;
        if ($base_currency) {
        	$base_currency_id = $base_currency->id;
        }
        $data['base_currency_id'] = $base_currency_id;
        $data['currencies'] = $this->currencies_model->get();

		$this->load->view('payslips/payslip_manage', $data);
	}

	/**
	 * payslip table
	 * @return table
	 */
	public function payslip_table() {
		$this->app->get_table_data(module_views_path('hr_payroll', 'payslips/payslip_table'));
	}

	/**
	 * delete payslip
	 * @param  [type] $id
	 * @return [type]
	 */
	public function delete_payslip($id) {
		if (!is_admin() && !has_permission('hrp_payslip', '', 'delete')) {
			access_denied('hrp_payslip');
		}
		if (!$id) {
			redirect(admin_url('hr_payroll/payslip_manage'));
		}

		$response = $this->hr_payroll_model->delete_payslip($id);
		if (is_array($response) && isset($response['referenced'])) {
			set_alert('warning', _l('is_referenced', _l('payslip_template')));
		} elseif ($response == true) {
			set_alert('success', _l('deleted', _l('payslip_template')));
		} else {
			set_alert('warning', _l('problem_deleting', _l('payslip_template')));
		}
		redirect(admin_url('hr_payroll/payslip_manage'));
	}

	/**
	 * payslip manage
	 * @param  string $id
	 * @return [type]
	 */
	public function payslip_templates_manage($id = '') {
		if (!has_permission('hrp_payslip_template', '', 'view') && !has_permission('hrp_payslip_template', '', 'view_own') && !is_admin()) {
			access_denied('hrp_payslip_template');
		}

		$this->load->model('staff_model');
		$this->load->model('departments_model');

		$data['staffs'] = $this->hr_payroll_model->get_staff_timekeeping_applicable_object();
		$data['internal_id'] = $id;

		$data['departments'] = $this->departments_model->get();
		$data['roles'] = $this->roles_model->get();

		$data['title'] = _l('payslip_template');
		$this->load->view('payslip_templates/payslip_template_manage', $data);
	}

	/**
	 * payslip table
	 * @return table
	 */
	public function payslip_template_table() {
		$this->app->get_table_data(module_views_path('hr_payroll', 'payslip_templates/payslip_template_table'));
	}

	/**
	 * get column key html add
	 * @return [type]
	 */
	public function get_payroll_column_method_html_add() {
		$method_option = $this->hr_payroll_model->get_list_payroll_column_method(['id' => '']);
		$order_display = $this->hr_payroll_model->count_payroll_column();

		echo json_encode([
			'method_option' => $method_option['method_option'],
			'order_display' => $order_display,

		]);
	}

	/**
	 * get payroll column function name html
	 * @return [type]
	 */
	public function get_payroll_column_function_name_html() {
		$method_option = $this->hr_payroll_model->get_list_payroll_column_function_name(['function_name' => '']);

		echo json_encode([
			'method_option' => $method_option['method_option'],

		]);
	}

	/**
	 * payroll column
	 * @return [type]
	 */
	public function payroll_column() {
		if ($this->input->post()) {
			$data = $this->input->post();
			if (!$this->input->post('id')) {

				if (!is_admin() && !has_permission('hrp_setting', '', 'create')) {
					access_denied('hr_payroll');
				}

				$add = $this->hr_payroll_model->add_payroll_column($data);
				if ($add) {
					$message = _l('added_successfully', _l('payroll_column'));
					set_alert('success', $message);
				}
				redirect(admin_url('hr_payroll/setting?group=payroll_columns'));
			} else {

				if (!is_admin() && !has_permission('hrp_setting', '', 'edit')) {
					access_denied('hr_payroll');
				}

				$id = $data['id'];
				unset($data['id']);
				$success = $this->hr_payroll_model->update_payroll_column($data, $id);
				if ($success == true) {
					$message = _l('updated_successfully', _l('payroll_column'));
					set_alert('success', $message);
				}
				redirect(admin_url('hr_payroll/setting?group=payroll_columns'));
			}

		}
	}

	/**
	 * get payroll column
	 * @param  [type] $id
	 * @return [type]
	 */
	public function get_payroll_column($id) {
		//get data
		$payroll_column = $this->hr_payroll_model->get_hrp_payroll_columns($id);
		//get taking method html
		if ($payroll_column) {
			$method_option = $this->hr_payroll_model->get_list_payroll_column_method(['taking_method' => $payroll_column->taking_method]);
		} else {
			$method_option = $this->hr_payroll_model->get_list_payroll_column_method(['taking_method' => '']);
		}
		//get function name html
		if ($payroll_column) {
			$function_name = $this->hr_payroll_model->get_list_payroll_column_function_name(['function_name' => $payroll_column->function_name]);
		} else {
			$function_name = $this->hr_payroll_model->get_list_payroll_column_function_name(['function_name' => '']);
		}

		echo json_encode([
			'payroll_column' => $payroll_column,
			'method_option' => $method_option,
			'function_name' => $function_name,
		]);
		die;

	}

	/**
	 * delete payroll column setting
	 * @param  string $id
	 * @return [type]
	 */
	public function delete_payroll_column_setting($id = '') {
		if (!is_admin() && !has_permission('hrp_setting', '', 'delete')) {
			access_denied('hr_payroll');
		}
		if (!$id) {
			redirect(admin_url('hr_payroll/setting?group=payroll_columns'));
		}

		$response = $this->hr_payroll_model->delete_payroll_column($id);
		if (is_array($response) && isset($response['referenced'])) {
			set_alert('warning', _l('is_referenced', _l('payslip_template')));
		} elseif ($response == true) {
			set_alert('success', _l('deleted', _l('payslip_template')));
		} else {
			set_alert('warning', _l('problem_deleting', _l('payslip_template')));
		}
		redirect(admin_url('hr_payroll/setting?group=payroll_columns'));
	}

	/**
	 * get payslip template
	 * @param  string $id
	 * @return [type]
	 */
	public function get_payslip_template($id = '') {
		$payslip_template_data = '';
		if (isset($id) && $id != '') {
			$payslip_template = $this->hr_payroll_model->get_hrp_payslip_templates($id);
			// update
			$payslip_template_selected = $this->hr_payroll_model->get_payslip_template_selected_html($payslip_template->payslip_id_copy);
			$payslip_column_selected = $this->hr_payroll_model->get_payslip_column_html($payslip_template->payslip_columns);
			$payslip_template_data = $payslip_template;

		} else {
			// create
			$payslip_template_selected = $this->hr_payroll_model->get_payslip_template_selected_html('');
			$payslip_column_selected = $this->hr_payroll_model->get_payslip_column_html('');
		}

		echo json_encode([
			'payslip_template_selected' => $payslip_template_selected,
			'payslip_column_selected' => $payslip_column_selected,
			'payslip_template_data' => $payslip_template_data,
		]);
		die;

	}

	/**
	 * payslip template
	 * @return [type]
	 */
	public function payslip_template() {
		if (!has_permission('hrp_payslip_template', '', 'create') && !has_permission('hrp_payslip_template', '', 'edit') && !is_admin()) {
			access_denied('hrp_payslip_template');
		}

		if ($this->input->post()) {
			$data = $this->input->post();

			if (!$this->input->post('id')) {

				if (!is_admin() && !has_permission('hrp_payslip_template', '', 'create')) {
					access_denied('hrp_payslip_template');
				}

				$insert_id = $this->hr_payroll_model->add_payslip_template($data);
				if ($insert_id) {
					$this->hr_payroll_model->add_payslip_templates_detail_first($insert_id);

					$message = _l('added_successfully', _l('payroll_column'));
					set_alert('success', $message);
				}
				redirect(admin_url('hr_payroll/view_payslip_templates_detail/' . $insert_id));
			} else {

				if (!is_admin() && !has_permission('hrp_payslip_template', '', 'edit')) {
					access_denied('hrp_payslip_template');
				}

				$id = $data['id'];
				unset($data['id']);

				$edit_payslip_column = false;
				if (isset($data['edit_payslip_column']) && $data['edit_payslip_column'] == 'true') {
					$edit_payslip_column = true;
					unset($data['edit_payslip_column']);
				}

				$check_update_detail = false;
				$check_update_detail = $this->hr_payroll_model->check_update_payslip_template_detail($data, $id);
				$success = $this->hr_payroll_model->update_payslip_template($data, $id);

				if ($success == true || $success) {
					if ($check_update_detail['status']) {
						$this->hr_payroll_model->update_payslip_templates_detail_first($check_update_detail['old_column_formular'], $id);
					}

					$message = _l('updated_successfully', _l('payroll_column'));
					set_alert('success', $message);
				}
				redirect(admin_url('hr_payroll/view_payslip_templates_detail/' . $id));
			}

		}
	}

	/**
	 * delete payslip template
	 * @param  [type] $id
	 * @return [type]
	 */
	public function delete_payslip_template($id) {
		if (!is_admin() && !has_permission('hrp_payslip_template', '', 'delete')) {
			access_denied('hr_payroll');
		}
		if (!$id) {
			redirect(admin_url('hr_payroll/payslip_templates_manage'));
		}

		$response = $this->hr_payroll_model->delete_payslip_template($id);
		if (is_array($response) && isset($response['referenced'])) {
			set_alert('warning', _l('is_referenced', _l('payslip_template')));
		} elseif ($response == true) {
			set_alert('success', _l('deleted', _l('payslip_template')));
		} else {
			set_alert('warning', _l('problem_deleting', _l('payslip_template')));
		}
		redirect(admin_url('hr_payroll/payslip_templates_manage'));
	}

	/**
	 * view payslip templates detail, add or edit
	 * @param [type] $parent_id
	 * @param string $id
	 */
	public function view_payslip_templates_detail($id = "") {

		$data_form = $this->input->post();
		if ($this->input->post()) {
			$data = $this->input->post();
			if(isset($data['csrf_token_name'])){
				unset($data['csrf_token_name']);
			}
			
			if (!is_admin() && !has_permission('hrp_payslip_template', '', 'edit') && !has_permission('hrp_payslip_template', '', 'create')) {
				$message = _l('access_denied');
				echo json_encode(['danger' => false, 'message' => $message]);
				die;
			}

			$id = $data['id'];
			unset($data['id']);
			$success = $this->hr_payroll_model->update_payslip_templates_detail($data, $id);

			if ($success == true) {
				$message = _l('payslip_template') . ' ' . _l('updated_successfully');
				$file_excel = $this->hr_payroll_model->get_hrp_payslip_templates($id);
				echo json_encode(['success' => true, 'message' => $message, 'name_excel' => $file_excel->templates_name]);
				die;
			} else {
				$message = _l('payslip_template') . ' ' . _l('updated_successfully');
				echo json_encode(['success' => true, 'message' => $message]);
				die;
			}

		}

		if ($id != '') {
			$data['id'] = $id;
			$data['file_excel'] = $this->hr_payroll_model->get_hrp_payslip_templates($data['id']);
			$data['data_form'] = $data['file_excel']->payslip_template_data;

		}
		if (has_permission('hrp_payslip_template', '', 'create') || has_permission('hrp_payslip_template', '', 'edit')) {

			$permission_actions = '<button id="luckysheet_info_detail_save" class="BTNSS btn btn-info luckysheet_info_detail_save pull-right">Save</button><a id="luckysheet_info_detail_export" class="btn btn-info luckysheet_info_detail_export pull-right"> Download</a><a href="' . admin_url() . 'hr_payroll/payslip_templates_manage' . '" class="btn mright5 btn-default pull-right" >Back</a>';
		} else {
			$permission_actions = '<a id="luckysheet_info_detail_export" class="btn btn-info luckysheet_info_detail_export pull-right"> Download</a><a href="' . admin_url() . 'hr_payroll/payslip_templates_manage' . '" class="btn mright5 btn-default pull-right" >Back</a>';
		}

		$data['permission_actions'] = $permission_actions;

		$data['title'] = _l('view_payslip_templates_detail');

		$this->load->view('payslip_templates/add_payslip_template', $data);

	}

	/**
	 * view payslip
	 * @param  string $id
	 * @return [type]
	 */
	public function view_payslip_detail($id = "") {

		if (!is_admin() && !has_permission('hrp_payslip', '', 'view')) {
			access_denied('view_payslip');
		}

		$data_form = $this->input->post();

		if ($this->input->post()) {
			$data = $this->input->post();

			if (!is_admin() && !has_permission('hrp_payslip', '', 'edit') && !has_permission('hrp_payslip', '', 'create')) {
				$message = _l('access_denied');
				echo json_encode(['danger' => false, 'message' => $message]);
				die;
			}
			$id = $data['id'];
			unset($data['id']);
			$success = $this->hr_payroll_model->update_payslip($data, $id);
			if ($success == true) {
				$message = _l('payslip_template') . ' ' . _l('updated_successfully');
				echo json_encode(['success' => true, 'message' => $message]);
				die;
			} else {
				$message = _l('payslip_template') . ' ' . _l('updated_failed');
				echo json_encode(['success' => false, 'message' => $message]);
				die;
			}

		}

		if ($id != '') {
			$data['id'] = $id;
			$payslip = $this->hr_payroll_model->get_hrp_payslip($data['id']);

			$data['payslip'] = $payslip;

			$path = HR_PAYROLL_PAYSLIP_FILE . $payslip->file_name;
			if(!file_exists($path)){
				set_alert('warning', _l('hrp_The_physical_file_has_been_deleted'));
				redirect(admin_url('hr_payroll/payslip_manage'));
			}
			$mystring = file_get_contents($path, true);

			//$data['data_form'] = replace_spreadsheet_value($mystring);
			$data['data_form'] = $mystring;

		}

		if (has_permission('hrp_payslip', '', 'create') || has_permission('hrp_payslip', '', 'edit')) {
			$permission_actions = '<button id="save_data" class="btn mright5 btn-primary pull-right luckysheet_info_detail_save" >Save</button><a href="#" class="btn mright5 btn-success pull-right payslip_download hide" >Download</a><button  class="btn mright5 btn-info pull-right luckysheet_info_detail_exports ">Create file</button><button id="payslip_close" class="btn mright5 btn-warning pull-right luckysheet_info_detail_payslip_close" >Payslip closing</button><a href="' . admin_url() . 'hr_payroll/payslip_manage' . '" class="btn mright5 btn-default pull-right" >Back</a>';
		} else {
			$permission_actions = '<a href="#" class="btn mright5 btn-success pull-right payslip_download hide" >Download</a><button  class="btn mright5 btn-info pull-right luckysheet_info_detail_exports ">Create file</button><a href="' . admin_url() . 'hr_payroll/payslip_manage' . '" class="btn mright5 btn-default pull-right" >Back</a>';
		}
		$data['permission_actions'] = $permission_actions;

		$data['title'] = _l('payslip_detail');

		$this->load->view('payslips/payslip', $data);

	}

	/**
	 * view payslip detail v2
	 * @param  string $id
	 * @return [type]
	 */
	public function view_payslip_detail_v2($id = "") {
		if (!is_admin() && !has_permission('hrp_payslip', '', 'view_own')) {
			access_denied('view_payslip');
		}

		$data_form = $this->input->post();

		if ($this->input->post()) {
			$data = $this->input->post();

			if (!is_admin() && !has_permission('hrp_payslip', '', 'edit') && !has_permission('hrp_payslip', '', 'create')) {
				$message = _l('access_denied');
				echo json_encode(['danger' => false, 'message' => $message]);
				die;
			}
			$id = $data['id'];
			unset($data['id']);
			$success = $this->hr_payroll_model->update_payslip($data, $id);
			if ($success == true) {
				$message = _l('payslip_template') . ' ' . _l('updated_successfully');
				echo json_encode(['success' => true, 'message' => $message]);
				die;
			} else {
				$message = _l('payslip_template') . ' ' . _l('updated_failed');
				echo json_encode(['success' => false, 'message' => $message]);
				die;
			}

		}

		if ($id != '') {

			$data['id'] = $id;
			$payslip = $this->hr_payroll_model->get_hrp_payslip($data['id']);

			$data['payslip'] = $payslip;

			$path = HR_PAYROLL_PAYSLIP_FILE . $payslip->file_name;
			if(!file_exists($path)){
				set_alert('warning', _l('hrp_The_physical_file_has_been_deleted'));
				redirect(admin_url('hr_payroll/payslip_manage'));
			}
			$mystring = file_get_contents($path, true);

			//remove employees not under management
			$mystring = $this->hr_payroll_model->remove_employees_not_under_management_on_payslip($mystring);

			//$data['data_form'] = replace_spreadsheet_value($mystring);
			$data['data_form'] = $mystring;

		}

		$permission_actions = '<a href="#" class="btn mright5 btn-success pull-right payslip_download hide" >Download</a><button  class="btn mright5 btn-info pull-right luckysheet_info_detail_exports ">Create file</button><a href="' . admin_url() . 'hr_payroll/payslip_manage' . '" class="btn mright5 btn-default pull-right" >Back</a>';
		$data['permission_actions'] = $permission_actions;

		$data['title'] = _l('view_payslip');

		$this->load->view('payslips/payslip_view_own', $data);

	}

	/**
	 * manage bonus
	 * @return [type]
	 */
	public function manage_bonus() {
		if (!has_permission('hrp_bonus_kpi', '', 'view') && !has_permission('hrp_bonus_kpi', '', 'view_own') && !is_admin()) {
			access_denied('hrp_bonus_kpi');
		}

		$this->load->model('staff_model');
		$this->load->model('departments_model');

		/*bonus commodity fill*/
		//get current month
		$current_month = date('Y-m');

		/*bonus commodity fill*/

		/*bonus Kpi*/
		//get current month

		//load staff
		if (!is_admin() && !has_permission('hrp_employee', '', 'view')) {
			//View own
			$staffs = $this->hr_payroll_model->get_staff_timekeeping_applicable_object(get_staffid_by_permission());
		} else {
			//admin or view global
			$staffs = $this->hr_payroll_model->get_staff_timekeeping_applicable_object();
		}

		$data_object_kpi = [];
		$bonus_status = true;

		foreach ($staffs as $staff_key => $staff_value) {
			/*check value from database*/
			$data_object_kpi[$staff_key]['staffid'] = $staff_value['staffid'];

			$staff_i = $this->hr_payroll_model->get_staff_info($staff_value['staffid']);
			if ($staff_i) {

				if (isset($staff_i->staff_identifi)) {
					$data_object_kpi[$staff_key]['hr_code'] = $staff_i->staff_identifi;
				} else {
					$data_object_kpi[$staff_key]['hr_code'] = $this->hr_payroll_model->hrp_format_code('EXS', $staff_i->staffid, 5);
				}

				$data_object_kpi[$staff_key]['staff_name'] = $staff_i->firstname . ' ' . $staff_i->lastname;

				$data_object_kpi[$staff_key]['job_position'] = '';

				$arr_department = $this->hr_payroll_model->get_staff_departments($staff_i->staffid, true);

				$list_department = '';
				if (count($arr_department) > 0) {

					foreach ($arr_department as $key => $department) {
						$department_value = $this->departments_model->get($department);

						if ($department_value) {
							if (new_strlen($list_department) != 0) {
								$list_department .= ', ' . $department_value->name;
							} else {
								$list_department .= $department_value->name;
							}
						}

					}
				}

				$data_object_kpi[$staff_key]['staff_departments'] = $list_department;

			} else {
				$data_object_kpi[$staff_key]['hr_code'] = '';
				$data_object_kpi[$staff_key]['staff_name'] = '';
				$data_object_kpi[$staff_key]['job_position'] = $staff_value['staffid'];
				$data_object_kpi[$staff_key]['staff_departments'] = '';

			}

			//get_data from hrm_allowance_commodity_fill
			$bonus_kpi = $this->hr_payroll_model->get_bonus_by_month($staff_value['staffid'], $current_month);
			if ($bonus_kpi) {

				$data_object_kpi[$staff_key]['bonus_kpi'] = $bonus_kpi->bonus_kpi;

			} else {
				$data_object_kpi[$staff_key]['bonus_kpi'] = 0;
				$bonus_status = false;
			}

		}

		/*bonus Kpi*/
		//check is add new or update data
		if ($bonus_status == true) {
			$data['button_name'] = _l('hrp_update');
		} else {
			$data['button_name'] = _l('submit');
		}

		$data['departments'] = $this->departments_model->get();
		$data['staffs_li'] = $this->staff_model->get();
		$data['roles'] = $this->roles_model->get();
		$data['staffs'] = $staffs;
		$data['data_object_kpi'] = $data_object_kpi;

		$this->load->view('bonus/bonus_kpi', $data);
	}

	/**
	 * add bonus kpi
	 * @return redirect
	 */
	public function add_bonus_kpi() {
		if (!has_permission('hrp_bonus_kpi', '', 'view') && !has_permission('hrp_bonus_kpi', '', 'edit') && !is_admin()) {
			access_denied('hrp_bonus_kpi');
		}
		if ($this->input->post()) {
			$data = $this->input->post();

			if (isset($data)) {

				$success = $this->hr_payroll_model->add_bonus_kpi($data);

				if ($success) {
					set_alert('success', _l('hrp_updated_successfully'));
				} else {
					set_alert('success', _l('hrp_updated_successfully'));
				}
				redirect(admin_url('hr_payroll/manage_bonus'));
			}

		}
	}

	/**
	 * bonus kpi filter
	 * @return array
	 */
	public function bonus_kpi_filter() {
		$this->load->model('departments_model');
		$data = $this->input->post();

		$months_filter = $data['month'];
		$year = date('Y', strtotime(($data['month'] . '-01')));
		$g_month = date('m', strtotime(($data['month'] . '-01')));

		$querystring = ' active=1';

		$department = $data['department'];

		$staff = '';
		if (isset($data['staff'])) {
			$staff = $data['staff'];
		}
		$staff_querystring = '';
		$department_querystring = '';
		$month_year_querystring = '';
		$month = date('m');
		$month_year = date('Y');
		$cmonth = date('m');
		$cyear = date('Y');

		if ($year != '') {
			$month_new = (string) $g_month;
			if (new_strlen($month_new) == 1) {
				$month_new = '0' . $month_new;
			}
			$month = $month_new;
			$month_year = (int) $year;

		}

		if ($department != '') {
			$arrdepartment = $this->staff_model->get('', 'staffid in (select tblstaff_departments.staffid from tblstaff_departments where departmentid = ' . $department . ')');
			$temp = '';
			foreach ($arrdepartment as $value) {
				$temp = $temp . $value['staffid'] . ',';
			}
			$temp = rtrim($temp, ",");
			$department_querystring = 'FIND_IN_SET(staffid, "' . $temp . '")';
		}

		if ($staff != '') {
			$temp = '';
			$araylengh = count($staff);
			for ($i = 0; $i < $araylengh; $i++) {
				$temp = $temp . $staff[$i];
				if ($i != $araylengh - 1) {
					$temp = $temp . ',';
				}
			}
			$staff_querystring = 'FIND_IN_SET(staffid, "' . $temp . '")';
		}

		$arrQuery = array($staff_querystring, $department_querystring, $month_year_querystring, $querystring);

		$newquerystring = '';
		foreach ($arrQuery as $string) {
			if ($string != '') {
				$newquerystring = $newquerystring . $string . ' AND ';
			}
		}

		$newquerystring = rtrim($newquerystring, "AND ");
		if ($newquerystring == '') {
			$newquerystring = [];
		}

		// data return
		$data_object = [];
		$index_data_object = 0;
		$bonus_status = true;

		if ($newquerystring != '') {

			//load staff
			if (!is_admin() && !has_permission('hrp_employee', '', 'view')) {
				//View own
				$staffs = $this->hr_payroll_model->get_staff_timekeeping_applicable_object(get_staffid_by_permission($newquerystring));
			} else {
				//admin or view global
				$staffs = $this->hr_payroll_model->get_staff_timekeeping_applicable_object($newquerystring);
			}

			foreach ($staffs as $staffs_key => $staff_value) {

				$bonus_value = $this->hr_payroll_model->get_bonus_by_month($staff_value['staffid'], $months_filter);

				if ($bonus_value) {

					$data_object[$index_data_object]['staffid'] = $staff_value['staffid'];

					$data_object[$index_data_object]['hr_code'] = $staff_value['staff_identifi'];
					$data_object[$index_data_object]['staff_name'] = $staff_value['full_name'];

					$data_object[$index_data_object]['job_position'] = '';

					$data_object[$index_data_object]['bonus_kpi'] = $bonus_value->bonus_kpi;

				} else {
					$data_object[$index_data_object]['staffid'] = $staff_value['staffid'];

					$data_object[$index_data_object]['hr_code'] = $staff_value['staff_identifi'];
					$data_object[$index_data_object]['staff_name'] = $staff_value['full_name'];

					$data_object[$index_data_object]['job_position'] = '';

					$data_object[$index_data_object]['bonus_kpi'] = 0;

					$bonus_status = false;

				}

				$arr_department = $this->hr_payroll_model->get_staff_departments($staff_value['staffid'], true);

				$list_department = '';
				if (count($arr_department) > 0) {

					foreach ($arr_department as $key => $department) {
						$department_value = $this->departments_model->get($department);

						if ($department_value) {
							if (new_strlen($list_department) != 0) {
								$list_department .= ', ' . $department_value->name;
							} else {
								$list_department .= $department_value->name;
							}
						}

					}
				}

				$data_object[$index_data_object]['staff_departments'] = $list_department;

				$index_data_object++;

			}

		}

		//check is add new or update data
		if ($bonus_status == true) {
			$button_name = _l('hrp_update');
		} else {
			$button_name = _l('submit');
		}

		echo json_encode([
			'data_object' => $data_object,
			'button_name' => $button_name,
		]);
		die;
	}

	/**
	 * payslip
	 * @param  string $value
	 * @return [type]
	 */
	public function payslip($value = '') {
		if ($this->input->post()) {
			$data = $this->input->post();

			if (!$this->input->post('id')) {

				if (!is_admin() && !has_permission('hrp_payslip', '', 'create')) {
					access_denied('hrp_payslip');
				}

				$insert_id = $this->hr_payroll_model->add_payslip($data);
				if ($insert_id) {
					$message = _l('added_successfully', _l('hrp_payslip'));
					set_alert('success', $message);
				}
				redirect(admin_url('hr_payroll/payslip_manage'));

			}

		}
	}

	/**
	 * payslip closing
	 * @return [type]
	 */
	public function payslip_closing() {
		if (!has_permission('hrp_payslip', '', 'edit') && !is_admin()) {
			$message = _l('access_denied');
			echo json_encode(['danger' => false, 'message' => $message]);
			die;
		}
		if ($this->input->post()) {
			$data = $this->input->post();
			if(isset($data['csrf_token_name'])){
				unset($data['csrf_token_name']);
			}
			$hrp_payslip = $this->hr_payroll_model->get_hrp_payslip($data['id']);

			if ($hrp_payslip) {
				$payslip_checked = $this->hr_payroll_model->payslip_checked($hrp_payslip->payslip_month, $hrp_payslip->payslip_template_id, true);
				// if ($payslip_checked) {

					$result = $this->hr_payroll_model->payslip_close($data);
					if ($result == true) {
						$message = _l('hrp_updated_successfully');
						$status = true;
					} else {
						$message = _l('hrp_updated_failed');
						$status = false;
					}
				// } else {
				// 	$status = false;
				// 	$message = _l('payslip_for_the_month_of');
				// }

			} else {
				$message = _l('hrp_updated_failed');
				$status = false;
			}

			echo json_encode([
				'message' => $message,
				'status' => $status,
			]);
		}
	}

	/**
	 * payslip update status
	 * @param  [type] $id
	 * @return [type]
	 */
	public function payslip_update_status($id) {
		if (!is_admin() && !has_permission('hrp_payslip', '', 'udpate')) {
			access_denied('hrp_payslip');
		}

		$result = $this->hr_payroll_model->update_payslip_status($id, 'payslip_opening');
		if ($result) {
			set_alert('success', _l('hrp_updated_successfully'));
		} else {
			set_alert('warning', _l('hrp_updated_failed'));
		}
		redirect(admin_url('hr_payroll/payslip_manage'));
	}

	/**
	 * table staff payslip
	 * @return [type]
	 */
	public function table_staff_payslip() {
		$this->app->get_table_data(module_views_path('hr_payroll', 'employee_payslip/table_staff_payslip'));
	}

	/**
	 * view staff payslip modal
	 * @return [type]
	 */
	public function view_staff_payslip_modal() {
		if (!$this->input->is_ajax_request()) {
			show_404();
		}

		$this->load->model('departments_model');

		if ($this->input->post('slug') === 'view') {
			$payslip_detail_id = $this->input->post('payslip_detail_id');

			$data['payslip_detail'] = $this->hr_payroll_model->get_payslip_detail($payslip_detail_id);

			$arr_department = $this->hr_payroll_model->get_staff_departments($data['payslip_detail']->staff_id, true);
			$list_department = '';
			if (count($arr_department) > 0) {

				foreach ($arr_department as $key => $department) {
					$department_value = $this->departments_model->get($department);

					if ($department_value) {
						if (new_strlen($list_department) != 0) {
							$list_department .= ', ' . $department_value->name;
						} else {
							$list_department .= $department_value->name;
						}
					}
				}
			}

			$employee = $this->hr_payroll_model->get_employees_data($data['payslip_detail']->month, '', ' staff_id = ' . $data['payslip_detail']->staff_id);

			$data['payslip'] = $this->hr_payroll_model->get_hrp_payslip($data['payslip_detail']->payslip_id);
			if($data['payslip'] && is_null($data['payslip']->to_currency_name)){
				$base_currency = get_base_currency();
				$base_currency_id = 0;
				if ($base_currency) {
					$data['payslip']->to_currency_name = $base_currency->name;
				}
			}

			$data['employee'] = count($employee) > 0 ? $employee[0] : [];
			$data['list_department'] = $list_department;

			$this->load->view('employee_payslip/staff_payslip_modal_view', $data);
		}
	}

	/**
	 * reports
	 * @return [type]
	 */
	public function reports() {
		if (!has_permission('hrp_report', '', 'view') && !is_admin()) {
			access_denied('reports');
		}

		$this->load->model('staff_model');
		$this->load->model('departments_model');

		$data['mysqlVersion'] = $this->db->query('SELECT VERSION() as version')->row();
		$data['sqlMode'] = $this->db->query('SELECT @@sql_mode as mode')->row();
		// $data['position']     = $this->hr_payroll_model->get_job_position();
		$data['staff'] = $this->staff_model->get();
		$data['department'] = $this->departments_model->get();
		$data['title'] = _l('hr_reports');

		$this->load->view('reports/manage_reports', $data);
	}

	/**
	 * payslip report
	 * @return [type]
	 */
	public function payslip_report() {
		if ($this->input->is_ajax_request()) {
			if ($this->input->post()) {

				$months_report = $this->input->post('months_filter');
				$position_filter = $this->input->post('position_filter');
				$department_filter = $this->input->post('department_filter');
				$staff_filter = $this->input->post('staff_filter');

				if ($months_report == 'this_month') {
					$from_date = date('Y-m-01');
					$to_date = date('Y-m-t');
				}
				if ($months_report == '1') {
					$from_date = date('Y-m-01', strtotime('first day of last month'));
					$to_date = date('Y-m-t', strtotime('last day of last month'));
				}
				if ($months_report == 'this_year') {
					$from_date = date('Y-m-d', strtotime(date('Y-01-01')));
					$to_date = date('Y-m-d', strtotime(date('Y-12-31')));
				}
				if ($months_report == 'last_year') {
					$from_date = date('Y-m-d', strtotime(date(date('Y', strtotime('last year')) . '-01-01')));
					$to_date = date('Y-m-d', strtotime(date(date('Y', strtotime('last year')) . '-12-31')));
				}

				if ($months_report == '3') {
					$months_report--;
					$from_date = date('Y-m-01', strtotime("-$months_report MONTH"));
					$to_date = date('Y-m-t');
				}
				if ($months_report == '6') {
					$months_report--;
					$from_date = date('Y-m-01', strtotime("-$months_report MONTH"));
					$to_date = date('Y-m-t');

				}
				if ($months_report == '12') {
					$months_report--;
					$from_date = date('Y-m-01', strtotime("-$months_report MONTH"));
					$to_date = date('Y-m-t');

				}
				if ($months_report == 'custom') {
					$from_date = to_sql_date($this->input->post('report_from'));
					$to_date = to_sql_date($this->input->post('report_to'));
				}

				$select = [
					'month',
					'pay_slip_number',
					'employee_name',
					'gross_pay',
					'total_deductions',
					'income_tax_paye',
					'it_rebate_value',
					'commission_amount',
					'bonus_kpi',
					'total_insurance',
					'net_pay',
					'total_cost',
				];
				$query = '';

				if (isset($from_date) && isset($to_date)) {

					$query = ' month >= \'' . $from_date . '\' and month <= \'' . $to_date . '\' and ';
				} else {
					$query = '';
				}

				if (isset($staff_filter)) {
					$staffid_list = implode(',', $staff_filter);
					$query .= db_prefix() . 'hrp_payslip_details.staff_id in (' . $staffid_list . ') and ';
				}
				if (isset($department_filter)) {
					$department_list = implode(',', $department_filter);
					$query .= db_prefix() . 'hrp_payslip_details.staff_id in (SELECT staffid FROM ' . db_prefix() . 'staff_departments where departmentid in (' . $department_list . ')) and ';
				}

				$query .= db_prefix() . 'hrp_payslips.payslip_status = "payslip_closing" and ';

				$total_query = '';
				if (($query) && ($query != '')) {
					$total_query = rtrim($query, ' and');
					$total_query = ' where ' . $total_query;
				}

				$where = [$total_query];

				$aColumns = $select;
				$sIndexColumn = 'id';
				$sTable = db_prefix() . 'hrp_payslip_details';
				$join = [
					'LEFT JOIN ' . db_prefix() . 'hrp_payslips ON ' . db_prefix() . 'hrp_payslip_details.payslip_id = ' . db_prefix() . 'hrp_payslips.id',
				];

				$result = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, [
					db_prefix() . 'hrp_payslip_details.id',
					db_prefix() . 'hrp_payslip_details.month',
					'payment_run_date',
					db_prefix() . 'hrp_payslip_details.payslip_id',

				]);

				$output = $result['output'];
				$rResult = $result['rResult'];
				foreach ($rResult as $aRow) {
					$payslip = $this->hr_payroll_model->get_hrp_payslip($aRow['payslip_id']);
					if($payslip && is_null($payslip->to_currency_name)){
						$base_currency = get_base_currency();
						$base_currency_id = 0;
						if ($base_currency) {
							$payslip->to_currency_name = $base_currency->name;
						}
					}

					$row = [];

					$row[] = $aRow['id'];
					$row[] = date('Y-m', strtotime($aRow['month']));
					$row[] = $aRow['payment_run_date'];
					$row[] = $aRow['pay_slip_number'];
					$row[] = $aRow['employee_name'];
					$row[] = currency_converter_value($aRow['gross_pay'], $payslip->to_currency_rate, $payslip->to_currency_name ?? '', true);
					$row[] = currency_converter_value($aRow['total_deductions'], $payslip->to_currency_rate, $payslip->to_currency_name ?? '', true);
					$row[] = currency_converter_value($aRow['income_tax_paye'], $payslip->to_currency_rate, $payslip->to_currency_name ?? '', true);
					$row[] = currency_converter_value($aRow['it_rebate_value'], $payslip->to_currency_rate, $payslip->to_currency_name ?? '', true);
					$row[] = currency_converter_value($aRow['commission_amount'], $payslip->to_currency_rate, $payslip->to_currency_name ?? '', true);
					$row[] = currency_converter_value($aRow['bonus_kpi'], $payslip->to_currency_rate, $payslip->to_currency_name ?? '', true);
					$row[] = currency_converter_value($aRow['total_insurance'], $payslip->to_currency_rate, $payslip->to_currency_name ?? '', true);
					$row[] = currency_converter_value($aRow['net_pay'], $payslip->to_currency_rate, $payslip->to_currency_name ?? '', true);
					$row[] = currency_converter_value($aRow['total_cost'], $payslip->to_currency_rate, $payslip->to_currency_name ?? '', true);

					$output['aaData'][] = $row;
				}

				echo json_encode($output);
				die();
			}
		}
	}

	/**
	 * income summary report
	 * @return [type]
	 */
	public function income_summary_report() {
		if ($this->input->is_ajax_request()) {
			if ($this->input->post()) {
				$this->load->model('departments_model');

				$months_report = $this->input->post('months_filter');
				$position_filter = $this->input->post('position_filter');
				$department_filter = $this->input->post('department_filter');
				$staff_filter = $this->input->post('staff_filter');

				if ($months_report == 'this_month') {
					$from_date = date('Y-m-01');
					$to_date = date('Y-m-t');
				}
				if ($months_report == '1') {
					$from_date = date('Y-m-01', strtotime('first day of last month'));
					$to_date = date('Y-m-t', strtotime('last day of last month'));
				}
				if ($months_report == 'this_year') {
					$from_date = date('Y-m-d', strtotime(date('Y-01-01')));
					$to_date = date('Y-m-d', strtotime(date('Y-12-31')));
				}
				if ($months_report == 'last_year') {
					$from_date = date('Y-m-d', strtotime(date(date('Y', strtotime('last year')) . '-01-01')));
					$to_date = date('Y-m-d', strtotime(date(date('Y', strtotime('last year')) . '-12-31')));
				}

				if ($months_report == '3') {
					$months_report--;
					$from_date = date('Y-m-01', strtotime("-$months_report MONTH"));
					$to_date = date('Y-m-t');
				}
				if ($months_report == '6') {
					$months_report--;
					$from_date = date('Y-m-01', strtotime("-$months_report MONTH"));
					$to_date = date('Y-m-t');

				}
				if ($months_report == '12') {
					$months_report--;
					$from_date = date('Y-m-01', strtotime("-$months_report MONTH"));
					$to_date = date('Y-m-t');

				}
				if ($months_report == 'custom') {
					$from_date = to_sql_date($this->input->post('report_from'));
					$to_date = to_sql_date($this->input->post('report_to'));
				}

				$select = [
					'staffid',

				];
				$query = '';
				$staff_query = '';

				if (isset($from_date) && isset($to_date)) {

					$staff_query = ' month >= \'' . $from_date . '\' and month <= \'' . $to_date . '\' and ';
				} else {
					$staff_query = '';
				}

				if (isset($staff_filter)) {
					$staffid_list = implode(',', $staff_filter);
					$query .= db_prefix() . 'staff.staffid in (' . $staffid_list . ') and ';

					$staff_query .= db_prefix() . 'hrp_payslip_details.staff_id in (' . $staffid_list . ') and ';
				}

				if (isset($department_filter)) {
					$department_list = implode(',', $department_filter);
					$query .= db_prefix() . 'staff.staffid in (SELECT staffid FROM ' . db_prefix() . 'staff_departments where departmentid in (' . $department_list . ')) and ';

					$staff_query .= db_prefix() . 'hrp_payslip_details.staff_id in (SELECT staffid FROM ' . db_prefix() . 'staff_departments where departmentid in (' . $department_list . ')) and ';
				}

				$query .= db_prefix() . 'staff.active = "1" and ';

				$total_query = '';
				$staff_query_trim = '';
				if (($query) && ($query != '')) {
					$total_query = rtrim($query, ' and');
					$total_query = ' where ' . $total_query;
				}

				if (($staff_query) && ($staff_query != '')) {
					$staff_query_trim = rtrim($staff_query, ' and');

				}
				$where = [$total_query];

				$aColumns = $select;
				$sIndexColumn = 'staffid';
				$sTable = db_prefix() . 'staff';
				$join = [];

				$result = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, ['firstname', 'lastname']);

				$output = $result['output'];
				$rResult = $result['rResult'];
				$rel_type = hrp_get_hr_profile_status();
				$staff_income = $this->hr_payroll_model->get_income_summary_report($staff_query_trim);

				$base_currency = get_base_currency();
				$base_currency_name = '';
				if ($base_currency) {
					$base_currency_name = $base_currency->name;
				}

				$staffs_data = [];
				$staffs = $this->hr_payroll_model->get_staff_timekeeping_applicable_object();
				foreach ($staffs as $value) {
					$staffs_data[$value['staffid']] = $value;
				}

				$temp = 0;
				foreach ($rResult as $staff_key => $aRow) {
					$row = [];

					$arr_department = $this->hr_payroll_model->get_staff_departments($aRow['staffid'], true);

					$list_department = '';
					if (count($arr_department) > 0) {

						foreach ($arr_department as $key => $department) {
							$department_value = $this->departments_model->get($department);

							if ($department_value) {
								if (new_strlen($list_department) != 0) {
									$list_department .= ', ' . $department_value->name;
								} else {
									$list_department .= $department_value->name;
								}
							}
						}
					}

					$data_object_kpi[$staff_key]['department_name'] = $list_department;

					if ($rel_type == 'hr_records') {
						if (isset($staffs_data[$aRow['staffid']])) {
							$row[] = $staffs_data[$aRow['staffid']]['staff_identifi'];
						} else {
							$row[] = '';
						}
					} else {
						$row[] = $this->hr_payroll_model->hrp_format_code('EXS', $aRow['staffid'], 5);
					}

					$row[] = $aRow['firstname'] . ' ' . $aRow['lastname'];

					$row[] = $list_department;

					if (isset($staff_income[$aRow['staffid']]['01'])) {
						$row[] = app_format_money($staff_income[$aRow['staffid']]['01'], $base_currency_name);
						$temp++;
					} else {
						$row[] = 0;
					}

					if (isset($staff_income[$aRow['staffid']]['02'])) {
						$row[] = app_format_money($staff_income[$aRow['staffid']]['02'], $base_currency_name);
						$temp++;
					} else {
						$row[] = 0;
					}

					if (isset($staff_income[$aRow['staffid']]['03'])) {
						$row[] = app_format_money($staff_income[$aRow['staffid']]['03'], $base_currency_name);
						$temp++;
					} else {
						$row[] = 0;
					}

					if (isset($staff_income[$aRow['staffid']]['04'])) {
						$row[] = app_format_money($staff_income[$aRow['staffid']]['04'], $base_currency_name);
						$temp++;
					} else {
						$row[] = 0;
					}

					if (isset($staff_income[$aRow['staffid']]['05'])) {
						$row[] = app_format_money($staff_income[$aRow['staffid']]['05'], $base_currency_name);
						$temp++;
					} else {
						$row[] = 0;
					}

					if (isset($staff_income[$aRow['staffid']]['06'])) {
						$row[] = app_format_money($staff_income[$aRow['staffid']]['06'], $base_currency_name);
						$temp++;
					} else {
						$row[] = 0;
					}

					if (isset($staff_income[$aRow['staffid']]['07'])) {
						$row[] = app_format_money($staff_income[$aRow['staffid']]['07'], $base_currency_name);
						$temp++;
					} else {
						$row[] = 0;
					}

					if (isset($staff_income[$aRow['staffid']]['08'])) {
						$row[] = app_format_money($staff_income[$aRow['staffid']]['08'], $base_currency_name);
						$temp++;
					} else {
						$row[] = 0;
					}

					if (isset($staff_income[$aRow['staffid']]['09'])) {
						$row[] = app_format_money($staff_income[$aRow['staffid']]['09'], $base_currency_name);
						$temp++;
					} else {
						$row[] = 0;
					}

					if (isset($staff_income[$aRow['staffid']]['10'])) {
						$row[] = app_format_money($staff_income[$aRow['staffid']]['10'], $base_currency_name);
						$temp++;
					} else {
						$row[] = 0;
					}

					if (isset($staff_income[$aRow['staffid']]['11'])) {
						$row[] = app_format_money($staff_income[$aRow['staffid']]['11'], $base_currency_name);
						$temp++;
					} else {
						$row[] = 0;
					}

					if (isset($staff_income[$aRow['staffid']]['12'])) {
						$row[] = app_format_money($staff_income[$aRow['staffid']]['12'], $base_currency_name);
						$temp++;
					} else {
						$row[] = 0;
					}

					if ($temp != 0) {
						if (isset($staff_income[$aRow['staffid']]['average_income'])) {

							$row[] = app_format_money($staff_income[$aRow['staffid']]['average_income'] / $temp, $base_currency_name);
						} else {
							$row[] = 0;
						}
					} else {
						$row[] = 0;
					}

					$temp = 0;
					$output['aaData'][] = $row;
				}

				echo json_encode($output);
				die();

			}
		}
	}

	/**
	 * insurance cost summary report
	 * @return [type]
	 */
	public function insurance_cost_summary_report() {
		if ($this->input->is_ajax_request()) {
			if ($this->input->post()) {
				$this->load->model('departments_model');

				$months_report = $this->input->post('months_filter');
				$position_filter = $this->input->post('position_filter');
				$department_filter = $this->input->post('department_filter');
				$staff_filter = $this->input->post('staff_filter');

				if ($months_report == 'this_month') {
					$from_date = date('Y-m-01');
					$to_date = date('Y-m-t');
				}
				if ($months_report == '1') {
					$from_date = date('Y-m-01', strtotime('first day of last month'));
					$to_date = date('Y-m-t', strtotime('last day of last month'));
				}
				if ($months_report == 'this_year') {
					$from_date = date('Y-m-d', strtotime(date('Y-01-01')));
					$to_date = date('Y-m-d', strtotime(date('Y-12-31')));
				}
				if ($months_report == 'last_year') {
					$from_date = date('Y-m-d', strtotime(date(date('Y', strtotime('last year')) . '-01-01')));
					$to_date = date('Y-m-d', strtotime(date(date('Y', strtotime('last year')) . '-12-31')));
				}

				if ($months_report == '3') {
					$months_report--;
					$from_date = date('Y-m-01', strtotime("-$months_report MONTH"));
					$to_date = date('Y-m-t');
				}
				if ($months_report == '6') {
					$months_report--;
					$from_date = date('Y-m-01', strtotime("-$months_report MONTH"));
					$to_date = date('Y-m-t');

				}
				if ($months_report == '12') {
					$months_report--;
					$from_date = date('Y-m-01', strtotime("-$months_report MONTH"));
					$to_date = date('Y-m-t');

				}
				if ($months_report == 'custom') {
					$from_date = to_sql_date($this->input->post('report_from'));
					$to_date = to_sql_date($this->input->post('report_to'));
				}

				$select = [
					'departmentid',

				];
				$query = '';
				$staff_query = '';

				if (isset($from_date) && isset($to_date)) {

					$staff_query = ' month >= \'' . $from_date . '\' and month <= \'' . $to_date . '\' and ';
				} else {
					$staff_query = '';
				}

				if (isset($staff_filter)) {
					$staffid_list = implode(',', $staff_filter);
					if(1==2){
						$query .= db_prefix() . 'staff.staffid in (' . $staffid_list . ') and ';
					}

					$staff_query .= db_prefix() . 'hrp_payslip_details.staff_id in (' . $staffid_list . ') and ';
				}

				if (isset($department_filter)) {
					$department_list = implode(',', $department_filter);
					$query .= db_prefix() . 'departments.departmentid in  (' . $department_list . ') and ';

					$staff_query .= db_prefix() . 'hrp_payslip_details.staff_id in (SELECT staffid FROM ' . db_prefix() . 'staff_departments where departmentid in (' . $department_list . ')) and ';
				}

				$total_query = '';
				$staff_query_trim = '';
				if (($query) && ($query != '')) {
					$total_query = rtrim($query, ' and');
					$total_query = ' where ' . $total_query;
				}

				if (($staff_query) && ($staff_query != '')) {
					$staff_query_trim = rtrim($staff_query, ' and');

				}

				$where = [$total_query];

				$aColumns = $select;
				$sIndexColumn = 'departmentid';
				$sTable = db_prefix() . 'departments';
				$join = [];

				$result = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, ['name']);

				$output = $result['output'];
				$rResult = $result['rResult'];
				$rel_type = hrp_get_hr_profile_status();

				$staff_insurance = $this->hr_payroll_model->get_insurance_summary_report($staff_query_trim);
				$base_currency = get_base_currency();
				$base_currency_name = '';
				if ($base_currency) {
					$base_currency_name = $base_currency->name;
				}

				$temp_insurance = 0;
				foreach ($rResult as $der_key => $aRow) {
					$row = [];

					$row[] = $aRow['name'];

					$staff_ids = $this->hr_payroll_model->get_staff_in_deparment($aRow['departmentid']);

					foreach ($staff_ids as $key => $value) {
						if (isset($staff_insurance[$value])) {
							$temp_insurance += $staff_insurance[$value];
						}
					}

					$row[] = app_format_money($temp_insurance, $base_currency_name);

					$temp_insurance = 0;

					$output['aaData'][] = $row;
				}

				echo json_encode($output);
				die();

			}
		}
	}

	/**
	 * payslip chart
	 * @return [type]
	 */
	public function payslip_chart() {
		if ($this->input->is_ajax_request()) {

			$months_report = $this->input->post('months_filter');
			$staff_id = $this->input->post('staff_id');
			$filter_by_year = '';

			$filter_by_year .= 'date_format(month, "%Y") = ' . $months_report;

			echo json_encode($this->hr_payroll_model->payslip_chart($filter_by_year, $staff_id));
		}
	}

	/**
	 * department payslip chart
	 * @return [type]
	 */
	public function department_payslip_chart() {
		if ($this->input->is_ajax_request()) {
			if ($this->input->post()) {
				$months_report = $this->input->post('months_filter');
				$department_filter = $this->input->post('department_filter');

				$from_date = date('Y-m-d', strtotime('1997-01-01'));
				$to_date = date('Y-m-d', strtotime(date('Y-12-31')));
				if ($months_report == 'this_month') {

					$from_date = date('Y-m-01');
					$to_date = date('Y-m-t');
				}
				if ($months_report == '1') {
					$from_date = date('Y-m-01', strtotime('first day of last month'));
					$to_date = date('Y-m-t', strtotime('last day of last month'));

				}
				if ($months_report == 'this_year') {
					$from_date = date('Y-m-d', strtotime(date('Y-01-01')));
					$to_date = date('Y-m-d', strtotime(date('Y-12-31')));
				}
				if ($months_report == 'last_year') {
					$from_date = date('Y-m-d', strtotime(date(date('Y', strtotime('last year')) . '-01-01')));
					$to_date = date('Y-m-d', strtotime(date(date('Y', strtotime('last year')) . '-12-31')));
				}

				if ($months_report == '3') {
					$months_report--;
					$from_date = date('Y-m-01', strtotime("-$months_report MONTH"));
					$to_date = date('Y-m-t');

				}
				if ($months_report == '6') {
					$months_report--;
					$from_date = date('Y-m-01', strtotime("-$months_report MONTH"));
					$to_date = date('Y-m-t');

				}
				if ($months_report == '12') {
					$months_report--;
					$from_date = date('Y-m-01', strtotime("-$months_report MONTH"));
					$to_date = date('Y-m-t');

				}
				if ($months_report == 'custom') {
					$from_date = to_sql_date($this->input->post('report_from'));
					$to_date = to_sql_date($this->input->post('report_to'));
				}

				$id_department = '';
				if (isset($department_filter)) {
					$id_department = implode(',', $department_filter);
				}
				$circle_mode = false;
				$list_diploma = array(
					"ps_total_insurance",
					"ps_income_tax_paye",
					"ps_total_deductions",
					"ps_net_pay",
				);
				$list_result = array();
				$list_data_department = [];

				$staff_payslip = $this->hr_payroll_model->get_department_payslip_chart($from_date, $to_date);
				$base_currency = get_base_currency();

				$current_name = '';
				if ($base_currency) {
					$current_name .= $base_currency->name;
				}

				echo json_encode([
					'department' => $staff_payslip['department_name'],
					'data_result' => $staff_payslip['list_result'],
					'circle_mode' => $circle_mode,
					'current_name' => $current_name,
				]);
				die;
			}
		}
	}

	/**
	 * payslip template checked
	 * @return [type]
	 */
	public function payslip_template_checked() {
		$data = $this->input->post();
		if ($this->input->is_ajax_request()) {
			$payslip_template_checked = $this->hr_payroll_model->payslip_template_checked($data);

			if ($payslip_template_checked === true) {
				$status = true;
			} else {
				$status = false;
			}

			echo json_encode([
				'status' => $status,
				'staff_name' => $payslip_template_checked,
			]);
		}
	}

	/**
	 * payslip checked
	 * @return [type]
	 */
	public function payslip_checked() {
		$data = $this->input->post();
		if ($this->input->is_ajax_request()) {
			$payslip_checked = $this->hr_payroll_model->payslip_checked($data['payslip_month'], $data['payslip_template_id']);

			if ($payslip_checked) {
				$status = true;
				$message = '';
			} else {
				$status = false;
				$message = _l('payslip_for_the_month_of');
			}
			$status = true;

			echo json_encode([
				'status' => $status,
				'message' => $message,
			]);
		}
	}

	/**
	 * create payslip file
	 * @return [type]
	 */
	public function create_payslip_file() {

		$data = $this->input->post();
		$get_data = $this->hr_payroll_model->payslip_download($data);
		if ($get_data) {

			if (!class_exists('XLSXReader_fin')) {
				require_once module_dir_path(HR_PAYROLL_MODULE_NAME) . '/assets/plugins/XLSXReader/XLSXReader.php';
			}
			require_once module_dir_path(HR_PAYROLL_MODULE_NAME) . '/assets/plugins/XLSXWriter/xlsxwriter.class.php';

			$this->delete_error_file_day_before('1', HR_PAYROLL_CREATE_PAYSLIP_EXCEL);

			$payroll_system_columns_dont_format = payroll_system_columns_dont_format();

			//Writer file
			$writer_header = [];
			$widths = [];
			$col_style1 = [];

			$payroll_column_key = $get_data['payroll_column_key'];
			foreach ($get_data['payroll_header'] as $key => $value) {
				if (!in_array($payroll_column_key[$key], $payroll_system_columns_dont_format)) {

					$writer_header[$value] = '#,##0.00';
				} else {
					$writer_header[$value] = 'string';

				}
				$widths[] = 30;
				$col_style1[] = $key;
			}

			$writer = new XLSXWriter();

			$style1 = ['widths' => $widths, 'fill' => '#ff9800', 'font-style' => 'bold', 'color' => '#0a0a0a', 'border' => 'left,right,top,bottom', 'border-color' => '#0a0a0a', 'font-size' => 13];

			$writer->writeSheetHeader_v2('Sheet1', $writer_header, $col_options = ['widths' => $widths, 'fill' => '#03a9f46b', 'font-style' => 'bold', 'color' => '#0a0a0a', 'border' => 'left,right,top,bottom', 'border-color' => '#0a0a0a', 'font-size' => 13],
				$col_style1, $style1);

			$data_object_kpi = [];
			$writer->writeSheetRow('Sheet1', $get_data['payroll_header']);

			foreach ($get_data['payslip_detail'] as $data_key => $payslip_detail) {

				$writer->writeSheetRow('Sheet1', array_values($payslip_detail));

			}

			$filename = 'Payslip_' . date('Y-m', strtotime($get_data['month'])) . '_' . strtotime(date('Y-m-d H:i:s')) . '.xlsx';
			$writer->writeToFile(new_str_replace($filename, HR_PAYROLL_CREATE_PAYSLIP_EXCEL . $filename, $filename));

			echo json_encode([
				'success' => true,
				'message' => _l('create_a_payslip_for_successful_download'),
				'site_url' => site_url(),
				'staff_id' => get_staff_user_id(),
				'filename' => HR_PAYROLL_CREATE_PAYSLIP_EXCEL . $filename,
			]);
			die;
		}

		echo json_encode([
			'success' => false,
			'message' => _l('an_error_occurred_while_creating_a_payslip_to_download'),
			'site_url' => site_url(),
			'staff_id' => get_staff_user_id(),
			'filename' => HR_PAYROLL_CREATE_PAYSLIP_EXCEL,
		]);
		die;

	}

	/**
	 *employees copy
	 * @return [type]
	 */
	public function employees_copy() {
		if (!has_permission('hrp_employee', '', 'create') && !has_permission('hrp_employee', '', 'edit') && !is_admin()) {
			access_denied('hrp_employee');
		}

		if ($this->input->post()) {
			$data = $this->input->post();
			$results = $this->hr_payroll_model->employees_copy($data);

			if ($results) {
				$message = _l('updated_successfully');
			} else {
				$message = _l('hrp_updated_failed');
			}

			echo json_encode([
				'message' => $results['message'],
				'status' => $results['status'],
			]);
		}

	}

	/**
	 * reset data
	 * @return [type]
	 */
	public function reset_data() {

		if (!is_admin()) {
			access_denied('hr_payroll');
		}
		//delete hrp_employees_value
		$this->db->truncate(db_prefix() . 'hrp_employees_value');

		//delete hrp_employees_timesheets
		$this->db->truncate(db_prefix() . 'hrp_employees_timesheets');

		//delete hrp_commissions
		$this->db->truncate(db_prefix() . 'hrp_commissions');

		//delete hrp_salary_deductions
		$this->db->truncate(db_prefix() . 'hrp_salary_deductions');

		//delete hrp_bonus_kpi
		$this->db->truncate(db_prefix() . 'hrp_bonus_kpi');

		//delete hrp_staff_insurances
		$this->db->truncate(db_prefix() . 'hrp_staff_insurances');

		//delete hrp_payslips
		$this->db->truncate(db_prefix() . 'hrp_payslips');

		//delete hrp_payslip_details
		$this->db->truncate(db_prefix() . 'hrp_payslip_details');

		//delete attendance_sample_file
		foreach (glob('modules/hr_payroll/uploads/attendance_sample_file/' . '*') as $file) {
			$file_arr = new_explode("/", $file);
			$filename = array_pop($file_arr);

			if (file_exists($file)) {
				//don't delete index.html file
				if ($filename != 'index.html') {
					unlink('modules/hr_payroll/uploads/attendance_sample_file/' . $filename);
				}
			}

		}

		foreach (glob('modules/hr_payroll/uploads/commissions_sample_file/' . '*') as $file) {
			$file_arr = new_explode("/", $file);
			$filename = array_pop($file_arr);

			if (file_exists($file)) {
				//don't delete index.html file
				if ($filename != 'index.html') {
					unlink('modules/hr_payroll/uploads/commissions_sample_file/' . $filename);
				}
			}

		}

		foreach (glob('modules/hr_payroll/uploads/employees_sample_file/' . '*') as $file) {
			$file_arr = new_explode("/", $file);
			$filename = array_pop($file_arr);

			if (file_exists($file)) {
				//don't delete index.html file
				if ($filename != 'index.html') {
					unlink('modules/hr_payroll/uploads/employees_sample_file/' . $filename);
				}
			}

		}

		foreach (glob('modules/hr_payroll/uploads/file_error_response/' . '*') as $file) {
			$file_arr = new_explode("/", $file);
			$filename = array_pop($file_arr);

			if (file_exists($file)) {
				//don't delete index.html file
				if ($filename != 'index.html') {
					unlink('modules/hr_payroll/uploads/file_error_response/' . $filename);
				}
			}

		}

		foreach (glob('modules/hr_payroll/uploads/payslip/' . '*') as $file) {
			$file_arr = new_explode("/", $file);
			$filename = array_pop($file_arr);

			if (file_exists($file)) {
				//don't delete index.html file
				if ($filename != 'index.html') {
					unlink('modules/hr_payroll/uploads/payslip/' . $filename);
				}
			}

		}

		foreach (glob('modules/hr_payroll/uploads/payslip_excel_file/' . '*') as $file) {
			$file_arr = new_explode("/", $file);
			$filename = array_pop($file_arr);

			if (file_exists($file)) {
				//don't delete index.html file
				if ($filename != 'index.html') {
					unlink('modules/hr_payroll/uploads/payslip_excel_file/' . $filename);
				}
			}

		}

		set_alert('success', _l('reset_data_successful'));
		redirect(admin_url('hr_payroll/setting?group=reset_data'));

	}

	/**
	 * employee export pdf
	 * @param  [type] $id 
	 * @return [type]     
	 */
	public function employee_export_pdf($id) {
		if (!$id) {
			show_404();
		}

		$this->db->where('id', $id);
		$hrp_payslip_details = $this->db->get(db_prefix() . 'hrp_payslip_details')->result_array();

		$data = [];
		$data['payslip_detail'] = $hrp_payslip_details[0];

		$arr_department = $this->hr_payroll_model->get_staff_departments($data['payslip_detail']['staff_id'], true);
		$list_department = '';
		if (count($arr_department) > 0) {

			foreach ($arr_department as $key => $department) {
				$this->load->model('departments_model');

				$department_value = $this->departments_model->get($department);

				if ($department_value) {
					if (new_strlen($list_department) != 0) {
						$list_department .= ', ' . $department_value->name;
					} else {
						$list_department .= $department_value->name;
					}
				}
			}
		}

		$employee = $this->hr_payroll_model->get_employees_data($data['payslip_detail']['month'], '', ' staff_id = ' . $data['payslip_detail']['staff_id']);
		$data['employee'] = count($employee) > 0 ? $employee[0] : [];
		$data['list_department'] = $list_department;
		$data['payslip'] = $this->hr_payroll_model->get_hrp_payslip($data['payslip_detail']['payslip_id']);
		if($data['payslip'] && is_null($data['payslip']->to_currency_name)){
			$base_currency = get_base_currency();
			$base_currency_id = 0;
			if ($base_currency) {
				$data['payslip']->to_currency_name = $base_currency->name;
			}
		}

		$html = $this->load->view('hr_payroll/employee_payslip/export_employee_payslip', $data, true);
		$html .= '<link href="' . module_dir_url(HR_PAYROLL_MODULE_NAME, 'assets/css/export_employee_pdf.css') . '"  rel="stylesheet" type="text/css" />';


		try {
			$pdf = $this->hr_payroll_model->employee_export_pdf($html);

		} catch (Exception $e) {
			echo new_html_entity_decode($e->getMessage());
			die;
		}

		$type = 'D';

		if ($this->input->get('output_type')) {
			$type = $this->input->get('output_type');
		}

		if ($this->input->get('print')) {
			$type = 'I';
		}

		$pdf->Output($data['payslip_detail']['employee_number'].'_'.date('m-Y', strtotime($data['payslip_detail']['month'])).'_'.strtotime(date('Y-m-d H:i:s')).'.pdf', $type);
	}

	/**
	 * payslip manage export pdf
	 * @param  [type] $id 
	 * @return [type]     
	 */
	public function payslip_manage_export_pdf($id)
	{
		if (!$id) {
			show_404();
		}

		$data = $this->input->post();

		//delete sub folder STOCK_EXPORT
		foreach(glob(HR_PAYROLL_EXPORT_EMPLOYEE_PAYSLIP . '*') as $file) { 
			$file_arr = new_explode("/",$file);
			$filename = array_pop($file_arr);

			if(file_exists($file)) {
				if ($filename != 'index.html') {
					unlink(HR_PAYROLL_EXPORT_EMPLOYEE_PAYSLIP.$filename);
				}
			}
		}

		$payslip = $this->hr_payroll_model->get_hrp_payslip($id);
		$payslip_details = $this->hr_payroll_model->get_payslip_detail_by_payslip_id($id);

		$has_pdf_template = false;

		foreach ($payslip_details as $payslip_detail) {
			$check_payslip_has_pdf_template = check_payslip_has_pdf_template($payslip_detail['payslip_id']);
			if(is_numeric($check_payslip_has_pdf_template) && is_numeric($check_payslip_has_pdf_template) && $check_payslip_has_pdf_template != 0 ){
				$has_pdf_template = true;
			}

			if($has_pdf_template){
				$payslip = $this->hr_payroll_model->hr_payroll_get_payslip_pdf_only_for_pdf($payslip_detail['id']);

				try {
					$pdf = hr_payroll_payslip_pdf($payslip);
				} catch (Exception $e) {
					echo $e->getMessage();
					die;
				}
			}else{
				$data = [];
				$data['payslip_detail'] = $payslip_detail;

				$arr_department = $this->hr_payroll_model->get_staff_departments($payslip_detail['staff_id'], true);
				$list_department = '';
				if (count($arr_department) > 0) {

					foreach ($arr_department as $key => $department) {
						$this->load->model('departments_model');

						$department_value = $this->departments_model->get($department);

						if ($department_value) {
							if (new_strlen($list_department) != 0) {
								$list_department .= ', ' . $department_value->name;
							} else {
								$list_department .= $department_value->name;
							}
						}
					}
				}

				$employee = $this->hr_payroll_model->get_employees_data($payslip_detail['month'], '', ' staff_id = ' . $payslip_detail['staff_id']);
				$data['employee'] = count($employee) > 0 ? $employee[0] : [];
				$data['list_department'] = $list_department;

				$data['payslip'] = $this->hr_payroll_model->get_hrp_payslip($data['payslip_detail']['payslip_id']);
				if($data['payslip'] && is_null($data['payslip']->to_currency_name)){
					$base_currency = get_base_currency();
					$base_currency_id = 0;
					if ($base_currency) {
						$data['payslip']->to_currency_name = $base_currency->name;
					}
				}
		
				$html = $this->load->view('hr_payroll/employee_payslip/export_employee_payslip', $data, true);
				$html .= '<link href="' . module_dir_url(HR_PAYROLL_MODULE_NAME, 'assets/css/export_employee_pdf.css') . '"  rel="stylesheet" type="text/css" />';


				try {
					$pdf = $this->hr_payroll_model->employee_export_pdf($html);

				} catch (Exception $e) {
					echo new_html_entity_decode($e->getMessage());
					die;
				}
			}

			if(!is_null($payslip_detail['employee_number']) && $payslip_detail['employee_number'] != ''  && $payslip_detail['employee_number'] != 0 ){
				$employee_name = $payslip_detail['employee_number'];
			}else{
				$employee_name = slug_it($payslip_detail['employee_name'] ?? '', ['separator' => '_']);
			}

			$this->re_save_to_dir($pdf, $employee_name .'_'.date('m-Y', strtotime($payslip_detail['month'])) . '.pdf');
		}

		$this->load->library('zip');

        //get list file
		foreach(glob(HR_PAYROLL_EXPORT_EMPLOYEE_PAYSLIP . '*') as $file) { 
			$file_arr = new_explode("/",$file);
			$filename = array_pop($file_arr);

			$this->zip->read_file(HR_PAYROLL_EXPORT_EMPLOYEE_PAYSLIP. $filename);
		}

		$this->zip->download($payslip->payslip_name .'_'. date('m-Y', strtotime($payslip->payslip_month)). '.zip');
		$this->zip->clear_data();
	}

	/**
	 * re save to dir
	 * @param  [type] $pdf       
	 * @param  [type] $file_name 
	 * @return [type]            
	 */
	private function re_save_to_dir($pdf, $file_name)
	{
		$dir = HR_PAYROLL_EXPORT_EMPLOYEE_PAYSLIP;

		$dir .= $file_name;

		$pdf->Output($dir, 'F');
	}

	/**
	 * manage attendance timesheet leaves
	 * @return [type] 
	 */
	public function manage_attendance_timesheet_leaves($month = '') {
		if (!has_permission('hrp_attendance', '', 'view') && !has_permission('hrp_attendance', '', 'view_own') && !is_admin()) {
			access_denied('hrp_attendance');
		}

		$this->load->model('staff_model');
		$this->load->model('departments_model');

		$rel_type = hrp_get_timesheets_status();

		//get current month
		if(strlen($month) > 0){
			$current_month = date('Y-m-d', strtotime($month . '-01'));
			$data['current_month'] = date('Y-m-d', strtotime($month . '-01'));
		}else{
			$current_month = date('Y-m-d', strtotime(date('Y-m') . '-01'));
			$data['current_month'] = date('Y-m-d', strtotime(date('Y-m') . '-01'));
		}

		//get day header in month
		$hrp_timesheet_leave_data_sample = hrp_timesheet_leave_data_sample();
		$days_header_in_month = $this->hr_payroll_model->get_day_header_in_month($current_month, $rel_type, false);

		$attendances = $this->hr_payroll_model->get_hrp_attendance_timesheet_leave($current_month);
		$attendances_value = [];
		$cell_background = [];
		$cell_background_data = [];

		foreach ($attendances as $key => $value) {
			$dt_cell_bg = [];
			foreach ($value as $hearder_key => $cell_value) {

				if($hearder_key != 'id' && $hearder_key != 'staff_id' && $hearder_key != 'month' && $hearder_key != 'paid_leave' && $hearder_key != 'unpaid_leave' && $hearder_key != 'rel_type'){
					if(strlen($cell_value) > 0){
						if(preg_match('/^PL:/', $cell_value) && strlen($cell_value) < 7){
							$dt_cell_bg[$hearder_key] = '#0c0';

						}elseif(preg_match('/^UPL:/', $cell_value) && strlen($cell_value) < 7){
							$dt_cell_bg[$hearder_key] = '#c00';

						}elseif(preg_match('/PL:/', $cell_value) && preg_match('/UPL:/', $cell_value)){
							$dt_cell_bg[$hearder_key] = '#FF9800';
						}else{
							$dt_cell_bg[$hearder_key] = '#fff';
						}
					}else{
						$dt_cell_bg[$hearder_key] = '#fff';
					}
				}elseif($hearder_key == 'paid_leave' && (float)$cell_value > 0){
					$dt_cell_bg[$hearder_key] = '#0c0';
				}elseif($hearder_key == 'unpaid_leave' && (float)$cell_value > 0){
					$dt_cell_bg[$hearder_key] = '#c00';
				}else{
					$dt_cell_bg[$hearder_key] = '#fff';
				}
			}
			$cell_background[$value['staff_id'] . '_' . $value['month']] = $dt_cell_bg;
			$attendances_value[$value['staff_id'] . '_' . $value['month']] = $value;
		}

		//load deparment by manager
		if (!is_admin() && !has_permission('hrp_employee', '', 'view')) {
			//View own
			$staffs = $this->hr_payroll_model->get_staff_timekeeping_applicable_object(get_staffid_by_permission());
		} else {
			//admin or view global
			$staffs = $this->hr_payroll_model->get_staff_timekeeping_applicable_object();
		}

		$data_object_kpi = [];

		foreach ($staffs as $staff_key => $staff_value) {
			/*check value from database*/

			$staff_i = $this->hr_payroll_model->get_staff_info($staff_value['staffid']);
			if ($staff_i) {

				if (isset($staff_i->staff_identifi)) {
					$data_object_kpi[$staff_key]['hr_code'] = $staff_i->staff_identifi;
				} else {
					$data_object_kpi[$staff_key]['hr_code'] = $this->hr_payroll_model->hrp_format_code('EXS', $staff_i->staffid, 5);
				}

				$data_object_kpi[$staff_key]['staff_name'] = $staff_i->firstname . ' ' . $staff_i->lastname;

				$arr_department = $this->hr_payroll_model->get_staff_departments($staff_i->staffid, true);

				$list_department = '';
				if (count($arr_department) > 0) {

					foreach ($arr_department as $key => $department) {
						$department_value = $this->departments_model->get($department);

						if ($department_value) {
							if (new_strlen($list_department) != 0) {
								$list_department .= ', ' . $department_value->name;
							} else {
								$list_department .= $department_value->name;
							}
						}

					}
				}

				$data_object_kpi[$staff_key]['staff_departments'] = $list_department;

			} else {
				$data_object_kpi[$staff_key]['hr_code'] = '';
				$data_object_kpi[$staff_key]['staff_name'] = '';
				$data_object_kpi[$staff_key]['staff_departments'] = '';

			}

			if (isset($attendances_value[$staff_value['staffid'] . '_' . $current_month])) {

				$data_object_kpi[$staff_key]['paid_leave'] = $attendances_value[$staff_value['staffid'] . '_' . $current_month]['paid_leave'];
				$data_object_kpi[$staff_key]['unpaid_leave'] = $attendances_value[$staff_value['staffid'] . '_' . $current_month]['unpaid_leave'];
				$data_object_kpi[$staff_key]['id'] = $attendances_value[$staff_value['staffid'] . '_' . $current_month]['id'];

				$data_object_kpi[$staff_key] = array_merge($data_object_kpi[$staff_key], $attendances_value[$staff_value['staffid'] . '_' . $current_month]);

				$cell_background_data[] = $cell_background[$staff_value['staffid'] . '_' . $current_month];
			} else {
			
				$data_object_kpi[$staff_key]['paid_leave'] = 0;
				$data_object_kpi[$staff_key]['unpaid_leave'] = 0;
				$data_object_kpi[$staff_key]['id'] = 0;
				$data_object_kpi[$staff_key] = array_merge($data_object_kpi[$staff_key], $days_header_in_month['days_header']);
				$cell_background_data[] = $hrp_timesheet_leave_data_sample;

			}
			$data_object_kpi[$staff_key]['rel_type'] = $rel_type;
			$data_object_kpi[$staff_key]['month'] = $current_month;
			$data_object_kpi[$staff_key]['staff_id'] = $staff_value['staffid'];

		}

		//check is add new or update data
		if (count($attendances_value) > 0) {
			$data['button_name'] = _l('hrp_update');
		} else {
			$data['button_name'] = _l('submit');
		}

		$data['departments'] = $this->departments_model->get();
		$data['roles'] = $this->roles_model->get();
		$data['staffs'] = $staffs;
		$data['data_object_kpi'] = $data_object_kpi;

		$data['body_value'] = json_encode($data_object_kpi);
		$data['columns'] = json_encode($days_header_in_month['columns_type']);
		$data['col_header'] = json_encode($days_header_in_month['headers']);
		$data['cell_background'] = json_encode($cell_background_data);

		$this->load->view('timesheet_leaves/timesheet_leave_manage', $data);
	}

	/**
	 * add timesheets leave
	 */
	public function add_timesheets_leave() {
		if (!has_permission('hrp_attendance', '', 'create') && !has_permission('hrp_attendance', '', 'edit') && !is_admin()) {
			access_denied('hrp_attendance');
		}

		if ($this->input->post()) {
			$data = $this->input->post();
			if (isset($data)) {

				if ($data['hrp_attendance_rel_type'] == 'update') {
					$success = $this->hr_payroll_model->add_update_attendance_timesheets_leave($data);
				} elseif ($data['hrp_attendance_rel_type'] == 'synchronization') {
					$success = $this->hr_payroll_model->synchronization_attendance($data);
				} else {
					$success = false;
				}

				if ($success) {
					set_alert('success', _l('hrp_updated_successfully'));
				}
				if(isset($data['attendance_fill_month'])){
					redirect(admin_url('hr_payroll/manage_attendance_timesheet_leaves/'.$data['attendance_fill_month']));
				}else{
					redirect(admin_url('hr_payroll/manage_attendance_timesheet_leaves'));
				}
			}

		}
	}

	/**
	 * timesheets leave filter
	 * @return [type] 
	 */
	public function timesheets_leave_filter() {
		$this->load->model('departments_model');
		$data = $this->input->post();

		$rel_type = hrp_get_timesheets_status();

		$months_filter = $data['month'];

		$querystring = ' active=1';
		$department = $data['department'];

		$staff = '';
		if (isset($data['staff'])) {
			$staff = $data['staff'];
		}
		$staff_querystring = '';
		$department_querystring = '';
		$role_querystring = '';

		if ($department != '') {
			$arrdepartment = $this->staff_model->get('', 'staffid in (select tblstaff_departments.staffid from tblstaff_departments where departmentid = ' . $department . ')');
			$temp = '';
			foreach ($arrdepartment as $value) {
				$temp = $temp . $value['staffid'] . ',';
			}
			$temp = rtrim($temp, ",");
			$department_querystring = 'FIND_IN_SET(staffid, "' . $temp . '")';
		}

		if ($staff != '') {
			$temp = '';
			$araylengh = count($staff);
			for ($i = 0; $i < $araylengh; $i++) {
				$temp = $temp . $staff[$i];
				if ($i != $araylengh - 1) {
					$temp = $temp . ',';
				}
			}
			$staff_querystring = 'FIND_IN_SET(staffid, "' . $temp . '")';
		}

		if (isset($data['role_attendance'])) {
			$temp = '';
			$araylengh = count($data['role_attendance']);
			for ($i = 0; $i < $araylengh; $i++) {
				$temp = $temp . $data['role_attendance'][$i];
				if ($i != $araylengh - 1) {
					$temp = $temp . ',';
				}
			}
			$role_querystring = 'FIND_IN_SET(role, "' . $temp . '")';
		}

		$arrQuery = array($staff_querystring, $department_querystring, $querystring, $role_querystring);

		$newquerystring = '';
		foreach ($arrQuery as $string) {
			if ($string != '') {
				$newquerystring = $newquerystring . $string . ' AND ';
			}
		}

		$newquerystring = rtrim($newquerystring, "AND ");
		if ($newquerystring == '') {
			$newquerystring = [];
		}

		$hrp_timesheet_leave_data_sample = hrp_timesheet_leave_data_sample();
		$month_filter = date('Y-m-d', strtotime($data['month'] . '-01'));
		//get day header in month
		$days_header_in_month = $this->hr_payroll_model->get_day_header_in_month($month_filter, $rel_type, false);

		$attendances = $this->hr_payroll_model->get_hrp_attendance_timesheet_leave($month_filter);
		$attendances_value = [];
		$cell_background = [];
		$cell_background_data = [];

		foreach ($attendances as $key => $value) {
			$dt_cell_bg = [];
			foreach ($value as $hearder_key => $cell_value) {

				if($hearder_key != 'id' && $hearder_key != 'staff_id' && $hearder_key != 'month' && $hearder_key != 'paid_leave' && $hearder_key != 'unpaid_leave' && $hearder_key != 'rel_type'){
					if(strlen($cell_value) > 0){
						if(preg_match('/^PL:/', $cell_value) && strlen($cell_value) < 7){
							$dt_cell_bg[$hearder_key] = '#0c0';

						}elseif(preg_match('/^UPL:/', $cell_value) && strlen($cell_value) < 7){
							$dt_cell_bg[$hearder_key] = '#c00';

						}elseif(preg_match('/PL:/', $cell_value) && preg_match('/UPL:/', $cell_value)){
							$dt_cell_bg[$hearder_key] = '#FF9800';
						}else{
							$dt_cell_bg[$hearder_key] = '#fff';
						}
					}else{
						$dt_cell_bg[$hearder_key] = '#fff';
					}
				}else{
					$dt_cell_bg[$hearder_key] = '#fff';
				}

			}
			$cell_background[$value['staff_id'] . '_' . $value['month']] = $dt_cell_bg;
			$attendances_value[$value['staff_id'] . '_' . $value['month']] = $value;
		}

		// data return
		$data_object_kpi = [];
		$index_data_object = 0;
		if ($newquerystring != '') {

			//load staff
			if (!is_admin() && !has_permission('hrp_employee', '', 'view')) {
				//View own
				$staffs = $this->hr_payroll_model->get_staff_timekeeping_applicable_object(get_staffid_by_permission($newquerystring));
			} else {
				//admin or view global
				$staffs = $this->hr_payroll_model->get_staff_timekeeping_applicable_object($newquerystring);
			}

			foreach ($staffs as $staff_key => $staff_value) {

				/*check value from database*/
				$data_object_kpi[$staff_key]['staff_id'] = $staff_value['staffid'];

				$staff_i = $this->hr_payroll_model->get_staff_info($staff_value['staffid']);
				if ($staff_i) {

					if (isset($staff_i->staff_identifi)) {
						$data_object_kpi[$staff_key]['hr_code'] = $staff_i->staff_identifi;
					} else {
						$data_object_kpi[$staff_key]['hr_code'] = $this->hr_payroll_model->hrp_format_code('EXS', $staff_i->staffid, 5);
					}

					$data_object_kpi[$staff_key]['staff_name'] = $staff_i->firstname . ' ' . $staff_i->lastname;

					$arr_department = $this->hr_payroll_model->get_staff_departments($staff_i->staffid, true);

					$list_department = '';
					if (count($arr_department) > 0) {

						foreach ($arr_department as $key => $department) {
							$department_value = $this->departments_model->get($department);

							if ($department_value) {
								if (new_strlen($list_department) != 0) {
									$list_department .= ', ' . $department_value->name;
								} else {
									$list_department .= $department_value->name;
								}
							}

						}
					}

					$data_object_kpi[$staff_key]['staff_departments'] = $list_department;

				} else {
					$data_object_kpi[$staff_key]['hr_code'] = '';
					$data_object_kpi[$staff_key]['staff_name'] = '';
					$data_object_kpi[$staff_key]['staff_departments'] = '';

				}

				if (isset($attendances_value[$staff_value['staffid'] . '_' . $month_filter])) {

					
					$data_object_kpi[$staff_key]['paid_leave'] = $attendances_value[$staff_value['staffid'] . '_' . $month_filter]['paid_leave'];
					$data_object_kpi[$staff_key]['unpaid_leave'] = $attendances_value[$staff_value['staffid'] . '_' . $month_filter]['unpaid_leave'];
					$data_object_kpi[$staff_key]['id'] = $attendances_value[$staff_value['staffid'] . '_' . $month_filter]['id'];
					$data_object_kpi[$staff_key] = array_merge($data_object_kpi[$staff_key], $attendances_value[$staff_value['staffid'] . '_' . $month_filter]);
					$cell_background_data[] = $cell_background[$staff_value['staffid'] . '_' . $month_filter];


				} else {
					
					$data_object_kpi[$staff_key]['paid_leave'] = 0;
					$data_object_kpi[$staff_key]['unpaid_leave'] = 0;
					$data_object_kpi[$staff_key]['id'] = 0;
					$data_object_kpi[$staff_key] = array_merge($data_object_kpi[$staff_key], $days_header_in_month['days_header']);
					$cell_background_data[] = $hrp_timesheet_leave_data_sample;

				}

				$data_object_kpi[$staff_key]['rel_type'] = $rel_type;
				$data_object_kpi[$staff_key]['month'] = $month_filter;

			}

		}

		//check is add new or update data
		if (count($attendances_value) > 0) {
			$button_name = _l('hrp_update');
		} else {
			$button_name = _l('submit');
		}

		echo json_encode([
			'data_object' => $data_object_kpi,
			'columns' => $days_header_in_month['columns_type'],
			'col_header' => $days_header_in_month['headers'],
			'button_name' => $button_name,
			'cell_background' => $cell_background_data

		]);
		die;
	}

	/**
	 * timesheet leave calculation
	 * @return [type] 
	 */
	public function timesheet_leave_calculation()
	{
		if (!has_permission('hrp_employee', '', 'edit') && !is_admin()) {
			access_denied('hrp_employee');
		}

		$data = $this->input->post();
		$this->hr_payroll_model->timesheet_leave_calculation($data);
		$message = _l('updated_successfully');
		echo json_encode([
			'message' => $message,
		]);
	}

	/**
	 * payslip pdf template
	 * @param  string $id 
	 * @return [type]     
	 */
	public function payslip_pdf_template($id = '') {

		if ($this->input->post()) {
			$message = '';
			$data = $this->input->post();

			$data['content'] = $this->input->post('mce_0', false);

			if (isset($data['mce_0'])) {
				unset($data['mce_0']);

			}

			if ($id == '') {
				$id = $this->hr_payroll_model->add_pdf_payslip_template($data);

				if ($id) {
					$message = _l('added_successfully', _l('pdf_payslip_template'));
					set_alert('success', $message);
				} else {
					$message = _l('added_failed', _l('pdf_payslip_template'));
					set_alert('warning', $message);
				}

				redirect(admin_url('hr_payroll/payslip_pdf_template/'.$id));
			} else {

				$success = $this->hr_payroll_model->update_pdf_payslip_template($data, $id);

				if ($success) {
					$message = _l('updated_successfully', _l('pdf_payslip_template'));
					set_alert('success', $message);

				}

				redirect(admin_url('hr_payroll/payslip_pdf_template/'.$id));
			}

		}
		$data = [];

		if ($id == '') {
			//add
			$title = _l('add_pdf_payslip_template');
			$data['title'] = $title;

		} else {
			//update
			$title = _l('update_pdf_payslip_template');
			$data['title'] = $title;
			$data['pdf_payslip_template'] = $this->hr_payroll_model->get_pdf_payslip_template($id);
		}

		$data['payslip_templates'] = $this->hr_payroll_model->get_hrp_payslip_templates();
		$data['pdf_payslip_merge_fields'] = $this->app_merge_fields->get_flat('hr_payslip', ['other'], '{email_signature}');

		$this->load->view('includes/pdf_payslip_template_detail', $data);

	}

	/**
	 * delete payslip pdf template
	 * @param  [type] $id
	 * @return [type]    
	 */
	public function delete_payslip_pdf_template_($id) {
		if (!$id) {
			redirect(admin_url('hr_payroll/setting?group=pdf_payslip_template'));
		}
		$response = $this->hr_payroll_model->delete_pdf_payslip_template($id);
		if (is_array($response) && isset($response['referenced'])) {
			set_alert('warning', _l('hr_is_referenced', _l('pdf_payslip_template')));
		} elseif ($response == true) {
			set_alert('success', _l('deleted', _l('pdf_payslip_template')));
		} else {
			set_alert('warning', _l('problem_deleting', _l('pdf_payslip_template')));
		}
		redirect(admin_url('hr_payroll/setting?group=pdf_payslip_template'));
	}

	/**
	 * save pdf payslip data
	 * @return [type] 
	 */
	public function save_pdf_payslip_data()
	{
		if (!has_permission('hrp_setting', '', 'edit')) {
			header('HTTP/1.0 400 Bad error');
			echo json_encode([
				'success' => false,
				'message' => _l('access_denied'),
			]);
			die;
		}

		$success = false;
		$message = '';

		$this->db->where('id_contract', $this->input->post('contract_id'));
		$this->db->update(db_prefix() . 'hr_staff_contract', [
			'content' => html_purify($this->input->post('content', false)),
		]);

		$success = $this->db->affected_rows() > 0;
		$message = _l('updated_successfully', _l('contract'));

		echo json_encode([
			'success' => $success,
			'message' => $message,
		]);
	}

	/**
	 * get pdf payslip template
	 * @param  string $pdf_payslip_template_id 
	 * @return [type]                          
	 */
	public function get_pdf_payslip_template($id = '', $payslip_template_id = '') {
		
		$base_currency_id = 0;
		$payslip_template_data = '';
		$payslip_name = '';
		$from_currency_id = 0;
		$from_currency_name = '';
		$from_currency_rate = '';
		$to_currency_id = 0;
		$to_currency_name = '';
		$to_currency_rate = '';

		$base_currency = get_base_currency();
        $base_currency_id = 0;
        if ($base_currency) {
        	$base_currency_id = $base_currency->id;
        }

		if (isset($id) && $id != '' && $id != 0) {

			// update
			$get_hrp_payslip = $this->hr_payroll_model->get_hrp_payslip($id);
			$pdf_payslip_template_id = '';
			if($get_hrp_payslip){
				$pdf_payslip_template_id = $get_hrp_payslip->pdf_template_id;
				$payslip_name = $get_hrp_payslip->payslip_name;
				$from_currency_id = $get_hrp_payslip->from_currency_id;
				$from_currency_name = $get_hrp_payslip->from_currency_name;
				$from_currency_rate = $get_hrp_payslip->from_currency_rate;
				$to_currency_id = $get_hrp_payslip->to_currency_id;
				$to_currency_name = $get_hrp_payslip->to_currency_name;
				$to_currency_rate = $get_hrp_payslip->to_currency_rate;

			}

			$pdf_payslip_template_selected = $this->hr_payroll_model->get_pdf_payslip_template_selected_html($payslip_template_id, $pdf_payslip_template_id);

		} else {
			// create
			$pdf_payslip_template_selected = $this->hr_payroll_model->get_pdf_payslip_template_selected_html($payslip_template_id, '');
		}

		echo json_encode([
			'pdf_payslip_template_selected' => $pdf_payslip_template_selected,
			'payslip_name' => $payslip_name,
			'from_currency_id' => $from_currency_id,
			'from_currency_name' => $from_currency_name,
			'from_currency_rate' => $from_currency_rate,
			'to_currency_id' => $to_currency_id,
			'to_currency_name' => $to_currency_name,
			'to_currency_rate' => $to_currency_rate,
			'base_currency_id' => $base_currency_id,
		]);
		die;
	}

	/**
	 * edit payslip
	 * @return [type] 
	 */
	public function edit_payslip() {
		if ($this->input->post()) {
			$data = $this->input->post();
			if (isset($data['id'])) {

				if (!is_admin() && !has_permission('hrp_payslip', '', 'edit')) {
					access_denied('hrp_payslip');
				}
				$update_data = [];

				$payslip = $this->hr_payroll_model->get_hrp_payslip($data['id']);
				$old_currency_id = $payslip->to_currency_id;
				if($old_currency_id != $data['to_currency_id']){
					$get_currency_rate = $this->hr_payroll_model->get_currency_rate_infor($data['to_currency_id']);
					$update_data['from_currency_name'] = $get_currency_rate['base_currency_name'];
					$update_data['from_currency_rate'] = $get_currency_rate['base_currency_rate'];
					$update_data['to_currency_name'] = $get_currency_rate['to_currency_name'];
					$update_data['to_currency_rate'] = $get_currency_rate['currency_rate'];
					$update_data['to_currency_id'] = $data['to_currency_id'];
					$update_data['from_currency_id'] = $data['from_currency_id'];
				}

				$update_data['payslip_name'] = $data['payslip_name'];
				$update_data['pdf_template_id'] = $data['pdf_template_id'];

				$this->db->where('id', $data['id']);
				$this->db->update(db_prefix().'hrp_payslips', $update_data);
				if($this->db->affected_rows() > 0){
					$message = _l('added_successfully', _l('hrp_payslip'));
					set_alert('success', $message);
				}
				redirect(admin_url('hr_payroll/payslip_manage'));

			}
		}
	}

	/**
	 * new employee export pdf
	 * @param  [type] $id 
	 * @return [type]     
	 */
	public function new_employee_export_pdf($id) {

		if (!$id) {
			show_404();
		}

		$payslip = $this->hr_payroll_model->hr_payroll_get_payslip_pdf_only_for_pdf($id);

		try {
			$pdf = hr_payroll_payslip_pdf($payslip);
		} catch (Exception $e) {
			echo $e->getMessage();
			die;
		}

		$type = 'D';
		if ($this->input->get('output_type')) {
			$type = $this->input->get('output_type');
		}

		if ($this->input->get('print')) {
			$type = 'I';
		}
		$pdf->Output(slug_it($payslip->payslip_number ?? '') . '.pdf', $type);
	}

	/**
     * currency rate table
     * @return [type] 
     */
    public function currency_rate_table(){
        $this->app->get_table_data(module_views_path('hr_payroll', 'includes/currencies/currency_rate_table'));
    }

    /**
     * update automatic conversion
     */
    public function update_setting_currency_rate(){
        $data = $this->input->post();
        $success = $this->hr_payroll_model->update_setting_currency_rate($data);
        if($success == true){
            $message = _l('updated_successfully', _l('setting'));
            set_alert('success', $message);
        }
        redirect(admin_url('hr_payroll/setting?group=currency_rates'));
    }

    /**
     * Gets all currency rate online.
     */
    public function get_all_currency_rate_online()
    {
        $result = $this->hr_payroll_model->get_all_currency_rate_online();
        if($result){
            set_alert('success', _l('updated_successfully', _l('hrp_currency_rates')));
        }
        else{
            set_alert('warning', _l('no_data_changes', _l('hrp_currency_rates')));                  
        }

        redirect(admin_url('hr_payroll/setting?group=currency_rates'));
    }

    /**
     * update currency rate
     * @return [type] 
     */
    public function update_currency_rate($id)
    {
        if($this->input->post()){
            $data = $this->input->post();

            $result =  $this->hr_payroll_model->update_currency_rate($data, $id);
            if($result){
                set_alert('success', _l('updated_successfully', _l('hrp_currency_rates')));
            }
            else{
                set_alert('warning', _l('no_data_changes', _l('hrp_currency_rates')));                  
            }
        }

        redirect(admin_url('hr_payroll/setting?group=currency_rates'));
    }

    /**
     * Gets the currency rate online.
     *
     * @param        $id     The identifier
     */
    public function get_currency_rate_online($id)
    {
            $result =  $this->hr_payroll_model->get_currency_rate_online($id);
            echo json_encode(['value' => $result]);
            die;
    }


    /**
     * delete currency
     * @param  [type] $id 
     * @return [type]     
     */
    public function delete_currency_rate($id){
        if($id != ''){
            $result =  $this->hr_payroll_model->delete_currency_rate($id);
            if($result){
                set_alert('success', _l('deleted_successfully', _l('hrp_currency_rates')));
            }
            else{
                set_alert('danger', _l('deleted_failure', _l('hrp_currency_rates')));                   
            }
        }
        redirect(admin_url('hr_payroll/setting?group=currency_rates'));
    }

    /**
     * currency rate modal
     * @return [type] 
     */
    public function currency_rate_modal()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $id=$this->input->post('id');

        $data=[];
        $data['currency_rate'] = $this->hr_payroll_model->get_currency_rate($id);

        $this->load->view('includes/currencies/currency_rate_modal', $data);
    }

    /**
     * currency rate table
     * @return [type] 
     */
    public function currency_rate_logs_table(){
        $this->app->get_table_data(module_views_path('hr_payroll', 'includes/currencies/currency_rate_logs_table'));
    }

    /**
     * get currency rate
     * @param  [type] $currency_id 
     * @return [type]              
     */
	public function get_currency_rate($currency_id){
        $get_currency_rate = $this->hr_payroll_model->get_currency_rate_infor($currency_id);

        $currency_rate = $get_currency_rate['currency_rate'];
        $convert_str = $get_currency_rate['convert_str'];
        $currency_name = $get_currency_rate['currency_name'];

        echo json_encode([
            'currency_rate' => hrp_app_format_number($currency_rate),
            'convert_str' => $convert_str,
            'currency_name' => $currency_name,
        ]);

    }


//End file
}