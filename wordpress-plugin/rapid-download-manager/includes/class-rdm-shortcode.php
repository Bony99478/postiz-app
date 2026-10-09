<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [rdm_download] shortcode - renders a download button/link for a
 * `rdm_download` post, with a signed URL generated fresh on every render
 * so cached pages never leak a stale or reusable link past its expiry.
 */
class RDM_Shortcode {

	public static function init() {
		add_shortcode( 'rdm_download', array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	public static function enqueue_assets() {
		wp_register_style( 'rdm-public', RDM_PLUGIN_URL . 'public/css/public.css', array(), RDM_VERSION );
	}

	public static function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'    => 0,
				'text'  => __( 'Download', 'rapid-download-manager' ),
				'class' => '',
			),
			$atts,
			'rdm_download'
		);

		$post_id = (int) $atts['id'];
		$post    = get_post( $post_id );

		if ( ! $post || RDM_POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			return '';
		}

		wp_enqueue_style( 'rdm-public' );

		$url   = RDM_Download_Handler::get_download_url( $post_id );
		$count = RDM_Stats::get_count( $post_id );
		$size  = get_post_meta( $post_id, '_rdm_file_size', true );

		$classes = 'rdm-download-button';
		if ( ! empty( $atts['class'] ) ) {
			$classes .= ' ' . sanitize_html_class( $atts['class'] );
		}

		ob_start();
		?>
		<a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $classes ); ?>" rel="nofollow">
			<span class="rdm-download-button__text"><?php echo esc_html( $atts['text'] ); ?></span>
			<?php if ( $size ) : ?>
				<span class="rdm-download-button__meta"><?php echo esc_html( size_format( (int) $size ) ); ?></span>
			<?php endif; ?>
		</a>
		<?php if ( apply_filters( 'rdm_show_download_count', true, $post_id ) ) : ?>
			<span class="rdm-download-count">
				<?php
				printf(
					/* translators: %s: number of downloads */
					esc_html( _n( '%s download', '%s downloads', $count, 'rapid-download-manager' ) ),
					esc_html( number_format_i18n( $count ) )
				);
				?>
			</span>
		<?php endif; ?>
		<?php
		return trim( ob_get_clean() );
	}
}
