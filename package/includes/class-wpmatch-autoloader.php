<?php
/**
 * Autoloader for WPMatch plugin classes
 *
 * @package WPMatch
 */

/**
 * Class autoloader for the plugin.
 */
class WPMatch_Autoloader {

	/**
	 * Plugin namespace prefix.
	 *
	 * @var string
	 */
	const PREFIX = 'WPMatch_';

	/**
	 * Directory mappings for different class types.
	 *
	 * @var array
	 */
	private static $class_map = array(
		'Admin'     => 'admin/',
		'Public'    => 'public/',
		'API'       => 'includes/api/',
		'Model'     => 'includes/models/',
		'Helper'    => 'includes/helpers/',
		'Trait'     => 'includes/traits/',
		'Interface' => 'includes/interfaces/',
	);

	/**
	 * Initialize the autoloader.
	 */
	public static function init() {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Autoload WPMatch classes.
	 *
	 * @param string $class_name The fully-qualified class name.
	 */
	public static function autoload( $class_name ) {
		// Check if this is a WPMatch class.
		if ( 0 !== strpos( $class_name, self::PREFIX ) ) {
			return;
		}

		// Remove prefix to get the relative class name.
		$relative_class = str_replace( self::PREFIX, '', $class_name );

		// Convert class name to filename.
		$filename = 'class-' . str_replace( '_', '-', strtolower( $class_name ) ) . '.php';

		// Determine the subdirectory based on class type.
		$subdirectory = self::get_subdirectory( $relative_class );

		// Build the file path.
		$file = WPMATCH_PLUGIN_DIR . $subdirectory . $filename;

		// If the file exists, load it.
		if ( file_exists( $file ) ) {
			require_once $file;
			return;
		}

		// Try loading from the includes directory if not found elsewhere.
		$file = WPMATCH_PLUGIN_DIR . 'includes/' . $filename;
		if ( file_exists( $file ) ) {
			require_once $file;
			return;
		}

		// Try loading interface files.
		if ( 0 === strpos( $relative_class, 'Interface' ) ) {
			$filename = 'interface-' . str_replace( '_', '-', strtolower( $class_name ) ) . '.php';
			$file = WPMATCH_PLUGIN_DIR . 'includes/interfaces/' . $filename;
			if ( file_exists( $file ) ) {
				require_once $file;
				return;
			}
		}

		// Try loading trait files.
		if ( 0 === strpos( $relative_class, 'Trait' ) ) {
			$filename = 'trait-' . str_replace( '_', '-', strtolower( $class_name ) ) . '.php';
			$file = WPMATCH_PLUGIN_DIR . 'includes/traits/' . $filename;
			if ( file_exists( $file ) ) {
				require_once $file;
				return;
			}
		}
	}

	/**
	 * Get the subdirectory for a class based on its name.
	 *
	 * @param string $class_name The relative class name without prefix.
	 * @return string The subdirectory path.
	 */
	private static function get_subdirectory( $class_name ) {
		// Check each mapping to find the appropriate directory.
		foreach ( self::$class_map as $keyword => $directory ) {
			if ( 0 === strpos( $class_name, $keyword ) ) {
				return $directory;
			}
		}

		// Default to includes directory.
		return 'includes/';
	}

	/**
	 * Register a custom class mapping.
	 *
	 * @param string $class_name The fully-qualified class name.
	 * @param string $file_path The absolute file path.
	 */
	public static function register_class( $class_name, $file_path ) {
		if ( ! isset( self::$custom_classes ) ) {
			self::$custom_classes = array();
		}
		self::$custom_classes[ $class_name ] = $file_path;
	}
}