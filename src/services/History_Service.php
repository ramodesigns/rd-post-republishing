<?php
/**
 * Service class for managing history data
 *
 * Handles database operations for history retrieval
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class History_Service
{
    /**
     * Constructor
     */
    public function __construct()
    {

    }

    /**
     * Get all history entries from the database
     *
     * @return array Array of history entries
     */
    public function get_all_history()
    {
        global $wpdb;

        $table_name = Init_Setup::get_log_table_name();

        $results = $wpdb->get_results(
            "SELECT id, timestamp, type, entry, postid FROM $table_name WHERE type = 'republish' ORDER BY timestamp DESC",
            ARRAY_A
        );

        if ($results === null) {
            return array();
        }

        return $results;
    }

    /**
     * Get history entries filtered by post ID
     *
     * @param int $post_id The post ID to filter by
     * @return array Array of history entries for the specified post
     */
    public function get_history_by_post_id($post_id)
    {
        global $wpdb;

        $table_name = Init_Setup::get_log_table_name();

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, timestamp, type, entry, postid FROM $table_name WHERE type = 'republish' AND postid = %d ORDER BY timestamp DESC",
                $post_id
            ),
            ARRAY_A
        );

        if ($results === null) {
            return array();
        }

        return $results;
    }

    /**
     * Get history entries with pagination
     *
     * @param int $limit Number of entries to return
     * @param int $offset Offset for pagination
     * @return array Array of history entries
     */
    public function get_history_paginated($limit = 50, $offset = 0)
    {
        global $wpdb;

        $table_name = Init_Setup::get_log_table_name();

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, timestamp, type, entry, postid FROM $table_name WHERE type = 'republish' ORDER BY timestamp DESC LIMIT %d OFFSET %d",
                $limit,
                $offset
            ),
            ARRAY_A
        );

        if ($results === null) {
            return array();
        }

        return $results;
    }
}
