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
	<h1><?php esc_html_e( 'Universal PWA', 'universal-pwa' ); ?></h1>
	<p><?php esc_html_e( 'Turns this site into an installable Progressive Web App: a live manifest, a minimal service worker, and a smart install-prompt banner. No offline caching, no push notifications.', 'universal-pwa' ); ?></p>

	<div class="upwa-columns">
		<div class="upwa-column-main">
			<form action="options.php" method="post">
				<?php
				settings_fields( UPWA_Settings::GROUP_NAME );
				do_settings_sections( UPWA_Settings::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>

		<div class="upwa-column-side">
			<div class="upwa-preview-box">
				<h2><?php esc_html_e( 'Live preview', 'universal-pwa' ); ?></h2>

				<div class="upwa-preview-icons">
					<div class="upwa-preview-icon">
						<img src="<?php echo $icon_192 ? esc_url( $icon_192['url'] ) : ''; ?>" width="48" height="48" alt="">
						<span>192&times;192</span>
					</div>
					<div class="upwa-preview-icon">
						<img src="<?php echo $icon_512 ? esc_url( $icon_512['url'] ) : ''; ?>" width="48" height="48" alt="">
						<span>512&times;512</span>
					</div>
					<div class="upwa-preview-icon">
						<img src="<?php echo $icon_mask ? esc_url( $icon_mask['url'] ) : ''; ?>" width="48" height="48" alt="">
						<span><?php esc_html_e( 'Maskable', 'universal-pwa' ); ?></span>
					</div>
				</div>
				<p class="description"><?php esc_html_e( 'Icons regenerate automatically when you save.', 'universal-pwa' ); ?></p>

				<h3><?php esc_html_e( 'Install banner', 'universal-pwa' ); ?></h3>
				<div class="upwa-banner-preview" id="upwa-banner-preview" style="--upwa-theme: <?php echo esc_attr( $options['theme_color'] ); ?>; --upwa-bg: <?php echo esc_attr( $options['background_color'] ); ?>;">
					<img id="upwa-banner-preview-icon" src="<?php echo $icon_192 ? esc_url( $icon_192['url'] ) : ''; ?>" width="32" height="32" alt="">
					<div class="upwa-banner-preview-text">
						<strong id="upwa-banner-preview-name"><?php echo esc_html( $options['app_name'] ); ?></strong>
						<span id="upwa-banner-preview-desc"><?php echo esc_html( $options['banner_text'] ); ?></span>
					</div>
					<span class="upwa-banner-preview-btn"><?php esc_html_e( 'Install App', 'universal-pwa' ); ?></span>
				</div>

				<h3><?php esc_html_e( 'manifest.json', 'universal-pwa' ); ?></h3>
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
			</div>
		</div>
	</div>
</div>
