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
     * Get latest history entry for each unique post with post details
     *
     * Returns id, post_id, post_title, post_publish_datetime, and post_url
     * Only returns the most recent entry per post_id
     *
     * @return array Array of history entries with post details
     */
    public function get_all_history()
    {
        global $wpdb;

        $table_name = Init_Setup::get_log_table_name();
        $posts_table = $wpdb->posts;

        // Get only the latest entry for each post_id by using a subquery
        $results = $wpdb->get_results(
            "SELECT l.id, l.postid as post_id, p.post_title, p.post_date as post_publish_datetime
            FROM $table_name l
            INNER JOIN (
                SELECT postid, MAX(id) as max_id
                FROM $table_name
                WHERE type = 'republish' AND postid IS NOT NULL AND postid > 0
                GROUP BY postid
            ) latest ON l.id = latest.max_id
            INNER JOIN $posts_table p ON l.postid = p.ID
            ORDER BY l.id DESC",
            ARRAY_A
        );

        if ($results === null) {
            return array();
        }

        // Add post URLs to results
        foreach ($results as &$row) {
            $row['post_url'] = get_permalink((int) $row['post_id']);
        }

        return $results;
    }

    /**
     * Get history entry for a specific post with post details
     *
     * @param int $post_id The post ID to filter by
     * @return array Array with single history entry for the specified post
     */
    public function get_history_by_post_id($post_id)
    {
        global $wpdb;

        $table_name = Init_Setup::get_log_table_name();
        $posts_table = $wpdb->posts;

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT l.id, l.postid as post_id, p.post_title, p.post_date as post_publish_datetime
                FROM $table_name l
                INNER JOIN $posts_table p ON l.postid = p.ID
                WHERE l.type = 'republish' AND l.postid = %d
                ORDER BY l.id DESC
                LIMIT 1",
                $post_id
            ),
            ARRAY_A
        );

        if ($results === null) {
            return array();
        }

        // Add post URL to result
        foreach ($results as &$row) {
            $row['post_url'] = get_permalink((int) $row['post_id']);
        }

        return $results;
    }

    /**
     * Get latest history entries with pagination (one per post)
     *
     * @param int $limit Number of entries to return
     * @param int $offset Offset for pagination
     * @return array Array of history entries with post details
     */
    public function get_history_paginated($limit = 50, $offset = 0)
    {
        global $wpdb;

        $table_name = Init_Setup::get_log_table_name();
        $posts_table = $wpdb->posts;

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT l.id, l.postid as post_id, p.post_title, p.post_date as post_publish_datetime
                FROM $table_name l
                INNER JOIN (
                    SELECT postid, MAX(id) as max_id
                    FROM $table_name
                    WHERE type = 'republish' AND postid IS NOT NULL AND postid > 0
                    GROUP BY postid
                ) latest ON l.id = latest.max_id
                INNER JOIN $posts_table p ON l.postid = p.ID
                ORDER BY l.id DESC
                LIMIT %d OFFSET %d",
                $limit,
                $offset
            ),
            ARRAY_A
        );

        if ($results === null) {
            return array();
        }

        // Add post URLs to results
        foreach ($results as &$row) {
            $row['post_url'] = get_permalink((int) $row['post_id']);
        }

        return $results;
    }
}
