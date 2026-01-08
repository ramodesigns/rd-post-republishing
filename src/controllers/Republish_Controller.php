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

class Republish_Controller
{

    /**
     * API namespace
     */
    private $namespace = 'postmetadata/v1/republish';

    /**
     * Service instance
     */
    private $service;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->service = new Republish_Service();
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes()
    {
        // Protected endpoints requiring application password authentication
        register_rest_route($this->namespace, '/execute', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'handle_republish_request'),
            'permission_callback' => array($this, 'check_authentication'),
        ));

        // Public endpoint
        register_rest_route($this->namespace, '/executepublic', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'handle_republish_request'),
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
     * Handle republish request
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_republish_request($request) {
        try {
            $oldest_post = $this->service->find_oldest_post();

            return new WP_REST_Response(array(
                'success'     => true,
                'timestamp'   => current_time('mysql'),
                'oldest_post' => $oldest_post,
            ), 200);
        } catch (Exception $e) {
            return new WP_Error(
                'republish_error',
                __('An error occurred: ') . $e->getMessage(),
                array('status' => 500)
            );
        }
    }
}