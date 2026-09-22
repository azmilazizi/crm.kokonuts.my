<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_103 extends App_module_migration
{
    // Malaysian company identifiers the client (customers) profile form is
    // currently missing — "VAT Number" isn't even the right concept here
    // (Malaysia has no VAT, it has SST), and TIN capture on B2B clients is
    // increasingly required for LHDN's e-Invoice (MyInvois) submissions.
    // Added as ordinary Custom Fields (Setup -> Custom Fields -> Customers)
    // rather than real columns — no schema change needed, and staff can
    // freely re-edit/reorder/remove them from that screen afterward.
    private $fields = [
        ['name' => 'TIN (Tax Identification No.)', 'field_order' => 1],
        ['name' => 'SSM Registration No.',          'field_order' => 2],
        ['name' => 'SST Registration No.',          'field_order' => 3],
    ];

    public function up()
    {
        $CI = &get_instance();

        // Hides the "VAT Number" field from the Add/Edit Customer form —
        // it's the wrong concept for Malaysia anyway (no VAT here, SST
        // instead), replaced by the custom fields added below.
        update_option('company_requires_vat_number_field', 0);

        $CI->load->model('custom_fields_model');

        foreach ($this->fields as $field) {
            $exists = $CI->db->where('fieldto', 'customers')
                ->where('name', $field['name'])
                ->count_all_results(db_prefix() . 'customfields');
            if ($exists > 0) {
                continue;
            }

            $CI->custom_fields_model->add([
                'name'        => $field['name'],
                'type'        => 'input',
                'fieldto'     => 'customers',
                'field_order' => $field['field_order'],
            ]);
        }
    }

    public function down()
    {
        $CI = &get_instance();
        $names = array_column($this->fields, 'name');
        $CI->db->where('fieldto', 'customers')
            ->where_in('name', $names)
            ->delete(db_prefix() . 'customfields');

        update_option('company_requires_vat_number_field', 1);
    }
}
