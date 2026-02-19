<?php

if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/fake-wp/');
}

if (!function_exists('__')) {
    function __($text, $domain = 'default')
    {
        return $text;
    }
}

if (!function_exists('add_filter')) {
    function add_filter($tag, $function_to_add, $priority = 10, $accepted_args = 1)
    {
        // no-op stub
    }
}

require_once __DIR__ . '/../forbid-gps.php';
