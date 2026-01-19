<?php
/**
 * Service class for managing the republish process
 *
 * Handles the execution of republishing operations
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Process_Service
{
    /**
     * Preferences service instance
     *
     * @var Preferences_Service
     */
    private $preferences_service;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->preferences_service = new Preferences_Service();
    }

    /**
     * Execute the republish process
     *
     * @return array Result of the process execution
     */
    public function execute_republish_process()
    {
        // Leave blank for now
    }

    /**
     * Validate prerequisites for the republish process
     *
     * @return array Result with 'success' (bool) and 'errors' (array of strings)
     */
    public function validate_prerequisites()
    {
        $errors = array();

        // Get all required preferences
        $status = $this->get_preference_value('status');
        $status_timestamp = $this->get_preference_value('status_timestamp');
        $posts_per_day = $this->get_preference_value('posts_per_day');
        $publish_start_time = $this->get_preference_value('publish_start_time');
        $publish_end_time = $this->get_preference_value('publish_end_time');

        // Validate 'status' preference
        if ($status === null) {
            $errors[] = "Preference 'status' is not set";
        } elseif ($status !== 'active') {
            $errors[] = "Preference 'status' must have a value of 'active'";
        }

        // Validate 'status_timestamp' preference
        if ($status_timestamp === null) {
            $errors[] = "Preference 'status_timestamp' is not set";
        } elseif (!$this->is_valid_past_epoch($status_timestamp)) {
            $errors[] = "Preference 'status_timestamp' must be a valid epoch time in the past";
        }

        // Validate 'posts_per_day' preference
        if ($posts_per_day === null) {
            $errors[] = "Preference 'posts_per_day' is not set";
        } elseif (!$this->is_positive_integer_string($posts_per_day)) {
            $errors[] = "Preference 'posts_per_day' must be a numerical value of 1 or more";
        }

        // Validate 'publish_start_time' preference
        if ($publish_start_time === null) {
            $errors[] = "Preference 'publish_start_time' is not set";
        } elseif (!$this->is_valid_start_time($publish_start_time)) {
            $errors[] = "Preference 'publish_start_time' must be a numerical value between 1 and 22";
        }

        // Validate 'publish_end_time' preference
        if ($publish_end_time === null) {
            $errors[] = "Preference 'publish_end_time' is not set";
        } elseif (!$this->is_valid_end_time($publish_end_time)) {
            $errors[] = "Preference 'publish_end_time' must be a numerical value between 2 and 23";
        }

        // Validate start time is before end time (only if both are valid)
        if ($publish_start_time !== null && $publish_end_time !== null
            && $this->is_valid_start_time($publish_start_time)
            && $this->is_valid_end_time($publish_end_time)) {
            if (intval($publish_start_time) >= intval($publish_end_time)) {
                $errors[] = "Preference 'publish_start_time' must have a lower value than 'publish_end_time'";
            }
        }

        return array(
            'success' => empty($errors),
            'errors' => $errors
        );
    }

    /**
     * Get a preference value by key
     *
     * @param string $key The preference key
     * @return string|null The preference value or null if not found
     */
    private function get_preference_value($key)
    {
        $preference = $this->preferences_service->get_preference_by_key($key);
        return $preference !== null ? $preference['value'] : null;
    }

    /**
     * Check if a value is a valid epoch time in the past
     *
     * @param string $value The value to check
     * @return bool True if valid past epoch time
     */
    private function is_valid_past_epoch($value)
    {
        if (!is_numeric($value)) {
            return false;
        }

        $timestamp = intval($value);
        $current_time = time();

        return $timestamp > 0 && $timestamp < $current_time;
    }

    /**
     * Check if a value is a positive integer string (1 or more)
     *
     * @param string $value The value to check
     * @return bool True if valid positive integer
     */
    private function is_positive_integer_string($value)
    {
        if (!is_numeric($value)) {
            return false;
        }

        $int_value = intval($value);
        return $int_value >= 1 && strval($int_value) === $value;
    }

    /**
     * Check if a value is a valid start time (1-22)
     *
     * @param string $value The value to check
     * @return bool True if valid start time
     */
    private function is_valid_start_time($value)
    {
        if (!is_numeric($value)) {
            return false;
        }

        $int_value = intval($value);
        return $int_value >= 1 && $int_value <= 22;
    }

    /**
     * Check if a value is a valid end time (2-23)
     *
     * @param string $value The value to check
     * @return bool True if valid end time
     */
    private function is_valid_end_time($value)
    {
        if (!is_numeric($value)) {
            return false;
        }

        $int_value = intval($value);
        return $int_value >= 2 && $int_value <= 23;
    }

}