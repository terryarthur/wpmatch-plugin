/**
 * Admin JavaScript for WPMatch plugin
 *
 * @package WPMatch
 */

(function($) {
    'use strict';

    /**
     * Admin functionality
     */
    var WPMatchAdmin = {

        /**
         * Initialize admin functions
         */
        init: function() {
            this.bindEvents();
            this.loadStats();
            this.initTabs();
            this.initToggleSwitches();
            this.initRangeSliders();
            this.initResetSettings();
        },

        /**
         * Bind admin events
         */
        bindEvents: function() {
            // Settings form submission
            $(document).on('submit', '#wpmatch-settings-form', this.saveSettings);

            // User management actions
            $(document).on('click', '.wpmatch-ban-user', this.banUser);
            $(document).on('click', '.wpmatch-verify-user', this.verifyUser);

            // Tab switching
            $(document).on('click', '.wpmatch-tab', this.switchTab);

            // Quick Setup buttons
            $(document).on('click', '#wpmatch-generate-sample-data', this.generateSampleData);
            $(document).on('click', '#wpmatch-create-demo-pages', this.createDemoPages);
        },

        /**
         * Load admin statistics
         */
        loadStats: function() {
            if (!$('.wpmatch-stats-grid').length) {
                return;
            }

            $.ajax({
                url: wpmatch_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpmatch_admin_action',
                    action_type: 'get_stats',
                    nonce: wpmatch_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        WPMatchAdmin.updateStatsDisplay(response.data);
                    }
                },
                error: function() {
                    console.log('Failed to load statistics');
                }
            });
        },

        /**
         * Update statistics display
         */
        updateStatsDisplay: function(stats) {
            $('.wpmatch-stat-number[data-stat="total_users"]').text(stats.total_users || 0);
            $('.wpmatch-stat-number[data-stat="total_profiles"]').text(stats.total_profiles || 0);
            $('.wpmatch-stat-number[data-stat="active_users"]').text(stats.active_users || 0);
            $('.wpmatch-stat-number[data-stat="complete_profiles"]').text(stats.complete_profiles || 0);
        },

        /**
         * Save settings
         */
        saveSettings: function(e) {
            e.preventDefault();

            var $form = $(this);
            var formData = $form.serialize();

            $.ajax({
                url: wpmatch_admin.ajax_url,
                type: 'POST',
                data: formData + '&action=wpmatch_admin_action&action_type=save_settings&nonce=' + wpmatch_admin.nonce,
                beforeSend: function() {
                    $form.find('.submit button').prop('disabled', true);
                },
                success: function(response) {
                    if (response.success) {
                        WPMatchAdmin.showNotice('Settings saved successfully!', 'success');
                    } else {
                        WPMatchAdmin.showNotice('Failed to save settings: ' + response.data.message, 'error');
                    }
                },
                error: function() {
                    WPMatchAdmin.showNotice('An error occurred while saving settings.', 'error');
                },
                complete: function() {
                    $form.find('.submit button').prop('disabled', false);
                }
            });
        },

        /**
         * Ban user
         */
        banUser: function(e) {
            e.preventDefault();

            if (!confirm('Are you sure you want to ban this user?')) {
                return;
            }

            var userId = $(this).data('user-id');

            $.ajax({
                url: wpmatch_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpmatch_admin_action',
                    action_type: 'ban_user',
                    user_id: userId,
                    nonce: wpmatch_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        WPMatchAdmin.showNotice('User banned successfully!', 'success');
                        location.reload();
                    } else {
                        WPMatchAdmin.showNotice('Failed to ban user: ' + response.data.message, 'error');
                    }
                },
                error: function() {
                    WPMatchAdmin.showNotice('An error occurred while banning user.', 'error');
                }
            });
        },

        /**
         * Verify user
         */
        verifyUser: function(e) {
            e.preventDefault();

            var userId = $(this).data('user-id');

            $.ajax({
                url: wpmatch_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpmatch_admin_action',
                    action_type: 'verify_user',
                    user_id: userId,
                    nonce: wpmatch_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        WPMatchAdmin.showNotice('User verified successfully!', 'success');
                        location.reload();
                    } else {
                        WPMatchAdmin.showNotice('Failed to verify user: ' + response.data.message, 'error');
                    }
                },
                error: function() {
                    WPMatchAdmin.showNotice('An error occurred while verifying user.', 'error');
                }
            });
        },

        /**
         * Enhanced tab switching with animations
         */
        switchTab: function(e) {
            e.preventDefault();

            var $tab = $(this);
            var target = $tab.data('tab');

            // Don't switch if already active
            if ($tab.hasClass('active')) {
                return;
            }

            // Update tab appearance with smooth transitions
            $('.wpmatch-tab').removeClass('active').attr('aria-selected', 'false');
            $tab.addClass('active').attr('aria-selected', 'true');

            // Fade out current panel, then fade in new one
            $('.wpmatch-tab-panel.active').fadeOut(200, function() {
                $(this).removeClass('active');
                $('#tab-' + target).fadeIn(300).addClass('active');
            });

            // Save active tab to localStorage for persistence
            localStorage.setItem('wpmatch_active_tab', target);

            // Trigger custom event for other scripts
            $(document).trigger('wpmatch:tab:switched', [target]);
        },

        /**
         * Initialize tab functionality with accessibility
         */
        initTabs: function() {
            // Restore last active tab from localStorage
            var savedTab = localStorage.getItem('wpmatch_active_tab');
            if (savedTab && $('#tab-' + savedTab).length) {
                $('.wpmatch-tab[data-tab="' + savedTab + '"]').trigger('click');
            }

            // Keyboard navigation for tabs
            $('.wpmatch-tab').on('keydown', function(e) {
                var $current = $(this);
                var $tabs = $('.wpmatch-tab');
                var currentIndex = $tabs.index($current);

                switch(e.which) {
                    case 37: // Left arrow
                        e.preventDefault();
                        var prevIndex = currentIndex > 0 ? currentIndex - 1 : $tabs.length - 1;
                        $tabs.eq(prevIndex).focus().trigger('click');
                        break;
                    case 39: // Right arrow
                        e.preventDefault();
                        var nextIndex = currentIndex < $tabs.length - 1 ? currentIndex + 1 : 0;
                        $tabs.eq(nextIndex).focus().trigger('click');
                        break;
                    case 13: // Enter
                    case 32: // Space
                        e.preventDefault();
                        $current.trigger('click');
                        break;
                }
            });
        },

        /**
         * Initialize toggle switches
         */
        initToggleSwitches: function() {
            // Add click handlers for toggle switches
            $('.wpmatch-toggle input[type="checkbox"]').on('change', function() {
                var $toggle = $(this).closest('.wpmatch-toggle');

                if (this.checked) {
                    $toggle.addClass('checked');
                } else {
                    $toggle.removeClass('checked');
                }

                // Add a subtle animation
                $toggle.addClass('animate');
                setTimeout(function() {
                    $toggle.removeClass('animate');
                }, 300);
            });

            // Initialize existing checked states
            $('.wpmatch-toggle input[type="checkbox"]:checked').each(function() {
                $(this).closest('.wpmatch-toggle').addClass('checked');
            });
        },

        /**
         * Initialize range sliders with live updates
         */
        initRangeSliders: function() {
            $('.wpmatch-range').on('input', function() {
                var value = $(this).val();
                var $valueDisplay = $(this).siblings('.wpmatch-range-value');
                $valueDisplay.text(value + '%');

                // Add visual feedback
                var percent = (value - this.min) / (this.max - this.min) * 100;
                $(this).css('background',
                    'linear-gradient(to right, #667eea 0%, #667eea ' + percent + '%, #e2e8f0 ' + percent + '%, #e2e8f0 100%)'
                );
            });

            // Initialize slider backgrounds
            $('.wpmatch-range').each(function() {
                var value = $(this).val();
                var percent = (value - this.min) / (this.max - this.min) * 100;
                $(this).css('background',
                    'linear-gradient(to right, #667eea 0%, #667eea ' + percent + '%, #e2e8f0 ' + percent + '%, #e2e8f0 100%)'
                );
            });
        },

        /**
         * Initialize settings reset functionality
         */
        initResetSettings: function() {
            $('#wpmatch-reset-settings').on('click', function(e) {
                e.preventDefault();

                if (!confirm('Are you sure you want to reset all settings to their default values? This action cannot be undone.')) {
                    return;
                }

                $.ajax({
                    url: wpmatch_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'wpmatch_admin_action',
                        action_type: 'reset_settings',
                        nonce: wpmatch_admin.nonce
                    },
                    beforeSend: function() {
                        $('#wpmatch-reset-settings').prop('disabled', true).text('Resetting...');
                    },
                    success: function(response) {
                        if (response.success) {
                            WPMatchAdmin.showNotice('Settings reset to defaults successfully!', 'success');
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        } else {
                            WPMatchAdmin.showNotice('Failed to reset settings: ' + response.data.message, 'error');
                        }
                    },
                    error: function() {
                        WPMatchAdmin.showNotice('An error occurred while resetting settings.', 'error');
                    },
                    complete: function() {
                        $('#wpmatch-reset-settings').prop('disabled', false).text('Reset to Defaults');
                    }
                });
            });
        },

        /**
         * Generate sample data
         */
        generateSampleData: function(e) {
            e.preventDefault();

            var $button = $(this);

            if (!confirm('This will create 5 demo user profiles for testing. Continue?')) {
                return;
            }

            $.ajax({
                url: wpmatch_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpmatch_generate_sample_data',
                    nonce: wpmatch_admin.nonce
                },
                beforeSend: function() {
                    $button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Creating Users...');
                },
                success: function(response) {
                    if (response.success) {
                        WPMatchAdmin.showNotice('Sample users created successfully! ' + response.data.message, 'success');
                        // Reload stats to show new users
                        WPMatchAdmin.loadStats();
                    } else {
                        WPMatchAdmin.showNotice('Failed to create sample users: ' + response.data.message, 'error');
                    }
                },
                error: function() {
                    WPMatchAdmin.showNotice('An error occurred while creating sample users.', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).html('<span class="dashicons dashicons-admin-users"></span> Generate Sample Users');
                }
            });
        },

        /**
         * Create demo pages
         */
        createDemoPages: function(e) {
            e.preventDefault();

            var $button = $(this);

            if (!confirm('This will create all essential dating pages with working shortcodes. Continue?')) {
                return;
            }

            $.ajax({
                url: wpmatch_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpmatch_create_demo_pages',
                    nonce: wpmatch_admin.nonce
                },
                beforeSend: function() {
                    $button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Creating Pages...');
                },
                success: function(response) {
                    if (response.success) {
                        WPMatchAdmin.showNotice('Demo pages created successfully! ' + response.data.message, 'success');

                        // Show page links if provided
                        if (response.data.pages && response.data.pages.length > 0) {
                            var pageLinks = '<div class="wpmatch-page-links" style="margin-top: 10px;"><strong>Created Pages:</strong><ul>';
                            response.data.pages.forEach(function(page) {
                                pageLinks += '<li><a href="' + page.url + '" target="_blank">' + page.title + '</a></li>';
                            });
                            pageLinks += '</ul></div>';

                            $('.wpmatch-quick-setup').append(pageLinks);
                        }
                    } else {
                        WPMatchAdmin.showNotice('Failed to create demo pages: ' + response.data.message, 'error');
                    }
                },
                error: function() {
                    WPMatchAdmin.showNotice('An error occurred while creating demo pages.', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).html('<span class="dashicons dashicons-admin-page"></span> Create Dating Pages');
                }
            });
        },

        /**
         * Show admin notice
         */
        showNotice: function(message, type) {
            var noticeClass = 'notice-' + (type === 'error' ? 'error' : 'success');
            var notice = '<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>';

            $('.wrap h1').after(notice);

            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $('.notice').fadeOut();
            }, 5000);
        }
    };

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        WPMatchAdmin.init();
    });

})(jQuery);