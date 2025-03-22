<?php

defined('BASEPATH') or exit('No direct script access allowed');

include_once APPPATH . 'libraries/pdf/App_pdf.php';

/**
 *  Export_employee pdf
 */
class Export_employee_pdf extends App_pdf {
	protected $export_employee;

	/**
	 * construct
	 * @param object
	 */
	public function __construct($export_employee) {

		$export_employee = hooks()->apply_filters('request_html_pdf_data', $export_employee);
		$GLOBALS['export_employee_pdf'] = $export_employee;

		parent::__construct();

		$this->export_employee = $export_employee;

		$this->SetTitle('export_employee');

		# Don't remove these lines - important for the PDF layout
		$this->export_employee = $this->fix_editor_html($this->export_employee);
	}

	/**
	 * prepare
	 * @return
	 */
	public function prepare() {
		$this->set_view_vars('export_employee', $this->export_employee);

		return $this->build();
	}

	/**
	 * type
	 * @return
	 */
	protected function type() {
		return 'export_employee';
	}

	/**
	 * file path
	 * @return
	 */
	protected function file_path() {
		$customPath = APPPATH . 'views/themes/' . active_clients_theme() . '/views/my_requestpdf.php';
		$actualPath = APP_MODULES_PATH . '/hr_payroll/views/employee_payslip/export_employee_pdf.php';

		if (file_exists($customPath)) {
			$actualPath = $customPath;
		}

		return $actualPath;
	}
}