<?php
/**
 * WPMatch Cookie Consent Banner
 *
 * GDPR-compliant cookie consent banner.
 *
 * @package WPMatch
 * @since   1.0.0
 */

// Security check.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div id="wpmatch-cookie-consent-banner" class="wpmatch-cookie-banner">
	<div class="cookie-banner-content">
		<div class="cookie-banner-icon">
			<span class="dashicons dashicons-privacy"></span>
		</div>
		<div class="cookie-banner-text">
			<h4><?php esc_html_e( 'We Value Your Privacy', 'wpmatch' ); ?></h4>
			<p>
				<?php
				printf(
					/* translators: %1$s: Privacy policy link start, %2$s: Privacy policy link end */
					esc_html__( 'We use cookies and similar technologies to enhance your experience, analyze traffic, and personalize content. By continuing to use our site, you consent to our use of cookies. Learn more in our %1$sprivacy policy%2$s.', 'wpmatch' ),
					'<a href="' . esc_url( get_privacy_policy_url() ) . '" target="_blank">',
					'</a>'
				);
				?>
			</p>
		</div>
		<div class="cookie-banner-actions">
			<button type="button" class="wpmatch-button primary accept-all-cookies">
				<?php esc_html_e( 'Accept All', 'wpmatch' ); ?>
			</button>
			<button type="button" class="wpmatch-button secondary customize-cookies">
				<?php esc_html_e( 'Customize', 'wpmatch' ); ?>
			</button>
			<button type="button" class="wpmatch-button tertiary reject-cookies">
				<?php esc_html_e( 'Reject All', 'wpmatch' ); ?>
			</button>
		</div>
	</div>

	<!-- Cookie Preferences Modal -->
	<div id="wpmatch-cookie-preferences-modal" class="cookie-preferences-modal" style="display: none;">
		<div class="modal-overlay"></div>
		<div class="modal-content">
			<div class="modal-header">
				<h3><?php esc_html_e( 'Cookie Preferences', 'wpmatch' ); ?></h3>
				<button type="button" class="modal-close">
					<span class="dashicons dashicons-no-alt"></span>
				</button>
			</div>
			<div class="modal-body">
				<p><?php esc_html_e( 'Choose which types of cookies you want to allow. You can change these settings at any time.', 'wpmatch' ); ?></p>

				<div class="cookie-category">
					<div class="category-header">
						<label class="category-toggle">
							<input type="checkbox" checked disabled>
							<span class="toggle-slider required"></span>
							<strong><?php esc_html_e( 'Essential Cookies', 'wpmatch' ); ?></strong>
							<span class="required-badge"><?php esc_html_e( 'Required', 'wpmatch' ); ?></span>
						</label>
					</div>
					<div class="category-description">
						<p><?php esc_html_e( 'These cookies are necessary for the website to function and cannot be switched off. They are usually only set in response to actions made by you which amount to a request for services.', 'wpmatch' ); ?></p>
						<strong><?php esc_html_e( 'Used for:', 'wpmatch' ); ?></strong>
						<ul>
							<li><?php esc_html_e( 'User authentication and security', 'wpmatch' ); ?></li>
							<li><?php esc_html_e( 'Form submissions and preferences', 'wpmatch' ); ?></li>
							<li><?php esc_html_e( 'Shopping cart functionality', 'wpmatch' ); ?></li>
						</ul>
					</div>
				</div>

				<div class="cookie-category">
					<div class="category-header">
						<label class="category-toggle">
							<input type="checkbox" id="analytics-cookies" name="analytics">
							<span class="toggle-slider"></span>
							<strong><?php esc_html_e( 'Analytics Cookies', 'wpmatch' ); ?></strong>
						</label>
					</div>
					<div class="category-description">
						<p><?php esc_html_e( 'These cookies help us understand how visitors interact with our website by collecting and reporting information anonymously.', 'wpmatch' ); ?></p>
						<strong><?php esc_html_e( 'Used for:', 'wpmatch' ); ?></strong>
						<ul>
							<li><?php esc_html_e( 'Website traffic analysis', 'wpmatch' ); ?></li>
							<li><?php esc_html_e( 'Feature usage statistics', 'wpmatch' ); ?></li>
							<li><?php esc_html_e( 'Performance monitoring', 'wpmatch' ); ?></li>
						</ul>
					</div>
				</div>

				<div class="cookie-category">
					<div class="category-header">
						<label class="category-toggle">
							<input type="checkbox" id="marketing-cookies" name="marketing">
							<span class="toggle-slider"></span>
							<strong><?php esc_html_e( 'Marketing Cookies', 'wpmatch' ); ?></strong>
						</label>
					</div>
					<div class="category-description">
						<p><?php esc_html_e( 'These cookies are used to deliver relevant advertisements and track the effectiveness of our advertising campaigns.', 'wpmatch' ); ?></p>
						<strong><?php esc_html_e( 'Used for:', 'wpmatch' ); ?></strong>
						<ul>
							<li><?php esc_html_e( 'Personalized advertising', 'wpmatch' ); ?></li>
							<li><?php esc_html_e( 'Social media integration', 'wpmatch' ); ?></li>
							<li><?php esc_html_e( 'Campaign effectiveness tracking', 'wpmatch' ); ?></li>
						</ul>
					</div>
				</div>

				<div class="cookie-category">
					<div class="category-header">
						<label class="category-toggle">
							<input type="checkbox" id="functional-cookies" name="functional">
							<span class="toggle-slider"></span>
							<strong><?php esc_html_e( 'Functional Cookies', 'wpmatch' ); ?></strong>
						</label>
					</div>
					<div class="category-description">
						<p><?php esc_html_e( 'These cookies enable enhanced functionality and personalization, such as videos and live chats.', 'wpmatch' ); ?></p>
						<strong><?php esc_html_e( 'Used for:', 'wpmatch' ); ?></strong>
						<ul>
							<li><?php esc_html_e( 'Video embedding and playback', 'wpmatch' ); ?></li>
							<li><?php esc_html_e( 'Live chat functionality', 'wpmatch' ); ?></li>
							<li><?php esc_html_e( 'Enhanced user interface features', 'wpmatch' ); ?></li>
						</ul>
					</div>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="wpmatch-button secondary" id="save-cookie-preferences">
					<?php esc_html_e( 'Save Preferences', 'wpmatch' ); ?>
				</button>
				<button type="button" class="wpmatch-button primary" id="accept-selected-cookies">
					<?php esc_html_e( 'Accept Selected', 'wpmatch' ); ?>
				</button>
			</div>
		</div>
	</div>
