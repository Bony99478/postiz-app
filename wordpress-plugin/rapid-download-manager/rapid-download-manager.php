<?php
/**
 * Plugin Name:       Rapid Download Manager
 * Plugin URI:        https://github.com/Bony99478/postiz-app
 * Description:       High-volume file download manager for WordPress. Serves protected, signed, resumable downloads and is built to sustain roughly 100,000 downloads/day without overloading the database.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Rapid Download Manager
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       rapid-download-manager
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

// ---------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------
define( 'RDM_VERSION', '1.0.0' );
define( 'RDM_PLUGIN_FILE', __FILE__ );
define( 'RDM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RDM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'RDM_PROTECTED_DIR_NAME', 'rdm-protected' );
define( 'RDM_POST_TYPE', 'rdm_download' );
define( 'RDM_TAXONOMY', 'rdm_category' );
define( 'RDM_CRON_FLUSH_HOOK', 'rdm_flush_pending_counts' );
define( 'RDM_CRON_PRUNE_HOOK', 'rdm_prune_rate_limit_data' );

// ---------------------------------------------------------------------
// Includes
// ---------------------------------------------------------------------
require_once RDM_PLUGIN_DIR . 'includes/functions.php';
require_once RDM_PLUGIN_DIR . 'includes/class-rdm-stats.php';
require_once RDM_PLUGIN_DIR . 'includes/class-rdm-rate-limiter.php';
require_once RDM_PLUGIN_DIR . 'includes/class-rdm-post-type.php';
require_once RDM_PLUGIN_DIR . 'includes/class-rdm-download-handler.php';
require_once RDM_PLUGIN_DIR . 'includes/class-rdm-shortcode.php';
require_once RDM_PLUGIN_DIR . 'includes/class-rdm-block.php';
require_once RDM_PLUGIN_DIR . 'includes/class-rdm-rest-controller.php';

if ( is_admin() ) {
	require_once RDM_PLUGIN_DIR . 'includes/class-rdm-admin.php';
}

require_once RDM_PLUGIN_DIR . 'includes/class-rdm-activator.php';
require_once RDM_PLUGIN_DIR . 'includes/class-rdm-deactivator.php';

// ---------------------------------------------------------------------
// Activation / deactivation
// ---------------------------------------------------------------------
register_activation_hook( __FILE__, array( 'RDM_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'RDM_Deactivator', 'deactivate' ) );

// ---------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------
/**
 * Boots every plugin component. Runs on `plugins_loaded` so translations
 * and other plugins hooking earlier are not blocked.
 */
function rdm_bootstrap() {
	load_plugin_textdomain( 'rapid-download-manager', false, dirname( plugin_basename( RDM_PLUGIN_FILE ) ) . '/languages' );

	RDM_Post_Type::init();
	RDM_Download_Handler::init();
	RDM_Shortcode::init();
	RDM_Block::init();
	RDM_REST_Controller::init();
	RDM_Stats::init();

	if ( is_admin() ) {
		RDM_Admin::init();
	}
}
add_action( 'plugins_loaded', 'rdm_bootstrap' );
