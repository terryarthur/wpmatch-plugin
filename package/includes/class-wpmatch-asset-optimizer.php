<?php
/**
 * WPMatch Asset Optimizer
 *
 * Handles minification and optimization of CSS, JavaScript, and image assets.
 *
 * @package WPMatch
 * @subpackage Assets
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPMatch Asset Optimizer class.
 *
 * @since 1.0.0
 */
class WPMatch_Asset_Optimizer {

	/**
	 * Assets directory.
	 */
	const ASSETS_DIR = 'assets';

	/**
	 * Optimized assets directory.
	 */
	const OPTIMIZED_DIR = 'assets/optimized';

	/**
	 * Cache directory for optimized assets.
	 */
	const CACHE_DIR = 'assets/cache';

	/**
	 * Initialize asset optimization.
	 */
	public static function init() {
		// Only optimize in production or when explicitly enabled.
		if ( self::should_optimize() ) {
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'optimize_frontend_assets' ), 999 );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'optimize_admin_assets' ), 999 );
		}

		// Always enable image optimization.
		add_filter( 'wp_handle_upload', array( __CLASS__, 'optimize_uploaded_image' ) );
		add_action( 'wpmatch_daily_cleanup', array( __CLASS__, 'cleanup_optimized_assets' ) );
	}

	/**
	 * Check if assets should be optimized.
	 *
	 * @return bool True if optimization should run.
	 */
	private static function should_optimize() {
		return ! WP_DEBUG || get_option( 'wpmatch_force_asset_optimization', false );
	}

	/**
	 * Optimize frontend assets.
	 */
	public static function optimize_frontend_assets() {
		global $wp_scripts, $wp_styles;

		// Get WPMatch assets only.
		$wpmatch_scripts = self::get_wpmatch_assets( $wp_scripts, 'js' );
		$wpmatch_styles  = self::get_wpmatch_assets( $wp_styles, 'css' );

		if ( ! empty( $wpmatch_scripts ) ) {
			self::combine_and_minify_scripts( $wpmatch_scripts );
		}

		if ( ! empty( $wpmatch_styles ) ) {
			self::combine_and_minify_styles( $wpmatch_styles );
		}
	}

	/**
	 * Optimize admin assets.
	 */
	public static function optimize_admin_assets() {
		global $wp_scripts, $wp_styles;

		// Only optimize on WPMatch admin pages.
		if ( ! self::is_wpmatch_admin_page() ) {
			return;
		}

		$wpmatch_scripts = self::get_wpmatch_assets( $wp_scripts, 'js' );
		$wpmatch_styles  = self::get_wpmatch_assets( $wp_styles, 'css' );

		if ( ! empty( $wpmatch_scripts ) ) {
			self::combine_and_minify_scripts( $wpmatch_scripts );
		}

		if ( ! empty( $wpmatch_styles ) ) {
			self::combine_and_minify_styles( $wpmatch_styles );
		}
	}

	/**
	 * Get WPMatch assets from queue.
	 *
	 * @param WP_Dependencies $queue Asset queue.
	 * @param string $type Asset type (js|css).
	 * @return array WPMatch assets.
	 */
	private static function get_wpmatch_assets( $queue, $type ) {
		$wpmatch_assets = array();

		foreach ( $queue->queue as $handle ) {
			if ( strpos( $handle, 'wpmatch' ) === 0 ) {
				$asset = $queue->registered[ $handle ];
				if ( $asset && isset( $asset->src ) ) {
					$wpmatch_assets[ $handle ] = $asset;
				}
			}
		}

		return $wpmatch_assets;
	}

	/**
	 * Check if current page is WPMatch admin page.
	 *
	 * @return bool True if WPMatch admin page.
	 */
	private static function is_wpmatch_admin_page() {
		$screen = get_current_screen();
		return $screen && ( strpos( $screen->id, 'wpmatch' ) !== false || strpos( $screen->base, 'wpmatch' ) !== false );
	}

	/**
	 * Combine and minify JavaScript files.
	 *
	 * @param array $scripts Array of script objects.
	 */
	private static function combine_and_minify_scripts( $scripts ) {
		if ( empty( $scripts ) ) {
			return;
		}

		$cache_key = 'wpmatch_js_' . md5( serialize( array_keys( $scripts ) ) . WPMATCH_VERSION );
		$cache_file = self::get_cache_file_path( $cache_key . '.js' );

		// Check if cached version exists.
		if ( file_exists( $cache_file ) ) {
			self::enqueue_optimized_script( $cache_key, $cache_file );
			self::dequeue_original_scripts( array_keys( $scripts ) );
			return;
		}

		$combined_content = '';
		$dependencies = array();

		foreach ( $scripts as $handle => $script ) {
			$file_path = self::get_local_file_path( $script->src );
			if ( $file_path && file_exists( $file_path ) ) {
				$content = file_get_contents( $file_path );
				if ( $content ) {
					// Add source map comment for debugging.
					$combined_content .= "/* Source: {$handle} */\n";
					$combined_content .= self::minify_javascript( $content );
					$combined_content .= "\n\n";

					// Collect dependencies.
					if ( ! empty( $script->deps ) ) {
						$dependencies = array_merge( $dependencies, $script->deps );
					}
				}
			}
		}

		if ( ! empty( $combined_content ) ) {
			// Create cache directory if it doesn't exist.
			self::ensure_cache_directory();

			// Write combined and minified content.
			file_put_contents( $cache_file, $combined_content );

			// Enqueue optimized version.
			self::enqueue_optimized_script( $cache_key, $cache_file, array_unique( $dependencies ) );

			// Dequeue original scripts.
			self::dequeue_original_scripts( array_keys( $scripts ) );
		}
	}

	/**
	 * Combine and minify CSS files.
	 *
	 * @param array $styles Array of style objects.
	 */
	private static function combine_and_minify_styles( $styles ) {
		if ( empty( $styles ) ) {
			return;
		}

		$cache_key = 'wpmatch_css_' . md5( serialize( array_keys( $styles ) ) . WPMATCH_VERSION );
		$cache_file = self::get_cache_file_path( $cache_key . '.css' );

		// Check if cached version exists.
		if ( file_exists( $cache_file ) ) {
			self::enqueue_optimized_style( $cache_key, $cache_file );
			self::dequeue_original_styles( array_keys( $styles ) );
			return;
		}

		$combined_content = '';
		$dependencies = array();

		foreach ( $styles as $handle => $style ) {
			$file_path = self::get_local_file_path( $style->src );
			if ( $file_path && file_exists( $file_path ) ) {
				$content = file_get_contents( $file_path );
				if ( $content ) {
					// Add source comment for debugging.
					$combined_content .= "/* Source: {$handle} */\n";
					$combined_content .= self::minify_css( $content, dirname( $file_path ) );
					$combined_content .= "\n\n";

					// Collect dependencies.
					if ( ! empty( $style->deps ) ) {
						$dependencies = array_merge( $dependencies, $style->deps );
					}
				}
			}
		}

		if ( ! empty( $combined_content ) ) {
			// Create cache directory if it doesn't exist.
			self::ensure_cache_directory();

			// Write combined and minified content.
			file_put_contents( $cache_file, $combined_content );

			// Enqueue optimized version.
			self::enqueue_optimized_style( $cache_key, $cache_file, array_unique( $dependencies ) );

			// Dequeue original styles.
			self::dequeue_original_styles( array_keys( $styles ) );
		}
	}

	/**
	 * Minify JavaScript content.
	 *
	 * @param string $content JavaScript content.
	 * @return string Minified content.
	 */
	private static function minify_javascript( $content ) {
		// Basic JavaScript minification.
		$content = preg_replace( '/\/\*[\s\S]*?\*\//', '', $content ); // Remove multi-line comments.
		$content = preg_replace( '/\/\/.*$/m', '', $content ); // Remove single-line comments.
		$content = preg_replace( '/\s+/', ' ', $content ); // Collapse whitespace.
		$content = preg_replace( '/;\s*}/', '}', $content ); // Remove semicolon before closing brace.
		$content = str_replace( array( '; ', ' ;' ), ';', $content ); // Clean up semicolons.
		$content = str_replace( array( '{ ', ' {' ), '{', $content ); // Clean up braces.
		$content = str_replace( array( '} ', ' }' ), '}', $content );
		$content = str_replace( array( ', ', ' ,' ), ',', $content ); // Clean up commas.

		return trim( $content );
	}

	/**
	 * Minify CSS content.
	 *
	 * @param string $content CSS content.
	 * @param string $base_path Base path for resolving relative URLs.
	 * @return string Minified content.
	 */
	private static function minify_css( $content, $base_path = '' ) {
		// Remove comments.
		$content = preg_replace( '/\/\*[\s\S]*?\*\//', '', $content );

		// Convert relative URLs to absolute if base path provided.
		if ( $base_path ) {
			$content = preg_replace_callback(
				'/url\(["\']?([^"\')]+)["\']?\)/',
				function( $matches ) use ( $base_path ) {
					$url = $matches[1];
					if ( ! preg_match( '/^(https?:\/\/|\/|data:)/', $url ) ) {
						$url = self::convert_to_web_path( $base_path . '/' . $url );
					}
					return "url('{$url}')";
				},
				$content
			);
		}

		// Remove unnecessary whitespace.
		$content = preg_replace( '/\s+/', ' ', $content );
		$content = preg_replace( '/;\s*}/', '}', $content );
		$content = str_replace( array( '; ', ' ;' ), ';', $content );
		$content = str_replace( array( '{ ', ' {' ), '{', $content );
		$content = str_replace( array( '} ', ' }' ), '}', $content );
		$content = str_replace( array( ': ', ' :' ), ':', $content );
		$content = str_replace( array( ', ', ' ,' ), ',', $content );

		return trim( $content );
	}

	/**
	 * Get local file path from URL.
	 *
	 * @param string $url Asset URL.
	 * @return string|false Local file path or false if not local.
	 */
	private static function get_local_file_path( $url ) {
		$site_url = get_site_url();

		// Check if URL is local.
		if ( strpos( $url, $site_url ) !== 0 ) {
			return false;
		}

		// Convert URL to local path.
		$relative_path = str_replace( $site_url, '', $url );
		$file_path = ABSPATH . ltrim( $relative_path, '/' );

		return $file_path;
	}

	/**
	 * Convert local file path to web-accessible path.
	 *
	 * @param string $file_path Local file path.
	 * @return string Web path.
	 */
	private static function convert_to_web_path( $file_path ) {
		$relative_path = str_replace( ABSPATH, '', $file_path );
		return get_site_url() . '/' . ltrim( $relative_path, '/' );
	}

	/**
	 * Get cache file path.
	 *
	 * @param string $filename Cache filename.
	 * @return string Full cache file path.
	 */
	private static function get_cache_file_path( $filename ) {
		$plugin_dir = plugin_dir_path( dirname( __FILE__ ) );
		return $plugin_dir . self::CACHE_DIR . '/' . $filename;
	}

	/**
	 * Ensure cache directory exists.
	 */
	private static function ensure_cache_directory() {
		$plugin_dir = plugin_dir_path( dirname( __FILE__ ) );
		$cache_dir = $plugin_dir . self::CACHE_DIR;

		if ( ! file_exists( $cache_dir ) ) {
			wp_mkdir_p( $cache_dir );
		}
	}

	/**
	 * Enqueue optimized script.
	 *
	 * @param string $handle Script handle.
	 * @param string $file_path File path.
	 * @param array $dependencies Dependencies.
	 */
	private static function enqueue_optimized_script( $handle, $file_path, $dependencies = array() ) {
		$url = self::convert_to_web_path( $file_path );
		$version = filemtime( $file_path );

		wp_enqueue_script( $handle, $url, $dependencies, $version, true );
	}

	/**
	 * Enqueue optimized style.
	 *
	 * @param string $handle Style handle.
	 * @param string $file_path File path.
	 * @param array $dependencies Dependencies.
	 */
	private static function enqueue_optimized_style( $handle, $file_path, $dependencies = array() ) {
		$url = self::convert_to_web_path( $file_path );
		$version = filemtime( $file_path );

		wp_enqueue_style( $handle, $url, $dependencies, $version );
	}

	/**
	 * Dequeue original scripts.
	 *
	 * @param array $handles Script handles to dequeue.
	 */
	private static function dequeue_original_scripts( $handles ) {
		foreach ( $handles as $handle ) {
			wp_dequeue_script( $handle );
		}
	}

	/**
	 * Dequeue original styles.
	 *
	 * @param array $handles Style handles to dequeue.
	 */
	private static function dequeue_original_styles( $handles ) {
		foreach ( $handles as $handle ) {
			wp_dequeue_style( $handle );
		}
	}

	/**
	 * Optimize uploaded images.
	 *
	 * @param array $upload Upload data.
	 * @return array Modified upload data.
	 */
	public static function optimize_uploaded_image( $upload ) {
		if ( ! isset( $upload['file'] ) || ! isset( $upload['type'] ) ) {
			return $upload;
		}

		$file_path = $upload['file'];
		$mime_type = $upload['type'];

		// Only process images.
		if ( strpos( $mime_type, 'image/' ) !== 0 ) {
			return $upload;
		}

		// Get optimization settings.
		$quality = get_option( 'wpmatch_image_quality', 85 );
		$max_width = get_option( 'wpmatch_max_image_width', 1920 );
		$max_height = get_option( 'wpmatch_max_image_height', 1080 );

		// Optimize the image.
		$optimized = self::optimize_image( $file_path, $quality, $max_width, $max_height );

		if ( $optimized ) {
			// Update file size in upload data.
			$upload['size'] = filesize( $file_path );
		}

		return $upload;
	}

	/**
	 * Optimize image file.
	 *
	 * @param string $file_path Image file path.
	 * @param int $quality JPEG quality (1-100).
	 * @param int $max_width Maximum width.
	 * @param int $max_height Maximum height.
	 * @return bool True if optimized successfully.
	 */
	private static function optimize_image( $file_path, $quality = 85, $max_width = 1920, $max_height = 1080 ) {
		if ( ! file_exists( $file_path ) ) {
			return false;
		}

		// Get image info.
		$image_info = getimagesize( $file_path );
		if ( ! $image_info ) {
			return false;
		}

		list( $width, $height, $type ) = $image_info;

		// Check if resize is needed.
		$needs_resize = $width > $max_width || $height > $max_height;

		// Create image resource.
		$image = false;
		switch ( $type ) {
			case IMAGETYPE_JPEG:
				$image = imagecreatefromjpeg( $file_path );
				break;
			case IMAGETYPE_PNG:
				$image = imagecreatefrompng( $file_path );
				break;
			case IMAGETYPE_WEBP:
				if ( function_exists( 'imagecreatefromwebp' ) ) {
					$image = imagecreatefromwebp( $file_path );
				}
				break;
		}

		if ( ! $image ) {
			return false;
		}

		// Calculate new dimensions if resize is needed.
		if ( $needs_resize ) {
			$ratio = min( $max_width / $width, $max_height / $height );
			$new_width = (int) round( $width * $ratio );
			$new_height = (int) round( $height * $ratio );

			// Create resized image.
			$resized_image = imagecreatetruecolor( $new_width, $new_height );

			// Preserve transparency for PNG.
			if ( $type === IMAGETYPE_PNG ) {
				imagealphablending( $resized_image, false );
				imagesavealpha( $resized_image, true );
				$transparent = imagecolorallocatealpha( $resized_image, 255, 255, 255, 127 );
				imagefill( $resized_image, 0, 0, $transparent );
			}

			// Resize image.
			imagecopyresampled( $resized_image, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height );
			imagedestroy( $image );
			$image = $resized_image;
		}

		// Save optimized image.
		$result = false;
		switch ( $type ) {
			case IMAGETYPE_JPEG:
				$result = imagejpeg( $image, $file_path, $quality );
				break;
			case IMAGETYPE_PNG:
				// PNG compression level (0-9, where 9 is highest compression).
				$png_quality = (int) round( ( 100 - $quality ) / 10 );
				$result = imagepng( $image, $file_path, $png_quality );
				break;
			case IMAGETYPE_WEBP:
				if ( function_exists( 'imagewebp' ) ) {
					$result = imagewebp( $image, $file_path, $quality );
				}
				break;
		}

		imagedestroy( $image );

		return $result;
	}

	/**
	 * Generate WebP versions of images.
	 *
	 * @param string $image_path Original image path.
	 * @return string|false WebP image path or false on failure.
	 */
	public static function generate_webp_version( $image_path ) {
		if ( ! function_exists( 'imagewebp' ) ) {
			return false;
		}

		$webp_path = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $image_path );

		// If WebP already exists and is newer, return it.
		if ( file_exists( $webp_path ) && filemtime( $webp_path ) >= filemtime( $image_path ) ) {
			return $webp_path;
		}

		// Get image info.
		$image_info = getimagesize( $image_path );
		if ( ! $image_info ) {
			return false;
		}

		list( $width, $height, $type ) = $image_info;

		// Create image resource.
		$image = false;
		switch ( $type ) {
			case IMAGETYPE_JPEG:
				$image = imagecreatefromjpeg( $image_path );
				break;
			case IMAGETYPE_PNG:
				$image = imagecreatefrompng( $image_path );
				break;
		}

		if ( ! $image ) {
			return false;
		}

		// Convert to WebP.
		$quality = get_option( 'wpmatch_webp_quality', 80 );
		$result = imagewebp( $image, $webp_path, $quality );
		imagedestroy( $image );

		return $result ? $webp_path : false;
	}

	/**
	 * Clean up old optimized assets.
	 */
	public static function cleanup_optimized_assets() {
		$plugin_dir = plugin_dir_path( dirname( __FILE__ ) );
		$cache_dir = $plugin_dir . self::CACHE_DIR;

		if ( ! file_exists( $cache_dir ) ) {
			return;
		}

		$files = glob( $cache_dir . '/*' );
		$cutoff_time = time() - ( 7 * DAY_IN_SECONDS ); // Keep files for 7 days.

		foreach ( $files as $file ) {
			if ( is_file( $file ) && filemtime( $file ) < $cutoff_time ) {
				unlink( $file );
			}
		}
	}

	/**
	 * Get asset optimization statistics.
	 *
	 * @return array Optimization statistics.
	 */
	public static function get_optimization_stats() {
		$plugin_dir = plugin_dir_path( dirname( __FILE__ ) );
		$cache_dir = $plugin_dir . self::CACHE_DIR;

		$stats = array(
			'cache_files' => 0,
			'cache_size' => 0,
			'savings_estimate' => 0,
		);

		if ( file_exists( $cache_dir ) ) {
			$files = glob( $cache_dir . '/*' );
			$stats['cache_files'] = count( $files );

			foreach ( $files as $file ) {
				if ( is_file( $file ) ) {
					$stats['cache_size'] += filesize( $file );
				}
			}

			// Estimate savings (rough calculation).
			$stats['savings_estimate'] = $stats['cache_size'] * 0.3; // Assume 30% reduction.
		}

		return $stats;
	}

	/**
	 * Clear all optimized assets cache.
	 */
	public static function clear_optimization_cache() {
		$plugin_dir = plugin_dir_path( dirname( __FILE__ ) );
		$cache_dir = $plugin_dir . self::CACHE_DIR;

		if ( file_exists( $cache_dir ) ) {
			$files = glob( $cache_dir . '/*' );
			foreach ( $files as $file ) {
				if ( is_file( $file ) ) {
					unlink( $file );
				}
			}
		}
	}

	/**
	 * Enable critical CSS inlining for above-the-fold content.
	 */
	public static function inline_critical_css() {
		// Only on frontend and not admin.
		if ( is_admin() ) {
			return;
		}

		$critical_css = self::get_critical_css();
		if ( $critical_css ) {
			echo "<style id='wpmatch-critical-css'>{$critical_css}</style>";
		}
	}

	/**
	 * Get critical CSS for above-the-fold content.
	 *
	 * @return string Critical CSS.
	 */
	private static function get_critical_css() {
		$cache_key = 'wpmatch_critical_css_' . get_queried_object_id();
		$cached = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		// Generate critical CSS (simplified version).
		$critical_css = '
			.wpmatch-container { max-width: 1200px; margin: 0 auto; }
			.wpmatch-loading { display: flex; justify-content: center; padding: 2rem; }
			.wpmatch-card { background: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
			.wpmatch-header { padding: 1rem; border-bottom: 1px solid #eee; }
			.wpmatch-content { padding: 1rem; }
		';

		// Cache for 1 hour.
		set_transient( $cache_key, $critical_css, HOUR_IN_SECONDS );

		return $critical_css;
	}
}

// Initialize asset optimizer.
WPMatch_Asset_Optimizer::init();