</div>

<script type="text/javascript">
(function() {
	'use strict';

	// Cookie consent banner functionality
	const banner = document.getElementById('wpmatch-cookie-consent-banner');
	const modal = document.getElementById('wpmatch-cookie-preferences-modal');

	if (!banner) return;

	// Handle accept all cookies
	const acceptAllBtn = banner.querySelector('.accept-all-cookies');
	if (acceptAllBtn) {
		acceptAllBtn.addEventListener('click', function() {
			setCookieConsent({
				essential: true,
				analytics: true,
				marketing: true,
				functional: true
			});
			hideBanner();
		});
	}

	// Handle reject all cookies
	const rejectBtn = banner.querySelector('.reject-cookies');
	if (rejectBtn) {
		rejectBtn.addEventListener('click', function() {
			setCookieConsent({
				essential: true,
				analytics: false,
				marketing: false,
				functional: false
			});
			hideBanner();
		});
	}

	// Handle customize cookies
	const customizeBtn = banner.querySelector('.customize-cookies');
	if (customizeBtn && modal) {
		customizeBtn.addEventListener('click', function() {
			showModal();
		});
	}

	// Modal functionality
	if (modal) {
		// Close modal
		const closeBtn = modal.querySelector('.modal-close');
		const overlay = modal.querySelector('.modal-overlay');

		if (closeBtn) {
			closeBtn.addEventListener('click', hideModal);
		}
		if (overlay) {
			overlay.addEventListener('click', hideModal);
		}

		// Save preferences
		const saveBtn = modal.querySelector('#save-cookie-preferences');
		if (saveBtn) {
			saveBtn.addEventListener('click', function() {
				const preferences = getModalPreferences();
				setCookieConsent(preferences);
				hideModal();
				hideBanner();
			});
		}

		// Accept selected
		const acceptSelectedBtn = modal.querySelector('#accept-selected-cookies');
		if (acceptSelectedBtn) {
			acceptSelectedBtn.addEventListener('click', function() {
				const preferences = getModalPreferences();
				setCookieConsent(preferences);
				hideModal();
				hideBanner();
			});
		}
	}

	function showModal() {
		if (modal) {
			modal.style.display = 'flex';
			document.body.style.overflow = 'hidden';
		}
	}

	function hideModal() {
		if (modal) {
			modal.style.display = 'none';
			document.body.style.overflow = '';
		}
	}

	function hideBanner() {
		if (banner) {
			banner.style.display = 'none';
		}
	}

	function getModalPreferences() {
		const analytics = modal.querySelector('#analytics-cookies').checked;
		const marketing = modal.querySelector('#marketing-cookies').checked;
		const functional = modal.querySelector('#functional-cookies').checked;

		return {
			essential: true, // Always true
			analytics: analytics,
			marketing: marketing,
			functional: functional
		};
	}

	function setCookieConsent(preferences) {
		// Set cookie for non-logged in users
		const expires = new Date();
		expires.setFullYear(expires.getFullYear() + 1);
		document.cookie = 'wpmatch_cookie_consent=' + JSON.stringify(preferences) + '; expires=' + expires.toUTCString() + '; path=/; SameSite=Lax';

		// Send to server for logged-in users
		if (typeof wpmatchConsent !== 'undefined') {
			const data = new FormData();
			data.append('action', 'wpmatch_update_consent');
			data.append('nonce', wpmatchConsent.nonce);
			data.append('analytics', preferences.analytics);
			data.append('marketing', preferences.marketing);
			data.append('communication', true); // Always allow for logged users
			data.append('cookies', true);

			fetch(wpmatchConsent.ajaxurl, {
				method: 'POST',
				body: data
			}).then(response => response.json()).then(data => {
				if (data.success) {
					console.log('Cookie preferences saved');
				}
			});
		}

		// Trigger custom event for other scripts
		const event = new CustomEvent('wpmatchCookieConsent', {
			detail: preferences
		});
		document.dispatchEvent(event);
	}

	// Auto-hide banner after 30 seconds if no interaction
	setTimeout(function() {
		if (banner && banner.style.display !== 'none') {
			// Assume implied consent after timeout (this may not be GDPR compliant in all jurisdictions)
			// Remove this for stricter compliance
			// hideBanner();
		}
	}, 30000);

})();
</script>

