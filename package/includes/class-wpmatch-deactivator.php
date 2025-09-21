<?php
/**
 * Fired during plugin deactivation
 *
 * @package WPMatch
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 */
class WPMatch_Deactivator {

	/**
	 * Deactivate the plugin.
	 *
	 * Clean up temporary data and flush rewrite rules.
	 * Note: We preserve user data and settings on deactivation.
	 */
	public static function deactivate() {
		// Clean up scheduled events.
		self::clear_scheduled_events();

		// Clear any transients.
		self::clear_transients();

		// Flush rewrite rules.
		flush_rewrite_rules();

		// Clear any user sessions if needed.
		self::clear_user_sessions();

		// Log deactivation for debugging.
		self::log_deactivation();
	}

	/**
	 * Clear all scheduled cron events.
	 */
	private static function clear_scheduled_events() {
		// Clear daily cleanup cron.
		$timestamp = wp_next_scheduled( 'wpmatch_daily_cleanup' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'wpmatch_daily_cleanup' );
		}

		// Clear hourly match generation cron.
		$timestamp = wp_next_scheduled( 'wpmatch_generate_matches' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'wpmatch_generate_matches' );
		}

		// Clear email notification cron.
		$timestamp = wp_next_scheduled( 'wpmatch_send_notifications' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'wpmatch_send_notifications' );
		}
	}

	/**
	 * Clear plugin transients.
	 */
	private static function clear_transients() {
		global $wpdb;

		// Delete all transients with our prefix.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options}
				WHERE option_name LIKE %s
				OR option_name LIKE %s",
				'_transient_wpmatch_%',
				'_transient_timeout_wpmatch_%'
			)
		);
	}

	/**
	 * Clear active user sessions.
	 */
	private static function clear_user_sessions() {
		// Clear any active dating sessions.
		delete_metadata( 'user', 0, 'wpmatch_session', '', true );
		delete_metadata( 'user', 0, 'wpmatch_last_active', '', true );
	}

	/**
	 * Log deactivation for debugging purposes.
	 */
	private static function log_deactivation() {
		// Store deactivation timestamp for debugging.
		update_option( 'wpmatch_deactivated_at', current_time( 'mysql' ) );

		// Optionally log to error log if debugging is enabled.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf(
				'WPMatch plugin deactivated at %s',
				current_time( 'mysql' )
			) );
		}
	}
}