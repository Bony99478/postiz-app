<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Verifies and serves download requests.
 *
 * Requests hit `?rdm_download=<post_id>&expires=<ts>&sig=<hmac>` (rewritten
 * to `/download/<post_id>/...` when pretty permalinks are on). The handler:
 *   1. Validates the HMAC signature and expiry so links can't be forged or
 *      reused past their lifetime.
 *   2. Applies a per-IP rate limit.
 *   3. Streams the file with HTTP Range support (resumable downloads,
 *      download managers, video/audio seeking) or delegates to the web
 *      server via X-Sendfile / X-Accel-Redirect when configured, which is
 *      the recommended mode at high volume since PHP stops being in the
 *      hot path for the actual file bytes.
 */
class RDM_Download_Handler {

	public static function init() {
		add_filter( 'query_vars', array( __CLASS__, 'register_query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_handle_request' ) );
	}

	public static function register_query_vars( $vars ) {
		$vars[] = 'rdm_download';
		$vars[] = 'rdm_expires';
		$vars[] = 'rdm_sig';
		return $vars;
	}

	/**
	 * Builds a signed, optionally time-limited, download URL for a post.
	 */
	public static function get_download_url( $post_id ) {
		$settings = rdm_get_settings();
		$post_id  = (int) $post_id;

		$expiry_override = get_post_meta( $post_id, '_rdm_expiry_override', true );
		$ttl              = '' !== $expiry_override ? (int) $expiry_override : (int) $settings['link_expiry_seconds'];
		$expires          = $ttl > 0 ? time() + $ttl : 0;

		$args = array( 'rdm_download' => $post_id );

		if ( ! empty( $settings['require_signed_links'] ) ) {
			$args['rdm_expires'] = $expires;
			$args['rdm_sig']     = self::sign( $post_id, $expires );
		}

		return add_query_arg( $args, home_url( '/' ) );
	}

	private static function sign( $post_id, $expires ) {
		return hash_hmac( 'sha256', $post_id . '|' . $expires, self::secret() );
	}

	private static function secret() {
		return wp_salt( 'auth' ) . 'rdm-download-manager';
	}

	private static function verify_signature( $post_id, $expires, $sig ) {
		if ( ! hash_equals( self::sign( $post_id, $expires ), (string) $sig ) ) {
			return false;
		}
		if ( $expires > 0 && time() > (int) $expires ) {
			return false;
		}
		return true;
	}

	public static function maybe_handle_request() {
		$post_id = (int) get_query_var( 'rdm_download' );
		if ( $post_id <= 0 ) {
			return;
		}

		self::handle_request( $post_id );
		exit;
	}

	private static function handle_request( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post || RDM_POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			self::deny( 404, __( 'Download not found.', 'rapid-download-manager' ) );
		}

		$settings = rdm_get_settings();

		if ( ! empty( $settings['require_signed_links'] ) ) {
			$expires = get_query_var( 'rdm_expires' );
			$sig     = get_query_var( 'rdm_sig' );

			if ( '' === $sig || ! self::verify_signature( $post_id, $expires, $sig ) ) {
				self::deny( 403, __( 'This download link is invalid or has expired. Please refresh the page and try again.', 'rapid-download-manager' ) );
			}
		}

		$rate_check = RDM_Rate_Limiter::check( RDM_Rate_Limiter::get_client_ip() );
		if ( is_wp_error( $rate_check ) ) {
			self::deny( 429, $rate_check->get_error_message() );
		}

		/**
		 * Fires right before a download is served. Useful for custom
		 * gating (login walls, paywalls, etc).
		 *
		 * @param int $post_id
		 */
		do_action( 'rdm_before_serve_download', $post_id );

		$external_url = get_post_meta( $post_id, '_rdm_external_url', true );
		if ( $external_url ) {
			RDM_Stats::record_download( $post_id );
			wp_redirect( $external_url, 302 ); // phpcs:ignore WordPress.Security.SafeRedirect
			return;
		}