<style>
.wpmatch-cookie-banner {
	position: fixed;
	bottom: 0;
	left: 0;
	right: 0;
	background: linear-gradient(135deg, rgba(45, 52, 54, 0.98) 0%, rgba(99, 110, 114, 0.98) 100%);
	color: white;
	padding: 20px;
	z-index: 999999;
	box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.3);
	backdrop-filter: blur(10px);
}

.cookie-banner-content {
	max-width: 1200px;
	margin: 0 auto;
	display: grid;
	grid-template-columns: auto 1fr auto;
	gap: 20px;
	align-items: center;
}

.cookie-banner-icon .dashicons {
	font-size: 24px;
	color: #fd297b;
}

.cookie-banner-text h4 {
	margin: 0 0 8px;
	color: white;
	font-size: 1.1em;
}

.cookie-banner-text p {
	margin: 0;
	line-height: 1.5;
	color: rgba(255, 255, 255, 0.9);
}

.cookie-banner-text a {
	color: #fd297b;
	text-decoration: underline;
}

.cookie-banner-actions {
	display: flex;
	gap: 10px;
	flex-wrap: wrap;
}

.cookie-banner-actions .wpmatch-button {
	padding: 8px 16px;
	font-size: 14px;
	white-space: nowrap;
}

/* Cookie Preferences Modal */
.cookie-preferences-modal {
	position: fixed;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;
	z-index: 1000000;
	display: flex;
	align-items: center;
	justify-content: center;
}

