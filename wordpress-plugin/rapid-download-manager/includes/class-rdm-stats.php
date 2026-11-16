<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Download counting, designed to survive ~100k downloads/day without
 * hammering the database.
 *
 * Every completed download only touches a transient (backed by an object
 * cache such as Redis/Memcached when one is configured, or the options
 * table otherwise). A WP-Cron job flushes the accumulated counters into
 * post meta every few minutes, so at most one DB write happens per
 * download post per flush interval - not one write per download.
 */
class RDM_Stats {

	const PENDING_OPTION = 'rdm_pending_counts';

	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'register_cron_schedule' ) );
		add_action( RDM_CRON_FLUSH_HOOK, array( __CLASS__, 'flush_pending_counts' ) );
	}

	public static function register_cron_schedule( $schedules ) {
		$schedules['rdm_five_minutes'] = array(
			'interval' => 300,
			'display'  => __( 'Every 5 Minutes', 'rapid-download-manager' ),
		);
		return $schedules;
	}

	/**
	 * Cheap, non-blocking increment. Never writes to post meta directly.
	 *
	 * @param int $post_id Download post ID.
	 */
	public static function record_download( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return;
		}

		$pending = get_transient( self::PENDING_OPTION );
		if ( ! is_array( $pending ) ) {
			$pending = array();
		}

		$pending[ $post_id ] = isset( $pending[ $post_id ] ) ? $pending[ $post_id ] + 1 : 1;

		// Long TTL: the cron job is the real flush trigger, this is only a safety net.
		set_transient( self::PENDING_OPTION, $pending, HOUR_IN_SECONDS );

		// Keep a rolling "downloads today" figure for the dashboard widget.
		$today_key = 'rdm_downloads_' . gmdate( 'Y-m-d' );
		$today     = (int) get_transient( $today_key );
		set_transient( $today_key, $today + 1, DAY_IN_SECONDS );
	}

	/**
	 * Cron callback: moves the accumulated in-memory/cache counters into
	 * durable post meta with one update per download, at most every 5
	 * minutes - regardless of how many downloads happened in between.
	 */
	public static function flush_pending_counts() {
		$pending = get_transient( self::PENDING_OPTION );
		if ( empty( $pending ) || ! is_array( $pending ) ) {
			return;
		}

		// Clear immediately so downloads recorded while we flush start a fresh batch.
		delete_transient( self::PENDING_OPTION );

		foreach ( $pending as $post_id => $count ) {
			$post_id = (int) $post_id;
			$count   = (int) $count;
			if ( $post_id <= 0 || $count <= 0 ) {
				continue;
			}

			$current = (int) get_post_meta( $post_id, '_rdm_download_count', true );
			update_post_meta( $post_id, '_rdm_download_count', $current + $count );
		}
	}

	/**
	 * Total count including anything not yet flushed from cache.
	 */
	public static function get_count( $post_id ) {
		$post_id = (int) $post_id;
		$stored  = (int) get_post_meta( $post_id, '_rdm_download_count', true );

		$pending = get_transient( self::PENDING_OPTION );
		if ( is_array( $pending ) && isset( $pending[ $post_id ] ) ) {
			$stored += (int) $pending[ $post_id ];
		}

		return $stored;
	}

	public static function get_downloads_today() {
		$today_key = 'rdm_downloads_' . gmdate( 'Y-m-d' );
		return (int) get_transient( $today_key );
	}

	/**
	 * @return array<int,int> post_id => count, sorted descending, for the admin dashboard.
	 */
	public static function get_top_downloads( $limit = 10 ) {
		$posts = get_posts(
			array(
				'post_type'      => RDM_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'fields'         => 'ids',
				'meta_key'       => '_rdm_download_count',
				'orderby'        => 'meta_value_num',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);

		$results = array();
		foreach ( $posts as $post_id ) {
			$results[ $post_id ] = self::get_count( $post_id );
		}
		arsort( $results );

		return array_slice( $results, 0, $limit, true );
	}
}
