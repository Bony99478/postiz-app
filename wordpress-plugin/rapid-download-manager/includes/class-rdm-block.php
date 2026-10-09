<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the "Download Button" Gutenberg block. Implemented as a
 * dynamic block (rendered server-side by the shortcode's logic) so it
 * always reflects the current signed URL and live download count, and so
 * no JS build tooling is required to ship the plugin.
 */
class RDM_Block {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_block' ) );
	}

	public static function register_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		wp_register_script(
			'rdm-block-editor',
			RDM_PLUGIN_URL . 'public/blocks/download-button/index.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render' ),
			RDM_VERSION,
			true
		);

		register_block_type(
			'rapid-download-manager/download-button',
			array(
				'editor_script'   => 'rdm-block-editor',
				'render_callback' => array( __CLASS__, 'render' ),
				'attributes'      => array(
					'downloadId' => array(
						'type'    => 'number',
						'default' => 0,
					),
					'text'       => array(
						'type'    => 'string',
						'default' => __( 'Download', 'rapid-download-manager' ),
					),
				),
			)
		);
	}

	public static function render( $attributes ) {
		return RDM_Shortcode::render(
			array(
				'id'   => isset( $attributes['downloadId'] ) ? $attributes['downloadId'] : 0,
				'text' => isset( $attributes['text'] ) ? $attributes['text'] : __( 'Download', 'rapid-download-manager' ),
			)
		);
	}
}
