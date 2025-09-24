/**
 * WPMatch Consent Management
 *
 * Handles privacy consent and cookie management for GDPR compliance.
 *
 * @package WPMatch
 * @since   1.0.0
 */

(function($) {
	'use strict';

	/**
	 * WPMatch Consent Management Object
	 */
	const WPMatchConsent = {

		/**
		 * Initialize consent management
		 */
		init: function() {
			this.bindEvents();
			this.loadUserPreferences();
			this.initializeConsentCheckers();
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function() {
			// Privacy settings form submission
			$(document).on('submit', '.wpmatch-privacy-form', this.handlePrivacyFormSubmit.bind(this));

			// Cookie consent changes
			$(document).on('change', '.consent-toggle', this.handleConsentToggle.bind(this));

			// Data export request
			$(document).on('click', '.request-data-export', this.handleDataExportRequest.bind(this));

			// Data deletion request
			$(document).on('click', '.request-data-deletion', this.handleDataDeletionRequest.bind(this));

			// Listen for cookie consent events
			document.addEventListener('wpmatchCookieConsent', this.handleCookieConsentChange.bind(this));
		},

		/**
		 * Load user's current privacy preferences
		 */
		loadUserPreferences: function() {
			if (!wpmatchConsent || !wpmatchConsent.userId) return;

			$.ajax({
				url: wpmatchConsent.ajaxurl,
				type: 'POST',
				data: {
					action: 'wpmatch_get_user_consent',
					nonce: wpmatchConsent.nonce,
					user_id: wpmatchConsent.userId
				},
				success: function(response) {
					if (response.success) {
						WPMatchConsent.updateConsentToggles(response.data.consent);
					}
				}
			});
		},

		/**
		 * Update consent toggle states
		 */
		updateConsentToggles: function(consent) {
			Object.keys(consent).forEach(function(key) {
				const toggle = $('.consent-toggle[data-type="' + key + '"]');
				if (toggle.length) {
					toggle.prop('checked', consent[key]);
					toggle.trigger('change', [true]); // Skip ajax on initial load
				}
			});
		},

		/**
		 * Handle privacy form submission
		 */
		handlePrivacyFormSubmit: function(e) {
			e.preventDefault();

			const $form = $(e.target);
			const formData = $form.serialize();

			$form.find('.submit-btn').prop('disabled', true).text('Saving...');

			$.ajax({
				url: wpmatchConsent.ajaxurl,
				type: 'POST',
				data: formData + '&action=wpmatch_update_consent&nonce=' + wpmatchConsent.nonce,
				success: function(response) {
					if (response.success) {
						WPMatchConsent.showNotice('success', wpmatchConsent.strings.consentUpdated);
						$form.find('.last-updated').text('Last updated: ' + new Date().toLocaleString());
					} else {
						WPMatchConsent.showNotice('error', response.data || wpmatchConsent.strings.consentError);
					}
				},
				error: function() {
					WPMatchConsent.showNotice('error', wpmatchConsent.strings.consentError);
				},
				complete: function() {
					$form.find('.submit-btn').prop('disabled', false).text('Save Preferences');
				}
			});
		},

		/**
		 * Handle individual consent toggle changes
		 */
		handleConsentToggle: function(e, skipAjax) {
			if (skipAjax) return;

			const $toggle = $(e.target);
			const consentType = $toggle.data('type');
			const enabled = $toggle.is(':checked');

			// Show impact warning for certain toggles
			if (!enabled && (consentType === 'analytics' || consentType === 'marketing')) {
				this.showConsentImpactWarning(consentType, enabled);
			}

			// Update consent in real-time
			this.updateSingleConsent(consentType, enabled);
		},

		/**
		 * Update a single consent setting
		 */
		updateSingleConsent: function(type, enabled) {
			const data = {
				action: 'wpmatch_update_single_consent',
				nonce: wpmatchConsent.nonce,
				consent_type: type,
				enabled: enabled
			};

			$.ajax({
				url: wpmatchConsent.ajaxurl,
				type: 'POST',
				data: data,
				success: function(response) {
					if (response.success) {
						WPMatchConsent.applyConsentChanges(type, enabled);
					}
				}
			});
		},

		/**
		 * Apply consent changes to current page
		 */
		applyConsentChanges: function(type, enabled) {
			switch(type) {
				case 'analytics':
					this.toggleAnalytics(enabled);
					break;
				case 'marketing':
					this.toggleMarketing(enabled);
					break;
				case 'location':
					this.toggleLocation(enabled);
					break;
			}
		},

		/**
		 * Toggle analytics tracking
		 */
		toggleAnalytics: function(enabled) {
			if (enabled) {
				// Re-enable analytics
				this.loadAnalyticsScripts();
			} else {
				// Disable analytics
				this.disableAnalyticsScripts();
			}
		},

		/**
		 * Toggle marketing cookies and scripts
		 */
		toggleMarketing: function(enabled) {
			if (enabled) {
				this.loadMarketingScripts();
			} else {
				this.disableMarketingScripts();
			}
		},

		/**
		 * Toggle location services
		 */
		toggleLocation: function(enabled) {
			if (!enabled) {
				// Clear stored location data
				this.clearLocationData();
			}
		},

		/**
		 * Load analytics scripts dynamically
		 */
		loadAnalyticsScripts: function() {
			// Example: Load Google Analytics
			if (wpmatchConsent.analyticsId && !window.gtag) {
				const script = document.createElement('script');
				script.async = true;
				script.src = 'https://www.googletagmanager.com/gtag/js?id=' + wpmatchConsent.analyticsId;
				document.head.appendChild(script);

				window.dataLayer = window.dataLayer || [];
				function gtag(){dataLayer.push(arguments);}
				gtag('js', new Date());
				gtag('config', wpmatchConsent.analyticsId, {
					anonymize_ip: true,
					cookie_flags: 'SameSite=Lax;Secure'
				});
			}
		},

		/**
		 * Disable analytics scripts
		 */
		disableAnalyticsScripts: function() {
			// Disable Google Analytics
			if (window.gtag) {
				gtag('consent', 'update', {
					analytics_storage: 'denied'
				});
			}
		},

		/**
		 * Load marketing scripts
		 */
		loadMarketingScripts: function() {
			// Load marketing pixels, social media widgets, etc.
			// This is where you'd load Facebook Pixel, Twitter conversion tracking, etc.
		},

		/**
		 * Disable marketing scripts
		 */
		disableMarketingScripts: function() {
			// Disable marketing tracking
			if (window.fbq) {
				fbq('consent', 'revoke');
			}
		},

		/**
		 * Clear stored location data
		 */
		clearLocationData: function() {
			$.ajax({
				url: wpmatchConsent.ajaxurl,
				type: 'POST',
				data: {
					action: 'wpmatch_clear_location_data',
					nonce: wpmatchConsent.nonce
				}
			});
		},

		/**
		 * Show consent impact warning
		 */
		showConsentImpactWarning: function(type, enabled) {
			let message = '';

			switch(type) {
				case 'analytics':
					message = 'Disabling analytics will prevent us from improving our service based on usage patterns.';
					break;
				case 'marketing':
					message = 'Disabling marketing cookies will result in less relevant advertisements.';
					break;
			}

			if (message && !enabled) {
				this.showNotice('warning', message, 5000);
			}
		},

		/**
		 * Handle cookie consent changes from banner
		 */
		handleCookieConsentChange: function(event) {
			const preferences = event.detail;

			// Apply preferences immediately
			this.applyConsentChanges('analytics', preferences.analytics);
			this.applyConsentChanges('marketing', preferences.marketing);

			// Update toggles if consent form is visible
			this.updateConsentToggles(preferences);
		},

		/**
		 * Handle data export request
		 */
		handleDataExportRequest: function(e) {
			e.preventDefault();

			if (!confirm('Request a complete export of your personal data? You will receive an email when the export is ready.')) {
				return;
			}

			const $btn = $(e.target);
			$btn.prop('disabled', true).text('Processing...');

			$.ajax({
				url: wpmatchConsent.ajaxurl,
				type: 'POST',
				data: {
					action: 'wpmatch_request_data_export',
					nonce: wpmatchConsent.nonce
				},
				success: function(response) {
					if (response.success) {
						WPMatchConsent.showNotice('success', 'Data export request submitted. You will receive an email when ready.');
					} else {
						WPMatchConsent.showNotice('error', response.data || 'Failed to submit export request.');
					}
				},
				error: function() {
					WPMatchConsent.showNotice('error', 'Failed to submit export request.');
				},
				complete: function() {
					$btn.prop('disabled', false).text('Request Data Export');
				}
			});
		},

		/**
		 * Handle data deletion request
		 */
		handleDataDeletionRequest: function(e) {
			e.preventDefault();

			const confirmText = 'DELETE';
			const userInput = prompt('This will permanently delete ALL your data including messages, matches, and profile information. This action cannot be undone.\n\nType "' + confirmText + '" to confirm:');

			if (userInput !== confirmText) {
				return;
			}

			const $btn = $(e.target);
			$btn.prop('disabled', true).text('Processing...');

			$.ajax({
				url: wpmatchConsent.ajaxurl,
				type: 'POST',
				data: {
					action: 'wpmatch_request_data_deletion',
					nonce: wpmatchConsent.nonce
				},
				success: function(response) {
					if (response.success) {
						WPMatchConsent.showNotice('success', 'Data deletion request submitted. Your account will be deleted within 30 days.');
					} else {
						WPMatchConsent.showNotice('error', response.data || 'Failed to submit deletion request.');
					}
				},
				error: function() {
					WPMatchConsent.showNotice('error', 'Failed to submit deletion request.');
				},
				complete: function() {
					$btn.prop('disabled', false).text('Request Data Deletion');
				}
			});
		},

		/**
		 * Initialize consent-based feature checkers
		 */
		initializeConsentCheckers: function() {
			// Check consent before enabling location features
			$(document).on('click', '.location-feature-trigger', function(e) {
				if (!WPMatchConsent.hasLocationConsent()) {
					e.preventDefault();
					WPMatchConsent.showLocationConsentRequest();
				}
			});

			// Check consent before analytics events
			$(document).on('click', '.track-event', function(e) {
				if (WPMatchConsent.hasAnalyticsConsent()) {
					const eventData = $(this).data('event');
					WPMatchConsent.trackEvent(eventData);
				}
			});
		},

		/**
		 * Check if user has given location consent
		 */
		hasLocationConsent: function() {
			const consent = this.getCurrentConsent();
			return consent && consent.location === true;
		},

		/**
		 * Check if user has given analytics consent
		 */
		hasAnalyticsConsent: function() {
			const consent = this.getCurrentConsent();
			return consent && consent.analytics === true;
		},

		/**
		 * Get current consent status
		 */
		getCurrentConsent: function() {
			// Try to get from cookie first
			const cookieConsent = this.getCookieConsent();
			if (cookieConsent) {
				return cookieConsent;
			}

			// Fall back to form values
			const consent = {};
			$('.consent-toggle').each(function() {
				const type = $(this).data('type');
				consent[type] = $(this).is(':checked');
			});
			return consent;
		},

		/**
		 * Get consent from cookie
		 */
		getCookieConsent: function() {
			const cookie = document.cookie.split(';').find(c => c.trim().startsWith('wpmatch_cookie_consent='));
			if (cookie) {
				try {
					return JSON.parse(cookie.split('=')[1]);
				} catch(e) {
					return null;
				}
			}
			return null;
		},

		/**
		 * Show location consent request
		 */
		showLocationConsentRequest: function() {
			const modal = $('<div class="consent-request-modal">' +
				'<div class="modal-overlay"></div>' +
				'<div class="modal-content">' +
					'<h3>Location Permission Required</h3>' +
					'<p>This feature requires access to your location data to provide distance-based matching. Your location will only be used for matching purposes and can be disabled at any time in your privacy settings.</p>' +
					'<div class="modal-actions">' +
						'<button class="wpmatch-button secondary dismiss-modal">Cancel</button>' +
						'<button class="wpmatch-button primary allow-location">Allow Location Access</button>' +
					'</div>' +
				'</div>' +
			'</div>');

			$('body').append(modal);
			modal.fadeIn();

			modal.on('click', '.dismiss-modal, .modal-overlay', function() {
				modal.fadeOut(function() {
					modal.remove();
				});
			});

			modal.on('click', '.allow-location', function() {
				WPMatchConsent.updateSingleConsent('location', true);
				$('.consent-toggle[data-type="location"]').prop('checked', true);
				modal.fadeOut(function() {
					modal.remove();
				});
			});
		},

		/**
		 * Track analytics event if consent given
		 */
		trackEvent: function(eventData) {
			if (window.gtag && this.hasAnalyticsConsent()) {
				gtag('event', eventData.action, {
					event_category: eventData.category,
					event_label: eventData.label,
					value: eventData.value
				});
			}
		},

		/**
		 * Show notification message
		 */
		showNotice: function(type, message, duration) {
			duration = duration || 3000;

			const notice = $('<div class="wpmatch-notice wpmatch-notice-' + type + '">' +
				'<span class="notice-icon"></span>' +
				'<span class="notice-text">' + message + '</span>' +
				'<button class="notice-dismiss">&times;</button>' +
			'</div>');

			$('body').append(notice);
			notice.slideDown();

			notice.on('click', '.notice-dismiss', function() {
				notice.slideUp(function() {
					notice.remove();
				});
			});

			setTimeout(function() {
				notice.slideUp(function() {
					notice.remove();
				});
			}, duration);
		}
	};

	// Initialize when document is ready
	$(document).ready(function() {
		WPMatchConsent.init();
	});

	// Export to global scope for use by other scripts
	window.WPMatchConsent = WPMatchConsent;

})(jQuery);