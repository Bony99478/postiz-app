<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the `rdm_download` custom post type, its category taxonomy,
 * the "Download File" meta box, and handles securely moving uploaded
 * files into the protected directory created on activation.
 */
class RDM_Post_Type {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'init', array( __CLASS__, 'register_taxonomy' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post_' . RDM_POST_TYPE, array( __CLASS__, 'save_meta_boxes' ), 10, 2 );
		add_filter( 'manage_' . RDM_POST_TYPE . '_posts_columns', array( __CLASS__, 'admin_columns' ) );
		add_action( 'manage_' . RDM_POST_TYPE . '_posts_custom_column', array( __CLASS__, 'render_admin_column' ), 10, 2 );
	}

	public static function register_post_type() {
		$labels = array(
			'name'               => __( 'Downloads', 'rapid-download-manager' ),
			'singular_name'      => __( 'Download', 'rapid-download-manager' ),
			'add_new_item'       => __( 'Add New Download', 'rapid-download-manager' ),
			'edit_item'          => __( 'Edit Download', 'rapid-download-manager' ),
			'new_item'           => __( 'New Download', 'rapid-download-manager' ),
			'view_item'          => __( 'View Download', 'rapid-download-manager' ),
			'search_items'       => __( 'Search Downloads', 'rapid-download-manager' ),
			'not_found'          => __( 'No downloads found', 'rapid-download-manager' ),
			'not_found_in_trash' => __( 'No downloads found in Trash', 'rapid-download-manager' ),
			'menu_name'          => __( 'Downloads', 'rapid-download-manager' ),
		);

		register_post_type(
			RDM_POST_TYPE,
			array(
				'labels'          => $labels,
				'public'          => true,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'menu_icon'       => 'dashicons-download',
				'supports'        => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
				'has_archive'     => true,
				'rewrite'         => array( 'slug' => 'downloads' ),
				'show_in_rest'    => true,
				'capability_type' => 'post',
			)
		);
	}

	public static function register_taxonomy() {
		register_taxonomy(
			RDM_TAXONOMY,
			array( RDM_POST_TYPE ),
			array(
				'label'        => __( 'Download Categories', 'rapid-download-manager' ),
				'hierarchical' => true,
				'public'       => true,
				'show_in_rest' => true,
				'rewrite'      => array( 'slug' => 'download-category' ),
			)
		);
	}

	public static function add_meta_boxes() {
		add_meta_box(
			'rdm_file_meta_box',
			__( 'Download File', 'rapid-download-manager' ),
			array( __CLASS__, 'render_file_meta_box' ),
			RDM_POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'rdm_stats_meta_box',
			__( 'Download Stats', 'rapid-download-manager' ),
			array( __CLASS__, 'render_stats_meta_box' ),
			RDM_POST_TYPE,
			'side',
			'default'
		);
	}

	public static function render_file_meta_box( $post ) {
		wp_nonce_field( 'rdm_save_file_meta', 'rdm_file_meta_nonce' );

		$file_path    = get_post_meta( $post->ID, '_rdm_file_path', true );
		$file_name    = get_post_meta( $post->ID, '_rdm_file_name', true );
		$file_size    = get_post_meta( $post->ID, '_rdm_file_size', true );
		$external_url = get_post_meta( $post->ID, '_rdm_external_url', true );
		$expiry       = get_post_meta( $post->ID, '_rdm_expiry_override', true );

		?>
		<p>
			<label for="rdm_file_upload"><strong><?php esc_html_e( 'Upload a file', 'rapid-download-manager' ); ?></strong></label><br />
			<input type="file" id="rdm_file_upload" name="rdm_file_upload" />
			<?php if ( $file_name ) : ?>
				<p class="description">
					<?php
					printf(
						/* translators: 1: file name, 2: human readable file size */
						esc_html__( 'Current file: %1$s (%2$s). Uploading a new file will replace it.', 'rapid-download-manager' ),
						esc_html( $file_name ),
						esc_html( size_format( (int) $file_size ) )
					);
					?>
				</p>
			<?php endif; ?>
		</p>
		<p>
			<label for="rdm_external_url"><strong><?php esc_html_e( 'Or serve from an external URL (CDN / S3 / object storage)', 'rapid-download-manager' ); ?></strong></label><br />
			<input type="url" id="rdm_external_url" name="rdm_external_url" class="widefat" value="<?php echo esc_attr( $external_url ); ?>" placeholder="https://cdn.example.com/file.zip" />
			<span class="description"><?php esc_html_e( 'When set, visitors are redirected here instead of the file being streamed by WordPress. Recommended for very large files or extreme traffic.', 'rapid-download-manager' ); ?></span>
		</p>
		<p>
			<label for="rdm_expiry_override"><strong><?php esc_html_e( 'Link expiry override (seconds, blank = use global setting)', 'rapid-download-manager' ); ?></strong></label><br />
			<input type="number" min="0" id="rdm_expiry_override" name="rdm_expiry_override" value="<?php echo esc_attr( $expiry ); ?>" />
		</p>
		<?php if ( $file_path && ! $external_url ) : ?>
			<p><em><?php esc_html_e( 'This file is stored in the protected directory and cannot be reached by a direct URL.', 'rapid-download-manager' ); ?></em></p>
		<?php endif; ?>
		<?php
	}

