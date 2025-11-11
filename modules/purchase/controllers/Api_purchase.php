<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . 'core/API_Controller.php';

class Api_purchase extends API_Controller
{
    public function __construct()
    {
        $this->module_language_file      = 'purchase';
        $this->module_language_directory = __DIR__ . '/../';

        parent::__construct();

        $this->load->model('purchase/purchase_model');
    }

    public function vendors_get()
    {
        if (!$this->authenticate_token()) {
            return;
        }

        $filters = [];

        $activeParam = $this->input->get('active');
        if ($activeParam !== null && $activeParam !== '') {
            $activeFilter = filter_var($activeParam, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($activeFilter === null) {
                $this->response([
                    'status'  => false,
                    'message' => 'Invalid active flag provided. Expected a boolean value.',
                ], self::HTTP_BAD_REQUEST);

                return;
            }

            $filters[db_prefix() . 'pur_vendor.active'] = $activeFilter ? 1 : 0;
        }

        $countryParam = $this->input->get('country_id');
        if ($countryParam !== null && $countryParam !== '') {
            if (!ctype_digit((string) $countryParam)) {
                $this->response([
                    'status'  => false,
                    'message' => 'Invalid country identifier provided.',
                ], self::HTTP_BAD_REQUEST);

                return;
            }

            $filters[db_prefix() . 'pur_vendor.country'] = (int) $countryParam;
        }

        $vendors = $this->purchase_model->get_vendor('', $filters);

        $searchTerm = trim((string) $this->input->get('search'));
        if ($searchTerm !== '') {
            $vendors = $this->filter_vendors_by_term($vendors, $searchTerm);
        }

        $this->response([
            'status' => true,
            'result' => array_values(array_map([$this, 'format_vendor_summary'], $vendors)),
        ], self::HTTP_OK);
    }

    public function vendor_get($id = null)
    {
        if (!$this->authenticate_token()) {
            return;
        }

        if (!is_numeric($id)) {
            $this->response([
                'status'  => false,
                'message' => 'Invalid vendor identifier provided.',
            ], self::HTTP_BAD_REQUEST);

            return;
        }

        $vendor = $this->purchase_model->get_vendor((int) $id);

        if (!$vendor) {
            $this->response([
                'status'  => false,
                'message' => 'Vendor not found.',
            ], self::HTTP_NOT_FOUND);

            return;
        }

        $vendorData = $this->format_vendor_detail($vendor);

        $includeContactsParam = $this->input->get('include_contacts');
        if ($includeContactsParam !== null && $includeContactsParam !== '') {
            $includeContacts = filter_var($includeContactsParam, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($includeContacts === null) {
                $this->response([
                    'status'  => false,
                    'message' => 'Invalid include_contacts flag provided. Expected a boolean value.',
                ], self::HTTP_BAD_REQUEST);

                return;
            }

            if ($includeContacts) {
                $vendorData['contacts'] = $this->purchase_model->get_contacts((int) $id);
            }
        }

        $this->response([
            'status' => true,
            'result' => $vendorData,
        ], self::HTTP_OK);
    }

    private function filter_vendors_by_term(array $vendors, string $term): array
    {
        $term = $this->toLower($term);

        return array_values(array_filter($vendors, function ($vendor) use ($term) {
            $fieldsToSearch = [
                'company',
                'phonenumber',
                'vat',
                'email',
                'vendor_code',
            ];

            foreach ($fieldsToSearch as $field) {
                if (!isset($vendor[$field]) || $vendor[$field] === '') {
                    continue;
                }

                $value = $this->toLower((string) $vendor[$field]);

                if ($this->containsTerm($value, $term)) {
                    return true;
                }
            }

            return false;
        }));
    }

    private function toLower(string $value): string
    {
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($value);
        }

        return strtolower($value);
    }

    private function containsTerm(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }

        if (function_exists('mb_strpos')) {
            return mb_strpos($haystack, $needle) !== false;
        }

        if (function_exists('mb_stripos')) {
            return mb_stripos($haystack, $needle) !== false;
        }

        return strpos($haystack, $needle) !== false;
    }

    private function format_vendor_summary(array $vendor): array
    {
        return [
            'id'         => isset($vendor['userid']) ? (int) $vendor['userid'] : null,
            'company'    => $vendor['company'] ?? '',
            'email'      => $vendor['email'] ?? '',
            'phonenumber'=> $vendor['phonenumber'] ?? '',
            'vat'        => $vendor['vat'] ?? null,
            'city'       => $vendor['city'] ?? '',
            'state'      => $vendor['state'] ?? '',
            'country_id' => isset($vendor['country']) ? (int) $vendor['country'] : null,
            'active'     => isset($vendor['active']) ? (bool) $vendor['active'] : null,
        ];
    }

    private function format_vendor_detail($vendor): array
    {
        if (is_object($vendor)) {
            $vendor = (array) $vendor;
        }

        $detail = $this->format_vendor_summary($vendor);

        $detail['address']        = $vendor['address'] ?? '';
        $detail['zip']            = $vendor['zip'] ?? '';
        $detail['website']        = $vendor['website'] ?? '';
        $detail['bank']           = $vendor['bank'] ?? '';
        $detail['payment_terms']  = $vendor['payment_terms'] ?? '';
        $detail['balance']        = isset($vendor['balance']) ? (float) $vendor['balance'] : null;
        $detail['balance_as_of']  = $vendor['balance_as_of'] ?? null;
        $detail['datecreated']    = $vendor['datecreated'] ?? null;
        $detail['default_currency']= isset($vendor['default_currency']) ? (int) $vendor['default_currency'] : null;

        if (isset($vendor['company'])) {
            $detail['display_name'] = $vendor['company'];
        } elseif (isset($vendor['firstname']) || isset($vendor['lastname'])) {
            $detail['display_name'] = trim(($vendor['firstname'] ?? '') . ' ' . ($vendor['lastname'] ?? ''));
        } else {
            $detail['display_name'] = '';
        }

        return $detail;
    }
}
