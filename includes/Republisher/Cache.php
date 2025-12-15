<?php

declare(strict_types=1);

namespace WPR\Republisher\Republisher;

/**
 * Cache clearing handler
 *
 * Handles clearing of WordPress core caches and integrates with
 * popular third-party caching plugins after post republishing.
 *
 * @link       https://www.paulramotowski.com
 * @since      1.0.0
 *
 * @package    RD_Post_Republishing
 * @subpackage RD_Post_Republishing/includes/Republisher
 */

/**
 * Cache class.
 *
 * Provides cache clearing functionality for republished posts.
 *
 * @since      1.0.0
 * @package    RD_Post_Republishing
 * @subpackage RD_Post_Republishing/includes/Republisher
 * @author     Paul Ramotowski <paulramotowski@gmail.com>
 */
class Cache {

	/**
	 * Clear all caches for a specific post.
	 *
	 * Clears WordPress core cache and attempts to clear caches from
	 * known third-party caching plugins.
	 *
	 * @since    1.0.0
	 * @param    int $post_id  The post ID to clear cache for.
	 * @return   array<string, bool>  Results of cache clearing attempts.
	 */
	public function clear_post_cache( int $post_id ): array {
		$results = [];

		// Clear WordPress core caches
		$results['wp_core'] = $this->clear_wp_core_cache( $post_id );

		// Clear third-party plugin caches
		$results['wp_rocket']      = $this->clear_wp_rocket_cache( $post_id );
		$results['w3tc']           = $this->clear_w3tc_cache( $post_id );
		$results['wp_super_cache'] = $this->clear_wp_super_cache( $post_id );
		$results['litespeed']      = $this->clear_litespeed_cache( $post_id );
		$results['wp_fastest']     = $this->clear_wp_fastest_cache( $post_id );
		$results['autoptimize']    = $this->clear_autoptimize_cache();

		/**
		 * Filter cache clearing results.
		 *
		 * Allows developers to add custom cache clearing logic.
		 *
		 * @since 1.0.0
		 * @param array<string, bool> $results  Cache clearing results.
		 * @param int                 $post_id  The post ID.
		 */
		$results = apply_filters( 'wpr_cache_clear_results', $results, $post_id );

		/**
		 * Action fired after all caches are cleared for a post.
		 *
		 * @since 1.0.0
		 * @param int                 $post_id  The post ID.
		 * @param array<string, bool> $results  Cache clearing results.
		 */
		do_action( 'wpr_after_cache_clear', $post_id, $results );

		return $results;
	}

