<?php
/**
 * User Registration Interface
 *
 * @package WPMatch
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Check if user is already logged in
if ( is_user_logged_in() ) {
	wp_redirect( home_url( '/wpmatch/dashboard' ) );
	exit;
}

$settings = get_option( 'wpmatch_settings', array() );
$min_age = isset( $settings['min_age'] ) ? $settings['min_age'] : 18;
?>

<div class="wpmatch-registration-container">
	<div class="wpmatch-registration-wrapper">
		<!-- Left Side - Branding & Info -->
		<div class="wpmatch-registration-branding">
			<div class="branding-content">
				<div class="wpmatch-logo">
					<h1>WPMatch</h1>
					<p class="tagline"><?php esc_html_e( 'Find Your Perfect Match', 'wpmatch' ); ?></p>
				</div>

				<div class="features-list">
					<div class="feature-item">
						<div class="feature-icon">
							<span class="dashicons dashicons-heart"></span>
						</div>
						<div class="feature-text">
							<h3><?php esc_html_e( 'Smart Matching', 'wpmatch' ); ?></h3>
							<p><?php esc_html_e( 'AI-powered compatibility matching based on your preferences and interests', 'wpmatch' ); ?></p>
						</div>
					</div>

					<div class="feature-item">
						<div class="feature-icon">
							<span class="dashicons dashicons-shield"></span>
						</div>
						<div class="feature-text">
							<h3><?php esc_html_e( 'Safe & Secure', 'wpmatch' ); ?></h3>
							<p><?php esc_html_e( 'Photo verification, profile screening, and 24/7 moderation for your safety', 'wpmatch' ); ?></p>
						</div>
					</div>

					<div class="feature-item">
						<div class="feature-icon">
							<span class="dashicons dashicons-format-chat"></span>
						</div>
						<div class="feature-text">
							<h3><?php esc_html_e( 'Real Connections', 'wpmatch' ); ?></h3>
							<p><?php esc_html_e( 'Advanced messaging, video chat, and meaningful conversation tools', 'wpmatch' ); ?></p>
						</div>
					</div>
				</div>

				<div class="success-stats">
					<div class="stat-item">
						<div class="stat-number">2.5M+</div>
						<div class="stat-label"><?php esc_html_e( 'Happy Members', 'wpmatch' ); ?></div>
					</div>
					<div class="stat-item">
						<div class="stat-number">150K+</div>
						<div class="stat-label"><?php esc_html_e( 'Successful Matches', 'wpmatch' ); ?></div>
					</div>
					<div class="stat-item">
						<div class="stat-number">50K+</div>
						<div class="stat-label"><?php esc_html_e( 'Love Stories', 'wpmatch' ); ?></div>
					</div>
				</div>
			</div>
		</div>

		<!-- Right Side - Registration Form -->
		<div class="wpmatch-registration-form">
			<div class="form-container">
				<div class="form-header">
					<h2><?php esc_html_e( 'Create Your Account', 'wpmatch' ); ?></h2>
					<p><?php esc_html_e( 'Start your journey to finding meaningful connections', 'wpmatch' ); ?></p>
				</div>

				<!-- Progress Steps -->
				<div class="registration-progress">
					<div class="progress-step active" data-step="1">
						<div class="step-number">1</div>
						<div class="step-label"><?php esc_html_e( 'Account', 'wpmatch' ); ?></div>
					</div>
					<div class="progress-step" data-step="2">
						<div class="step-number">2</div>
						<div class="step-label"><?php esc_html_e( 'Profile', 'wpmatch' ); ?></div>
					</div>
					<div class="progress-step" data-step="3">
						<div class="step-number">3</div>
						<div class="step-label"><?php esc_html_e( 'Photos', 'wpmatch' ); ?></div>
					</div>
					<div class="progress-step" data-step="4">
						<div class="step-number">4</div>
						<div class="step-label"><?php esc_html_e( 'Preferences', 'wpmatch' ); ?></div>
					</div>
				</div>

				<form id="wpmatch-registration-form" class="registration-form" method="post">
					<?php wp_nonce_field( 'wpmatch_registration', 'wpmatch_registration_nonce' ); ?>

					<!-- Step 1: Account Information -->
					<div class="form-step active" data-step="1">
						<div class="step-content">
							<h3><?php esc_html_e( 'Account Information', 'wpmatch' ); ?></h3>
							<p class="step-description"><?php esc_html_e( 'Let\'s start with the basics', 'wpmatch' ); ?></p>

							<div class="form-grid">
								<div class="form-group">
									<label for="first_name"><?php esc_html_e( 'First Name', 'wpmatch' ); ?> <span class="required">*</span></label>
									<input type="text" id="first_name" name="first_name" required maxlength="50">
									<div class="form-error" id="first_name_error"></div>
								</div>

								<div class="form-group">
									<label for="last_name"><?php esc_html_e( 'Last Name', 'wpmatch' ); ?> <span class="required">*</span></label>
									<input type="text" id="last_name" name="last_name" required maxlength="50">
									<div class="form-error" id="last_name_error"></div>
								</div>

								<div class="form-group full-width">
									<label for="email"><?php esc_html_e( 'Email Address', 'wpmatch' ); ?> <span class="required">*</span></label>
									<input type="email" id="email" name="email" required>
									<div class="form-error" id="email_error"></div>
								</div>

								<div class="form-group">
									<label for="password"><?php esc_html_e( 'Password', 'wpmatch' ); ?> <span class="required">*</span></label>
									<div class="password-input">
										<input type="password" id="password" name="password" required minlength="8">
										<button type="button" class="password-toggle" onclick="togglePassword('password')">
											<span class="dashicons dashicons-visibility"></span>
										</button>
									</div>
									<div class="password-strength" id="password_strength"></div>
									<div class="form-error" id="password_error"></div>
								</div>

								<div class="form-group">
									<label for="confirm_password"><?php esc_html_e( 'Confirm Password', 'wpmatch' ); ?> <span class="required">*</span></label>
									<div class="password-input">
										<input type="password" id="confirm_password" name="confirm_password" required>
										<button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
											<span class="dashicons dashicons-visibility"></span>
										</button>
									</div>
									<div class="form-error" id="confirm_password_error"></div>
								</div>

								<div class="form-group">
									<label for="birth_date"><?php esc_html_e( 'Date of Birth', 'wpmatch' ); ?> <span class="required">*</span></label>
									<input type="date" id="birth_date" name="birth_date" required max="<?php echo date( 'Y-m-d', strtotime( '-' . $min_age . ' years' ) ); ?>">
									<div class="form-help"><?php printf( esc_html__( 'You must be at least %d years old', 'wpmatch' ), $min_age ); ?></div>
									<div class="form-error" id="birth_date_error"></div>
								</div>

								<div class="form-group">
									<label for="gender"><?php esc_html_e( 'Gender', 'wpmatch' ); ?> <span class="required">*</span></label>
									<select id="gender" name="gender" required>
										<option value=""><?php esc_html_e( 'Select your gender', 'wpmatch' ); ?></option>
										<option value="man"><?php esc_html_e( 'Man', 'wpmatch' ); ?></option>
										<option value="woman"><?php esc_html_e( 'Woman', 'wpmatch' ); ?></option>
										<option value="non-binary"><?php esc_html_e( 'Non-binary', 'wpmatch' ); ?></option>
										<option value="other"><?php esc_html_e( 'Other', 'wpmatch' ); ?></option>
									</select>
									<div class="form-error" id="gender_error"></div>
								</div>
							</div>

							<div class="form-group checkbox-group">
								<label class="checkbox-label">
									<input type="checkbox" id="terms_agreement" name="terms_agreement" required>
									<span class="checkmark"></span>
									<?php printf(
										esc_html__( 'I agree to the %1$sTerms of Service%2$s and %3$sPrivacy Policy%4$s', 'wpmatch' ),
										'<a href="#" target="_blank">',
										'</a>',
										'<a href="#" target="_blank">',
										'</a>'
									); ?>
								</label>
								<div class="form-error" id="terms_agreement_error"></div>
							</div>

							<div class="form-group checkbox-group">
								<label class="checkbox-label">
									<input type="checkbox" id="marketing_emails" name="marketing_emails">
									<span class="checkmark"></span>
									<?php esc_html_e( 'I want to receive tips, updates, and special offers via email', 'wpmatch' ); ?>
								</label>
							</div>
						</div>

						<div class="form-actions">
							<button type="button" class="btn btn-primary btn-next" onclick="nextStep()"><?php esc_html_e( 'Continue', 'wpmatch' ); ?></button>
						</div>
					</div>

					<!-- Step 2: Profile Information -->
					<div class="form-step" data-step="2">
						<div class="step-content">
							<h3><?php esc_html_e( 'Tell Us About Yourself', 'wpmatch' ); ?></h3>
							<p class="step-description"><?php esc_html_e( 'Help others get to know the real you', 'wpmatch' ); ?></p>

							<div class="form-grid">
								<div class="form-group">
									<label for="location"><?php esc_html_e( 'Location', 'wpmatch' ); ?> <span class="required">*</span></label>
									<div class="location-input">
										<input type="text" id="location" name="location" placeholder="Enter your city" required>
										<button type="button" class="location-detect" onclick="detectLocation()">
											<span class="dashicons dashicons-location"></span>
										</button>
									</div>
									<div class="form-help"><?php esc_html_e( 'This helps us show you people nearby', 'wpmatch' ); ?></div>
									<div class="form-error" id="location_error"></div>
								</div>

								<div class="form-group">
									<label for="occupation"><?php esc_html_e( 'Occupation', 'wpmatch' ); ?></label>
									<input type="text" id="occupation" name="occupation" placeholder="What do you do for work?">
								</div>

								<div class="form-group">
									<label for="education"><?php esc_html_e( 'Education', 'wpmatch' ); ?></label>
									<select id="education" name="education">
										<option value=""><?php esc_html_e( 'Select your education level', 'wpmatch' ); ?></option>
										<option value="high_school"><?php esc_html_e( 'High School', 'wpmatch' ); ?></option>
										<option value="some_college"><?php esc_html_e( 'Some College', 'wpmatch' ); ?></option>
										<option value="bachelors"><?php esc_html_e( 'Bachelor\'s Degree', 'wpmatch' ); ?></option>
										<option value="masters"><?php esc_html_e( 'Master\'s Degree', 'wpmatch' ); ?></option>
										<option value="doctorate"><?php esc_html_e( 'Doctorate', 'wpmatch' ); ?></option>
										<option value="other"><?php esc_html_e( 'Other', 'wpmatch' ); ?></option>
									</select>
								</div>

								<div class="form-group">
									<label for="height"><?php esc_html_e( 'Height', 'wpmatch' ); ?></label>
									<div class="height-input">
										<select id="height_feet" name="height_feet">
											<option value=""><?php esc_html_e( 'Feet', 'wpmatch' ); ?></option>
											<?php for ( $i = 4; $i <= 7; $i++ ) : ?>
												<option value="<?php echo $i; ?>"><?php echo $i; ?>'</option>
											<?php endfor; ?>
										</select>
										<select id="height_inches" name="height_inches">
											<option value=""><?php esc_html_e( 'Inches', 'wpmatch' ); ?></option>
											<?php for ( $i = 0; $i <= 11; $i++ ) : ?>
												<option value="<?php echo $i; ?>"><?php echo $i; ?>"</option>
											<?php endfor; ?>
										</select>
									</div>
								</div>

								<div class="form-group full-width">
									<label for="about_me"><?php esc_html_e( 'About Me', 'wpmatch' ); ?> <span class="required">*</span></label>
									<textarea id="about_me" name="about_me" rows="4" placeholder="Tell us about yourself, your interests, what makes you unique..." required maxlength="500"></textarea>
									<div class="character-count">
										<span id="about_me_count">0</span>/500 <?php esc_html_e( 'characters', 'wpmatch' ); ?>
									</div>
									<div class="form-error" id="about_me_error"></div>
								</div>
							</div>
						</div>

						<div class="form-actions">
							<button type="button" class="btn btn-secondary btn-back" onclick="prevStep()"><?php esc_html_e( 'Back', 'wpmatch' ); ?></button>
							<button type="button" class="btn btn-primary btn-next" onclick="nextStep()"><?php esc_html_e( 'Continue', 'wpmatch' ); ?></button>
						</div>
					</div>

					<!-- Step 3: Photo Upload -->
					<div class="form-step" data-step="3">
						<div class="step-content">
							<h3><?php esc_html_e( 'Add Your Photos', 'wpmatch' ); ?></h3>
							<p class="step-description"><?php esc_html_e( 'Upload at least one photo to get started', 'wpmatch' ); ?></p>

							<div class="photo-upload-container">
								<div class="photo-upload-grid">
									<div class="photo-slot primary-photo" data-slot="0">
										<input type="file" id="photo_0" name="photos[]" accept="image/*" onchange="handlePhotoUpload(this, 0)">
										<label for="photo_0" class="photo-upload-label">
											<div class="upload-content">
												<span class="dashicons dashicons-plus-alt2"></span>
												<span class="upload-text"><?php esc_html_e( 'Primary Photo', 'wpmatch' ); ?></span>
												<span class="upload-subtext"><?php esc_html_e( 'Required', 'wpmatch' ); ?></span>
											</div>
										</label>
										<div class="photo-preview" style="display: none;">
											<img src="" alt="Photo preview">
											<div class="photo-actions">
												<button type="button" class="photo-remove" onclick="removePhoto(0)">
													<span class="dashicons dashicons-no-alt"></span>
												</button>
											</div>
										</div>
									</div>

									<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
									<div class="photo-slot" data-slot="<?php echo $i; ?>">
										<input type="file" id="photo_<?php echo $i; ?>" name="photos[]" accept="image/*" onchange="handlePhotoUpload(this, <?php echo $i; ?>)">
										<label for="photo_<?php echo $i; ?>" class="photo-upload-label">
											<div class="upload-content">
												<span class="dashicons dashicons-plus-alt2"></span>
												<span class="upload-text"><?php esc_html_e( 'Add Photo', 'wpmatch' ); ?></span>
											</div>
										</label>
										<div class="photo-preview" style="display: none;">
											<img src="" alt="Photo preview">
											<div class="photo-actions">
												<button type="button" class="photo-remove" onclick="removePhoto(<?php echo $i; ?>)">
													<span class="dashicons dashicons-no-alt"></span>
												</button>
											</div>
										</div>
									</div>
									<?php endfor; ?>
								</div>

								<div class="photo-guidelines">
									<h4><?php esc_html_e( 'Photo Guidelines', 'wpmatch' ); ?></h4>
									<ul>
										<li><?php esc_html_e( 'Use clear, recent photos of yourself', 'wpmatch' ); ?></li>
										<li><?php esc_html_e( 'Show your face clearly in your primary photo', 'wpmatch' ); ?></li>
										<li><?php esc_html_e( 'No group photos as your primary image', 'wpmatch' ); ?></li>
										<li><?php esc_html_e( 'Maximum file size: 5MB per photo', 'wpmatch' ); ?></li>
										<li><?php esc_html_e( 'Supported formats: JPG, PNG, GIF', 'wpmatch' ); ?></li>
									</ul>
								</div>
							</div>
						</div>

						<div class="form-actions">
							<button type="button" class="btn btn-secondary btn-back" onclick="prevStep()"><?php esc_html_e( 'Back', 'wpmatch' ); ?></button>
							<button type="button" class="btn btn-primary btn-next" onclick="nextStep()"><?php esc_html_e( 'Continue', 'wpmatch' ); ?></button>
						</div>
					</div>

					<!-- Step 4: Preferences -->
					<div class="form-step" data-step="4">
						<div class="step-content">
							<h3><?php esc_html_e( 'Your Preferences', 'wpmatch' ); ?></h3>
							<p class="step-description"><?php esc_html_e( 'Help us find your perfect match', 'wpmatch' ); ?></p>

							<div class="form-grid">
								<div class="form-group">
									<label for="interested_in"><?php esc_html_e( 'Interested In', 'wpmatch' ); ?> <span class="required">*</span></label>
									<select id="interested_in" name="interested_in" required>
										<option value=""><?php esc_html_e( 'Select your preference', 'wpmatch' ); ?></option>
										<option value="men"><?php esc_html_e( 'Men', 'wpmatch' ); ?></option>
										<option value="women"><?php esc_html_e( 'Women', 'wpmatch' ); ?></option>
										<option value="everyone"><?php esc_html_e( 'Everyone', 'wpmatch' ); ?></option>
									</select>
									<div class="form-error" id="interested_in_error"></div>
								</div>

								<div class="form-group">
									<label for="age_range_min"><?php esc_html_e( 'Age Range', 'wpmatch' ); ?></label>
									<div class="age-range-input">
										<select id="age_range_min" name="age_range_min">
											<option value=""><?php esc_html_e( 'Min Age', 'wpmatch' ); ?></option>
											<?php for ( $age = $min_age; $age <= 80; $age++ ) : ?>
												<option value="<?php echo $age; ?>"><?php echo $age; ?></option>
											<?php endfor; ?>
										</select>
										<span class="age-separator"><?php esc_html_e( 'to', 'wpmatch' ); ?></span>
										<select id="age_range_max" name="age_range_max">
											<option value=""><?php esc_html_e( 'Max Age', 'wpmatch' ); ?></option>
											<?php for ( $age = $min_age; $age <= 99; $age++ ) : ?>
												<option value="<?php echo $age; ?>"><?php echo $age; ?></option>
											<?php endfor; ?>
										</select>
									</div>
								</div>

								<div class="form-group">
									<label for="max_distance"><?php esc_html_e( 'Maximum Distance', 'wpmatch' ); ?></label>
									<div class="distance-input">
										<input type="range" id="max_distance" name="max_distance" min="5" max="100" value="25" oninput="updateDistanceDisplay(this.value)">
										<div class="distance-display">
											<span id="distance_value">25</span> <?php esc_html_e( 'miles', 'wpmatch' ); ?>
										</div>
									</div>
								</div>

								<div class="form-group full-width">
									<label for="looking_for"><?php esc_html_e( 'What Are You Looking For?', 'wpmatch' ); ?></label>
									<div class="looking-for-options">
										<label class="option-button">
											<input type="radio" name="looking_for" value="long_term">
											<span class="option-content">
												<span class="option-icon">💕</span>
												<span class="option-text"><?php esc_html_e( 'Long-term relationship', 'wpmatch' ); ?></span>
											</span>
										</label>
										<label class="option-button">
											<input type="radio" name="looking_for" value="short_term">
											<span class="option-content">
												<span class="option-icon">😊</span>
												<span class="option-text"><?php esc_html_e( 'Short-term, open to long', 'wpmatch' ); ?></span>
											</span>
										</label>
										<label class="option-button">
											<input type="radio" name="looking_for" value="casual">
											<span class="option-content">
												<span class="option-icon">🎉</span>
												<span class="option-text"><?php esc_html_e( 'Casual, nothing serious', 'wpmatch' ); ?></span>
											</span>
										</label>
										<label class="option-button">
											<input type="radio" name="looking_for" value="friends">
											<span class="option-content">
												<span class="option-icon">👥</span>
												<span class="option-text"><?php esc_html_e( 'New friends', 'wpmatch' ); ?></span>
											</span>
										</label>
									</div>
								</div>
							</div>
						</div>

						<div class="form-actions">
							<button type="button" class="btn btn-secondary btn-back" onclick="prevStep()"><?php esc_html_e( 'Back', 'wpmatch' ); ?></button>
							<button type="submit" class="btn btn-primary btn-submit">
								<span class="btn-text"><?php esc_html_e( 'Create My Account', 'wpmatch' ); ?></span>
								<span class="btn-loading" style="display: none;">
									<span class="spinner"></span>
									<?php esc_html_e( 'Creating...', 'wpmatch' ); ?>
								</span>
							</button>
						</div>
					</div>
				</form>

				<!-- Login Link -->
				<div class="form-footer">
					<p><?php esc_html_e( 'Already have an account?', 'wpmatch' ); ?>
						<a href="<?php echo wp_login_url(); ?>" class="login-link"><?php esc_html_e( 'Sign In', 'wpmatch' ); ?></a>
					</p>
				</div>
			</div>
		</div>
	</div>
</div>

<style>
/* Main Container - Desktop-First Responsive Design */
.wpmatch-registration-container {
	min-height: 100vh;
	background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
	display: flex;
	align-items: center;
	justify-content: center;
	padding: 20px;
	font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.wpmatch-registration-wrapper {
	background: white;
	border-radius: 20px;
	box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
	overflow: hidden;
	width: 100%;
	max-width: 1200px;
	min-height: 700px;
	display: grid;
	grid-template-columns: 1fr 1fr;
	animation: slideUp 0.6s ease-out;
}

@keyframes slideUp {
	from {
		opacity: 0;
		transform: translateY(30px);
	}
	to {
		opacity: 1;
		transform: translateY(0);
	}
}

/* Left Side - Branding */
.wpmatch-registration-branding {
	background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
	color: white;
	padding: 60px 40px;
	display: flex;
	flex-direction: column;
	justify-content: center;
	position: relative;
	overflow: hidden;
}

.wpmatch-registration-branding::before {
	content: '';
	position: absolute;
	top: -50%;
	right: -50%;
	width: 200%;
	height: 200%;
	background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="2" fill="rgba(255,255,255,0.1)"/></svg>') repeat;
	animation: float 20s infinite linear;
}

@keyframes float {
	from { transform: translate(0, 0) rotate(0deg); }
	to { transform: translate(-100px, -100px) rotate(360deg); }
}

.branding-content {
	position: relative;
	z-index: 2;
}

.wpmatch-logo h1 {
	font-size: 48px;
	font-weight: 700;
	margin: 0 0 10px 0;
	background: linear-gradient(45deg, #fff, #f0f0f0);
	-webkit-background-clip: text;
	-webkit-text-fill-color: transparent;
	background-clip: text;
}

.wpmatch-logo .tagline {
	font-size: 18px;
	opacity: 0.9;
	margin: 0 0 50px 0;
	font-weight: 300;
}

.features-list {
	margin: 40px 0;
}

.feature-item {
	display: flex;
	align-items: center;
	margin-bottom: 30px;
	padding: 20px;
	background: rgba(255, 255, 255, 0.1);
	border-radius: 12px;
	backdrop-filter: blur(10px);
	transition: transform 0.3s ease;
}

.feature-item:hover {
	transform: translateX(10px);
}

.feature-icon {
	width: 50px;
	height: 50px;
	background: rgba(255, 255, 255, 0.2);
	border-radius: 50%;
	display: flex;
	align-items: center;
	justify-content: center;
	margin-right: 20px;
	flex-shrink: 0;
}

.feature-icon .dashicons {
	font-size: 24px;
	color: white;
}

.feature-text h3 {
	margin: 0 0 8px 0;
	font-size: 18px;
	font-weight: 600;
}

.feature-text p {
	margin: 0;
	font-size: 14px;
	opacity: 0.9;
	line-height: 1.5;
}

.success-stats {
	display: grid;
	grid-template-columns: repeat(3, 1fr);
	gap: 20px;
	margin-top: 40px;
}

.stat-item {
	text-align: center;
	padding: 20px;
	background: rgba(255, 255, 255, 0.1);
	border-radius: 12px;
	backdrop-filter: blur(10px);
}

.stat-number {
	font-size: 24px;
	font-weight: 700;
	margin-bottom: 5px;
}

.stat-label {
	font-size: 12px;
	opacity: 0.8;
	text-transform: uppercase;
	letter-spacing: 0.5px;
}

/* Right Side - Form */
.wpmatch-registration-form {
	padding: 60px 40px;
	overflow-y: auto;
	max-height: 100vh;
}

.form-container {
	max-width: 500px;
	margin: 0 auto;
}

.form-header {
	text-align: center;
	margin-bottom: 40px;
}

.form-header h2 {
	font-size: 32px;
	font-weight: 700;
	color: #2d3748;
	margin: 0 0 10px 0;
}

.form-header p {
	font-size: 16px;
	color: #718096;
	margin: 0;
}

/* Progress Steps */
.registration-progress {
	display: flex;
	justify-content: space-between;
	margin-bottom: 40px;
	position: relative;
}

.registration-progress::before {
	content: '';
	position: absolute;
	top: 20px;
	left: 30px;
	right: 30px;
	height: 2px;
	background: #e2e8f0;
	z-index: 1;
}

.progress-step {
	display: flex;
	flex-direction: column;
	align-items: center;
	position: relative;
	z-index: 2;
	flex: 1;
	cursor: pointer;
	transition: all 0.3s ease;
}

.step-number {
	width: 40px;
	height: 40px;
	border-radius: 50%;
	background: #e2e8f0;
	color: #a0aec0;
	display: flex;
	align-items: center;
	justify-content: center;
	font-weight: 600;
	margin-bottom: 8px;
	transition: all 0.3s ease;
}

.progress-step.active .step-number {
	background: #667eea;
	color: white;
}

.progress-step.completed .step-number {
	background: #48bb78;
	color: white;
}

.step-label {
	font-size: 12px;
	color: #a0aec0;
	text-align: center;
	font-weight: 500;
}

.progress-step.active .step-label,
.progress-step.completed .step-label {
	color: #2d3748;
}

/* Form Steps */
.form-step {
	display: none;
}

.form-step.active {
	display: block;
	animation: fadeIn 0.3s ease-in;
}

@keyframes fadeIn {
	from { opacity: 0; transform: translateX(20px); }
	to { opacity: 1; transform: translateX(0); }
}

.step-content h3 {
	font-size: 24px;
	font-weight: 600;
	color: #2d3748;
	margin: 0 0 8px 0;
}

.step-description {
	font-size: 16px;
	color: #718096;
	margin: 0 0 30px 0;
}

/* Form Layout */
.form-grid {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 20px;
	margin-bottom: 30px;
}

.form-group {
	display: flex;
	flex-direction: column;
}

.form-group.full-width {
	grid-column: 1 / -1;
}

.form-group label {
	font-size: 14px;
	font-weight: 600;
	color: #2d3748;
	margin-bottom: 8px;
	display: flex;
	align-items: center;
}

.required {
	color: #e53e3e;
	margin-left: 4px;
}

.form-group input,
.form-group select,
.form-group textarea {
	padding: 12px 16px;
	border: 2px solid #e2e8f0;
	border-radius: 8px;
	font-size: 16px;
	background: white;
	transition: all 0.3s ease;
	font-family: inherit;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
	outline: none;
	border-color: #667eea;
	box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.form-group input:invalid,
.form-group select:invalid,
.form-group textarea:invalid {
	border-color: #e53e3e;
}

.form-help {
	font-size: 12px;
	color: #718096;
	margin-top: 4px;
}

.form-error {
	font-size: 12px;
	color: #e53e3e;
	margin-top: 4px;
	display: none;
}

.form-error.show {
	display: block;
}

/* Special Input Types */
.password-input {
	position: relative;
}

.password-toggle {
	position: absolute;
	right: 12px;
	top: 50%;
	transform: translateY(-50%);
	background: none;
	border: none;
	color: #a0aec0;
	cursor: pointer;
	padding: 4px;
}

.password-toggle:hover {
	color: #667eea;
}

.password-strength {
	margin-top: 8px;
	font-size: 12px;
}

.password-strength.weak { color: #e53e3e; }
.password-strength.medium { color: #ed8936; }
.password-strength.strong { color: #48bb78; }

.location-input {
	position: relative;
}

.location-detect {
	position: absolute;
	right: 12px;
	top: 50%;
	transform: translateY(-50%);
	background: none;
	border: none;
	color: #a0aec0;
	cursor: pointer;
	padding: 4px;
}

.location-detect:hover {
	color: #667eea;
}

.height-input {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 10px;
}

.age-range-input {
	display: grid;
	grid-template-columns: 1fr auto 1fr;
	gap: 10px;
	align-items: center;
}

.age-separator {
	text-align: center;
	color: #718096;
	font-size: 14px;
}

.distance-input {
	display: flex;
	flex-direction: column;
	gap: 10px;
}

.distance-display {
	text-align: center;
	font-weight: 600;
	color: #667eea;
}

.character-count {
	text-align: right;
	font-size: 12px;
	color: #718096;
	margin-top: 4px;
}

/* Checkbox Styling */
.checkbox-group {
	margin: 20px 0;
}

.checkbox-label {
	display: flex;
	align-items: flex-start;
	cursor: pointer;
	font-size: 14px;
	line-height: 1.5;
}

.checkbox-label input[type="checkbox"] {
	display: none;
}

.checkmark {
	width: 20px;
	height: 20px;
	border: 2px solid #e2e8f0;
	border-radius: 4px;
	margin-right: 12px;
	flex-shrink: 0;
	display: flex;
	align-items: center;
	justify-content: center;
	transition: all 0.3s ease;
}

.checkbox-label input[type="checkbox"]:checked + .checkmark {
	background: #667eea;
	border-color: #667eea;
}

.checkbox-label input[type="checkbox"]:checked + .checkmark::after {
	content: '✓';
	color: white;
	font-size: 12px;
	font-weight: bold;
}

/* Photo Upload */
.photo-upload-grid {
	display: grid;
	grid-template-columns: repeat(3, 1fr);
	gap: 20px;
	margin-bottom: 30px;
}

.photo-slot {
	aspect-ratio: 1;
	border: 2px dashed #e2e8f0;
	border-radius: 12px;
	position: relative;
	overflow: hidden;
	transition: all 0.3s ease;
}

.photo-slot:hover {
	border-color: #667eea;
	background: rgba(102, 126, 234, 0.05);
}

.photo-slot.primary-photo {
	grid-column: 1 / -1;
	aspect-ratio: 16/9;
	border-color: #667eea;
	background: rgba(102, 126, 234, 0.05);
}

.photo-slot input[type="file"] {
	display: none;
}

.photo-upload-label {
	position: absolute;
	inset: 0;
	display: flex;
	align-items: center;
	justify-content: center;
	cursor: pointer;
	transition: all 0.3s ease;
}

.upload-content {
	text-align: center;
	color: #718096;
}

.upload-content .dashicons {
	font-size: 32px;
	margin-bottom: 8px;
	display: block;
}

.upload-text {
	display: block;
	font-weight: 600;
	margin-bottom: 4px;
}

.upload-subtext {
	font-size: 12px;
	color: #a0aec0;
}

.photo-preview {
	position: absolute;
	inset: 0;
}

.photo-preview img {
	width: 100%;
	height: 100%;
	object-fit: cover;
}

.photo-actions {
	position: absolute;
	top: 8px;
	right: 8px;
}

.photo-remove {
	width: 24px;
	height: 24px;
	border-radius: 50%;
	background: rgba(0, 0, 0, 0.7);
	border: none;
	color: white;
	cursor: pointer;
	display: flex;
	align-items: center;
	justify-content: center;
}

.photo-remove .dashicons {
	font-size: 14px;
}

.photo-guidelines {
	background: #f7fafc;
	padding: 20px;
	border-radius: 8px;
	border-left: 4px solid #667eea;
}

.photo-guidelines h4 {
	margin: 0 0 15px 0;
	color: #2d3748;
	font-size: 16px;
}

.photo-guidelines ul {
	margin: 0;
	padding-left: 20px;
}

.photo-guidelines li {
	margin-bottom: 5px;
	color: #718096;
	font-size: 14px;
}

/* Looking For Options */
.looking-for-options {
	display: grid;
	grid-template-columns: repeat(2, 1fr);
	gap: 15px;
}

.option-button {
	cursor: pointer;
}

.option-button input[type="radio"] {
	display: none;
}

.option-content {
	display: flex;
	flex-direction: column;
	align-items: center;
	padding: 20px;
	border: 2px solid #e2e8f0;
	border-radius: 12px;
	transition: all 0.3s ease;
	text-align: center;
}

.option-button:hover .option-content {
	border-color: #667eea;
	background: rgba(102, 126, 234, 0.05);
}

.option-button input[type="radio"]:checked + .option-content {
	border-color: #667eea;
	background: #667eea;
	color: white;
}

.option-icon {
	font-size: 24px;
	margin-bottom: 8px;
}

.option-text {
	font-size: 14px;
	font-weight: 600;
}

/* Form Actions */
.form-actions {
	display: flex;
	gap: 15px;
	justify-content: flex-end;
	margin-top: 40px;
}

.btn {
	padding: 12px 32px;
	border-radius: 8px;
	font-size: 16px;
	font-weight: 600;
	border: none;
	cursor: pointer;
	transition: all 0.3s ease;
	display: flex;
	align-items: center;
	gap: 8px;
	min-width: 120px;
	justify-content: center;
}

.btn-primary {
	background: #667eea;
	color: white;
}

.btn-primary:hover {
	background: #5a67d8;
	transform: translateY(-2px);
	box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.btn-secondary {
	background: transparent;
	color: #667eea;
	border: 2px solid #667eea;
}

.btn-secondary:hover {
	background: #667eea;
	color: white;
}

.btn-loading .spinner {
	width: 16px;
	height: 16px;
	border: 2px solid rgba(255, 255, 255, 0.3);
	border-top: 2px solid white;
	border-radius: 50%;
	animation: spin 1s linear infinite;
}

@keyframes spin {
	to { transform: rotate(360deg); }
}

/* Form Footer */
.form-footer {
	text-align: center;
	margin-top: 30px;
	padding-top: 30px;
	border-top: 1px solid #e2e8f0;
}

.form-footer p {
	margin: 0;
	color: #718096;
}

.login-link {
	color: #667eea;
	text-decoration: none;
	font-weight: 600;
}

.login-link:hover {
	text-decoration: underline;
}

/* Responsive Design */
@media (max-width: 1024px) {
	.wpmatch-registration-wrapper {
		grid-template-columns: 1fr;
		max-width: 600px;
	}

	.wpmatch-registration-branding {
		padding: 40px 30px;
	}

	.wpmatch-logo h1 {
		font-size: 36px;
	}

	.success-stats {
		grid-template-columns: repeat(3, 1fr);
		gap: 15px;
	}

	.feature-item {
		padding: 15px;
	}
}

@media (max-width: 768px) {
	.wpmatch-registration-container {
		padding: 10px;
	}

	.wpmatch-registration-form {
		padding: 40px 30px;
	}

	.form-grid {
		grid-template-columns: 1fr;
		gap: 15px;
	}

	.form-header h2 {
		font-size: 28px;
	}

	.registration-progress {
		margin-bottom: 30px;
	}

	.step-label {
		font-size: 10px;
	}

	.photo-upload-grid {
		grid-template-columns: repeat(2, 1fr);
		gap: 15px;
	}

	.photo-slot.primary-photo {
		grid-column: 1 / -1;
	}

	.looking-for-options {
		grid-template-columns: 1fr;
	}

	.form-actions {
		flex-direction: column;
	}

	.success-stats {
		grid-template-columns: 1fr;
		gap: 15px;
	}

	.age-range-input {
		grid-template-columns: 1fr;
		gap: 15px;
	}

	.age-separator {
		order: 2;
	}
}

@media (max-width: 480px) {
	.wpmatch-registration-branding {
		padding: 30px 20px;
	}

	.wpmatch-registration-form {
		padding: 30px 20px;
	}

	.wpmatch-logo h1 {
		font-size: 28px;
	}

	.form-header h2 {
		font-size: 24px;
	}

	.registration-progress {
		flex-wrap: wrap;
		gap: 10px;
	}

	.progress-step {
		flex: 0 0 calc(50% - 5px);
	}

	.btn {
		padding: 14px 24px;
		font-size: 14px;
	}
}
</style>

<script>
let currentStep = 1;
const totalSteps = 4;
let uploadedPhotos = [];

// Step Navigation
function nextStep() {
	if (validateCurrentStep()) {
		if (currentStep < totalSteps) {
			updateProgressStep(currentStep + 1);
		}
	}
}

function prevStep() {
	if (currentStep > 1) {
		updateProgressStep(currentStep - 1);
	}
}

function updateProgressStep(step) {
	// Hide current step
	document.querySelector('.form-step.active').classList.remove('active');
	document.querySelector('.progress-step.active').classList.remove('active');

	// Mark previous steps as completed
	for (let i = 1; i < step; i++) {
		document.querySelector(`[data-step="${i}"]`).classList.add('completed');
	}

	// Show new step
	document.querySelector(`.form-step[data-step="${step}"]`).classList.add('active');
	document.querySelector(`.progress-step[data-step="${step}"]`).classList.add('active');

	currentStep = step;
}

// Form Validation
function validateCurrentStep() {
	const currentStepElement = document.querySelector('.form-step.active');
	const inputs = currentStepElement.querySelectorAll('input[required], select[required], textarea[required]');
	let isValid = true;

	inputs.forEach(input => {
		if (!validateField(input)) {
			isValid = false;
		}
	});

	// Special validations
	if (currentStep === 1) {
		if (!validatePasswords()) isValid = false;
		if (!validateAge()) isValid = false;
	} else if (currentStep === 3) {
		if (uploadedPhotos.length === 0) {
			showFieldError('photo_0', 'At least one photo is required');
			isValid = false;
		}
	}

	return isValid;
}

function validateField(field) {
	const value = field.value.trim();
	const fieldName = field.name || field.id;
	let isValid = true;

	clearFieldError(fieldName);

	if (field.hasAttribute('required') && !value) {
		showFieldError(fieldName, 'This field is required');
		isValid = false;
	}

	// Specific validations
	switch (fieldName) {
		case 'email':
			if (value && !isValidEmail(value)) {
				showFieldError(fieldName, 'Please enter a valid email address');
				isValid = false;
			}
			break;
		case 'password':
			if (value && value.length < 8) {
				showFieldError(fieldName, 'Password must be at least 8 characters');
				isValid = false;
			}
			updatePasswordStrength(value);
			break;
		case 'about_me':
			if (value && value.length > 500) {
				showFieldError(fieldName, 'About me must be 500 characters or less');
				isValid = false;
			}
			break;
	}

	return isValid;
}

function validatePasswords() {
	const password = document.getElementById('password').value;
	const confirmPassword = document.getElementById('confirm_password').value;

	if (password && confirmPassword && password !== confirmPassword) {
		showFieldError('confirm_password', 'Passwords do not match');
		return false;
	}
	return true;
}

function validateAge() {
	const birthDate = document.getElementById('birth_date').value;
	if (!birthDate) return false;

	const age = calculateAge(new Date(birthDate));
	const minAge = <?php echo $min_age; ?>;

	if (age < minAge) {
		showFieldError('birth_date', `You must be at least ${minAge} years old`);
		return false;
	}
	return true;
}

function calculateAge(birthDate) {
	const today = new Date();
	const age = today.getFullYear() - birthDate.getFullYear();
	const monthDiff = today.getMonth() - birthDate.getMonth();

	if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
		return age - 1;
	}
	return age;
}

function isValidEmail(email) {
	return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function showFieldError(fieldName, message) {
	const errorElement = document.getElementById(fieldName + '_error');
	if (errorElement) {
		errorElement.textContent = message;
		errorElement.classList.add('show');
	}
}

function clearFieldError(fieldName) {
	const errorElement = document.getElementById(fieldName + '_error');
	if (errorElement) {
		errorElement.classList.remove('show');
	}
}

// Password Strength
function updatePasswordStrength(password) {
	const strengthElement = document.getElementById('password_strength');
	let strength = 0;
	let text = '';

	if (password.length >= 8) strength++;
	if (/[A-Z]/.test(password)) strength++;
	if (/[a-z]/.test(password)) strength++;
	if (/[0-9]/.test(password)) strength++;
	if (/[^A-Za-z0-9]/.test(password)) strength++;

	switch (strength) {
		case 0:
		case 1:
		case 2:
			text = 'Weak';
			strengthElement.className = 'password-strength weak';
			break;
		case 3:
		case 4:
			text = 'Medium';
			strengthElement.className = 'password-strength medium';
			break;
		case 5:
			text = 'Strong';
			strengthElement.className = 'password-strength strong';
			break;
	}

	strengthElement.textContent = text;
}

// Utility Functions
function togglePassword(fieldId) {
	const field = document.getElementById(fieldId);
	const button = field.nextElementSibling;
	const icon = button.querySelector('.dashicons');

	if (field.type === 'password') {
		field.type = 'text';
		icon.classList.remove('dashicons-visibility');
		icon.classList.add('dashicons-hidden');
	} else {
		field.type = 'password';
		icon.classList.remove('dashicons-hidden');
		icon.classList.add('dashicons-visibility');
	}
}

function updateDistanceDisplay(value) {
	document.getElementById('distance_value').textContent = value;
}

function detectLocation() {
	if (navigator.geolocation) {
		navigator.geolocation.getCurrentPosition(function(position) {
			// Reverse geocoding would go here
			// For now, just show a message
			alert('Location detected! This would fill in your city automatically.');
		}, function(error) {
			alert('Unable to detect location. Please enter your city manually.');
		});
	} else {
		alert('Geolocation is not supported by this browser.');
	}
}

// Photo Upload
function handlePhotoUpload(input, slot) {
	const file = input.files[0];
	if (!file) return;

	// Validate file
	if (!validatePhotoFile(file)) {
		input.value = '';
		return;
	}

	const reader = new FileReader();
	reader.onload = function(e) {
		const photoSlot = document.querySelector(`.photo-slot[data-slot="${slot}"]`);
		const label = photoSlot.querySelector('.photo-upload-label');
		const preview = photoSlot.querySelector('.photo-preview');
		const img = preview.querySelector('img');

		img.src = e.target.result;
		label.style.display = 'none';
		preview.style.display = 'block';

		// Track uploaded photos
		uploadedPhotos[slot] = file;
	};
	reader.readAsDataURL(file);
}

function removePhoto(slot) {
	const photoSlot = document.querySelector(`.photo-slot[data-slot="${slot}"]`);
	const label = photoSlot.querySelector('.photo-upload-label');
	const preview = photoSlot.querySelector('.photo-preview');
	const input = photoSlot.querySelector('input[type="file"]');

	label.style.display = 'flex';
	preview.style.display = 'none';
	input.value = '';

	// Remove from tracking
	delete uploadedPhotos[slot];
}

function validatePhotoFile(file) {
	const maxSize = 5 * 1024 * 1024; // 5MB
	const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];

	if (file.size > maxSize) {
		alert('Photo must be smaller than 5MB');
		return false;
	}

	if (!allowedTypes.includes(file.type)) {
		alert('Please upload a JPG, PNG, or GIF image');
		return false;
	}

	return true;
}

// Character Count
document.getElementById('about_me').addEventListener('input', function() {
	const count = this.value.length;
	document.getElementById('about_me_count').textContent = count;

	if (count > 500) {
		this.value = this.value.substring(0, 500);
		document.getElementById('about_me_count').textContent = 500;
	}
});

// Form Submission
document.getElementById('wpmatch-registration-form').addEventListener('submit', function(e) {
	e.preventDefault();

	if (!validateCurrentStep()) {
		return;
	}

	const submitBtn = this.querySelector('.btn-submit');
	const btnText = submitBtn.querySelector('.btn-text');
	const btnLoading = submitBtn.querySelector('.btn-loading');

	// Show loading state
	btnText.style.display = 'none';
	btnLoading.style.display = 'flex';
	submitBtn.disabled = true;

	// Prepare form data
	const formData = new FormData(this);

	// Add photos
	uploadedPhotos.forEach((file, index) => {
		if (file) {
			formData.append(`photo_${index}`, file);
		}
	});

	// Add AJAX action to form data
	formData.append('action', 'wpmatch_register_user');

	// Submit via AJAX
	fetch('<?php echo admin_url( 'admin-ajax.php' ); ?>', {
		method: 'POST',
		body: formData
	})
	.then(response => response.json())
	.then(data => {
		if (data.success) {
			// Redirect to success page or dashboard
			window.location.href = data.data.redirect_url || '/wpmatch/dashboard';
		} else {
			alert(data.data.message || 'Registration failed. Please try again.');
		}
	})
	.catch(error => {
		console.error('Error:', error);
		alert('Registration failed. Please try again.');
	})
	.finally(() => {
		// Reset button state
		btnText.style.display = 'block';
		btnLoading.style.display = 'none';
		submitBtn.disabled = false;
	});
});

// Real-time validation
document.addEventListener('DOMContentLoaded', function() {
	const inputs = document.querySelectorAll('input, select, textarea');
	inputs.forEach(input => {
		input.addEventListener('blur', function() {
			validateField(this);
		});

		if (input.name === 'confirm_password') {
			input.addEventListener('input', validatePasswords);
		}
	});
});
</script>