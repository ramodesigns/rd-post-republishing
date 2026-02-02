<?php

namespace Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Calculation_Service;
use Preferences_Service;
use Calculation_Helper;
use PHPUnit\Framework\TestCase;
use Mockery;

class CalculationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Test all valid operations in calculate()
     */
    public function test_calculate_all_operations()
    {
        $service = new Calculation_Service();
        
        // Sum
        $result = $service->calculate('sum', [1, 2, 3]);
        $this->assertEquals(6, $result['result']);

        // Average
        $result = $service->calculate('average', [10, 20]);
        $this->assertEquals(15, $result['result']);

        // Min
        $result = $service->calculate('min', [5, 1, 10]);
        $this->assertEquals(1, $result['result']);

        // Max
        $result = $service->calculate('max', [5, 1, 10]);
        $this->assertEquals(10, $result['result']);

        // Count
        $result = $service->calculate('count', [1, 2, 3, 4]);
        $this->assertEquals(4, $result['result']);
    }

    /**
     * Test empty arrays in calculate()
     */
    public function test_calculate_empty_arrays()
    {
        $service = new Calculation_Service();
        
        $this->assertEquals(0, $service->calculate('average', [])['result']);
        $this->assertNull($service->calculate('min', [])['result']);
        $this->assertNull($service->calculate('max', [])['result']);
    }

    /**
     * Test error handling in calculate()
     */
    public function test_calculate_errors()
    {
        $service = new Calculation_Service();
        
        // Unknown operation (Helper will catch it first now)
        $result = $service->calculate('unknown', [1, 2]);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid operation', $result['error']);
    }

    /**
     * Test date validation error in get_post_times
     */
    public function test_get_post_times_validation_error()
    {
        $helper_mock = Mockery::mock('Calculation_Helper');
        $helper_mock->shouldReceive('validate_date')->andReturn(['error']);
        
        $service = new Calculation_Service(null, $helper_mock);
        $result = $service->get_post_times('invalid');
        
        $this->assertFalse($result['success']);
        $this->assertEquals(['error'], $result['errors']);
    }

    /**
     * Test time categorization for past, present, and future
     */
    public function test_categorize_times_logic()
    {
        $service = new Calculation_Service();
        $times = ['09:00', '12:00', '15:00'];

        // Case 1: Date is in the past
        Functions\when('current_time')->justReturn('2026-02-01');
        $result = $service->categorize_times('01-01-2020', $times);
        $this->assertCount(3, $result['previous_times']);
        $this->assertEmpty($result['future_times']);

        // Case 2: Date is in the future
        Functions\when('current_time')->justReturn('2026-02-01');
        $result = $service->categorize_times('01-01-2099', $times);
        $this->assertEmpty($result['previous_times']);
        $this->assertCount(3, $result['future_times']);

        // Case 3: Date is today
        Functions\when('current_time')->alias(function($arg) {
            if ($arg === 'Y-m-d') return '2026-02-01';
            if ($arg === 'H:i') return '11:00';
            return '';
        });
        
        $result = $service->categorize_times('01-02-2026', $times);
        $this->assertCount(1, $result['previous_times']); // 09:00 is before 11:00
        $this->assertCount(2, $result['future_times']);   // 12:00 and 15:00 are after
    }

    /**
     * Test get_post_times success and configuration error
     */
    public function test_get_post_times_behavior()
    {
        // Mock home_url() for helper
        Functions\when('home_url')->justReturn('https://example.com');

        $prefs_mock = Mockery::mock('Preferences_Service');
        $prefs_mock->shouldReceive('get_preference_by_key')->with('publish_start_time')->andReturn(['value' => 9]);
        $prefs_mock->shouldReceive('get_preference_by_key')->with('publish_end_time')->andReturn(['value' => 17]);
        $prefs_mock->shouldReceive('get_preference_by_key')->with('posts_per_day')->andReturn(['value' => 4]);

        $service = new Calculation_Service($prefs_mock);
        
        Functions\when('current_time')->justReturn('2024-01-01');
        
        $result = $service->get_post_times('01-01-2024');
        $this->assertTrue($result['success']);
        $this->assertCount(4, array_merge($result['previous_times'], $result['future_times']));

        // Test invalid configuration
        $prefs_fail_mock = Mockery::mock('Preferences_Service');
        $prefs_fail_mock->shouldReceive('get_preference_by_key')->andReturn(['value' => 0]);
        
        $service_fail = new Calculation_Service($prefs_fail_mock);
        $result_fail = $service_fail->get_post_times('01-01-2024');
        $this->assertFalse($result_fail['success']);
        $this->assertEquals('Invalid publishing configuration', $result_fail['errors'][0]);

        // Test path where preference is null
        $prefs_null_mock = Mockery::mock('Preferences_Service');
        $prefs_null_mock->shouldReceive('get_preference_by_key')->andReturn(null);
        $service_null = new Calculation_Service($prefs_null_mock);
        $result_null = $service_null->get_post_times('01-01-2024');
        $this->assertFalse($result_null['success']);
    }

    /**
     * Test getting available operations
     */
    public function test_get_available_operations()
    {
        $service = new Calculation_Service();
        $ops = $service->get_available_operations();
        $this->assertIsArray($ops);
        $this->assertCount(5, $ops);
    }

    /**
     * Test constructor without dependency injection
     */
    public function test_constructor_defaults()
    {
        $service = new Calculation_Service();
        $this->assertInstanceOf('Calculation_Service', $service);
    }
}
