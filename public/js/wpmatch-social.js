/**
 * WPMatch Social Integration JavaScript
 *
 * @package WPMatch
 * @subpackage Social
 * @since 1.0.0
 */

(function($) {
	'use strict';

	/**
	 * Social Integration functionality
	 */
	const WPMatchSocial = {

		/**
		 * Initialize social features
		 */
		init: function() {
			this.bindEvents();
			this.loadSocialButtons();
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function() {
			$(document).on('click', '.wpmatch-social-connect', this.handleSocialConnect);
			$(document).on('click', '.wpmatch-social-disconnect', this.handleSocialDisconnect);
			$(document).on('click', '.wpmatch-social-share', this.handleSocialShare);
		},

		/**
		 * Handle social platform connection
		 */
		handleSocialConnect: function(e) {
			e.preventDefault();

			const platform = $(this).data('platform');
			const popup = window.open(
				$(this).attr('href'),
				'social_connect',
				'width=600,height=400,scrollbars=yes,resizable=yes'
			);

			// Check for popup completion
			const checkClosed = setInterval(function() {
				if (popup.closed) {
					clearInterval(checkClosed);
					location.reload(); // Refresh to show connected state
				}
			}, 1000);
		},

		/**
		 * Handle social platform disconnection
		 */
		handleSocialDisconnect: function(e) {
			e.preventDefault();

			if (confirm('Are you sure you want to disconnect this social account?')) {
				const platform = $(this).data('platform');

				$.ajax({
					url: wpmatch_ajax.ajaxurl,
					type: 'POST',
					data: {
						action: 'wpmatch_disconnect_social',
						platform: platform,
						nonce: wpmatch_ajax.nonce
					},
					success: function(response) {
						if (response.success) {
							location.reload();
						} else {
							alert('Error disconnecting account: ' + response.data);
						}
					},
					error: function() {
						alert('Error disconnecting account. Please try again.');
					}
				});
			}
		},

		/**
		 * Handle social sharing
		 */
		handleSocialShare: function(e) {
			e.preventDefault();

			const platform = $(this).data('platform');
			const url = $(this).data('url') || window.location.href;
			const text = $(this).data('text') || '';

			let shareUrl = '';

			switch (platform) {
				case 'facebook':
					shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`;
					break;
				case 'twitter':
					shareUrl = `https://twitter.com/intent/tweet?url=${encodeURIComponent(url)}&text=${encodeURIComponent(text)}`;
					break;
				case 'linkedin':
					shareUrl = `https://www.linkedin.com/sharing/share-offsite/?url=${encodeURIComponent(url)}`;
					break;
				case 'instagram':
					// Instagram doesn't support direct sharing via URL
					alert('Please share manually on Instagram');
					return;
			}

			if (shareUrl) {
				window.open(shareUrl, 'social_share', 'width=600,height=400,scrollbars=yes,resizable=yes');
			}
		},

		/**
		 * Load social platform buttons
		 */
		loadSocialButtons: function() {
			// Initialize any social platform SDKs if needed
			if (typeof FB !== 'undefined') {
				FB.init({
					appId: wpmatch_social?.facebook_app_id || '',
					version: 'v18.0'
				});
			}
		}
	};

	// Initialize when document is ready
	$(document).ready(function() {
		WPMatchSocial.init();
	});

	// Expose to global scope if needed
	window.WPMatchSocial = WPMatchSocial;

})(jQuery);