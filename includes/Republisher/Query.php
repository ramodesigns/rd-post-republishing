<?php

declare(strict_types=1);

namespace WPR\Republisher\Republisher;

use WPR\Republisher\Database\Repository;

/**
 * Post selection query class
 *
 * Handles the selection of posts eligible for republishing based on
 * configured criteria.
 *
 * @link       https://www.paulramotowski.com
 * @since      1.0.0
 *
 * @package    RD_Post_Republishing
 * @subpackage RD_Post_Republishing/includes/Republisher
 */

/**
 * Query class for selecting eligible posts.
 *
 * Implements the oldest-first selection algorithm with support for
 * multiple post types, category filtering, and quota calculations.
 *
 * @since      1.0.0
 * @package    RD_Post_Republishing
 * @subpackage RD_Post_Republishing/includes/Republisher
 * @author     Paul Ramotowski <paulramotowski@gmail.com>
 */
class Query {

	/**
	 * WordPress database instance.
	 *
	 * @since    1.0.0
	 * @var      \wpdb
	 */
	private \wpdb $wpdb;

	/**
	 * Repository instance for data access.
	 *
	 * @since    1.0.0
	 * @var      Repository
	 */
	private Repository $repository;

	/**
	 * Maximum posts per day (hard limit).
	 *
	 * @since    1.0.0
	 */
	private const MAX_POSTS_PER_DAY = 50;

	/**
	 * Initialize the query class.
	 *
	 * @since    1.0.0
	 * @param    Repository|null $repository  Optional repository instance.
	 */
	public function __construct( ?Repository $repository = null ) {
		global $wpdb;
		$this->wpdb       = $wpdb;
		$this->repository = $repository ?? new Repository();
	}

	/**
	 * Get eligible posts for republishing.
	 *
	 * Selects posts based on the oldest-first algorithm, respecting
	 * all configured filters and quotas.
	 *
	 * @since    1.0.0
	 * @param    array<string, mixed>|null $settings  Optional settings override.
	 * @return   array<int, object>  Array of eligible post objects.
	 */
	public function get_eligible_posts( ?array $settings = null ): array {
		$settings = $settings ?? $this->repository->get_settings();

		// Calculate the actual limit
		$limit = $this->calculate_quota( $settings );

		if ( $limit <= 0 ) {
			return [];
		}

		// Get already republished post IDs for today
		$excluded_ids = $this->repository->get_today_republished_ids();

		// Build and execute the query
		return $this->execute_selection_query(
			$settings['enabled_post_types'] ?? [ 'post' ],
			$settings['minimum_age_days'] ?? 30,
			$settings['category_filter_type'] ?? 'none',
			$settings['category_filter_ids'] ?? [],
			$excluded_ids,
			$limit,
			$settings['maintain_chronological_order'] ?? true
		);
	}

