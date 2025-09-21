<?php
/**
 * Registration form template
 *
 * @package WPMatch
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wpmatch-public">
	<form class="wpmatch-registration-form" method="post">
		<?php wp_nonce_field( 'wpmatch_registration', 'wpmatch_registration_nonce' ); ?>

		<h3><?php esc_html_e( 'Join Our Dating Community', 'wpmatch' ); ?></h3>

		<div class="wpmatch-form-group">
			<label for="wpmatch-username"><?php esc_html_e( 'Username', 'wpmatch' ); ?> *</label>
			<input type="text" id="wpmatch-username" name="username" required />
		</div>

		<div class="wpmatch-form-group">
			<label for="wpmatch-email"><?php esc_html_e( 'Email Address', 'wpmatch' ); ?> *</label>
			<input type="email" id="wpmatch-email" name="email" required />
		</div>

		<div class="wpmatch-form-group">
			<label for="wpmatch-password"><?php esc_html_e( 'Password', 'wpmatch' ); ?> *</label>
			<input type="password" id="wpmatch-password" name="password" required />
		</div>

		<div class="wpmatch-form-group">
			<label for="wpmatch-confirm-password"><?php esc_html_e( 'Confirm Password', 'wpmatch' ); ?> *</label>
			<input type="password" id="wpmatch-confirm-password" name="confirm_password" required />
		</div>

		<div class="wpmatch-form-group">
			<label for="wpmatch-birth-date"><?php esc_html_e( 'Date of Birth', 'wpmatch' ); ?> *</label>
			<input type="date" id="wpmatch-birth-date" name="birth_date" required />
		</div>

		<div class="wpmatch-form-group">
			<label for="wpmatch-gender"><?php esc_html_e( 'Gender', 'wpmatch' ); ?> *</label>
			<select id="wpmatch-gender" name="gender" required>
				<option value=""><?php esc_html_e( 'Select Gender', 'wpmatch' ); ?></option>
				<option value="male"><?php esc_html_e( 'Male', 'wpmatch' ); ?></option>
				<option value="female"><?php esc_html_e( 'Female', 'wpmatch' ); ?></option>
				<option value="non-binary"><?php esc_html_e( 'Non-binary', 'wpmatch' ); ?></option>
				<option value="other"><?php esc_html_e( 'Other', 'wpmatch' ); ?></option>
			</select>
		</div>

		<div class="wpmatch-form-group">
			<label>
				<input type="checkbox" name="agree_terms" required />
				<?php esc_html_e( 'I agree to the Terms of Service and Privacy Policy', 'wpmatch' ); ?> *
			</label>
		</div>

		<div class="wpmatch-form-group">
			<button type="submit" class="wpmatch-button full-width">
				<?php esc_html_e( 'Create Account', 'wpmatch' ); ?>
			</button>
		</div>

		<p class="wpmatch-login-link">
			<?php esc_html_e( 'Already have an account?', 'wpmatch' ); ?>
			<a href="<?php echo esc_url( wp_login_url() ); ?>"><?php esc_html_e( 'Log in here', 'wpmatch' ); ?></a>
		</p>
	</form>
</div>