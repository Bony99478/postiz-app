<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Simple sliding-window-ish rate limiter backed by transients, so it
 * benefits from an object cache automatically when one is configured.
 * Protects the download endpoint from scraping/abuse without adding a
 * database table.
 */
class RDM_Rate_Limiter {

	/**
	 * @return true|WP_Error True when the request is allowed, WP_Error (429) otherwise.
	 */
	public static function check( $identifier ) {
		$settings = rdm_get_settings();
		$limit    = max( 1, (int) $settings['rate_limit_count'] );
		$window   = max( 1, (int) $settings['rate_limit_window'] );

		$key   = 'rdm_rl_' . md5( $identifier );
		$count = get_transient( $key );

		if ( false === $count ) {
			set_transient( $key, 1, $window );
			return true;
		}

		if ( (int) $count >= $limit ) {
			return new WP_Error(
				'rdm_rate_limited',
				__( 'Too many download requests. Please try again shortly.', 'rapid-download-manager' ),
				array( 'status' => 429 )
			);
		}

		// Transients don't expose atomic increment; a small race here only
		// costs an occasional extra allowed request, which is an acceptable
		// trade-off for avoiding a lock/table at this volume.
		set_transient( $key, (int) $count + 1, $window );
		return true;
	}

	/**
	 * Best-effort real client IP, aware of common reverse-proxy headers.
	 * The result is only used for rate limiting / hashing, never trusted
	 * for security decisions.
	 */
	public static function get_client_ip() {
		$headers = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );

		foreach ( $headers as $header ) {
			if ( empty( $_SERVER[ $header ] ) ) {
				continue;
			}
			$value = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
			$ips   = array_map( 'trim', explode( ',', $value ) );
			foreach ( $ips as $ip ) {
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '0.0.0.0';
	}
}
