<?php
/**
 * WordPress REST API Class for User Preferences
 *
 * Registers REST API endpoints:
 * - postmetadata/v1/preferences/update (protected)
 * - postmetadata/v1/preferences/retrieve (protected)
 * - postmetadata/v1/preferences/updatepublic (public)
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
        $this->preferences_service = new Preferences_Service();
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes()
    {
        // Protected endpoints requiring authentication
        register_rest_route($this->namespace, '/update', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'handle_update_preferences_request'),
            'permission_callback' => array($this, 'check_authentication'),
            'args' => $this->get_endpoint_args()
        ));

        register_rest_route($this->namespace, '/retrieve', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'handle_retrieve_preferences_request'),
            'permission_callback' => array($this, 'check_authentication')
        ));

        // Public endpoints
        register_rest_route($this->namespace, '/updatepublic', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'handle_update_preferences_request'),
            'permission_callback' => array($this, 'check_debug_authorization'),
            'args' => $this->get_endpoint_args()
        ));

        register_rest_route($this->namespace, '/retrievepublic', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'handle_retrieve_preferences_request'),
            'permission_callback' => array($this, 'check_debug_authorization')
        ));

        // Generate Token endpoint (protected)
        register_rest_route($this->namespace, '/generate_token', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'handle_generate_token_request'),
            'permission_callback' => array($this, 'check_authentication')
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
            'preferences' => array(
                'required' => true,
                'type' => 'array',
                'description' => 'Array of key-value pairs to update',
                'items' => array(
                    'type' => 'object',
                    'properties' => array(
                        'key' => array(
                            'type' => 'string',
                            'description' => 'Preference key (max 50 characters)'
                        ),
                        'value' => array(
                            'type' => 'string',
                            'description' => 'Preference value (max 1000 characters)'
                        )
                    )
                )
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
     * Handle update preferences request
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_update_preferences_request($request)
    {
        try {
            $preferences = $request->get_param('preferences');

            if (empty($preferences) || !is_array($preferences)) {
                return new WP_REST_Response(array(
                    'success' => false,
                    'message' => 'No preferences provided',
                    'timestamp' => current_time('mysql')
                ), 400);
            }

            $result = $this->preferences_service->update_preferences($preferences);

            // After updating preferences, manage the cron job
            require_once plugin_dir_path( dirname( __FILE__ ) ) . 'services/Cron_Service.php';
            $cron_service = new Cron_Service();
            $cron_service->manage_cron();

            $successful_count = count($result['successful']);
            $failed_count = count($result['failed']);
            $total = $result['total'];

            // All successful
            if ($failed_count === 0) {
                return new WP_REST_Response(array(
                    'success' => true,
                    'message' => 'All preferences updated successfully',
                    'updated' => $result['successful'],
                    'timestamp' => current_time('mysql')
                ), 200);
            }

            // All failed
            if ($successful_count === 0) {
                return new WP_REST_Response(array(
                    'success' => false,
                    'message' => 'All preferences failed validation',
                    'failed' => $result['failed'],
                    'timestamp' => current_time('mysql')
                ), 400);
            }

            // Partial success (207 Multi-Status)
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Some preferences failed validation',
                'updated' => $result['successful'],
                'failed' => $result['failed'],
                'timestamp' => current_time('mysql')
            ), 207);

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
    public function handle_retrieve_preferences_request($request)
    {
        try {
            $key = $request->get_param('key');

            if ($key !== null && $key !== '') {
                $preference = $this->preferences_service->get_preference_by_key($key);

                return new WP_REST_Response(array(
                    'success' => true,
                    'data' => $preference,
                    'timestamp' => current_time('mysql')
                ), 200);
            }

            $preferences = $this->preferences_service->get_all_preferences();

            return new WP_REST_Response(array(
                'success' => true,
                'data' => $preferences,
                'count' => count($preferences),
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

    /**
     * Handle generate token request
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_generate_token_request($request)
    {
        try {
            $token = $this->authorisation_helper->generate_token();

            return new WP_REST_Response(array(
                'success' => true,
                'token' => $token,
                'timestamp' => current_time('mysql')
            ), 200);
        } catch (Exception $e) {
            return new WP_Error(
                'generate_token_error',
                __('An error occurred: ') . $e->getMessage(),
                array('status' => 500)
            );
        }
    }
}