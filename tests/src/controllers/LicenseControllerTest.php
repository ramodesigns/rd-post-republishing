<?php

namespace Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use License_Controller;
use PHPUnit\Framework\TestCase;
use Mockery;

class LicenseControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        
        // Mock WordPress translation functions
        Functions\when('__')->returnArg(1);
        Functions\when('_e')->echoArg(1);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Test register_rest_routes
     */
    public function test_register_rest_routes()
    {
        $auth_helper_mock = Mockery::mock('Authorisation_Helper');
        
        // We expect 4 routes to be registered
        Functions\expect('register_rest_route')
            ->times(4)
            ->with(Mockery::type('string'), Mockery::type('string'), Mockery::type('array'));

        $controller = new License_Controller($auth_helper_mock);
        $controller->register_rest_routes();
        
        $this->assertTrue(true);
    }

    /**
     * Test handle_retrieve_license_request
     */
    public function test_handle_retrieve_license_request()
    {
        $auth_helper_mock = Mockery::mock('Authorisation_Helper');
        $controller = new License_Controller($auth_helper_mock);
        
        $license_service_mock = Mockery::mock('License_Service');
        $license_service_mock->shouldReceive('get_license')->andReturn('TEST-KEY');
        
        // Inject the mock service
        $reflection = new \ReflectionClass($controller);
        $property = $reflection->getProperty('license_service');
        if (method_exists($property, 'setAccessible')) {
            $property->setAccessible(true);
        }
        $property->setValue($controller, $license_service_mock);
        
        $request = new \WP_REST_Request('GET', '/postmetadata/v1/license/retrieve');
        $response = $controller->handle_retrieve_license_request($request);
        
        $this->assertInstanceOf('\WP_REST_Response', $response);
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertEquals('TEST-KEY', $data['license']);
    }

    /**
     * Test handle_save_license_request success
     */
    public function test_handle_save_license_request_success()
    {
        $auth_helper_mock = Mockery::mock('Authorisation_Helper');
        $controller = new License_Controller($auth_helper_mock);
        
        $license_service_mock = Mockery::mock('License_Service');
        $license_service_mock->shouldReceive('save_license')->with('VALIDKEY')->andReturn(true);
        
        // Inject the mock service
        $reflection = new \ReflectionClass($controller);
        $property = $reflection->getProperty('license_service');
        if (method_exists($property, 'setAccessible')) {
            $property->setAccessible(true);
        }
        $property->setValue($controller, $license_service_mock);
        
        $request = new \WP_REST_Request('POST', '/postmetadata/v1/license/save');
        $request->set_param('key', 'VALIDKEY');
        
        $response = $controller->handle_save_license_request($request);
        
        $this->assertInstanceOf('\WP_REST_Response', $response);
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertEquals(200, $response->get_status());
    }

    /**
     * Test handle_save_license_request failure
     */
    public function test_handle_save_license_request_failure()
    {
        $auth_helper_mock = Mockery::mock('Authorisation_Helper');
        $controller = new License_Controller($auth_helper_mock);
        
        $license_service_mock = Mockery::mock('License_Service');
        $license_service_mock->shouldReceive('save_license')->andReturn(false);
        
        // Inject the mock service
        $reflection = new \ReflectionClass($controller);
        $property = $reflection->getProperty('license_service');
        if (method_exists($property, 'setAccessible')) {
            $property->setAccessible(true);
        }
        $property->setValue($controller, $license_service_mock);
        
        $request = new \WP_REST_Request('POST', '/postmetadata/v1/license/save');
        $request->set_param('key', 'FAIL');
        
        $response = $controller->handle_save_license_request($request);
        
        $this->assertInstanceOf('\WP_REST_Response', $response);
        $data = $response->get_data();
        $this->assertFalse($data['success']);
        $this->assertEquals(500, $response->get_status());
    }

    /**
     * Test check_authentication permission denied
     */
    public function test_check_authentication_denied()
    {
        $auth_helper_mock = Mockery::mock('Authorisation_Helper');
        $controller = new License_Controller($auth_helper_mock);
        
        Functions\when('current_user_can')->alias(function($cap) {
            return $cap === 'manage_options' ? false : true;
        });
        
        $request = new \WP_REST_Request('GET', '/postmetadata/v1/license/retrieve');
        $result = $controller->check_authentication($request);
        
        $this->assertInstanceOf('\WP_Error', $result);
        $this->assertEquals('rest_forbidden', $result->get_error_code());
    }

    /**
     * Test check_authentication permission granted
     */
    public function test_check_authentication_granted()
    {
        $auth_helper_mock = Mockery::mock('Authorisation_Helper');
        $controller = new License_Controller($auth_helper_mock);
        
        Functions\when('current_user_can')->alias(function($cap) {
            return $cap === 'manage_options' ? true : false;
        });
        
        $request = new \WP_REST_Request('GET', '/postmetadata/v1/license/retrieve');
        $result = $controller->check_authentication($request);
        
        $this->assertTrue($result);
    }
}
