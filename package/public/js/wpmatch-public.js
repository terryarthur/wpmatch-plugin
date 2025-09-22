/**
 * Public JavaScript for WPMatch plugin
 *
 * @package WPMatch
 */

(function($) {
    'use strict';

    /**
     * Public functionality
     */
    var WPMatchPublic = {

        /**
         * Initialize public functions
         */
        init: function() {
            this.bindEvents();
            this.initializeComponents();
        },

        /**
         * Bind public events
         */
        bindEvents: function() {
            // Profile form submission
            $(document).on('submit', '.wpmatch-profile-form', this.updateProfile);

            // Photo upload
            $(document).on('change', '.wpmatch-photo-upload', this.uploadPhoto);

            // Search form
            $(document).on('submit', '.wpmatch-search-form', this.performSearch);

            // Match actions
            $(document).on('click', '.wpmatch-like-btn', this.likeProfile);
            $(document).on('click', '.wpmatch-pass-btn', this.passProfile);
            $(document).on('click', '.wpmatch-message-btn', this.openMessage);

            // Registration form
            $(document).on('submit', '.wpmatch-registration-form', this.registerUser);
        },

        /**
         * Initialize components
         */
        initializeComponents: function() {
            // Initialize any third-party components
            this.initPhotoUpload();
            this.initLocationServices();
        },

        /**
         * Update user profile
         */
        updateProfile: function(e) {
            e.preventDefault();

            var $form = $(this);
            var formData = new FormData($form[0]);

            $.ajax({
                url: wpmatch_public.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                beforeSend: function() {
                    $form.find('button[type="submit"]').prop('disabled', true);
                    WPMatchPublic.showMessage('Updating profile...', 'info');
                },
                success: function(response) {
                    if (response.success) {
                        WPMatchPublic.showMessage('Profile updated successfully!', 'success');
                    } else {
                        WPMatchPublic.showMessage('Failed to update profile: ' + response.data.message, 'error');
                    }
                },
                error: function() {
                    WPMatchPublic.showMessage('An error occurred while updating profile.', 'error');
                },
                complete: function() {
                    $form.find('button[type="submit"]').prop('disabled', false);
                }
            });
        },

        /**
         * Upload photo
         */
        uploadPhoto: function() {
            var $input = $(this);
            var file = $input[0].files[0];

            if (!file) {
                return;
            }

            // Validate file type
            var allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            if (allowedTypes.indexOf(file.type) === -1) {
                WPMatchPublic.showMessage('Please select a valid image file (JPG, PNG, or GIF).', 'error');
                return;
            }

            // Validate file size (5MB max)
            if (file.size > 5 * 1024 * 1024) {
                WPMatchPublic.showMessage('File size must be less than 5MB.', 'error');
                return;
            }

            var formData = new FormData();
            formData.append('photo', file);
            formData.append('action', 'wpmatch_public_action');
            formData.append('action_type', 'upload_photo');
            formData.append('nonce', wpmatch_public.nonce);

            $.ajax({
                url: wpmatch_public.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                beforeSend: function() {
                    WPMatchPublic.showMessage('Uploading photo...', 'info');
                },
                success: function(response) {
                    if (response.success) {
                        WPMatchPublic.showMessage('Photo uploaded successfully!', 'success');
                        // Refresh photo gallery
                        location.reload();
                    } else {
                        WPMatchPublic.showMessage('Failed to upload photo: ' + response.data.message, 'error');
                    }
                },
                error: function() {
                    WPMatchPublic.showMessage('An error occurred while uploading photo.', 'error');
                }
            });
        },

        /**
         * Perform search
         */
        performSearch: function(e) {
            e.preventDefault();

            var $form = $(this);
            var formData = $form.serialize();

            $.ajax({
                url: wpmatch_public.ajax_url,
                type: 'POST',
                data: formData + '&action=wpmatch_public_action&action_type=search_profiles&nonce=' + wpmatch_public.nonce,
                beforeSend: function() {
                    $('.wpmatch-search-results').html('<div class="wpmatch-loading"><div class="wpmatch-spinner"></div> Searching...</div>');
                },
                success: function(response) {
                    if (response.success) {
                        $('.wpmatch-search-results').html(response.data.html);
                    } else {
                        WPMatchPublic.showMessage('Search failed: ' + response.data.message, 'error');
                    }
                },
                error: function() {
                    WPMatchPublic.showMessage('An error occurred during search.', 'error');
                }
            });
        },

        /**
         * Like a profile
         */
        likeProfile: function(e) {
            e.preventDefault();

            var $btn = $(this);
            var profileId = $btn.data('profile-id');

            $.ajax({
                url: wpmatch_public.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpmatch_public_action',
                    action_type: 'like_profile',
                    profile_id: profileId,
                    nonce: wpmatch_public.nonce
                },
                beforeSend: function() {
                    $btn.prop('disabled', true);
                },
                success: function(response) {
                    if (response.success) {
                        $btn.removeClass('wpmatch-button').addClass('wpmatch-button success');
                        $btn.text('Liked!');

                        if (response.data.match) {
                            WPMatchPublic.showMessage('It\'s a match! 🎉', 'success');
                        }
                    } else {
                        WPMatchPublic.showMessage('Failed to like profile: ' + response.data.message, 'error');
                        $btn.prop('disabled', false);
                    }
                },
                error: function() {
                    WPMatchPublic.showMessage('An error occurred while liking profile.', 'error');
                    $btn.prop('disabled', false);
                }
            });
        },

        /**
         * Pass on a profile
         */
        passProfile: function(e) {
            e.preventDefault();

            var $btn = $(this);
            var profileId = $btn.data('profile-id');

            $.ajax({
                url: wpmatch_public.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpmatch_public_action',
                    action_type: 'pass_profile',
                    profile_id: profileId,
                    nonce: wpmatch_public.nonce
                },
                beforeSend: function() {
                    $btn.prop('disabled', true);
                },
                success: function(response) {
                    if (response.success) {
                        $btn.closest('.wpmatch-match-card').fadeOut();
                    } else {
                        WPMatchPublic.showMessage('Failed to pass profile: ' + response.data.message, 'error');
                        $btn.prop('disabled', false);
                    }
                },
                error: function() {
                    WPMatchPublic.showMessage('An error occurred while passing profile.', 'error');
                    $btn.prop('disabled', false);
                }
            });
        },

        /**
         * Open message dialog
         */
        openMessage: function(e) {
            e.preventDefault();

            var profileId = $(this).data('profile-id');
            // This would open a message dialog/modal
            WPMatchPublic.showMessage('Messaging functionality coming soon!', 'info');
        },

        /**
         * Register new user
         */
        registerUser: function(e) {
            e.preventDefault();

            var $form = $(this);
            var formData = $form.serialize();

            $.ajax({
                url: wpmatch_public.ajax_url,
                type: 'POST',
                data: formData + '&action=wpmatch_public_action&action_type=register_user&nonce=' + wpmatch_public.nonce,
                beforeSend: function() {
                    $form.find('button[type="submit"]').prop('disabled', true);
                    WPMatchPublic.showMessage('Creating account...', 'info');
                },
                success: function(response) {
                    if (response.success) {
                        WPMatchPublic.showMessage('Account created successfully! Please check your email for verification.', 'success');
                        $form[0].reset();
                    } else {
                        WPMatchPublic.showMessage('Registration failed: ' + response.data.message, 'error');
                    }
                },
                error: function() {
                    WPMatchPublic.showMessage('An error occurred during registration.', 'error');
                },
                complete: function() {
                    $form.find('button[type="submit"]').prop('disabled', false);
                }
            });
        },

        /**
         * Initialize photo upload functionality
         */
        initPhotoUpload: function() {
            // Add drag and drop functionality
            $('.wpmatch-photo-upload-area').on('dragover', function(e) {
                e.preventDefault();
                $(this).addClass('dragover');
            });

            $('.wpmatch-photo-upload-area').on('dragleave', function(e) {
                e.preventDefault();
                $(this).removeClass('dragover');
            });

            $('.wpmatch-photo-upload-area').on('drop', function(e) {
                e.preventDefault();
                $(this).removeClass('dragover');

                var files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    $('.wpmatch-photo-upload')[0].files = files;
                    $('.wpmatch-photo-upload').trigger('change');
                }
            });
        },

        /**
         * Initialize location services
         */
        initLocationServices: function() {
            if (!navigator.geolocation) {
                return;
            }

            $('.wpmatch-get-location').on('click', function(e) {
                e.preventDefault();

                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        $('#wpmatch-latitude').val(position.coords.latitude);
                        $('#wpmatch-longitude').val(position.coords.longitude);
                        WPMatchPublic.showMessage('Location updated successfully!', 'success');
                    },
                    function(error) {
                        WPMatchPublic.showMessage('Failed to get your location. Please try again.', 'error');
                    }
                );
            });
        },

        /**
         * Show message to user
         */
        showMessage: function(message, type) {
            var messageClass = 'wpmatch-message ' + type;
            var messageHtml = '<div class="' + messageClass + '">' + message + '</div>';

            // Remove existing messages
            $('.wpmatch-message').remove();

            // Add new message
            $('.wpmatch-public').prepend(messageHtml);

            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $('.wpmatch-message').fadeOut();
            }, 5000);
        }
    };

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        WPMatchPublic.init();
    });

})(jQuery);