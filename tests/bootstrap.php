<?php
/**
 * PHPUnit bootstrap file for RD Post Republishing
 *
 * @package RD_Post_Republishing
 */

declare(strict_types=1);

// Define constants for testing
if ( ! defined( 'WPR_TESTING' ) ) {
	define( 'WPR_TESTING', true );
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}

if ( ! defined( 'WPR_VERSION' ) ) {
	define( 'WPR_VERSION', '1.0.0' );
}

if ( ! defined( 'WPR_PLUGIN_DIR' ) ) {
	define( 'WPR_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
}

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

// Load Composer autoloader
$autoloader = dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! file_exists( $autoloader ) ) {
	echo 'Composer autoloader not found. Run `composer install` first.' . PHP_EOL;
	exit( 1 );
}

require_once $autoloader;

// Initialize Brain Monkey
Monkey\setUp();

// Set up WordPress function mocks
Monkey\Functions\stubs( [
	// WordPress options functions
	'get_option'    => function ( $option, $default = false ) {
		return $default;
	},
	'add_option'    => '__return_true',
	'update_option' => '__return_true',
	'delete_option' => '__return_true',

	// WordPress transient functions
	'get_transient'    => '__return_false',
	'set_transient'    => '__return_true',
	'delete_transient' => '__return_true',

	// WordPress cache functions
	'wp_cache_get'    => '__return_false',
	'wp_cache_set'    => '__return_true',
	'wp_cache_delete' => '__return_true',

	// WordPress time functions
	'current_time' => function ( $type = 'mysql' ) {
		return 'mysql' === $type ? gmdate( 'Y-m-d H:i:s' ) : time();
	},
	'wp_date' => function ( $format, $timestamp = null ) {
		return gmdate( $format, $timestamp ?? time() );
	},
	'wp_timezone' => function () {
		return new DateTimeZone( 'UTC' );
	},

	// WordPress user functions
	'get_current_user_id' => function () {
		return 1;
	},
	'get_userdata' => function ( $user_id ) {
		return (object) [
			'ID'           => $user_id,
			'display_name' => 'Test User',
			'user_email'   => 'test@example.com',
		];
	},
	'user_can' => '__return_true',
	'wp_get_current_user' => function () {
		return (object) [
			'ID'      => 1,
			'exists'  => function () { return true; },
		];
	},

	// WordPress i18n functions
	'__'        => function ( $text ) { return $text; },
	'_e'        => function ( $text ) { echo $text; },
	'_n'        => function ( $single, $plural, $number ) { return $number === 1 ? $single : $plural; },
	'esc_html'  => function ( $text ) { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); },
	'esc_attr'  => function ( $text ) { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); },
	'esc_url'   => function ( $url ) { return filter_var( $url, FILTER_SANITIZE_URL ); },

	// WordPress sanitization functions
	'sanitize_text_field' => function ( $text ) {
		return trim( strip_tags( $text ) );
	},
	'absint' => function ( $maybeint ) {
		return abs( (int) $maybeint );
	},
	'wp_unslash' => function ( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	},

	// WordPress JSON functions
	'wp_json_encode' => function ( $data, $options = 0 ) {
		return json_encode( $data, $options );
	},

	// WordPress array functions
	'wp_parse_args' => function ( $args, $defaults = [] ) {
		if ( is_object( $args ) ) {
			$args = get_object_vars( $args );
		}
		return array_merge( $defaults, $args );
	},

	// WordPress hook functions (stubbed)
	'do_action'     => function () {},
	'apply_filters' => function ( $tag, $value ) { return $value; },
	'add_action'    => '__return_true',
	'add_filter'    => '__return_true',
] );

/**
 * Helper function to return true.
 *
 * @return bool
 */
function __return_true(): bool {
	return true;
}

/**
 * Helper function to return false.
 *
 * @return bool
 */
function __return_false(): bool {
	return false;
}
