<?php
/**
 * WPMatch Security Features Initializer
 *
 * @package WPMatch
 * @subpackage Security
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Initialize security and compliance features.
 */
class WPMatch_Security_Init {

	/**
	 * Initialize all security features.
	 */
	public static function init() {
		// Hook into WordPress init to safely initialize features.
		add_action( 'init', array( __CLASS__, 'init_security_features' ), 1 );
	}

	/**
	 * Initialize security features after WordPress is loaded.
	 */
	public static function init_security_features() {
		// Initialize GDPR Manager.
		if ( class_exists( 'WPMatch_GDPR_Manager' ) ) {
			try {
				WPMatch_GDPR_Manager::get_instance();
			} catch ( Exception $e ) {
				error_log( 'WPMatch GDPR Manager initialization failed: ' . $e->getMessage() );
			}
		}

		// Initialize Consent Manager.
		if ( class_exists( 'WPMatch_Consent_Manager' ) ) {
			try {
				WPMatch_Consent_Manager::get_instance();
			} catch ( Exception $e ) {
				error_log( 'WPMatch Consent Manager initialization failed: ' . $e->getMessage() );
			}
		}

		// Initialize Security Manager.
		if ( class_exists( 'WPMatch_Security_Manager' ) ) {
			try {
				WPMatch_Security_Manager::get_instance();
			} catch ( Exception $e ) {
				error_log( 'WPMatch Security Manager initialization failed: ' . $e->getMessage() );
			}
		}

		// Initialize Security Hardening.
		if ( class_exists( 'WPMatch_Security_Hardening' ) ) {
			try {
				WPMatch_Security_Hardening::get_instance();
			} catch ( Exception $e ) {
				error_log( 'WPMatch Security Hardening initialization failed: ' . $e->getMessage() );
			}
		}

		// Initialize Two Factor Authentication - DISABLED to prevent lockout.
		// Uncomment below to re-enable 2FA after fixing the implementation.
		/*
		if ( class_exists( 'WPMatch_Two_Factor' ) ) {
			try {
				WPMatch_Two_Factor::get_instance();
			} catch ( Exception $e ) {
				error_log( 'WPMatch Two Factor initialization failed: ' . $e->getMessage() );
			}
		}
		*/

		// Initialize Social OAuth.
		if ( class_exists( 'WPMatch_Social_OAuth' ) ) {
			try {
				WPMatch_Social_OAuth::get_instance();
			} catch ( Exception $e ) {
				error_log( 'WPMatch Social OAuth initialization failed: ' . $e->getMessage() );
			}
		}
	}
}