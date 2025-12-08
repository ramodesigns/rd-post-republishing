<?php

declare(strict_types=1);

namespace WPR\Republisher\Tests\Unit\Api;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use WPR\Republisher\Api\RateLimiter;

/**
 * Unit tests for the RateLimiter class.
 *
 * @coversDefaultClass \WPR\Republisher\Api\RateLimiter
 */
class RateLimiterTest extends TestCase {

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Mock WordPress functions
		Monkey\Functions\stubs( [
			'get_option' => function ( $option, $default = false ) {
				if ( 'wpr_settings' === $option ) {
					return [
						'api_rate_limit_seconds' => 86400,
						'debug_mode'             => false,
					];
				}
				return $default;
			},
			'wp_cache_get' => '__return_false',
			'wp_cache_set' => '__return_true',
		] );
	}

	/**
	 * Tear down test environment.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * @covers ::__construct
	 */
	public function test_constructor_sets_default_window(): void {
		$limiter = new RateLimiter( null, null, 1 );

		$this->assertEquals( 86400, $limiter->get_window() );
	}

	/**
	 * @covers ::__construct
	 */
	public function test_constructor_accepts_custom_window(): void {
		$limiter = new RateLimiter( null, 3600, 1 );

		$this->assertEquals( 3600, $limiter->get_window() );
	}

	/**
	 * @covers ::__construct
	 */
	public function test_constructor_enforces_minimum_window(): void {
		$limiter = new RateLimiter( null, 0, 1 );

		$this->assertEquals( RateLimiter::MIN_WINDOW, $limiter->get_window() );
	}

	/**
	 * @covers ::get_window
	 */
	public function test_get_window_returns_current_window(): void {
		$limiter = new RateLimiter( null, 7200, 1 );

		$this->assertEquals( 7200, $limiter->get_window() );
	}

	/**
	 * @covers ::set_window
	 */
	public function test_set_window_updates_window(): void {
		$limiter = new RateLimiter( null, 3600, 1 );
		$limiter->set_window( 1800 );

		$this->assertEquals( 1800, $limiter->get_window() );
	}

	/**
	 * @covers ::set_window
	 */
	public function test_set_window_enforces_minimum(): void {
		$limiter = new RateLimiter( null, 3600, 1 );
		$limiter->set_window( -100 );

		$this->assertEquals( RateLimiter::MIN_WINDOW, $limiter->get_window() );
	}

	/**
	 * @covers ::get_status
	 */
	public function test_get_status_returns_array(): void {
		$limiter = new RateLimiter( null, 3600, 1 );
		$status = $limiter->get_status( '/trigger', null );

		$this->assertIsArray( $status );
		$this->assertArrayHasKey( 'limited', $status );
		$this->assertArrayHasKey( 'window_seconds', $status );
		$this->assertArrayHasKey( 'max_requests', $status );
		$this->assertArrayHasKey( 'next_allowed', $status );
		$this->assertArrayHasKey( 'retry_after', $status );
	}

	/**
	 * @covers ::get_rate_limit_headers
	 */
	public function test_get_rate_limit_headers_returns_headers(): void {
		$limiter = new RateLimiter( null, 3600, 1 );
		$headers = $limiter->get_rate_limit_headers( '/trigger', null );

		$this->assertIsArray( $headers );
		$this->assertArrayHasKey( 'X-RateLimit-Limit', $headers );
		$this->assertArrayHasKey( 'X-RateLimit-Remaining', $headers );
		$this->assertArrayHasKey( 'X-RateLimit-Reset', $headers );
	}

	/**
	 * @covers ::create_rate_limit_error
	 */
	public function test_create_rate_limit_error_returns_wp_error(): void {
		// Mock WP_Error class
		$limiter = new RateLimiter( null, 3600, 1 );
		$error = $limiter->create_rate_limit_error( '/trigger', null );

		$this->assertInstanceOf( \WP_Error::class, $error );
	}

	/**
	 * @covers ::should_bypass
	 */
	public function test_should_bypass_returns_false_when_not_debug(): void {
		$limiter = new RateLimiter( null, 3600, 1 );

		$this->assertFalse( $limiter->should_bypass() );
	}

	/**
	 * Test constants are defined correctly.
	 */
	public function test_constants_are_defined(): void {
		$this->assertEquals( 86400, RateLimiter::DEFAULT_WINDOW );
		$this->assertEquals( 1, RateLimiter::MIN_WINDOW );
		$this->assertEquals( 1, RateLimiter::DEFAULT_MAX_REQUESTS );
	}
}