	/**
	 * Get a preview of posts that would be republished.
	 *
	 * Returns more details than get_eligible_posts for admin preview.
	 *
	 * @since    1.0.0
	 * @param    int $days_ahead  Number of days to preview (1-7).
	 * @return   array<string, array<int, object>>  Posts grouped by date.
	 */
	public function get_republishing_preview( int $days_ahead = 7 ): array {
		$settings = $this->repository->get_settings();
		$preview  = [];

		// For today, get actual eligible posts
		$today_posts = $this->get_eligible_posts( $settings );
		$today_key   = wp_date( 'Y-m-d' );
		if ( false !== $today_key ) {
			$preview[ $today_key ] = $today_posts;
		}

		// For future days, simulate the selection
		// (This is an approximation since actual selection depends on what gets republished)
		$limit        = $this->calculate_quota( $settings );
		$all_excluded = $this->repository->get_today_republished_ids();

		// Add today's eligible posts to excluded for future simulation
		foreach ( $today_posts as $post ) {
			/** @var object{ID: int} $post */
			$all_excluded[] = $post->ID;
		}

		for ( $day = 1; $day < min( $days_ahead, 7 ); $day++ ) {
			$future_timestamp = strtotime( "+{$day} days" );
			$future_date      = false !== $future_timestamp ? wp_date( 'Y-m-d', $future_timestamp ) : wp_date( 'Y-m-d' );
			$min_age          = $settings['minimum_age_days'] ?? 30;

			// Adjust minimum age for future date
			$adjusted_min_age = $min_age - $day;
			if ( $adjusted_min_age < 1 ) {
				$adjusted_min_age = 1;
			}

			$future_posts = $this->execute_selection_query(
				$settings['enabled_post_types'] ?? [ 'post' ],
				$adjusted_min_age,
				$settings['category_filter_type'] ?? 'none',
				$settings['category_filter_ids'] ?? [],
				$all_excluded,
				$limit,
				$settings['maintain_chronological_order'] ?? true
			);

			if ( false !== $future_date && is_string( $future_date ) ) {
				$preview[ $future_date ] = $future_posts;
			}

			// Add these to excluded for next iteration
			foreach ( $future_posts as $post ) {
				/** @var object{ID: int} $post */
				$all_excluded[] = $post->ID;
			}
		}

		return $preview;
	}

	/**
	 * Calculate the daily quota based on settings.
	 *
	 * @since    1.0.0
	 * @param    array<string, mixed> $settings  Plugin settings.
	 */
	public function calculate_quota( array $settings ): int {
		$quota_type  = $settings['daily_quota_type'] ?? 'number';
		$quota_value = (int) ( $settings['daily_quota_value'] ?? 5 );

		if ( 'percentage' === $quota_type ) {
			// Calculate based on total eligible posts
			$total_eligible = $this->get_total_eligible_count( $settings );
			$calculated     = (int) ceil( $total_eligible * ( $quota_value / 100 ) );
			$quota_value    = min( $calculated, self::MAX_POSTS_PER_DAY );
		}

		// Apply hard maximum
		$quota_value = min( $quota_value, self::MAX_POSTS_PER_DAY );

		// Subtract already republished today
		$already_done = $this->repository->get_today_republish_count();
		$remaining    = $quota_value - $already_done;

		return max( 0, $remaining );
	}

	/**
	 * Get total count of eligible posts (for percentage calculation).
	 *
	 * @since    1.0.0
	 * @param    array<string, mixed> $settings  Plugin settings.
	 */
	public function get_total_eligible_count( array $settings ): int {
		$post_types           = $settings['enabled_post_types'] ?? [ 'post' ];
		$min_age_days         = $settings['minimum_age_days'] ?? 30;
		$category_filter_type = $settings['category_filter_type'] ?? 'none';
		$category_filter_ids  = $settings['category_filter_ids'] ?? [];

		if ( empty( $post_types ) ) {
			return 0;
		}

		$min_timestamp = strtotime( "-{$min_age_days} days" );
		$min_date      = false !== $min_timestamp ? wp_date( 'Y-m-d H:i:s', $min_timestamp ) : wp_date( 'Y-m-d H:i:s' );

		// Build post types placeholder
		$post_types_escaped = array_map( 'esc_sql', $post_types );
		$post_types_in      = "'" . implode( "','", array_filter( $post_types_escaped, 'is_string' ) ) . "'";

		$query = "SELECT COUNT(DISTINCT p.ID)
			FROM {$this->wpdb->posts} p";

		$where = [
			"p.post_status = 'publish'",
			"p.post_type IN ({$post_types_in})",
			$this->wpdb->prepare( 'p.post_date < %s', $min_date ),
		];

		// Add category filter if applicable
		$category_join = '';
		if ( 'none' !== $category_filter_type && ! empty( $category_filter_ids ) ) {
			$category_join      = $this->build_category_join();
			$category_condition = $this->build_category_condition(
				$category_filter_type,
				$category_filter_ids
			);
			if ( $category_condition ) {
				$where[] = $category_condition;
			}
		}

		$where_clause = implode( ' AND ', $where );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $this->wpdb->get_var( "SELECT COUNT(DISTINCT p.ID) FROM {$this->wpdb->posts} p {$category_join} WHERE {$where_clause}" );
	}

