<?php

$autoload = __DIR__ . '/../application/vendor/autoload.php';

if (file_exists($autoload)) {
    require_once $autoload;
}

if (!defined('FCPATH')) {
    define('FCPATH', __DIR__ . '/../');
}

if (!defined('APPPATH')) {
    define('APPPATH', __DIR__ . '/../application/');
}

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__ . '/../system/');
}

if (file_exists(APPPATH . 'config/constants.php')) {
    require_once APPPATH . 'config/constants.php';
}
