<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package WPMatch
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Load plugin file for constants.
require_once plugin_dir_path( __FILE__ ) . 'wpmatch.php';

// Load uninstaller class.
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpmatch-uninstaller.php';

// Run uninstall process.
WPMatch_Uninstaller::uninstall();