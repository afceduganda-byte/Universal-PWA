<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings -> Universal PWA admin page, built on the WordPress
 * Settings API (which handles the nonce/referer check for us via
 * settings_fields()) with explicit sanitization/escaping throughout.
 */
class UPWA_Settings {

	const PAGE_SLUG  = 'universal-pwa';
	const GROUP_NAME = 'upwa_settings_group';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'maybe_redirect_to_setup' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( UPWA_PLUGIN_FILE ), array( $this, 'add_action_links' ) );
	}

	/**
	 * A dedicated top-level menu item (rather than tucking it under
	 * Settings) so the app's setup dashboard is immediately visible in
	 * the admin sidebar.
	 */
	public function add_settings_page() {
		add_menu_page(
			__( 'Universal PWA', 'universal-pwa' ),
			__( 'Universal PWA', 'universal-pwa' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' ),
			'dashicons-smartphone',
			80
		);
	}

	/**
	 * Adds a prominent "Set Up App" link on the Plugins list row, so
	 * setting up the app doesn't depend on already knowing where the
	 * menu lives.
	 */
	public function add_action_links( $links ) {
		$setup_link = '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) . '"><strong>' . esc_html__( 'Set Up App', 'universal-pwa' ) . '</strong></a>';
		array_unshift( $links, $setup_link );
		return $links;
	}

	/**
	 * Sends the admin straight to the setup dashboard right after
	 * activation (but not on bulk/network activation), so "installing
	 * the plugin" and "setting up the app" feel like one step.
	 */
	public function maybe_redirect_to_setup() {
		if ( ! get_transient( 'upwa_activation_redirect' ) ) {
			return;
		}

		delete_transient( 'upwa_activation_redirect' );

		if ( wp_doing_ajax() || isset( $_GET['activate-multi'] ) || is_network_admin() ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
		exit;
	}

	public function enqueue_admin_assets( $hook ) {
		if ( 'toplevel_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'upwa-admin', UPWA_PLUGIN_URL . 'admin/css/admin.css', array(), UPWA_VERSION );

		wp_enqueue_script(
			'upwa-admin',
			UPWA_PLUGIN_URL . 'admin/js/admin.js',
			array( 'jquery', 'wp-color-picker' ),
			UPWA_VERSION,
			true
		);

		wp_localize_script(
			'upwa-admin',
			'upwaAdmin',
			array(
				'chooseLogoTitle'  => __( 'Choose logo', 'universal-pwa' ),
				'chooseLogoButton' => __( 'Use this image', 'universal-pwa' ),
			)
		);
	}

	public function register_settings() {
		register_setting(
			self::GROUP_NAME,
			UPWA_OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => array(),
			)
		);

		add_settings_section( 'upwa_general', __( 'General', 'universal-pwa' ), '__return_false', self::PAGE_SLUG );
		add_settings_section( 'upwa_appearance', __( 'Appearance', 'universal-pwa' ), '__return_false', self::PAGE_SLUG );
		add_settings_section( 'upwa_banner', __( 'Install Banner', 'universal-pwa' ), '__return_false', self::PAGE_SLUG );

		add_settings_field( 'enabled', __( 'Enable Universal PWA', 'universal-pwa' ), array( $this, 'field_enabled' ), self::PAGE_SLUG, 'upwa_general' );
		add_settings_field( 'app_name', __( 'App name', 'universal-pwa' ), array( $this, 'field_app_name' ), self::PAGE_SLUG, 'upwa_general' );
		add_settings_field( 'short_name', __( 'Short name', 'universal-pwa' ), array( $this, 'field_short_name' ), self::PAGE_SLUG, 'upwa_general' );

		add_settings_field( 'logo', __( 'Logo / icon', 'universal-pwa' ), array( $this, 'field_logo' ), self::PAGE_SLUG, 'upwa_appearance' );
		add_settings_field( 'theme_color', __( 'Theme color', 'universal-pwa' ), array( $this, 'field_theme_color' ), self::PAGE_SLUG, 'upwa_appearance' );
		add_settings_field( 'background_color', __( 'Background color', 'universal-pwa' ), array( $this, 'field_background_color' ), self::PAGE_SLUG, 'upwa_appearance' );

		add_settings_field( 'banner_text', __( 'Banner text', 'universal-pwa' ), array( $this, 'field_banner_text' ), self::PAGE_SLUG, 'upwa_banner' );
	}

	public function sanitize( $input ) {
		$existing = Universal_PWA::get_options();

		if ( ! is_array( $input ) ) {
			$input = array();
		}

		$output = array();

		$output['enabled'] = ! empty( $input['enabled'] ) ? 1 : 0;

		$output['app_name'] = isset( $input['app_name'] ) && '' !== trim( $input['app_name'] )
			? sanitize_text_field( $input['app_name'] )
			: $existing['app_name'];

		$output['short_name'] = isset( $input['short_name'] ) && '' !== trim( $input['short_name'] )
			? sanitize_text_field( $input['short_name'] )
			: $existing['short_name'];

		$output['logo_id'] = isset( $input['logo_id'] ) ? absint( $input['logo_id'] ) : 0;

		$theme_color = isset( $input['theme_color'] ) ? sanitize_hex_color( $input['theme_color'] ) : '';
		$output['theme_color'] = $theme_color ? $theme_color : $existing['theme_color'];

		$bg_color = isset( $input['background_color'] ) ? sanitize_hex_color( $input['background_color'] ) : '';
		$output['background_color'] = $bg_color ? $bg_color : $existing['background_color'];

		$output['banner_text'] = isset( $input['banner_text'] ) && '' !== trim( $input['banner_text'] )
			? sanitize_text_field( $input['banner_text'] )
			: $existing['banner_text'];

		return $output;
	}

	private function name( $key ) {
		return UPWA_OPTION_KEY . '[' . $key . ']';
	}

	public function field_enabled() {
		$options = Universal_PWA::get_options();
		printf(
			'<label><input type="checkbox" name="%1$s" value="1" %2$s> %3$s</label>',
			esc_attr( $this->name( 'enabled' ) ),
			checked( ! empty( $options['enabled'] ), true, false ),
			esc_html__( 'Make this site installable as a PWA', 'universal-pwa' )
		);
	}

	public function field_app_name() {
		$options = Universal_PWA::get_options();
		printf(
			'<input type="text" class="regular-text upwa-preview-field" data-preview="name" id="upwa_app_name" name="%1$s" value="%2$s" placeholder="%3$s">',
			esc_attr( $this->name( 'app_name' ) ),
			esc_attr( $options['app_name'] ),
			esc_attr( get_bloginfo( 'name' ) )
		);
		echo '<p class="description">' . esc_html__( 'Full app name shown on the install prompt and splash screen. Defaults to the site title.', 'universal-pwa' ) . '</p>';
	}

	public function field_short_name() {
		$options = Universal_PWA::get_options();
		printf(
			'<input type="text" class="regular-text upwa-preview-field" data-preview="short_name" id="upwa_short_name" name="%1$s" value="%2$s" maxlength="30">',
			esc_attr( $this->name( 'short_name' ) ),
			esc_attr( $options['short_name'] )
		);
		echo '<p class="description">' . esc_html__( 'Shown under the home-screen icon. Keep it short (~12 characters).', 'universal-pwa' ) . '</p>';
	}

	public function field_logo() {
		$options = Universal_PWA::get_options();
		$logo_id = (int) $options['logo_id'];
		$thumb   = $logo_id ? wp_get_attachment_image_url( $logo_id, 'thumbnail' ) : '';

		echo '<div class="upwa-logo-field">';
		printf(
			'<img id="upwa_logo_preview" src="%1$s" style="%2$s" width="80" height="80" alt="">',
			esc_url( $thumb ),
			$thumb ? '' : 'display:none;'
		);
		printf(
			'<input type="hidden" id="upwa_logo_id" name="%1$s" value="%2$s">',
			esc_attr( $this->name( 'logo_id' ) ),
			esc_attr( $logo_id )
		);
		echo '<p>';
		echo '<button type="button" class="button button-primary" id="upwa_choose_logo">' . esc_html__( 'Upload app icon', 'universal-pwa' ) . '</button> ';
		echo '<button type="button" class="button-link" id="upwa_remove_logo" style="' . ( $logo_id ? '' : 'display:none;' ) . '">' . esc_html__( 'Remove', 'universal-pwa' ) . '</button>';
		echo '</p>';
		echo '<p class="description">' . esc_html__( 'Recommended: a square PNG or JPG, at least 512×512px. We automatically generate every size the install prompt needs (192×192, 512×512, and a maskable version) from it.', 'universal-pwa' ) . '</p>';
		echo '<p class="description">' . esc_html__( 'Left empty, it falls back to your WordPress Site Icon (Customizer → Site Identity), then your site favicon.', 'universal-pwa' ) . '</p>';
		echo '</div>';
	}

	public function field_theme_color() {
		$options = Universal_PWA::get_options();
		printf(
			'<input type="text" class="upwa-color-field upwa-preview-field" data-preview="theme_color" name="%1$s" value="%2$s">',
			esc_attr( $this->name( 'theme_color' ) ),
			esc_attr( $options['theme_color'] )
		);
		echo '<p class="description">' . esc_html__( 'Browser toolbar / status bar color and the install button color.', 'universal-pwa' ) . '</p>';
	}

	public function field_background_color() {
		$options = Universal_PWA::get_options();
		printf(
			'<input type="text" class="upwa-color-field upwa-preview-field" data-preview="background_color" name="%1$s" value="%2$s">',
			esc_attr( $this->name( 'background_color' ) ),
			esc_attr( $options['background_color'] )
		);
		echo '<p class="description">' . esc_html__( 'Splash screen background and the padding color used for the maskable icon.', 'universal-pwa' ) . '</p>';
	}

	public function field_banner_text() {
		$options = Universal_PWA::get_options();
		printf(
			'<textarea class="large-text upwa-preview-field" data-preview="banner_text" rows="3" name="%1$s">%2$s</textarea>',
			esc_attr( $this->name( 'banner_text' ) ),
			esc_textarea( $options['banner_text'] )
		);
		echo '<p class="description">' . esc_html__( 'Shown in the custom install banner (Android/desktop) and, combined with the Share instructions, on iOS.', 'universal-pwa' ) . '</p>';
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$options  = Universal_PWA::get_options();
		$manifest = UPWA_Manifest::build_manifest( $options );
		$icon_192 = UPWA_Icon::get_icon_data( 192, false );
		$icon_512 = UPWA_Icon::get_icon_data( 512, false );
		$icon_mask = UPWA_Icon::get_icon_data( 512, true );

		include UPWA_PLUGIN_DIR . 'admin/views/settings-page.php';
	}
}
