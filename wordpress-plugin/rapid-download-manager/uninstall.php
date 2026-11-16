<?php
/**
 * Fires only when the plugin is deleted from the Plugins screen (never on
 * simple deactivation). Removes options and scheduled events. Uploaded
 * files and `rdm_download` posts are left untouched so an accidental
 * uninstall never destroys content - remove them manually if truly no
 * longer needed.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'rdm_settings' );

wp_clear_scheduled_hook( 'rdm_flush_pending_counts' );
wp_clear_scheduled_hook( 'rdm_prune_rate_limit_data' );

delete_transient( 'rdm_pending_counts' );
