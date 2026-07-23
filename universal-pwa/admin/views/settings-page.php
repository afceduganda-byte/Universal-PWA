<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * @var array $options
 * @var array $manifest
 * @var array|null $icon_192
 * @var array|null $icon_512
 * @var array|null $icon_mask
 */
?>
<div class="wrap upwa-settings-wrap">

	<div class="upwa-header">
		<div class="upwa-header-icon">
			<img src="<?php echo $icon_192 ? esc_url( $icon_192['url'] ) : ''; ?>" width="40" height="40" alt="">
		</div>
		<div>
			<h1><?php esc_html_e( 'Universal PWA', 'universal-pwa' ); ?></h1>
			<p class="upwa-header-tagline"><?php esc_html_e( 'Make this site installable as an app: home-screen icon, app name, and an install prompt for visitors.', 'universal-pwa' ); ?></p>
		</div>
	</div>

	<div class="upwa-layout">
		<div class="upwa-card upwa-card-main">
			<form action="options.php" method="post">
				<?php
				settings_fields( UPWA_Settings::GROUP_NAME );
				do_settings_sections( UPWA_Settings::PAGE_SLUG );
				submit_button( __( 'Save changes', 'universal-pwa' ) );
				?>
			</form>
		</div>

		<div class="upwa-card upwa-card-preview">
			<h2><?php esc_html_e( 'Preview', 'universal-pwa' ); ?></h2>

			<p class="upwa-preview-label"><?php esc_html_e( 'App icon', 'universal-pwa' ); ?></p>
			<div class="upwa-preview-icons">
				<div class="upwa-preview-icon">
					<img src="<?php echo $icon_192 ? esc_url( $icon_192['url'] ) : ''; ?>" width="56" height="56" alt="">
					<span><?php esc_html_e( 'Home screen', 'universal-pwa' ); ?></span>
				</div>
				<div class="upwa-preview-icon">
					<img src="<?php echo $icon_mask ? esc_url( $icon_mask['url'] ) : ''; ?>" width="56" height="56" alt="">
					<span><?php esc_html_e( 'Maskable', 'universal-pwa' ); ?></span>
				</div>
			</div>

			<p class="upwa-preview-label"><?php esc_html_e( 'Install prompt', 'universal-pwa' ); ?></p>
			<div class="upwa-banner-preview" id="upwa-banner-preview" style="--upwa-theme: <?php echo esc_attr( $options['theme_color'] ); ?>; --upwa-bg: <?php echo esc_attr( $options['background_color'] ); ?>;">
				<img id="upwa-banner-preview-icon" src="<?php echo $icon_192 ? esc_url( $icon_192['url'] ) : ''; ?>" width="36" height="36" alt="">
				<div class="upwa-banner-preview-text">
					<strong id="upwa-banner-preview-name"><?php echo esc_html( $options['app_name'] ); ?></strong>
					<span id="upwa-banner-preview-desc"><?php echo esc_html( $options['banner_text'] ); ?></span>
				</div>
				<span class="upwa-banner-preview-btn"><?php esc_html_e( 'Install App', 'universal-pwa' ); ?></span>
			</div>

			<p class="description upwa-preview-note"><?php esc_html_e( 'Updates automatically when you save — no cache clearing needed.', 'universal-pwa' ); ?></p>

			<details class="upwa-advanced">
				<summary><?php esc_html_e( 'Advanced: view manifest.json', 'universal-pwa' ); ?></summary>
				<pre id="upwa-manifest-preview" class="upwa-manifest-preview"><?php echo esc_html( wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>
				<p class="description">
					<?php
					printf(
						/* translators: %s: manifest.json URL */
						esc_html__( 'Served live at %s', 'universal-pwa' ),
						'<code>' . esc_html( home_url( '/manifest.json' ) ) . '</code>'
					);
					?>
				</p>
			</details>
		</div>
	</div>
</div>
