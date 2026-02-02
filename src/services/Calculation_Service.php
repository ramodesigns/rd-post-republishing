<?php
/**
 * Service class for managing calculations
 *
 * Handles calculation operations for the plugin
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Calculation_Service
{
    /**
     * Maximum length for calculation input
     */
    const MAX_INPUT_LENGTH = 500;

    /**
     * Preferences service instance
     *
     * @var Preferences_Service
     */
    private $preferences_service;

    /**
     * Calculation helper instance
     *
     * @var Calculation_Helper
     */
    private $calculation_helper;

    /**
     * Constructor
     *
     * @param Preferences_Service|null $preferences_service Optional service for dependency injection
     * @param Calculation_Helper|null $calculation_helper Optional helper for dependency injection
     */
    public function __construct($preferences_service = null, $calculation_helper = null)
    {
        $this->preferences_service = $preferences_service ?: new Preferences_Service();
        $this->calculation_helper = $calculation_helper ?: new Calculation_Helper();
    }

    /**
     * Perform a calculation
     *
     * @param string $operation The operation to perform
     * @param array $values The values to use in the calculation
     * @return array Result with 'success' and 'result' or 'error'
     */
    public function calculate($operation, $values)
    {
        $validation_error = $this->calculation_helper->validate_calculation($operation, $values);

        if ($validation_error !== null) {
            return array(
                'success' => false,
                'error' => $validation_error
            );
        }

        $result = null;

        switch ($operation) {
            case 'sum':
                $result = array_sum($values);
                break;
            case 'average':
                $result = count($values) > 0 ? array_sum($values) / count($values) : 0;
                break;
            case 'min':
                $result = count($values) > 0 ? min($values) : null;
                break;
            case 'max':
                $result = count($values) > 0 ? max($values) : null;
                break;
            case 'count':
                $result = count($values);
                break;
            default:
                return array(
                    'success' => false,
                    'error' => 'Unknown operation: ' . $operation
                );
        }

        return array(
            'success' => true,
            'operation' => $operation,
            'result' => $result
        );
    }

    /**
     * Get available operations
     *
     * @return array List of available operations with descriptions
     */
    public function get_available_operations()
    {
        return array(
            array(
                'name' => 'sum',
                'description' => 'Calculate the sum of all values'
            ),
            array(
                'name' => 'average',
                'description' => 'Calculate the average of all values'
            ),
            array(
                'name' => 'min',
                'description' => 'Find the minimum value'
            ),
            array(
                'name' => 'max',
                'description' => 'Find the maximum value'
            ),
            array(
                'name' => 'count',
                'description' => 'Count the number of values'
            )
        );
    }

    /**
     * Get post times for a given date
     *
     * @param string $date The date in dd-mm-yyyy format
     * @return array Result with 'success', 'previous_times' and 'future_times' arrays or 'errors' array
     */
    public function get_post_times($date)
    {
        $errors = $this->calculation_helper->validate_date($date);

        if (!empty($errors)) {
            return array(
                'success' => false,
                'errors' => $errors
            );
        }

        // Fetch preference values
        $publish_start_time = (int) $this->get_preference_value('publish_start_time');
        $publish_end_time = (int) $this->get_preference_value('publish_end_time');
        $posts_per_day = (int) $this->get_preference_value('posts_per_day');

        // Validation: ensure we have a valid number of posts and time range
        if ($posts_per_day <= 0 || $publish_end_time <= $publish_start_time) {
            return array(
                'success' => false,
                'errors' => array('Invalid publishing configuration')
            );
        }

        // Generate deterministic times based on the date
        $times = $this->calculation_helper->generate_post_times($date, $publish_start_time, $publish_end_time, $posts_per_day);

        // Split times into previous and future based on current date/time
        $categorized_times = $this->categorize_times($date, $times);

        return array(
            'success' => true,
            'previous_times' => $categorized_times['previous_times'],
            'future_times' => $categorized_times['future_times']
        );
    }

    /**
     * Categorize times into previous and future based on current date/time
     *
     * @param string $date The date in dd-mm-yyyy format
     * @param array $times Array of times in hh:mm format
     * @return array Array with 'previous_times' and 'future_times'
     */
    public function categorize_times($date, $times)
    {
        $previous_times = array();
        $future_times = array();

        // Parse the request date (dd-mm-yyyy)
        $parts = explode('-', $date);
        $day = (int) $parts[0];
        $month = (int) $parts[1];
        $year = (int) $parts[2];

        // Create date string in Y-m-d format for comparison
        $request_date = sprintf('%04d-%02d-%02d', $year, $month, $day);
        $today = current_time('Y-m-d');

        if ($request_date < $today) {
            // Date is in the past - all times are previous
            $previous_times = $times;
        } elseif ($request_date > $today) {
            // Date is in the future - all times are future
            $future_times = $times;
        } else {
            // Date is today - split based on current time
            $current_time = current_time('H:i');

            foreach ($times as $time) {
                if ($time < $current_time) {
                    $previous_times[] = $time;
                } else {
                    $future_times[] = $time;
                }
            }
        }

        return array(
            'previous_times' => $previous_times,
            'future_times' => $future_times
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
}
