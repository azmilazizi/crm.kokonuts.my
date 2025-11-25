// Ensure accounting upload constants are available even if the module bootstrap
// file wasn't loaded for the current request (e.g. API-only contexts).
if (!defined('ACCOUNTING_MODULE_NAME')) {
    define('ACCOUNTING_MODULE_NAME', 'accounting');
}

if (!defined('ACCOUTING_MODULE_UPLOAD_FOLDER')) {
    define('ACCOUTING_MODULE_UPLOAD_FOLDER', module_dir_path(ACCOUNTING_MODULE_NAME, 'uploads'));
}

