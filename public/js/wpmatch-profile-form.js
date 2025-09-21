/**
 * WPMatch Profile Form JavaScript
 *
 * Handles photo uploads, tab navigation, and form interactions.
 *
 * @package WPMatch
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * WPMatch Profile Form Controller
     */
    var WPMatchProfileForm = {

        /**
         * Initialize profile form functionality
         */
        init: function() {
            this.bindEvents();
            this.initTabs();
            this.initPhotoUpload();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Tab navigation
            $(document).on('click', '.wpmatch-tab', this.switchTab);

            // Photo upload
            $(document).on('change', '.wpmatch-photo-input', this.handlePhotoUpload);
            $(document).on('click', '.wpmatch-photo-action.delete', this.deletePhoto);
            $(document).on('click', '.wpmatch-photo-action.primary', this.setPrimaryPhoto);

            // Photo upload triggers
            $(document).on('click', '.wpmatch-photo-placeholder', this.triggerPhotoUpload);
        },

        /**
         * Initialize tab functionality
         */
        initTabs: function() {
            // Ensure only active tab panel is visible on load
            $('.wpmatch-tab-panel:not(.active)').hide();

            // Set first tab as active if none are active
            if (!$('.wpmatch-tab.active').length) {
                $('.wpmatch-tab:first').addClass('active').attr('aria-selected', 'true');
                $('.wpmatch-tab-panel:first').addClass('active').show();
            }
        },

        /**
         * Initialize photo upload functionality
         */
        initPhotoUpload: function() {
            // Add drag and drop functionality
            $('.wpmatch-photo-placeholder').on('dragover', function(e) {
                e.preventDefault();
                $(this).addClass('drag-over');
            });

            $('.wpmatch-photo-placeholder').on('dragleave', function(e) {
                e.preventDefault();
                $(this).removeClass('drag-over');
            });

            $('.wpmatch-photo-placeholder').on('drop', function(e) {
                e.preventDefault();
                $(this).removeClass('drag-over');

                var files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    var input = $(this).find('.wpmatch-photo-input')[0];
                    input.files = files;
                    $(input).trigger('change');
                }
            });
        },

        /**
         * Switch tabs
         */
        switchTab: function(e) {
            e.preventDefault();

            var $tab = $(this);
            var target = $tab.data('tab');

            // Don't switch if already active
            if ($tab.hasClass('active')) {
                return;
            }

            // Update tab appearance
            $('.wpmatch-tab').removeClass('active').attr('aria-selected', 'false');
            $tab.addClass('active').attr('aria-selected', 'true');

            // Update content visibility with smooth transitions
            $('.wpmatch-tab-panel.active').fadeOut(200, function() {
                $(this).removeClass('active');
                $('#tab-' + target).fadeIn(300).addClass('active');
            });
        },

        /**
         * Trigger photo upload
         */
        triggerPhotoUpload: function(e) {
            e.preventDefault();
            $(this).find('.wpmatch-photo-input').trigger('click');
        },

        /**
         * Handle photo upload
         */
        handlePhotoUpload: function(e) {
            var input = this;
            var $slot = $(input).closest('.wpmatch-photo-slot');
            var file = input.files[0];

            if (!file) {
                return;
            }

            // Validate file
            if (!WPMatchProfileForm.validatePhoto(file)) {
                return;
            }

            // Show loading state
            $slot.addClass('uploading');
            $slot.find('.wpmatch-upload-text').text(wpmatch_profile.strings.uploading);

            // Create form data
            var formData = new FormData();
            formData.append('action', 'wpmatch_public_action');
            formData.append('action_type', 'upload_photo');
            formData.append('photo', file);
            formData.append('nonce', wpmatch_profile.nonce);

            // Upload photo
            $.ajax({
                url: wpmatch_profile.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        WPMatchProfileForm.displayPhoto($slot, response.data);
                        WPMatchProfileForm.showMessage(response.data.message, 'success');
                    } else {
                        WPMatchProfileForm.showMessage(response.data.message, 'error');
                    }
                },
                error: function() {
                    WPMatchProfileForm.showMessage(wpmatch_profile.strings.upload_error, 'error');
                },
                complete: function() {
                    $slot.removeClass('uploading');
                    input.value = ''; // Reset input
                }
            });
        },

        /**
         * Validate photo before upload
         */
        validatePhoto: function(file) {
            // Check file type
            var allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            if (allowedTypes.indexOf(file.type) === -1) {
                this.showMessage(wpmatch_profile.strings.invalid_file_type, 'error');
                return false;
            }

            // Check file size (5MB)
            var maxSize = 5 * 1024 * 1024;
            if (file.size > maxSize) {
                this.showMessage(wpmatch_profile.strings.file_too_large, 'error');
                return false;
            }

            return true;
        },

        /**
         * Display uploaded photo
         */
        displayPhoto: function($slot, data) {
            var photoHtml = '<img src="' + data.photo_url + '" alt="Profile photo" class="wpmatch-photo-preview">' +
                           '<div class="wpmatch-photo-overlay">' +
                           '<button type="button" class="wpmatch-photo-action delete" data-photo-id="' + data.photo_id + '" title="Delete photo">' +
                           '<span class="dashicons dashicons-trash"></span>' +
                           '</button>';

            if (!data.is_primary) {
                photoHtml += '<button type="button" class="wpmatch-photo-action primary" data-photo-id="' + data.photo_id + '" title="Make primary">' +
                            '<span class="dashicons dashicons-star-filled"></span>' +
                            '</button>';
            }

            photoHtml += '</div>';

            if (data.is_primary) {
                photoHtml += '<div class="wpmatch-primary-badge">Main</div>';
            }

            $slot.html(photoHtml);
        },

        /**
         * Delete photo
         */
        deletePhoto: function(e) {
            e.preventDefault();

            if (!confirm(wpmatch_profile.strings.confirm_delete)) {
                return;
            }

            var $button = $(this);
            var photoId = $button.data('photo-id');
            var $slot = $button.closest('.wpmatch-photo-slot');

            $.ajax({
                url: wpmatch_profile.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpmatch_public_action',
                    action_type: 'delete_photo',
                    photo_id: photoId,
                    nonce: wpmatch_profile.nonce
                },
                success: function(response) {
                    if (response.success) {
                        WPMatchProfileForm.resetPhotoSlot($slot);
                        WPMatchProfileForm.showMessage(response.data.message, 'success');
                    } else {
                        WPMatchProfileForm.showMessage(response.data.message, 'error');
                    }
                },
                error: function() {
                    WPMatchProfileForm.showMessage(wpmatch_profile.strings.delete_error, 'error');
                }
            });
        },

        /**
         * Set primary photo
         */
        setPrimaryPhoto: function(e) {
            e.preventDefault();

            var $button = $(this);
            var photoId = $button.data('photo-id');

            $.ajax({
                url: wpmatch_profile.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpmatch_public_action',
                    action_type: 'set_primary_photo',
                    photo_id: photoId,
                    nonce: wpmatch_profile.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Remove all primary badges and buttons
                        $('.wpmatch-primary-badge').remove();
                        $('.wpmatch-photo-action.primary').remove();

                        // Add primary badge to this photo
                        $button.closest('.wpmatch-photo-slot').append('<div class="wpmatch-primary-badge">Main</div>');

                        // Add primary buttons to other photos
                        $('.wpmatch-photo-preview').each(function() {
                            var $slot = $(this).closest('.wpmatch-photo-slot');
                            if (!$slot.find('.wpmatch-primary-badge').length) {
                                var $overlay = $slot.find('.wpmatch-photo-overlay');
                                var currentPhotoId = $overlay.find('.delete').data('photo-id');
                                $overlay.append('<button type="button" class="wpmatch-photo-action primary" data-photo-id="' + currentPhotoId + '" title="Make primary">' +
                                              '<span class="dashicons dashicons-star-filled"></span></button>');
                            }
                        });

                        WPMatchProfileForm.showMessage(response.data.message, 'success');
                    } else {
                        WPMatchProfileForm.showMessage(response.data.message, 'error');
                    }
                },
                error: function() {
                    WPMatchProfileForm.showMessage(wpmatch_profile.strings.primary_error, 'error');
                }
            });
        },

        /**
         * Reset photo slot to empty state
         */
        resetPhotoSlot: function($slot) {
            var slotIndex = $slot.data('slot');
            var uploadText = slotIndex === 0 ? wpmatch_profile.strings.add_main_photo : wpmatch_profile.strings.add_photo;

            var html = '<div class="wpmatch-photo-placeholder">' +
                      '<div class="wpmatch-upload-prompt">' +
                      '<span class="dashicons dashicons-plus"></span>' +
                      '<span class="wpmatch-upload-text">' + uploadText + '</span>' +
                      '</div>' +
                      '<input type="file" class="wpmatch-photo-input" accept="image/*" data-slot="' + slotIndex + '">' +
                      '</div>';

            $slot.html(html);
        },

        /**
         * Show message to user
         */
        showMessage: function(message, type) {
            var $notice = $('<div class="wpmatch-notice ' + type + '"><p>' + message + '</p></div>');
            $('.wpmatch-profile-form-container h2').after($notice);

            // Auto-remove after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        }
    };

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        if ($('.wpmatch-profile-form').length) {
            WPMatchProfileForm.init();
        }
    });

})(jQuery);