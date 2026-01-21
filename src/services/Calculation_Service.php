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
