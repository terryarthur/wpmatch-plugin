<?php
/**
 * Plugin Name: WPMatch - Ultimate WordPress Dating Plugin
 * Plugin URI: https://wpmatch.com
 * Description: A comprehensive WordPress dating plugin that combines the best features from leading dating platforms with enterprise-level functionality and extensive customization options.
 * Version: 1.0.0
 * Author: WPMatch Team
 * Author URI: https://wpmatch.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wpmatch
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package WPMatch
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'WPMATCH_VERSION', '1.0.0' );
define( 'WPMATCH_PLUGIN_FILE', __FILE__ );
define( 'WPMATCH_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPMATCH_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPMATCH_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Minimum requirements.
define( 'WPMATCH_MIN_PHP_VERSION', '7.4' );
define( 'WPMATCH_MIN_WP_VERSION', '5.8' );

/**
 * Check minimum requirements before loading plugin.
 *
 * @return bool Whether requirements are met.
 */
function wpmatch_check_requirements() {
	$errors = array();

	// Check PHP version.
	if ( version_compare( PHP_VERSION, WPMATCH_MIN_PHP_VERSION, '<' ) ) {
		$errors[] = sprintf(
			/* translators: 1: Current PHP version, 2: Required PHP version */
			esc_html__( 'WPMatch requires PHP version %2$s or higher. Your current version is %1$s.', 'wpmatch' ),
			PHP_VERSION,
			WPMATCH_MIN_PHP_VERSION
		);
	}

	// Check WordPress version.
	global $wp_version;
	if ( version_compare( $wp_version, WPMATCH_MIN_WP_VERSION, '<' ) ) {
		$errors[] = sprintf(
			/* translators: 1: Current WordPress version, 2: Required WordPress version */
			esc_html__( 'WPMatch requires WordPress version %2$s or higher. Your current version is %1$s.', 'wpmatch' ),
			$wp_version,
			WPMATCH_MIN_WP_VERSION
		);
	}

	// Display errors if any.
	if ( ! empty( $errors ) ) {
		add_action(
			'admin_notices',
			function () use ( $errors ) {
				?>
			<div class="notice notice-error">
				<p><strong><?php esc_html_e( 'WPMatch Plugin Error', 'wpmatch' ); ?></strong></p>
				<?php foreach ( $errors as $error ) : ?>
					<p><?php echo esc_html( $error ); ?></p>
				<?php endforeach; ?>
			</div>
				<?php
			}
		);
		return false;
	}

	return true;
}

// Check requirements before proceeding.
if ( ! wpmatch_check_requirements() ) {
	return;
}

// Load autoloader.
require_once WPMATCH_PLUGIN_DIR . 'includes/class-wpmatch-autoloader.php';
WPMatch_Autoloader::init();

// Load plugin activation/deactivation functions.
require_once WPMATCH_PLUGIN_DIR . 'includes/class-wpmatch-activator.php';
require_once WPMATCH_PLUGIN_DIR . 'includes/class-wpmatch-deactivator.php';

/**
 * Activate the plugin.
 *
 * Creates database tables and sets up plugin defaults.
 *
 * @since 1.0.0
 */
function wpmatch_activate() {
	WPMatch_Activator::activate();
}

/**
 * Deactivate the plugin.
 *
 * Cleans up temporary data and flushes rewrite rules.
 *
 * @since 1.0.0
 */
function wpmatch_deactivate() {
	WPMatch_Deactivator::deactivate();
}

// Register activation hook.
register_activation_hook( __FILE__, 'wpmatch_activate' );

// Register deactivation hook.
register_deactivation_hook( __FILE__, 'wpmatch_deactivate' );

/**
 * Uninstall handling.
 *
 * Uninstall process is handled via uninstall.php file.
 * This ensures proper cleanup when plugin is deleted.
 */

/**
 * Initialize the plugin.
 */
function wpmatch_init() {
	// Load text domain for translations.
	load_plugin_textdomain( 'wpmatch', false, dirname( WPMATCH_PLUGIN_BASENAME ) . '/languages' );

	// Load main plugin class.
	require_once WPMATCH_PLUGIN_DIR . 'includes/class-wpmatch.php';

	// Initialize plugin.
	$plugin = WPMatch::get_instance();
	$plugin->run();
}
add_action( 'plugins_loaded', 'wpmatch_init' );

/**
 * Get the main plugin instance.
 *
 * @return WPMatch The main plugin instance.
 */
function wpmatch() {
	return WPMatch::get_instance();
}

/**
 * Testing function for uninstall process.
 *
 * This function is for testing purposes only.
 * Actual uninstall handling is done via uninstall.php.
 *
 * @since 1.0.0
 */
function wpmatch_uninstall() {
	// This would normally be in uninstall.php.
	if ( class_exists( 'WPMatch_Uninstaller' ) ) {
		require_once WPMATCH_PLUGIN_DIR . 'includes/class-wpmatch-uninstaller.php';
		WPMatch_Uninstaller::uninstall();
	}
}