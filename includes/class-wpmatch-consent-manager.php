<?php
/**
 * WPMatch Consent Management System
 *
 * Handles user consent collection, management, and compliance with privacy regulations.
 *
 * @package WPMatch
 * @subpackage Privacy
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPMatch Consent Manager class.
 *
 * @since 1.0.0
 */
class WPMatch_Consent_Manager {

	/**
	 * Consent categories.
	 */
	const CATEGORY_ESSENTIAL     = 'essential';
	const CATEGORY_FUNCTIONAL    = 'functional';
	const CATEGORY_ANALYTICS     = 'analytics';
	const CATEGORY_MARKETING     = 'marketing';
	const CATEGORY_PERSONALIZATION = 'personalization';

	/**
	 * Consent purposes.
	 */
	const PURPOSE_CORE_FEATURES     = 'core_features';
	const PURPOSE_USER_EXPERIENCE   = 'user_experience';
	const PURPOSE_PERFORMANCE       = 'performance';
	const PURPOSE_ADVERTISING       = 'advertising';
	const PURPOSE_PERSONALIZED_ADS  = 'personalized_ads';
	const PURPOSE_SOCIAL_FEATURES   = 'social_features';
	const PURPOSE_LOCATION_SERVICES = 'location_services';

	/**
	 * Instance of the class.
	 *
	 * @var WPMatch_Consent_Manager
	 */
	private static $instance = null;

	/**
	 * Consent configuration.
	 *
	 * @var array
	 */
	private $consent_config = array();

