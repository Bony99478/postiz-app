<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fires on deactivation. Deliberately leaves the protected directory,
 * uploaded files and stored options in place so counts and files survive
 * a temporary deactivation (e.g. during an update).
 */
class RDM_Deactivator {

	public static function deactivate() {
		wp_clear_scheduled_hook( RDM_CRON_FLUSH_HOOK );
		wp_clear_scheduled_hook( RDM_CRON_PRUNE_HOOK );
		flush_rewrite_rules();
	}
}
