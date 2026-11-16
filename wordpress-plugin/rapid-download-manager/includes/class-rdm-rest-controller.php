<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Small REST API surface for the admin dashboard's stats widget and for
 * external integrations that want download counts without scraping HTML.
 */
class RDM_REST_Controller {

	const NAMESPACE = 'rdm/v1';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/downloads/(?P<id>\d+)/count',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_count' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id' => array(
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/stats/summary',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_summary' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	public static function get_count( WP_REST_Request $request ) {
		$post_id = (int) $request->get_param( 'id' );
		$post    = get_post( $post_id );

		if ( ! $post || RDM_POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'rdm_not_found', __( 'Download not found.', 'rapid-download-manager' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response(
			array(
				'id'    => $post_id,
				'count' => RDM_Stats::get_count( $post_id ),
			)
		);
	}

	public static function get_summary( WP_REST_Request $request ) {
		return rest_ensure_response(
			array(
				'downloads_today' => RDM_Stats::get_downloads_today(),
				'top_downloads'   => RDM_Stats::get_top_downloads( 10 ),
			)
		);
	}
}
