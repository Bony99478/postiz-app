<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fires once when the plugin is activated: creates the protected upload
 * directory, seeds default options and schedules the cron jobs that keep
 * download counting cheap at high volume.
 */
class RDM_Activator {

	public static function activate() {
		self::create_protected_directory();
		self::add_default_options();
		self::schedule_cron();

		// Custom post type must be registered before we flush rewrite rules.
		RDM_Post_Type::register_post_type();
		RDM_Post_Type::register_taxonomy();
		flush_rewrite_rules();
	}

	/**
	 * Creates wp-content/uploads/rdm-protected/ and locks it down so files
	 * placed there can never be served directly by the web server - every
	 * download must go through rdm_download_handler().
	 */
	private static function create_protected_directory() {
		$upload_dir = wp_upload_dir();
		$protected  = trailingslashit( $upload_dir['basedir'] ) . RDM_PROTECTED_DIR_NAME;

		if ( ! file_exists( $protected ) ) {
			wp_mkdir_p( $protected );
		}

		// Apache / LiteSpeed.
		$htaccess = trailingslashit( $protected ) . '.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			$rules = "Order deny,allow\nDeny from all\n\n<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n";
			file_put_contents( $htaccess, $rules );
		}

		// IIS.
		$web_config = trailingslashit( $protected ) . 'web.config';
		if ( ! file_exists( $web_config ) ) {
			$config = "<configuration>\n\t<system.webServer>\n\t\t<authorization>\n\t\t\t<deny users=\"*\" />\n\t\t</authorization>\n\t</system.webServer>\n</configuration>\n";
			file_put_contents( $web_config, $config );
		}

		$index = trailingslashit( $protected ) . 'index.php';
		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, "<?php\n// Silence is golden.\n" );
		}
	}

	private static function add_default_options() {
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

		if ( false === get_option( 'rdm_settings' ) ) {
			add_option( 'rdm_settings', $defaults );
		}
	}

	private static function schedule_cron() {
		if ( ! wp_next_scheduled( RDM_CRON_FLUSH_HOOK ) ) {
			wp_schedule_event( time() + 300, 'rdm_five_minutes', RDM_CRON_FLUSH_HOOK );
		}
		if ( ! wp_next_scheduled( RDM_CRON_PRUNE_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', RDM_CRON_PRUNE_HOOK );
		}
	}
}
