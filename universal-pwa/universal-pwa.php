<?php
/**
 * Plugin Name:       Universal PWA
 * Plugin URI:        https://countrysightstours.com
 * Description:       Turns any WordPress site into an installable Progressive Web App. Dynamic manifest, minimal service worker, and a smart custom install-prompt banner. No offline caching, no push notifications.
 * Version:           1.0.0
 * Requires at least: 5.6
 * Requires PHP:      7.2
 * Author:            Universal PWA
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       universal-pwa
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'UPWA_VERSION', '1.0.0' );
define( 'UPWA_PLUGIN_FILE', __FILE__ );
define( 'UPWA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'UPWA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'UPWA_OPTION_KEY', 'upwa_options' );

require_once UPWA_PLUGIN_DIR . 'includes/class-upwa-icon.php';
require_once UPWA_PLUGIN_DIR . 'includes/class-upwa-manifest.php';
require_once UPWA_PLUGIN_DIR . 'includes/class-upwa-service-worker.php';
require_once UPWA_PLUGIN_DIR . 'includes/class-upwa-frontend.php';
require_once UPWA_PLUGIN_DIR . 'includes/class-upwa-activation.php';
require_once UPWA_PLUGIN_DIR . 'admin/class-upwa-settings.php';
require_once UPWA_PLUGIN_DIR . 'includes/class-universal-pwa.php';

register_activation_hook( UPWA_PLUGIN_FILE, array( 'UPWA_Activation', 'activate' ) );
register_deactivation_hook( UPWA_PLUGIN_FILE, array( 'UPWA_Activation', 'deactivate' ) );

Universal_PWA::instance();
