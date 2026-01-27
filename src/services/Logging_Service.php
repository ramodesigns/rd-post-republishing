<?php
/**
 * Service class for managing logs
 *
 * Handles database operations for the rd_pr_log table
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Logging_Service
{
    /**
     * Maximum length for log type key
     */
    const MAX_KEY_LENGTH = 50;

    /**
     * Maximum length for log entry value
     */
    const MAX_VALUE_LENGTH = 500;

    /**
     * Maximum digits for post ID
     */
    const MAX_POSTID_DIGITS = 10;

    /**
     * Constructor
     */
    public function __construct()
    {

    }

    /**
     * Get all logs from the database
     *
     * @return array Array of log entries
     */
    public function get_all_logs()
    {
        global $wpdb;

        $table_name = Init_Setup::get_log_table_name();

        $results = $wpdb->get_results(
            "SELECT id, timestamp, type, entry, postid FROM $table_name ORDER BY timestamp DESC",
            ARRAY_A
        );

        if ($results === null) {
            return array();
        }

        return $results;
    }

    /**
     * Get all logs of specific type from the database
     *
     * @param string $type The log type to filter by
     * @return array Array of log entries matching the type
     */
    public function get_logs_of_type($type)
    {
        global $wpdb;

        $table_name = Init_Setup::get_log_table_name();

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, timestamp, type, entry, postid FROM $table_name WHERE type = %s ORDER BY timestamp DESC",
                $type
            ),
            ARRAY_A
        );

        if ($results === null) {
            return array();
        }

        return $results;
    }

    /**
     * Insert a log entry
     *
     * @param string $type The log type
     * @param string $entry The log entry message
     * @param int|null $post_id The post ID (optional)
     * @return array Result with 'success' and 'error' or 'id'
     */
    public function insert_log($type, $entry, $post_id = null)
    {
        $validation_error = $this->validate_log($type, $entry, $post_id);

        if ($validation_error !== null) {
            return array(
                'success' => false,
                'error' => $validation_error
            );
        }

        global $wpdb;

        $table_name = Init_Setup::get_log_table_name();

        // Generate timestamp with milliseconds
        $timestamp = $this->get_timestamp_with_milliseconds();

        $data = array(
            'timestamp' => $timestamp,
            'type' => $type,
            'entry' => (string) $entry
        );
        $format = array('%s', '%s', '%s');

        if ($post_id !== null) {
            $data['postid'] = (int) $post_id;
            $format[] = '%d';
        }

        $result = $wpdb->insert($table_name, $data, $format);

        if ($result === false) {
            return array(
                'success' => false,
                'error' => 'Database error occurred'
            );
        }

        return array(
            'success' => true,
            'id' => $wpdb->insert_id
        );
    }

    /**
     * Count logs of a specific type for today
     *
     * @param string $type The log type to count
     * @return int Count of logs matching the type for today
     */
    public function count_logs_of_type_today($type)
    {
        global $wpdb;

        $table_name = Init_Setup::get_log_table_name();
        $today = current_time('Y-m-d');

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE type = %s AND DATE(timestamp) = %s",
                $type,
                $today
            )
        );

        return intval($count);
    }

    /**
     * Get current timestamp with milliseconds
     *
     * @return string Timestamp in Y-m-d H:i:s.v format
     */
    private function get_timestamp_with_milliseconds()
    {
        $microtime = microtime(true);
        $milliseconds = sprintf('%03d', ($microtime - floor($microtime)) * 1000);
        $datetime = new DateTime();
        $datetime->setTimestamp((int) $microtime);
        $datetime->setTimezone(wp_timezone());

        return $datetime->format('Y-m-d H:i:s') . '.' . $milliseconds;
    }

    /**
     * Validate a log type, entry, and post_id
     *
     * @param mixed $type The log type
     * @param mixed $entry The log entry
     * @param mixed $post_id The post ID (optional)
     * @return string|null Error message or null if valid
     */
    private function validate_log($type, $entry, $post_id = null)
    {
        if ($type === null || $type === '') {
            return 'Type is required';
        }

        if (!is_string($type)) {
            return 'Type must be a string';
        }

        if (strlen($type) > self::MAX_KEY_LENGTH) {
            return 'Type exceeds maximum length of ' . self::MAX_KEY_LENGTH . ' characters';
        }

        if ($entry === null || $entry === '') {
            return 'Entry is required';
        }

        if (!is_string($entry) && !is_numeric($entry)) {
            return 'Entry must be a string or number';
        }

        $entry_string = (string) $entry;
        if (strlen($entry_string) > self::MAX_VALUE_LENGTH) {
            return 'Entry exceeds maximum length of ' . self::MAX_VALUE_LENGTH . ' characters';
        }

        // Validate post_id if provided
        if ($post_id !== null) {
            if (!is_numeric($post_id)) {
                return 'Post ID must be a number';
            }

            $post_id_int = (int) $post_id;
            if ($post_id_int < 0) {
                return 'Post ID must be a positive number';
            }

            if (strlen((string) $post_id_int) > self::MAX_POSTID_DIGITS) {
                return 'Post ID exceeds maximum length of ' . self::MAX_POSTID_DIGITS . ' digits';
            }
        }

        return null;
    }




}