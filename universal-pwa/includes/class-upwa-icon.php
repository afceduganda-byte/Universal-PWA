<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generates and caches the manifest icons (192, 512, maskable 512)
 * from the best available source image: uploaded logo, WP Site Icon,
 * or the site favicon. Falls back gracefully when GD/Imagick are
 * unavailable, or when no source image exists at all.
 */
class UPWA_Icon {

	const SIZES = array( 192, 512 );

	public static function cache_dir() {
		$upload_dir = wp_upload_dir();
		return trailingslashit( $upload_dir['basedir'] ) . 'universal-pwa/';
	}

	public static function cache_url() {
		$upload_dir = wp_upload_dir();
		return trailingslashit( $upload_dir['baseurl'] ) . 'universal-pwa/';
	}

	private static function file_name( $size, $maskable = false ) {
		return $maskable ? "icon-{$size}-maskable.png" : "icon-{$size}.png";
	}

	/**
	 * Returns icon data for the manifest: url, width, height.
	 * Generates the icon on demand if it isn't cached yet.
	 */
	public static function get_icon_data( $size, $maskable = false ) {
		self::ensure_cache_dir();

		$path = self::cache_dir() . self::file_name( $size, $maskable );

		if ( ! file_exists( $path ) ) {
			self::generate_icon( $size, $maskable );
		}

		if ( ! file_exists( $path ) ) {
			return null;
		}

		$dimensions = @getimagesize( $path );
		$width      = $dimensions ? $dimensions[0] : $size;
		$height     = $dimensions ? $dimensions[1] : $size;

		return array(
			'url'    => self::cache_url() . self::file_name( $size, $maskable ) . '?v=' . filemtime( $path ),
			'width'  => $width,
			'height' => $height,
		);
	}

	/**
	 * Clears and rebuilds every cached icon size. Called whenever the
	 * plugin settings are saved so logo/color changes take effect
	 * immediately without a manual cache clear.
	 */
	public static function regenerate_all( $options = null ) {
		self::ensure_cache_dir();

		foreach ( self::SIZES as $size ) {
			self::generate_icon( $size, false, $options );
		}
		self::generate_icon( 512, true, $options );
	}

