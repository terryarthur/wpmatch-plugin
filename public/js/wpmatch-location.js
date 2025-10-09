/**
 * WPMatch Location Features JavaScript
 *
 * @package WPMatch
 * @since 1.6.0
 */

(function($) {
    'use strict';

    /**
     * Location object
     */
    const WPMatchLocation = {

        /**
         * Current user location
         */
        currentLocation: null,

        /**
         * Geolocation watch ID
         */
        watchId: null,

        /**
         * Map instance
         */
        map: null,

        /**
         * User settings
         */
        settings: {
            share_location: false,
            privacy_level: 'approximate',
            search_radius: 25,
            auto_update: false
        },

        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.loadSettings();
            this.checkLocationPermission();
            this.initNearMeFeature();
            this.loadNearbyUsers();
            this.loadLocationEvents();
            this.loadSearchHistory();
            this.initializePrivacyControls();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            // Location controls
            $(document).on('click', '.enable-location-btn', this.enableLocation);
            $(document).on('click', '.disable-location-btn', this.disableLocation);
            $(document).on('click', '.update-location-btn', this.updateLocation);
            $(document).on('click', '.refresh-nearby-btn', this.refreshNearbyUsers);

            // Settings
            $(document).on('click', '.toggle-switch', this.toggleSetting);
            $(document).on('change', '.range-slider', this.updateRangeSetting);
            $(document).on('change', '.privacy-select', this.updatePrivacyLevel);

            // User actions
            $(document).on('click', '.view-profile-btn', this.viewUserProfile);
            $(document).on('click', '.send-message-btn', this.sendMessage);
            $(document).on('click', '.add-favorite-btn', this.addToFavorites);

            // Map controls
            $(document).on('click', '.map-btn', this.switchMapView);

            // Event filters
            $(document).on('click', '.event-filter-btn', this.filterLocationEvents);

            // Search history
            $(document).on('click', '.repeat-search-btn', this.repeatSearch);
            $(document).on('click', '.clear-history-btn', this.clearSearchHistory);
            $(document).on('click', '.delete-search-btn', this.deleteSearchItem);

            // Distance filter
            $(document).on('change', '#distance-range', this.updateDistanceFilter);
        },

        /**
         * Check location permission
         */
        checkLocationPermission: function() {
            if (!navigator.geolocation) {
                this.showLocationError('Geolocation is not supported by this browser');
                return;
            }

            navigator.permissions.query({name: 'geolocation'}).then(permission => {
                this.updateLocationStatus(permission.state);

                permission.addEventListener('change', () => {
                    this.updateLocationStatus(permission.state);
                });
            }).catch(() => {
                // Permissions API not supported
                this.updateLocationStatus('prompt');
            });
        },

        /**
         * Update location status
         */
        updateLocationStatus: function(state) {
            const $status = $('.location-status');
            let icon, text, details;

            switch (state) {
                case 'granted':
                    icon = '📍';
                    text = 'Location Access Enabled';
                    details = 'Your location is being used to find nearby matches';
                    break;

                case 'denied':
                    icon = '🚫';
                    text = 'Location Access Denied';
                    details = 'Enable location access to see nearby users and events';
                    break;

                case 'prompt':
                default:
                    icon = '📍';
                    text = 'Location Access Required';
                    details = 'Allow location access to find matches and events near you';
                    break;
            }

            $status.find('.status-icon').text(icon);
            $status.find('.status-text').text(text);
            $status.find('.status-details').text(details);

            this.updateLocationControls(state);
        },

        /**
         * Update location controls
         */
        updateLocationControls: function(state) {
            const $controls = $('.location-controls');
            $controls.empty();

            if (state === 'granted') {
                $controls.append(`
                    <button class="location-btn primary update-location-btn">
                        <span>🔄</span> Update Location
                    </button>
                    <button class="location-btn disable-location-btn">
                        <span>⏸️</span> Disable Sharing
                    </button>
                `);
            } else {
                $controls.append(`
                    <button class="location-btn primary enable-location-btn">
                        <span>📍</span> Enable Location
                    </button>
                `);
            }
        },

        /**
         * Enable location
         */
        enableLocation: function(e) {
            e.preventDefault();

            const $btn = $(this);
            const originalText = $btn.html();
            $btn.html('<span>⏳</span> Requesting...').prop('disabled', true);

            WPMatchLocation.getCurrentLocation()
                .then(location => {
                    WPMatchLocation.currentLocation = location;
                    WPMatchLocation.updateLocationOnServer(location);
                    WPMatchLocation.startLocationTracking();
                    WPMatchLocation.showNotification('Location enabled successfully!', 'success');
                })
                .catch(error => {
                    WPMatchLocation.showLocationError(error.message);
                })
                .finally(() => {
                    $btn.html(originalText).prop('disabled', false);
                });
        },

        /**
         * Disable location
         */
        disableLocation: function(e) {
            e.preventDefault();

            if (!confirm(wpMatchLocation.strings.disable_confirm)) return;

            WPMatchLocation.stopLocationTracking();
            WPMatchLocation.clearLocationOnServer();
            WPMatchLocation.showNotification('Location sharing disabled', 'info');
        },

        /**
         * Update location
         */
        updateLocation: function(e) {
            e.preventDefault();

            const $btn = $(this);
            const originalText = $btn.html();
            $btn.html('<span>📡</span> Updating...').prop('disabled', true);

            WPMatchLocation.getCurrentLocation()
                .then(location => {
                    WPMatchLocation.currentLocation = location;
                    WPMatchLocation.updateLocationOnServer(location);
                    WPMatchLocation.refreshNearbyUsers();
                    WPMatchLocation.showNotification('Location updated!', 'success');
                })
                .catch(error => {
                    WPMatchLocation.showLocationError(error.message);
                })
                .finally(() => {
                    $btn.html(originalText).prop('disabled', false);
                });
        },

        /**
         * Get current location
         */
        getCurrentLocation: function() {
            return new Promise((resolve, reject) => {
                const options = {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 300000 // 5 minutes
                };

                navigator.geolocation.getCurrentPosition(
                    position => {
                        resolve({
                            latitude: position.coords.latitude,
                            longitude: position.coords.longitude,
                            accuracy: position.coords.accuracy
                        });
                    },
                    error => {
                        let message;
                        switch (error.code) {
                            case error.PERMISSION_DENIED:
                                message = 'Location access denied by user';
                                break;
                            case error.POSITION_UNAVAILABLE:
                                message = 'Location information is unavailable';
                                break;
                            case error.TIMEOUT:
                                message = 'Location request timed out';
                                break;
                            default:
                                message = 'An unknown error occurred';
                                break;
                        }
                        reject(new Error(message));
                    },
                    options
                );
            });
        },

        /**
         * Start location tracking
         */
        startLocationTracking: function() {
            if (!this.settings.auto_update) return;

            const options = {
                enableHighAccuracy: false,
                timeout: 60000,
                maximumAge: 600000 // 10 minutes
            };

            this.watchId = navigator.geolocation.watchPosition(
                position => {
                    const newLocation = {
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude,
                        accuracy: position.coords.accuracy
                    };

                    // Only update if location has changed significantly
                    if (this.hasLocationChanged(newLocation)) {
                        this.currentLocation = newLocation;
                        this.updateLocationOnServer(newLocation);
                    }
                },
                error => {
                    console.warn('Location tracking error:', error);
                },
                options
            );
        },

        /**
         * Stop location tracking
         */
        stopLocationTracking: function() {
            if (this.watchId) {
                navigator.geolocation.clearWatch(this.watchId);
                this.watchId = null;
            }
        },

        /**
         * Check if location has changed significantly
         */
        hasLocationChanged: function(newLocation) {
            if (!this.currentLocation) return true;

            const distance = this.calculateDistance(
                this.currentLocation.latitude,
                this.currentLocation.longitude,
                newLocation.latitude,
                newLocation.longitude
            );

            // Update if moved more than 100 meters
            return distance > 0.1;
        },

        /**
         * Calculate distance between two points
         */
        calculateDistance: function(lat1, lon1, lat2, lon2) {
            const R = 6371; // Earth's radius in kilometers
            const dLat = this.toRadians(lat2 - lat1);
            const dLon = this.toRadians(lon2 - lon1);

            const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                      Math.cos(this.toRadians(lat1)) * Math.cos(this.toRadians(lat2)) *
                      Math.sin(dLon / 2) * Math.sin(dLon / 2);

            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
            return R * c;
        },

        /**
         * Convert degrees to radians
         */
        toRadians: function(degrees) {
            return degrees * (Math.PI / 180);
        },

        /**
         * Update location on server
         */
        updateLocationOnServer: function(location) {
            $.ajax({
                url: wpMatchLocation.apiUrl + '/location/update',
                type: 'POST',
                data: {
                    latitude: location.latitude,
                    longitude: location.longitude,
                    accuracy: location.accuracy,
                    privacy_level: this.settings.privacy_level
                },
                headers: {
                    'X-WP-Nonce': wpMatchLocation.nonce
                },
                success: function(response) {
                    if (response.success) {
                        console.log('Location updated on server');
                    }
                },
                error: function() {
                    console.error('Failed to update location on server');
                }
            });
        },

        /**
         * Clear location on server
         */
        clearLocationOnServer: function() {
            $.ajax({
                url: wpMatchLocation.apiUrl + '/location/clear',
                type: 'POST',
                headers: {
                    'X-WP-Nonce': wpMatchLocation.nonce
                },
                success: function(response) {
                    if (response.success) {
                        console.log('Location cleared on server');
                    }
                }
            });
        },

        /**
         * Load settings
         */
        loadSettings: function() {
            $.ajax({
                url: wpMatchLocation.apiUrl + '/location/settings',
                type: 'GET',
                headers: {
                    'X-WP-Nonce': wpMatchLocation.nonce
                },
                success: function(response) {
                    if (response.success) {
                        WPMatchLocation.settings = response.data;
                        WPMatchLocation.updateSettingsUI();
                    }
                }
            });
        },

        /**
         * Update settings UI
         */
        updateSettingsUI: function() {
            // Update toggle switches
            $('.toggle-switch').each(function() {
                const setting = $(this).data('setting');
                if (WPMatchLocation.settings[setting]) {
                    $(this).addClass('active');
                }
            });

            // Update range sliders
            $('.range-slider').each(function() {
                const setting = $(this).data('setting');
                if (WPMatchLocation.settings[setting]) {
                    $(this).val(WPMatchLocation.settings[setting]);
                    $(this).siblings('.range-value').text(WPMatchLocation.settings[setting] + ' km');
                }
            });

            // Update privacy level
            $('.privacy-select').val(this.settings.privacy_level);
            this.updatePrivacyIndicator();
        },

        /**
         * Toggle setting
         */
        toggleSetting: function(e) {
            e.preventDefault();

            const $toggle = $(this);
            const setting = $toggle.data('setting');
            const isActive = $toggle.hasClass('active');

            $toggle.toggleClass('active');
            WPMatchLocation.settings[setting] = !isActive;

            WPMatchLocation.saveSetting(setting, !isActive);

            // Handle special cases
            if (setting === 'auto_update') {
                if (!isActive) {
                    WPMatchLocation.startLocationTracking();
                } else {
                    WPMatchLocation.stopLocationTracking();
                }
            }
        },

        /**
         * Update range setting
         */
        updateRangeSetting: function(e) {
            const $slider = $(this);
            const setting = $slider.data('setting');
            const value = parseInt($slider.val());

            $slider.siblings('.range-value').text(value + ' km');
            WPMatchLocation.settings[setting] = value;

            WPMatchLocation.saveSetting(setting, value);

            // Refresh nearby users if search radius changed
            if (setting === 'search_radius') {
                WPMatchLocation.refreshNearbyUsers();
            }
        },

        /**
         * Update privacy level
         */
        updatePrivacyLevel: function(e) {
            const level = $(this).val();
            WPMatchLocation.settings.privacy_level = level;
            WPMatchLocation.saveSetting('privacy_level', level);
            WPMatchLocation.updatePrivacyIndicator();

            // Update location on server with new privacy level
            if (WPMatchLocation.currentLocation) {
                WPMatchLocation.updateLocationOnServer(WPMatchLocation.currentLocation);
            }
        },

        /**
         * Update privacy indicator
         */
        updatePrivacyIndicator: function() {
            const level = this.settings.privacy_level;
            const $indicator = $('.privacy-level');

            $indicator.removeClass('exact approximate city hidden').addClass(level);

            const labels = {
                exact: 'Exact Location',
                approximate: 'Approximate',
                city: 'City Only',
                hidden: 'Hidden'
            };

            $indicator.text(labels[level] || level);
        },

        /**
         * Save setting
         */
        saveSetting: function(key, value) {
            $.ajax({
                url: wpMatchLocation.apiUrl + '/location/settings',
                type: 'POST',
                data: {
                    setting: key,
                    value: value
                },
                headers: {
                    'X-WP-Nonce': wpMatchLocation.nonce
                },
                success: function(response) {
                    if (response.success) {
                        console.log('Setting saved:', key, value);
                    }
                }
            });
        },

        /**
         * Load nearby users
         */
        loadNearbyUsers: function() {
            const $container = $('.nearby-users-grid');
            this.showLoadingState($container);

            $.ajax({
                url: wpMatchLocation.apiUrl + '/location/nearby',
                type: 'GET',
                data: {
                    radius: this.settings.search_radius,
                    limit: 20
                },
                headers: {
                    'X-WP-Nonce': wpMatchLocation.nonce
                },
                success: function(response) {
                    if (response.success) {
                        WPMatchLocation.renderNearbyUsers(response.data);
                    } else {
                        WPMatchLocation.showError($container, response.message);
                    }
                },
                error: function() {
                    WPMatchLocation.showError($container, wpMatchLocation.strings.load_error);
                }
            });
        },

        /**
         * Render nearby users
         */
        renderNearbyUsers: function(users) {
            const $grid = $('.nearby-users-grid');
            $grid.empty();

            if (users.length === 0) {
                $grid.html(`
                    <div class="no-nearby-users">
                        <p>${wpMatchLocation.strings.no_nearby_users}</p>
                    </div>
                `);
                return;
            }

            users.forEach(user => {
                const $card = this.createUserCard(user);
                $grid.append($card);
            });
        },

        /**
         * Create user card
         */
        createUserCard: function(user) {
            const distance = user.distance < 1 ?
                Math.round(user.distance * 1000) + 'm' :
                Math.round(user.distance * 10) / 10 + 'km';

            return $(`
                <div class="user-card" data-user-id="${user.user_id}">
                    <div class="user-card-header">
                        <img src="${user.avatar || wpMatchLocation.default_avatar}" alt="${user.display_name}" class="user-avatar">
                        <div class="distance-badge">${distance}</div>
                    </div>
                    <div class="user-card-content">
                        <div class="user-info">
                            <h4>${user.display_name}</h4>
                            <p>${user.age ? user.age + ' years old' : ''}</p>
                        </div>
                        <div class="user-details">
                            <div class="user-detail">
                                <span>📍</span> ${user.location_name || 'Nearby'}
                            </div>
                            ${user.last_active ? `
                                <div class="user-detail">
                                    <span>🕒</span> ${this.formatLastActive(user.last_active)}
                                </div>
                            ` : ''}
                        </div>
                        <div class="user-actions">
                            <a href="#" class="user-btn primary view-profile-btn" data-user-id="${user.user_id}">
                                <span>👤</span> View Profile
                            </a>
                            <a href="#" class="user-btn secondary send-message-btn" data-user-id="${user.user_id}">
                                <span>💬</span> Message
                            </a>
                        </div>
                    </div>
                </div>
            `);
        },

        /**
         * Format last active time
         */
        formatLastActive: function(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const diff = now - date;
            const hours = diff / (1000 * 60 * 60);

            if (hours < 1) {
                const minutes = Math.floor(diff / (1000 * 60));
                return `${minutes}m ago`;
            } else if (hours < 24) {
                return `${Math.floor(hours)}h ago`;
            } else {
                const days = Math.floor(hours / 24);
                return `${days}d ago`;
            }
        },

        /**
         * Refresh nearby users
         */
        refreshNearbyUsers: function(e) {
            if (e) e.preventDefault();

            const $btn = $('.refresh-nearby-btn');
            const originalText = $btn.html();
            $btn.html('🔄 Refreshing...').prop('disabled', true);

            WPMatchLocation.loadNearbyUsers();

            setTimeout(() => {
                $btn.html(originalText).prop('disabled', false);
            }, 2000);
        },

        /**
         * Update distance filter
         */
        updateDistanceFilter: function(e) {
            const distance = $(this).val();
            $('.distance-value').text(distance + ' km');
            WPMatchLocation.settings.search_radius = parseInt(distance);
            WPMatchLocation.saveSetting('search_radius', parseInt(distance));
            WPMatchLocation.refreshNearbyUsers();
        },

        /**
         * View user profile
         */
        viewUserProfile: function(e) {
            e.preventDefault();

            const userId = $(this).data('user-id');

            // Record search activity
            WPMatchLocation.recordSearchActivity('profile_view', userId);

            // Navigate to profile (implementation depends on your routing)
            window.location.href = wpMatchLocation.profile_url + '?user_id=' + userId;
        },

        /**
         * Send message
         */
        sendMessage: function(e) {
            e.preventDefault();

            const userId = $(this).data('user-id');

            // Record search activity
            WPMatchLocation.recordSearchActivity('message_sent', userId);

            // Navigate to messages (implementation depends on your routing)
            window.location.href = wpMatchLocation.messages_url + '?user_id=' + userId;
        },

        /**
         * Add to favorites
         */
        addToFavorites: function(e) {
            e.preventDefault();

            const userId = $(this).data('user-id');
            const $btn = $(this);

            $.ajax({
                url: wpMatchLocation.apiUrl + '/location/favorite',
                type: 'POST',
                data: {
                    user_id: userId
                },
                headers: {
                    'X-WP-Nonce': wpMatchLocation.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $btn.addClass('favorited').html('<span>❤️</span> Favorited');
                        WPMatchLocation.showNotification('Added to favorites!', 'success');
                    } else {
                        WPMatchLocation.showNotification(response.message, 'error');
                    }
                },
                error: function() {
                    WPMatchLocation.showNotification(wpMatchLocation.strings.error, 'error');
                }
            });
        },

        /**
         * Record search activity
         */
        recordSearchActivity: function(activity, targetUserId) {
            $.ajax({
                url: wpMatchLocation.apiUrl + '/location/activity',
                type: 'POST',
                data: {
                    activity: activity,
                    target_user_id: targetUserId
                },
                headers: {
                    'X-WP-Nonce': wpMatchLocation.nonce
                }
            });
        },

        /**
         * Load location events
         */
        loadLocationEvents: function(filter = 'nearby') {
            const $container = $('.location-events-grid');
            this.showLoadingState($container);

            $.ajax({
                url: wpMatchLocation.apiUrl + '/location/events',
                type: 'GET',
                data: {
                    filter: filter,
                    radius: this.settings.search_radius
                },
                headers: {
                    'X-WP-Nonce': wpMatchLocation.nonce
                },
                success: function(response) {
                    if (response.success) {
                        WPMatchLocation.renderLocationEvents(response.data);
                    } else {
                        WPMatchLocation.showError($container, response.message);
                    }
                },
                error: function() {
                    WPMatchLocation.showError($container, wpMatchLocation.strings.load_error);
                }
            });
        },

        /**
         * Render location events
         */
        renderLocationEvents: function(events) {
            const $grid = $('.location-events-grid');
            $grid.empty();

            if (events.length === 0) {
                $grid.html(`
                    <div class="no-location-events">
                        <p>${wpMatchLocation.strings.no_events}</p>
                    </div>
                `);
                return;
            }

            events.forEach(event => {
                const $card = this.createLocationEventCard(event);
                $grid.append($card);
            });
        },

        /**
         * Create location event card
         */
        createLocationEventCard: function(event) {
            const distance = event.distance < 1 ?
                Math.round(event.distance * 1000) + 'm' :
                Math.round(event.distance * 10) / 10 + 'km';

            const eventDate = new Date(event.start_time);

            return $(`
                <div class="location-event-card" data-event-id="${event.event_id}">
                    <div class="event-card-header">
                        <h4 class="event-title">${event.title}</h4>
                        <div class="event-location">
                            <span>📍</span> ${event.location_name} (${distance})
                        </div>
                    </div>
                    <div class="event-card-content">
                        <div class="event-details">
                            <div class="event-detail">
                                <span>📅</span> ${eventDate.toLocaleDateString()}
                            </div>
                            <div class="event-detail">
                                <span>🕒</span> ${eventDate.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}
                            </div>
                            <div class="event-detail">
                                <span>👥</span> ${event.current_participants}/${event.max_participants} participants
                            </div>
                        </div>
                        <div class="event-actions">
                            <a href="#" class="user-btn primary view-event-btn" data-event-id="${event.event_id}">
                                View Details
                            </a>
                            <a href="#" class="user-btn secondary register-event-btn" data-event-id="${event.event_id}">
                                Register
                            </a>
                        </div>
                    </div>
                </div>
            `);
        },

        /**
         * Filter location events
         */
        filterLocationEvents: function(e) {
            e.preventDefault();

            const $btn = $(this);
            const filter = $btn.data('filter');

            $('.event-filter-btn').removeClass('active');
            $btn.addClass('active');

            WPMatchLocation.loadLocationEvents(filter);
        },

        /**
         * Load search history
         */
        loadSearchHistory: function() {
            $.ajax({
                url: wpMatchLocation.apiUrl + '/location/search-history',
                type: 'GET',
                headers: {
                    'X-WP-Nonce': wpMatchLocation.nonce
                },
                success: function(response) {
                    if (response.success) {
                        WPMatchLocation.renderSearchHistory(response.data);
                    }
                }
            });
        },

        /**
         * Render search history
         */
        renderSearchHistory: function(searches) {
            const $list = $('.search-history-list');
            $list.empty();

            if (searches.length === 0) {
                $list.html(`
                    <div class="no-search-history">
                        <p>${wpMatchLocation.strings.no_history}</p>
                    </div>
                `);
                return;
            }

            searches.forEach(search => {
                const $item = this.createSearchHistoryItem(search);
                $list.append($item);
            });
        },

        /**
         * Create search history item
         */
        createSearchHistoryItem: function(search) {
            const searchDate = new Date(search.created_at);

            return $(`
                <div class="search-history-item" data-search-id="${search.search_id}">
                    <div class="search-info">
                        <h5>${search.search_type.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}</h5>
                        <p>${search.location_name} • ${search.radius}km radius • ${searchDate.toLocaleDateString()}</p>
                    </div>
                    <div class="search-actions">
                        <button class="search-action-btn repeat-search-btn" data-search-id="${search.search_id}">
                            Repeat
                        </button>
                        <button class="search-action-btn delete-search-btn" data-search-id="${search.search_id}">
                            Delete
                        </button>
                    </div>
                </div>
            `);
        },

        /**
         * Repeat search
         */
        repeatSearch: function(e) {
            e.preventDefault();

            const searchId = $(this).data('search-id');

            $.ajax({
                url: wpMatchLocation.apiUrl + '/location/repeat-search',
                type: 'POST',
                data: {
                    search_id: searchId
                },
                headers: {
                    'X-WP-Nonce': wpMatchLocation.nonce
                },
                success: function(response) {
                    if (response.success) {
                        WPMatchLocation.showNotification('Search repeated!', 'success');
                        WPMatchLocation.loadNearbyUsers();
                    } else {
                        WPMatchLocation.showNotification(response.message, 'error');
                    }
                }
            });
        },

        /**
         * Clear search history
         */
        clearSearchHistory: function(e) {
            e.preventDefault();

            if (!confirm(wpMatchLocation.strings.clear_history_confirm)) return;

            $.ajax({
                url: wpMatchLocation.apiUrl + '/location/clear-history',
                type: 'POST',
                headers: {
                    'X-WP-Nonce': wpMatchLocation.nonce
                },
                success: function(response) {
                    if (response.success) {
                        WPMatchLocation.showNotification('Search history cleared', 'success');
                        WPMatchLocation.loadSearchHistory();
                    }
                }
            });
        },

        /**
         * Delete search item
         */
        deleteSearchItem: function(e) {
            e.preventDefault();

            const searchId = $(this).data('search-id');
            const $item = $(this).closest('.search-history-item');

            $.ajax({
                url: wpMatchLocation.apiUrl + '/location/delete-search',
                type: 'POST',
                data: {
                    search_id: searchId
                },
                headers: {
                    'X-WP-Nonce': wpMatchLocation.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $item.fadeOut(() => $item.remove());
                    }
                }
            });
        },

        /**
         * Switch map view
         */
        switchMapView: function(e) {
            e.preventDefault();

            const $btn = $(this);
            const view = $btn.data('view');

            $('.map-btn').removeClass('active');
            $btn.addClass('active');

            // Implementation would depend on map library used
            WPMatchLocation.showNotification(`Switched to ${view} view`, 'info');
        },

        /**
         * Show loading state
         */
        showLoadingState: function($container) {
            $container.html(`
                <div class="location-loading">
                    <div class="loading-spinner"></div>
                    <span>${wpMatchLocation.strings.loading}</span>
                </div>
            `);
        },

        /**
         * Show error
         */
        showError: function($container, message) {
            $container.html(`
                <div class="location-error">
                    <span class="error-icon">⚠️</span>
                    <p>${message}</p>
                </div>
            `);
        },

        /**
         * Show location error
         */
        showLocationError: function(message) {
            $('.location-error').remove();

            const $error = $(`
                <div class="location-error">
                    <span class="error-icon">⚠️</span>
                    <p>${message}</p>
                </div>
            `);

            $('.location-status').after($error);

            setTimeout(() => {
                $error.fadeOut(() => $error.remove());
            }, 5000);
        },

        /**
         * Show notification
         */
        showNotification: function(message, type = 'info') {
            const $notification = $(`
                <div class="wpmatch-notification ${type}">
                    <span>${message}</span>
                    <button class="close-notification">&times;</button>
                </div>
            `);

            $('body').append($notification);

            // Auto hide after 4 seconds
            setTimeout(() => {
                $notification.fadeOut(() => $notification.remove());
            }, 4000);

            // Manual close
            $notification.find('.close-notification').on('click', function() {
                $notification.fadeOut(() => $notification.remove());
            });
        }
    };

        /**
         * Initialize Near Me feature
         */
        initNearMeFeature: function() {
            if (!window.WPMatchNearMe) return;

            const self = this;

            // Initialize Near Me specific functionality
            this.bindNearMeEvents();
            this.initLocationControls();
            this.checkInitialLocationState();
        },

        /**
         * Bind Near Me specific events
         */
        bindNearMeEvents: function() {
            const self = this;

            // Distance slider
            $('#distance-range').on('input', function() {
                const distance = $(this).val();
                $('#distance-display').text(distance + ' km');
                self.updateSearchParams();
            });

            // Quick filter chips
            $('.filter-chip').on('click', function() {
                const radius = $(this).data('radius');
                $('#distance-range').val(radius).trigger('input');
                $('.filter-chip').removeClass('active');
                $(this).addClass('active');
                self.searchNearbyUsers();
            });

            // Search button
            $('#search-nearby').on('click', function() {
                self.searchNearbyUsers();
            });

            // View toggle
            $('.view-btn').on('click', function() {
                const view = $(this).data('view');
                self.switchView(view);
                $('.view-btn').removeClass('active');
                $(this).addClass('active');
            });

            // Location enable/update buttons
            $('.enable-location, .get-location, .update-location').on('click', function() {
                self.requestLocationPermission();
            });

            // Privacy settings toggle
            $('.toggle-privacy').on('click', function() {
                const $panel = $('.privacy-settings-panel');
                const $icon = $(this).find('i');

                $panel.slideToggle();
                $icon.toggleClass('fa-chevron-down fa-chevron-up');
                $('.show-text, .hide-text').toggle();
            });

            // Save privacy settings
            $('.save-privacy-settings').on('click', function() {
                self.savePrivacySettings();
            });

            // Expand search area
            $('.expand-search').on('click', function() {
                const currentRadius = parseInt($('#distance-range').val());
                const newRadius = Math.min(currentRadius * 2, window.WPMatchNearMe.defaults.maxRadius);
                $('#distance-range').val(newRadius).trigger('input');
                self.searchNearbyUsers();
            });

            // Age inputs
            $('#min-age, #max-age').on('change', function() {
                self.updateSearchParams();
            });

            // Gender filter
            $('#gender-filter').on('change', function() {
                self.updateSearchParams();
            });

            // Modal close
            $('#modal-close, #modal-overlay').on('click', function() {
                $('#user-profile-modal').fadeOut();
            });

            // Distance filter chips
            $(document).on('click', '.distance-chip', function() {
                const radius = $(this).data('radius');
                if (radius) {
                    $('#distance-range').val(radius).trigger('input');
                    $('.distance-chip').removeClass('active');
                    $(this).addClass('active');
                    self.searchNearbyUsers();
                }
            });

            // User action buttons
            $(document).on('click', '.like-user', function() {
                self.handleUserAction($(this).data('user-id'), 'like');
            });

            $(document).on('click', '.super-like-user', function() {
                self.handleUserAction($(this).data('user-id'), 'super_like');
            });

            $(document).on('click', '.message-user', function() {
                self.handleUserAction($(this).data('user-id'), 'message');
            });

            $(document).on('click', '.view-profile', function() {
                self.handleUserAction($(this).data('user-id'), 'view_profile');
            });

            // Advanced filters toggle
            $('.toggle-advanced-filters').on('click', function() {
                const $panel = $('.advanced-filters-panel');
                const $icon = $(this).find('i');

                $panel.slideToggle();
                $icon.toggleClass('fa-chevron-down fa-chevron-up');
                $(this).toggleClass('expanded');
                $('.show-text, .hide-text').toggle();
            });

            // Advanced filter controls
            $('.apply-filters').on('click', function() {
                self.applyAdvancedFilters();
            });

            $('.reset-filters').on('click', function() {
                self.resetAllFilters();
            });

            $('.save-filter-preset').on('click', function() {
                self.saveFilterPreset();
            });

            // Individual filter changes
            $('#online-status-filter, #location-precision-filter, #travel-status-filter, #verification-filter').on('change', function() {
                self.updateAdvancedFilters();
            });

            // Interests filter
            $('#interests-filter').on('input', function() {
                self.searchInterests($(this).val());
            });

            // Location type checkboxes
            $('.checkbox-group input[type="checkbox"]').on('change', function() {
                self.updateLocationTypeFilters();
            });

            // Saved locations filter
            $('#saved-locations-filter').on('change', function() {
                if ($(this).val() === 'custom') {
                    self.showCustomLocationModal();
                } else {
                    self.updateSavedLocationFilter($(this).val());
                }
            });

            // Travel mode controls
            $('#toggle-travel-mode').on('click', function() {
                self.toggleTravelMode();
            });

            $('.exit-travel-mode').on('click', function() {
                self.exitTravelMode();
            });

            $('.search-destination').on('click', function() {
                self.searchDestination($('#travel-destination').val());
            });

            $('#travel-destination').on('input', function() {
                if ($(this).val().length > 2) {
                    self.searchDestination($(this).val());
                }
            });

            $('#travel-radius').on('input', function() {
                $('#travel-radius-display').text($(this).val() + ' km');
            });

            $('.start-travel-search').on('click', function() {
                self.startTravelSearch();
            });

            $('.save-travel-plan').on('click', function() {
                self.saveTravelPlan();
            });

            // Privacy controls
            $('.toggle-privacy').on('click', function() {
                const $panel = $('.privacy-settings-panel');
                const $button = $(this);

                $panel.slideToggle();
                $button.toggleClass('expanded');
                $('.show-text, .hide-text').toggle();
            });

            $('#visibility-radius').on('input', function() {
                $('#visibility-radius-value').text($(this).val() + ' km');
                self.updatePrivacyScore();
            });

            $('#location-precision').on('change', function() {
                self.updatePrecisionIndicator($(this).val());
                self.updatePrivacyScore();
            });

            $('.privacy-settings-panel input[type="checkbox"], .privacy-settings-panel select').on('change', function() {
                self.updatePrivacyScore();
            });

            $('.save-privacy-settings').on('click', function() {
                self.savePrivacySettings();
            });

            $('.reset-privacy-defaults').on('click', function() {
                self.resetPrivacyDefaults();
            });

            $('.export-location-data').on('click', function() {
                self.exportLocationData();
            });

            $('.delete-location-data').on('click', function() {
                self.deleteLocationData();
            });

            $('.privacy-help').on('click', function() {
                self.showPrivacyHelp();
            });
        },

        /**
         * Initialize location controls
         */
        initLocationControls: function() {
            if (window.WPMatchNearMe.userLocation) {
                this.showLocationEnabled();
            } else {
                this.showLocationRequired();
            }
        },

        /**
         * Check initial location state
         */
        checkInitialLocationState: function() {
            if (window.WPMatchNearMe.userLocation) {
                // Auto-search if location is available
                setTimeout(() => {
                    this.searchNearbyUsers();
                }, 500);
            }
        },

        /**
         * Request location permission
         */
        requestLocationPermission: function() {
            if (!navigator.geolocation) {
                this.showNotification('error', window.WPMatchNearMe.strings.locationError);
                return;
            }

            const self = this;

            // Show loading state
            this.showLocationLoading();

            navigator.geolocation.getCurrentPosition(
                function(position) {
                    self.updateUserLocation(position.coords);
                },
                function(error) {
                    self.handleLocationError(error);
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 300000 // 5 minutes
                }
            );
        },

        /**
         * Update user location
         */
        updateUserLocation: function(coords) {
            const self = this;

            const locationData = {
                latitude: coords.latitude,
                longitude: coords.longitude,
                accuracy: coords.accuracy,
                location_type: 'current',
                privacy_level: 'approximate'
            };

            fetch(window.WPMatchNearMe.apiUrl + '/location/update', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.WPMatchNearMe.nonce
                },
                body: JSON.stringify(locationData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.WPMatchNearMe.userLocation = {
                        latitude: coords.latitude,
                        longitude: coords.longitude,
                        city: data.data.location_details.city,
                        state: data.data.location_details.state
                    };

                    self.showLocationEnabled();
                    self.showNotification('success', window.WPMatchNearMe.strings.locationUpdated);

                    // Auto-search after location update
                    setTimeout(() => {
                        self.searchNearbyUsers();
                    }, 1000);
                } else {
                    self.showNotification('error', data.message || 'Failed to update location');
                }
            })
            .catch(error => {
                self.showNotification('error', window.WPMatchNearMe.strings.locationError);
            });
        },

        /**
         * Search for nearby users
         */
        searchNearbyUsers: function() {
            if (!window.WPMatchNearMe.userLocation) {
                this.showLocationRequired();
                return;
            }

            const searchParams = this.getSearchParameters();
            this.showLoadingState();

            fetch(window.WPMatchNearMe.apiUrl + '/location/nearby?' + new URLSearchParams(searchParams), {
                headers: {
                    'X-WP-Nonce': window.WPMatchNearMe.nonce
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.displayNearbyUsers(data.data, data.meta);
                } else {
                    this.showNotification('error', data.message || window.WPMatchNearMe.strings.searchError);
                    this.showEmptyState();
                }
            })
            .catch(error => {
                this.showNotification('error', window.WPMatchNearMe.strings.searchError);
                this.showEmptyState();
            });
        },

        /**
         * Get search parameters
         */
        getSearchParameters: function() {
            return {
                radius: $('#distance-range').val(),
                limit: 20,
                min_age: $('#min-age').val() || 18,
                max_age: $('#max-age').val() || 99,
                gender: $('#gender-filter').val() || ''
            };
        },

        /**
         * Display nearby users
         */
        displayNearbyUsers: function(users, meta) {
            const $grid = $('#users-grid');
            const $header = $('#results-header');
            const $count = $('#results-count');

            if (!users || users.length === 0) {
                this.showEmptyState();
                return;
            }

            // Update results count
            $count.text(users.length);
            $header.show();

            // Clear and populate grid
            $grid.empty();

            users.forEach(user => {
                const userCard = this.createUserCard(user);
                $grid.append(userCard);
            });

            // Hide other states
            $('#loading-state, #empty-state, #permission-required').hide();
        },

        /**
         * Create user card HTML
         */
        createUserCard: function(user) {
            const age = user.age ? user.age : '';
            const distanceKm = parseFloat(user.distance_km) || 0;

            // Format distance display
            const distance = distanceKm < 1 ?
                Math.round(distanceKm * 1000) + 'm away' :
                distanceKm.toFixed(1) + 'km away';

            // Determine distance category for styling
            const distanceClass = this.getDistanceClass(distanceKm);

            const verified = user.is_verified ?
                '<div class="verified-badge"><i class="fas fa-check-circle"></i></div>' : '';

            // Travel mode badge for users traveling
            const travelBadge = user.is_traveling ?
                '<div class="travel-mode-badge">Traveling</div>' : '';

            return $(`
                <div class="user-card" data-user-id="${user.user_id}" data-distance="${distanceKm}">
                    <div class="user-image">
                        <img src="${user.profile_image || '/wp-content/plugins/wpmatch/public/images/default-avatar.png'}"
                             alt="${user.display_name}" loading="lazy">
                        ${verified}
                        ${travelBadge}
                        <div class="distance-badge ${distanceClass}">${distance}</div>
                    </div>
                    <div class="user-info">
                        <h4>${user.display_name} ${age ? ', ' + age : ''}</h4>
                        <p class="location">
                            <i class="fas fa-map-marker-alt"></i>
                            ${user.city}, ${user.state}
                        </p>
                        <p class="last-seen">${this.formatLastSeen(user.last_active)}</p>
                        <div class="location-indicator">
                            <div class="location-icon ${user.location_precision || 'approximate'}"></div>
                            <span>${this.getLocationPrecisionText(user.location_precision)}</span>
                        </div>
                    </div>
                    <div class="user-actions">
                        <button type="button" class="btn-icon like-user" data-user-id="${user.user_id}"
                                title="${window.WPMatchNearMe.strings.like}">
                            <i class="fas fa-heart"></i>
                        </button>
                        <button type="button" class="btn-icon super-like-user" data-user-id="${user.user_id}"
                                title="${window.WPMatchNearMe.strings.superLike}">
                            <i class="fas fa-star"></i>
                        </button>
                        <button type="button" class="btn-icon message-user" data-user-id="${user.user_id}"
                                title="${window.WPMatchNearMe.strings.sendMessage}">
                            <i class="fas fa-comment"></i>
                        </button>
                        <button type="button" class="btn-icon view-profile" data-user-id="${user.user_id}"
                                title="${window.WPMatchNearMe.strings.viewProfile}">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
            `);
        },

        /**
         * Get distance class for styling
         */
        getDistanceClass: function(distanceKm) {
            if (distanceKm <= 5) return 'close';
            if (distanceKm <= 25) return 'moderate';
            return 'far';
        },

        /**
         * Get location precision text
         */
        getLocationPrecisionText: function(precision) {
            const precisionLabels = {
                exact: 'Exact location',
                approximate: 'Approximate location',
                'city-only': 'City only',
                hidden: 'Location hidden'
            };
            return precisionLabels[precision] || 'Approximate location';
        },

        /**
         * Format last seen time
         */
        formatLastSeen: function(lastActive) {
            if (!lastActive) return '';

            const now = new Date();
            const lastSeen = new Date(lastActive);
            const diffMs = now - lastSeen;
            const diffMins = Math.floor(diffMs / 60000);

            if (diffMins < 5) {
                return window.WPMatchNearMe.strings.online;
            } else if (diffMins < 60) {
                return window.WPMatchNearMe.strings.lastSeen.replace('%s', diffMins + ' minutes');
            } else if (diffMins < 1440) {
                const hours = Math.floor(diffMins / 60);
                return window.WPMatchNearMe.strings.lastSeen.replace('%s', hours + ' hours');
            } else {
                const days = Math.floor(diffMins / 1440);
                return window.WPMatchNearMe.strings.lastSeen.replace('%s', days + ' days');
            }
        },

        /**
         * Switch view (grid/list/map)
         */
        switchView: function(view) {
            $('.users-grid, .map-view').hide();

            if (view === 'map') {
                $('#map-view').show();
                // Initialize map if needed
                this.initializeMap();
            } else {
                $('#users-grid').show();
                if (view === 'list') {
                    $('#users-grid').addClass('list-view').removeClass('grid-view');
                } else {
                    $('#users-grid').addClass('grid-view').removeClass('list-view');
                }
            }
        },

        /**
         * Save privacy settings
         */
        savePrivacySettings: function() {
            const settings = {
                show_exact_location: $('#show-exact-location').is(':checked'),
                show_distance: $('#show-distance').is(':checked'),
                hide_from_nearby: $('#hide-from-nearby').is(':checked'),
                location_blur_radius_km: parseFloat($('#location-blur-radius').val())
            };

            fetch(window.WPMatchNearMe.apiUrl + '/location/privacy', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.WPMatchNearMe.nonce
                },
                body: JSON.stringify(settings)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.showNotification('success', window.WPMatchNearMe.strings.privacyUpdated);
                    window.WPMatchNearMe.privacySettings = settings;
                } else {
                    this.showNotification('error', data.message || 'Failed to save privacy settings');
                }
            })
            .catch(error => {
                this.showNotification('error', 'Failed to save privacy settings');
            });
        },

        /**
         * Show different UI states
         */
        showLocationRequired: function() {
            $('#permission-required').show();
            $('#loading-state, #results-header, #users-grid, #empty-state').hide();
        },

        showLocationEnabled: function() {
            const location = window.WPMatchNearMe.userLocation;
            if (location) {
                $('#location-status .status-item').removeClass('inactive').addClass('active');
                $('#location-status .status-item span').text(
                    `Location: ${location.city}, ${location.state}`
                );
                $('#location-status .get-location').hide();
                $('#location-status .update-location').show();
            }
            $('#permission-required').hide();
        },

        showLocationLoading: function() {
            $('#location-status .status-item span').text('Getting your location...');
            $('#location-status .get-location, .update-location').prop('disabled', true);
        },

        showLoadingState: function() {
            $('#loading-state').show();
            $('#results-header, #users-grid, #empty-state, #permission-required').hide();
        },

        showEmptyState: function() {
            $('#empty-state').show();
            $('#loading-state, #results-header, #users-grid, #permission-required').hide();
        },

        /**
         * Handle location errors
         */
        handleLocationError: function(error) {
            let message = window.WPMatchNearMe.strings.locationError;

            switch(error.code) {
                case error.PERMISSION_DENIED:
                    message = window.WPMatchNearMe.strings.permissionDenied;
                    break;
                case error.POSITION_UNAVAILABLE:
                    message = 'Location information is unavailable.';
                    break;
                case error.TIMEOUT:
                    message = 'Location request timed out.';
                    break;
            }

            this.showNotification('error', message);
            this.showLocationRequired();
        },

        /**
         * Update search parameters
         */
        updateSearchParams: function() {
            // Debounced auto-search
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                if (window.WPMatchNearMe.userLocation) {
                    this.searchNearbyUsers();
                }
            }, 1000);
        },

        /**
         * Initialize map (placeholder)
         */
        initializeMap: function() {
            const $mapContainer = $('#map-container');
            if (!window.WPMatchNearMe.userLocation) {
                $mapContainer.html('<div class="map-placeholder"><p>Location required for map view</p></div>');
                return;
            }

            // This would integrate with a mapping service
            $mapContainer.html('<div class="map-placeholder"><p>Map integration coming soon</p></div>');
        },

        /**
         * Handle user actions (like, message, etc.)
         */
        handleUserAction: function(userId, action) {
            switch(action) {
                case 'like':
                    this.likeUser(userId);
                    break;
                case 'super_like':
                    this.superLikeUser(userId);
                    break;
                case 'message':
                    this.messageUser(userId);
                    break;
                case 'view_profile':
                    this.viewUserProfile(userId);
                    break;
            }
        },

        /**
         * Like user
         */
        likeUser: function(userId) {
            fetch(window.WPMatchNearMe.apiUrl + '/matching/like', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.WPMatchNearMe.nonce
                },
                body: JSON.stringify({ user_id: userId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    $(`.like-user[data-user-id="${userId}"]`)
                        .addClass('liked')
                        .html('<i class="fas fa-heart"></i>')
                        .prop('disabled', true);

                    if (data.data && data.data.is_match) {
                        this.showNotification('success', 'It\'s a match! 🎉');
                    } else {
                        this.showNotification('success', 'Like sent!');
                    }
                }
            })
            .catch(() => {
                this.showNotification('error', 'Failed to send like');
            });
        },

        /**
         * Super like user
         */
        superLikeUser: function(userId) {
            fetch(window.WPMatchNearMe.apiUrl + '/matching/super-like', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.WPMatchNearMe.nonce
                },
                body: JSON.stringify({ user_id: userId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    $(`.super-like-user[data-user-id="${userId}"]`)
                        .addClass('super-liked')
                        .html('<i class="fas fa-star"></i>')
                        .prop('disabled', true);

                    this.showNotification('success', 'Super like sent! ⭐');
                }
            })
            .catch(() => {
                this.showNotification('error', 'Failed to send super like');
            });
        },

        /**
         * Message user
         */
        messageUser: function(userId) {
            // Redirect to messaging interface
            window.location.href = `/messages?user_id=${userId}`;
        },

        /**
         * View user profile
         */
        viewUserProfile: function(userId) {
            // Load profile in modal or redirect
            this.loadUserProfileModal(userId);
        },

        /**
         * Load user profile modal
         */
        loadUserProfileModal: function(userId) {
            const $modal = $('#user-profile-modal');
            const $modalBody = $('#modal-body');

            $modalBody.html('<div class="loading-spinner"></div><p>Loading profile...</p>');
            $modal.fadeIn();

            fetch(window.WPMatchNearMe.apiUrl + '/users/profile/' + userId, {
                headers: {
                    'X-WP-Nonce': window.WPMatchNearMe.nonce
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    $modalBody.html(this.renderProfileModal(data.data));
                } else {
                    $modalBody.html('<p>Error loading profile</p>');
                }
            })
            .catch(() => {
                $modalBody.html('<p>Error loading profile</p>');
            });
        },

        /**
         * Render profile modal content
         */
        renderProfileModal: function(user) {
            return `
                <div class="profile-modal-content">
                    <div class="profile-header">
                        <img src="${user.profile_image}" alt="${user.display_name}" class="profile-image">
                        <div class="profile-basic-info">
                            <h2>${user.display_name}, ${user.age}</h2>
                            <p class="location"><i class="fas fa-map-marker-alt"></i> ${user.city}, ${user.state}</p>
                            <p class="distance">${user.distance_km}km away</p>
                        </div>
                    </div>
                    <div class="profile-details">
                        <div class="profile-section">
                            <h3>About</h3>
                            <p>${user.bio || 'No bio available'}</p>
                        </div>
                        <div class="profile-section">
                            <h3>Interests</h3>
                            <div class="interests-tags">
                                ${user.interests ? user.interests.map(interest => `<span class="interest-tag">${interest}</span>`).join('') : 'No interests listed'}
                            </div>
                        </div>
                    </div>
                    <div class="profile-actions">
                        <button type="button" class="btn btn-primary message-user" data-user-id="${user.user_id}">
                            <i class="fas fa-comment"></i> Send Message
                        </button>
                        <button type="button" class="btn btn-secondary like-user" data-user-id="${user.user_id}">
                            <i class="fas fa-heart"></i> Like
                        </button>
                    </div>
                </div>
            `;
        },

        /**
         * Sort users by distance
         */
        sortUsersByDistance: function() {
            const $grid = $('#users-grid');
            const $cards = $grid.find('.user-card').get();

            $cards.sort((a, b) => {
                const distanceA = parseFloat($(a).data('distance')) || 0;
                const distanceB = parseFloat($(b).data('distance')) || 0;
                return distanceA - distanceB;
            });

            $grid.empty().append($cards);
        },

        /**
         * Filter users by distance range
         */
        filterUsersByDistance: function(maxDistance) {
            $('#users-grid .user-card').each(function() {
                const cardDistance = parseFloat($(this).data('distance')) || 0;
                if (cardDistance <= maxDistance) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        },

        /**
         * Show notification with different types
         */
        showNotification: function(type, message) {
            const $notification = $(`
                <div class="wpmatch-notification ${type}">
                    <div class="notification-content">
                        <i class="fas fa-${this.getNotificationIcon(type)}"></i>
                        <span>${message}</span>
                    </div>
                    <button type="button" class="close-notification">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `);

            $('body').append($notification);

            // Show notification
            setTimeout(() => {
                $notification.addClass('show');
            }, 100);

            // Auto hide after 4 seconds
            setTimeout(() => {
                $notification.removeClass('show');
                setTimeout(() => $notification.remove(), 300);
            }, 4000);

            // Manual close
            $notification.find('.close-notification').on('click', function() {
                $notification.removeClass('show');
                setTimeout(() => $notification.remove(), 300);
            });
        },

        /**
         * Get notification icon based on type
         */
        getNotificationIcon: function(type) {
            const icons = {
                success: 'check-circle',
                error: 'exclamation-circle',
                warning: 'exclamation-triangle',
                info: 'info-circle'
            };
            return icons[type] || 'info-circle';
        },

        /**
         * Apply advanced filters
         */
        applyAdvancedFilters: function() {
            const filters = this.getAdvancedFiltersData();
            this.currentFilters = {...this.currentFilters, ...filters};

            // Update search with new filters
            this.searchNearbyUsers();

            // Update active filters display
            this.updateActiveFiltersDisplay();

            // Show notification
            this.showNotification('success', 'Filters applied successfully!');
        },

        /**
         * Get advanced filters data
         */
        getAdvancedFiltersData: function() {
            const filters = {};

            // Basic filters
            filters.online_status = $('#online-status-filter').val();
            filters.location_precision = $('#location-precision-filter').val();
            filters.travel_status = $('#travel-status-filter').val();
            filters.verification = $('#verification-filter').val();

            // Interests
            const selectedInterests = [];
            $('#selected-interests .interest-tag').each(function() {
                selectedInterests.push($(this).data('interest'));
            });
            filters.interests = selectedInterests;

            // Location types
            const locationTypes = [];
            $('.checkbox-group input[type="checkbox"]:checked').each(function() {
                locationTypes.push($(this).val());
            });
            filters.location_types = locationTypes;

            // Saved location
            filters.saved_location = $('#saved-locations-filter').val();

            return filters;
        },

        /**
         * Reset all filters
         */
        resetAllFilters: function() {
            // Reset form values
            $('#online-status-filter, #location-precision-filter, #travel-status-filter, #verification-filter, #saved-locations-filter').val('');
            $('#interests-filter').val('');
            $('#selected-interests').empty();
            $('.checkbox-group input[type="checkbox"]').prop('checked', false);

            // Reset distance to default
            $('#distance-range').val(window.WPMatchNearMe.defaults.radius).trigger('input');

            // Reset age range
            $('#min-age').val(window.WPMatchNearMe.defaults.minAge);
            $('#max-age').val(window.WPMatchNearMe.defaults.maxAge);

            // Reset gender filter
            $('#gender-filter').val('');

            // Clear current filters
            this.currentFilters = {};

            // Hide active filters display
            $('.active-filters-summary').removeClass('show');

            // Refresh search
            this.searchNearbyUsers();

            this.showNotification('info', 'All filters have been reset');
        },

        /**
         * Update advanced filters (real-time)
         */
        updateAdvancedFilters: function() {
            // Add debounced filter update
            clearTimeout(this.filterTimeout);
            this.filterTimeout = setTimeout(() => {
                this.applyAdvancedFilters();
            }, 500);
        },

        /**
         * Search interests
         */
        searchInterests: function(query) {
            if (query.length < 2) return;

            // Debounce search
            clearTimeout(this.interestSearchTimeout);
            this.interestSearchTimeout = setTimeout(() => {
                this.performInterestSearch(query);
            }, 300);
        },

        /**
         * Perform interest search
         */
        performInterestSearch: function(query) {
            fetch(window.WPMatchNearMe.apiUrl + '/interests/search?q=' + encodeURIComponent(query), {
                headers: {
                    'X-WP-Nonce': window.WPMatchNearMe.nonce
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.displayInterestSuggestions(data.data);
                }
            })
            .catch(error => {
                console.error('Interest search error:', error);
            });
        },

        /**
         * Display interest suggestions
         */
        displayInterestSuggestions: function(interests) {
            const $container = $('#interests-filter').parent();
            let $suggestions = $container.find('.interest-suggestions');

            if ($suggestions.length === 0) {
                $suggestions = $('<div class="interest-suggestions"></div>');
                $container.append($suggestions);
            }

            $suggestions.empty();

            interests.forEach(interest => {
                const $suggestion = $(`
                    <div class="interest-suggestion" data-interest="${interest.name}">
                        ${interest.name}
                        <small>(${interest.user_count} users)</small>
                    </div>
                `);

                $suggestion.on('click', () => {
                    this.addInterestFilter(interest.name);
                    $suggestions.hide();
                    $('#interests-filter').val('');
                });

                $suggestions.append($suggestion);
            });

            $suggestions.show();
        },

        /**
         * Add interest filter
         */
        addInterestFilter: function(interestName) {
            // Check if interest already added
            if ($(`#selected-interests .interest-tag[data-interest="${interestName}"]`).length > 0) {
                return;
            }

            const $tag = $(`
                <div class="interest-tag" data-interest="${interestName}">
                    ${interestName}
                    <button type="button" class="remove-interest" title="Remove">×</button>
                </div>
            `);

            $tag.find('.remove-interest').on('click', function() {
                $tag.remove();
                WPMatchLocation.updateAdvancedFilters();
            });

            $('#selected-interests').append($tag);
            this.updateAdvancedFilters();
        },

        /**
         * Update location type filters
         */
        updateLocationTypeFilters: function() {
            this.updateAdvancedFilters();
        },

        /**
         * Update saved location filter
         */
        updateSavedLocationFilter: function(locationType) {
            // Handle different saved location types
            switch(locationType) {
                case 'home':
                    this.useLocationForSearch('home');
                    break;
                case 'work':
                    this.useLocationForSearch('work');
                    break;
                case '':
                default:
                    this.useLocationForSearch('current');
                    break;
            }
        },

        /**
         * Use specific location for search
         */
        useLocationForSearch: function(locationType) {
            // This would load the saved location and update search center
            fetch(window.WPMatchNearMe.apiUrl + '/location/saved/' + locationType, {
                headers: {
                    'X-WP-Nonce': window.WPMatchNearMe.nonce
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.searchCenterLocation = data.data;
                    this.searchNearbyUsers();
                    this.showNotification('success', `Using ${locationType} location for search`);
                }
            })
            .catch(error => {
                this.showNotification('error', `Failed to load ${locationType} location`);
            });
        },

        /**
         * Show custom location modal
         */
        showCustomLocationModal: function() {
            // Create modal if it doesn't exist
            let $modal = $('.custom-location-modal');
            if ($modal.length === 0) {
                $modal = $(`
                    <div class="custom-location-modal">
                        <div class="custom-location-content">
                            <h3>Search Custom Location</h3>
                            <div class="location-input-group">
                                <label for="custom-location-input">Enter Location</label>
                                <input type="text" id="custom-location-input" placeholder="City, State or Address">
                                <div class="location-suggestions"></div>
                            </div>
                            <div class="modal-actions">
                                <button type="button" class="btn btn-primary use-custom-location">Use This Location</button>
                                <button type="button" class="btn btn-secondary cancel-custom-location">Cancel</button>
                            </div>
                        </div>
                    </div>
                `);
                $('body').append($modal);

                // Bind events
                $modal.find('.cancel-custom-location').on('click', () => {
                    $modal.hide();
                    $('#saved-locations-filter').val('');
                });

                $modal.find('.use-custom-location').on('click', () => {
                    this.useCustomLocation($('#custom-location-input').val());
                });

                $modal.find('#custom-location-input').on('input', function() {
                    WPMatchLocation.searchCustomLocations($(this).val());
                });
            }

            $modal.show();
        },

        /**
         * Search custom locations
         */
        searchCustomLocations: function(query) {
            if (query.length < 3) return;

            clearTimeout(this.locationSearchTimeout);
            this.locationSearchTimeout = setTimeout(() => {
                // Use a geocoding service or location search API
                this.performLocationSearch(query);
            }, 300);
        },

        /**
         * Perform location search
         */
        performLocationSearch: function(query) {
            fetch(window.WPMatchNearMe.apiUrl + '/location/search?q=' + encodeURIComponent(query), {
                headers: {
                    'X-WP-Nonce': window.WPMatchNearMe.nonce
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.displayLocationSuggestions(data.data);
                }
            })
            .catch(error => {
                console.error('Location search error:', error);
            });
        },

        /**
         * Display location suggestions
         */
        displayLocationSuggestions: function(locations) {
            const $suggestions = $('.custom-location-modal .location-suggestions');
            $suggestions.empty();

            locations.forEach(location => {
                const $suggestion = $(`
                    <div class="location-suggestion" data-lat="${location.lat}" data-lng="${location.lng}">
                        ${location.display_name}
                    </div>
                `);

                $suggestion.on('click', () => {
                    $('#custom-location-input').val(location.display_name);
                    $suggestions.hide();
                });

                $suggestions.append($suggestion);
            });

            $suggestions.show();
        },

        /**
         * Use custom location
         */
        useCustomLocation: function(locationString) {
            const lat = $('#custom-location-input').parent().find('.location-suggestions .location-suggestion.selected').data('lat');
            const lng = $('#custom-location-input').parent().find('.location-suggestions .location-suggestion.selected').data('lng');

            if (lat && lng) {
                this.searchCenterLocation = { latitude: lat, longitude: lng, name: locationString };
                this.searchNearbyUsers();
                $('.custom-location-modal').hide();
                this.showNotification('success', `Using custom location: ${locationString}`);
            } else {
                this.showNotification('error', 'Please select a location from the suggestions');
            }
        },

        /**
         * Update active filters display
         */
        updateActiveFiltersDisplay: function() {
            const filters = this.getAdvancedFiltersData();
            const $summary = $('.active-filters-summary');
            const $tags = $summary.find('.active-filter-tags');

            $tags.empty();

            let hasActiveFilters = false;

            // Add filter tags for each active filter
            Object.keys(filters).forEach(key => {
                const value = filters[key];
                if (value && (Array.isArray(value) ? value.length > 0 : true)) {
                    hasActiveFilters = true;
                    const displayName = this.getFilterDisplayName(key, value);
                    if (displayName) {
                        const $tag = $(`
                            <div class="active-filter-tag" data-filter="${key}">
                                ${displayName}
                                <button type="button" class="remove-filter">×</button>
                            </div>
                        `);

                        $tag.find('.remove-filter').on('click', () => {
                            this.removeFilter(key);
                        });

                        $tags.append($tag);
                    }
                }
            });

            if (hasActiveFilters) {
                $summary.addClass('show');
            } else {
                $summary.removeClass('show');
            }
        },

        /**
         * Get filter display name
         */
        getFilterDisplayName: function(key, value) {
            const displayNames = {
                online_status: { online: 'Online Now', recent: 'Active Today', week: 'Active This Week' },
                location_precision: { exact: 'Exact Location', approximate: 'Approximate', 'city-only': 'City Only' },
                travel_status: { local: 'Locals Only', traveling: 'Travelers Only' },
                verification: { verified: 'Verified Only', photo_verified: 'Photo Verified' }
            };

            if (Array.isArray(value)) {
                return value.length > 0 ? value.join(', ') : null;
            }

            return displayNames[key] ? displayNames[key][value] : value;
        },

        /**
         * Remove specific filter
         */
        removeFilter: function(filterKey) {
            // Reset the specific filter
            switch(filterKey) {
                case 'interests':
                    $('#selected-interests').empty();
                    break;
                case 'location_types':
                    $('.checkbox-group input[type="checkbox"]').prop('checked', false);
                    break;
                default:
                    $(`#${filterKey.replace('_', '-')}-filter`).val('');
                    break;
            }

            // Reapply filters
            this.applyAdvancedFilters();
        },

        /**
         * Save filter preset
         */
        saveFilterPreset: function() {
            const filters = this.getAdvancedFiltersData();
            const presetName = prompt('Enter a name for this filter preset:');

            if (presetName) {
                fetch(window.WPMatchNearMe.apiUrl + '/location/filter-presets', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': window.WPMatchNearMe.nonce
                    },
                    body: JSON.stringify({
                        name: presetName,
                        filters: filters
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        this.showNotification('success', `Filter preset "${presetName}" saved!`);
                    }
                })
                .catch(error => {
                    this.showNotification('error', 'Failed to save filter preset');
                });
            }
        },

        /* ========================================================================
           Privacy Controls Methods
           ======================================================================== */

        /**
         * Update privacy score based on current settings
         */
        updatePrivacyScore: function() {
            const settings = this.getPrivacySettings();
            let score = 100;
            const scoreFactors = [];

            // Location sharing enabled
            if (settings.enable_location_sharing) {
                score -= 10;
                scoreFactors.push({ name: 'Location sharing enabled', impact: -10, status: 'warning' });
            } else {
                scoreFactors.push({ name: 'Location sharing disabled', impact: 0, status: 'secure' });
            }

            // Location precision
            switch (settings.location_precision) {
                case 'exact':
                    score -= 20;
                    scoreFactors.push({ name: 'Exact location precision', impact: -20, status: 'risk' });
                    break;
                case 'approximate':
                    score -= 10;
                    scoreFactors.push({ name: 'Approximate location', impact: -10, status: 'warning' });
                    break;
                case 'city':
                    score -= 5;
                    scoreFactors.push({ name: 'City-level location', impact: -5, status: 'warning' });
                    break;
                case 'region':
                    scoreFactors.push({ name: 'Regional location only', impact: 0, status: 'secure' });
                    break;
            }

            // Auto-update location
            if (settings.auto_update_location) {
                score -= 15;
                scoreFactors.push({ name: 'Auto-update location', impact: -15, status: 'risk' });
            } else {
                scoreFactors.push({ name: 'Manual location updates', impact: 0, status: 'secure' });
            }

            // Distance visibility
            if (settings.show_distance) {
                score -= 5;
                scoreFactors.push({ name: 'Distance shown to others', impact: -5, status: 'warning' });
            } else {
                scoreFactors.push({ name: 'Distance hidden', impact: 0, status: 'secure' });
            }

            // Hide from nearby searches
            if (settings.hide_from_nearby) {
                score += 10;
                scoreFactors.push({ name: 'Hidden from nearby searches', impact: +10, status: 'secure' });
            } else {
                scoreFactors.push({ name: 'Visible in nearby searches', impact: 0, status: 'warning' });
            }

            // Ghost mode
            if (settings.ghost_mode) {
                score += 15;
                scoreFactors.push({ name: 'Ghost mode enabled', impact: +15, status: 'secure' });
            }

            // Location history
            if (settings.save_location_history) {
                score -= 10;
                scoreFactors.push({ name: 'Location history saved', impact: -10, status: 'warning' });
            } else {
                scoreFactors.push({ name: 'No location history', impact: 0, status: 'secure' });
            }

            // Ensure score stays within bounds
            score = Math.max(0, Math.min(100, score));

            // Update UI
            this.displayPrivacyScore(score, scoreFactors);
        },

        /**
         * Get current privacy settings from form
         */
        getPrivacySettings: function() {
            return {
                enable_location_sharing: $('#enable-location-sharing').is(':checked'),
                location_precision: $('#location-precision').val(),
                auto_update_location: $('#auto-update-location').is(':checked'),
                show_distance: $('#show-distance').is(':checked'),
                show_last_location_update: $('#show-last-location-update').is(':checked'),
                hide_from_nearby: $('#hide-from-nearby').is(':checked'),
                visibility_radius_km: $('#visibility-radius').val(),
                hide_from_search_engines: $('#hide-from-search-engines').is(':checked'),
                require_match_for_location: $('#require-match-for-location').is(':checked'),
                ghost_mode: $('#ghost-mode').is(':checked'),
                location_schedule: $('#location-schedule').val(),
                save_location_history: $('#save-location-history').is(':checked'),
                location_retention: $('#location-retention').val()
            };
        },

        /**
         * Display privacy score and factors
         */
        displayPrivacyScore: function(score, factors) {
            // Update score circle
            const $scoreCircle = $('.score-circle');
            const $scoreValue = $('.score-value');
            const degrees = (score / 100) * 360;

            let scoreColor = '#4caf50'; // Good
            if (score < 70) scoreColor = '#ff9800'; // Warning
            if (score < 40) scoreColor = '#f44336'; // Risk

            $scoreCircle.css('background', `conic-gradient(${scoreColor} 0deg, ${scoreColor} ${degrees}deg, #e0e0e0 ${degrees}deg)`);
            $scoreValue.text(score);

            // Update summary items
            const $summaryItems = $('#summary-items');
            $summaryItems.empty();

            factors.forEach(factor => {
                const $item = $(`
                    <div class="summary-item">
                        <span>${factor.name}</span>
                        <span class="summary-item-status ${factor.status}">${factor.status}</span>
                    </div>
                `);
                $summaryItems.append($item);
            });
        },

        /**
         * Update precision indicator
         */
        updatePrecisionIndicator: function(precision) {
            const $indicator = $('#precision-indicator .precision-level');
            $indicator.removeClass('exact approximate city region').addClass(precision);
        },

        /**
         * Save privacy settings
         */
        savePrivacySettings: function() {
            const settings = this.getPrivacySettings();

            fetch(window.WPMatchNearMe.apiUrl + '/location/privacy-settings', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.WPMatchNearMe.nonce
                },
                body: JSON.stringify(settings)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.showNotification('success', 'Privacy settings saved successfully!');
                } else {
                    this.showNotification('error', data.message || 'Failed to save privacy settings');
                }
            })
            .catch(error => {
                this.showNotification('error', 'Failed to save privacy settings');
            });
        },

        /**
         * Reset privacy settings to defaults
         */
        resetPrivacyDefaults: function() {
            if (!confirm('Reset all privacy settings to their default values?')) {
                return;
            }

            // Reset form to defaults
            $('#enable-location-sharing').prop('checked', true);
            $('#location-precision').val('approximate');
            $('#auto-update-location').prop('checked', false);
            $('#show-distance').prop('checked', true);
            $('#show-last-location-update').prop('checked', false);
            $('#hide-from-nearby').prop('checked', false);
            $('#visibility-radius').val(50);
            $('#visibility-radius-value').text('50 km');
            $('#hide-from-search-engines').prop('checked', true);
            $('#require-match-for-location').prop('checked', false);
            $('#ghost-mode').prop('checked', false);
            $('#location-schedule').val('always');
            $('#save-location-history').prop('checked', false);
            $('#location-retention').val('30_days');

            // Update indicators
            this.updatePrecisionIndicator('approximate');
            this.updatePrivacyScore();

            this.showNotification('info', 'Privacy settings reset to defaults');
        },

        /**
         * Export location data
         */
        exportLocationData: function() {
            fetch(window.WPMatchNearMe.apiUrl + '/location/export-data', {
                method: 'POST',
                headers: {
                    'X-WP-Nonce': window.WPMatchNearMe.nonce
                }
            })
            .then(response => response.blob())
            .then(blob => {
                // Create download link
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `wpmatch-location-data-${new Date().toISOString().split('T')[0]}.json`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);

                this.showNotification('success', 'Location data exported successfully');
            })
            .catch(error => {
                this.showNotification('error', 'Failed to export location data');
            });
        },

        /**
         * Delete all location data
         */
        deleteLocationData: function() {
            const confirmation = prompt('Type "DELETE" to confirm permanent deletion of all your location data:');

            if (confirmation !== 'DELETE') {
                this.showNotification('info', 'Location data deletion cancelled');
                return;
            }

            fetch(window.WPMatchNearMe.apiUrl + '/location/delete-all-data', {
                method: 'DELETE',
                headers: {
                    'X-WP-Nonce': window.WPMatchNearMe.nonce
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.showNotification('success', 'All location data has been permanently deleted');

                    // Reset location-dependent features
                    this.userLocation = null;
                    this.showLocationRequired();
                } else {
                    this.showNotification('error', data.message || 'Failed to delete location data');
                }
            })
            .catch(error => {
                this.showNotification('error', 'Failed to delete location data');
            });
        },

        /**
         * Show privacy help modal
         */
        showPrivacyHelp: function() {
            // Create help modal if it doesn't exist
            let $helpModal = $('.privacy-help-modal');
            if ($helpModal.length === 0) {
                $helpModal = $(`
                    <div class="privacy-help-modal modal">
                        <div class="modal-overlay"></div>
                        <div class="modal-content">
                            <button type="button" class="modal-close">
                                <i class="fas fa-times"></i>
                            </button>
                            <div class="modal-body">
                                <h3><i class="fas fa-shield-alt"></i> Location Privacy Help</h3>

                                <div class="help-section">
                                    <h4>Location Precision Levels</h4>
                                    <ul>
                                        <li><strong>Exact:</strong> Your precise GPS coordinates are used for matching</li>
                                        <li><strong>Approximate:</strong> Your location is randomized within 1-5km</li>
                                        <li><strong>City:</strong> Only your city is shared</li>
                                        <li><strong>Region:</strong> Only your region/state is shared</li>
                                    </ul>
                                </div>

                                <div class="help-section">
                                    <h4>Advanced Privacy Features</h4>
                                    <ul>
                                        <li><strong>Ghost Mode:</strong> Browse profiles without appearing in discovery</li>
                                        <li><strong>Location Schedule:</strong> Control when your location is shared</li>
                                        <li><strong>Visibility Radius:</strong> Maximum distance for appearing in searches</li>
                                    </ul>
                                </div>

                                <div class="help-section">
                                    <h4>Data Management</h4>
                                    <ul>
                                        <li><strong>Location History:</strong> Keep past locations for better matching</li>
                                        <li><strong>Data Retention:</strong> How long to store your location data</li>
                                        <li><strong>Export Data:</strong> Download all your location information</li>
                                    </ul>
                                </div>

                                <div class="help-section">
                                    <h4>Privacy Score</h4>
                                    <p>Your privacy score is calculated based on your current settings:</p>
                                    <ul>
                                        <li><strong>90-100%:</strong> Excellent privacy protection</li>
                                        <li><strong>70-89%:</strong> Good privacy with some sharing</li>
                                        <li><strong>40-69%:</strong> Moderate privacy - consider adjusting settings</li>
                                        <li><strong>Below 40%:</strong> Low privacy - review your settings</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                `);
                $('body').append($helpModal);

                // Bind close events
                $helpModal.find('.modal-close, .modal-overlay').on('click', function() {
                    $helpModal.fadeOut();
                });
            }

            $helpModal.fadeIn();
        },

        /**
         * Initialize privacy controls
         */
        initializePrivacyControls: function() {
            // Update precision indicator
            const currentPrecision = $('#location-precision').val();
            this.updatePrecisionIndicator(currentPrecision);

            // Calculate initial privacy score
            this.updatePrivacyScore();
        },

        /* ========================================================================
           Travel Mode (Passport Feature) Methods
           ======================================================================== */

        /**
         * Toggle travel mode
         */
        toggleTravelMode: function() {
            const $travelSection = $('#travel-mode-section');
            const $toggleButton = $('#toggle-travel-mode');

            if ($travelSection.is(':visible')) {
                this.exitTravelMode();
            } else {
                // Show travel mode
                $travelSection.slideDown();
                $toggleButton.hide();

                // Load existing travel plans
                this.loadTravelPlans();

                // Initialize travel mode
                this.initializeTravelMode();

                this.showNotification('info', 'Travel mode activated! Plan your next adventure.');
            }
        },

        /**
         * Exit travel mode
         */
        exitTravelMode: function() {
            const $travelSection = $('#travel-mode-section');
            const $toggleButton = $('#toggle-travel-mode');

            $travelSection.slideUp(() => {
                // Reset travel mode
                this.resetTravelMode();
            });
            $toggleButton.show();

            // Return to regular search mode
            this.travelMode = false;
            this.searchNearbyUsers();

            this.showNotification('info', 'Returned to local search mode');
        },

        /**
         * Initialize travel mode
         */
        initializeTravelMode: function() {
            this.travelMode = true;
            this.currentTravelDestination = null;

            // Set default travel dates (tomorrow to next week)
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            const nextWeek = new Date();
            nextWeek.setDate(nextWeek.getDate() + 7);

            $('#travel-start-date').val(tomorrow.toISOString().split('T')[0]);
            $('#travel-end-date').val(nextWeek.toISOString().split('T')[0]);
        },

        /**
         * Reset travel mode
         */
        resetTravelMode: function() {
            $('#travel-destination').val('');
            $('#travel-start-date').val('');
            $('#travel-end-date').val('');
            $('#travel-radius').val(25);
            $('#travel-radius-display').text('25 km');
            $('#show-locals').prop('checked', true);
            $('#show-travelers').prop('checked', false);
            $('#notify-matches').prop('checked', false);
            $('#destination-suggestions').hide().empty();
        },

        /**
         * Search destination
         */
        searchDestination: function(query) {
            if (query.length < 2) return;

            clearTimeout(this.destinationSearchTimeout);
            this.destinationSearchTimeout = setTimeout(() => {
                this.performDestinationSearch(query);
            }, 300);
        },

        /**
         * Perform destination search
         */
        performDestinationSearch: function(query) {
            fetch(window.WPMatchNearMe.apiUrl + '/travel/destinations/search?q=' + encodeURIComponent(query), {
                headers: {
                    'X-WP-Nonce': window.WPMatchNearMe.nonce
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.displayDestinationSuggestions(data.data);
                }
            })
            .catch(error => {
                console.error('Destination search error:', error);
            });
        },

        /**
         * Display destination suggestions
         */
        displayDestinationSuggestions: function(destinations) {
            const $suggestions = $('#destination-suggestions');
            $suggestions.empty();

            if (destinations.length === 0) {
                $suggestions.hide();
                return;
            }

            destinations.forEach(destination => {
                const $suggestion = $(`
                    <div class="destination-suggestion"
                         data-lat="${destination.latitude}"
                         data-lng="${destination.longitude}"
                         data-name="${destination.name}"
                         data-country="${destination.country}">
                        <strong>${destination.name}</strong>
                        <small>${destination.country}</small>
                    </div>
                `);

                $suggestion.on('click', () => {
                    this.selectDestination(destination);
                    $suggestions.hide();
                });

                $suggestions.append($suggestion);
            });

            $suggestions.show();
        },

        /**
         * Select destination
         */
        selectDestination: function(destination) {
            $('#travel-destination').val(destination.name + ', ' + destination.country);
            this.currentTravelDestination = {
                name: destination.name,
                country: destination.country,
                latitude: destination.latitude,
                longitude: destination.longitude
            };

            this.showNotification('success', `Destination selected: ${destination.name}`);
        },

        /**
         * Start travel search
         */
        startTravelSearch: function() {
            if (!this.currentTravelDestination) {
                this.showNotification('error', 'Please select a destination first');
                return;
            }

            const startDate = $('#travel-start-date').val();
            const endDate = $('#travel-end-date').val();

            if (!startDate || !endDate) {
                this.showNotification('error', 'Please select travel dates');
                return;
            }

            // Get travel search parameters
            const searchParams = {
                destination: this.currentTravelDestination,
                start_date: startDate,
                end_date: endDate,
                radius: $('#travel-radius').val(),
                show_locals: $('#show-locals').is(':checked'),
                show_travelers: $('#show-travelers').is(':checked'),
                notify_matches: $('#notify-matches').is(':checked')
            };

            // Perform travel search
            this.performTravelSearch(searchParams);
        },

        /**
         * Perform travel search
         */
        performTravelSearch: function(searchParams) {
            this.showLoadingState();

            fetch(window.WPMatchNearMe.apiUrl + '/travel/search', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.WPMatchNearMe.nonce
                },
                body: JSON.stringify(searchParams)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.displayTravelResults(data.data);
                    this.showNotification('success', `Found ${data.data.length} users in ${searchParams.destination.name}`);
                } else {
                    this.showEmptyState();
                    this.showNotification('info', data.message || 'No users found at destination');
                }
            })
            .catch(error => {
                this.showEmptyState();
                this.showNotification('error', 'Error searching destination');
            });
        },

        /**
         * Display travel results
         */
        displayTravelResults: function(users) {
            const $grid = $('#users-grid');
            const $header = $('#results-header');
            const $count = $('#results-count');

            // Update results count
            $count.text(users.length);
            $header.show();

            // Clear and populate grid
            $grid.empty();

            users.forEach(user => {
                // Add travel mode indicators to user data
                user.is_travel_search = true;
                const userCard = this.createUserCard(user);
                $grid.append(userCard);
            });

            // Hide other states
            $('#loading-state, #empty-state, #permission-required').hide();

            // Add travel mode indicator to results
            if (!$('.travel-results-indicator').length) {
                $header.append(`
                    <div class="travel-results-indicator">
                        <i class="fas fa-plane"></i>
                        <span>Travel Mode: ${this.currentTravelDestination.name}</span>
                    </div>
                `);
            }
        },

        /**
         * Save travel plan
         */
        saveTravelPlan: function() {
            if (!this.currentTravelDestination) {
                this.showNotification('error', 'Please select a destination first');
                return;
            }

            const startDate = $('#travel-start-date').val();
            const endDate = $('#travel-end-date').val();

            if (!startDate || !endDate) {
                this.showNotification('error', 'Please select travel dates');
                return;
            }

            const travelPlan = {
                destination: this.currentTravelDestination,
                start_date: startDate,
                end_date: endDate,
                radius: $('#travel-radius').val(),
                show_locals: $('#show-locals').is(':checked'),
                show_travelers: $('#show-travelers').is(':checked'),
                notify_matches: $('#notify-matches').is(':checked')
            };

            fetch(window.WPMatchNearMe.apiUrl + '/travel/plans', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.WPMatchNearMe.nonce
                },
                body: JSON.stringify(travelPlan)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.showNotification('success', 'Travel plan saved successfully!');
                    this.loadTravelPlans();
                } else {
                    this.showNotification('error', data.message || 'Failed to save travel plan');
                }
            })
            .catch(error => {
                this.showNotification('error', 'Failed to save travel plan');
            });
        },

        /**
         * Load travel plans
         */
        loadTravelPlans: function() {
            fetch(window.WPMatchNearMe.apiUrl + '/travel/plans', {
                headers: {
                    'X-WP-Nonce': window.WPMatchNearMe.nonce
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.displayTravelPlans(data.data);
                }
            })
            .catch(error => {
                console.error('Error loading travel plans:', error);
            });
        },

        /**
         * Display travel plans
         */
        displayTravelPlans: function(plans) {
            const $plansList = $('#travel-plans-list');
            $plansList.empty();

            if (plans.length === 0) {
                $plansList.html(`
                    <div class="no-travel-plans">
                        <p>No travel plans yet. Create your first travel plan above!</p>
                    </div>
                `);
                return;
            }

            plans.forEach(plan => {
                const $planItem = this.createTravelPlanItem(plan);
                $plansList.append($planItem);
            });
        },

        /**
         * Create travel plan item
         */
        createTravelPlanItem: function(plan) {
            const startDate = new Date(plan.start_date);
            const endDate = new Date(plan.end_date);
            const now = new Date();

            let status = 'upcoming';
            let statusText = 'Upcoming';

            if (startDate <= now && endDate >= now) {
                status = 'active';
                statusText = 'Active Now';
            } else if (endDate < now) {
                status = 'past';
                statusText = 'Past Trip';
            }

            const $planItem = $(`
                <div class="travel-plan-item" data-plan-id="${plan.plan_id}">
                    <div class="travel-plan-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <div class="travel-plan-details">
                        <div class="travel-plan-destination">${plan.destination_name}</div>
                        <div class="travel-plan-dates">
                            ${startDate.toLocaleDateString()} - ${endDate.toLocaleDateString()}
                        </div>
                        <div class="travel-plan-status ${status}">
                            ${statusText}
                        </div>
                    </div>
                    <div class="travel-plan-actions">
                        <button type="button" class="travel-plan-action primary use-plan" data-plan-id="${plan.plan_id}">
                            Use Plan
                        </button>
                        <button type="button" class="travel-plan-action edit-plan" data-plan-id="${plan.plan_id}">
                            Edit
                        </button>
                        <button type="button" class="travel-plan-action delete-plan" data-plan-id="${plan.plan_id}">
                            Delete
                        </button>
                    </div>
                </div>
            `);

            // Bind action events
            $planItem.find('.use-plan').on('click', () => {
                this.useTravelPlan(plan);
            });

            $planItem.find('.edit-plan').on('click', () => {
                this.editTravelPlan(plan);
            });

            $planItem.find('.delete-plan').on('click', () => {
                this.deleteTravelPlan(plan.plan_id);
            });

            return $planItem;
        },

        /**
         * Use travel plan
         */
        useTravelPlan: function(plan) {
            // Populate form with plan data
            $('#travel-destination').val(plan.destination_name);
            $('#travel-start-date').val(plan.start_date);
            $('#travel-end-date').val(plan.end_date);
            $('#travel-radius').val(plan.radius);
            $('#travel-radius-display').text(plan.radius + ' km');
            $('#show-locals').prop('checked', plan.show_locals);
            $('#show-travelers').prop('checked', plan.show_travelers);
            $('#notify-matches').prop('checked', plan.notify_matches);

            // Set current destination
            this.currentTravelDestination = {
                name: plan.destination_name,
                country: plan.destination_country,
                latitude: plan.destination_lat,
                longitude: plan.destination_lng
            };

            // Start search automatically
            this.startTravelSearch();

            this.showNotification('success', `Using travel plan: ${plan.destination_name}`);
        },

        /**
         * Edit travel plan
         */
        editTravelPlan: function(plan) {
            // For now, just populate the form - in a full implementation
            // you might want a dedicated edit modal
            this.useTravelPlan(plan);
            this.showNotification('info', 'Edit your travel plan and save changes');
        },

        /**
         * Delete travel plan
         */
        deleteTravelPlan: function(planId) {
            if (!confirm('Are you sure you want to delete this travel plan?')) {
                return;
            }

            fetch(window.WPMatchNearMe.apiUrl + '/travel/plans/' + planId, {
                method: 'DELETE',
                headers: {
                    'X-WP-Nonce': window.WPMatchNearMe.nonce
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.showNotification('success', 'Travel plan deleted');
                    this.loadTravelPlans();
                } else {
                    this.showNotification('error', 'Failed to delete travel plan');
                }
            })
            .catch(error => {
                this.showNotification('error', 'Failed to delete travel plan');
            });
        }
    };

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        WPMatchLocation.init();
    });

})(jQuery);