	public static function render_stats_meta_box( $post ) {
		$count = RDM_Stats::get_count( $post->ID );
		echo '<p style="font-size:24px;font-weight:600;margin:0;">' . esc_html( number_format_i18n( $count ) ) . '</p>';
		echo '<p class="description">' . esc_html__( 'Total downloads (recent activity may take a few minutes to appear).', 'rapid-download-manager' ) . '</p>';

		if ( get_post_meta( $post->ID, '_rdm_file_path', true ) || get_post_meta( $post->ID, '_rdm_external_url', true ) ) {
			$url = RDM_Download_Handler::get_download_url( $post->ID );
			echo '<p><strong>' . esc_html__( 'Shortcode', 'rapid-download-manager' ) . ':</strong><br /><code>[rdm_download id="' . esc_html( $post->ID ) . '"]</code></p>';
			echo '<p><strong>' . esc_html__( 'Direct link (signed)', 'rapid-download-manager' ) . ':</strong><br /><input type="text" readonly class="widefat" value="' . esc_attr( $url ) . '" onclick="this.select();" /></p>';
		}
	}

	public static function save_meta_boxes( $post_id, $post ) {
		if ( ! isset( $_POST['rdm_file_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rdm_file_meta_nonce'] ) ), 'rdm_save_file_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// External URL.
		if ( isset( $_POST['rdm_external_url'] ) ) {
			$external_url = esc_url_raw( wp_unslash( $_POST['rdm_external_url'] ) );
			if ( $external_url ) {
				update_post_meta( $post_id, '_rdm_external_url', $external_url );
			} else {
				delete_post_meta( $post_id, '_rdm_external_url' );
			}
		}

		// Expiry override.
		if ( isset( $_POST['rdm_expiry_override'] ) ) {
			$expiry = sanitize_text_field( wp_unslash( $_POST['rdm_expiry_override'] ) );
			if ( '' === $expiry ) {
				delete_post_meta( $post_id, '_rdm_expiry_override' );
			} else {
				update_post_meta( $post_id, '_rdm_expiry_override', max( 0, (int) $expiry ) );
			}
		}

		// File upload.
		if ( ! empty( $_FILES['rdm_file_upload']['name'] ) ) {
			self::handle_file_upload( $post_id );
		}
	}

	/**
	 * Moves an uploaded file into the protected directory (never the
	 * public uploads tree) and stores its path/metadata on the post.
	 */
	private static function handle_file_upload( $post_id ) {
		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$overrides = array(
			'test_form' => false,
			'action'    => 'editpost',
		);

		add_filter( 'upload_dir', array( __CLASS__, 'filter_upload_dir' ) );
		$movefile = wp_handle_upload( $_FILES['rdm_file_upload'], $overrides );
		remove_filter( 'upload_dir', array( __CLASS__, 'filter_upload_dir' ) );

		if ( isset( $movefile['error'] ) ) {
			return;
		}

		if ( empty( $movefile['file'] ) ) {
			return;
		}

		// Remove any previous file for this post before saving the new path.
		$old_path = get_post_meta( $post_id, '_rdm_file_path', true );
		if ( $old_path && file_exists( $old_path ) ) {
			@unlink( $old_path );
		}

		update_post_meta( $post_id, '_rdm_file_path', $movefile['file'] );
		update_post_meta( $post_id, '_rdm_file_name', sanitize_file_name( basename( $movefile['file'] ) ) );
		update_post_meta( $post_id, '_rdm_file_size', filesize( $movefile['file'] ) );
		update_post_meta( $post_id, '_rdm_file_mime', $movefile['type'] );
		delete_post_meta( $post_id, '_rdm_external_url' );
	}

	/**
	 * Redirects wp_handle_upload() into wp-content/uploads/rdm-protected/
	 * instead of the normal, publicly reachable, date-based folder.
	 */
	public static function filter_upload_dir( $dirs ) {
		$dirs['subdir'] = '/' . RDM_PROTECTED_DIR_NAME;
		$dirs['path']   = $dirs['basedir'] . $dirs['subdir'];
		$dirs['url']    = $dirs['baseurl'] . $dirs['subdir'];
		return $dirs;
	}

	public static function admin_columns( $columns ) {
		$columns['rdm_downloads'] = __( 'Downloads', 'rapid-download-manager' );
		$columns['rdm_size']      = __( 'File Size', 'rapid-download-manager' );
		return $columns;
	}

	public static function render_admin_column( $column, $post_id ) {
		if ( 'rdm_downloads' === $column ) {
			echo esc_html( number_format_i18n( RDM_Stats::get_count( $post_id ) ) );
		} elseif ( 'rdm_size' === $column ) {
			$size = get_post_meta( $post_id, '_rdm_file_size', true );
			echo $size ? esc_html( size_format( (int) $size ) ) : '&#8212;';
		}
	}
}
