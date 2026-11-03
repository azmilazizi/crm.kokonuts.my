<?php defined('BASEPATH') or exit('No direct script access allowed');

/** Empty is an unconfigured branch; false is an invalid review URL. */
function kokonuts_google_review_url($value)
{
    if (!is_string($value)) return false;
    $url = trim($value);
    if ($url === '') return '';
    if (strlen($url) > 2048 || preg_match('/[\x00-\x20\x7f]/', $url)) return false;
    $parts = parse_url($url);
    if (!$parts || strtolower($parts['scheme'] ?? '') !== 'https'
        || isset($parts['user']) || isset($parts['pass'])
        || (isset($parts['port']) && $parts['port'] !== 443)) return false;
    $hosts = ['g.page', 'maps.app.goo.gl', 'search.google.com', 'maps.google.com', 'www.google.com', 'google.com'];
    return in_array(strtolower($parts['host'] ?? ''), $hosts, true) ? $url : false;
}
