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

// Stub WordPress classes if they don't exist
if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request {
        protected $method;
        protected $route;
        protected $params = [];
        public function __construct($method = '', $route = '') {
            $this->method = $method;
            $this->route = $route;
        }
        public function get_param($key) { return $this->params[$key] ?? null; }
        public function set_param($key, $value) { $this->params[$key] = $value; }
    }
}

if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response {
        protected $data;
        protected $status;
        public function __construct($data = null, $status = 200) {
            $this->data = $data;
            $this->status = $status;
        }
        public function get_data() { return $this->data; }
        public function get_status() { return $this->status; }
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error {
        protected $code;
        protected $message;
        protected $data;
        public function __construct($code = '', $message = '', $data = '') {
            $this->code = $code;
            $this->message = $message;
            $this->data = $data;
        }
        public function get_error_code() { return $this->code; }
    }
}

if (!class_exists('WP_REST_Server')) {
    class WP_REST_Server {
        const READABLE = 'GET';
        const CREATABLE = 'POST';
    }
}