	/**
	 * Get statistics about eligible posts by type.
	 *
	 * @since    1.0.0
	 * @return   array<string, int>  Post counts by type.
	 */
	public function get_eligible_stats(): array {
		$settings     = $this->repository->get_settings();
		$post_types   = $settings['enabled_post_types'] ?? [ 'post' ];
		$min_age_days = $settings['minimum_age_days'] ?? 30;
		$stats        = [];

		if ( empty( $post_types ) ) {
			return $stats;
		}

		$min_timestamp = strtotime( "-{$min_age_days} days" );
		$min_date      = false !== $min_timestamp ? wp_date( 'Y-m-d H:i:s', $min_timestamp ) : wp_date( 'Y-m-d H:i:s' );

		foreach ( $post_types as $post_type ) {
			$query = $this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->wpdb->posts}
				WHERE post_status = 'publish'
				AND post_type = %s
				AND post_date < %s",
				$post_type,
				$min_date
			);

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$stats[ $post_type ] = (int) $this->wpdb->get_var( $query );
		}

		return $stats;
	}

	/**
	 * Execute the main post selection query.
	 *
	 * @since    1.0.0
	 * @param    array<int, string> $post_types            Enabled post types.
	 * @param    int                $min_age_days          Minimum age in days.
	 * @param    string             $category_filter_type  Filter type: none, whitelist, blacklist.
	 * @param    array<int, int>    $category_filter_ids   Category IDs to filter.
	 * @param    array<int, int>    $excluded_ids          Post IDs to exclude.
	 * @param    int                $limit                 Maximum posts to return.
	 * @param    bool               $maintain_order        Whether to maintain chronological order.
	 * @return   array<int, object>
	 */
	private function execute_selection_query(
		array $post_types,
		int $min_age_days,
		string $category_filter_type,
		array $category_filter_ids,
		array $excluded_ids,
		int $limit,
		bool $maintain_order
	): array {
		if ( empty( $post_types ) || $limit <= 0 ) {
			return [];
		}

		$min_timestamp = strtotime( "-{$min_age_days} days" );
		$min_date      = false !== $min_timestamp ? wp_date( 'Y-m-d H:i:s', $min_timestamp ) : wp_date( 'Y-m-d H:i:s' );

		// Build post types IN clause
		$post_types_escaped = array_map( 'esc_sql', $post_types );
		$post_types_in      = "'" . implode( "','", array_filter( $post_types_escaped, 'is_string' ) ) . "'";

		// Base query - select oldest posts first (cross-type priority)
		$query = "SELECT DISTINCT p.ID, p.post_title, p.post_date, p.post_type, p.post_status
			FROM {$this->wpdb->posts} p";

		$where = [
			"p.post_status = 'publish'",
			"p.post_type IN ({$post_types_in})",
			$this->wpdb->prepare( 'p.post_date < %s', $min_date ),
		];

		// Add category filter
		$category_join = '';
		if ( 'none' !== $category_filter_type && ! empty( $category_filter_ids ) ) {
			$category_join      = $this->build_category_join();
			$category_condition = $this->build_category_condition(
				$category_filter_type,
				$category_filter_ids
			);
			if ( $category_condition ) {
				$where[] = $category_condition;
			}
		}

		// Exclude already republished posts
		if ( ! empty( $excluded_ids ) ) {
			$excluded_ids_escaped = array_map( 'absint', $excluded_ids );
			$excluded_in          = implode( ',', $excluded_ids_escaped );
			$where[]              = "p.ID NOT IN ({$excluded_in})";
		}

		$where_clause = implode( ' AND ', $where );

		// Order by oldest first (absolute oldest across all enabled post types)
		$order_by = 'ORDER BY p.post_date ASC';

		$full_query = "{$query} {$category_join} WHERE {$where_clause} {$order_by} LIMIT %d";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results = $this->wpdb->get_results(
			$this->wpdb->prepare( $full_query, $limit )
		);

		if ( empty( $results ) ) {
			return [];
		}

		// If maintaining chronological order, posts are already sorted by post_date ASC
		// This means they will be republished in the same relative order they were originally published
		if ( ! $maintain_order ) {
			// Shuffle for random order
			shuffle( $results );
		}

		return $results;
	}

	/**
	 * Build the JOIN clause for category filtering.
	 *
	 * @since    1.0.0
	 */
	private function build_category_join(): string {
		return "LEFT JOIN {$this->wpdb->term_relationships} tr ON p.ID = tr.object_id
			LEFT JOIN {$this->wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id";
	}

	/**
	 * Build the WHERE condition for category filtering.
	 *
	 * @since    1.0.0
	 * @param    string          $filter_type  Filter type: whitelist or blacklist.
	 * @param    array<int, int> $category_ids Category IDs.
	 */
	private function build_category_condition( string $filter_type, array $category_ids ): string {
		if ( empty( $category_ids ) ) {
			return '';
		}

		$category_ids_escaped = array_map( 'absint', $category_ids );
		$category_in          = implode( ',', $category_ids_escaped );

		if ( 'whitelist' === $filter_type ) {
			// Only include posts in these categories
			return "tt.taxonomy = 'category' AND tt.term_id IN ({$category_in})";
		}

		if ( 'blacklist' === $filter_type ) {
			// Exclude posts in these categories
			// This requires a subquery to properly exclude
			return "p.ID NOT IN (
				SELECT tr2.object_id
				FROM {$this->wpdb->term_relationships} tr2
				JOIN {$this->wpdb->term_taxonomy} tt2 ON tr2.term_taxonomy_id = tt2.term_taxonomy_id
				WHERE tt2.taxonomy = 'category' AND tt2.term_id IN ({$category_in})
			)";
		}

		return '';
	}

	/**
	 * Check if a specific post is eligible for republishing.
	 *
	 * @since    1.0.0
	 * @param    int                       $post_id   The post ID to check.
	 * @param    array<string, mixed>|null $settings  Optional settings override.
	 */
	public function is_post_eligible( int $post_id, ?array $settings = null ): bool {
		$settings = $settings ?? $this->repository->get_settings();
		$post     = get_post( $post_id );

		if ( ! $post || 'publish' !== $post->post_status ) {
			return false;
		}

		// Check post type
		$enabled_types = $settings['enabled_post_types'] ?? [ 'post' ];
		if ( ! in_array( $post->post_type, $enabled_types, true ) ) {
			return false;
		}

		// Check minimum age
		$min_age_days = $settings['minimum_age_days'] ?? 30;
		$min_date     = strtotime( "-{$min_age_days} days" );
		$post_date    = strtotime( $post->post_date );

		if ( $post_date >= $min_date ) {
			return false;
		}

		// Check if already republished today
		if ( $this->repository->was_republished_today( $post_id ) ) {
			return false;
		}

		// Check category filter
		$filter_type = $settings['category_filter_type'] ?? 'none';
		$filter_ids  = $settings['category_filter_ids'] ?? [];

		if ( 'none' !== $filter_type && ! empty( $filter_ids ) ) {
			$post_categories = wp_get_post_categories( $post_id );

			// Handle WP_Error case
			if ( is_wp_error( $post_categories ) ) {
				$post_categories = [];
			}

			if ( 'whitelist' === $filter_type ) {
				// Post must be in at least one whitelisted category
				if ( empty( array_intersect( $post_categories, $filter_ids ) ) ) {
					return false;
				}
			} elseif ( 'blacklist' === $filter_type ) {
				// Post must not be in any blacklisted category
				if ( ! empty( array_intersect( $post_categories, $filter_ids ) ) ) {
					return false;
				}
			}
		}

		return true;
	}
}
