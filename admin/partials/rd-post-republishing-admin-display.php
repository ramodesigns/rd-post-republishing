<?php

declare(strict_types=1);

/**
 * Main admin settings page template
 *
 * Displays the tabbed interface for plugin settings.
 *
 * @link       https://www.paulramotowski.com
 * @since      1.0.0
 *
 * @package    RD_Post_Republishing
 * @subpackage RD_Post_Republishing/admin/partials
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Variables passed from Admin class: $current_tab, $tabs
?>

<div class="wrap wpr-settings-wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<nav class="nav-tab-wrapper wpr-nav-tabs">
		<?php foreach ( $tabs as $tab_slug => $tab_name ) : ?>
			<a href="<?php echo esc_url( add_query_arg( 'tab', $tab_slug, admin_url( 'options-general.php?page=rd-post-republishing' ) ) ); ?>"
			   class="nav-tab <?php echo $current_tab === $tab_slug ? 'nav-tab-active' : ''; ?>">
				<?php echo esc_html( $tab_name ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<div class="wpr-tab-content">
		<?php
		$tab_file = plugin_dir_path( __DIR__ ) . "views/tab-{$current_tab}.php";
		if ( file_exists( $tab_file ) ) {
			include $tab_file;
		} else {
			echo '<p>' . esc_html__( 'Tab content not found.', 'rd-post-republishing' ) . '</p>';
		}
		?>
	</div>
</div>
