<?php
/**
 * Service class for managing user preferences
 *
 * Handles database operations for the rd_pr_pref table
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Preferences_Service
{
    /**
     * Maximum length for preference key
     */
    const MAX_KEY_LENGTH = 50;

    /**
     * Maximum length for preference value
     */
    const MAX_VALUE_LENGTH = 500;

    /**
     * Constructor
     */
    public function __construct()
    {

    }

    /**
     * Get all preferences from the database
     *
     * @return array Array of key-value pairs
     */
    public function get_all_preferences()
    {
        global $wpdb;

        $table_name = Init_Setup::get_table_name();

        $results = $wpdb->get_results(
            "SELECT `key`, `value` FROM $table_name ORDER BY id ASC",
            ARRAY_A
        );

        if ($results === null) {
            return array();
        }

        return $results;
    }

    /**
     * Get a specific preference by key
     *
     * @param string $key The preference key to retrieve
     * @return array|null The preference as key-value pair, or null if not found
     */
    public function get_preference_by_key($key)
    {
        global $wpdb;

        $table_name = Init_Setup::get_table_name();

        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT `key`, `value` FROM $table_name WHERE `key` = %s",
                $key
            ),
            ARRAY_A
        );

        return $result;
    }


    /**
     * Update multiple preferences
     *
     * @param array $preferences Array of key-value pairs
     * @return array Result with 'successful', 'failed', and 'total' counts
     */
    public function update_preferences($preferences)
    {
        $result = array(
            'successful' => array(),
            'failed' => array(),
            'total' => count($preferences)
        );

        foreach ($preferences as $preference) {
            $key = isset($preference['key']) ? $preference['key'] : null;
            $value = isset($preference['value']) ? $preference['value'] : null;

            $validation_error = $this->validate_preference($key, $value);

            if ($validation_error !== null) {
                $result['failed'][] = array(
                    'key' => $key,
                    'value' => $value,
                    'error' => $validation_error
                );
                continue;
            }

            $update_result = $this->upsert_preference($key, $value);

            if ($update_result === false) {
                $result['failed'][] = array(
                    'key' => $key,
                    'value' => $value,
                    'error' => 'Database error occurred'
                );
            } else {
                $result['successful'][] = array(
                    'key' => $key,
                    'value' => $value
                );
            }
        }

        return $result;
    }

    /**
     * Validate a preference key and value
     *
     * @param mixed $key The preference key
     * @param mixed $value The preference value
     * @return string|null Error message or null if valid
     */
    private function validate_preference($key, $value)
    {
        if ($key === null || $key === '') {
            return 'Key is required';
        }

        if (!is_string($key)) {
            return 'Key must be a string';
        }

        if (strlen($key) > self::MAX_KEY_LENGTH) {
            return 'Key exceeds maximum length of ' . self::MAX_KEY_LENGTH . ' characters';
        }

        if ($value === null) {
            return 'Value is required';
        }

        if (!is_string($value) && !is_numeric($value)) {
            return 'Value must be a string or number';
        }

        $value_string = (string) $value;
        if (strlen($value_string) > self::MAX_VALUE_LENGTH) {
            return 'Value exceeds maximum length of ' . self::MAX_VALUE_LENGTH . ' characters';
        }

        return null;
    }

    /**
     * Insert or update a preference in the database
     *
     * @param string $key The preference key
     * @param string $value The preference value
     * @return bool True on success, false on failure
     */
    private function upsert_preference($key, $value)
    {
        global $wpdb;

        $table_name = Init_Setup::get_table_name();
        $value_string = (string) $value;

        // Check if the key already exists
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id FROM $table_name WHERE `key` = %s",
                $key
            )
        );

        if ($existing) {
            // Update existing row
            $result = $wpdb->update(
                $table_name,
                array('value' => $value_string),
                array('key' => $key),
                array('%s'),
                array('%s')
            );

            return $result !== false;
        } else {
            // Insert new row
            $result = $wpdb->insert(
                $table_name,
                array(
                    'key' => $key,
                    'value' => $value_string
                ),
                array('%s', '%s')
            );

            return $result !== false;
        }
    }
}