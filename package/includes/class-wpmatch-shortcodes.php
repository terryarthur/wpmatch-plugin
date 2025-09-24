<?php
/**
 * WPMatch Shortcodes System
 *
 * Manages all frontend shortcodes for the dating plugin interface.
 *
 * @package WPMatch
 */

/**
 * WPMatch Shortcodes class.
 *
 * @since 1.0.0
 */
class WPMatch_Shortcodes {

	/**
	 * Plugin name.
	 *
	 * @var string
	 */
	private $plugin_name;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Initialize the class.
	 *
	 * @param string $plugin_name The name of this plugin.
	 * @param string $version The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;

		$this->register_shortcodes();
	}

	/**
	 * Register all shortcodes.
	 *
	 * @since 1.0.0
	 */
	private function register_shortcodes() {
		add_shortcode( 'wpmatch_profile', array( $this, 'profile_shortcode' ) );
		add_shortcode( 'wpmatch_swipe', array( $this, 'swipe_shortcode' ) );
		add_shortcode( 'wpmatch_matches', array( $this, 'matches_shortcode' ) );
		add_shortcode( 'wpmatch_messages', array( $this, 'messages_shortcode' ) );
		add_shortcode( 'wpmatch_search', array( $this, 'search_shortcode' ) );
		add_shortcode( 'wpmatch_dashboard', array( $this, 'dashboard_shortcode' ) );
	}

	/**
	 * Profile management shortcode.
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Shortcode output.
	 */
	public function profile_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'view'     => 'edit', // edit, view, public
				'user_id'  => null,
				'redirect' => '',
			),
			$atts,
			'wpmatch_profile'
		);

		// Check if user is logged in.
		if ( ! is_user_logged_in() ) {
			return $this->login_required_message();
		}

		$user_id = $atts['user_id'] ? absint( $atts['user_id'] ) : get_current_user_id();

		// Enqueue assets.
		$this->enqueue_profile_assets();

		ob_start();

		switch ( $atts['view'] ) {
			case 'edit':
				echo $this->render_profile_edit_form( $user_id, $atts['redirect'] );
				break;
			case 'view':
				echo $this->render_profile_view( $user_id );
				break;
			case 'public':
				echo $this->render_public_profile( $user_id );
				break;
			default:
				echo $this->render_profile_edit_form( $user_id, $atts['redirect'] );
		}

		return ob_get_clean();
	}

	/**
	 * Swipe interface shortcode.
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Shortcode output.
	 */
	public function swipe_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'limit'        => 10,
				'auto_refresh' => 'true',
			),
			$atts,
			'wpmatch_swipe'
		);

		// Check if user is logged in.
		if ( ! is_user_logged_in() ) {
			return $this->login_required_message();
		}

		// Check if user has a complete profile.
		if ( ! $this->user_has_complete_profile() ) {
			return $this->complete_profile_message();
		}

		// Enqueue swipe assets.
		$this->enqueue_swipe_assets();

		ob_start();
		echo $this->render_swipe_interface( $atts );
		return ob_get_clean();
	}

	/**
	 * Matches display shortcode.
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Shortcode output.
	 */
	public function matches_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'view'        => 'grid', // grid, list
				'limit'       => 20,
				'show_online' => 'true',
			),
			$atts,
			'wpmatch_matches'
		);

		// Check if user is logged in.
		if ( ! is_user_logged_in() ) {
			return $this->login_required_message();
		}

		// Enqueue matches assets.
		$this->enqueue_matches_assets();

		ob_start();
		echo $this->render_matches_display( $atts );
		return ob_get_clean();
	}

	/**
	 * Messages interface shortcode.
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Shortcode output.
	 */
	public function messages_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'conversation_id' => null,
				'layout'          => 'full', // full, compact
			),
			$atts,
			'wpmatch_messages'
		);

		// Check if user is logged in.
		if ( ! is_user_logged_in() ) {
			return $this->login_required_message();
		}

		// Enqueue messaging assets.
		$this->enqueue_messaging_assets();

		ob_start();
		echo $this->render_messaging_interface( $atts );
		return ob_get_clean();
	}

	/**
	 * Search interface shortcode.
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Shortcode output.
	 */
	public function search_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'filters'  => 'all', // all, basic, advanced
				'layout'   => 'grid',
				'per_page' => 12,
			),
			$atts,
			'wpmatch_search'
		);

		// Check if user is logged in.
		if ( ! is_user_logged_in() ) {
			return $this->login_required_message();
		}

		// Enqueue search assets.
		$this->enqueue_search_assets();

		ob_start();
		echo $this->render_search_interface( $atts );
		return ob_get_clean();
	}

	/**
	 * Dashboard shortcode.
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Shortcode output.
	 */
	public function dashboard_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'layout' => 'default',
			),
			$atts,
			'wpmatch_dashboard'
		);

		// Check if user is logged in.
		if ( ! is_user_logged_in() ) {
			return $this->login_required_message();
		}

		// Enqueue dashboard assets.
		$this->enqueue_dashboard_assets();

		ob_start();
		echo $this->render_dashboard( $atts );
		return ob_get_clean();
	}

	/**
	 * Render profile edit form.
	 *
	 * @since 1.0.0
	 * @param int    $user_id User ID.
	 * @param string $redirect Redirect URL after save.
	 * @return string HTML output.
	 */
	private function render_profile_edit_form( $user_id, $redirect = '' ) {
		$user_data    = get_userdata( $user_id );
		$profile_data = $this->get_user_profile_data( $user_id );

		ob_start();
		?>
		<div class="wpmatch-profile-edit">
			<form id="wpmatch-profile-form" class="wpmatch-form" enctype="multipart/form-data">
				<?php wp_nonce_field( 'wpmatch_update_profile', 'wpmatch_profile_nonce' ); ?>
				<input type="hidden" name="action" value="wpmatch_update_profile">
				<input type="hidden" name="user_id" value="<?php echo esc_attr( $user_id ); ?>">
				<?php if ( $redirect ) : ?>
					<input type="hidden" name="redirect_to" value="<?php echo esc_url( $redirect ); ?>">
				<?php endif; ?>

				<div class="wpmatch-profile-header">
					<h2><?php esc_html_e( 'Edit Your Profile', 'wpmatch' ); ?></h2>
					<div class="wpmatch-profile-completion">
						<span class="completion-text"><?php esc_html_e( 'Profile Completion:', 'wpmatch' ); ?></span>
						<div class="completion-bar">
							<div class="completion-fill" style="width: <?php echo esc_attr( $profile_data['completion_percentage'] ?? 0 ); ?>%"></div>
						</div>
						<span class="completion-percentage"><?php echo esc_html( $profile_data['completion_percentage'] ?? 0 ); ?>%</span>
					</div>
				</div>

				<div class="wpmatch-profile-sections">
					<!-- Basic Information -->
					<div class="wpmatch-profile-section" data-section="basic">
						<h3><?php esc_html_e( 'Basic Information', 'wpmatch' ); ?></h3>

						<div class="wpmatch-field-group">
							<label for="display_name"><?php esc_html_e( 'Display Name', 'wpmatch' ); ?> *</label>
							<input type="text" id="display_name" name="display_name"
								value="<?php echo esc_attr( $user_data->display_name ?? '' ); ?>" required>
						</div>

						<div class="wpmatch-field-group">
							<label for="age"><?php esc_html_e( 'Age', 'wpmatch' ); ?> *</label>
							<input type="number" id="age" name="age" min="18" max="99"
								value="<?php echo esc_attr( $profile_data['age'] ?? '' ); ?>" required>
						</div>

						<div class="wpmatch-field-group">
							<label for="gender"><?php esc_html_e( 'Gender', 'wpmatch' ); ?> *</label>
							<select id="gender" name="gender" required>
								<option value=""><?php esc_html_e( 'Select Gender', 'wpmatch' ); ?></option>
								<option value="male" <?php selected( $profile_data['gender'] ?? '', 'male' ); ?>><?php esc_html_e( 'Male', 'wpmatch' ); ?></option>
								<option value="female" <?php selected( $profile_data['gender'] ?? '', 'female' ); ?>><?php esc_html_e( 'Female', 'wpmatch' ); ?></option>
								<option value="non-binary" <?php selected( $profile_data['gender'] ?? '', 'non-binary' ); ?>><?php esc_html_e( 'Non-binary', 'wpmatch' ); ?></option>
								<option value="other" <?php selected( $profile_data['gender'] ?? '', 'other' ); ?>><?php esc_html_e( 'Other', 'wpmatch' ); ?></option>
							</select>
						</div>

						<div class="wpmatch-field-group">
							<label for="orientation"><?php esc_html_e( 'Sexual Orientation', 'wpmatch' ); ?></label>
							<select id="orientation" name="orientation">
								<option value=""><?php esc_html_e( 'Select Orientation', 'wpmatch' ); ?></option>
								<option value="straight" <?php selected( $profile_data['orientation'] ?? '', 'straight' ); ?>><?php esc_html_e( 'Straight', 'wpmatch' ); ?></option>
								<option value="gay" <?php selected( $profile_data['orientation'] ?? '', 'gay' ); ?>><?php esc_html_e( 'Gay', 'wpmatch' ); ?></option>
								<option value="lesbian" <?php selected( $profile_data['orientation'] ?? '', 'lesbian' ); ?>><?php esc_html_e( 'Lesbian', 'wpmatch' ); ?></option>
								<option value="bisexual" <?php selected( $profile_data['orientation'] ?? '', 'bisexual' ); ?>><?php esc_html_e( 'Bisexual', 'wpmatch' ); ?></option>
								<option value="pansexual" <?php selected( $profile_data['orientation'] ?? '', 'pansexual' ); ?>><?php esc_html_e( 'Pansexual', 'wpmatch' ); ?></option>
								<option value="asexual" <?php selected( $profile_data['orientation'] ?? '', 'asexual' ); ?>><?php esc_html_e( 'Asexual', 'wpmatch' ); ?></option>
								<option value="other" <?php selected( $profile_data['orientation'] ?? '', 'other' ); ?>><?php esc_html_e( 'Other', 'wpmatch' ); ?></option>
							</select>
						</div>

						<div class="wpmatch-field-group">
							<label for="location"><?php esc_html_e( 'Location', 'wpmatch' ); ?> *</label>
							<input type="text" id="location" name="location"
								value="<?php echo esc_attr( $profile_data['location'] ?? '' ); ?>"
								placeholder="<?php esc_attr_e( 'City, State/Country', 'wpmatch' ); ?>" required>
							<button type="button" id="get-current-location" class="wpmatch-btn-secondary">
								<?php esc_html_e( 'Use Current Location', 'wpmatch' ); ?>
							</button>
						</div>
					</div>

					<!-- About Me -->
					<div class="wpmatch-profile-section" data-section="about">
						<h3><?php esc_html_e( 'About Me', 'wpmatch' ); ?></h3>

						<div class="wpmatch-field-group">
							<label for="about_me"><?php esc_html_e( 'About Me', 'wpmatch' ); ?> *</label>
							<textarea id="about_me" name="about_me" rows="4" maxlength="500" required
								placeholder="<?php esc_attr_e( 'Tell others about yourself...', 'wpmatch' ); ?>"><?php echo esc_textarea( $profile_data['about_me'] ?? '' ); ?></textarea>
							<span class="char-counter">0/500</span>
						</div>

						<div class="wpmatch-field-group">
							<label for="looking_for"><?php esc_html_e( 'What I\'m Looking For', 'wpmatch' ); ?></label>
							<textarea id="looking_for" name="looking_for" rows="3" maxlength="300"
								placeholder="<?php esc_attr_e( 'Describe what you\'re looking for in a partner...', 'wpmatch' ); ?>"><?php echo esc_textarea( $profile_data['looking_for'] ?? '' ); ?></textarea>
							<span class="char-counter">0/300</span>
						</div>
					</div>

					<!-- Physical Attributes -->
					<div class="wpmatch-profile-section" data-section="physical">
						<h3><?php esc_html_e( 'Physical Attributes', 'wpmatch' ); ?></h3>

						<div class="wpmatch-field-row">
							<div class="wpmatch-field-group">
								<label for="height"><?php esc_html_e( 'Height (inches)', 'wpmatch' ); ?></label>
								<input type="number" id="height" name="height" min="36" max="96"
									value="<?php echo esc_attr( $profile_data['height'] ?? '' ); ?>">
							</div>

							<div class="wpmatch-field-group">
								<label for="body_type"><?php esc_html_e( 'Body Type', 'wpmatch' ); ?></label>
								<select id="body_type" name="body_type">
									<option value=""><?php esc_html_e( 'Select Body Type', 'wpmatch' ); ?></option>
									<option value="slim" <?php selected( $profile_data['body_type'] ?? '', 'slim' ); ?>><?php esc_html_e( 'Slim', 'wpmatch' ); ?></option>
									<option value="athletic" <?php selected( $profile_data['body_type'] ?? '', 'athletic' ); ?>><?php esc_html_e( 'Athletic', 'wpmatch' ); ?></option>
									<option value="average" <?php selected( $profile_data['body_type'] ?? '', 'average' ); ?>><?php esc_html_e( 'Average', 'wpmatch' ); ?></option>
									<option value="curvy" <?php selected( $profile_data['body_type'] ?? '', 'curvy' ); ?>><?php esc_html_e( 'Curvy', 'wpmatch' ); ?></option>
									<option value="heavyset" <?php selected( $profile_data['body_type'] ?? '', 'heavyset' ); ?>><?php esc_html_e( 'Heavyset', 'wpmatch' ); ?></option>
								</select>
							</div>
						</div>

						<div class="wpmatch-field-group">
							<label for="ethnicity"><?php esc_html_e( 'Ethnicity', 'wpmatch' ); ?></label>
							<select id="ethnicity" name="ethnicity">
								<option value=""><?php esc_html_e( 'Select Ethnicity', 'wpmatch' ); ?></option>
								<option value="asian" <?php selected( $profile_data['ethnicity'] ?? '', 'asian' ); ?>><?php esc_html_e( 'Asian', 'wpmatch' ); ?></option>
								<option value="black" <?php selected( $profile_data['ethnicity'] ?? '', 'black' ); ?>><?php esc_html_e( 'Black/African American', 'wpmatch' ); ?></option>
								<option value="hispanic" <?php selected( $profile_data['ethnicity'] ?? '', 'hispanic' ); ?>><?php esc_html_e( 'Hispanic/Latino', 'wpmatch' ); ?></option>
								<option value="native" <?php selected( $profile_data['ethnicity'] ?? '', 'native' ); ?>><?php esc_html_e( 'Native American', 'wpmatch' ); ?></option>
								<option value="pacific" <?php selected( $profile_data['ethnicity'] ?? '', 'pacific' ); ?>><?php esc_html_e( 'Pacific Islander', 'wpmatch' ); ?></option>
								<option value="white" <?php selected( $profile_data['ethnicity'] ?? '', 'white' ); ?>><?php esc_html_e( 'White/Caucasian', 'wpmatch' ); ?></option>
								<option value="mixed" <?php selected( $profile_data['ethnicity'] ?? '', 'mixed' ); ?>><?php esc_html_e( 'Mixed/Multi-racial', 'wpmatch' ); ?></option>
								<option value="other" <?php selected( $profile_data['ethnicity'] ?? '', 'other' ); ?>><?php esc_html_e( 'Other', 'wpmatch' ); ?></option>
							</select>
						</div>
					</div>

					<!-- Lifestyle -->
					<div class="wpmatch-profile-section" data-section="lifestyle">
						<h3><?php esc_html_e( 'Lifestyle', 'wpmatch' ); ?></h3>

						<div class="wpmatch-field-row">
							<div class="wpmatch-field-group">
								<label for="education"><?php esc_html_e( 'Education', 'wpmatch' ); ?></label>
								<select id="education" name="education">
									<option value=""><?php esc_html_e( 'Select Education Level', 'wpmatch' ); ?></option>
									<option value="high_school" <?php selected( $profile_data['education'] ?? '', 'high_school' ); ?>><?php esc_html_e( 'High School', 'wpmatch' ); ?></option>
									<option value="some_college" <?php selected( $profile_data['education'] ?? '', 'some_college' ); ?>><?php esc_html_e( 'Some College', 'wpmatch' ); ?></option>
									<option value="bachelors" <?php selected( $profile_data['education'] ?? '', 'bachelors' ); ?>><?php esc_html_e( 'Bachelor\'s Degree', 'wpmatch' ); ?></option>
									<option value="masters" <?php selected( $profile_data['education'] ?? '', 'masters' ); ?>><?php esc_html_e( 'Master\'s Degree', 'wpmatch' ); ?></option>
									<option value="phd" <?php selected( $profile_data['education'] ?? '', 'phd' ); ?>><?php esc_html_e( 'PhD/Doctorate', 'wpmatch' ); ?></option>
									<option value="trade" <?php selected( $profile_data['education'] ?? '', 'trade' ); ?>><?php esc_html_e( 'Trade School', 'wpmatch' ); ?></option>
								</select>
							</div>

							<div class="wpmatch-field-group">
								<label for="profession"><?php esc_html_e( 'Profession', 'wpmatch' ); ?></label>
								<input type="text" id="profession" name="profession"
									value="<?php echo esc_attr( $profile_data['profession'] ?? '' ); ?>"
									placeholder="<?php esc_attr_e( 'Your job title or profession', 'wpmatch' ); ?>">
							</div>
						</div>

						<div class="wpmatch-field-row">
							<div class="wpmatch-field-group">
								<label for="smoking"><?php esc_html_e( 'Smoking', 'wpmatch' ); ?></label>
								<select id="smoking" name="smoking">
									<option value=""><?php esc_html_e( 'Select Option', 'wpmatch' ); ?></option>
									<option value="never" <?php selected( $profile_data['smoking'] ?? '', 'never' ); ?>><?php esc_html_e( 'Never', 'wpmatch' ); ?></option>
									<option value="socially" <?php selected( $profile_data['smoking'] ?? '', 'socially' ); ?>><?php esc_html_e( 'Socially', 'wpmatch' ); ?></option>
									<option value="regularly" <?php selected( $profile_data['smoking'] ?? '', 'regularly' ); ?>><?php esc_html_e( 'Regularly', 'wpmatch' ); ?></option>
									<option value="trying_to_quit" <?php selected( $profile_data['smoking'] ?? '', 'trying_to_quit' ); ?>><?php esc_html_e( 'Trying to Quit', 'wpmatch' ); ?></option>
								</select>
							</div>

							<div class="wpmatch-field-group">
								<label for="drinking"><?php esc_html_e( 'Drinking', 'wpmatch' ); ?></label>
								<select id="drinking" name="drinking">
									<option value=""><?php esc_html_e( 'Select Option', 'wpmatch' ); ?></option>
									<option value="never" <?php selected( $profile_data['drinking'] ?? '', 'never' ); ?>><?php esc_html_e( 'Never', 'wpmatch' ); ?></option>
									<option value="socially" <?php selected( $profile_data['drinking'] ?? '', 'socially' ); ?>><?php esc_html_e( 'Socially', 'wpmatch' ); ?></option>
									<option value="regularly" <?php selected( $profile_data['drinking'] ?? '', 'regularly' ); ?>><?php esc_html_e( 'Regularly', 'wpmatch' ); ?></option>
									<option value="never_drinks" <?php selected( $profile_data['drinking'] ?? '', 'never_drinks' ); ?>><?php esc_html_e( 'Non-drinker', 'wpmatch' ); ?></option>
								</select>
							</div>
						</div>

						<div class="wpmatch-field-row">
							<div class="wpmatch-field-group">
								<label for="children"><?php esc_html_e( 'Children', 'wpmatch' ); ?></label>
								<select id="children" name="children">
									<option value=""><?php esc_html_e( 'Select Option', 'wpmatch' ); ?></option>
									<option value="none" <?php selected( $profile_data['children'] ?? '', 'none' ); ?>><?php esc_html_e( 'No Children', 'wpmatch' ); ?></option>
									<option value="have_children" <?php selected( $profile_data['children'] ?? '', 'have_children' ); ?>><?php esc_html_e( 'Have Children', 'wpmatch' ); ?></option>
									<option value="want_children" <?php selected( $profile_data['children'] ?? '', 'want_children' ); ?>><?php esc_html_e( 'Want Children', 'wpmatch' ); ?></option>
									<option value="no_preference" <?php selected( $profile_data['children'] ?? '', 'no_preference' ); ?>><?php esc_html_e( 'No Preference', 'wpmatch' ); ?></option>
								</select>
							</div>

							<div class="wpmatch-field-group">
								<label for="wants_children"><?php esc_html_e( 'Wants Children', 'wpmatch' ); ?></label>
								<select id="wants_children" name="wants_children">
									<option value=""><?php esc_html_e( 'Select Option', 'wpmatch' ); ?></option>
									<option value="yes" <?php selected( $profile_data['wants_children'] ?? '', 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpmatch' ); ?></option>
									<option value="no" <?php selected( $profile_data['wants_children'] ?? '', 'no' ); ?>><?php esc_html_e( 'No', 'wpmatch' ); ?></option>
									<option value="maybe" <?php selected( $profile_data['wants_children'] ?? '', 'maybe' ); ?>><?php esc_html_e( 'Maybe', 'wpmatch' ); ?></option>
									<option value="undecided" <?php selected( $profile_data['wants_children'] ?? '', 'undecided' ); ?>><?php esc_html_e( 'Undecided', 'wpmatch' ); ?></option>
								</select>
							</div>
						</div>
					</div>

					<!-- Photos -->
					<div class="wpmatch-profile-section" data-section="photos">
						<h3><?php esc_html_e( 'Photos', 'wpmatch' ); ?></h3>
						<p class="section-description"><?php esc_html_e( 'Add up to 6 photos. The first photo will be your main profile picture.', 'wpmatch' ); ?></p>

						<div id="wpmatch-photo-upload" class="wpmatch-photo-grid">
							<?php echo $this->render_photo_upload_slots( $user_id ); ?>
						</div>
					</div>

					<!-- Interests -->
					<div class="wpmatch-profile-section" data-section="interests">
						<h3><?php esc_html_e( 'Interests', 'wpmatch' ); ?></h3>
						<p class="section-description"><?php esc_html_e( 'Select your interests to help find compatible matches.', 'wpmatch' ); ?></p>

						<div id="wpmatch-interests-selector">
							<?php echo $this->render_interests_selector( $user_id ); ?>
						</div>
					</div>
				</div>

				<div class="wpmatch-form-actions">
					<button type="submit" class="wpmatch-btn-primary">
						<span class="btn-text"><?php esc_html_e( 'Save Profile', 'wpmatch' ); ?></span>
						<span class="btn-loader" style="display: none;"><?php esc_html_e( 'Saving...', 'wpmatch' ); ?></span>
					</button>
					<button type="button" class="wpmatch-btn-secondary" id="preview-profile">
						<?php esc_html_e( 'Preview Profile', 'wpmatch' ); ?>
					</button>
				</div>
			</form>

			<div id="wpmatch-profile-preview" class="wpmatch-modal" style="display: none;">
				<div class="modal-content">
					<span class="modal-close">&times;</span>
					<div id="preview-content"></div>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render swipe interface.
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	private function render_swipe_interface( $atts ) {
		ob_start();
		?>
		<div class="wpmatch-swipe-container">
			<div class="wpmatch-swipe-header">
				<div class="swipe-stats">
					<div class="stat-item">
						<span class="stat-number" id="likes-remaining">--</span>
						<span class="stat-label"><?php esc_html_e( 'Likes Left', 'wpmatch' ); ?></span>
					</div>
					<div class="stat-item">
						<span class="stat-number" id="super-likes-remaining">--</span>
						<span class="stat-label"><?php esc_html_e( 'Super Likes', 'wpmatch' ); ?></span>
					</div>
				</div>
			</div>

			<div class="wpmatch-swipe-deck" id="swipe-deck">
				<div class="loading-card">
					<div class="loading-spinner"></div>
					<p><?php esc_html_e( 'Loading potential matches...', 'wpmatch' ); ?></p>
				</div>
			</div>

			<div class="wpmatch-swipe-actions">
				<button class="swipe-btn pass-btn" data-action="pass" title="<?php esc_attr_e( 'Pass', 'wpmatch' ); ?>">
					<span class="btn-icon">✕</span>
				</button>
				<button class="swipe-btn super-like-btn" data-action="super_like" title="<?php esc_attr_e( 'Super Like', 'wpmatch' ); ?>">
					<span class="btn-icon">⭐</span>
				</button>
				<button class="swipe-btn like-btn" data-action="like" title="<?php esc_attr_e( 'Like', 'wpmatch' ); ?>">
					<span class="btn-icon">❤</span>
				</button>
				<button class="swipe-btn undo-btn" data-action="undo" title="<?php esc_attr_e( 'Undo', 'wpmatch' ); ?>">
					<span class="btn-icon">↶</span>
				</button>
			</div>

			<div class="wpmatch-swipe-info">
				<p><?php esc_html_e( 'Swipe right to like, left to pass, or use the buttons below.', 'wpmatch' ); ?></p>
			</div>
		</div>

		<!-- Match Modal -->
		<div id="wpmatch-match-modal" class="wpmatch-modal" style="display: none;">
			<div class="modal-content match-modal">
				<div class="match-celebration">
					<h2><?php esc_html_e( "It's a Match!", 'wpmatch' ); ?></h2>
					<div class="match-profiles">
						<div class="profile-pic user-profile"></div>
						<div class="match-icon">❤</div>
						<div class="profile-pic match-profile"></div>
					</div>
					<p class="match-message"></p>
					<div class="match-actions">
						<button class="wpmatch-btn-primary" id="send-message-btn">
							<?php esc_html_e( 'Send Message', 'wpmatch' ); ?>
						</button>
						<button class="wpmatch-btn-secondary" id="keep-swiping-btn">
							<?php esc_html_e( 'Keep Swiping', 'wpmatch' ); ?>
						</button>
					</div>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render matches display.
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	private function render_matches_display( $atts ) {
		ob_start();
		?>
		<div class="wpmatch-matches-container">
			<div class="wpmatch-matches-header">
				<h2><?php esc_html_e( 'Your Matches', 'wpmatch' ); ?></h2>
				<div class="matches-filters">
					<button class="filter-btn active" data-filter="all"><?php esc_html_e( 'All', 'wpmatch' ); ?></button>
					<button class="filter-btn" data-filter="recent"><?php esc_html_e( 'Recent', 'wpmatch' ); ?></button>
					<button class="filter-btn" data-filter="online"><?php esc_html_e( 'Online', 'wpmatch' ); ?></button>
				</div>
				<div class="view-toggle">
					<button class="view-btn <?php echo 'grid' === $atts['view'] ? 'active' : ''; ?>" data-view="grid">
						<span class="view-icon">⊞</span>
					</button>
					<button class="view-btn <?php echo 'list' === $atts['view'] ? 'active' : ''; ?>" data-view="list">
						<span class="view-icon">☰</span>
					</button>
				</div>
			</div>

			<div class="wpmatch-matches-grid" id="matches-grid" data-view="<?php echo esc_attr( $atts['view'] ); ?>">
				<div class="loading-matches">
					<div class="loading-spinner"></div>
					<p><?php esc_html_e( 'Loading your matches...', 'wpmatch' ); ?></p>
				</div>
			</div>

			<div class="wpmatch-load-more" style="display: none;">
				<button class="wpmatch-btn-secondary" id="load-more-matches">
					<?php esc_html_e( 'Load More Matches', 'wpmatch' ); ?>
				</button>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get user profile data.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @return array Profile data.
	 */
	private function get_user_profile_data( $user_id ) {
		global $wpdb;

		$profile = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wpmatch_user_profiles WHERE user_id = %d",
				$user_id
			),
			ARRAY_A
		);

		if ( ! $profile ) {
			// Return default structure.
			return array(
				'completion_percentage' => 0,
				'age'                   => '',
				'gender'                => '',
				'orientation'           => '',
				'location'              => '',
				'about_me'              => '',
				'looking_for'           => '',
				'height'                => '',
				'body_type'             => '',
				'ethnicity'             => '',
				'education'             => '',
				'profession'            => '',
				'smoking'               => '',
				'drinking'              => '',
				'children'              => '',
				'wants_children'        => '',
			);
		}

		return $profile;
	}

	/**
	 * Render photo upload slots.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @return string HTML output.
	 */
	private function render_photo_upload_slots( $user_id ) {
		// Get existing photos.
		global $wpdb;
		$photos = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wpmatch_user_media
			WHERE user_id = %d AND media_type = 'photo'
			ORDER BY is_primary DESC, display_order ASC",
				$user_id
			)
		);

		ob_start();

		for ( $i = 0; $i < 6; $i++ ) {
			$photo      = $photos[ $i ] ?? null;
			$is_primary = $photo && $photo->is_primary;
			?>
			<div class="photo-slot" data-slot="<?php echo esc_attr( $i ); ?>">
				<?php if ( $photo ) : ?>
					<img src="<?php echo esc_url( $photo->file_path ); ?>" alt="Profile photo">
					<div class="photo-overlay">
						<?php if ( $is_primary ) : ?>
							<span class="primary-badge"><?php esc_html_e( 'Main', 'wpmatch' ); ?></span>
						<?php else : ?>
							<button type="button" class="make-primary-btn" data-media-id="<?php echo esc_attr( $photo->media_id ); ?>">
								<?php esc_html_e( 'Make Main', 'wpmatch' ); ?>
							</button>
						<?php endif; ?>
						<button type="button" class="delete-photo-btn" data-media-id="<?php echo esc_attr( $photo->media_id ); ?>">
							<?php esc_html_e( 'Delete', 'wpmatch' ); ?>
						</button>
					</div>
				<?php else : ?>
					<div class="photo-upload-placeholder">
						<input type="file" class="photo-input" name="photos[]" accept="image/*" data-slot="<?php echo esc_attr( $i ); ?>">
						<div class="upload-icon">📷</div>
						<span class="upload-text">
							<?php echo 0 === $i ? esc_html__( 'Add Main Photo', 'wpmatch' ) : esc_html__( 'Add Photo', 'wpmatch' ); ?>
						</span>
					</div>
				<?php endif; ?>
			</div>
			<?php
		}

		return ob_get_clean();
	}

	/**
	 * Render interests selector.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @return string HTML output.
	 */
	private function render_interests_selector( $user_id ) {
		// Get user's current interests.
		global $wpdb;
		$user_interests = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT interest_name FROM {$wpdb->prefix}wpmatch_user_interests WHERE user_id = %d",
				$user_id
			)
		);

		// Get available interest categories and interests.
		$interest_categories = $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}wpmatch_interest_categories
			WHERE is_active = 1 ORDER BY sort_order, category_name"
		);

		ob_start();
		?>
		<div class="interests-container">
			<?php foreach ( $interest_categories as $category ) : ?>
				<?php
				$interests = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT * FROM {$wpdb->prefix}wpmatch_predefined_interests
					WHERE category_id = %d AND is_active = 1
					ORDER BY sort_order, interest_name",
						$category->id
					)
				);
				?>

				<?php if ( $interests ) : ?>
					<div class="interest-category" data-category="<?php echo esc_attr( $category->category_key ); ?>">
						<h4><?php echo esc_html( $category->category_name ); ?></h4>
						<div class="interest-tags">
							<?php foreach ( $interests as $interest ) : ?>
								<label class="interest-tag <?php echo in_array( $interest->interest_name, $user_interests, true ) ? 'selected' : ''; ?>">
									<input type="checkbox" name="interests[]" value="<?php echo esc_attr( $interest->interest_name ); ?>"
										data-category="<?php echo esc_attr( $category->category_key ); ?>"
										<?php checked( in_array( $interest->interest_name, $user_interests, true ) ); ?>>
									<span class="tag-text"><?php echo esc_html( $interest->interest_name ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>
			<?php endforeach; ?>

			<div class="custom-interests">
				<h4><?php esc_html_e( 'Add Custom Interest', 'wpmatch' ); ?></h4>
				<div class="custom-interest-input">
					<input type="text" id="custom-interest" placeholder="<?php esc_attr_e( 'Type a custom interest...', 'wpmatch' ); ?>" maxlength="50">
					<button type="button" id="add-custom-interest" class="wpmatch-btn-secondary">
						<?php esc_html_e( 'Add', 'wpmatch' ); ?>
					</button>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Check if user has complete profile.
	 *
	 * @since 1.0.0
	 * @return bool True if profile is complete.
	 */
	private function user_has_complete_profile() {
		$user_id      = get_current_user_id();
		$profile_data = $this->get_user_profile_data( $user_id );

		// Check required fields.
		$required_fields = array( 'age', 'gender', 'location', 'about_me' );

		foreach ( $required_fields as $field ) {
			if ( empty( $profile_data[ $field ] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get login required message.
	 *
	 * @since 1.0.0
	 * @return string HTML message.
	 */
	private function login_required_message() {
		ob_start();
		?>
		<div class="wpmatch-notice wpmatch-notice-info">
			<p><?php esc_html_e( 'Please log in to access this feature.', 'wpmatch' ); ?></p>
			<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="wpmatch-btn-primary">
				<?php esc_html_e( 'Log In', 'wpmatch' ); ?>
			</a>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get complete profile message.
	 *
	 * @since 1.0.0
	 * @return string HTML message.
	 */
	private function complete_profile_message() {
		ob_start();
		?>
		<div class="wpmatch-notice wpmatch-notice-warning">
			<p><?php esc_html_e( 'Please complete your profile before browsing potential matches.', 'wpmatch' ); ?></p>
			<a href="<?php echo esc_url( add_query_arg( 'wpmatch_action', 'edit_profile' ) ); ?>" class="wpmatch-btn-primary">
				<?php esc_html_e( 'Complete Profile', 'wpmatch' ); ?>
			</a>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Enqueue profile assets.
	 *
	 * @since 1.0.0
	 */
	private function enqueue_profile_assets() {
		wp_enqueue_style( 'wpmatch-profile', plugin_dir_url( __FILE__ ) . '../public/css/wpmatch-profile.css', array(), $this->version );
		wp_enqueue_script( 'wpmatch-profile', plugin_dir_url( __FILE__ ) . '../public/js/wpmatch-profile.js', array( 'jquery' ), $this->version, true );

		wp_localize_script(
			'wpmatch-profile',
			'wpMatchProfile',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wpmatch_profile_nonce' ),
				'userId'  => get_current_user_id(),
				'strings' => array(
					'saving'        => __( 'Saving...', 'wpmatch' ),
					'saved'         => __( 'Profile saved!', 'wpmatch' ),
					'error'         => __( 'Error saving profile. Please try again.', 'wpmatch' ),
					'confirmDelete' => __( 'Are you sure you want to delete this photo?', 'wpmatch' ),
				),
			)
		);
	}

	/**
	 * Enqueue swipe assets.
	 *
	 * @since 1.0.0
	 */
	private function enqueue_swipe_assets() {
		wp_enqueue_style( 'wpmatch-swipe', plugin_dir_url( __FILE__ ) . '../public/css/wpmatch-swipe.css', array(), $this->version );
		wp_enqueue_script( 'wpmatch-swipe', plugin_dir_url( __FILE__ ) . '../public/js/wpmatch-swipe.js', array( 'jquery' ), $this->version, true );

		wp_localize_script(
			'wpmatch-swipe',
			'wpMatchSwipe',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'restUrl' => rest_url( 'wpmatch/v1/' ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'userId'  => get_current_user_id(),
				'strings' => array(
					'noMoreMatches' => __( 'No more potential matches at the moment. Check back later!', 'wpmatch' ),
					'loadingError'  => __( 'Error loading matches. Please try again.', 'wpmatch' ),
					'actionError'   => __( 'Error processing action. Please try again.', 'wpmatch' ),
				),
			)
		);
	}

	/**
	 * Enqueue matches assets.
	 *
	 * @since 1.0.0
	 */
	private function enqueue_matches_assets() {
		wp_enqueue_style( 'wpmatch-matches', plugin_dir_url( __FILE__ ) . '../public/css/wpmatch-matches.css', array(), $this->version );
		wp_enqueue_script( 'wpmatch-matches', plugin_dir_url( __FILE__ ) . '../public/js/wpmatch-matches.js', array( 'jquery' ), $this->version, true );

		wp_localize_script(
			'wpmatch-matches',
			'wpMatchMatches',
			array(
				'restUrl' => rest_url( 'wpmatch/v1/' ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'userId'  => get_current_user_id(),
			)
		);
	}

	/**
	 * Enqueue messaging assets.
	 *
	 * @since 1.0.0
	 */
	private function enqueue_messaging_assets() {
		wp_enqueue_style( 'wpmatch-messaging', plugin_dir_url( __FILE__ ) . '../public/css/wpmatch-messaging.css', array(), $this->version );
		wp_enqueue_script( 'wpmatch-messaging', plugin_dir_url( __FILE__ ) . '../public/js/wpmatch-messaging.js', array( 'jquery' ), $this->version, true );

		wp_localize_script(
			'wpmatch-messaging',
			'wpMatchMessaging',
			array(
				'restUrl' => rest_url( 'wpmatch/v1/' ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'userId'  => get_current_user_id(),
			)
		);
	}

	/**
	 * Enqueue search assets.
	 *
	 * @since 1.0.0
	 */
	private function enqueue_search_assets() {
		wp_enqueue_style( 'wpmatch-search', plugin_dir_url( __FILE__ ) . '../public/css/wpmatch-search.css', array(), $this->version );
		wp_enqueue_script( 'wpmatch-search', plugin_dir_url( __FILE__ ) . '../public/js/wpmatch-search.js', array( 'jquery' ), $this->version, true );

		wp_localize_script(
			'wpmatch-search',
			'wpMatchSearch',
			array(
				'restUrl' => rest_url( 'wpmatch/v1/' ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'userId'  => get_current_user_id(),
				'strings' => array(
					'pleaseSetFilters'      => __( 'Please set some filters before saving', 'wpmatch' ),
					'enterSearchName'       => __( 'Enter a name for this search:', 'wpmatch' ),
					'searchSavedSuccess'    => __( 'Search saved successfully!', 'wpmatch' ),
					'searchSaveFailed'      => __( 'Failed to save search:', 'wpmatch' ),
					'unknownError'          => __( 'Unknown error', 'wpmatch' ),
					'networkError'          => __( 'Network error. Could not save search.', 'wpmatch' ),
					'failedToLikeUser'      => __( 'Failed to like user:', 'wpmatch' ),
					'networkErrorTryAgain'  => __( 'Network error. Please try again.', 'wpmatch' ),
				),
			)
		);
	}

	/**
	 * Enqueue dashboard assets.
	 *
	 * @since 1.0.0
	 */
	private function enqueue_dashboard_assets() {
		wp_enqueue_style( 'wpmatch-dashboard', plugin_dir_url( __FILE__ ) . '../public/css/wpmatch-dashboard.css', array(), $this->version );
		wp_enqueue_script( 'wpmatch-dashboard', plugin_dir_url( __FILE__ ) . '../public/js/wpmatch-dashboard.js', array( 'jquery' ), $this->version, true );

		wp_localize_script(
			'wpmatch-dashboard',
			'wpMatchDashboard',
			array(
				'restUrl' => rest_url( 'wpmatch/v1/' ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'userId'  => get_current_user_id(),
			)
		);
	}

	// Additional methods for rendering other shortcodes will be added here...

	/**
	 * Render messaging interface.
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	private function render_messaging_interface( $atts ) {
		$conversation_id = $atts['conversation_id'];

		ob_start();
		?>
		<div class="wpmatch-messaging-container" data-layout="<?php echo esc_attr( $atts['layout'] ); ?>">
			<div class="messaging-sidebar">
				<div class="conversations-header">
					<h3><?php esc_html_e( 'Conversations', 'wpmatch' ); ?></h3>
					<div class="new-message-btn" title="<?php esc_attr_e( 'New Message', 'wpmatch' ); ?>">
						<span class="btn-icon">✉</span>
					</div>
				</div>

				<div class="conversations-search">
					<input type="text" id="conversations-search" placeholder="<?php esc_attr_e( 'Search conversations...', 'wpmatch' ); ?>">
				</div>

				<div class="conversations-list" id="conversations-list">
					<div class="loading-conversations">
						<div class="loading-spinner"></div>
						<p><?php esc_html_e( 'Loading conversations...', 'wpmatch' ); ?></p>
					</div>
				</div>
			</div>

			<div class="messaging-main">
				<?php if ( $conversation_id ) : ?>
					<div class="conversation-view" data-conversation-id="<?php echo esc_attr( $conversation_id ); ?>">
						<?php echo $this->render_conversation_view( $conversation_id ); ?>
					</div>
				<?php else : ?>
					<div class="no-conversation-selected">
						<div class="empty-state">
							<span class="empty-icon">💬</span>
							<h3><?php esc_html_e( 'Select a Conversation', 'wpmatch' ); ?></h3>
							<p><?php esc_html_e( 'Choose a conversation from the sidebar to start messaging.', 'wpmatch' ); ?></p>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<!-- New Message Modal -->
		<div id="new-message-modal" class="wpmatch-modal" style="display: none;">
			<div class="modal-content">
				<span class="modal-close">&times;</span>
				<h3><?php esc_html_e( 'Start New Conversation', 'wpmatch' ); ?></h3>
				<div class="new-message-form">
					<div class="recipient-search">
						<input type="text" id="recipient-search" placeholder="<?php esc_attr_e( 'Search your matches...', 'wpmatch' ); ?>">
						<div class="recipient-results" id="recipient-results"></div>
					</div>
					<div class="message-compose">
						<textarea id="new-message-text" placeholder="<?php esc_attr_e( 'Type your message...', 'wpmatch' ); ?>" rows="3"></textarea>
						<button type="button" id="send-new-message" class="wpmatch-btn-primary">
							<?php esc_html_e( 'Send Message', 'wpmatch' ); ?>
						</button>
					</div>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render search interface.
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	private function render_search_interface( $atts ) {
		ob_start();
		?>
		<div class="wpmatch-search-container">
			<div class="search-header">
				<h2><?php esc_html_e( 'Discover People', 'wpmatch' ); ?></h2>
				<div class="search-toggle">
					<button class="filter-toggle-btn" id="toggle-filters">
						<span class="filter-icon">⚙</span>
						<span class="filter-text"><?php esc_html_e( 'Filters', 'wpmatch' ); ?></span>
					</button>
				</div>
			</div>

			<?php if ( 'basic' !== $atts['filters'] ) : ?>
				<div class="search-filters" id="search-filters">
					<div class="filters-row">
						<div class="filter-group">
							<label for="age-range"><?php esc_html_e( 'Age Range', 'wpmatch' ); ?></label>
							<div class="range-inputs">
								<input type="number" id="min-age" name="min_age" min="18" max="99" value="18" class="age-input">
								<span class="range-separator">-</span>
								<input type="number" id="max-age" name="max_age" min="18" max="99" value="35" class="age-input">
							</div>
						</div>

						<div class="filter-group">
							<label for="distance"><?php esc_html_e( 'Distance (miles)', 'wpmatch' ); ?></label>
							<input type="range" id="distance" name="distance" min="1" max="500" value="50" class="distance-slider">
							<span class="distance-value">50 miles</span>
						</div>

						<div class="filter-group">
							<label for="gender-filter"><?php esc_html_e( 'Gender', 'wpmatch' ); ?></label>
							<select id="gender-filter" name="gender">
								<option value=""><?php esc_html_e( 'Any', 'wpmatch' ); ?></option>
								<option value="male"><?php esc_html_e( 'Male', 'wpmatch' ); ?></option>
								<option value="female"><?php esc_html_e( 'Female', 'wpmatch' ); ?></option>
								<option value="non-binary"><?php esc_html_e( 'Non-binary', 'wpmatch' ); ?></option>
								<option value="other"><?php esc_html_e( 'Other', 'wpmatch' ); ?></option>
							</select>
						</div>
					</div>

					<?php if ( 'advanced' === $atts['filters'] || 'all' === $atts['filters'] ) : ?>
						<div class="advanced-filters">
							<div class="filters-row">
								<div class="filter-group">
									<label for="body-type-filter"><?php esc_html_e( 'Body Type', 'wpmatch' ); ?></label>
									<select id="body-type-filter" name="body_type">
										<option value=""><?php esc_html_e( 'Any', 'wpmatch' ); ?></option>
										<option value="slim"><?php esc_html_e( 'Slim', 'wpmatch' ); ?></option>
										<option value="athletic"><?php esc_html_e( 'Athletic', 'wpmatch' ); ?></option>
										<option value="average"><?php esc_html_e( 'Average', 'wpmatch' ); ?></option>
										<option value="curvy"><?php esc_html_e( 'Curvy', 'wpmatch' ); ?></option>
										<option value="heavyset"><?php esc_html_e( 'Heavyset', 'wpmatch' ); ?></option>
									</select>
								</div>

								<div class="filter-group">
									<label for="education-filter"><?php esc_html_e( 'Education', 'wpmatch' ); ?></label>
									<select id="education-filter" name="education">
										<option value=""><?php esc_html_e( 'Any', 'wpmatch' ); ?></option>
										<option value="high_school"><?php esc_html_e( 'High School', 'wpmatch' ); ?></option>
										<option value="some_college"><?php esc_html_e( 'Some College', 'wpmatch' ); ?></option>
										<option value="bachelors"><?php esc_html_e( 'Bachelor\'s Degree', 'wpmatch' ); ?></option>
										<option value="masters"><?php esc_html_e( 'Master\'s Degree', 'wpmatch' ); ?></option>
										<option value="phd"><?php esc_html_e( 'PhD/Doctorate', 'wpmatch' ); ?></option>
									</select>
								</div>

								<div class="filter-group">
									<label for="children-filter"><?php esc_html_e( 'Children', 'wpmatch' ); ?></label>
									<select id="children-filter" name="children">
										<option value=""><?php esc_html_e( 'Any', 'wpmatch' ); ?></option>
										<option value="none"><?php esc_html_e( 'No Children', 'wpmatch' ); ?></option>
										<option value="have_children"><?php esc_html_e( 'Have Children', 'wpmatch' ); ?></option>
										<option value="want_children"><?php esc_html_e( 'Want Children', 'wpmatch' ); ?></option>
									</select>
								</div>
							</div>

							<div class="filters-row">
								<div class="filter-group">
									<label><?php esc_html_e( 'Lifestyle', 'wpmatch' ); ?></label>
									<div class="checkbox-group">
										<label class="checkbox-label">
											<input type="checkbox" name="smoking" value="never">
											<span><?php esc_html_e( 'Non-smoker', 'wpmatch' ); ?></span>
										</label>
										<label class="checkbox-label">
											<input type="checkbox" name="drinking" value="never">
											<span><?php esc_html_e( 'Non-drinker', 'wpmatch' ); ?></span>
										</label>
									</div>
								</div>

								<div class="filter-group">
									<label><?php esc_html_e( 'Other Preferences', 'wpmatch' ); ?></label>
									<div class="checkbox-group">
										<label class="checkbox-label">
											<input type="checkbox" name="verified_only" value="1">
											<span><?php esc_html_e( 'Verified profiles only', 'wpmatch' ); ?></span>
										</label>
										<label class="checkbox-label">
											<input type="checkbox" name="online_only" value="1">
											<span><?php esc_html_e( 'Online now', 'wpmatch' ); ?></span>
										</label>
										<label class="checkbox-label">
											<input type="checkbox" name="with_photos" value="1">
											<span><?php esc_html_e( 'With photos', 'wpmatch' ); ?></span>
										</label>
									</div>
								</div>
							</div>
						</div>
					<?php endif; ?>

					<div class="filter-actions">
						<button type="button" id="apply-filters" class="wpmatch-btn-primary">
							<?php esc_html_e( 'Apply Filters', 'wpmatch' ); ?>
						</button>
						<button type="button" id="clear-filters" class="wpmatch-btn-secondary">
							<?php esc_html_e( 'Clear All', 'wpmatch' ); ?>
						</button>
					</div>
				</div>
			<?php endif; ?>

			<div class="search-results-header">
				<div class="results-count">
					<span id="results-count-text"><?php esc_html_e( 'Loading...', 'wpmatch' ); ?></span>
				</div>
				<div class="view-options">
					<div class="sort-dropdown">
						<select id="sort-by" name="sort_by">
							<option value="compatibility"><?php esc_html_e( 'Best Match', 'wpmatch' ); ?></option>
							<option value="distance"><?php esc_html_e( 'Distance', 'wpmatch' ); ?></option>
							<option value="last_active"><?php esc_html_e( 'Last Active', 'wpmatch' ); ?></option>
							<option value="newest"><?php esc_html_e( 'Newest Members', 'wpmatch' ); ?></option>
						</select>
					</div>
					<div class="layout-toggle">
						<button class="layout-btn <?php echo 'grid' === $atts['layout'] ? 'active' : ''; ?>" data-layout="grid">
							<span class="layout-icon">⊞</span>
						</button>
						<button class="layout-btn <?php echo 'list' === $atts['layout'] ? 'active' : ''; ?>" data-layout="list">
							<span class="layout-icon">☰</span>
						</button>
					</div>
				</div>
			</div>

			<div class="search-results" id="search-results" data-layout="<?php echo esc_attr( $atts['layout'] ); ?>">
				<div class="loading-results">
					<div class="loading-spinner"></div>
					<p><?php esc_html_e( 'Searching for compatible matches...', 'wpmatch' ); ?></p>
				</div>
			</div>

			<div class="search-pagination" id="search-pagination" style="display: none;">
				<button class="wpmatch-btn-secondary" id="load-more-results">
					<?php esc_html_e( 'Load More Results', 'wpmatch' ); ?>
				</button>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render dashboard.
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	private function render_dashboard( $atts ) {
		$user_id = get_current_user_id();
		$stats   = $this->get_user_stats( $user_id );

		ob_start();
		?>
		<div class="wpmatch-dashboard-container">
			<div class="dashboard-header">
				<h2><?php esc_html_e( 'Your Dating Dashboard', 'wpmatch' ); ?></h2>
				<div class="last-login">
					<?php esc_html_e( 'Last active:', 'wpmatch' ); ?>
					<span id="last-active-time"><?php echo esc_html( human_time_diff( strtotime( get_user_meta( $user_id, 'wpmatch_last_activity', true ) ) ) ); ?> <?php esc_html_e( 'ago', 'wpmatch' ); ?></span>
				</div>
			</div>

			<div class="dashboard-stats">
				<div class="stat-card">
					<div class="stat-icon">👁</div>
					<div class="stat-content">
						<span class="stat-number"><?php echo esc_html( $stats['profile_views'] ?? 0 ); ?></span>
						<span class="stat-label"><?php esc_html_e( 'Profile Views', 'wpmatch' ); ?></span>
					</div>
				</div>

				<div class="stat-card">
					<div class="stat-icon">❤</div>
					<div class="stat-content">
						<span class="stat-number"><?php echo esc_html( $stats['likes_received'] ?? 0 ); ?></span>
						<span class="stat-label"><?php esc_html_e( 'Likes Received', 'wpmatch' ); ?></span>
					</div>
				</div>

				<div class="stat-card">
					<div class="stat-icon">🔥</div>
					<div class="stat-content">
						<span class="stat-number"><?php echo esc_html( $stats['matches'] ?? 0 ); ?></span>
						<span class="stat-label"><?php esc_html_e( 'Matches', 'wpmatch' ); ?></span>
					</div>
				</div>

				<div class="stat-card">
					<div class="stat-icon">💬</div>
					<div class="stat-content">
						<span class="stat-number"><?php echo esc_html( $stats['conversations'] ?? 0 ); ?></span>
						<span class="stat-label"><?php esc_html_e( 'Conversations', 'wpmatch' ); ?></span>
					</div>
				</div>
			</div>

			<div class="dashboard-sections">
				<div class="dashboard-section">
					<h3><?php esc_html_e( 'Recent Activity', 'wpmatch' ); ?></h3>
					<div class="activity-feed" id="activity-feed">
						<div class="loading-activity">
							<div class="loading-spinner"></div>
							<p><?php esc_html_e( 'Loading recent activity...', 'wpmatch' ); ?></p>
						</div>
					</div>
				</div>

				<div class="dashboard-section">
					<h3><?php esc_html_e( 'Quick Actions', 'wpmatch' ); ?></h3>
					<div class="quick-actions">
						<a href="<?php echo esc_url( add_query_arg( 'wpmatch_page', 'swipe' ) ); ?>" class="action-card">
							<div class="action-icon">🎯</div>
							<div class="action-content">
								<span class="action-title"><?php esc_html_e( 'Discover Matches', 'wpmatch' ); ?></span>
								<span class="action-desc"><?php esc_html_e( 'Swipe through potential matches', 'wpmatch' ); ?></span>
							</div>
						</a>

						<a href="<?php echo esc_url( add_query_arg( 'wpmatch_page', 'messages' ) ); ?>" class="action-card">
							<div class="action-icon">💬</div>
							<div class="action-content">
								<span class="action-title"><?php esc_html_e( 'Messages', 'wpmatch' ); ?></span>
								<span class="action-desc"><?php esc_html_e( 'Chat with your matches', 'wpmatch' ); ?></span>
								<?php if ( $stats['unread_messages'] > 0 ) : ?>
									<span class="unread-badge"><?php echo esc_html( $stats['unread_messages'] ); ?></span>
								<?php endif; ?>
							</div>
						</a>

						<a href="<?php echo esc_url( add_query_arg( 'wpmatch_page', 'profile' ) ); ?>" class="action-card">
							<div class="action-icon">👤</div>
							<div class="action-content">
								<span class="action-title"><?php esc_html_e( 'Edit Profile', 'wpmatch' ); ?></span>
								<span class="action-desc"><?php esc_html_e( 'Update your dating profile', 'wpmatch' ); ?></span>
							</div>
						</a>

						<a href="<?php echo esc_url( add_query_arg( 'wpmatch_page', 'search' ) ); ?>" class="action-card">
							<div class="action-icon">🔍</div>
							<div class="action-content">
								<span class="action-title"><?php esc_html_e( 'Advanced Search', 'wpmatch' ); ?></span>
								<span class="action-desc"><?php esc_html_e( 'Find specific types of people', 'wpmatch' ); ?></span>
							</div>
						</a>
					</div>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render conversation view.
	 *
	 * @since 1.0.0
	 * @param string $conversation_id Conversation ID.
	 * @return string HTML output.
	 */
	private function render_conversation_view( $conversation_id ) {
		ob_start();
		?>
		<div class="conversation-header">
			<div class="participant-info">
				<div class="participant-avatar"></div>
				<div class="participant-details">
					<h4 class="participant-name"></h4>
					<span class="participant-status"></span>
				</div>
			</div>
			<div class="conversation-actions">
				<button class="action-btn" id="view-profile-btn" title="<?php esc_attr_e( 'View Profile', 'wpmatch' ); ?>">
					<span class="btn-icon">👤</span>
				</button>
				<button class="action-btn" id="block-user-btn" title="<?php esc_attr_e( 'Block User', 'wpmatch' ); ?>">
					<span class="btn-icon">🚫</span>
				</button>
			</div>
		</div>

		<div class="messages-container" id="messages-container">
			<div class="loading-messages">
				<div class="loading-spinner"></div>
				<p><?php esc_html_e( 'Loading messages...', 'wpmatch' ); ?></p>
			</div>
		</div>

		<div class="message-compose">
			<div class="compose-input">
				<textarea id="message-input" placeholder="<?php esc_attr_e( 'Type your message...', 'wpmatch' ); ?>" rows="1"></textarea>
				<div class="compose-actions">
					<button class="compose-btn" id="emoji-btn" title="<?php esc_attr_e( 'Add Emoji', 'wpmatch' ); ?>">
						<span class="btn-icon">😊</span>
					</button>
					<button class="compose-btn" id="attach-btn" title="<?php esc_attr_e( 'Attach Image', 'wpmatch' ); ?>">
						<span class="btn-icon">📎</span>
					</button>
					<button class="compose-btn send-btn" id="send-btn" title="<?php esc_attr_e( 'Send Message', 'wpmatch' ); ?>">
						<span class="btn-icon">➤</span>
					</button>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get user statistics.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @return array User statistics.
	 */
	private function get_user_stats( $user_id ) {
		global $wpdb;

		// Get profile views (placeholder - would need actual tracking).
		$profile_views = get_user_meta( $user_id, 'wpmatch_profile_views', true ) ?: 0;

		// Get likes received.
		$likes_received = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}wpmatch_user_actions
			WHERE target_user_id = %d AND action_type IN ('like', 'super_like') AND is_undone = 0",
				$user_id
			)
		);

		// Get matches.
		$matches = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}wpmatch_matches
			WHERE (user1_id = %d OR user2_id = %d) AND status = 'active'",
				$user_id,
				$user_id
			)
		);

		// Get conversations.
		$conversations = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}wpmatch_conversations
			WHERE (user1_id = %d OR user2_id = %d)
			AND NOT (
				(user1_id = %d AND user1_deleted = 1)
				OR (user2_id = %d AND user2_deleted = 1)
			)",
				$user_id,
				$user_id,
				$user_id,
				$user_id
			)
		);

		// Get unread messages.
		$unread_messages = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}wpmatch_messages
			WHERE recipient_id = %d AND is_read = 0 AND is_deleted_recipient = 0",
				$user_id
			)
		);

		return array(
			'profile_views'   => $profile_views,
			'likes_received'  => $likes_received ?: 0,
			'matches'         => $matches ?: 0,
			'conversations'   => $conversations ?: 0,
			'unread_messages' => $unread_messages ?: 0,
		);
	}
}