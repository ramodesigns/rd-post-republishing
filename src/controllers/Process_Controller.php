<?php
/**
 * WordPress REST API Class for Process
 *
 * Registers REST API endpoints:
 * - postmetadata/v1/process/trigger (protected)
 * - postmetadata/v1/process/triggerpublic (public)
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Process_Controller
{

    /**
     * API namespace
     */
    private $namespace = 'postmetadata/v1/process';

    /**
     * Process service instance
     *
     * @var Process_Service
     */
    private $process_service;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->process_service = new Process_Service();
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes()
    {
        // Protected endpoints requiring application password authentication
        register_rest_route($this->namespace, '/trigger', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'handle_trigger_process_request'),
            //'permission_callback' => array($this, 'check_authentication'),
            'permission_callback' => '__return_true'
        ));

        // Public endpoint
        register_rest_route($this->namespace, '/triggerpublic', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'handle_trigger_process_request'),
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
     * Handle trigger process request
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_trigger_process_request($request)
    {
        try {


        } catch (Exception $e) {
            return new WP_Error(
                'process_error',
                __('An error occurred: ') . $e->getMessage(),
                array('status' => 500)
            );
        }
    }
}