.modal-overlay {
	position: absolute;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;
	background: rgba(0, 0, 0, 0.7);
	backdrop-filter: blur(4px);
}

.modal-content {
	position: relative;
	background: white;
	border-radius: 12px;
	max-width: 600px;
	width: 90%;
	max-height: 80vh;
	overflow: hidden;
	box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}

.modal-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 20px 25px;
	border-bottom: 1px solid #e1e4e8;
	background: #f8f9fa;
}

.modal-header h3 {
	margin: 0;
	color: #2d3436;
}

.modal-close {
	background: none;
	border: none;
	cursor: pointer;
	padding: 5px;
	color: #666;
	font-size: 18px;
}

.modal-body {
	padding: 25px;
	max-height: 60vh;
	overflow-y: auto;
}

.cookie-category {
	margin-bottom: 25px;
	border: 1px solid #e1e4e8;
	border-radius: 8px;
	overflow: hidden;
}

.category-header {
	background: #f8f9fa;
	padding: 15px 20px;
	border-bottom: 1px solid #e1e4e8;
}

.category-toggle {
	display: flex;
	align-items: center;
	gap: 12px;
	cursor: pointer;
	margin: 0;
}

.category-toggle input[type="checkbox"] {
	display: none;
}

.toggle-slider {
	position: relative;
	width: 44px;
	height: 24px;
	background: #ccc;
	border-radius: 24px;
	transition: all 0.3s;
}

.toggle-slider:before {
	content: '';
	position: absolute;
	width: 18px;
	height: 18px;
	background: white;
	border-radius: 50%;
	top: 3px;
	left: 3px;
	transition: all 0.3s;
}

input[type="checkbox"]:checked + .toggle-slider {
	background: #fd297b;
}

input[type="checkbox"]:checked + .toggle-slider:before {
	transform: translateX(20px);
}

.toggle-slider.required {
	background: #11998e;
	opacity: 0.7;
}

.required-badge {
	background: #11998e;
	color: white;
	padding: 2px 8px;
	border-radius: 10px;
	font-size: 0.75em;
	font-weight: 500;
	margin-left: auto;
}

.category-description {
	padding: 20px;
}

.category-description p {
	margin: 0 0 15px;
	color: #666;
	line-height: 1.5;
}

.category-description ul {
	margin: 8px 0 0 20px;
	color: #666;
}

.category-description li {
	margin-bottom: 5px;
}

.modal-footer {
	padding: 20px 25px;
	border-top: 1px solid #e1e4e8;
	background: #f8f9fa;
	display: flex;
	gap: 10px;
	justify-content: flex-end;
}

/* Responsive Design */
@media (max-width: 768px) {
	.cookie-banner-content {
		grid-template-columns: 1fr;
		gap: 15px;
		text-align: center;
	}

	.cookie-banner-actions {
		justify-content: center;
	}

	.modal-content {
		width: 95%;
		margin: 10px;
	}

	.modal-footer {
		flex-direction: column;
	}

	.category-toggle {
		flex-wrap: wrap;
	}

	.required-badge {
		margin-left: 0;
		margin-top: 5px;
	}
}
</style>