	/**
	 * Get the single instance.
	 *
	 * @return WPMatch_Consent_Manager
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->load_consent_config();
		$this->init_hooks();
	}

	/**
	 * Load consent configuration.
	 */
	private function load_consent_config() {
		$this->consent_config = array(
			self::CATEGORY_ESSENTIAL => array(
				'name'        => __( 'Essential', 'wpmatch' ),
				'description' => __( 'Required for core functionality and security. Cannot be disabled.', 'wpmatch' ),
				'required'    => true,
				'purposes'    => array(
					self::PURPOSE_CORE_FEATURES => array(
						'name'        => __( 'Core Dating Features', 'wpmatch' ),
						'description' => __( 'Profile creation, matching, messaging, and other core dating functionality.', 'wpmatch' ),
						'required'    => true,
					),
				),
			),
			self::CATEGORY_FUNCTIONAL => array(
				'name'        => __( 'Functional', 'wpmatch' ),
				'description' => __( 'Enhanced features that improve your experience.', 'wpmatch' ),
				'required'    => false,
				'purposes'    => array(
					self::PURPOSE_USER_EXPERIENCE => array(
						'name'        => __( 'Enhanced User Experience', 'wpmatch' ),
						'description' => __( 'Remembering preferences, saved searches, and customized interface settings.', 'wpmatch' ),
						'required'    => false,
					),
					self::PURPOSE_LOCATION_SERVICES => array(
						'name'        => __( 'Location Services', 'wpmatch' ),
						'description' => __( 'Distance-based matching and location-aware features.', 'wpmatch' ),
						'required'    => false,
					),
					self::PURPOSE_SOCIAL_FEATURES => array(
						'name'        => __( 'Social Features', 'wpmatch' ),
						'description' => __( 'Social media integration, friend connections, and social sharing.', 'wpmatch' ),
						'required'    => false,
					),
				),
			),
			self::CATEGORY_ANALYTICS => array(
				'name'        => __( 'Analytics', 'wpmatch' ),
				'description' => __( 'Help us understand how our service is used to improve it.', 'wpmatch' ),
				'required'    => false,
				'purposes'    => array(
					self::PURPOSE_PERFORMANCE => array(
						'name'        => __( 'Performance Analytics', 'wpmatch' ),
						'description' => __( 'Anonymous usage statistics, performance monitoring, and crash reporting.', 'wpmatch' ),
						'required'    => false,
					),
				),
			),
			self::CATEGORY_MARKETING => array(
				'name'        => __( 'Marketing', 'wpmatch' ),
				'description' => __( 'Personalized content and promotional communications.', 'wpmatch' ),
				'required'    => false,
				'purposes'    => array(
					self::PURPOSE_ADVERTISING => array(
						'name'        => __( 'General Advertising', 'wpmatch' ),
						'description' => __( 'Show relevant ads and promotional content.', 'wpmatch' ),
						'required'    => false,
					),
					self::PURPOSE_PERSONALIZED_ADS => array(
						'name'        => __( 'Personalized Advertising', 'wpmatch' ),
						'description' => __( 'Ads tailored to your interests and behavior.', 'wpmatch' ),
						'required'    => false,
					),
				),
			),
			self::CATEGORY_PERSONALIZATION => array(
				'name'        => __( 'Personalization', 'wpmatch' ),
				'description' => __( 'Customize your experience based on your activity.', 'wpmatch' ),
				'required'    => false,
				'purposes'    => array(
					self::PURPOSE_PERSONALIZED_ADS => array(
						'name'        => __( 'Personalized Recommendations', 'wpmatch' ),
						'description' => __( 'Customized match suggestions and content recommendations.', 'wpmatch' ),
						'required'    => false,
					),
				),
			),
		);

		// Allow customization via filter.
		$this->consent_config = apply_filters( 'wpmatch_consent_config', $this->consent_config );
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Frontend hooks.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_consent_assets' ) );
		add_action( 'wp_footer', array( $this, 'render_consent_modal' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_wpmatch_save_consent_preferences', array( $this, 'handle_save_consent_preferences' ) );
		add_action( 'wp_ajax_nopriv_wpmatch_save_consent_preferences', array( $this, 'handle_save_consent_preferences' ) );
		add_action( 'wp_ajax_wpmatch_get_consent_status', array( $this, 'handle_get_consent_status' ) );
		add_action( 'wp_ajax_nopriv_wpmatch_get_consent_status', array( $this, 'handle_get_consent_status' ) );

		// Admin hooks.
		add_action( 'admin_menu', array( $this, 'add_consent_admin_menu' ) );

		// User profile hooks.
		add_action( 'show_user_profile', array( $this, 'show_user_consent_section' ) );
		add_action( 'edit_user_profile', array( $this, 'show_user_consent_section' ) );

		// Shortcode.
		add_shortcode( 'wpmatch_consent_preferences', array( $this, 'consent_preferences_shortcode' ) );
	}

	/**
	 * Enqueue consent management assets.
	 */
	public function enqueue_consent_assets() {
		if ( is_admin() ) {
			return;
		}

		wp_enqueue_style(
			'wpmatch-consent',
			plugin_dir_url( dirname( __FILE__ ) ) . 'public/css/wpmatch-consent.css',
			array(),
			WPMATCH_VERSION
		);

		wp_enqueue_script(
			'wpmatch-consent',
			plugin_dir_url( dirname( __FILE__ ) ) . 'public/js/wpmatch-consent.js',
			array( 'jquery' ),
			WPMATCH_VERSION,
			true
		);

		wp_localize_script(
			'wpmatch-consent',
			'wpMatchConsent',
			array(
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( 'wpmatch_consent_nonce' ),
				'currentUserId'    => get_current_user_id(),
				'consentConfig'    => $this->consent_config,
				'strings'          => array(
					'saveSuccess'     => __( 'Consent preferences saved successfully.', 'wpmatch' ),
					'saveError'       => __( 'Error saving consent preferences.', 'wpmatch' ),
					'loadError'       => __( 'Error loading consent status.', 'wpmatch' ),
					'acceptAll'       => __( 'Accept All', 'wpmatch' ),
					'rejectAll'       => __( 'Reject All', 'wpmatch' ),
					'savePreferences' => __( 'Save Preferences', 'wpmatch' ),
					'manageConsent'   => __( 'Manage Consent', 'wpmatch' ),
					'close'           => __( 'Close', 'wpmatch' ),
				),
			)
		);
	}

	/**
	 * Render consent modal.
	 */
	public function render_consent_modal() {
		if ( is_admin() ) {
			return;
		}

		$user_id = get_current_user_id();
		$show_banner = ! $user_id || ! $this->has_essential_consent( $user_id );

		?>
		<div id="wpmatch-consent-modal" class="wpmatch-consent-modal" style="display: none;">
			<div class="wpmatch-consent-modal-content">
				<div class="wpmatch-consent-header">
					<h2><?php esc_html_e( 'Privacy & Cookie Preferences', 'wpmatch' ); ?></h2>
					<button class="wpmatch-consent-close" aria-label="<?php esc_attr_e( 'Close', 'wpmatch' ); ?>">&times;</button>
				</div>

				<div class="wpmatch-consent-body">
					<div class="wpmatch-consent-intro">
						<p><?php esc_html_e( 'We value your privacy. Please choose which types of cookies and data processing you\'re comfortable with. You can change these preferences at any time.', 'wpmatch' ); ?></p>
					</div>

					<div class="wpmatch-consent-categories">
						<?php foreach ( $this->consent_config as $category_id => $category ) : ?>
							<div class="wpmatch-consent-category" data-category="<?php echo esc_attr( $category_id ); ?>">
								<div class="wpmatch-consent-category-header">
									<h3><?php echo esc_html( $category['name'] ); ?></h3>
									<div class="wpmatch-consent-toggle">
										<label class="wpmatch-switch">
											<input
												type="checkbox"
												id="consent-<?php echo esc_attr( $category_id ); ?>"
												data-category="<?php echo esc_attr( $category_id ); ?>"
												<?php echo $category['required'] ? 'checked disabled' : ''; ?>
											>
											<span class="wpmatch-slider"></span>
										</label>
									</div>
								</div>

								<div class="wpmatch-consent-category-description">
									<p><?php echo esc_html( $category['description'] ); ?></p>
									<?php if ( $category['required'] ) : ?>
										<p class="wpmatch-consent-required">
											<em><?php esc_html_e( 'Required for service functionality', 'wpmatch' ); ?></em>
										</p>
									<?php endif; ?>
								</div>

								<?php if ( ! empty( $category['purposes'] ) ) : ?>
									<div class="wpmatch-consent-purposes">
										<button class="wpmatch-consent-expand" type="button">
											<?php esc_html_e( 'View Details', 'wpmatch' ); ?>
											<span class="wpmatch-expand-icon">▼</span>
										</button>
										<div class="wpmatch-consent-purposes-list" style="display: none;">
											<?php foreach ( $category['purposes'] as $purpose_id => $purpose ) : ?>
												<div class="wpmatch-consent-purpose">
													<h4><?php echo esc_html( $purpose['name'] ); ?></h4>
													<p><?php echo esc_html( $purpose['description'] ); ?></p>
												</div>
											<?php endforeach; ?>
										</div>
									</div>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>

					<div class="wpmatch-consent-legal-info">
						<p>
							<?php
							printf(
								/* translators: 1: Privacy policy link start, 2: Privacy policy link end, 3: Cookie policy link start, 4: Cookie policy link end */
								esc_html__( 'For more information, please read our %1$sPrivacy Policy%2$s and %3$sCookie Policy%4$s.', 'wpmatch' ),
								'<a href="' . esc_url( get_privacy_policy_url() ) . '" target="_blank">',
								'</a>',
								'<a href="' . esc_url( home_url( '/cookie-policy' ) ) . '" target="_blank">',
								'</a>'
							);
							?>
						</p>
					</div>
				</div>

				<div class="wpmatch-consent-footer">
					<div class="wpmatch-consent-actions">
						<button class="wpmatch-btn wpmatch-btn-secondary" id="wpmatch-reject-all">
							<?php esc_html_e( 'Reject All', 'wpmatch' ); ?>
						</button>
						<button class="wpmatch-btn wpmatch-btn-primary" id="wpmatch-accept-all">
							<?php esc_html_e( 'Accept All', 'wpmatch' ); ?>
						</button>
						<button class="wpmatch-btn wpmatch-btn-primary" id="wpmatch-save-preferences">
							<?php esc_html_e( 'Save Preferences', 'wpmatch' ); ?>
						</button>
					</div>
				</div>
			</div>
		</div>

		<?php if ( $show_banner ) : ?>
		<div id="wpmatch-consent-banner" class="wpmatch-consent-banner">
			<div class="wpmatch-consent-banner-content">
				<div class="wpmatch-consent-banner-text">
					<p><?php esc_html_e( 'We use cookies to enhance your dating experience, analyze usage, and provide personalized matches. By continuing to use our service, you agree to our use of cookies.', 'wpmatch' ); ?></p>
				</div>
				<div class="wpmatch-consent-banner-actions">
					<button class="wpmatch-btn wpmatch-btn-small wpmatch-btn-secondary" id="wpmatch-banner-manage">
						<?php esc_html_e( 'Manage Preferences', 'wpmatch' ); ?>
					</button>
					<button class="wpmatch-btn wpmatch-btn-small wpmatch-btn-primary" id="wpmatch-banner-accept">
						<?php esc_html_e( 'Accept All', 'wpmatch' ); ?>
					</button>
				</div>
			</div>
		</div>
		<?php endif; ?>

		<style>
		.wpmatch-consent-modal {
			position: fixed;
			top: 0;
			left: 0;
			width: 100%;
			height: 100%;
			background: rgba(0, 0, 0, 0.7);
			z-index: 10000;
			display: flex;
			align-items: center;
			justify-content: center;
		}

		.wpmatch-consent-modal-content {
			background: white;
			max-width: 600px;
			width: 90%;
			max-height: 80vh;
			border-radius: 8px;
			box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
			overflow: hidden;
		}

		.wpmatch-consent-header {
			padding: 20px;
			border-bottom: 1px solid #eee;
			display: flex;
			justify-content: space-between;
			align-items: center;
		}

		.wpmatch-consent-header h2 {
			margin: 0;
			font-size: 1.4em;
		}

		.wpmatch-consent-close {
			background: none;
			border: none;
			font-size: 24px;
			cursor: pointer;
			color: #666;
		}

		.wpmatch-consent-body {
			padding: 20px;
			max-height: 50vh;
			overflow-y: auto;
		}

		.wpmatch-consent-intro {
			margin-bottom: 20px;
		}

		.wpmatch-consent-category {
			margin-bottom: 20px;
			border: 1px solid #eee;
			border-radius: 6px;
			padding: 15px;
		}

		.wpmatch-consent-category-header {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-bottom: 10px;
		}

		.wpmatch-consent-category-header h3 {
			margin: 0;
			font-size: 1.1em;
		}

		.wpmatch-switch {
			position: relative;
			display: inline-block;
			width: 50px;
			height: 24px;
		}

		.wpmatch-switch input {
			opacity: 0;
			width: 0;
			height: 0;
		}

		.wpmatch-slider {
			position: absolute;
			cursor: pointer;
			top: 0;
			left: 0;
			right: 0;
			bottom: 0;
			background-color: #ccc;
			transition: 0.4s;
			border-radius: 24px;
		}

		.wpmatch-slider:before {
			position: absolute;
			content: "";
			height: 18px;
			width: 18px;
			left: 3px;
			bottom: 3px;
			background-color: white;
			transition: 0.4s;
			border-radius: 50%;
		}

		input:checked + .wpmatch-slider {
			background-color: #007cba;
		}

		input:checked + .wpmatch-slider:before {
			transform: translateX(26px);
		}

		input:disabled + .wpmatch-slider {
			background-color: #007cba;
			opacity: 0.6;
			cursor: not-allowed;
		}

		.wpmatch-consent-required {
			font-size: 0.9em;
			color: #666;
		}

		.wpmatch-consent-expand {
			background: none;
			border: none;
			color: #007cba;
			cursor: pointer;
			font-size: 0.9em;
			padding: 5px 0;
		}

		.wpmatch-consent-purposes-list {
			margin-top: 10px;
			padding-left: 20px;
		}

		.wpmatch-consent-purpose {
			margin-bottom: 10px;
		}

		.wpmatch-consent-purpose h4 {
			margin: 0 0 5px 0;
			font-size: 1em;
		}

		.wpmatch-consent-legal-info {
			margin-top: 20px;
			padding-top: 15px;
			border-top: 1px solid #eee;
			font-size: 0.9em;
			color: #666;
		}

		.wpmatch-consent-footer {
			padding: 20px;
			border-top: 1px solid #eee;
			background: #f9f9f9;
		}

		.wpmatch-consent-actions {
			display: flex;
			gap: 10px;
			justify-content: flex-end;
		}

		.wpmatch-btn {
			padding: 10px 20px;
			border: none;
			border-radius: 4px;
			cursor: pointer;
			font-size: 14px;
			transition: background-color 0.3s;
		}

		.wpmatch-btn-primary {
			background: #007cba;
			color: white;
		}

		.wpmatch-btn-primary:hover {
			background: #005a87;
		}

		.wpmatch-btn-secondary {
			background: #f0f0f0;
			color: #333;
		}

		.wpmatch-btn-secondary:hover {
			background: #e0e0e0;
		}

		.wpmatch-consent-banner {
			position: fixed;
			bottom: 0;
			left: 0;
			right: 0;
			background: #333;
			color: white;
			padding: 20px;
			z-index: 9999;
		}

		.wpmatch-consent-banner-content {
			max-width: 1200px;
			margin: 0 auto;
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 20px;
		}

		.wpmatch-consent-banner-text {
			flex: 1;
		}

		.wpmatch-consent-banner-actions {
			display: flex;
			gap: 10px;
		}

		.wpmatch-btn-small {
			padding: 8px 16px;
			font-size: 13px;
		}

		@media (max-width: 768px) {
			.wpmatch-consent-modal-content {
				width: 95%;
				max-height: 90vh;
			}

			.wpmatch-consent-banner-content {
				flex-direction: column;
				text-align: center;
			}

			.wpmatch-consent-actions {
				flex-direction: column;
			}

			.wpmatch-consent-category-header {
				flex-direction: column;
				align-items: flex-start;
				gap: 10px;
			}
		}
		</style>
		<?php
	}

	/**
	 * Handle save consent preferences AJAX.
	 */
	public function handle_save_consent_preferences() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wpmatch_consent_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wpmatch' ) ) );
		}

		$user_id = get_current_user_id();
		$preferences = isset( $_POST['preferences'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['preferences'] ) ) : array();

		// Save consent preferences.
		$this->save_user_consent( $user_id, $preferences );

		// Set cookie for non-logged in users.
		if ( ! $user_id ) {
			$this->set_consent_cookie( $preferences );
		}

		wp_send_json_success( array(
			'message'     => __( 'Consent preferences saved successfully.', 'wpmatch' ),
			'preferences' => $preferences,
		) );
	}

