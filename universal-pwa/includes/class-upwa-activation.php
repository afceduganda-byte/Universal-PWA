<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Activation/deactivation: flush rewrite rules so /manifest.json and
 * /sw.js resolve immediately, without requiring a manual permalink
 * resave.
 */
class UPWA_Activation {

	public static function activate() {
		// Register the rewrite rules before flushing so they're actually
		// included in the flushed rule set on this same request.
		$manifest = new UPWA_Manifest();
		$manifest->add_rewrite_rule();

		$sw = new UPWA_Service_Worker();
		$sw->add_rewrite_rule();

		if ( false === get_option( UPWA_OPTION_KEY ) ) {
			add_option( UPWA_OPTION_KEY, array() );
		}

		flush_rewrite_rules();

		// Picked up by UPWA_Settings::maybe_redirect_to_setup() on the very
		// next admin request, so activating the plugin drops the admin
		// straight onto the setup dashboard instead of the plugins list.
		set_transient( 'upwa_activation_redirect', true, 30 );
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}
}
