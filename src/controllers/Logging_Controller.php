<?php
/**
 * WordPress REST API Class for Logging
 *
 * Registers REST API endpoints:
 * -
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Logging_Controller
{

    /**
     * API namespace
     */
    private $namespace = 'postmetadata/v1/logging';

    /**
     * Logging service instance
     *
     * @var Logging_Service
     */
    private $logging_service;

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
        $this->logging_service = new Logging_Service();
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes()
    {
        // Protected endpoints requiring application password authentication
        register_rest_route($this->namespace, '/add', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'handle_add_log_request'),
            'permission_callback' => array($this, 'check_authentication'),
            'args' => $this->get_endpoint_args()
        ));

        register_rest_route($this->namespace, '/retrieve', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'handle_retrieve_log_request'),
            'permission_callback' => array($this, 'check_authentication')
        ));

        // Public endpoints
        register_rest_route($this->namespace, '/addpublic', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'handle_add_log_request'),
            'permission_callback' => array($this, 'check_debug_authorization'),
            'args' => $this->get_endpoint_args()
        ));

        register_rest_route($this->namespace, '/retrievepublic', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'handle_retrieve_log_request'),
            'permission_callback' => array($this, 'check_debug_authorization')
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
            'type' => array(
                'required' => true,
                'type' => 'string',
                'description' => 'Log type for categorization (max 50 characters)'
            ),
            'entry' => array(
                'required' => true,
                'type' => 'string',
                'description' => 'Log entry message (max 500 characters)'
            ),
            'post_id' => array(
                'required' => false,
                'type' => 'integer',
                'description' => 'Post ID associated with the log entry (max 10 digits)'
            )
        );
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
     * Handle add log request
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_add_log_request($request)
    {
        try {
            $type = $request->get_param('type');
            $entry = $request->get_param('entry');
            $post_id = $request->get_param('post_id');

            $result = $this->logging_service->insert_log($type, $entry, $post_id);

            if ($result['success']) {
                return new WP_REST_Response(array(
                    'success' => true,
                    'message' => 'Log entry added successfully',
                    'id' => $result['id'],
                    'timestamp' => current_time('mysql')
                ), 201);
            }

            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Failed to add log entry',
                'error' => $result['error'],
                'timestamp' => current_time('mysql')
            ), 400);

        } catch (Exception $e) {
            return new WP_Error(
                'add_log_error',
                __('An error occurred: ') . $e->getMessage(),
                array('status' => 500)
            );
        }
    }

    /**
     * Handle retrieve log request
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_retrieve_log_request($request)
    {
        try {
            $type = $request->get_param('type');

            if ($type !== null && $type !== '') {
                $logs = $this->logging_service->get_logs_of_type($type);
            } else {
                $logs = $this->logging_service->get_all_logs();
            }

            return new WP_REST_Response(array(
                'success' => true,
                'data' => $logs,
                'count' => count($logs),
                'timestamp' => current_time('mysql')
            ), 200);

        } catch (Exception $e) {
            return new WP_Error(
                'retrieve_log_error',
                __('An error occurred: ') . $e->getMessage(),
                array('status' => 500)
            );
        }
    }
}