<?php

defined('BASEPATH') or exit('No direct script access allowed');

if (!function_exists('app_env')) {
    /**
     * Retrieve environment variables from multiple sources with a fallback.
     */
    function app_env($key, $default = null)
    {
        $value = getenv($key);

        if ($value === false) {
            $value = $_ENV[$key] ?? $_SERVER[$key] ?? null;
        }

        if ($value === null || $value === false || $value === '') {
            return $default;
        }

        return $value;
    }
}
/*
* --------------------------------------------------------------------------
* Base Site URL
* --------------------------------------------------------------------------
*
* URL to your CodeIgniter root. Typically this will be your base URL,
* WITH a trailing slash:
*
*   http://example.com/
*
* If this is not set then CodeIgniter will try guess the protocol, domain
* and path to your installation. However, you should always configure this
* explicitly and never rely on auto-guessing, especially in production
* environments.
*
*/
define('APP_BASE_URL', app_env('APP_BASE_URL', 'http://localhost'));

/*
* --------------------------------------------------------------------------
* Encryption Key
* IMPORTANT: Do not change this ever!
* --------------------------------------------------------------------------
*
* If you use the Encryption class, you must set an encryption key.
* See the user guide for more info.
*
* http://codeigniter.com/user_guide/libraries/encryption.html
*
* Auto added on install
*/
define('APP_ENC_KEY', app_env('APP_ENC_KEY', '5717581a0dc793fc77e7c8d3f24fa899'));

/**
 * Database Credentials
 * The hostname of your database server
 */
define('APP_DB_HOSTNAME', app_env('DB_HOST', 'localhost'));

/**
 * The username used to connect to the database
 */
define('APP_DB_USERNAME', app_env('DB_USERNAME', 'kokonuts_crmuser'));

/**
 * The password used to connect to the database
 */
define('APP_DB_PASSWORD', app_env('DB_PASSWORD', '@Zmil001'));

/**
 * The name of the database you want to connect to
 */
define('APP_DB_NAME', app_env('DB_DATABASE', 'kokonuts_crm'));

/**
 * @since  2.3.0
 * Database charset
 */
define('APP_DB_CHARSET', app_env('DB_CHARSET', 'utf8mb4'));

/**
 * @since  2.3.0
 * Database collation
 */
define('APP_DB_COLLATION', app_env('DB_COLLATION', 'utf8mb4_unicode_ci'));

/**
 *
 * Session handler driver
 * By default the database driver will be used.
 *
 * For files session use this config:
 * define('SESS_DRIVER', 'files');
 * define('SESS_SAVE_PATH', NULL);
 * In case you are having problem with the SESS_SAVE_PATH consult with your hosting provider to set "session.save_path" value to php.ini
 *
 */
define('SESS_DRIVER', 'database');
define('SESS_SAVE_PATH', 'sessions');
define('APP_SESSION_COOKIE_SAME_SITE', 'Lax');

/**
 * Enables CSRF Protection
 */
define('APP_CSRF_PROTECTION', false);
