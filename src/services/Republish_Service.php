<?php
/**
 * Service class for republishing posts
 *
 * Handles republishing operations for WordPress posts
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Republish_Service
{

    /**
     * Constructor
     */
    public function __construct()
    {

    }

    public function find_oldest_post()
    {
        $args = array(
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'orderby'        => 'date',
            'order'          => 'ASC',
        );

        $query = new WP_Query($args);

        if ($query->have_posts()) {
            $post = $query->posts[0];
            return array(
                'id'         => $post->ID,
                'title'      => $post->post_title,
                'date'       => $post->post_date,
                'permalink'  => get_permalink($post->ID),
            );
        }

        return null;
    }

    /**
     * Republish a post with a new date
     *
     * @param int $id The post ID to republish
     * @param int|null $timestamp Optional epoch timestamp for the new publish date
     * @return array|WP_Error Result array on success, WP_Error on failure
     */
    public function republish_post($id, $timestamp = null)
    {
        $post = get_post($id);

        if (!$post || $post->post_status !== 'publish') {
            return new WP_Error(
                'invalid_post',
                __('Post not found or not published.'),
                array('status' => 404)
            );
        }

        // Use provided timestamp or current time
        if ($timestamp !== null && is_numeric($timestamp)) {
            $epoch = intval($timestamp);
            $new_date = gmdate('Y-m-d H:i:s', $epoch + (get_option('gmt_offset') * HOUR_IN_SECONDS));
            $new_date_gmt = gmdate('Y-m-d H:i:s', $epoch);
        } else {
            $new_date = current_time('mysql');
            $new_date_gmt = current_time('mysql', true);
        }

        $updated = wp_update_post(array(
            'ID'                => $id,
            'post_date'         => $new_date,
            'post_date_gmt'     => $new_date_gmt,
            'post_modified'     => $new_date,
            'post_modified_gmt' => $new_date_gmt,
        ), true);

        if (is_wp_error($updated)) {
            return $updated;
        }

        return array(
            'id'           => $id,
            'title'        => $post->post_title,
            'new_date'     => $new_date,
            'permalink'    => get_permalink($id),
            'republished'  => true,
        );
    }
}