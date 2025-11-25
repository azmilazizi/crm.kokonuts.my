
        // Ensure accounting upload constants are available even if the module bootstrap
        // file wasn't loaded for the current request (e.g. API-only contexts).
        if (!defined('ACCOUNTING_MODULE_NAME')) {
            define('ACCOUNTING_MODULE_NAME', 'accounting');
        }

        if (!defined('ACCOUTING_MODULE_UPLOAD_FOLDER')) {
            if (!function_exists('module_dir_path')) {
                $this->load->helper('modules');
            }

            $uploadPath = null;

            if (function_exists('module_dir_path')) {
                $uploadPath = module_dir_path(ACCOUNTING_MODULE_NAME, 'uploads');
            } elseif (defined('APP_MODULES_PATH')) {
                $uploadPath = rtrim(APP_MODULES_PATH, '/\\') . '/' . ACCOUNTING_MODULE_NAME . '/uploads/';
            } else {
                $uploadPath = FCPATH . 'modules/' . ACCOUNTING_MODULE_NAME . '/uploads/';
            }

            define('ACCOUTING_MODULE_UPLOAD_FOLDER', rtrim($uploadPath, '/'));
        }