	/**
	 * Clear WordPress core caches for a post.
	 *
	 * @since    1.0.0
	 * @param    int $post_id  The post ID.
	 * @return   bool  True on success.
	 */
	private function clear_wp_core_cache( int $post_id ): bool {
		// Clean the post cache
		clean_post_cache( $post_id );

		// Delete from object cache
		wp_cache_delete( $post_id, 'posts' );
		wp_cache_delete( $post_id, 'post_meta' );

		// Clear related taxonomy caches
		$taxonomies = get_object_taxonomies( get_post_type( $post_id ) );
		foreach ( $taxonomies as $taxonomy ) {
			$terms = wp_get_object_terms( $post_id, $taxonomy, [ 'fields' => 'ids' ] );
			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term_id ) {
					clean_term_cache( $term_id, $taxonomy );
				}
			}
		}

		// Clear front page cache if this is the latest post
		wp_cache_delete( 'last_changed', 'posts' );

		// Clear sitemaps cache
		wp_cache_delete( 'wp_sitemaps_posts_all', 'sitemaps' );
		wp_cache_delete( 'wp_sitemaps_index', 'sitemaps' );

		return true;
	}

	/**
	 * Clear WP Rocket cache for a post.
	 *
	 * @since    1.0.0
	 * @param    int $post_id  The post ID.
	 * @return   bool  True if cleared, false if plugin not active.
	 */
	private function clear_wp_rocket_cache( int $post_id ): bool {
		if ( ! function_exists( 'rocket_clean_post' ) ) {
			return false;
		}

		rocket_clean_post( $post_id );

		// Also clear minified CSS/JS if available
		if ( function_exists( 'rocket_clean_minify' ) ) {
			rocket_clean_minify();
		}

		return true;
	}

	/**
	 * Clear W3 Total Cache for a post.
	 *
	 * @since    1.0.0
	 * @param    int $post_id  The post ID.
	 * @return   bool  True if cleared, false if plugin not active.
	 */
	private function clear_w3tc_cache( int $post_id ): bool {
		if ( ! function_exists( 'w3tc_flush_post' ) ) {
			return false;
		}

		w3tc_flush_post( $post_id );

		return true;
	}

	/**
	 * Clear WP Super Cache for a post.
	 *
	 * @since    1.0.0
	 * @param    int $post_id  The post ID.
	 * @return   bool  True if cleared, false if plugin not active.
	 */
	private function clear_wp_super_cache( int $post_id ): bool {
		if ( ! function_exists( 'wp_cache_post_change' ) ) {
			return false;
		}

		wp_cache_post_change( $post_id );

		return true;
	}

	/**
	 * Clear LiteSpeed Cache for a post.
	 *
	 * @since    1.0.0
	 * @param    int $post_id  The post ID.
	 * @return   bool  True if cleared, false if plugin not active.
	 */
	private function clear_litespeed_cache( int $post_id ): bool {
		if ( ! class_exists( 'LiteSpeed\Purge' ) && ! function_exists( 'litespeed_purge_single' ) ) {
			return false;
		}

		// LiteSpeed Cache 3.x+
		if ( class_exists( 'LiteSpeed\Purge' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Intentionally firing LiteSpeed Cache hook.
			do_action( 'litespeed_purge_post', $post_id );
			return true;
		}

		// LiteSpeed Cache 2.x
		if ( function_exists( 'litespeed_purge_single' ) ) {
			litespeed_purge_single( $post_id );
			return true;
		}

		return false;
	}

	/**
	 * Clear WP Fastest Cache for a post.
	 *
	 * @since    1.0.0
	 * @param    int $post_id  The post ID.
	 * @return   bool  True if cleared, false if plugin not active.
	 */
	private function clear_wp_fastest_cache( int $post_id ): bool {
		global $wp_fastest_cache;

		if ( ! isset( $wp_fastest_cache ) || ! is_object( $wp_fastest_cache ) ) {
			return false;
		}

		if ( method_exists( $wp_fastest_cache, 'singleDeleteCache' ) ) {
			$wp_fastest_cache->singleDeleteCache( false, $post_id );
			return true;
		}

		return false;
	}

	/**
	 * Clear Autoptimize cache (global, not post-specific).
	 *
	 * @since    1.0.0
	 * @return   bool  True if cleared, false if plugin not active.
	 */
	private function clear_autoptimize_cache(): bool {
		if ( ! class_exists( 'autoptimizeCache' ) ) {
			return false;
		}

		if ( method_exists( 'autoptimizeCache', 'clearall' ) ) {
			\autoptimizeCache::clearall();
			return true;
		}

		return false;
	}

	/**
	 * Clear all caches site-wide (use sparingly).
	 *
	 * @since    1.0.0
	 * @return   array<string, bool>  Results of cache clearing attempts.
	 */
	public function clear_all_caches(): array {
		$results = [];

		// WordPress core
		wp_cache_flush();
		$results['wp_core'] = true;

		// WP Rocket
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
			$results['wp_rocket'] = true;
		}

		// W3 Total Cache
		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
			$results['w3tc'] = true;
		}

		// WP Super Cache
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
			$results['wp_super_cache'] = true;
		}

		// LiteSpeed Cache
		if ( class_exists( 'LiteSpeed\Purge' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Intentionally firing LiteSpeed Cache hook.
			do_action( 'litespeed_purge_all' );
			$results['litespeed'] = true;
		}

		// WP Fastest Cache
		global $wp_fastest_cache;
		if ( isset( $wp_fastest_cache ) && method_exists( $wp_fastest_cache, 'deleteCache' ) ) {
			$wp_fastest_cache->deleteCache( true );
			$results['wp_fastest'] = true;
		}

		// Autoptimize
		$results['autoptimize'] = $this->clear_autoptimize_cache();

		/**
		 * Action fired after all site caches are cleared.
		 *
		 * @since 1.0.0
		 * @param array<string, bool> $results  Cache clearing results.
		 */
		do_action( 'wpr_after_clear_all_caches', $results );

		return $results;
	}

	/**
	 * Get list of detected cache plugins.
	 *
	 * Useful for admin display showing which cache integrations are active.
	 *
	 * @since    1.0.0
	 * @return   array<string, bool>  Array of plugin name => active status.
	 */
	public function get_detected_cache_plugins(): array {
		return [
			'WP Rocket'        => function_exists( 'rocket_clean_post' ),
			'W3 Total Cache'   => function_exists( 'w3tc_flush_post' ),
			'WP Super Cache'   => function_exists( 'wp_cache_post_change' ),
			'LiteSpeed Cache'  => class_exists( 'LiteSpeed\Purge' ) || function_exists( 'litespeed_purge_single' ),
			'WP Fastest Cache' => isset( $GLOBALS['wp_fastest_cache'] ),
			'Autoptimize'      => class_exists( 'autoptimizeCache' ),
		];
	}
}
