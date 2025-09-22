jQuery(document).ready(function($) {
    'use strict';

    // Tab switching functionality
    $('.wpmatch-tab').on('click', function() {
        var tabId = $(this).data('tab');

        // Remove active class from all tabs and contents
        $('.wpmatch-tab').removeClass('active');
        $('.wpmatch-tab-content').removeClass('active');

        // Add active class to clicked tab and corresponding content
        $(this).addClass('active');
        $('#' + tabId).addClass('active');
    });

    // Photo upload functionality
    $('.wpmatch-photo-slot').on('click', function() {
        if (!$(this).hasClass('has-photo')) {
            var input = $('<input type="file" accept="image/*" style="display: none;">');
            $('body').append(input);

            input.on('change', function() {
                var file = this.files[0];
                if (file) {
                    var reader = new FileReader();
                    reader.onload = function(e) {
                        var slot = $('.wpmatch-photo-slot').filter(function() {
                            return !$(this).hasClass('has-photo');
                        }).first();

                        slot.addClass('has-photo');
                        slot.html('<img src="' + e.target.result + '" alt="Profile Photo">');

                        // Create hidden input for form submission
                        var hiddenInput = $('<input type="hidden" name="profile_photos[]">');
                        hiddenInput.val(e.target.result);
                        $('#wpmatch-profile-form').append(hiddenInput);
                    };
                    reader.readAsDataURL(file);
                }
                input.remove();
            });

            input.click();
        }
    });

    // Form validation
    $('#wpmatch-profile-form').on('submit', function(e) {
        var errors = [];

        // Required field validation
        var requiredFields = ['display_name', 'age', 'location'];
        requiredFields.forEach(function(field) {
            var value = $('[name="' + field + '"]').val();
            if (!value || value.trim() === '') {
                errors.push('Please fill in your ' + field.replace('_', ' '));
            }
        });

        // Age validation
        var age = parseInt($('[name="age"]').val());
        if (age && (age < 18 || age > 100)) {
            errors.push('Age must be between 18 and 100');
        }

        // Height validation
        var height = $('[name="height"]').val();
        if (height && !/^\d+'\s*\d*"?$/.test(height)) {
            errors.push('Height must be in format like 5\'8"');
        }

        if (errors.length > 0) {
            e.preventDefault();
            showNotice(errors.join('<br>'), 'error');
            return false;
        }

        showNotice('Saving profile...', 'success');
    });

    // AJAX form submission
    $('#wpmatch-profile-form').on('submit', function(e) {
        e.preventDefault();

        var formData = new FormData(this);
        formData.append('action', 'wpmatch_update_profile');
        formData.append('nonce', wpmatchProfile.nonce);

        $.ajax({
            url: wpmatchProfile.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function() {
                $('.wpmatch-submit-btn').prop('disabled', true).text('Saving...');
            },
            success: function(response) {
                if (response.success) {
                    showNotice('Profile updated successfully!', 'success');
                } else {
                    showNotice(response.data || 'An error occurred', 'error');
                }
            },
            error: function() {
                showNotice('Network error. Please try again.', 'error');
            },
            complete: function() {
                $('.wpmatch-submit-btn').prop('disabled', false).text('Save Profile');
            }
        });
    });

    // Auto-expand textarea
    $('textarea').on('input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    });

    // Character counter for bio
    $('[name="bio"]').on('input', function() {
        var maxLength = 500;
        var currentLength = $(this).val().length;
        var remaining = maxLength - currentLength;

        var counter = $(this).siblings('.character-counter');
        if (counter.length === 0) {
            counter = $('<div class="character-counter" style="text-align: right; font-size: 12px; color: #666; margin-top: 5px;"></div>');
            $(this).after(counter);
        }

        counter.text(remaining + ' characters remaining');
        if (remaining < 0) {
            counter.css('color', '#e91e63');
        } else {
            counter.css('color', '#666');
        }
    });

    // Interest selection with limit
    $('.wpmatch-interest-item input[type="checkbox"]').on('change', function() {
        var maxInterests = 10;
        var selected = $('.wpmatch-interest-item input[type="checkbox"]:checked').length;

        if (selected > maxInterests) {
            $(this).prop('checked', false);
            showNotice('You can select a maximum of ' + maxInterests + ' interests', 'error');
        }
    });

    // Location autocomplete (if Google Maps is available)
    if (typeof google !== 'undefined' && google.maps && google.maps.places) {
        var locationInput = $('[name="location"]')[0];
        if (locationInput) {
            var autocomplete = new google.maps.places.Autocomplete(locationInput, {
                types: ['(cities)'],
                componentRestrictions: {country: 'us'}
            });

            autocomplete.addListener('place_changed', function() {
                var place = autocomplete.getPlace();
                if (place.geometry) {
                    $('[name="latitude"]').val(place.geometry.location.lat());
                    $('[name="longitude"]').val(place.geometry.location.lng());
                }
            });
        }
    }

    // Show/hide sections based on preferences
    $('[name="looking_for"]').on('change', function() {
        var value = $(this).val();
        if (value === 'friends') {
            $('.dating-specific').hide();
        } else {
            $('.dating-specific').show();
        }
    });

    // Real-time validation feedback
    $('input, select, textarea').on('blur', function() {
        validateField($(this));
    });

    function validateField($field) {
        var fieldName = $field.attr('name');
        var value = $field.val();
        var isValid = true;
        var message = '';

        // Remove existing validation message
        $field.siblings('.validation-message').remove();
        $field.removeClass('error');

        switch (fieldName) {
            case 'age':
                var age = parseInt(value);
                if (value && (age < 18 || age > 100)) {
                    isValid = false;
                    message = 'Age must be between 18 and 100';
                }
                break;
            case 'height':
                if (value && !/^\d+'\s*\d*"?$/.test(value)) {
                    isValid = false;
                    message = 'Please enter height in format like 5\'8"';
                }
                break;
            case 'bio':
                if (value.length > 500) {
                    isValid = false;
                    message = 'Bio must be 500 characters or less';
                }
                break;
        }

        if (!isValid) {
            $field.addClass('error');
            var validationMessage = $('<div class="validation-message" style="color: #e91e63; font-size: 12px; margin-top: 5px;">' + message + '</div>');
            $field.after(validationMessage);
        }

        return isValid;
    }

    function showNotice(message, type) {
        var notice = $('.wpmatch-notice');
        if (notice.length === 0) {
            notice = $('<div class="wpmatch-notice"></div>');
            $('.wpmatch-profile-container').prepend(notice);
        }

        notice.removeClass('success error').addClass(type);
        notice.html(message).show();

        setTimeout(function() {
            notice.fadeOut();
        }, 5000);
    }

    // Initialize character counter on page load
    $('[name="bio"]').trigger('input');

    // Initialize looking_for change handler
    $('[name="looking_for"]').trigger('change');
});