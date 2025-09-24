<?php
/**
 * Two-Factor Authentication Verification Form
 *
 * @package WPMatch
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$login_url = wp_login_url();
$site_name = get_bloginfo( 'name' );

// Handle form submission
if ( isset( $_POST['wpmatch_2fa_verify'] ) && isset( $_POST['wpmatch_2fa_nonce'] ) ) {
	if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpmatch_2fa_nonce'] ) ), 'wpmatch_2fa_verify' ) ) {
		$verification_code = sanitize_text_field( wp_unslash( $_POST['verification_code'] ) );
		$selected_method = sanitize_text_field( wp_unslash( $_POST['method'] ) );

		$two_factor = WPMatch_Two_Factor::get_instance();

		if ( $two_factor->verify_2fa_code( $user_id, $verification_code, $selected_method ) ) {
			if ( $two_factor->complete_2fa_login( $user_id ) ) {
				wp_safe_redirect( admin_url() );
				exit;
			} else {
				$error_message = __( 'Login completion failed. Please try again.', 'wpmatch' );
			}
		} else {
			$error_message = __( 'Invalid verification code. Please try again.', 'wpmatch' );
		}
	} else {
		$error_message = __( 'Security check failed. Please try again.', 'wpmatch' );
	}
}
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html( sprintf( __( 'Two-Factor Authentication &lsaquo; %s', 'wpmatch' ), $site_name ) ); ?></title>
	<?php wp_head(); ?>
	<style>
		body {
			background: #f1f1f1;
			margin: 0;
			padding: 0;
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
		}

		.wpmatch-2fa-container {
			max-width: 400px;
			margin: 80px auto;
			background: #fff;
			border-radius: 8px;
			box-shadow: 0 2px 10px rgba(0,0,0,0.1);
			overflow: hidden;
		}

		.wpmatch-2fa-header {
			background: #0073aa;
			color: white;
			text-align: center;
			padding: 30px 20px;
		}

		.wpmatch-2fa-header h1 {
			margin: 0;
			font-size: 24px;
			font-weight: 400;
		}

		.wpmatch-2fa-header .shield-icon {
			font-size: 48px;
			margin-bottom: 10px;
			display: block;
		}

		.wpmatch-2fa-body {
			padding: 30px;
		}

		.wpmatch-2fa-user-info {
			text-align: center;
			margin-bottom: 25px;
			padding-bottom: 25px;
			border-bottom: 1px solid #eee;
		}

		.wpmatch-2fa-user-info .avatar {
			margin-bottom: 10px;
		}

		.wpmatch-2fa-user-info .user-name {
			font-weight: 600;
			font-size: 16px;
			margin-bottom: 5px;
		}

		.wpmatch-2fa-user-info .user-email {
			color: #666;
			font-size: 14px;
		}

		.wpmatch-2fa-methods {
			margin-bottom: 25px;
		}

		.method-tabs {
			display: flex;
			border-bottom: 1px solid #ddd;
			margin-bottom: 20px;
		}

		.method-tab {
			flex: 1;
			padding: 12px;
			text-align: center;
			background: #f9f9f9;
			border: none;
			cursor: pointer;
			font-size: 14px;
			transition: all 0.3s ease;
		}

		.method-tab.active {
			background: #0073aa;
			color: white;
		}

		.method-tab:hover:not(.active) {
			background: #e6e6e6;
		}

		.method-content {
			display: none;
		}

		.method-content.active {
			display: block;
		}

		.form-group {
			margin-bottom: 20px;
		}

		.form-group label {
			display: block;
			margin-bottom: 8px;
			font-weight: 600;
			color: #333;
		}

		.form-group input[type="text"] {
			width: 100%;
			padding: 12px;
			border: 2px solid #ddd;
			border-radius: 4px;
			font-size: 16px;
			text-align: center;
			letter-spacing: 2px;
			font-family: 'Courier New', monospace;
			box-sizing: border-box;
		}

		.form-group input[type="text"]:focus {
			outline: none;
			border-color: #0073aa;
			box-shadow: 0 0 0 2px rgba(0,115,170,0.1);
		}

		.form-help {
			font-size: 13px;
			color: #666;
			margin-top: 5px;
		}

		.submit-button {
			width: 100%;
			padding: 12px;
			background: #0073aa;
			color: white;
			border: none;
			border-radius: 4px;
			font-size: 16px;
			font-weight: 600;
			cursor: pointer;
			transition: background-color 0.3s ease;
		}

		.submit-button:hover {
			background: #005a87;
		}

		.submit-button:disabled {
			background: #ccc;
			cursor: not-allowed;
		}

		.resend-code {
			display: inline-block;
			margin-top: 15px;
			color: #0073aa;
			text-decoration: none;
			font-size: 14px;
		}

		.resend-code:hover {
			text-decoration: underline;
		}

		.error-message {
			background: #dc3232;
			color: white;
			padding: 12px;
			border-radius: 4px;
			margin-bottom: 20px;
			text-align: center;
		}

		.success-message {
			background: #00a32a;
			color: white;
			padding: 12px;
			border-radius: 4px;
			margin-bottom: 20px;
			text-align: center;
		}

		.wpmatch-2fa-footer {
			background: #f9f9f9;
			padding: 20px;
			text-align: center;
			border-top: 1px solid #eee;
		}

		.wpmatch-2fa-footer a {
			color: #0073aa;
			text-decoration: none;
			font-size: 14px;
		}

		.wpmatch-2fa-footer a:hover {
			text-decoration: underline;
		}

		@media (max-width: 480px) {
			.wpmatch-2fa-container {
				margin: 20px;
				max-width: none;
			}

			.method-tabs {
				flex-direction: column;
			}

			.method-tab {
				border-bottom: 1px solid #ddd;
			}
		}
	</style>
</head>
<body>
	<div class="wpmatch-2fa-container">
		<div class="wpmatch-2fa-header">
			<span class="shield-icon">🛡️</span>
			<h1><?php esc_html_e( 'Two-Factor Authentication', 'wpmatch' ); ?></h1>
		</div>

		<div class="wpmatch-2fa-body">
			<div class="wpmatch-2fa-user-info">
				<?php echo get_avatar( $user, 64, '', '', array( 'class' => 'avatar' ) ); ?>
				<div class="user-name"><?php echo esc_html( $user->display_name ); ?></div>
				<div class="user-email"><?php echo esc_html( $user->user_email ); ?></div>
			</div>

			<?php if ( isset( $error_message ) ) : ?>
				<div class="error-message">
					<?php echo esc_html( $error_message ); ?>
				</div>
			<?php endif; ?>

			<?php if ( isset( $success_message ) ) : ?>
				<div class="success-message">
					<?php echo esc_html( $success_message ); ?>
				</div>
			<?php endif; ?>

			<form method="post" action="" id="wpmatch-2fa-form">
				<?php wp_nonce_field( 'wpmatch_2fa_verify', 'wpmatch_2fa_nonce' ); ?>

				<div class="wpmatch-2fa-methods">
					<?php if ( count( $available_methods ) > 1 ) : ?>
						<div class="method-tabs">
							<?php foreach ( $available_methods as $index => $method ) : ?>
								<button type="button" class="method-tab <?php echo 0 === $index ? 'active' : ''; ?>" data-method="<?php echo esc_attr( $method ); ?>">
									<?php
									switch ( $method ) {
										case WPMatch_Two_Factor::METHOD_EMAIL:
											esc_html_e( 'Email', 'wpmatch' );
											break;
										case WPMatch_Two_Factor::METHOD_TOTP:
											esc_html_e( 'Authenticator App', 'wpmatch' );
											break;
										case WPMatch_Two_Factor::METHOD_BACKUP_CODES:
											esc_html_e( 'Backup Code', 'wpmatch' );
											break;
										case WPMatch_Two_Factor::METHOD_SMS:
											esc_html_e( 'SMS', 'wpmatch' );
											break;
									}
									?>
								</button>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

					<?php foreach ( $available_methods as $index => $method ) : ?>
						<div class="method-content <?php echo 0 === $index ? 'active' : ''; ?>" data-method="<?php echo esc_attr( $method ); ?>">
							<?php if ( WPMatch_Two_Factor::METHOD_EMAIL === $method ) : ?>
								<div class="form-group">
									<label for="verification_code_email"><?php esc_html_e( 'Email Verification Code', 'wpmatch' ); ?></label>
									<input type="text" name="verification_code" id="verification_code_email" maxlength="6" autocomplete="one-time-code" placeholder="000000" required>
									<div class="form-help">
										<?php esc_html_e( 'Enter the 6-digit code sent to your email address.', 'wpmatch' ); ?>
									</div>
								</div>
								<button type="button" class="resend-code" data-user-id="<?php echo esc_attr( $user_id ); ?>" data-method="email">
									<?php esc_html_e( 'Resend Code', 'wpmatch' ); ?>
								</button>

							<?php elseif ( WPMatch_Two_Factor::METHOD_TOTP === $method ) : ?>
								<div class="form-group">
									<label for="verification_code_totp"><?php esc_html_e( 'Authenticator Code', 'wpmatch' ); ?></label>
									<input type="text" name="verification_code" id="verification_code_totp" maxlength="6" autocomplete="one-time-code" placeholder="000000" required>
									<div class="form-help">
										<?php esc_html_e( 'Enter the 6-digit code from your authenticator app.', 'wpmatch' ); ?>
									</div>
								</div>

							<?php elseif ( WPMatch_Two_Factor::METHOD_BACKUP_CODES === $method ) : ?>
								<div class="form-group">
									<label for="verification_code_backup"><?php esc_html_e( 'Backup Code', 'wpmatch' ); ?></label>
									<input type="text" name="verification_code" id="verification_code_backup" maxlength="9" autocomplete="one-time-code" placeholder="xxxx-xxxx" required>
									<div class="form-help">
										<?php esc_html_e( 'Enter one of your backup codes. Each code can only be used once.', 'wpmatch' ); ?>
									</div>
								</div>

							<?php elseif ( WPMatch_Two_Factor::METHOD_SMS === $method ) : ?>
								<div class="form-group">
									<label for="verification_code_sms"><?php esc_html_e( 'SMS Verification Code', 'wpmatch' ); ?></label>
									<input type="text" name="verification_code" id="verification_code_sms" maxlength="6" autocomplete="one-time-code" placeholder="000000" required>
									<div class="form-help">
										<?php esc_html_e( 'Enter the 6-digit code sent to your phone.', 'wpmatch' ); ?>
									</div>
								</div>
								<button type="button" class="resend-code" data-user-id="<?php echo esc_attr( $user_id ); ?>" data-method="sms">
									<?php esc_html_e( 'Resend Code', 'wpmatch' ); ?>
								</button>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>

				<input type="hidden" name="method" value="<?php echo esc_attr( $preferred_method ); ?>" id="selected-method">

				<button type="submit" name="wpmatch_2fa_verify" class="submit-button">
					<?php esc_html_e( 'Verify & Login', 'wpmatch' ); ?>
				</button>
			</form>
		</div>

		<div class="wpmatch-2fa-footer">
			<a href="<?php echo esc_url( $login_url ); ?>">
				&larr; <?php esc_html_e( 'Back to Login', 'wpmatch' ); ?>
			</a>
		</div>
	</div>

	<script>
		(function() {
			// Tab switching
			const tabs = document.querySelectorAll('.method-tab');
			const contents = document.querySelectorAll('.method-content');
			const methodInput = document.getElementById('selected-method');

			tabs.forEach(function(tab, index) {
				tab.addEventListener('click', function() {
					const method = this.dataset.method;

					// Update active states
					tabs.forEach(t => t.classList.remove('active'));
					contents.forEach(c => c.classList.remove('active'));

					this.classList.add('active');
					document.querySelector('.method-content[data-method="' + method + '"]').classList.add('active');

					// Update hidden input
					methodInput.value = method;

					// Clear previous input
					const activeInput = document.querySelector('.method-content.active input[name="verification_code"]');
					if (activeInput) {
						activeInput.focus();
						activeInput.value = '';
					}
				});
			});

			// Auto-format codes
			document.querySelectorAll('input[name="verification_code"]').forEach(function(input) {
				input.addEventListener('input', function() {
					let value = this.value.replace(/[^0-9a-zA-Z-]/g, '');

					if (this.id === 'verification_code_backup') {
						// Format backup codes as xxxx-xxxx
						value = value.replace(/-/g, '');
						if (value.length > 4) {
							value = value.substring(0, 4) + '-' + value.substring(4, 8);
						}
					} else {
						// Numeric codes only
						value = value.replace(/[^0-9]/g, '');
					}

					this.value = value;
				});

				// Auto-submit when code is complete
				input.addEventListener('input', function() {
					if (this.id === 'verification_code_backup' && this.value.length === 9) {
						document.getElementById('wpmatch-2fa-form').submit();
					} else if (this.id !== 'verification_code_backup' && this.value.length === 6) {
						document.getElementById('wpmatch-2fa-form').submit();
					}
				});
			});

			// Resend code functionality
			document.querySelectorAll('.resend-code').forEach(function(button) {
				button.addEventListener('click', function() {
					const userId = this.dataset.userId;
					const method = this.dataset.method;
					const originalText = this.textContent;

					this.textContent = '<?php echo esc_js( __( 'Sending...', 'wpmatch' ) ); ?>';
					this.style.pointerEvents = 'none';

					// Make AJAX request to resend code
					const formData = new FormData();
					formData.append('action', 'wpmatch_send_2fa_code');
					formData.append('nonce', '<?php echo esc_js( wp_create_nonce( 'wpmatch_2fa_nonce' ) ); ?>');
					formData.append('user_id', userId);
					formData.append('method', method);

					fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
						method: 'POST',
						body: formData
					})
					.then(response => response.json())
					.then(data => {
						if (data.success) {
							this.textContent = '<?php echo esc_js( __( 'Code Sent!', 'wpmatch' ) ); ?>';
							setTimeout(() => {
								this.textContent = originalText;
								this.style.pointerEvents = 'auto';
							}, 3000);
						} else {
							this.textContent = '<?php echo esc_js( __( 'Send Failed', 'wpmatch' ) ); ?>';
							setTimeout(() => {
								this.textContent = originalText;
								this.style.pointerEvents = 'auto';
							}, 3000);
						}
					})
					.catch(() => {
						this.textContent = originalText;
						this.style.pointerEvents = 'auto';
					});
				});
			});

			// Focus first input
			const firstInput = document.querySelector('.method-content.active input[name="verification_code"]');
			if (firstInput) {
				firstInput.focus();
			}
		})();
	</script>

	<?php wp_footer(); ?>
</body>
</html>