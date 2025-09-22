jQuery(document).ready(function($) {
    'use strict';

    var currentView = 'grid';
    var currentFilter = 'all';
    var currentSort = 'recent';

    // Initialize matches interface
    function initMatchesInterface() {
        bindEvents();
        loadMatches();
    }

    // Bind event listeners
    function bindEvents() {
        // View toggle buttons
        $('.wpmatch-view-btn').on('click', function() {
            var view = $(this).data('view');
            switchView(view);
        });

        // Filter dropdown
        $('.wpmatch-filter-select').on('change', function() {
            currentFilter = $(this).val();
            loadMatches();
        });

        // Sort dropdown
        $('.wpmatch-sort-select').on('change', function() {
            currentSort = $(this).val();
            loadMatches();
        });

        // Match card clicks
        $(document).on('click', '.wpmatch-match-card, .wpmatch-list-item', function() {
            var userId = $(this).data('user-id');
            if (userId) {
                viewProfile(userId);
            }
        });

        // Action button clicks
        $(document).on('click', '.wpmatch-btn-message, .wpmatch-message-btn', function(e) {
            e.stopPropagation();
            var userId = $(this).closest('[data-user-id]').data('user-id');
            startConversation(userId);
        });

        $(document).on('click', '.wpmatch-btn-view', function(e) {
            e.stopPropagation();
            var userId = $(this).closest('[data-user-id]').data('user-id');
            viewProfile(userId);
        });

        // Start swiping button
        $(document).on('click', '.wpmatch-start-swiping', function() {
            window.location.href = wpmatchMatches.swipeUrl;
        });
    }

    // Switch between grid and list view
    function switchView(view) {
        currentView = view;

        $('.wpmatch-view-btn').removeClass('active');
        $('.wpmatch-view-btn[data-view="' + view + '"]').addClass('active');

        $('.wpmatch-matches-grid, .wpmatch-matches-list').removeClass('active');
        $('.wpmatch-matches-' + view).addClass('active');

        // Re-render matches in new view
        renderMatches();
    }

    // Load matches from server
    function loadMatches() {
        showLoading();

        $.ajax({
            url: wpmatchMatches.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpmatch_get_matches',
                nonce: wpmatchMatches.nonce,
                filter: currentFilter,
                sort: currentSort
            },
            success: function(response) {
                hideLoading();
                if (response.success) {
                    displayMatches(response.data);
                } else {
                    showError(response.data || 'Failed to load matches');
                }
            },
            error: function() {
                hideLoading();
                showError('Network error. Please try again.');
            }
        });
    }

    // Display matches in current view
    function displayMatches(matches) {
        if (!matches || matches.length === 0) {
            showEmptyState();
            return;
        }

        updateResultsCount(matches.length);

        if (currentView === 'grid') {
            renderGridView(matches);
        } else {
            renderListView(matches);
        }
    }

    // Render matches in grid view
    function renderGridView(matches) {
        var $grid = $('.wpmatch-matches-grid');
        $grid.empty();

        matches.forEach(function(match) {
            var cardHtml = createMatchCard(match);
            $grid.append(cardHtml);
        });
    }

    // Render matches in list view
    function renderListView(matches) {
        var $list = $('.wpmatch-matches-list');
        $list.empty();

        matches.forEach(function(match) {
            var itemHtml = createListItem(match);
            $list.append(itemHtml);
        });
    }

    // Create match card element
    function createMatchCard(match) {
        var statusClass = getStatusClass(match.status);
        var compatibilityWidth = Math.round(match.compatibility || 0);
        var interests = (match.interests || []).slice(0, 3);
        var interestTags = interests.map(function(interest) {
            return '<span class="wpmatch-interest-tag">' + interest + '</span>';
        }).join('');

        var cardHtml = '<div class="wpmatch-match-card" data-user-id="' + match.id + '">' +
            '<div class="wpmatch-match-photo" style="background-image: url(' + (match.photo || wpmatchMatches.defaultPhoto) + ');">' +
                '<div class="wpmatch-match-status ' + statusClass + '">' + getStatusLabel(match.status) + '</div>' +
                (match.online ? '<div class="wpmatch-online-badge">Online</div>' : '') +
            '</div>' +
            '<div class="wpmatch-match-info">' +
                '<div class="wpmatch-match-details">' +
                    '<div class="wpmatch-match-name">' + match.name + '</div>' +
                    '<div class="wpmatch-match-age">' + match.age + '</div>' +
                '</div>' +
                '<div class="wpmatch-match-distance">' + (match.distance || 'Distance unknown') + '</div>' +
                '<div class="wpmatch-match-compatibility">' +
                    '<div class="wpmatch-compatibility-label">' + compatibilityWidth + '% Match</div>' +
                    '<div class="wpmatch-compatibility-bar">' +
                        '<div class="wpmatch-compatibility-fill" style="width: ' + compatibilityWidth + '%"></div>' +
                    '</div>' +
                '</div>' +
                '<div class="wpmatch-result-interests">' + interestTags + '</div>' +
                '<div class="wpmatch-match-actions">' +
                    '<button class="wpmatch-action-button wpmatch-btn-message">Message</button>' +
                    '<button class="wpmatch-action-button wpmatch-btn-view">View Profile</button>' +
                '</div>' +
            '</div>' +
        '</div>';

        return $(cardHtml);
    }

    // Create list item element
    function createListItem(match) {
        var compatibilityWidth = Math.round(match.compatibility || 0);
        var timeAgo = formatTimeAgo(match.matched_at);

        var itemHtml = '<div class="wpmatch-list-item" data-user-id="' + match.id + '">' +
            '<div class="wpmatch-list-photo" style="background-image: url(' + (match.photo || wpmatchMatches.defaultPhoto) + ');"></div>' +
            '<div class="wpmatch-list-info">' +
                '<div class="wpmatch-list-header">' +
                    '<div class="wpmatch-list-name">' + match.name + '</div>' +
                    '<div class="wpmatch-list-time">' + timeAgo + '</div>' +
                '</div>' +
                '<div class="wpmatch-list-details">' +
                    '<div class="wpmatch-list-age">' + match.age + ' years old</div>' +
                    '<div class="wpmatch-list-distance">' + (match.distance || 'Distance unknown') + '</div>' +
                    '<div class="wpmatch-list-compatibility">' +
                        '<span>' + compatibilityWidth + '% match</span>' +
                        '<div class="wpmatch-mini-bar">' +
                            '<div class="wpmatch-mini-fill" style="width: ' + compatibilityWidth + '%"></div>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>' +
            '<div class="wpmatch-list-actions">' +
                '<button class="wpmatch-list-btn wpmatch-message-btn" style="background: #e91e63; color: white;">Message</button>' +
                '<button class="wpmatch-list-btn" style="background: #f0f0f0; color: #333;">View</button>' +
            '</div>' +
        '</div>';

        return $(itemHtml);
    }

    // Get status CSS class
    function getStatusClass(status) {
        switch (status) {
            case 'new': return 'wpmatch-status-new';
            case 'recent': return 'wpmatch-status-recent';
            case 'mutual': return 'wpmatch-status-mutual';
            default: return '';
        }
    }

    // Get status label
    function getStatusLabel(status) {
        switch (status) {
            case 'new': return 'New';
            case 'recent': return 'Recent';
            case 'mutual': return 'Mutual';
            default: return '';
        }
    }

    // Format time ago
    function formatTimeAgo(timestamp) {
        if (!timestamp) return '';

        var now = new Date();
        var matchTime = new Date(timestamp);
        var diffMs = now - matchTime;
        var diffHours = Math.floor(diffMs / (1000 * 60 * 60));
        var diffDays = Math.floor(diffHours / 24);

        if (diffDays > 0) {
            return diffDays + ' day' + (diffDays > 1 ? 's' : '') + ' ago';
        } else if (diffHours > 0) {
            return diffHours + ' hour' + (diffHours > 1 ? 's' : '') + ' ago';
        } else {
            return 'Just now';
        }
    }

    // Update results count
    function updateResultsCount(count) {
        $('.wpmatch-results-count').text(count + ' match' + (count !== 1 ? 'es' : ''));
    }

    // Show empty state
    function showEmptyState() {
        $('.wpmatch-results-header').hide();
        $('.wpmatch-matches-grid, .wpmatch-matches-list').hide();

        var emptyHtml = '<div class="wpmatch-empty-state">' +
            '<h3>No matches yet</h3>' +
            '<p>Start swiping to find your perfect match! The more you swipe, the better our algorithm gets at finding compatible people for you.</p>' +
            '<a href="' + wpmatchMatches.swipeUrl + '" class="wpmatch-start-swiping">Start Swiping</a>' +
        '</div>';

        $('.wpmatch-search-results').html(emptyHtml);
    }

    // Show loading state
    function showLoading() {
        var loadingHtml = '<div class="wpmatch-loading">' +
            '<div class="wpmatch-loading-spinner"></div>' +
            '<p>Loading your matches...</p>' +
        '</div>';

        $('.wpmatch-search-results').html(loadingHtml);
    }

    // Hide loading state
    function hideLoading() {
        $('.wpmatch-loading').remove();
        $('.wpmatch-results-header').show();
        $('.wpmatch-matches-' + currentView).show();
    }

    // Show error message
    function showError(message) {
        var errorHtml = '<div class="wpmatch-empty-state">' +
            '<h3>Oops!</h3>' +
            '<p>' + message + '</p>' +
            '<button onclick="window.location.reload();" class="wpmatch-start-swiping">Reload Page</button>' +
        '</div>';

        $('.wpmatch-search-results').html(errorHtml);
    }

    // View user profile
    function viewProfile(userId) {
        window.location.href = wpmatchMatches.profileUrl + '?user=' + userId;
    }

    // Start conversation with user
    function startConversation(userId) {
        window.location.href = wpmatchMatches.messageUrl + '?user=' + userId;
    }

    // Re-render current matches (used when switching views)
    function renderMatches() {
        var matches = $('.wpmatch-match-card, .wpmatch-list-item').map(function() {
            return $(this).data('user-id');
        }).get();

        if (matches.length > 0) {
            loadMatches();
        }
    }

    // Initialize the interface
    initMatchesInterface();
});