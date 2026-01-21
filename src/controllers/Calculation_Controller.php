<?php
/**
 * WordPress REST API Class for Calculations
 *
 * Registers REST API endpoints:
 * - /calculate - Perform a calculation
 * - /operations - Get available operations
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Calculation_Controller
{

    /**
     * API namespace
     */
    private $namespace = 'postmetadata/v1/calculation';

    /**
     * Calculation service instance
     *
     * @var Calculation_Service
     */
    private $calculation_service;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->calculation_service = new Calculation_Service();
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes()
    {
        // Protected endpoints requiring application password authentication
        register_rest_route($this->namespace, '/calculate', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'handle_calculate_request'),
            //'permission_callback' => array($this, 'check_authentication'),
            'permission_callback' => '__return_true',
            'args' => $this->get_calculate_endpoint_args()
        ));

        register_rest_route($this->namespace, '/operations', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'handle_operations_request'),
            //'permission_callback' => array($this, 'check_authentication'),
            'permission_callback' => '__return_true'
        ));

        // Public endpoints
        register_rest_route($this->namespace, '/calculatepublic', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'handle_calculate_request'),
            'permission_callback' => '__return_true',
            'args' => $this->get_calculate_endpoint_args()
        ));

        register_rest_route($this->namespace, '/operationspublic', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'handle_operations_request'),
            'permission_callback' => '__return_true'
        ));

        register_rest_route($this->namespace, '/posttimes', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'handle_posttimes_request'),
            //'permission_callback' => array($this, 'check_authentication'),
            'permission_callback' => '__return_true',
            'args' => $this->get_posttimes_endpoint_args()
        ));

        register_rest_route($this->namespace, '/posttimespublic', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'handle_posttimes_request'),
            'permission_callback' => '__return_true',
            'args' => $this->get_posttimes_endpoint_args()
        ));
    }

    /**
     * Get endpoint arguments for calculate validation
     *
     * @return array
     */
    private function get_calculate_endpoint_args()
    {
        return array(
            'operation' => array(
                'required' => true,
                'type' => 'string',
                'description' => 'The calculation operation to perform (sum, average, min, max, count)'
            ),
            'values' => array(
                'required' => true,
                'type' => 'array',
                'description' => 'Array of numeric values to use in the calculation',
                'items' => array(
                    'type' => 'number'
                )
            )
        );
    }

    /**
     * Get endpoint arguments for posttimes validation
     *
     * @return array
     */
    private function get_posttimes_endpoint_args()
    {
        return array(
            'date' => array(
                'required' => true,
                'type' => 'string',
                'description' => 'The date in dd-mm-yyyy format'
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
     * Handle calculate request
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_calculate_request($request)
    {
        try {
            $operation = $request->get_param('operation');
            $values = $request->get_param('values');

            $result = $this->calculation_service->calculate($operation, $values);

            if ($result['success']) {
                return new WP_REST_Response(array(
                    'success' => true,
                    'message' => 'Calculation completed successfully',
                    'operation' => $result['operation'],
                    'result' => $result['result'],
                    'timestamp' => current_time('mysql')
                ), 200);
            }

            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Calculation failed',
                'error' => $result['error'],
                'timestamp' => current_time('mysql')
            ), 400);

        } catch (Exception $e) {
            return new WP_Error(
                'calculation_error',
                __('An error occurred: ') . $e->getMessage(),
                array('status' => 500)
            );
        }
    }

    /**
     * Handle operations request
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_operations_request($request)
    {
        try {
            $operations = $this->calculation_service->get_available_operations();

            return new WP_REST_Response(array(
                'success' => true,
                'data' => $operations,
                'count' => count($operations),
                'timestamp' => current_time('mysql')
            ), 200);

        } catch (Exception $e) {
            return new WP_Error(
                'operations_error',
                __('An error occurred: ') . $e->getMessage(),
                array('status' => 500)
            );
        }
    }

    /**
     * Handle posttimes request
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_posttimes_request($request)
    {
        try {
            $date = $request->get_param('date');

            $result = $this->calculation_service->get_post_times($date);

            if ($result['success']) {
                return new WP_REST_Response(array(
                    'success' => true,
                    'previous_times' => $result['previous_times'],
                    'future_times' => $result['future_times'],
                    'timestamp' => current_time('mysql')
                ), 200);
            }

            return new WP_REST_Response(array(
                'success' => false,
                'errors' => $result['errors'],
                'timestamp' => current_time('mysql')
            ), 400);

        } catch (Exception $e) {
            return new WP_Error(
                'posttimes_error',
                __('An error occurred: ') . $e->getMessage(),
                array('status' => 500)
            );
        }
    }
}
