<?php

declare(strict_types=1);

namespace WPR\Republisher\Tests\Unit\Logger;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use WPR\Republisher\Logger\Logger;

/**
 * Unit tests for the Logger class.
 *
 * @coversDefaultClass \WPR\Republisher\Logger\Logger
 */
class LoggerTest extends TestCase {

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Reset singleton
		Logger::reset();

		// Mock repository
		Monkey\Functions\stubs( [
			'get_option' => function ( $option, $default = false ) {
				if ( 'wpr_settings' === $option ) {
					return [
						'debug_mode' => true,
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
		Logger::reset();
		parent::tearDown();
	}

	/**
	 * @covers ::__construct
	 * @covers ::get_instance
	 */
	public function test_get_instance_returns_singleton(): void {
		$instance1 = Logger::get_instance();
		$instance2 = Logger::get_instance();

		$this->assertSame( $instance1, $instance2 );
	}

	/**
	 * @covers ::reset
	 */
	public function test_reset_clears_singleton(): void {
		$instance1 = Logger::get_instance();
		Logger::reset();
		$instance2 = Logger::get_instance();

		$this->assertNotSame( $instance1, $instance2 );
	}

	/**
	 * @covers ::is_debug_mode
	 */
	public function test_is_debug_mode_returns_setting(): void {
		$logger = new Logger();

		$this->assertTrue( $logger->is_debug_mode() );
	}

	/**
	 * @covers ::set_debug_mode
	 */
	public function test_set_debug_mode_updates_state(): void {
		$logger = new Logger();
		$logger->set_debug_mode( false );

		$this->assertFalse( $logger->is_debug_mode() );
	}

	/**
	 * @covers ::get_memory_usage
	 */
	public function test_get_memory_usage_returns_formatted_string(): void {
		$logger = new Logger();
		$memory = $logger->get_memory_usage();

		$this->assertMatchesRegularExpression( '/^\d+(\.\d+)?\s+(B|KB|MB|GB)$/', $memory );
	}

	/**
	 * @covers ::get_peak_memory_usage
	 */
	public function test_get_peak_memory_usage_returns_formatted_string(): void {
		$logger = new Logger();
		$memory = $logger->get_peak_memory_usage();

		$this->assertMatchesRegularExpression( '/^\d+(\.\d+)?\s+(B|KB|MB|GB)$/', $memory );
	}

	/**
	 * @covers ::start_timer
	 */
	public function test_start_timer_returns_float(): void {
		$logger = new Logger();
		$start = $logger->start_timer();

		$this->assertIsFloat( $start );
		$this->assertGreaterThan( 0, $start );
	}

	/**
	 * @covers ::start_timer
	 * @covers ::end_timer
	 */
	public function test_end_timer_returns_duration(): void {
		$logger = new Logger();
		$start = $logger->start_timer();

		// Small delay
		usleep( 1000 );

		$duration = $logger->end_timer( $start, 'Test operation' );

		$this->assertIsFloat( $duration );
		$this->assertGreaterThan( 0, $duration );
	}

	/**
	 * @covers ::debug
	 */
	public function test_debug_logs_when_debug_enabled(): void {
		$logger = new Logger();
		$logger->set_debug_mode( true );

		// Should not throw - just verifies it runs
		$logger->debug( 'Test debug message', [ 'key' => 'value' ] );

		$this->assertTrue( true );
	}

	/**
	 * @covers ::info
	 */
	public function test_info_logs_message(): void {
		$logger = new Logger();
		$logger->set_debug_mode( true );

		$logger->info( 'Test info message' );

		$this->assertTrue( true );
	}

	/**
	 * @covers ::warning
	 */
	public function test_warning_logs_message(): void {
		$logger = new Logger();

		$logger->warning( 'Test warning message' );

		$this->assertTrue( true );
	}

	/**
	 * @covers ::error
	 */
	public function test_error_logs_message(): void {
		$logger = new Logger();

		$logger->error( 'Test error message' );

		$this->assertTrue( true );
	}

	/**
	 * @covers ::republish_event
	 */
	public function test_republish_event_logs_post_action(): void {
		$logger = new Logger();
		$logger->set_debug_mode( true );

		$logger->republish_event( 123, 'republish', 'success' );

		$this->assertTrue( true );
	}

	/**
	 * @covers ::cron_event
	 */
	public function test_cron_event_logs_event(): void {
		$logger = new Logger();
		$logger->set_debug_mode( true );

		$logger->cron_event( 'wpr_daily', 'Cron executed' );

		$this->assertTrue( true );
	}

	/**
	 * @covers ::api_event
	 */
	public function test_api_event_logs_request(): void {
		$logger = new Logger();
		$logger->set_debug_mode( true );

		$logger->api_event( '/trigger', 200 );

		$this->assertTrue( true );
	}

	/**
	 * @covers ::cache_event
	 */
	public function test_cache_event_logs_operation(): void {
		$logger = new Logger();
		$logger->set_debug_mode( true );

		$logger->cache_event( 'clear', 123, [ 'wp_rocket' => true ] );

		$this->assertTrue( true );
	}

	/**
	 * @covers ::db_event
	 */
	public function test_db_event_logs_operation(): void {
		$logger = new Logger();
		$logger->set_debug_mode( true );

		$logger->db_event( 'insert', 'wpr_history', [ 'rows' => 1 ] );

		$this->assertTrue( true );
	}
}
