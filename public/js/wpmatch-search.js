/**
 * WPMatch Search JavaScript
 *
 * Handles search functionality and interactions.
 *
 * @package WPMatch
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * WPMatch Search Controller
     */
    var WPMatchSearch = {

        /**
         * Initialize search functionality
         */
        init: function() {
            this.bindEvents();
            this.initDistanceSlider();
            this.initLocationAutocomplete();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // View toggle
            $(document).on('click', '.wpmatch-view-btn', this.toggleView);

            // Filter tags
            $(document).on('click', '.wpmatch-filter-tag', this.applyFilterTag);

            // Clear filters
            $(document).on('click', '#clear-filters', this.clearFilters);

            // Save preferences
            $(document).on('click', '#save-preferences', this.savePreferences);

            // Expand search
            $(document).on('click', '#expand-search', this.expandSearch);

            // Profile actions
            $(document).on('click', '.wpmatch-view-profile', this.viewProfile);
            $(document).on('click', '.wpmatch-send-message', this.sendMessage);

            // Form submission
            $(document).on('submit', '#wpmatch-search-form', this.handleSearch);
        },

        /**
         * Initialize distance slider
         */
        initDistanceSlider: function() {
            var $slider = $('#max_distance');
            var $value = $('.wpmatch-distance-value');

            if ($slider.length) {
                $slider.on('input', function() {
                    var distance = $(this).val();
                    $value.text(distance + ' ' + (distance == 1 ? 'mile' : 'miles'));
                });
            }
        },

        /**
         * Initialize location autocomplete
         */
        initLocationAutocomplete: function() {
            var $input = $('#location');
            var $suggestions = $('#location-suggestions');
            var searchTimeout;

            if ($input.length) {
                $input.on('input', function() {
                    var query = $(this).val().trim();

                    clearTimeout(searchTimeout);

                    if (query.length < 2) {
                        $suggestions.hide().empty();
                        return;
                    }

                    searchTimeout = setTimeout(function() {
                        WPMatchSearch.getLocationSuggestions(query, $suggestions);
                    }, 300);
                });

                // Hide suggestions when clicking outside
                $(document).on('click', function(e) {
                    if (!$(e.target).closest('.wpmatch-filter-group').length) {
                        $suggestions.hide();
                    }
                });

                // Handle suggestion clicks
                $(document).on('click', '.wpmatch-suggestion', function() {
                    var value = $(this).data('value');
                    $input.val(value);
                    $suggestions.hide();
                });
            }
        },

        /**
         * Get location suggestions
         */
        getLocationSuggestions: function(query, $container) {
            $.ajax({
                url: wpmatch_search.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpmatch_public_action',
                    action_type: 'get_search_suggestions',
                    query: query,
                    nonce: wpmatch_search.nonce
                },
                success: function(response) {
                    if (response.success && response.data.suggestions.length > 0) {
                        var html = '';
                        $.each(response.data.suggestions, function(index, suggestion) {
                            html += '<div class="wpmatch-suggestion" data-value="' + suggestion.value + '">';
                            html += '<span class="wpmatch-suggestion-label">' + suggestion.label + '</span>';
                            html += '</div>';
                        });
                        $container.html(html).show();
                    } else {
                        $container.hide();
                    }
                },
                error: function() {
                    $container.hide();
                }
            });
        },

        /**
         * Toggle between grid and list view
         */
        toggleView: function(e) {
            e.preventDefault();

            var $btn = $(this);
            var view = $btn.data('view');

            // Update button states
            $('.wpmatch-view-btn').removeClass('active');
            $btn.addClass('active');

            // Update view containers
            $('.wpmatch-results-grid, .wpmatch-results-list').removeClass('active');
            $('.wpmatch-results-' + view).addClass('active');

            // Save preference
            localStorage.setItem('wpmatch_search_view', view);
        },

        /**
         * Apply filter tag
         */
        applyFilterTag: function(e) {
            e.preventDefault();

            var $tag = $(this);
            var filter = $tag.data('filter');
            var value = $tag.data('value');

            // Update form field
            if (filter === 'location') {
                $('#location').val(value);
            }

            // Submit form
            $('#wpmatch-search-form').trigger('submit');
        },

        /**
         * Clear all filters
         */
        clearFilters: function(e) {
            e.preventDefault();

            var $form = $('#wpmatch-search-form');

            // Reset form fields
            $form.find('input[type="text"], input[type="number"]').val('');
            $form.find('select').prop('selectedIndex', 0);
            $form.find('#max_distance').val(50);
            $('.wpmatch-distance-value').text('50 miles');

            // Clear URL parameters and reload
            if (window.history && window.history.replaceState) {
                var url = window.location.pathname;
                window.history.replaceState({}, document.title, url);
                window.location.reload();
            }
        },

        /**
         * Save search preferences
         */
        savePreferences: function(e) {
            e.preventDefault();

            var preferences = {
                min_age: $('#wpmatch-search-form input[name="min_age"]').val(),
                max_age: $('#wpmatch-search-form input[name="max_age"]').val(),
                max_distance: $('#wpmatch-search-form input[name="max_distance"]').val(),
                gender: $('#wpmatch-search-form select[name="gender"]').val()
            };

            $.ajax({
                url: wpmatch_search.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpmatch_public_action',
                    action_type: 'save_search_preferences',
                    preferences: preferences,
                    nonce: wpmatch_search.nonce
                },
                success: function(response) {
                    if (response.success) {
                        WPMatchSearch.showNotice(wpmatch_search.strings.preferences_saved, 'success');
                    } else {
                        WPMatchSearch.showNotice(response.data.message || 'Failed to save preferences', 'error');
                    }
                },
                error: function() {
                    WPMatchSearch.showNotice('Failed to save preferences', 'error');
                }
            });
        },

        /**
         * Expand search area
         */
        expandSearch: function(e) {
            e.preventDefault();

            // Increase distance and clear location
            $('#max_distance').val(200);
            $('.wpmatch-distance-value').text('200 miles');
            $('#location').val('');

            // Submit form
            $('#wpmatch-search-form').trigger('submit');
        },

        /**
         * View profile
         */
        viewProfile: function(e) {
            e.preventDefault();

            var userId = $(this).data('user-id');
            // Redirect to profile page
            window.location.href = wpmatch_search.profile_url + '?user_id=' + userId;
        },

        /**
         * Send message
         */
        sendMessage: function(e) {
            e.preventDefault();

            var userId = $(this).data('user-id');
            // Redirect to messages page with user
            window.location.href = wpmatch_search.messages_url + '?recipient=' + userId;
        },

        /**
         * Handle search form submission
         */
        handleSearch: function(e) {
            var $form = $(this);
            var $submitBtn = $form.find('button[type="submit"]');

            // Show loading state
            $submitBtn.prop('disabled', true).html('<span class="wpmatch-spinner"></span> ' + wpmatch_search.strings.loading);

            // Allow form to submit normally
            return true;
        },

        /**
         * Show notification message
         */
        showNotice: function(message, type) {
            var $notice = $('<div class="wpmatch-notice ' + type + '"><p>' + message + '</p></div>');
            $('.wpmatch-search-header').after($notice);

            // Auto-remove after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        },

        /**
         * Load more results (for pagination)
         */
        loadMoreResults: function(page) {
            var $form = $('#wpmatch-search-form');
            var formData = $form.serialize() + '&page=' + page;

            $.ajax({
                url: window.location.href,
                type: 'GET',
                data: formData,
                success: function(response) {
                    // Parse response and append results
                    var $response = $(response);
                    var $newResults = $response.find('.wpmatch-profile-card, .wpmatch-profile-row');

                    if ($newResults.length > 0) {
                        $('.wpmatch-results-grid').append($newResults.filter('.wpmatch-profile-card'));
                        $('.wpmatch-results-list').append($newResults.filter('.wpmatch-profile-row'));
                    }
                },
                error: function() {
                    WPMatchSearch.showNotice('Failed to load more results', 'error');
                }
            });
        }
    };

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        if ($('.wpmatch-search-container').length) {
            WPMatchSearch.init();

            // Restore saved view preference
            var savedView = localStorage.getItem('wpmatch_search_view');
            if (savedView && $('.wpmatch-view-btn[data-view="' + savedView + '"]').length) {
                $('.wpmatch-view-btn[data-view="' + savedView + '"]').trigger('click');
            }
        }
    });

})(jQuery);