	/**
	 * Handle get consent status AJAX.
	 */
	public function handle_get_consent_status() {
		$user_id = get_current_user_id();
		$status = $this->get_user_consent_status( $user_id );

		wp_send_json_success( array( 'status' => $status ) );
	}

	/**
	 * Save user consent preferences.
	 *
	 * @param int $user_id User ID (0 for non-logged in users).
	 * @param array $preferences Consent preferences.
	 */
	private function save_user_consent( $user_id, $preferences ) {
		global $wpdb;

		$ip_address = $this->get_user_ip();
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		foreach ( $this->consent_config as $category_id => $category ) {
			$consent_given = isset( $preferences[ $category_id ] ) && $preferences[ $category_id ];

			// Always grant essential consent.
			if ( $category['required'] ) {
				$consent_given = true;
			}

			if ( $user_id ) {
				// Save to database for logged-in users.
				$wpdb->insert(
					$wpdb->prefix . 'wpmatch_user_consent',
					array(
						'user_id'        => $user_id,
						'consent_type'   => $category_id,
						'consent_given'  => $consent_given ? 1 : 0,
						'consent_source' => 'consent_modal',
						'ip_address'     => $ip_address,
						'user_agent'     => $user_agent,
						'created_at'     => current_time( 'mysql' ),
					),
					array( '%d', '%s', '%d', '%s', '%s', '%s', '%s' )
				);
			}

			// Trigger action for each consent decision.
			do_action( 'wpmatch_consent_given', $user_id, $category_id, $consent_given, 'consent_modal' );
		}

		// Update user meta with current consent status.
		if ( $user_id ) {
			update_user_meta( $user_id, 'wpmatch_consent_preferences', $preferences );
			update_user_meta( $user_id, 'wpmatch_consent_last_updated', current_time( 'mysql' ) );
		}
	}

