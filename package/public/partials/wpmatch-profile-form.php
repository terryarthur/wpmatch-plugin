<?php
/**
 * User profile form template
 *
 * @package WPMatch
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Check if user is logged in.
if ( ! is_user_logged_in() ) {
	echo '<p>' . esc_html__( 'You must be logged in to edit your profile.', 'wpmatch' ) . '</p>';
	return;
}

$current_user_id = get_current_user_id();
$profile_manager = new WPMatch_Profile_Manager();
$profile         = $profile_manager->get_profile( $current_user_id );
$preferences     = $profile ? $profile->preferences : null;

// Handle form submission.
if ( isset( $_POST['wpmatch_save_profile'] ) && isset( $_POST['wpmatch_profile_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpmatch_profile_nonce'] ) ), 'wpmatch_save_profile' ) ) {
	$profile_data = array(
		'age'            => isset( $_POST['age'] ) ? sanitize_text_field( wp_unslash( $_POST['age'] ) ) : '',
		'location'       => isset( $_POST['location'] ) ? sanitize_text_field( wp_unslash( $_POST['location'] ) ) : '',
		'latitude'       => isset( $_POST['latitude'] ) ? sanitize_text_field( wp_unslash( $_POST['latitude'] ) ) : '',
		'longitude'      => isset( $_POST['longitude'] ) ? sanitize_text_field( wp_unslash( $_POST['longitude'] ) ) : '',
		'gender'         => isset( $_POST['gender'] ) ? sanitize_text_field( wp_unslash( $_POST['gender'] ) ) : '',
		'orientation'    => isset( $_POST['orientation'] ) ? sanitize_text_field( wp_unslash( $_POST['orientation'] ) ) : '',
		'education'      => isset( $_POST['education'] ) ? sanitize_text_field( wp_unslash( $_POST['education'] ) ) : '',
		'profession'     => isset( $_POST['profession'] ) ? sanitize_text_field( wp_unslash( $_POST['profession'] ) ) : '',
		'income_range'   => isset( $_POST['income_range'] ) ? sanitize_text_field( wp_unslash( $_POST['income_range'] ) ) : '',
		'height'         => isset( $_POST['height'] ) ? sanitize_text_field( wp_unslash( $_POST['height'] ) ) : '',
		'body_type'      => isset( $_POST['body_type'] ) ? sanitize_text_field( wp_unslash( $_POST['body_type'] ) ) : '',
		'ethnicity'      => isset( $_POST['ethnicity'] ) ? sanitize_text_field( wp_unslash( $_POST['ethnicity'] ) ) : '',
		'smoking'        => isset( $_POST['smoking'] ) ? sanitize_text_field( wp_unslash( $_POST['smoking'] ) ) : '',
		'drinking'       => isset( $_POST['drinking'] ) ? sanitize_text_field( wp_unslash( $_POST['drinking'] ) ) : '',
		'children'       => isset( $_POST['children'] ) ? sanitize_text_field( wp_unslash( $_POST['children'] ) ) : '',
		'wants_children' => isset( $_POST['wants_children'] ) ? sanitize_text_field( wp_unslash( $_POST['wants_children'] ) ) : '',
		'pets'           => isset( $_POST['pets'] ) ? sanitize_text_field( wp_unslash( $_POST['pets'] ) ) : '',
		'about_me'       => isset( $_POST['about_me'] ) ? sanitize_textarea_field( wp_unslash( $_POST['about_me'] ) ) : '',
		'looking_for'    => isset( $_POST['looking_for'] ) ? sanitize_textarea_field( wp_unslash( $_POST['looking_for'] ) ) : '',
		'preferences'    => array(
			'min_age'          => isset( $_POST['min_age'] ) ? sanitize_text_field( wp_unslash( $_POST['min_age'] ) ) : '18',
			'max_age'          => isset( $_POST['max_age'] ) ? sanitize_text_field( wp_unslash( $_POST['max_age'] ) ) : '99',
			'max_distance'     => isset( $_POST['max_distance'] ) ? sanitize_text_field( wp_unslash( $_POST['max_distance'] ) ) : '50',
			'preferred_gender' => isset( $_POST['preferred_gender'] ) ? sanitize_text_field( wp_unslash( $_POST['preferred_gender'] ) ) : '',
			'show_profile'     => isset( $_POST['show_profile'] ) ? 1 : 0,
			'allow_messages'   => isset( $_POST['allow_messages'] ) ? 1 : 0,
		),
	);

	$result = $profile_manager->save_profile( $current_user_id, $profile_data );

	if ( is_wp_error( $result ) ) {
		$error_message = $result->get_error_message();
	} else {
		$success_message = __( 'Profile saved successfully!', 'wpmatch' );
		// Refresh profile data.
		$profile     = $profile_manager->get_profile( $current_user_id );
		$preferences = $profile ? $profile->preferences : null;
	}
}
?>

<div class="wpmatch-public">
	<div class="wpmatch-profile-form-container">
		<h2><?php esc_html_e( 'Edit Your Dating Profile', 'wpmatch' ); ?></h2>

		<?php if ( isset( $error_message ) ) : ?>
			<div class="wpmatch-notice error">
				<p><?php echo esc_html( $error_message ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( isset( $success_message ) ) : ?>
			<div class="wpmatch-notice success">
				<p><?php echo esc_html( $success_message ); ?></p>
			</div>
		<?php endif; ?>

		<form method="post" class="wpmatch-profile-form">
			<?php wp_nonce_field( 'wpmatch_save_profile', 'wpmatch_profile_nonce' ); ?>

			<!-- Profile Form Tabs -->
			<div class="wpmatch-tabs" role="tablist">
				<button type="button" class="wpmatch-tab active" data-tab="basic" role="tab" aria-selected="true" aria-controls="tab-basic">
					<span class="dashicons dashicons-admin-users"></span>
					<?php esc_html_e( 'Basic Info', 'wpmatch' ); ?>
				</button>
				<button type="button" class="wpmatch-tab" data-tab="photos" role="tab" aria-selected="false" aria-controls="tab-photos">
					<span class="dashicons dashicons-camera"></span>
					<?php esc_html_e( 'Photos', 'wpmatch' ); ?>
				</button>
				<button type="button" class="wpmatch-tab" data-tab="details" role="tab" aria-selected="false" aria-controls="tab-details">
					<span class="dashicons dashicons-id-alt"></span>
					<?php esc_html_e( 'Details', 'wpmatch' ); ?>
				</button>
				<button type="button" class="wpmatch-tab" data-tab="about" role="tab" aria-selected="false" aria-controls="tab-about">
					<span class="dashicons dashicons-edit"></span>
					<?php esc_html_e( 'About You', 'wpmatch' ); ?>
				</button>
				<button type="button" class="wpmatch-tab" data-tab="preferences" role="tab" aria-selected="false" aria-controls="tab-preferences">
					<span class="dashicons dashicons-admin-settings"></span>
					<?php esc_html_e( 'Preferences', 'wpmatch' ); ?>
				</button>
			</div>

			<!-- Tab Content Panels -->
			<div class="wpmatch-tab-content">

				<!-- Basic Info Tab -->
				<div id="tab-basic" class="wpmatch-tab-panel active" role="tabpanel" aria-labelledby="tab-basic">
					<div class="wpmatch-form-row">
						<div class="wpmatch-form-group">
							<label for="age"><?php esc_html_e( 'Age', 'wpmatch' ); ?> <span class="required">*</span></label>
							<input type="number" id="age" name="age" min="18" max="120" value="<?php echo esc_attr( $profile ? $profile->age : '' ); ?>" required>
						</div>
						<div class="wpmatch-form-group">
							<label for="gender"><?php esc_html_e( 'Gender', 'wpmatch' ); ?> <span class="required">*</span></label>
							<select id="gender" name="gender" required>
								<option value=""><?php esc_html_e( 'Select Gender', 'wpmatch' ); ?></option>
								<option value="male" <?php selected( $profile ? $profile->gender : '', 'male' ); ?>><?php esc_html_e( 'Male', 'wpmatch' ); ?></option>
								<option value="female" <?php selected( $profile ? $profile->gender : '', 'female' ); ?>><?php esc_html_e( 'Female', 'wpmatch' ); ?></option>
								<option value="non-binary" <?php selected( $profile ? $profile->gender : '', 'non-binary' ); ?>><?php esc_html_e( 'Non-Binary', 'wpmatch' ); ?></option>
								<option value="other" <?php selected( $profile ? $profile->gender : '', 'other' ); ?>><?php esc_html_e( 'Other', 'wpmatch' ); ?></option>
							</select>
						</div>
					</div>

					<div class="wpmatch-form-row">
						<div class="wpmatch-form-group">
							<label for="orientation"><?php esc_html_e( 'Sexual Orientation', 'wpmatch' ); ?></label>
							<select id="orientation" name="orientation">
								<option value=""><?php esc_html_e( 'Select Orientation', 'wpmatch' ); ?></option>
								<option value="straight" <?php selected( $profile ? $profile->orientation : '', 'straight' ); ?>><?php esc_html_e( 'Straight', 'wpmatch' ); ?></option>
								<option value="gay" <?php selected( $profile ? $profile->orientation : '', 'gay' ); ?>><?php esc_html_e( 'Gay', 'wpmatch' ); ?></option>
								<option value="lesbian" <?php selected( $profile ? $profile->orientation : '', 'lesbian' ); ?>><?php esc_html_e( 'Lesbian', 'wpmatch' ); ?></option>
								<option value="bisexual" <?php selected( $profile ? $profile->orientation : '', 'bisexual' ); ?>><?php esc_html_e( 'Bisexual', 'wpmatch' ); ?></option>
								<option value="pansexual" <?php selected( $profile ? $profile->orientation : '', 'pansexual' ); ?>><?php esc_html_e( 'Pansexual', 'wpmatch' ); ?></option>
								<option value="asexual" <?php selected( $profile ? $profile->orientation : '', 'asexual' ); ?>><?php esc_html_e( 'Asexual', 'wpmatch' ); ?></option>
								<option value="other" <?php selected( $profile ? $profile->orientation : '', 'other' ); ?>><?php esc_html_e( 'Other', 'wpmatch' ); ?></option>
							</select>
						</div>
						<div class="wpmatch-form-group">
							<label for="location"><?php esc_html_e( 'Location', 'wpmatch' ); ?> <span class="required">*</span></label>
							<input type="text" id="location" name="location" value="<?php echo esc_attr( $profile ? $profile->location : '' ); ?>" placeholder="<?php esc_attr_e( 'City, State', 'wpmatch' ); ?>" required>
							<input type="hidden" id="latitude" name="latitude" value="<?php echo esc_attr( $profile ? $profile->latitude : '' ); ?>">
							<input type="hidden" id="longitude" name="longitude" value="<?php echo esc_attr( $profile ? $profile->longitude : '' ); ?>">
						</div>
					</div>
				</div>

				<!-- Photos Tab -->
				<div id="tab-photos" class="wpmatch-tab-panel" role="tabpanel" aria-labelledby="tab-photos">
					<div class="wpmatch-photos-section">
						<h3><?php esc_html_e( 'Your Photos', 'wpmatch' ); ?></h3>
						<p class="wpmatch-help-text"><?php esc_html_e( 'Add up to 10 photos to showcase your personality. Your first photo will be your main profile photo.', 'wpmatch' ); ?></p>

						<div class="wpmatch-photo-upload-container">
							<div class="wpmatch-photo-grid" id="wpmatch-photo-grid">
								<?php
								// Get existing photos for this user.
								global $wpdb;
								$photos = $wpdb->get_results(
									$wpdb->prepare(
										"SELECT * FROM {$wpdb->prefix}wpmatch_user_media
										WHERE user_id = %d AND media_type = 'photo'
										ORDER BY is_primary DESC, display_order ASC",
										$current_user_id
									)
								);

								// Display existing photos.
								for ( $i = 0; $i < 10; $i++ ) {
									$photo      = isset( $photos[ $i ] ) ? $photos[ $i ] : null;
									$photo_url  = $photo ? $photo->file_path : '';
									$is_primary = $photo ? $photo->is_primary : ( 0 === $i );
									?>
									<div class="wpmatch-photo-slot" data-slot="<?php echo esc_attr( $i ); ?>">
										<?php if ( $photo_url ) : ?>
											<img src="<?php echo esc_url( $photo_url ); ?>" alt="<?php esc_attr_e( 'Profile photo', 'wpmatch' ); ?>" class="wpmatch-photo-preview">
											<div class="wpmatch-photo-overlay">
												<button type="button" class="wpmatch-photo-action delete" data-photo-id="<?php echo esc_attr( $photo->media_id ); ?>" title="<?php esc_attr_e( 'Delete photo', 'wpmatch' ); ?>">
													<span class="dashicons dashicons-trash"></span>
												</button>
												<?php if ( ! $is_primary ) : ?>
													<button type="button" class="wpmatch-photo-action primary" data-photo-id="<?php echo esc_attr( $photo->media_id ); ?>" title="<?php esc_attr_e( 'Make primary', 'wpmatch' ); ?>">
														<span class="dashicons dashicons-star-filled"></span>
													</button>
												<?php endif; ?>
											</div>
											<?php if ( $is_primary ) : ?>
												<div class="wpmatch-primary-badge"><?php esc_html_e( 'Main', 'wpmatch' ); ?></div>
											<?php endif; ?>
										<?php else : ?>
											<div class="wpmatch-photo-placeholder">
												<div class="wpmatch-upload-prompt">
													<span class="dashicons dashicons-plus"></span>
													<span class="wpmatch-upload-text">
														<?php echo 0 === $i ? esc_html__( 'Add main photo', 'wpmatch' ) : esc_html__( 'Add photo', 'wpmatch' ); ?>
													</span>
												</div>
												<input type="file" class="wpmatch-photo-input" accept="image/*" data-slot="<?php echo esc_attr( $i ); ?>">
											</div>
										<?php endif; ?>
									</div>
									<?php
								}
								?>
							</div>

							<div class="wpmatch-photo-requirements">
								<h4><?php esc_html_e( 'Photo Requirements', 'wpmatch' ); ?></h4>
								<ul>
									<li><?php esc_html_e( 'JPG, PNG, or GIF format', 'wpmatch' ); ?></li>
									<li><?php esc_html_e( 'Maximum 5MB per photo', 'wpmatch' ); ?></li>
									<li><?php esc_html_e( 'Minimum 300x300 pixels', 'wpmatch' ); ?></li>
									<li><?php esc_html_e( 'Photos should clearly show your face', 'wpmatch' ); ?></li>
									<li><?php esc_html_e( 'No inappropriate content', 'wpmatch' ); ?></li>
								</ul>
							</div>
						</div>
					</div>
				</div>

				<!-- Details Tab -->
				<div id="tab-details" class="wpmatch-tab-panel" role="tabpanel" aria-labelledby="tab-details">
					<div class="wpmatch-form-row">
						<div class="wpmatch-form-group">
							<label for="height"><?php esc_html_e( 'Height (cm)', 'wpmatch' ); ?></label>
							<input type="number" id="height" name="height" min="120" max="250" value="<?php echo esc_attr( $profile ? $profile->height : '' ); ?>">
						</div>
						<div class="wpmatch-form-group">
							<label for="body_type"><?php esc_html_e( 'Body Type', 'wpmatch' ); ?></label>
							<select id="body_type" name="body_type">
								<option value=""><?php esc_html_e( 'Select Body Type', 'wpmatch' ); ?></option>
								<option value="slim" <?php selected( $profile ? $profile->body_type : '', 'slim' ); ?>><?php esc_html_e( 'Slim', 'wpmatch' ); ?></option>
								<option value="athletic" <?php selected( $profile ? $profile->body_type : '', 'athletic' ); ?>><?php esc_html_e( 'Athletic', 'wpmatch' ); ?></option>
								<option value="average" <?php selected( $profile ? $profile->body_type : '', 'average' ); ?>><?php esc_html_e( 'Average', 'wpmatch' ); ?></option>
								<option value="curvy" <?php selected( $profile ? $profile->body_type : '', 'curvy' ); ?>><?php esc_html_e( 'Curvy', 'wpmatch' ); ?></option>
								<option value="heavyset" <?php selected( $profile ? $profile->body_type : '', 'heavyset' ); ?>><?php esc_html_e( 'Heavyset', 'wpmatch' ); ?></option>
							</select>
						</div>
					</div>

					<div class="wpmatch-form-row">
						<div class="wpmatch-form-group">
							<label for="education"><?php esc_html_e( 'Education', 'wpmatch' ); ?></label>
							<select id="education" name="education">
								<option value=""><?php esc_html_e( 'Select Education Level', 'wpmatch' ); ?></option>
								<option value="high_school" <?php selected( $profile ? $profile->education : '', 'high_school' ); ?>><?php esc_html_e( 'High School', 'wpmatch' ); ?></option>
								<option value="some_college" <?php selected( $profile ? $profile->education : '', 'some_college' ); ?>><?php esc_html_e( 'Some College', 'wpmatch' ); ?></option>
								<option value="bachelors" <?php selected( $profile ? $profile->education : '', 'bachelors' ); ?>><?php esc_html_e( 'Bachelor\'s Degree', 'wpmatch' ); ?></option>
								<option value="masters" <?php selected( $profile ? $profile->education : '', 'masters' ); ?>><?php esc_html_e( 'Master\'s Degree', 'wpmatch' ); ?></option>
								<option value="doctorate" <?php selected( $profile ? $profile->education : '', 'doctorate' ); ?>><?php esc_html_e( 'Doctorate', 'wpmatch' ); ?></option>
							</select>
						</div>
						<div class="wpmatch-form-group">
							<label for="profession"><?php esc_html_e( 'Profession', 'wpmatch' ); ?></label>
							<input type="text" id="profession" name="profession" value="<?php echo esc_attr( $profile ? $profile->profession : '' ); ?>" placeholder="<?php esc_attr_e( 'Your job title', 'wpmatch' ); ?>">
						</div>
					</div>

					<div class="wpmatch-form-row">
						<div class="wpmatch-form-group">
							<label for="smoking"><?php esc_html_e( 'Smoking', 'wpmatch' ); ?></label>
							<select id="smoking" name="smoking">
								<option value=""><?php esc_html_e( 'Select Option', 'wpmatch' ); ?></option>
								<option value="never" <?php selected( $profile ? $profile->smoking : '', 'never' ); ?>><?php esc_html_e( 'Never', 'wpmatch' ); ?></option>
								<option value="occasionally" <?php selected( $profile ? $profile->smoking : '', 'occasionally' ); ?>><?php esc_html_e( 'Occasionally', 'wpmatch' ); ?></option>
								<option value="regularly" <?php selected( $profile ? $profile->smoking : '', 'regularly' ); ?>><?php esc_html_e( 'Regularly', 'wpmatch' ); ?></option>
								<option value="trying_to_quit" <?php selected( $profile ? $profile->smoking : '', 'trying_to_quit' ); ?>><?php esc_html_e( 'Trying to Quit', 'wpmatch' ); ?></option>
							</select>
						</div>
						<div class="wpmatch-form-group">
							<label for="drinking"><?php esc_html_e( 'Drinking', 'wpmatch' ); ?></label>
							<select id="drinking" name="drinking">
								<option value=""><?php esc_html_e( 'Select Option', 'wpmatch' ); ?></option>
								<option value="never" <?php selected( $profile ? $profile->drinking : '', 'never' ); ?>><?php esc_html_e( 'Never', 'wpmatch' ); ?></option>
								<option value="socially" <?php selected( $profile ? $profile->drinking : '', 'socially' ); ?>><?php esc_html_e( 'Socially', 'wpmatch' ); ?></option>
								<option value="occasionally" <?php selected( $profile ? $profile->drinking : '', 'occasionally' ); ?>><?php esc_html_e( 'Occasionally', 'wpmatch' ); ?></option>
								<option value="regularly" <?php selected( $profile ? $profile->drinking : '', 'regularly' ); ?>><?php esc_html_e( 'Regularly', 'wpmatch' ); ?></option>
							</select>
						</div>
					</div>

					<div class="wpmatch-form-row">
						<div class="wpmatch-form-group">
							<label for="children"><?php esc_html_e( 'Children', 'wpmatch' ); ?></label>
							<select id="children" name="children">
								<option value=""><?php esc_html_e( 'Select Option', 'wpmatch' ); ?></option>
								<option value="none" <?php selected( $profile ? $profile->children : '', 'none' ); ?>><?php esc_html_e( 'No Children', 'wpmatch' ); ?></option>
								<option value="have_children" <?php selected( $profile ? $profile->children : '', 'have_children' ); ?>><?php esc_html_e( 'Have Children', 'wpmatch' ); ?></option>
								<option value="prefer_not_to_say" <?php selected( $profile ? $profile->children : '', 'prefer_not_to_say' ); ?>><?php esc_html_e( 'Prefer Not to Say', 'wpmatch' ); ?></option>
							</select>
						</div>
						<div class="wpmatch-form-group">
							<label for="wants_children"><?php esc_html_e( 'Want Children', 'wpmatch' ); ?></label>
							<select id="wants_children" name="wants_children">
								<option value=""><?php esc_html_e( 'Select Option', 'wpmatch' ); ?></option>
								<option value="yes" <?php selected( $profile ? $profile->wants_children : '', 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpmatch' ); ?></option>
								<option value="no" <?php selected( $profile ? $profile->wants_children : '', 'no' ); ?>><?php esc_html_e( 'No', 'wpmatch' ); ?></option>
								<option value="maybe" <?php selected( $profile ? $profile->wants_children : '', 'maybe' ); ?>><?php esc_html_e( 'Maybe', 'wpmatch' ); ?></option>
								<option value="not_sure" <?php selected( $profile ? $profile->wants_children : '', 'not_sure' ); ?>><?php esc_html_e( 'Not Sure', 'wpmatch' ); ?></option>
							</select>
						</div>
					</div>
				</div>

				<!-- About You Tab -->
				<div id="tab-about" class="wpmatch-tab-panel" role="tabpanel" aria-labelledby="tab-about">
					<div class="wpmatch-form-group">
						<label for="about_me"><?php esc_html_e( 'About Me', 'wpmatch' ); ?> <span class="required">*</span></label>
						<textarea id="about_me" name="about_me" rows="6" placeholder="<?php esc_attr_e( 'Tell people about yourself, your interests, and what makes you unique...', 'wpmatch' ); ?>" required><?php echo esc_textarea( $profile ? $profile->about_me : '' ); ?></textarea>
						<small class="wpmatch-help-text"><?php esc_html_e( 'A compelling description helps attract the right matches.', 'wpmatch' ); ?></small>
					</div>

					<div class="wpmatch-form-group">
						<label for="looking_for"><?php esc_html_e( 'What I\'m Looking For', 'wpmatch' ); ?></label>
						<textarea id="looking_for" name="looking_for" rows="4" placeholder="<?php esc_attr_e( 'Describe what you\'re looking for in a partner or relationship...', 'wpmatch' ); ?>"><?php echo esc_textarea( $profile ? $profile->looking_for : '' ); ?></textarea>
					</div>
				</div>

				<!-- Preferences Tab -->
				<div id="tab-preferences" class="wpmatch-tab-panel" role="tabpanel" aria-labelledby="tab-preferences">
					<h3><?php esc_html_e( 'Match Preferences', 'wpmatch' ); ?></h3>

					<div class="wpmatch-form-row">
						<div class="wpmatch-form-group">
							<label for="min_age"><?php esc_html_e( 'Minimum Age', 'wpmatch' ); ?></label>
							<input type="number" id="min_age" name="min_age" min="18" max="99" value="<?php echo esc_attr( $preferences ? $preferences->min_age : '18' ); ?>">
						</div>
						<div class="wpmatch-form-group">
							<label for="max_age"><?php esc_html_e( 'Maximum Age', 'wpmatch' ); ?></label>
							<input type="number" id="max_age" name="max_age" min="18" max="99" value="<?php echo esc_attr( $preferences ? $preferences->max_age : '99' ); ?>">
						</div>
					</div>

					<div class="wpmatch-form-row">
						<div class="wpmatch-form-group">
							<label for="max_distance"><?php esc_html_e( 'Maximum Distance (km)', 'wpmatch' ); ?></label>
							<input type="number" id="max_distance" name="max_distance" min="1" max="500" value="<?php echo esc_attr( $preferences ? $preferences->max_distance : '50' ); ?>">
						</div>
						<div class="wpmatch-form-group">
							<label for="preferred_gender"><?php esc_html_e( 'Interested In', 'wpmatch' ); ?></label>
							<select id="preferred_gender" name="preferred_gender">
								<option value=""><?php esc_html_e( 'All Genders', 'wpmatch' ); ?></option>
								<option value="male" <?php selected( $preferences ? $preferences->preferred_gender : '', 'male' ); ?>><?php esc_html_e( 'Men', 'wpmatch' ); ?></option>
								<option value="female" <?php selected( $preferences ? $preferences->preferred_gender : '', 'female' ); ?>><?php esc_html_e( 'Women', 'wpmatch' ); ?></option>
								<option value="non-binary" <?php selected( $preferences ? $preferences->preferred_gender : '', 'non-binary' ); ?>><?php esc_html_e( 'Non-Binary', 'wpmatch' ); ?></option>
							</select>
						</div>
					</div>

					<h3><?php esc_html_e( 'Privacy Settings', 'wpmatch' ); ?></h3>

					<div class="wpmatch-form-group">
						<label class="wpmatch-checkbox-label">
							<input type="checkbox" name="show_profile" value="1" <?php checked( $preferences ? $preferences->show_profile : 1, 1 ); ?>>
							<?php esc_html_e( 'Show my profile to other users', 'wpmatch' ); ?>
						</label>
					</div>

					<div class="wpmatch-form-group">
						<label class="wpmatch-checkbox-label">
							<input type="checkbox" name="allow_messages" value="1" <?php checked( $preferences ? $preferences->allow_messages : 1, 1 ); ?>>
							<?php esc_html_e( 'Allow other users to message me', 'wpmatch' ); ?>
						</label>
					</div>
				</div>

			</div>

			<div class="wpmatch-form-actions">
				<button type="submit" name="wpmatch_save_profile" class="wpmatch-button primary">
					<?php esc_html_e( 'Save Profile', 'wpmatch' ); ?>
				</button>

				<?php if ( $profile && $profile->profile_completion > 0 ) : ?>
					<div class="wpmatch-profile-completion">
						<span class="wpmatch-completion-label"><?php esc_html_e( 'Profile Completion:', 'wpmatch' ); ?></span>
						<div class="wpmatch-completion-bar">
							<div class="wpmatch-completion-fill" style="width: <?php echo esc_attr( $profile->profile_completion ); ?>%"></div>
						</div>
						<span class="wpmatch-completion-percent"><?php echo esc_html( $profile->profile_completion ); ?>%</span>
					</div>
				<?php endif; ?>
			</div>
		</form>
	</div>
</div>