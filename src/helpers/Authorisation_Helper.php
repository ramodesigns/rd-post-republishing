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
     * Check if debug mode is authorized based on debug_timestamp
     *
     * @return bool True if authorized, false otherwise
     */
    public function is_debug_authorized()
    {
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
}