	/**
	 * Set consent cookie for non-logged in users.
	 *
	 * @param array $preferences Consent preferences.
	 */
	private function set_consent_cookie( $preferences ) {
		$cookie_data = array(
			'preferences' => $preferences,
			'timestamp'   => time(),
			'version'     => '1.0',
		);

		setcookie(
			'wpmatch_consent',
			wp_json_encode( $cookie_data ),
			time() + ( 365 * DAY_IN_SECONDS ), // 1 year
			COOKIEPATH,
			COOKIE_DOMAIN,
			is_ssl(),
			true // HttpOnly
		);
	}

	/**
	 * Get user consent status.
	 *
	 * @param int $user_id User ID.
	 * @return array Consent status.
	 */
	public function get_user_consent_status( $user_id ) {
		if ( $user_id ) {
			// Get from user meta.
			$preferences = get_user_meta( $user_id, 'wpmatch_consent_preferences', true );
			if ( is_array( $preferences ) ) {
				return $preferences;
			}

			// Fallback to database.
			global $wpdb;
			$consents = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT consent_type, consent_given
					FROM {$wpdb->prefix}wpmatch_user_consent
					WHERE user_id = %d
					AND created_at = (
						SELECT MAX(created_at)
						FROM {$wpdb->prefix}wpmatch_user_consent c2
						WHERE c2.user_id = %d AND c2.consent_type = {$wpdb->prefix}wpmatch_user_consent.consent_type
					)",
					$user_id,
					$user_id
				)
			);

