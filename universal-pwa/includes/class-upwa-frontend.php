<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueues the front-end service worker registration and install
 * prompt scripts/styles, and hands them the current settings.
 */
class UPWA_Frontend {

	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function enqueue() {
		$options = Universal_PWA::get_options();

		if ( empty( $options['enabled'] ) ) {
			return;
		}

		wp_enqueue_script(
			'upwa-sw-register',
			UPWA_PLUGIN_URL . 'public/js/upwa-sw-register.js',
			array(),
			UPWA_VERSION,
			true
		);

		wp_enqueue_style(
			'upwa-banner',
			UPWA_PLUGIN_URL . 'public/css/upwa-banner.css',
			array(),
			UPWA_VERSION
		);

		wp_enqueue_script(
			'upwa-install',
			UPWA_PLUGIN_URL . 'public/js/upwa-install.js',
			array(),
			UPWA_VERSION,
			true
		);

		$icon = UPWA_Icon::get_icon_data( 192, false );

		wp_localize_script(
			'upwa-install',
			'upwaSettings',
			array(
				'enabled'         => true,
				'appName'         => $options['app_name'],
				'shortName'       => $options['short_name'],
				'logoUrl'         => $icon ? $icon['url'] : '',
				'themeColor'      => $options['theme_color'],
				'backgroundColor' => $options['background_color'],
				'bannerText'      => $options['banner_text'],
				'installLabel'    => __( 'Install App', 'universal-pwa' ),
				'waitingLabel'    => __( 'Install App', 'universal-pwa' ),
				'dismissLabel'    => __( 'Dismiss', 'universal-pwa' ),
				'iosInstruction'  => $this->ios_instruction_html(),
				'bannerDelay'     => 6000,
			)
		);

		echo '<style id="upwa-inline-vars">:root{--upwa-theme:' . esc_attr( $options['theme_color'] ) . ';--upwa-bg:' . esc_attr( $options['background_color'] ) . ';}</style>';
	}

	/**
	 * Trusted, plugin-authored instructional markup (share icon + copy)
	 * for the iOS banner. Not user input, so it's injected as-is on the
	 * front end rather than escaped like the admin-supplied banner text.
	 */
	private function ios_instruction_html() {
		$share_icon = '<svg class="upwa-share-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l-5 5h3v9h4V7h3l-5-5zM5 22h14a2 2 0 0 0 2-2v-9a2 2 0 0 0-2-2h-3v2h3v9H5v-9h3V9H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2z"/></svg>';

		return sprintf(
			/* translators: %s is the iOS Share icon (graphic, no text). */
			esc_html__( 'Tap %s Share, then "Add to Home Screen".', 'universal-pwa' ),
			$share_icon
		);
	}
}
