<?php

defined('BASEPATH') or exit('No direct script access allowed');

include_once(APPPATH . 'libraries/pdf/App_pdf.php');

class Hr_payroll_payslip_pdf extends App_pdf
{
    protected $payslip;

    public function __construct($payslip)
    {
        // $payslip                = hooks()->apply_filters('payslip_html_pdf_data', $payslip);
        $GLOBALS['hr_payslip_pdf'] = $payslip;

        parent::__construct();

        $this->payslip = $payslip;
        // $this->load_language($this->payslip->vendor);
        $this->SetTitle($this->payslip->payslip_number ?? '');

        # Don't remove these lines - important for the PDF layout
        if(!is_null($this->payslip->content)){
            $this->payslip->content = $this->fix_editor_html($this->payslip->content ?? '');
        }else{
            $this->payslip->content = '';
        }

    }

    public function prepare()
    {
        $this->set_view_vars('hr_payslip', $this->payslip);

        return $this->build();
    }

    protected function type()
    {
        return 'hr_payslip';
    }

    protected function file_path()
    {
        $actualPath = APP_MODULES_PATH . '/hr_payroll/views/employee_payslip/new_export_employee_pdf.php';
        return $actualPath;
    }
}
