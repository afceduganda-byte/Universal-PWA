<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serves a dynamically generated manifest.json at the site root and
 * links it (plus iOS home-screen meta) from wp_head.
 */
class UPWA_Manifest {

	const QUERY_VAR = 'upwa_manifest';

	public function __construct() {
		add_action( 'init', array( $this, 'add_rewrite_rule' ) );
		add_filter( 'query_vars', array( $this, 'add_query_var' ) );
		add_action( 'template_redirect', array( $this, 'maybe_render' ) );
		add_action( 'wp_head', array( $this, 'print_head_tags' ), 1 );
	}

	public function add_rewrite_rule() {
		add_rewrite_rule( '^manifest\.json$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
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

		nocache_headers();
		header( 'Content-Type: application/manifest+json; charset=utf-8' );
		echo wp_json_encode( $this->build_manifest( $options ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		exit;
	}

	/**
	 * Builds the manifest data structure. Public/static so the admin
	 * settings screen can reuse it for the live preview.
	 */
	public static function build_manifest( $options = null ) {
		if ( null === $options ) {
			$options = Universal_PWA::get_options();
		}

		$icon_192     = UPWA_Icon::get_icon_data( 192, false );
		$icon_512     = UPWA_Icon::get_icon_data( 512, false );
		$icon_512_mask = UPWA_Icon::get_icon_data( 512, true );

		$icons = array();

		if ( $icon_192 ) {
			$icons[] = array(
				'src'     => $icon_192['url'],
				'sizes'   => $icon_192['width'] . 'x' . $icon_192['height'],
				'type'    => 'image/png',
				'purpose' => 'any',
			);
		}

		if ( $icon_512 ) {
			$icons[] = array(
				'src'     => $icon_512['url'],
				'sizes'   => $icon_512['width'] . 'x' . $icon_512['height'],
				'type'    => 'image/png',
				'purpose' => 'any',
			);
		}

		if ( $icon_512_mask ) {
			$icons[] = array(
				'src'     => $icon_512_mask['url'],
				'sizes'   => $icon_512_mask['width'] . 'x' . $icon_512_mask['height'],
				'type'    => 'image/png',
				'purpose' => 'maskable',
			);
		}

		return array(
			'name'             => $options['app_name'],
			'short_name'       => $options['short_name'],
			'start_url'        => home_url( '/', 'relative' ),
			'scope'            => home_url( '/', 'relative' ),
			'display'          => 'standalone',
			'background_color' => $options['background_color'],
			'theme_color'      => $options['theme_color'],
			'icons'            => $icons,
		);
	}

	public function print_head_tags() {
		$options = Universal_PWA::get_options();

		if ( empty( $options['enabled'] ) ) {
			return;
		}

		$icon_192 = UPWA_Icon::get_icon_data( 192, false );

		echo "\n" . '<link rel="manifest" href="' . esc_url( home_url( '/manifest.json' ) ) . '">' . "\n";
		echo '<meta name="theme-color" content="' . esc_attr( $options['theme_color'] ) . '">' . "\n";

		// iOS Safari ignores manifest.json entirely; these tags are what
		// actually control the Add to Home Screen icon/title/status bar there.
		echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
		echo '<meta name="apple-mobile-web-app-status-bar-style" content="default">' . "\n";
		echo '<meta name="apple-mobile-web-app-title" content="' . esc_attr( $options['short_name'] ) . '">' . "\n";

		if ( $icon_192 ) {
			echo '<link rel="apple-touch-icon" href="' . esc_url( $icon_192['url'] ) . '">' . "\n";
		}
	}
}
