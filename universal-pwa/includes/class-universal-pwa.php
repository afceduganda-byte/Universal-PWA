<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bootstraps the plugin: options helpers and wiring between the
 * manifest, service worker, frontend and admin components.
 */
class Universal_PWA {

	private static $instance = null;

	private $manifest;
	private $service_worker;
	private $frontend;
	private $settings;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->manifest       = new UPWA_Manifest();
		$this->service_worker = new UPWA_Service_Worker();
		$this->frontend       = new UPWA_Frontend();
		$this->settings       = new UPWA_Settings();

		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'update_option_' . UPWA_OPTION_KEY, array( $this, 'on_options_updated' ), 10, 2 );
		add_action( 'add_option_' . UPWA_OPTION_KEY, array( $this, 'on_options_added' ), 10, 2 );
	}

	public function load_textdomain() {
		load_plugin_textdomain( 'universal-pwa', false, dirname( plugin_basename( UPWA_PLUGIN_FILE ) ) . '/languages' );
	}

	/**
	 * Regenerate cached icons whenever settings change so the manifest
	 * and installed icon reflect new logo/colors without manual cache busting.
	 */
	public function on_options_updated( $old_value, $new_value ) {
		UPWA_Icon::regenerate_all( $new_value );
	}

	public function on_options_added( $option, $value ) {
		UPWA_Icon::regenerate_all( $value );
	}

	/**
	 * Returns the plugin options merged with defaults.
	 *
	 * @return array
	 */
	public static function get_options() {
		$defaults = array(
			'enabled'          => 1,
			'app_name'         => get_bloginfo( 'name' ),
			'short_name'       => self::default_short_name(),
			'logo_id'          => 0,
			'theme_color'      => '#0b0b0b',
			'background_color' => '#ffffff',
			'banner_text'      => __( 'Install our app for quick access and a better experience.', 'universal-pwa' ),
		);

		$saved = get_option( UPWA_OPTION_KEY, array() );

		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return wp_parse_args( $saved, $defaults );
	}

	private static function default_short_name() {
		$name = get_bloginfo( 'name' );
		if ( strlen( $name ) <= 12 ) {
			return $name;
		}
		return substr( $name, 0, 12 );
	}
}
