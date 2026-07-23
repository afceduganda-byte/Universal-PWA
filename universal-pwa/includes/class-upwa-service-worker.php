<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serves the minimal service worker from the site root (e.g. /sw.js)
 * via a rewrite rule. A service worker's scope defaults to the folder
 * it's served from, so serving it from inside /wp-content/plugins/...
 * would silently break installability for the whole site.
 */
class UPWA_Service_Worker {

	const QUERY_VAR = 'upwa_sw';

	public function __construct() {
		add_action( 'init', array( $this, 'add_rewrite_rule' ) );
		add_filter( 'query_vars', array( $this, 'add_query_var' ) );
		add_action( 'template_redirect', array( $this, 'maybe_render' ) );
	}

	public function add_rewrite_rule() {
		add_rewrite_rule( '^sw\.js$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
	}

	public function add_query_var( $vars ) {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	public function maybe_render() {
		if ( ! get_query_var( self::QUERY_VAR ) ) {
			return;
		}

		$options = Universal_PWA::get_options();

		if ( empty( $options['enabled'] ) ) {
			status_header( 404 );
			exit;
		}

		$source_file = UPWA_PLUGIN_DIR . 'public/js/upwa-sw-source.js';

		nocache_headers();
		header( 'Content-Type: application/javascript; charset=utf-8' );
		// Without this header a service worker served via a rewrite would
		// still default its max scope to the request path's directory.
		header( 'Service-Worker-Allowed: /' );

		if ( file_exists( $source_file ) ) {
			readfile( $source_file );
		}

		exit;
	}
}
