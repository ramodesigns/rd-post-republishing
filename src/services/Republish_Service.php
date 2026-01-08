<?php
/**
 * WordPress REST API Class for User Preferences
 *
 * Registers REST API endpoints:
 * - postmetadata/v1/preferences/update (protected)
 * - postmetadata/v1/preferences/retrieve (protected)
 * - postmetadata/v1/preferences/retrievepublic (public)
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

    public function republish_post($id)
    {
        $post = get_post($id);

        if (!$post || $post->post_status !== 'publish') {
            return new WP_Error(
                'invalid_post',
                __('Post not found or not published.'),
                array('status' => 404)
            );
        }

        $current_time = current_time('mysql');
        $current_time_gmt = current_time('mysql', true);

        $updated = wp_update_post(array(
            'ID'                => $id,
            'post_date'         => $current_time,
            'post_date_gmt'     => $current_time_gmt,
            'post_modified'     => $current_time,
            'post_modified_gmt' => $current_time_gmt,
        ), true);

        if (is_wp_error($updated)) {
            return $updated;
        }

        return array(
            'id'           => $id,
            'title'        => $post->post_title,
            'new_date'     => $current_time,
            'permalink'    => get_permalink($id),
            'republished'  => true,
        );
    }
}