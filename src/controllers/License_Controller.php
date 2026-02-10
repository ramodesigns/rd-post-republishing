<?php
/**
 * License Controller Class
 *
 * Registers REST API endpoints for license activation.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class License_Controller
{
    /**
     * API namespace
     */
    private $namespace = 'postmetadata/v1/license';

    /**
     * Authorisation helper instance
     *
     * @var Authorisation_Helper
     */
    private $authorisation_helper;

    /**
     * License service instance
     *
     * @var License_Service
     */
    private $license_service;

    /**
     * Constructor
     *
     * @param Authorisation_Helper $authorisation_helper
     */
    public function __construct($authorisation_helper)
    {
        $this->authorisation_helper = $authorisation_helper;
        $this->license_service = new License_Service();
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes()
    {
        // Protected endpoints requiring authentication
        register_rest_route($this->namespace, '/retrieve', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'handle_retrieve_license_request'),
            'permission_callback' => array($this, 'check_authentication')
        ));

        register_rest_route($this->namespace, '/save', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'handle_save_license_request'),
            'permission_callback' => array($this, 'check_authentication'),
            'args' => $this->get_save_license_args()
        ));

        // Public endpoints (no security)
        register_rest_route($this->namespace, '/retrievepublic', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'handle_retrieve_license_request'),
            'permission_callback' => '__return_true'
        ));

        register_rest_route($this->namespace, '/savepublic', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'handle_save_license_request'),
            'permission_callback' => '__return_true',
            'args' => $this->get_save_license_args()
        ));
    }

    /**
     * Get arguments for save license endpoint
     *
     * @return array
     */
    private function get_save_license_args()
    {
        return array(
            'key' => array(
                'required' => true,
                'type' => 'string',
                'description' => 'License key (alphanumeric, max 50 characters)',
                'validate_callback' => function($param, $request, $key) {
                    if (empty($param)) {
                        return true;
                    }
                    if (strlen($param) > 50) {
                        return new WP_Error('rest_invalid_param', __('License key cannot exceed 50 characters.', 'rd-post-republishing'), array('status' => 400));
                    }
                    if (!ctype_alnum($param)) {
                        return new WP_Error('rest_invalid_param', __('License key must be alphanumeric.', 'rd-post-republishing'), array('status' => 400));
                    }
                    return true;
                }
            )
        );
    }

    /**
     * Check authentication for protected endpoints
     *
     * @param WP_REST_Request $request
     * @return bool|WP_Error
     */
    public function check_authentication($request)
    {
        if (!current_user_can('manage_options')) {
            return new WP_Error('rest_forbidden', __('You do not have permission to access this endpoint.', 'rd-post-republishing'), array('status' => 403));
        }
        return true;
    }

    /**
     * Handle retrieve license request
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_retrieve_license_request($request)
    {
        $license = $this->license_service->get_license();

        return new WP_REST_Response(array(
            'success' => true,
            'license' => $license
        ), 200);
    }

    /**
     * Handle save license request
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_save_license_request($request)
    {
        $license_key = $request->get_param('key');
        $success = $this->license_service->save_license($license_key);

        if ($success) {
            return new WP_REST_Response(array(
                'success' => true,
                'message' => __('License key saved successfully.', 'rd-post-republishing')
            ), 200);
        } else {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('Failed to save license key.', 'rd-post-republishing')
            ), 500);
        }
    }
}