		$file_path = get_post_meta( $post_id, '_rdm_file_path', true );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			self::deny( 404, __( 'The requested file is missing.', 'rapid-download-manager' ) );
		}

		// Defend against path traversal even though the path is DB-stored,
		// not user-supplied - cheap insurance against a corrupted value.
		$protected_base = trailingslashit( wp_upload_dir()['basedir'] ) . RDM_PROTECTED_DIR_NAME;
		$real_path       = realpath( $file_path );
		$real_base       = realpath( $protected_base );
		if ( ! $real_path || ! $real_base || 0 !== strpos( $real_path, $real_base ) ) {
			self::deny( 403, __( 'Access denied.', 'rapid-download-manager' ) );
		}

		RDM_Stats::record_download( $post_id );

		$file_name = get_post_meta( $post_id, '_rdm_file_name', true );
		$mime      = get_post_meta( $post_id, '_rdm_file_mime', true );

		self::stream_file( $real_path, $file_name ? $file_name : basename( $real_path ), $mime ? $mime : 'application/octet-stream', $settings );
	}

	private static function deny( $status, $message ) {
		wp_die( esc_html( $message ), esc_html__( 'Download error', 'rapid-download-manager' ), array( 'response' => $status ) );
	}

	/**
	 * Serves the file, delegating to the web server when possible and
	 * falling back to a Range-aware PHP stream.
	 */
	private static function stream_file( $path, $file_name, $mime, $settings ) {
		$filesize = filesize( $path );

		nocache_headers();
		header( 'Content-Type: ' . $mime );
		header( 'Content-Disposition: attachment; filename="' . self::sanitize_header_filename( $file_name ) . '"' );
		header( 'Content-Description: File Transfer' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Accept-Ranges: bytes' );

		$mode = isset( $settings['delivery_mode'] ) ? $settings['delivery_mode'] : 'php';

		if ( 'xsendfile' === $mode ) {
			// Apache/LiteSpeed mod_xsendfile / mod_x-sendfile. The web
			// server takes over delivery entirely once it sees this header.
			header( trim( $settings['xsendfile_header'] ) . ': ' . $path );
			return;
		}

		if ( 'xaccel' === $mode ) {
			// Nginx X-Accel-Redirect. Requires an `internal` location block
			// mapping xaccel_internal_prefix to the protected directory -
			// see the plugin README for the server config snippet.
			$internal_path = rtrim( $settings['xaccel_internal_prefix'], '/' ) . '/' . basename( $path );
			header( 'X-Accel-Redirect: ' . $internal_path );
			return;
		}

		self::php_stream_with_range( $path, $filesize, $settings );
	}

	private static function sanitize_header_filename( $file_name ) {
		$file_name = preg_replace( '/[\r\n"]/', '', $file_name );
		return $file_name;
	}

	/**
	 * Streams the file directly from PHP, honoring the Range header so
	 * download managers and paused/resumed downloads work correctly.
	 */
	private static function php_stream_with_range( $path, $filesize, $settings ) {
		$chunk_size = max( 8, (int) $settings['chunk_size_kb'] ) * 1024;

		$start = 0;
		$end   = $filesize - 1;
		$http_status = 200;

		if ( isset( $_SERVER['HTTP_RANGE'] ) ) {
			$range = sanitize_text_field( wp_unslash( $_SERVER['HTTP_RANGE'] ) );
			if ( preg_match( '/bytes=(\d*)-(\d*)/', $range, $matches ) ) {
				if ( '' !== $matches[1] ) {
					$start = (int) $matches[1];
				}
				if ( '' !== $matches[2] ) {
					$end = (int) $matches[2];
				}
				$end = min( $end, $filesize - 1 );

				if ( $start > $end || $start >= $filesize ) {
					header( 'HTTP/1.1 416 Range Not Satisfiable' );
					header( 'Content-Range: bytes */' . $filesize );
					exit;
				}

				$http_status = 206;
			}
		}

		$length = $end - $start + 1;

		if ( 206 === $http_status ) {
			header( 'HTTP/1.1 206 Partial Content' );
			header( "Content-Range: bytes {$start}-{$end}/{$filesize}" );
		}
		header( 'Content-Length: ' . $length );

		// Get out of PHP/WordPress's way for the actual byte-shoveling.
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 );
		}
		ignore_user_abort( true );

		$handle = fopen( $path, 'rb' );
		if ( ! $handle ) {
			self::deny( 500, __( 'Unable to read file.', 'rapid-download-manager' ) );
		}

		fseek( $handle, $start );
		$bytes_remaining = $length;

		while ( $bytes_remaining > 0 && ! feof( $handle ) ) {
			if ( connection_aborted() ) {
				break;
			}

			$read_size = min( $chunk_size, $bytes_remaining );
			echo fread( $handle, $read_size ); // phpcs:ignore WordPress.Security.EscapeOutput
			flush();

			$bytes_remaining -= $read_size;
		}

		fclose( $handle );
	}
}
