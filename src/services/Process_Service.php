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
     * Logging service instance
     *
     * @var Logging_Service
     */
    private $logging_service;

    /**
     * Calculation service instance
     *
     * @var Calculation_Service
     */
    private $calculation_service;

    /**
     * Republish service instance
     *
     * @var Republish_Service
     */
    private $republish_service;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->preferences_service = new Preferences_Service();
        $this->logging_service = new Logging_Service();
        $this->calculation_service = new Calculation_Service($this->preferences_service);
        $this->republish_service = new Republish_Service();
    }

    /**
     * Execute the republish process
     *
     * @return array Result with 'success' (bool), 'errors' (array), and additional data
     */
    public function execute_republish_process()
    {
        $errors = array();

        // First validate prerequisites
        $validation_result = $this->validate_prerequisites();

        if (!$validation_result['success']) {
            return $validation_result;
        }

        // Get posts_per_day preference
        $posts_per_day = intval($this->get_preference_value('posts_per_day'));

        // Count republish logs for today
        $republish_count_today = $this->logging_service->count_logs_of_type_today('republish');

        // Check if we've already reached the daily limit
        if ($republish_count_today >= $posts_per_day) {
            $errors[] = "Daily republish limit reached. Already republished " . $republish_count_today . " of " . $posts_per_day . " posts today.";
            return array(
                'success' => false,
                'errors' => $errors
            );
        }

        // Get today's date in dd-mm-yyyy format
        $today = current_time('d-m-Y');

        // Get post times for today
        $post_times_result = $this->calculation_service->get_post_times($today);

        if (!$post_times_result['success']) {
            return array(
                'success' => false,
                'errors' => $post_times_result['errors']
            );
        }

        $previous_times = $post_times_result['previous_times'];

        // Get all times that are due for republishing
        // These are all previous_times from the next index onwards
        $next_time_index = $republish_count_today;
        $times_due = array_slice($previous_times, $next_time_index);

        if (empty($times_due)) {
            // No times are due yet - either all caught up or next time is still in the future
            return array(
                'success' => true,
                'errors' => $errors,
                'message' => 'No posts are due for republishing yet'
            );
        }

        // Republish posts for each time due
        $republished_posts = array();

        foreach ($times_due as $time) {
            // Find the oldest post
            $oldest_post = $this->republish_service->find_oldest_post();

            if ($oldest_post === null) {
                $errors[] = "No posts available to republish";
                break;
            }

            $post_id = $oldest_post['id'];

            // Log the attempt
            $this->logging_service->insert_log('process', 'Attempting to Republish Post', $post_id);

            // Convert time (hh:mm) to epoch timestamp for today
            $timestamp = $this->convert_time_to_timestamp($time);

            // Republish the post
            $result = $this->republish_service->republish_post($post_id, $timestamp);

            if (is_wp_error($result)) {
                // Log the failure
                $this->logging_service->insert_log('error', 'Failed to Republish Post', $post_id);
                $errors[] = "Failed to republish post ID " . $post_id . ": " . $result->get_error_message();
                continue;
            }

            // Log the success
            $this->logging_service->insert_log('republish', 'Successfully Republished Post', $post_id);

            $republished_posts[] = $result;

            sleep(1);
        }

        return array(
            'success' => empty($errors),
            'errors' => $errors,
            'republished_posts' => $republished_posts
        );
    }

    /**
     * Convert a time string (hh:mm) to epoch timestamp for today
     *
     * @param string $time The time in hh:mm format
     * @return int The epoch timestamp
     */
    private function convert_time_to_timestamp($time)
    {
        $parts = explode(':', $time);
        $hours = (int) $parts[0];
        $minutes = (int) $parts[1];

        // Get today's date components
        $year = (int) current_time('Y');
        $month = (int) current_time('m');
        $day = (int) current_time('d');

        // Create timestamp for today at the specified time
        $local_timestamp = mktime($hours, $minutes, 0, $month, $day, $year);

        return $local_timestamp;
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
        $posts_per_day = $this->get_preference_value('posts_per_day');
        $publish_start_time = $this->get_preference_value('publish_start_time');
        $publish_end_time = $this->get_preference_value('publish_end_time');

        // Validate 'status' preference
        if ($status === null) {
            $errors[] = "Preference 'status' is not set";
        } elseif ($status !== 'active') {
            $errors[] = "Preference 'status' must have a value of 'active'";
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