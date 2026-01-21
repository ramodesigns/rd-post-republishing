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
     * Constructor
     */
    public function __construct()
    {

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
        $validation_error = $this->validate_calculation($operation, $values);

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
     * @return array Result with 'success' and 'times' array or 'errors' array
     */
    public function get_post_times($date)
    {
        $errors = $this->validate_date($date);

        if (!empty($errors)) {
            return array(
                'success' => false,
                'errors' => $errors
            );
        }

        // Placeholder times array - actual implementation to be added later
        $times = array(
            '09:00',
            '12:00',
            '15:00',
            '18:00'
        );

        return array(
            'success' => true,
            'times' => $times
        );
    }

    /**
     * Validate date input
     *
     * @param mixed $date The date to validate
     * @return array Array of error messages (empty if valid)
     */
    private function validate_date($date)
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
    private function validate_calculation($operation, $values)
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
