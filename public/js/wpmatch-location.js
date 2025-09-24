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
            this.loadNearbyUsers();
            this.loadLocationEvents();
            this.loadSearchHistory();
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
     * Initialize when document is ready
     */
    $(document).ready(function() {
        WPMatchLocation.init();
    });

})(jQuery);