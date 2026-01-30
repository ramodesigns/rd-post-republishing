<?php
/**
 * WordPress REST API Class for Process
 *
 * Registers REST API endpoints:
 * - postmetadata/v1/process/trigger (protected)
 * - postmetadata/v1/process/triggerpublic (public)
 * - postmetadata/v1/process/validate (protected)
 * - postmetadata/v1/process/validatepublic (public)
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
     * Authorisation helper instance
     *
     * @var Authorisation_Helper
     */
    private $authorisation_helper;

    /**
     * Constructor
     *
     * @param Authorisation_Helper $authorisation_helper
     */
    public function __construct($authorisation_helper)
    {
        $this->authorisation_helper = $authorisation_helper;
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
            'permission_callback' => array($this, 'check_authentication')
        ));

        // Public endpoint
        register_rest_route($this->namespace, '/triggerpublic', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'handle_trigger_process_request'),
            'permission_callback' => array($this, 'check_debug_authorization')
        ));

        // Validate endpoint (protected)
        register_rest_route($this->namespace, '/validate', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'handle_validate_process_request'),
            'permission_callback' => array($this, 'check_authentication')
        ));

        // Validate endpoint (public)
        register_rest_route($this->namespace, '/validatepublic', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'handle_validate_process_request'),
            'permission_callback' => array($this, 'check_debug_authorization')
        ));
    }

    /**
     * Permission callback for public endpoints
     *
     * @param WP_REST_Request $request
     * @return bool|WP_Error
     */
    public function check_debug_authorization($request)
    {
        if ($this->authorisation_helper->is_debug_authorized()) {
            return true;
        }

        return new WP_Error(
            'rest_forbidden',
            __('Public access is restricted. Please enable Debug mode in settings.'),
            array('status' => 403)
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
     * Handle trigger process request
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_trigger_process_request($request)
    {
        try {
            $result = $this->process_service->execute_republish_process();

            $response = array(
                'success' => $result['success'],
                'errors' => $result['errors'],
                'timestamp' => current_time('mysql')
            );

            // Include additional data for testing if present
            if (isset($result['posts_per_day'])) {
                $response['posts_per_day'] = $result['posts_per_day'];
            }
            if (isset($result['republish_count_today'])) {
                $response['republish_count_today'] = $result['republish_count_today'];
            }
            if (isset($result['posts_to_republish'])) {
                $response['posts_to_republish'] = $result['posts_to_republish'];
            }

            return new WP_REST_Response($response, 200);

        } catch (Exception $e) {
            return new WP_Error(
                'process_error',
                __('An error occurred: ') . $e->getMessage(),
                array('status' => 500)
            );
        }
    }

    /**
     * Handle validate process request
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_validate_process_request($request)
    {
        try {
            $result = $this->process_service->validate_prerequisites();

            return new WP_REST_Response(array(
                'success' => $result['success'],
                'errors' => $result['errors'],
                'timestamp' => current_time('mysql')
            ), 200);

        } catch (Exception $e) {
            return new WP_Error(
                'validate_error',
                __('An error occurred: ') . $e->getMessage(),
                array('status' => 500)
            );
        }
    }
}