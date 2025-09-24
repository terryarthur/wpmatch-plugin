<?php
/**
 * Fired during plugin uninstall
 *
 * @package WPMatch
 */

/**
 * Fired during plugin uninstall.
 *
 * This class defines all code necessary to run during the plugin's uninstall.
 * This permanently removes all plugin data.
 */
class WPMatch_Uninstaller {

	/**
	 * Uninstall the plugin.
	 *
	 * Remove all plugin data including database tables, options, and user meta.
	 * This is irreversible and should only run when the plugin is being permanently removed.
	 */
	public static function uninstall() {
		// Check if we should preserve data (safety option).
		$preserve_data = get_option( 'wpmatch_preserve_data_on_uninstall', false );
		if ( $preserve_data ) {
			return;
		}

		// Remove database tables.
		self::drop_tables();

		// Remove plugin options.
		self::remove_options();

		// Remove user meta.
		self::remove_user_meta();

		// Remove user roles.
		self::remove_user_roles();

		// Remove uploaded files.
		self::remove_uploaded_files();

		// Clear any remaining transients.
		self::clear_all_transients();

		// Remove capabilities from other roles.
		self::remove_capabilities();
	}

	/**
	 * Drop all plugin database tables.
	 */
	private static function drop_tables() {
		global $wpdb;

		// List of tables to drop.
		$tables = array(
			$wpdb->prefix . 'wpmatch_user_profiles',
			$wpdb->prefix . 'wpmatch_user_media',
			$wpdb->prefix . 'wpmatch_user_interests',
			$wpdb->prefix . 'wpmatch_user_preferences',
			$wpdb->prefix . 'wpmatch_user_verifications',
		);

		// Drop each table.
		foreach ( $tables as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}
	}

	/**
	 * Remove all plugin options.
	 */
	private static function remove_options() {
		// List of option names to remove.
		$options = array(
			'wpmatch_version',
			'wpmatch_db_version',
			'wpmatch_settings',
			'wpmatch_activated_at',
			'wpmatch_deactivated_at',
			'wpmatch_preserve_data_on_uninstall',
			'wpmatch_license_key',
			'wpmatch_license_status',
		);

		// Remove each option.
		foreach ( $options as $option ) {
			delete_option( $option );
		}

		// Remove any additional options that might have been created.
		global $wpdb;
		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			WHERE option_name LIKE 'wpmatch_%'"
		);
	}

	/**
	 * Remove all user meta created by the plugin.
	 */
	private static function remove_user_meta() {
		global $wpdb;

		// List of user meta keys to remove.
		$meta_keys = array(
			'wpmatch_profile_id',
			'wpmatch_profile_complete',
			'wpmatch_last_active',
			'wpmatch_session',
			'wpmatch_verification_status',
			'wpmatch_premium_member',
			'wpmatch_subscription_expires',
			'wpmatch_total_likes',
			'wpmatch_total_matches',
			'wpmatch_blocked_users',
			'wpmatch_reported_users',
			'wpmatch_test_user',
		);

		// Remove each meta key for all users.
		foreach ( $meta_keys as $meta_key ) {
			$wpdb->delete(
				$wpdb->usermeta,
				array( 'meta_key' => $meta_key ),
				array( '%s' )
			);
		}

		// Remove any additional user meta that might have been created.
		$wpdb->query(
			"DELETE FROM {$wpdb->usermeta}
			WHERE meta_key LIKE 'wpmatch_%'"
		);
	}

	/**
	 * Remove custom user roles.
	 */
	private static function remove_user_roles() {
		// Remove custom roles.
		remove_role( 'wpmatch_member' );
		remove_role( 'wpmatch_premium_member' );
		remove_role( 'wpmatch_moderator' );
	}

	/**
	 * Remove capabilities from other roles.
	 */
	private static function remove_capabilities() {
		// List of capabilities to remove.
		$capabilities = array(
			'wpmatch_manage_settings',
			'wpmatch_manage_users',
			'wpmatch_view_analytics',
			'wpmatch_moderate_profiles',
			'wpmatch_moderate_media',
			'wpmatch_moderate_messages',
			'wpmatch_ban_users',
			'wpmatch_review_reports',
			'wpmatch_edit_profile',
			'wpmatch_upload_media',
			'wpmatch_send_messages',
			'wpmatch_view_profiles',
			'wpmatch_use_search',
			'wpmatch_unlimited_likes',
			'wpmatch_see_who_liked',
			'wpmatch_advanced_search',
			'wpmatch_boost_profile',
			'wpmatch_read_receipts',
			'wpmatch_access_moderation',
		);

		// Remove from administrator role.
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( $capabilities as $cap ) {
				$admin->remove_cap( $cap );
			}
		}

		// Remove from any other roles that might have them.
		$roles = wp_roles()->roles;
		foreach ( $roles as $role_name => $role_info ) {
			$role = get_role( $role_name );
			if ( $role ) {
				foreach ( $capabilities as $cap ) {
					if ( $role->has_cap( $cap ) ) {
						$role->remove_cap( $cap );
					}
				}
			}
		}
	}

	/**
	 * Remove uploaded files.
	 */
	private static function remove_uploaded_files() {
		// Get upload directory.
		$upload_dir  = wp_upload_dir();
		$wpmatch_dir = $upload_dir['basedir'] . '/wpmatch';

		// Remove directory if it exists.
		if ( is_dir( $wpmatch_dir ) ) {
			self::remove_directory( $wpmatch_dir );
		}
	}

	/**
	 * Recursively remove a directory.
	 *
	 * @param string $dir Directory path.
	 */
	private static function remove_directory( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$files = array_diff( scandir( $dir ), array( '.', '..' ) );

		foreach ( $files as $file ) {
			$path = $dir . '/' . $file;
			if ( is_dir( $path ) ) {
				self::remove_directory( $path );
			} else {
				unlink( $path );
			}
		}

		rmdir( $dir );
	}

	/**
	 * Clear all plugin transients.
	 */
	private static function clear_all_transients() {
		global $wpdb;

		// Delete all transients with our prefix.
		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			WHERE option_name LIKE '_transient_wpmatch_%'
			OR option_name LIKE '_transient_timeout_wpmatch_%'
			OR option_name LIKE '_site_transient_wpmatch_%'
			OR option_name LIKE '_site_transient_timeout_wpmatch_%'"
		);
	}
}