			$status = array();
			foreach ( $consents as $consent ) {
				$status[ $consent->consent_type ] = (bool) $consent->consent_given;
			}

			return $status;
		} else {
			// Get from cookie for non-logged in users.
			if ( isset( $_COOKIE['wpmatch_consent'] ) ) {
				$cookie_data = json_decode( wp_unslash( $_COOKIE['wpmatch_consent'] ), true );
				if ( isset( $cookie_data['preferences'] ) ) {
					return $cookie_data['preferences'];
				}
			}
		}

		return array();
	}

	/**
	 * Check if user has given consent for a specific category.
	 *
	 * @param int $user_id User ID.
	 * @param string $category_id Category ID.
	 * @return bool True if consent given.
	 */
	public function has_consent( $user_id, $category_id ) {
		// Essential consent is always true.
		if ( self::CATEGORY_ESSENTIAL === $category_id ) {
			return true;
		}

		$status = $this->get_user_consent_status( $user_id );
		return isset( $status[ $category_id ] ) && $status[ $category_id ];
	}

	/**
	 * Check if user has essential consent.
	 *
	 * @param int $user_id User ID.
	 * @return bool True if essential consent given.
	 */
	public function has_essential_consent( $user_id ) {
		$status = $this->get_user_consent_status( $user_id );
		return ! empty( $status );
	}

	/**
	 * Consent preferences shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Shortcode output.
	 */
	public function consent_preferences_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'show_title' => true,
		), $atts );

		ob_start();
		?>
		<div class="wpmatch-consent-preferences-widget">
			<?php if ( $atts['show_title'] ) : ?>
				<h3><?php esc_html_e( 'Privacy Preferences', 'wpmatch' ); ?></h3>
			<?php endif; ?>

			<p><?php esc_html_e( 'Manage your privacy and cookie preferences for the WPMatch dating service.', 'wpmatch' ); ?></p>

			<button class="wpmatch-btn wpmatch-btn-primary" id="wpmatch-open-consent-modal">
				<?php esc_html_e( 'Manage Privacy Preferences', 'wpmatch' ); ?>
			</button>

			<div class="wpmatch-consent-current-status">
				<h4><?php esc_html_e( 'Current Preferences:', 'wpmatch' ); ?></h4>
				<div id="wpmatch-consent-status-display">
					<p><?php esc_html_e( 'Loading...', 'wpmatch' ); ?></p>
				</div>
			</div>
		</div>

		<script>
		jQuery(document).ready(function($) {
			// Load current consent status.
			$.post(wpMatchConsent.ajaxUrl, {
				action: 'wpmatch_get_consent_status',
				nonce: wpMatchConsent.nonce
			}, function(response) {
				if (response.success) {
					var statusHtml = '';
					$.each(wpMatchConsent.consentConfig, function(categoryId, category) {
						var hasConsent = response.data.status[categoryId] || category.required;
						var statusText = hasConsent ? '✓ Enabled' : '✗ Disabled';
						var statusClass = hasConsent ? 'enabled' : 'disabled';
						statusHtml += '<div class="consent-status-item ' + statusClass + '">';
						statusHtml += '<strong>' + category.name + ':</strong> ' + statusText;
						statusHtml += '</div>';
					});
					$('#wpmatch-consent-status-display').html(statusHtml);
				}
			});

			// Open consent modal.
			$('#wpmatch-open-consent-modal').on('click', function() {
				if (window.wpMatchConsentModal) {
					wpMatchConsentModal.open();
				}
			});
		});
		</script>

		<style>
		.wpmatch-consent-preferences-widget {
			padding: 20px;
			border: 1px solid #ddd;
			border-radius: 6px;
			background: #f9f9f9;
		}

		.wpmatch-consent-current-status {
			margin-top: 20px;
		}

		.consent-status-item {
			padding: 5px 0;
		}

		.consent-status-item.enabled {
			color: #00a32a;
		}

		.consent-status-item.disabled {
			color: #d63638;
		}
		</style>
		<?php

		return ob_get_clean();
	}

	/**
	 * Add consent admin menu.
	 */
	public function add_consent_admin_menu() {
		add_submenu_page(
			'wpmatch',
			__( 'Consent Management', 'wpmatch' ),
			__( 'Consent Management', 'wpmatch' ),
			'manage_options',
			'wpmatch-consent',
			array( $this, 'render_consent_admin_page' )
		);
	}

	/**
	 * Render consent admin page.
	 */
	public function render_consent_admin_page() {
		$consent_stats = $this->get_consent_statistics();
		?>
		<div class="wrap wpmatch-admin">
			<div class="wpmatch-admin-header">
				<div class="wpmatch-header-content">
					<div class="wpmatch-header-main">
						<h1>
							<span class="dashicons dashicons-yes-alt"></span>
							<?php esc_html_e( 'Consent Management', 'wpmatch' ); ?>
						</h1>
						<p><?php esc_html_e( 'Monitor and manage user consent preferences for GDPR compliance and data processing activities.', 'wpmatch' ); ?></p>
					</div>
					<div class="wpmatch-header-actions">
						<a href="#" class="wpmatch-button secondary" onclick="exportConsentData()">
							<span class="dashicons dashicons-download"></span>
							<?php esc_html_e( 'Export Consent Data', 'wpmatch' ); ?>
						</a>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpmatch-privacy' ) ); ?>" class="wpmatch-button secondary">
							<span class="dashicons dashicons-privacy"></span>
							<?php esc_html_e( 'Privacy Settings', 'wpmatch' ); ?>
						</a>
					</div>
				</div>
			</div>

			<div class="wpmatch-consent-admin-dashboard">
				<div class="wpmatch-stats-grid">
					<div class="wpmatch-stat-card">
						<h3><?php esc_html_e( 'Total Users', 'wpmatch' ); ?></h3>
						<p class="wpmatch-stat-number"><?php echo esc_html( number_format( $consent_stats['total_users'] ) ); ?></p>
					</div>

					<div class="wpmatch-stat-card">
						<h3><?php esc_html_e( 'Essential Consent', 'wpmatch' ); ?></h3>
						<p class="wpmatch-stat-number"><?php echo esc_html( number_format( $consent_stats['essential_consent'] ) ); ?></p>
					</div>

					<div class="wpmatch-stat-card">
						<h3><?php esc_html_e( 'Analytics Consent', 'wpmatch' ); ?></h3>
						<p class="wpmatch-stat-number"><?php echo esc_html( number_format( $consent_stats['analytics_consent'] ) ); ?></p>
					</div>

					<div class="wpmatch-stat-card">
						<h3><?php esc_html_e( 'Marketing Consent', 'wpmatch' ); ?></h3>
						<p class="wpmatch-stat-number"><?php echo esc_html( number_format( $consent_stats['marketing_consent'] ) ); ?></p>
					</div>
				</div>

				<h2><?php esc_html_e( 'Consent Categories', 'wpmatch' ); ?></h2>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Category', 'wpmatch' ); ?></th>
							<th><?php esc_html_e( 'Description', 'wpmatch' ); ?></th>
							<th><?php esc_html_e( 'Required', 'wpmatch' ); ?></th>
							<th><?php esc_html_e( 'Consent Rate', 'wpmatch' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $this->consent_config as $category_id => $category ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $category['name'] ); ?></strong></td>
								<td><?php echo esc_html( $category['description'] ); ?></td>
								<td><?php echo $category['required'] ? '✓' : '✗'; ?></td>
								<td>
									<?php
									$rate = $consent_stats['category_rates'][ $category_id ] ?? 0;
									echo esc_html( number_format( $rate, 1 ) . '%' );
									?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Show user consent section in profile.
	 *
	 * @param WP_User $user User object.
	 */
	public function show_user_consent_section( $user ) {
		$consent_status = $this->get_user_consent_status( $user->ID );
		?>
		<h3><?php esc_html_e( 'Privacy & Consent', 'wpmatch' ); ?></h3>
		<table class="form-table">
			<tr>
				<th><label><?php esc_html_e( 'Consent Status', 'wpmatch' ); ?></label></th>
				<td>
					<?php foreach ( $this->consent_config as $category_id => $category ) : ?>
						<p>
							<strong><?php echo esc_html( $category['name'] ); ?>:</strong>
							<?php
							$has_consent = isset( $consent_status[ $category_id ] ) && $consent_status[ $category_id ];
							echo $has_consent ? '✓ ' . esc_html__( 'Granted', 'wpmatch' ) : '✗ ' . esc_html__( 'Not Granted', 'wpmatch' );
							?>
						</p>
					<?php endforeach; ?>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Get consent statistics.
	 *
	 * @return array Consent statistics.
	 */
	private function get_consent_statistics() {
		global $wpdb;

		$stats = array(
			'total_users'       => 0,
			'essential_consent' => 0,
			'analytics_consent' => 0,
			'marketing_consent' => 0,
			'category_rates'    => array(),
		);

		// Get total users.
		$stats['total_users'] = $wpdb->get_var(
			"SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}wpmatch_user_profiles"
		);

		// Get consent counts by category.
		foreach ( $this->consent_config as $category_id => $category ) {
			$consent_count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT user_id)
					FROM {$wpdb->prefix}wpmatch_user_consent
					WHERE consent_type = %s AND consent_given = 1",
					$category_id
				)
			);

			$stats[ $category_id . '_consent' ] = (int) $consent_count;

			// Calculate consent rate.
			if ( $stats['total_users'] > 0 ) {
				$stats['category_rates'][ $category_id ] = ( $consent_count / $stats['total_users'] ) * 100;
			} else {
				$stats['category_rates'][ $category_id ] = 0;
			}
		}

		return $stats;
	}

	/**
	 * Get user IP address.
	 *
	 * @return string IP address.
	 */
	private function get_user_ip() {
		$ip_keys = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '127.0.0.1';
	}
}

// Initialize consent manager.
WPMatch_Consent_Manager::get_instance();