	private static function ensure_cache_dir() {
		$dir = self::cache_dir();
		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}
	}

	private static function generate_icon( $size, $maskable = false, $options = null ) {
		self::ensure_cache_dir();

		$dest = self::cache_dir() . self::file_name( $size, $maskable );

		$source = self::get_source_path();

		if ( null === $source ) {
			self::generate_placeholder( $dest, $size, $options );
			return;
		}

		if ( $maskable ) {
			$padded = self::pad_for_maskable( $source['path'], $dest, $size, $options );
			if ( ! $padded ) {
				// No GD/Imagick available for compositing: reuse the plain
				// square icon so the manifest still has a valid maskable entry.
				$plain = self::cache_dir() . self::file_name( $size, false );
				if ( file_exists( $plain ) ) {
					copy( $plain, $dest );
				} elseif ( ! self::resize_with_editor( $source['path'], $dest, $size ) ) {
					self::copy_original( $source['path'], $dest );
				}
			}
		} else {
			if ( ! self::resize_with_editor( $source['path'], $dest, $size ) ) {
				// GD/Imagick unavailable: fail gracefully by using the
				// source image at its original size instead of crashing.
				self::copy_original( $source['path'], $dest );
			}
		}

		if ( ! empty( $source['is_temp'] ) && file_exists( $source['path'] ) ) {
			@unlink( $source['path'] );
		}
	}

	/**
	 * Resize + center-crop to an exact square using WordPress' image
	 * editor abstraction (automatically backed by Imagick or GD).
	 */
	private static function resize_with_editor( $source_path, $dest_path, $size ) {
		if ( ! function_exists( 'wp_get_image_editor' ) ) {
			return false;
		}

		$editor = wp_get_image_editor( $source_path );

		if ( is_wp_error( $editor ) ) {
			return false;
		}

		$editor->resize( $size, $size, true );
		$saved = $editor->save( $dest_path, 'image/png' );

		return ! is_wp_error( $saved );
	}

	/**
	 * Copies the source image to the destination as-is (no resize)
	 * when no image editor library is available on this host.
	 */
	private static function copy_original( $source_path, $dest_path ) {
		$info = @getimagesize( $source_path );
		$ext  = $info ? self::ext_from_mime( $info['mime'] ) : pathinfo( $source_path, PATHINFO_EXTENSION );

		// Keep the destination filename consistent (.png) but copy raw bytes;
		// browsers infer type from the manifest's declared "type" field only
		// loosely, so we also expose the true mime type via getimagesize()
		// when building manifest entries elsewhere.
		return @copy( $source_path, $dest_path );
	}

	private static function ext_from_mime( $mime ) {
		$map = array(
			'image/jpeg' => 'jpg',
			'image/png'  => 'png',
			'image/gif'  => 'gif',
			'image/webp' => 'webp',
		);
		return isset( $map[ $mime ] ) ? $map[ $mime ] : 'png';
	}

	/**
	 * Pads the source image onto a square canvas filled with the
	 * background color so it satisfies the maskable-icon "safe zone"
	 * (icon content within the center ~80%). Uses GD if available,
	 * otherwise Imagick. Returns false if neither is available.
	 */
	private static function pad_for_maskable( $source_path, $dest_path, $size, $options = null ) {
		if ( null === $options ) {
			$options = Universal_PWA::get_options();
		}
		$bg = self::hex_to_rgb( isset( $options['background_color'] ) ? $options['background_color'] : '#ffffff' );

		if ( function_exists( 'imagecreatetruecolor' ) ) {
			return self::pad_with_gd( $source_path, $dest_path, $size, $bg );
		}

		if ( class_exists( 'Imagick' ) ) {
			return self::pad_with_imagick( $source_path, $dest_path, $size, $bg );
		}

		return false;
	}

	private static function pad_with_gd( $source_path, $dest_path, $size, $bg ) {
		$info = @getimagesize( $source_path );
		if ( ! $info ) {
			return false;
		}

		switch ( $info['mime'] ) {
			case 'image/jpeg':
				$src_img = @imagecreatefromjpeg( $source_path );
				break;
			case 'image/png':
				$src_img = @imagecreatefrompng( $source_path );
				break;
			case 'image/gif':
				$src_img = @imagecreatefromgif( $source_path );
				break;
			case 'image/webp':
				$src_img = function_exists( 'imagecreatefromwebp' ) ? @imagecreatefromwebp( $source_path ) : false;
				break;
			default:
				$src_img = false;
		}

		if ( ! $src_img ) {
			return false;
		}

		$canvas = imagecreatetruecolor( $size, $size );
		$color  = imagecolorallocate( $canvas, $bg[0], $bg[1], $bg[2] );
		imagefill( $canvas, 0, 0, $color );

		$inner       = (int) round( $size * 0.8 );
		$src_width   = imagesx( $src_img );
		$src_height  = imagesy( $src_img );
		$scale       = min( $inner / $src_width, $inner / $src_height );
		$new_width   = (int) round( $src_width * $scale );
		$new_height  = (int) round( $src_height * $scale );
		$offset_x    = (int) round( ( $size - $new_width ) / 2 );
		$offset_y    = (int) round( ( $size - $new_height ) / 2 );

		imagecopyresampled( $canvas, $src_img, $offset_x, $offset_y, 0, 0, $new_width, $new_height, $src_width, $src_height );

		$result = imagepng( $canvas, $dest_path );

		imagedestroy( $src_img );
		imagedestroy( $canvas );

		return $result;
	}

	private static function pad_with_imagick( $source_path, $dest_path, $size, $bg ) {
		try {
			$source = new Imagick( $source_path );

			$inner = (int) round( $size * 0.8 );
			$source->resizeImage( $inner, $inner, Imagick::FILTER_LANCZOS, 1, true );

			$canvas = new Imagick();
			$canvas->newImage( $size, $size, new ImagickPixel( sprintf( 'rgb(%d,%d,%d)', $bg[0], $bg[1], $bg[2] ) ) );
			$canvas->setImageFormat( 'png' );

			$offset_x = (int) round( ( $size - $source->getImageWidth() ) / 2 );
			$offset_y = (int) round( ( $size - $source->getImageHeight() ) / 2 );

			$canvas->compositeImage( $source, Imagick::COMPOSITE_OVER, $offset_x, $offset_y );
			$canvas->writeImage( $dest_path );

			$source->destroy();
			$canvas->destroy();

			return true;
		} catch ( Exception $e ) {
			return false;
		}
	}

	private static function hex_to_rgb( $hex ) {
		$hex = ltrim( (string) $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
			return array( 255, 255, 255 );
		}
		return array(
			hexdec( substr( $hex, 0, 2 ) ),
			hexdec( substr( $hex, 2, 2 ) ),
			hexdec( substr( $hex, 4, 2 ) ),
		);
	}

	/**
	 * Determines the best available source image, in priority order:
	 * uploaded logo -> WordPress Site Icon -> site favicon.ico.
	 * Returns null if none is available (caller must use a placeholder).
	 */
	private static function get_source_path() {
		$options = Universal_PWA::get_options();

		if ( ! empty( $options['logo_id'] ) ) {
			$path = get_attached_file( (int) $options['logo_id'] );
			if ( $path && file_exists( $path ) ) {
				return array( 'path' => $path, 'is_temp' => false );
			}
		}

		$site_icon_id = get_option( 'site_icon' );
		if ( $site_icon_id ) {
			$path = get_attached_file( (int) $site_icon_id );
			if ( $path && file_exists( $path ) ) {
				return array( 'path' => $path, 'is_temp' => false );
			}
		}

		$favicon = self::download_favicon();
		if ( $favicon ) {
			return array( 'path' => $favicon, 'is_temp' => true );
		}

		return null;
	}

	/**
	 * Best-effort fetch of /favicon.ico from the site root. Many hosts
	 * don't have one; failures here are silent and simply fall through
	 * to the generated placeholder icon.
	 */
	private static function download_favicon() {
		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$url = trailingslashit( home_url() ) . 'favicon.ico';
		$tmp = download_url( $url, 5 );

		if ( is_wp_error( $tmp ) ) {
			return false;
		}

		$info = @getimagesize( $tmp );
		if ( ! $info || ! in_array( $info['mime'], array( 'image/png', 'image/jpeg', 'image/gif', 'image/x-icon', 'image/vnd.microsoft.icon' ), true ) ) {
			@unlink( $tmp );
			return false;
		}

		// GD/Imagick generally can't decode .ico directly; only keep it
		// when it's actually a renamed PNG/JPEG/GIF (common in the wild).
		if ( 'image/x-icon' === $info['mime'] || 'image/vnd.microsoft.icon' === $info['mime'] ) {
			@unlink( $tmp );
			return false;
		}

		return $tmp;
	}

	/**
	 * Last-resort icon when there is no logo, no Site Icon, and no
	 * usable favicon: a solid square in the theme color with the
	 * site's first initial, so the manifest is always valid.
	 */
	private static function generate_placeholder( $dest_path, $size, $options = null ) {
		if ( null === $options ) {
			$options = Universal_PWA::get_options();
		}

		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			return false;
		}

		$bg     = self::hex_to_rgb( isset( $options['theme_color'] ) ? $options['theme_color'] : '#0b0b0b' );
		$canvas = imagecreatetruecolor( $size, $size );
		$color  = imagecolorallocate( $canvas, $bg[0], $bg[1], $bg[2] );
		imagefill( $canvas, 0, 0, $color );

		$initial = strtoupper( substr( trim( (string) ( $options['app_name'] ?? get_bloginfo( 'name' ) ) ), 0, 1 ) );
		if ( '' === $initial ) {
			$initial = 'W';
		}

		$white     = imagecolorallocate( $canvas, 255, 255, 255 );
		$font_size = (int) round( $size * 0.5 );

		if ( function_exists( 'imagettftext' ) && file_exists( self::builtin_font_path() ) ) {
			$box = imagettfbbox( $font_size, 0, self::builtin_font_path(), $initial );
			$text_width  = abs( $box[4] - $box[0] );
			$text_height = abs( $box[5] - $box[1] );
			$x = (int) round( ( $size - $text_width ) / 2 );
			$y = (int) round( ( $size + $text_height ) / 2 );
			imagettftext( $canvas, $font_size, 0, $x, $y, $white, self::builtin_font_path(), $initial );
		} else {
			// Fall back to GD's built-in bitmap font if no TTF is available.
			$gd_font = 5;
			$x       = (int) round( ( $size - imagefontwidth( $gd_font ) ) / 2 );
			$y       = (int) round( ( $size - imagefontheight( $gd_font ) ) / 2 );
			imagestring( $canvas, $gd_font, $x, $y, $initial, $white );
		}

		$result = imagepng( $canvas, $dest_path );
		imagedestroy( $canvas );

		return $result;
	}

	private static function builtin_font_path() {
		// A common location for a system TTF; if unavailable the
		// bitmap-font fallback above still produces a valid icon.
		return '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';
	}
}
