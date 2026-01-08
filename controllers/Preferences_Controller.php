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

class Preferences_Controller
{

    /**
     * API namespace
     */
    private $namespace = 'postmetadata/v1/preferences';

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes()
    {
        // Protected endpoints requiring application password authentication
        register_rest_route($this->namespace, '/update', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'handle_update_preferences_request'),
            'permission_callback' => array($this, 'check_authentication'),
            'args' => $this->get_endpoint_args()
        ));

        register_rest_route($this->namespace, '/retrieve', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'handle_retrieve_preferences_request'),
            'permission_callback' => array($this, 'check_authentication'),
        ));

        // Public endpoint
        register_rest_route($this->namespace, '/retrievepublic', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'handle_retrieve_preferences_request'),
            'permission_callback' => '__return_true'
        ));
    }

    /**
     * Get endpoint arguments for validation
     *
     * @return array
     */
    private function get_endpoint_args()
    {
        return array(
            'post_ids' => array(
                'required' => true,
                'type' => 'array',
                'description' => 'Array of post IDs to optimize',
                'items' => array(
                    'type' => 'integer',
                    'minimum' => 1
                )
            )
        );
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
     * Handle update preferences request
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_update_preferences_request($request)
    {
        try {
            return new WP_REST_Response(array(
                'success' => true,
                'timestamp' => current_time('mysql')
            ), 200);
        } catch (Exception $e) {
            return new WP_Error(
                'update_preference_error',
                __('An error occurred: ') . $e->getMessage(),
                array('status' => 500)
            );
        }
    }

    /**
     * Handle retrieve preferences request
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_retrieve_preferences_request($request) {
        try {
            return new WP_REST_Response(array(
                'success' => true,
                'timestamp' => current_time('mysql')
            ), 200);
        } catch (Exception $e) {
            return new WP_Error(
                'retrieve_preference_error',
                __('An error occurred: ') . $e->getMessage(),
                array('status' => 500)
            );
        }
    }
}