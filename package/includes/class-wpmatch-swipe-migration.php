<?php
/**
 * WPMatch Swipe Database Migration Handler
 *
 * Handles database migrations for the swipe matching system with rollback procedures.
 *
 * @package WPMatch
 * @subpackage Database
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPMatch Swipe Migration class.
 *
 * @since 1.0.0
 */
class WPMatch_Swipe_Migration {

	/**
	 * Current database version for swipe tables.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const SWIPE_DB_VERSION = '1.0.0';

	/**
	 * Option name for storing swipe database version.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const VERSION_OPTION = 'wpmatch_swipe_db_version';

	/**
	 * Run migration process.
	 *
	 * @since 1.0.0
	 * @return bool True on success, false on failure.
	 */
	public static function migrate() {
		$current_version = get_option( self::VERSION_OPTION, '0.0.0' );

		if ( version_compare( $current_version, self::SWIPE_DB_VERSION, '<' ) ) {
			return self::run_migration( $current_version );
		}

		return true;
	}

	/**
	 * Run the actual migration based on current version.
	 *
	 * @since 1.0.0
	 * @param string $from_version Current database version.
	 * @return bool True on success, false on failure.
	 */
	private static function run_migration( $from_version ) {
		global $wpdb;

		// Start transaction for rollback capability.
		$wpdb->query( 'START TRANSACTION' );

		try {
			// Create backup of existing data if upgrading.
			if ( '0.0.0' !== $from_version ) {
				if ( ! self::backup_existing_data() ) {
					throw new Exception( 'Failed to backup existing data' );
				}
			}

			// Run migration steps.
			if ( ! self::migrate_to_version_1_0_0() ) {
				throw new Exception( 'Failed to migrate to version 1.0.0' );
			}

			// Update version option.
			update_option( self::VERSION_OPTION, self::SWIPE_DB_VERSION );

			// Commit transaction.
			$wpdb->query( 'COMMIT' );

			return true;

		} catch ( Exception $e ) {
			// Rollback on failure.
			$wpdb->query( 'ROLLBACK' );
			error_log( 'WPMatch Swipe Migration failed: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Migrate to version 1.0.0 (initial swipe tables).
	 *
	 * @since 1.0.0
	 * @return bool True on success, false on failure.
	 */
	private static function migrate_to_version_1_0_0() {
		// Include the swipe database class.
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wpmatch-swipe-db.php';

		// Create all swipe-related tables.
		return WPMatch_Swipe_DB::create_tables();
	}

	/**
	 * Backup existing data before migration.
	 *
	 * @since 1.0.0
	 * @return bool True on success, false on failure.
	 */
	private static function backup_existing_data() {
		global $wpdb;

		$timestamp = current_time( 'Y_m_d_H_i_s' );
		$tables_to_backup = array(
			$wpdb->prefix . 'wpmatch_swipes',
			$wpdb->prefix . 'wpmatch_matches',
			$wpdb->prefix . 'wpmatch_match_queue',
			$wpdb->prefix . 'wpmatch_swipe_analytics',
		);

		foreach ( $tables_to_backup as $table ) {
			// Check if table exists.
			$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

			if ( $table_exists ) {
				$backup_table = $table . '_backup_' . $timestamp;
				$result = $wpdb->query( "CREATE TABLE {$backup_table} LIKE {$table}" );

				if ( false === $result ) {
					return false;
				}

				$result = $wpdb->query( "INSERT INTO {$backup_table} SELECT * FROM {$table}" );

				if ( false === $result ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Rollback to previous version.
	 *
	 * @since 1.0.0
	 * @param string $to_version Version to rollback to.
	 * @return bool True on success, false on failure.
	 */
	public static function rollback( $to_version = '0.0.0' ) {
		global $wpdb;

		$wpdb->query( 'START TRANSACTION' );

		try {
			if ( '0.0.0' === $to_version ) {
				// Complete rollback - drop all swipe tables.
				if ( ! self::drop_swipe_tables() ) {
					throw new Exception( 'Failed to drop swipe tables' );
				}
			} else {
				// Restore from backup.
				if ( ! self::restore_from_backup( $to_version ) ) {
					throw new Exception( 'Failed to restore from backup' );
				}
			}

			// Update version option.
			update_option( self::VERSION_OPTION, $to_version );

			$wpdb->query( 'COMMIT' );
			return true;

		} catch ( Exception $e ) {
			$wpdb->query( 'ROLLBACK' );
			error_log( 'WPMatch Swipe Rollback failed: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Drop all swipe-related tables.
	 *
	 * @since 1.0.0
	 * @return bool True on success, false on failure.
	 */
	private static function drop_swipe_tables() {
		global $wpdb;

		$tables = array(
			$wpdb->prefix . 'wpmatch_swipe_analytics',
			$wpdb->prefix . 'wpmatch_match_queue',
			$wpdb->prefix . 'wpmatch_matches',
			$wpdb->prefix . 'wpmatch_swipes',
		);

		foreach ( $tables as $table ) {
			$result = $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
			if ( false === $result ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Restore tables from backup.
	 *
	 * @since 1.0.0
	 * @param string $version Version to restore from.
	 * @return bool True on success, false on failure.
	 */
	private static function restore_from_backup( $version ) {
		global $wpdb;

		// Find most recent backup for the specified version.
		$tables = $wpdb->get_results( "SHOW TABLES LIKE '{$wpdb->prefix}wpmatch_%_backup_%'" );

		if ( empty( $tables ) ) {
			return false;
		}

		// Restore each table.
		foreach ( $tables as $table_obj ) {
			$backup_table = current( $table_obj );

			// Extract original table name.
			$original_table = preg_replace( '/_backup_\d{4}_\d{2}_\d{2}_\d{2}_\d{2}_\d{2}$/', '', $backup_table );

			// Drop current table and restore from backup.
			$wpdb->query( "DROP TABLE IF EXISTS {$original_table}" );
			$result = $wpdb->query( "CREATE TABLE {$original_table} LIKE {$backup_table}" );

			if ( false === $result ) {
				return false;
			}

			$result = $wpdb->query( "INSERT INTO {$original_table} SELECT * FROM {$backup_table}" );

			if ( false === $result ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Clean up old backup tables.
	 *
	 * @since 1.0.0
	 * @param int $days_to_keep Number of days to keep backups.
	 * @return bool True on success, false on failure.
	 */
	public static function cleanup_backups( $days_to_keep = 30 ) {
		global $wpdb;

		$cutoff_date = date( 'Y_m_d_H_i_s', strtotime( "-{$days_to_keep} days" ) );

		$backup_tables = $wpdb->get_results( "SHOW TABLES LIKE '{$wpdb->prefix}wpmatch_%_backup_%'" );

		foreach ( $backup_tables as $table_obj ) {
			$table_name = current( $table_obj );

			// Extract timestamp from table name.
			if ( preg_match( '/_backup_(\d{4}_\d{2}_\d{2}_\d{2}_\d{2}_\d{2})$/', $table_name, $matches ) ) {
				$table_timestamp = $matches[1];

				if ( $table_timestamp < $cutoff_date ) {
					$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
				}
			}
		}

		return true;
	}

	/**
	 * Get current swipe database version.
	 *
	 * @since 1.0.0
	 * @return string Current version.
	 */
	public static function get_version() {
		return get_option( self::VERSION_OPTION, '0.0.0' );
	}

	/**
	 * Check if swipe database is up to date.
	 *
	 * @since 1.0.0
	 * @return bool True if up to date, false otherwise.
	 */
	public static function is_up_to_date() {
		$current_version = self::get_version();
		return version_compare( $current_version, self::SWIPE_DB_VERSION, '>=' );
	}

	/**
	 * Get migration status information.
	 *
	 * @since 1.0.0
	 * @return array Migration status details.
	 */
	public static function get_status() {
		return array(
			'current_version' => self::get_version(),
			'target_version'  => self::SWIPE_DB_VERSION,
			'is_up_to_date'   => self::is_up_to_date(),
			'tables_exist'    => self::check_tables_exist(),
		);
	}

	/**
	 * Check if all required swipe tables exist.
	 *
	 * @since 1.0.0
	 * @return bool True if all tables exist, false otherwise.
	 */
	private static function check_tables_exist() {
		global $wpdb;

		$required_tables = array(
			$wpdb->prefix . 'wpmatch_swipes',
			$wpdb->prefix . 'wpmatch_matches',
			$wpdb->prefix . 'wpmatch_match_queue',
			$wpdb->prefix . 'wpmatch_swipe_analytics',
		);

		foreach ( $required_tables as $table ) {
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			if ( ! $exists ) {
				return false;
			}
		}

		return true;
	}
}