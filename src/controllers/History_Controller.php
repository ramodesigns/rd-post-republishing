<?php
/**
 * WordPress REST API Class for History
 *
 * Registers REST API endpoints:
 * - postmetadata/v1/history/retrieve (protected)
 * - postmetadata/v1/history/retrievepublic (public)
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class History_Controller
{

    /**
     * API namespace
     */
    private $namespace = 'postmetadata/v1/history';

    /**
     * History service instance
     *
     * @var History_Service
     */
    private $history_service;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->history_service = new History_Service();
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes()
    {
        // Protected endpoint requiring authentication
        register_rest_route($this->namespace, '/retrieve', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'handle_retrieve_history_request'),
            'permission_callback' => array($this, 'check_authentication')
        ));

        // Public endpoint for testing
        register_rest_route($this->namespace, '/retrievepublic', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'handle_retrieve_history_request'),
            'permission_callback' => '__return_true'
        ));
    }

    /**
     * Authentication callback for protected endpoint
     *
     * @param WP_REST_Request $request
     * @return bool|WP_Error
     */
    public function check_authentication($request)
    {
        // Check if user is authenticated via application password
        if (!is_user_logged_in()) {
            return new WP_Error(
                'rest_forbidden',
                __('Authentication required. Please provide valid application password credentials.'),
                array('status' => 401)
            );
        }

        // Check if user has appropriate capabilities
        if (!current_user_can('edit_posts')) {
            return new WP_Error(
                'rest_forbidden',
                __('You do not have sufficient permissions to access this endpoint.'),
                array('status' => 403)
            );
        }

        return true;
    }

    /**
     * Handle retrieve history request
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_retrieve_history_request($request)
    {
        try {
            $post_id = $request->get_param('post_id');
            $limit = $request->get_param('limit');
            $offset = $request->get_param('offset');

            // If post_id is provided, filter by post
            if ($post_id !== null && $post_id !== '') {
                $history = $this->history_service->get_history_by_post_id((int) $post_id);
            }
            // If pagination params are provided, use paginated query
            elseif ($limit !== null) {
                $limit = (int) $limit > 0 ? (int) $limit : 50;
                $offset = $offset !== null ? (int) $offset : 0;
                $history = $this->history_service->get_history_paginated($limit, $offset);
            }
            // Otherwise get all history
            else {
                $history = $this->history_service->get_all_history();
            }

            return new WP_REST_Response(array(
                'success' => true,
                'data' => $history,
                'count' => count($history),
                'timestamp' => current_time('mysql')
            ), 200);

        } catch (Exception $e) {
            return new WP_Error(
                'retrieve_history_error',
                __('An error occurred: ') . $e->getMessage(),
                array('status' => 500)
            );
        }
    }
}
