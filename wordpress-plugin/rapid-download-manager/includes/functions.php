<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns plugin settings merged with defaults so every caller can rely on
 * every key existing, even right after a partial upgrade.
 *
 * @return array
 */
function rdm_get_settings() {
	$defaults = array(
		'require_signed_links'   => 1,
		'link_expiry_seconds'    => 3600,
		'rate_limit_count'       => 30,
		'rate_limit_window'      => 60,
		'stats_flush_interval'   => 300,
		'delivery_mode'          => 'php',
		'xsendfile_header'       => 'X-Sendfile',
		'xaccel_internal_prefix' => '/rdm-internal/',
		'chunk_size_kb'          => 512,
	);

	$stored = get_option( 'rdm_settings', array() );
	if ( ! is_array( $stored ) ) {
		$stored = array();
	}

	return wp_parse_args( $stored, $defaults );
}

/**
 * Cron callback: when no persistent object cache is active, transients
 * live in wp_options and only self-delete lazily on access. At high
 * download volume the rate-limiter can create many short-lived
 * `rdm_rl_*` transients; this proactively removes ones past their
 * expiry so the options table doesn't grow unbounded.
 */
function rdm_prune_rate_limit_data() {
	if ( wp_using_ext_object_cache() ) {
		// Backed by Redis/Memcached: expiry is handled by the cache itself.
		return;
	}

	global $wpdb;

	$timeout_prefix = $wpdb->esc_like( '_transient_timeout_rdm_rl_' ) . '%';

	// delete_transient() removes both the value row and the timeout row for
	// a given key in one call, so walking the timeout rows is enough to
	// clean up completely.
	$expired_keys = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d",
			$timeout_prefix,
			time()
		)
	);

	foreach ( $expired_keys as $timeout_key ) {
		$transient_key = str_replace( '_transient_timeout_', '', $timeout_key );
		delete_transient( $transient_key );
	}
}
add_action( RDM_CRON_PRUNE_HOOK, 'rdm_prune_rate_limit_data' );
