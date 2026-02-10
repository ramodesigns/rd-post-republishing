<?php

namespace Tests;

use Brain\Monkey;
use License_Service;
use Preferences_Service;
use PHPUnit\Framework\TestCase;
use Mockery;

class LicenseServiceTest extends TestCase
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
     * Test get_license when preference exists
     */
    public function test_get_license_success()
    {
        $prefs_mock = Mockery::mock('Preferences_Service');
        $prefs_mock->shouldReceive('get_preference_by_key')
            ->with('license')
            ->andReturn(['key' => 'license', 'value' => 'TEST-KEY-123']);

        $service = new License_Service($prefs_mock);
        $result = $service->get_license();

        $this->assertEquals('TEST-KEY-123', $result);
    }

    /**
     * Test constructor without dependency injection
     */
    public function test_constructor_defaults()
    {
        $service = new License_Service();
        $this->assertInstanceOf('License_Service', $service);
    }

    /**
     * Test get_license when preference does not exist
     */
    public function test_get_license_empty()
    {
        $prefs_mock = Mockery::mock('Preferences_Service');
        $prefs_mock->shouldReceive('get_preference_by_key')
            ->with('license')
            ->andReturn(null);

        $service = new License_Service($prefs_mock);
        $result = $service->get_license();

        $this->assertNull($result);
    }

    /**
     * Test save_license success
     */
    public function test_save_license_success()
    {
        $prefs_mock = Mockery::mock('Preferences_Service');
        $prefs_mock->shouldReceive('update_preferences')
            ->with([
                [
                    'key' => 'license',
                    'value' => 'NEW-KEY'
                ]
            ])
            ->andReturn([
                'successful' => [['key' => 'license', 'value' => 'NEW-KEY']],
                'failed' => [],
                'total' => 1
            ]);

        $service = new License_Service($prefs_mock);
        $result = $service->save_license('NEW-KEY');

        $this->assertTrue($result);
    }

    /**
     * Test save_license failure
     */
    public function test_save_license_failure()
    {
        $prefs_mock = Mockery::mock('Preferences_Service');
        $prefs_mock->shouldReceive('update_preferences')
            ->andReturn([
                'successful' => [],
                'failed' => [['key' => 'license', 'value' => 'FAIL', 'error' => 'DB error']],
                'total' => 1
            ]);

        $service = new License_Service($prefs_mock);
        $result = $service->save_license('FAIL');

        $this->assertFalse($result);
    }
}
