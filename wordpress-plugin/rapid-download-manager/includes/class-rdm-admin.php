<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin dashboard widget + settings page. Kept deliberately dependency
 * free (no bundled JS framework) so the plugin has no build step.
 */
class RDM_Admin {

	const SETTINGS_GROUP = 'rdm_settings_group';
	const SETTINGS_SLUG  = 'rdm_settings';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	public static function enqueue_assets( $hook ) {
		if ( strpos( $hook, 'rapid-download-manager' ) === false ) {
			return;
		}
		wp_enqueue_style( 'rdm-admin', RDM_PLUGIN_URL . 'admin/css/admin.css', array(), RDM_VERSION );
	}

	public static function add_menu() {
		add_submenu_page(
			'edit.php?post_type=' . RDM_POST_TYPE,
			__( 'Download Stats', 'rapid-download-manager' ),
			__( 'Stats', 'rapid-download-manager' ),
			'manage_options',
			'rapid-download-manager-stats',
			array( __CLASS__, 'render_stats_page' )
		);

		add_submenu_page(
			'edit.php?post_type=' . RDM_POST_TYPE,
			__( 'Download Settings', 'rapid-download-manager' ),
			__( 'Settings', 'rapid-download-manager' ),
			'manage_options',
			'rapid-download-manager-settings',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	public static function register_settings() {
		register_setting( self::SETTINGS_GROUP, self::SETTINGS_SLUG, array( __CLASS__, 'sanitize_settings' ) );
	}

	public static function sanitize_settings( $input ) {
		$settings = rdm_get_settings();

		$settings['require_signed_links'] = ! empty( $input['require_signed_links'] ) ? 1 : 0;
		$settings['link_expiry_seconds']  = max( 0, (int) ( $input['link_expiry_seconds'] ?? 0 ) );
		$settings['rate_limit_count']     = max( 1, (int) ( $input['rate_limit_count'] ?? 30 ) );
		$settings['rate_limit_window']    = max( 1, (int) ( $input['rate_limit_window'] ?? 60 ) );
		$settings['chunk_size_kb']        = max( 8, (int) ( $input['chunk_size_kb'] ?? 512 ) );

		$mode                     = isset( $input['delivery_mode'] ) ? sanitize_key( $input['delivery_mode'] ) : 'php';
		$settings['delivery_mode'] = in_array( $mode, array( 'php', 'xsendfile', 'xaccel' ), true ) ? $mode : 'php';

		$settings['xsendfile_header']       = isset( $input['xsendfile_header'] ) ? sanitize_text_field( $input['xsendfile_header'] ) : 'X-Sendfile';
		$settings['xaccel_internal_prefix'] = isset( $input['xaccel_internal_prefix'] ) ? '/' . trim( sanitize_text_field( $input['xaccel_internal_prefix'] ), '/' ) . '/' : '/rdm-internal/';

		return $settings;
	}

	public static function render_stats_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$today = RDM_Stats::get_downloads_today();
		$top   = RDM_Stats::get_top_downloads( 15 );
		?>
		<div class="wrap rdm-admin">
			<h1><?php esc_html_e( 'Download Stats', 'rapid-download-manager' ); ?></h1>

			<div class="rdm-stat-cards">
				<div class="rdm-stat-card">
					<span class="rdm-stat-card__value"><?php echo esc_html( number_format_i18n( $today ) ); ?></span>
					<span class="rdm-stat-card__label"><?php esc_html_e( 'Downloads today', 'rapid-download-manager' ); ?></span>
				</div>
			</div>

			<h2><?php esc_html_e( 'Top Downloads', 'rapid-download-manager' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'File', 'rapid-download-manager' ); ?></th>
						<th><?php esc_html_e( 'Downloads', 'rapid-download-manager' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $top ) ) : ?>
						<tr><td colspan="2"><?php esc_html_e( 'No downloads recorded yet.', 'rapid-download-manager' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $top as $post_id => $count ) : ?>
							<tr>
								<td><a href="<?php echo esc_url( get_edit_post_link( $post_id ) ); ?>"><?php echo esc_html( get_the_title( $post_id ) ); ?></a></td>
								<td><?php echo esc_html( number_format_i18n( $count ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
			<p class="description"><?php esc_html_e( 'Counts are batched and flushed every few minutes, so very recent downloads may not appear immediately.', 'rapid-download-manager' ); ?></p>
		</div>
		<?php
	}

	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = rdm_get_settings();
		?>
		<div class="wrap rdm-admin">
			<h1><?php esc_html_e( 'Rapid Download Manager Settings', 'rapid-download-manager' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( self::SETTINGS_GROUP ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Require signed links', 'rapid-download-manager' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="rdm_settings[require_signed_links]" value="1" <?php checked( $settings['require_signed_links'], 1 ); ?> />
								<?php esc_html_e( 'Reject download requests without a valid signature (recommended).', 'rapid-download-manager' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="rdm_link_expiry"><?php esc_html_e( 'Link expiry (seconds)', 'rapid-download-manager' ); ?></label></th>
						<td>
							<input type="number" min="0" id="rdm_link_expiry" name="rdm_settings[link_expiry_seconds]" value="<?php echo esc_attr( $settings['link_expiry_seconds'] ); ?>" class="regular-text" />
							<p class="description"><?php esc_html_e( '0 = links never expire. Individual downloads can override this value.', 'rapid-download-manager' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="rdm_rate_limit_count"><?php esc_html_e( 'Rate limit', 'rapid-download-manager' ); ?></label></th>
						<td>
							<input type="number" min="1" id="rdm_rate_limit_count" name="rdm_settings[rate_limit_count]" value="<?php echo esc_attr( $settings['rate_limit_count'] ); ?>" class="small-text" />
							<?php esc_html_e( 'requests per', 'rapid-download-manager' ); ?>
							<input type="number" min="1" name="rdm_settings[rate_limit_window]" value="<?php echo esc_attr( $settings['rate_limit_window'] ); ?>" class="small-text" />
							<?php esc_html_e( 'seconds, per IP address.', 'rapid-download-manager' ); ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="rdm_delivery_mode"><?php esc_html_e( 'Delivery mode', 'rapid-download-manager' ); ?></label></th>
						<td>
							<select id="rdm_delivery_mode" name="rdm_settings[delivery_mode]">
								<option value="php" <?php selected( $settings['delivery_mode'], 'php' ); ?>><?php esc_html_e( 'PHP stream (works everywhere, default)', 'rapid-download-manager' ); ?></option>
								<option value="xsendfile" <?php selected( $settings['delivery_mode'], 'xsendfile' ); ?>><?php esc_html_e( 'X-Sendfile (Apache/LiteSpeed with mod_xsendfile)', 'rapid-download-manager' ); ?></option>
								<option value="xaccel" <?php selected( $settings['delivery_mode'], 'xaccel' ); ?>><?php esc_html_e( 'X-Accel-Redirect (Nginx)', 'rapid-download-manager' ); ?></option>
							</select>
							<p class="description">
								<?php esc_html_e( 'At high traffic, X-Sendfile or X-Accel-Redirect are strongly recommended: the web server streams the file directly and PHP is freed immediately after the access checks. See the plugin README for the required server configuration.', 'rapid-download-manager' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="rdm_xsendfile_header"><?php esc_html_e( 'X-Sendfile header name', 'rapid-download-manager' ); ?></label></th>
						<td><input type="text" id="rdm_xsendfile_header" name="rdm_settings[xsendfile_header]" value="<?php echo esc_attr( $settings['xsendfile_header'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="rdm_xaccel_prefix"><?php esc_html_e( 'X-Accel-Redirect internal path prefix', 'rapid-download-manager' ); ?></label></th>
						<td><input type="text" id="rdm_xaccel_prefix" name="rdm_settings[xaccel_internal_prefix]" value="<?php echo esc_attr( $settings['xaccel_internal_prefix'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="rdm_chunk_size"><?php esc_html_e( 'PHP stream chunk size (KB)', 'rapid-download-manager' ); ?></label></th>
						<td><input type="number" min="8" id="rdm_chunk_size" name="rdm_settings[chunk_size_kb]" value="<?php echo esc_attr( $settings['chunk_size_kb'] ); ?>" class="small-text" /></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
