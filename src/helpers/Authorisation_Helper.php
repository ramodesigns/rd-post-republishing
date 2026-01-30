<?php
/**
 * Helper class for authorization checks
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Authorisation_Helper
{
    /**
     * Preferences service instance
     *
     * @var Preferences_Service
     */
    private $preferences_service;

    /**
     * Constructor
     *
     * @param Preferences_Service $preferences_service
     */
    public function __construct($preferences_service)
    {
        $this->preferences_service = $preferences_service;
    }

    /**
     * Check if authorized based on debug_timestamp or secret token
     *
     * @return bool True if authorized, false otherwise
     */
    public function is_debug_authorized()
    {
        // 1. Check for Secret Token in Request
        $token_pref = $this->preferences_service->get_preference_by_key('cron_secret_token');
        if ($token_pref && !empty($token_pref['value'])) {
            $provided_token = null;

            // Check query parameter
            if (isset($_GET['token'])) {
                $provided_token = $_GET['token'];
            }
            // Check Authorization header (Bearer token)
            elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
                if (preg_match('/Bearer\s+(.*)$/i', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
                    $provided_token = $matches[1];
                }
            }

            if ($provided_token !== null && hash_equals($token_pref['value'], $provided_token)) {
                return true;
            }
        }

        // 2. Check for Debug Timestamp
        $debug_pref = $this->preferences_service->get_preference_by_key('debug_timestamp');

        if (!$debug_pref || empty($debug_pref['value'])) {
            return false;
        }

        $timestamp = $debug_pref['value'];

        if (!is_numeric($timestamp)) {
            return false;
        }

        $now = time();
        return (int)$timestamp > $now;
    }

    /**
     * Generate a unique random token
     *
     * @return string
     */
    public function generate_token()
    {
        if (function_exists('random_bytes')) {
            $random = bin2hex(random_bytes(32));
        } elseif (function_exists('openssl_random_pseudo_bytes')) {
            $random = bin2hex(openssl_random_pseudo_bytes(32));
        } else {
            $random = md5(uniqid(wp_generate_password(32, true, true), true));
        }
        $data = home_url() . microtime() . $random;
        return hash('sha256', $data);
    }
}
