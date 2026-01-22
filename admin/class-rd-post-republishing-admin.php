<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://www.paulramotowski.com
 * @since      1.0.0
 *
 * @package    Rd_Post_Republishing
 * @subpackage Rd_Post_Republishing/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Rd_Post_Republishing
 * @subpackage Rd_Post_Republishing/admin
 * @author     Paul Ramotowski <paulramotowski@gmail.com>
 */
class Rd_Post_Republishing_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * The current admin page hook suffix.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $page_hooks    Array of page hook suffixes.
	 */
	private $page_hooks = array();

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the admin menu pages.
	 *
	 * @since    1.0.0
	 */
	public function add_admin_menu() {

		// Add main menu page under Comments (position 26 is after Comments which is 25)
		$this->page_hooks['settings'] = add_menu_page(
			__( 'Post Republisher', 'rd-post-republishing' ),
			__( 'Post Republisher', 'rd-post-republishing' ),
			'manage_options',
			'rd-post-republisher',
			array( $this, 'render_settings_page' ),
			'dashicons-update',
			26
		);

		// Add Settings submenu (this replaces the default menu item)
		$this->page_hooks['settings'] = add_submenu_page(
			'rd-post-republisher',
			__( 'Settings', 'rd-post-republishing' ),
			__( 'Settings', 'rd-post-republishing' ),
			'manage_options',
			'rd-post-republisher',
			array( $this, 'render_settings_page' )
		);

		// Add Configure submenu
		$this->page_hooks['configure'] = add_submenu_page(
			'rd-post-republisher',
			__( 'Configure', 'rd-post-republishing' ),
			__( 'Configure', 'rd-post-republishing' ),
			'manage_options',
			'rd-post-republisher-configure',
			array( $this, 'render_configure_page' )
		);

		// Add Logs submenu
		$this->page_hooks['logs'] = add_submenu_page(
			'rd-post-republisher',
			__( 'Logs', 'rd-post-republishing' ),
			__( 'Logs', 'rd-post-republishing' ),
			'manage_options',
			'rd-post-republisher-logs',
			array( $this, 'render_logs_page' )
		);

	}

	/**
	 * Render the Settings page.
	 *
	 * @since    1.0.0
	 */
	public function render_settings_page() {
		require_once plugin_dir_path( __FILE__ ) . 'partials/rd-pr-settings-display.php';
	}

	/**
	 * Render the Configure page.
	 *
	 * @since    1.0.0
	 */
	public function render_configure_page() {
		require_once plugin_dir_path( __FILE__ ) . 'partials/rd-pr-configure-display.php';
	}

	/**
	 * Render the Logs page.
	 *
	 * @since    1.0.0
	 */
	public function render_logs_page() {
		require_once plugin_dir_path( __FILE__ ) . 'partials/rd-pr-logs-display.php';
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		$screen = get_current_screen();

		if ( ! $screen ) {
			return;
		}

		// Enqueue page-specific styles
		if ( $screen->id === 'toplevel_page_rd-post-republisher' ) {
			wp_enqueue_style(
				'rd-pr-settings',
				plugin_dir_url( __FILE__ ) . 'css/rd-pr-settings.css',
				array(),
				// $this->version,
				filemtime( plugin_dir_path( __FILE__ ) . 'css/rd-pr-settings.css' ),
				'all'
			);
		}

		if ( $screen->id === 'post-republisher_page_rd-post-republisher-configure' ) {
			wp_enqueue_style(
				'rd-pr-configure',
				plugin_dir_url( __FILE__ ) . 'css/rd-pr-configure.css',
				array(),
				// $this->version,
				filemtime( plugin_dir_path( __FILE__ ) . 'css/rd-pr-configure.css' ),
				'all'
			);
		}

		if ( $screen->id === 'post-republisher_page_rd-post-republisher-logs' ) {
			wp_enqueue_style(
				'rd-pr-logs',
				plugin_dir_url( __FILE__ ) . 'css/rd-pr-logs.css',
				array(),
				// $this->version,
				filemtime( plugin_dir_path( __FILE__ ) . 'css/rd-pr-logs.css' ),
				'all'
			);
		}

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		$screen = get_current_screen();

		if ( ! $screen ) {
			return;
		}

		// Enqueue page-specific scripts
		if ( $screen->id === 'toplevel_page_rd-post-republisher' ) {
			wp_enqueue_script(
				'rd-pr-settings',
				plugin_dir_url( __FILE__ ) . 'js/rd-pr-settings.js',
				array( 'jquery' ),
				// $this->version,
				filemtime( plugin_dir_path( __FILE__ ) . 'js/rd-pr-settings.js' ),
				true
			);

			wp_localize_script(
				'rd-pr-settings',
				'rdPrSettings',
				array(
					'restUrl'  => esc_url_raw( rest_url( 'postmetadata/v1/preferences' ) ),
					'nonce'    => wp_create_nonce( 'wp_rest' ),
				)
			);
		}

		if ( $screen->id === 'post-republisher_page_rd-post-republisher-configure' ) {
			wp_enqueue_script(
				'rd-pr-configure',
				plugin_dir_url( __FILE__ ) . 'js/rd-pr-configure.js',
				array( 'jquery' ),
				// $this->version,
				filemtime( plugin_dir_path( __FILE__ ) . 'js/rd-pr-configure.js' ),
				true
			);
		}

		if ( $screen->id === 'post-republisher_page_rd-post-republisher-logs' ) {
			wp_enqueue_script(
				'rd-pr-logs',
				plugin_dir_url( __FILE__ ) . 'js/rd-pr-logs.js',
				array( 'jquery' ),
				// $this->version,
				filemtime( plugin_dir_path( __FILE__ ) . 'js/rd-pr-logs.js' ),
				true
			);
		}

	}

}
