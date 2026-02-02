<?php
/**
 * Helper class for calculation-related logic
 *
 * Handles date validation, calculation validation, and deterministic time generation.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Calculation_Helper
{
    /**
     * Generate deterministic post times based on the date and site domain
     *
     * @param string $date The date string used as seed
     * @param int $start_hour The start hour (e.g., 9 for 9am)
     * @param int $end_hour The end hour (e.g., 17 for 5pm)
     * @param int $posts_per_day Number of times to generate
     * @param string|null $domain Optional domain override
     * @return array Array of times in hh:mm format
     */
    public function generate_post_times($date, $start_hour, $end_hour, $posts_per_day, $domain = null)
    {
        $times = array();

        // Get site domain for unique seed per website
        if ($domain === null) {
            $domain = parse_url(home_url(), PHP_URL_HOST);
        }

        // Convert hours to minutes from midnight
        $start_minutes = $start_hour * 60;
        $end_minutes = $end_hour * 60;
        $total_minutes = $end_minutes - $start_minutes;

        // Calculate segment size for each post
        $segment_size = $total_minutes / $posts_per_day;

        for ($i = 0; $i < $posts_per_day; $i++) {
            // Generate a deterministic offset within this segment using domain, date and index
            $segment_seed = crc32($domain . '_' . $date . '_' . $i);
            $offset_within_segment = abs($segment_seed) % (int) $segment_size;

            // Calculate the time in minutes from midnight
            $time_in_minutes = $start_minutes + ($i * $segment_size) + $offset_within_segment;

            // Convert to hours and minutes
            $hours = (int) floor($time_in_minutes / 60);
            $minutes = (int) ($time_in_minutes % 60);

            // Format as hh:mm
            $times[] = sprintf('%02d:%02d', $hours, $minutes);
        }

        return $times;
    }

    /**
     * Validate date input
     *
     * @param mixed $date The date to validate
     * @return array Array of error messages (empty if valid)
     */
    public function validate_date($date)
    {
        $errors = array();

        if ($date === null || $date === '') {
            $errors[] = 'date is missing';
            return $errors;
        }

        if (!is_string($date)) {
            $errors[] = 'date is invalid';
            return $errors;
        }

        // Check format dd-mm-yyyy
        if (!preg_match('/^\d{2}-\d{2}-\d{4}$/', $date)) {
            $errors[] = 'date is invalid';
            return $errors;
        }

        // Parse and validate the date components
        $parts = explode('-', $date);
        $day = (int) $parts[0];
        $month = (int) $parts[1];
        $year = (int) $parts[2];

        if (!checkdate($month, $day, $year)) {
            $errors[] = 'date is invalid';
            return $errors;
        }

        return $errors;
    }

    /**
     * Validate calculation inputs
     *
     * @param mixed $operation The operation to validate
     * @param mixed $values The values to validate
     * @return string|null Error message or null if valid
     */
    public function validate_calculation($operation, $values)
    {
        if ($operation === null || $operation === '') {
            return 'Operation is required';
        }

        if (!is_string($operation)) {
            return 'Operation must be a string';
        }

        $valid_operations = array('sum', 'average', 'min', 'max', 'count');
        if (!in_array($operation, $valid_operations)) {
            return 'Invalid operation. Valid operations are: ' . implode(', ', $valid_operations);
        }

        if ($values === null) {
            return 'Values are required';
        }

        if (!is_array($values)) {
            return 'Values must be an array';
        }

        foreach ($values as $index => $value) {
            if (!is_numeric($value)) {
                return 'Value at index ' . $index . ' must be numeric';
            }
        }

        return null;
    }
}
