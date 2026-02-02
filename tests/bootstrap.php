<?php
/**
 * Bootstrap file for PHPUnit tests
 */

// Define ABSPATH to prevent classes from exiting
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

// Define other common WordPress constants if needed
if (!defined('WPINC')) {
    define('WPINC', 'wp-includes');
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return dirname($file) . '/';
    }
}

require_once __DIR__ . '/../vendor/autoload.php';
