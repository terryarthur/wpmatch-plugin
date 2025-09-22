/**
 * WPMatch Events JavaScript
 *
 * @package WPMatch
 * @since 1.6.0
 */

(function($) {
    'use strict';

    /**
     * Events object
     */
    const WPMatchEvents = {

        /**
         * Current speed dating session
         */
        speedDatingSession: null,

        /**
         * Timer interval
         */
        timerInterval: null,

        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.loadEvents();
            this.checkSpeedDatingSession();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            // Event filters
            $(document).on('click', '.event-filter-btn', this.filterEvents);

            // Event registration
            $(document).on('click', '.register-event-btn', this.registerForEvent);

            // Event cancellation
            $(document).on('click', '.cancel-registration-btn', this.cancelRegistration);

            // View event details
            $(document).on('click', '.view-event-btn', this.viewEventDetails);

            // Create event
            $(document).on('click', '.create-event-btn', this.showCreateEventForm);
            $(document).on('submit', '.create-event-form', this.createEvent);

            // Speed dating actions
            $(document).on('click', '.join-speed-dating-btn', this.joinSpeedDating);
            $(document).on('click', '.leave-speed-dating-btn', this.leaveSpeedDating);
            $(document).on('click', '.interested-btn', this.markInterested);
            $(document).on('click', '.not-interested-btn', this.markNotInterested);

            // My events tabs
            $(document).on('click', '.my-events-tab', this.switchMyEventsTab);

            // Modal close
            $(document).on('click', '.event-modal-close, .event-modal', function(e) {
                if (e.target === this) {
                    WPMatchEvents.closeModal();
                }
            });

            // Form validation
            $(document).on('change', '.form-input, .form-select, .form-textarea', this.validateForm);
        },

        /**
         * Load events
         */
        loadEvents: function(filter = 'upcoming') {
            const $eventsGrid = $('.events-grid');
            this.showLoadingState($eventsGrid);

            $.ajax({
                url: wpmatch_events.rest_url + 'wpmatch/v1/events/list',
                type: 'GET',
                data: {
                    status: filter,
                    limit: 20
                },
                headers: {
                    'X-WP-Nonce': wpmatch_events.nonce
                },
                success: function(response) {
                    if (response.success) {
                        WPMatchEvents.renderEvents(response.data);
                    } else {
                        WPMatchEvents.showError($eventsGrid, response.message);
                    }
                },
                error: function() {
                    WPMatchEvents.showError($eventsGrid, wpmatch_events.strings.error);
                }
            });
        },

        /**
         * Render events
         */
        renderEvents: function(events) {
            const $grid = $('.events-grid');
            $grid.empty();

            if (events.length === 0) {
                $grid.html(`
                    <div class="no-events">
                        <p>${wpmatch_events.strings.no_events}</p>
                    </div>
                `);
                return;
            }

            events.forEach(event => {
                const $card = this.createEventCard(event);
                $grid.append($card);
            });
        },

        /**
         * Create event card
         */
        createEventCard: function(event) {
            const eventDate = new Date(event.start_time);
            const isRegistered = event.is_registered || false;
            const isFull = event.current_participants >= event.max_participants;
            const isOwner = event.organizer_id == wpmatch_events.current_user_id;

            let actionButton = '';
            if (isOwner) {
                actionButton = `<a href="#" class="event-btn event-btn-secondary manage-event-btn" data-event-id="${event.event_id}">Manage</a>`;
            } else if (isRegistered) {
                actionButton = `<button class="event-btn event-btn-warning cancel-registration-btn" data-event-id="${event.event_id}">Cancel Registration</button>`;
            } else if (isFull) {
                actionButton = `<button class="event-btn" disabled>Event Full</button>`;
            } else {
                actionButton = `<button class="event-btn event-btn-primary register-event-btn" data-event-id="${event.event_id}">Register</button>`;
            }

            return $(`
                <div class="event-card ${event.event_type === 'speed_dating' ? 'speed-dating' : ''} ${event.is_featured ? 'featured' : ''}">
                    <div class="event-image">
                        ${event.image_url ? `<img src="${event.image_url}" alt="${event.title}">` : ''}
                        <div class="event-type-badge">${this.formatEventType(event.event_type)}</div>
                        <div class="event-status-badge ${event.status}">${this.formatStatus(event.status)}</div>
                    </div>
                    <div class="event-content">
                        <h3 class="event-title">${event.title}</h3>
                        <p class="event-description">${event.description}</p>

                        <div class="event-meta">
                            <div class="event-meta-item">
                                <span class="event-meta-icon">📅</span>
                                <span>${this.formatDateTime(event.start_time)}</span>
                            </div>
                            ${event.location ? `
                                <div class="event-meta-item">
                                    <span class="event-meta-icon">📍</span>
                                    <span>${event.location}</span>
                                </div>
                            ` : ''}
                            <div class="event-meta-item">
                                <span class="event-meta-icon">⏱️</span>
                                <span>${event.duration_minutes} minutes</span>
                            </div>
                        </div>

                        <div class="event-participants">
                            <div class="participants-info">
                                <span class="participants-count">${event.current_participants}/${event.max_participants}</span>
                                <span>participants</span>
                            </div>
                            <div class="participants-avatars">
                                ${this.renderParticipantAvatars(event.participants || [])}
                            </div>
                        </div>

                        <div class="event-actions">
                            <a href="#" class="event-btn event-btn-secondary view-event-btn" data-event-id="${event.event_id}">View Details</a>
                            ${actionButton}
                        </div>
                    </div>
                </div>
            `);
        },

        /**
         * Render participant avatars
         */
        renderParticipantAvatars: function(participants) {
            let html = '';
            const maxShow = 5;

            participants.slice(0, maxShow).forEach(participant => {
                html += `<img src="${participant.avatar || wpmatch_events.default_avatar}" alt="${participant.name}" class="participant-avatar">`;
            });

            if (participants.length > maxShow) {
                html += `<div class="more-participants">+${participants.length - maxShow}</div>`;
            }

            return html;
        },

        /**
         * Format event type
         */
        formatEventType: function(type) {
            const types = {
                'speed_dating': 'Speed Dating',
                'virtual_meetup': 'Virtual Meetup',
                'group_activity': 'Group Activity',
                'workshop': 'Workshop',
                'social_mixer': 'Social Mixer'
            };
            return types[type] || type.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
        },

        /**
         * Format status
         */
        formatStatus: function(status) {
            const statuses = {
                'upcoming': 'Upcoming',
                'ongoing': 'Live Now',
                'completed': 'Completed',
                'cancelled': 'Cancelled'
            };
            return statuses[status] || status;
        },

        /**
         * Format date time
         */
        formatDateTime: function(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString() + ' at ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        },

        /**
         * Filter events
         */
        filterEvents: function(e) {
            e.preventDefault();

            const $btn = $(this);
            const filter = $btn.data('filter');

            // Update active filter
            $('.event-filter-btn').removeClass('active');
            $btn.addClass('active');

            // Load filtered events
            WPMatchEvents.loadEvents(filter);
        },

        /**
         * Register for event
         */
        registerForEvent: function(e) {
            e.preventDefault();

            const $btn = $(this);
            const eventId = $btn.data('event-id');

            if ($btn.prop('disabled')) return;

            // Show loading state
            const originalText = $btn.text();
            $btn.text(wpmatch_events.strings.registering).prop('disabled', true);

            $.ajax({
                url: wpmatch_events.rest_url + 'wpmatch/v1/events/register',
                type: 'POST',
                data: {
                    event_id: eventId
                },
                headers: {
                    'X-WP-Nonce': wpmatch_events.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $btn.text('Registered').removeClass('event-btn-primary').addClass('event-btn-success');

                        // Update button to allow cancellation
                        setTimeout(() => {
                            $btn.text('Cancel Registration')
                                .removeClass('event-btn-success register-event-btn')
                                .addClass('event-btn-warning cancel-registration-btn')
                                .prop('disabled', false);
                        }, 2000);

                        WPMatchEvents.showNotification(wpmatch_events.strings.registration_success, 'success');

                        // Trigger achievement check
                        $(document).trigger('wpmatch:action', ['event_registration', {event_id: eventId}]);
                    } else {
                        $btn.text(originalText).prop('disabled', false);
                        WPMatchEvents.showNotification(response.message || wpmatch_events.strings.registration_error, 'error');
                    }
                },
                error: function() {
                    $btn.text(originalText).prop('disabled', false);
                    WPMatchEvents.showNotification(wpmatch_events.strings.error, 'error');
                }
            });
        },

        /**
         * Cancel registration
         */
        cancelRegistration: function(e) {
            e.preventDefault();

            const $btn = $(this);
            const eventId = $btn.data('event-id');

            if (!confirm(wpmatch_events.strings.cancel_confirm)) return;

            const originalText = $btn.text();
            $btn.text(wpmatch_events.strings.cancelling).prop('disabled', true);

            $.ajax({
                url: wpmatch_events.rest_url + 'wpmatch/v1/events/cancel-registration',
                type: 'POST',
                data: {
                    event_id: eventId
                },
                headers: {
                    'X-WP-Nonce': wpmatch_events.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $btn.text('Register')
                            .removeClass('event-btn-warning cancel-registration-btn')
                            .addClass('event-btn-primary register-event-btn')
                            .prop('disabled', false);

                        WPMatchEvents.showNotification(wpmatch_events.strings.cancellation_success, 'success');
                    } else {
                        $btn.text(originalText).prop('disabled', false);
                        WPMatchEvents.showNotification(response.message || wpmatch_events.strings.cancellation_error, 'error');
                    }
                },
                error: function() {
                    $btn.text(originalText).prop('disabled', false);
                    WPMatchEvents.showNotification(wpmatch_events.strings.error, 'error');
                }
            });
        },

        /**
         * View event details
         */
        viewEventDetails: function(e) {
            e.preventDefault();

            const eventId = $(this).data('event-id');

            $.ajax({
                url: wpmatch_events.rest_url + 'wpmatch/v1/events/details',
                type: 'GET',
                data: {
                    event_id: eventId
                },
                headers: {
                    'X-WP-Nonce': wpmatch_events.nonce
                },
                success: function(response) {
                    if (response.success) {
                        WPMatchEvents.showEventDetailsModal(response.data);
                    } else {
                        WPMatchEvents.showNotification(response.message || wpmatch_events.strings.error, 'error');
                    }
                },
                error: function() {
                    WPMatchEvents.showNotification(wpmatch_events.strings.error, 'error');
                }
            });
        },

        /**
         * Show event details modal
         */
        showEventDetailsModal: function(event) {
            const modal = $(`
                <div class="event-modal">
                    <div class="event-modal-content">
                        <div class="event-modal-header">
                            <h3 class="event-modal-title">${event.title}</h3>
                            <button class="event-modal-close">&times;</button>
                        </div>
                        <div class="event-modal-body">
                            <div class="event-detail-section">
                                <h4>Description</h4>
                                <p>${event.description}</p>
                            </div>

                            <div class="event-detail-section">
                                <h4>Event Details</h4>
                                <p><strong>Date & Time:</strong> ${this.formatDateTime(event.start_time)}</p>
                                <p><strong>Duration:</strong> ${event.duration_minutes} minutes</p>
                                ${event.location ? `<p><strong>Location:</strong> ${event.location}</p>` : ''}
                                <p><strong>Type:</strong> ${this.formatEventType(event.event_type)}</p>
                                <p><strong>Participants:</strong> ${event.current_participants}/${event.max_participants}</p>
                            </div>

                            ${event.requirements ? `
                                <div class="event-detail-section">
                                    <h4>Requirements</h4>
                                    <p>${event.requirements}</p>
                                </div>
                            ` : ''}

                            ${event.organizer_name ? `
                                <div class="event-detail-section">
                                    <h4>Organizer</h4>
                                    <p>${event.organizer_name}</p>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `);

            $('body').append(modal);
        },

        /**
         * Close modal
         */
        closeModal: function() {
            $('.event-modal').fadeOut(() => {
                $('.event-modal').remove();
            });
        },

        /**
         * Show create event form
         */
        showCreateEventForm: function(e) {
            e.preventDefault();
            // Implementation would show a form modal or navigate to create page
            WPMatchEvents.showNotification('Create event feature coming soon!', 'info');
        },

        /**
         * Create event
         */
        createEvent: function(e) {
            e.preventDefault();

            const $form = $(this);
            const formData = new FormData(this);

            // Validate form
            if (!this.validateEventForm($form)) {
                return;
            }

            // Show loading state
            const $submitBtn = $form.find('[type="submit"]');
            const originalText = $submitBtn.text();
            $submitBtn.text(wpmatch_events.strings.creating).prop('disabled', true);

            $.ajax({
                url: wpmatch_events.rest_url + 'wpmatch/v1/events/create',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: {
                    'X-WP-Nonce': wpmatch_events.nonce
                },
                success: function(response) {
                    if (response.success) {
                        WPMatchEvents.showNotification(wpmatch_events.strings.create_success, 'success');
                        $form[0].reset();
                        WPMatchEvents.loadEvents();
                    } else {
                        WPMatchEvents.showNotification(response.message || wpmatch_events.strings.create_error, 'error');
                    }
                    $submitBtn.text(originalText).prop('disabled', false);
                },
                error: function() {
                    WPMatchEvents.showNotification(wpmatch_events.strings.error, 'error');
                    $submitBtn.text(originalText).prop('disabled', false);
                }
            });
        },

        /**
         * Validate event form
         */
        validateEventForm: function($form) {
            let isValid = true;

            // Clear previous errors
            $form.find('.error').removeClass('error');

            // Required field validation
            $form.find('[required]').each(function() {
                if (!$(this).val().trim()) {
                    $(this).addClass('error');
                    isValid = false;
                }
            });

            // Date validation
            const startDate = new Date($form.find('[name="start_time"]').val());
            if (startDate <= new Date()) {
                $form.find('[name="start_time"]').addClass('error');
                isValid = false;
            }

            if (!isValid) {
                WPMatchEvents.showNotification(wpmatch_events.strings.validation_error, 'error');
            }

            return isValid;
        },

        /**
         * Check speed dating session
         */
        checkSpeedDatingSession: function() {
            $.ajax({
                url: wpmatch_events.rest_url + 'wpmatch/v1/events/speed-dating/current',
                type: 'GET',
                headers: {
                    'X-WP-Nonce': wpmatch_events.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        WPMatchEvents.showSpeedDatingInterface(response.data);
                    }
                }
            });
        },

        /**
         * Show speed dating interface
         */
        showSpeedDatingInterface: function(session) {
            this.speedDatingSession = session;

            // Show speed dating section
            const $section = $('.speed-dating-section');
            if ($section.length === 0) {
                this.createSpeedDatingSection();
            }

            this.updateSpeedDatingDisplay();
            this.startSpeedDatingTimer();
        },

        /**
         * Create speed dating section
         */
        createSpeedDatingSection: function() {
            const $section = $(`
                <div class="speed-dating-section">
                    <div class="speed-dating-header">
                        <h3>Speed Dating Session</h3>
                    </div>
                    <div class="speed-dating-timer">
                        <div class="timer-display">00:00</div>
                        <div class="timer-label">Time Remaining</div>
                    </div>
                    <div class="speed-dating-round">
                        <div class="round-info">
                            <div class="current-round">Round 1</div>
                            <div class="total-rounds">of 5</div>
                        </div>
                        <div class="round-partner">
                            <img src="" alt="" class="partner-avatar">
                            <div class="partner-info">
                                <h4 class="partner-name"></h4>
                                <p class="partner-age"></p>
                            </div>
                        </div>
                        <div class="round-actions">
                            <button class="round-btn interested-btn">Interested</button>
                            <button class="round-btn not-interested-btn">Not Interested</button>
                            <button class="round-btn leave-speed-dating-btn">Leave Session</button>
                        </div>
                    </div>
                </div>
            `);

            $('.wpmatch-events-dashboard').prepend($section);
        },

        /**
         * Update speed dating display
         */
        updateSpeedDatingDisplay: function() {
            if (!this.speedDatingSession) return;

            const session = this.speedDatingSession;

            // Update round info
            $('.current-round').text(`Round ${session.current_round}`);
            $('.total-rounds').text(`of ${session.total_rounds}`);

            // Update partner info
            if (session.current_partner) {
                $('.partner-avatar').attr('src', session.current_partner.avatar || wpmatch_events.default_avatar);
                $('.partner-name').text(session.current_partner.name);
                $('.partner-age').text(`Age ${session.current_partner.age}`);
            }
        },

        /**
         * Start speed dating timer
         */
        startSpeedDatingTimer: function() {
            if (this.timerInterval) {
                clearInterval(this.timerInterval);
            }

            this.timerInterval = setInterval(() => {
                if (!this.speedDatingSession) return;

                const now = new Date();
                const roundEnd = new Date(this.speedDatingSession.round_end_time);
                const timeLeft = Math.max(0, roundEnd - now);

                if (timeLeft === 0) {
                    this.nextSpeedDatingRound();
                    return;
                }

                const minutes = Math.floor(timeLeft / 60000);
                const seconds = Math.floor((timeLeft % 60000) / 1000);

                $('.timer-display').text(
                    `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`
                );
            }, 1000);
        },

        /**
         * Next speed dating round
         */
        nextSpeedDatingRound: function() {
            $.ajax({
                url: wpmatch_events.rest_url + 'wpmatch/v1/events/speed-dating/next-round',
                type: 'POST',
                data: {
                    session_id: this.speedDatingSession.session_id
                },
                headers: {
                    'X-WP-Nonce': wpmatch_events.nonce
                },
                success: function(response) {
                    if (response.success) {
                        if (response.data.completed) {
                            WPMatchEvents.endSpeedDatingSession();
                        } else {
                            WPMatchEvents.speedDatingSession = response.data;
                            WPMatchEvents.updateSpeedDatingDisplay();
                        }
                    }
                }
            });
        },

        /**
         * Mark interested in speed dating
         */
        markInterested: function(e) {
            e.preventDefault();
            WPMatchEvents.submitSpeedDatingFeedback(true);
        },

        /**
         * Mark not interested in speed dating
         */
        markNotInterested: function(e) {
            e.preventDefault();
            WPMatchEvents.submitSpeedDatingFeedback(false);
        },

        /**
         * Submit speed dating feedback
         */
        submitSpeedDatingFeedback: function(interested) {
            if (!this.speedDatingSession || !this.speedDatingSession.current_partner) return;

            $.ajax({
                url: wpmatch_events.rest_url + 'wpmatch/v1/events/speed-dating/feedback',
                type: 'POST',
                data: {
                    session_id: this.speedDatingSession.session_id,
                    partner_id: this.speedDatingSession.current_partner.user_id,
                    interested: interested
                },
                headers: {
                    'X-WP-Nonce': wpmatch_events.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Disable buttons
                        $('.round-actions .round-btn').prop('disabled', true);

                        // Show feedback
                        const message = interested ? 'Marked as interested!' : 'Feedback recorded';
                        WPMatchEvents.showNotification(message, 'success');
                    }
                }
            });
        },

        /**
         * Leave speed dating session
         */
        leaveSpeedDating: function(e) {
            e.preventDefault();

            if (!confirm('Are you sure you want to leave the speed dating session?')) return;

            $.ajax({
                url: wpmatch_events.rest_url + 'wpmatch/v1/events/speed-dating/leave',
                type: 'POST',
                data: {
                    session_id: this.speedDatingSession.session_id
                },
                headers: {
                    'X-WP-Nonce': wpmatch_events.nonce
                },
                success: function(response) {
                    if (response.success) {
                        WPMatchEvents.endSpeedDatingSession();
                        WPMatchEvents.showNotification('Left speed dating session', 'info');
                    }
                }
            });
        },

        /**
         * End speed dating session
         */
        endSpeedDatingSession: function() {
            this.speedDatingSession = null;

            if (this.timerInterval) {
                clearInterval(this.timerInterval);
                this.timerInterval = null;
            }

            $('.speed-dating-section').fadeOut(() => {
                $('.speed-dating-section').remove();
            });
        },

        /**
         * Switch my events tab
         */
        switchMyEventsTab: function(e) {
            e.preventDefault();

            const $tab = $(this);
            const type = $tab.data('type');

            // Update active tab
            $('.my-events-tab').removeClass('active');
            $tab.addClass('active');

            // Load events for tab
            WPMatchEvents.loadMyEvents(type);
        },

        /**
         * Load my events
         */
        loadMyEvents: function(type) {
            // Implementation for loading user's events
        },

        /**
         * Show loading state
         */
        showLoadingState: function($container) {
            $container.html(`
                <div class="loading-events">
                    <div class="loading-spinner"></div>
                    <p>${wpmatch_events.strings.loading}</p>
                </div>
            `);
        },

        /**
         * Show error
         */
        showError: function($container, message) {
            $container.html(`
                <div class="events-error">
                    <p>${message}</p>
                    <button class="event-btn event-btn-primary retry-btn" onclick="WPMatchEvents.loadEvents()">
                        ${wpmatch_events.strings.retry}
                    </button>
                </div>
            `);
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

            // Auto hide after 5 seconds
            setTimeout(() => {
                $notification.fadeOut(() => $notification.remove());
            }, 5000);

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
        WPMatchEvents.init();
    });

})(jQuery);