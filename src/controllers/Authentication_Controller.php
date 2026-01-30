<?php
/**
 * WordPress REST API Class for Authentication
 *
 * Registers REST API endpoints:
 * - postmetadata/v1/authentication/generate (protected)
 * - postmetadata/v1/authentication/generatepublic (public)
 * - postmetadata/v1/authentication/retrieve (protected)
 * - postmetadata/v1/authentication/retrievepublic (public)
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Authentication_Controller
{
    /**
     * API namespace
     */
    private $namespace = 'postmetadata/v1/authentication';

    /**
     * Authentication service instance
     *
     * @var Authentication_Service
     */
    private $authentication_service;

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
        $this->authentication_service = new Authentication_Service();
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes()
    {
        // Generate Token endpoints
        register_rest_route($this->namespace, '/generate', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'handle_generate_request'),
            'permission_callback' => array($this, 'check_authentication')
        ));

        register_rest_route($this->namespace, '/generatepublic', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'handle_generate_request'),
            'permission_callback' => array($this, 'check_debug_authorization')
        ));

        // Retrieve Token endpoints
        register_rest_route($this->namespace, '/retrieve', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'handle_retrieve_request'),
            'permission_callback' => array($this, 'check_authentication')
        ));

        register_rest_route($this->namespace, '/retrievepublic', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'handle_retrieve_request'),
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
        if (!is_user_logged_in()) {
            return new WP_Error(
                'rest_forbidden',
                __('Authentication required. Please provide valid application password credentials.'),
                array('status' => 401)
            );
        }

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
     * Handle generate token request
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_generate_request($request)
    {
        try {
            $token = $this->authentication_service->generate_at_token();

            return new WP_REST_Response(array(
                'token' => $token
            ), 200);

        } catch (Exception $e) {
            return new WP_Error(
                'generate_error',
                __('An error occurred: ') . $e->getMessage(),
                array('status' => 500)
            );
        }
    }

    /**
     * Handle retrieve token request
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_retrieve_request($request)
    {
        try {
            $token = $this->authentication_service->retrieve_at_token();

            return new WP_REST_Response(array(
                'token' => $token
            ), 200);

        } catch (Exception $e) {
            return new WP_Error(
                'retrieve_error',
                __('An error occurred: ') . $e->getMessage(),
                array('status' => 500)
            );
        }
    }
}
