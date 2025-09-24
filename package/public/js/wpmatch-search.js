jQuery(document).ready(function($) {
    'use strict';

    var searchFilters = {};
    var searchResults = [];
    var currentSort = 'compatibility';
    var isLoading = false;

    // Initialize search interface
    function initSearchInterface() {
        bindEvents();
        loadSavedFilters();
        initDistanceSlider();
    }

    // Bind event listeners
    function bindEvents() {
        // Search button
        $('.wpmatch-search-btn').on('click', performSearch);

        // Clear filters button
        $('.wpmatch-clear-btn').on('click', clearFilters);

        // Save search button
        $('.wpmatch-save-search-btn').on('click', saveSearch);

        // Sort dropdown
        $('.wpmatch-sort-select').on('change', function() {
            currentSort = $(this).val();
            sortResults();
        });

        // Distance slider
        $('.wpmatch-slider').on('input', function() {
            var value = $(this).val();
            $('.wpmatch-distance-value').text(value + ' miles');
            searchFilters.distance = value;
        });

        // Filter inputs
        $('.wpmatch-filter-input, .wpmatch-filter-select').on('change', function() {
            var name = $(this).attr('name');
            var value = $(this).val();
            searchFilters[name] = value;
        });

        // Interest checkboxes
        $('.wpmatch-interest-checkbox input[type="checkbox"]').on('change', function() {
            updateInterestFilters();
        });

        // Result card clicks
        $(document).on('click', '.wpmatch-result-card', function() {
            var userId = $(this).data('user-id');
            viewProfile(userId);
        });

        // Action button clicks
        $(document).on('click', '.wpmatch-like-btn', function(e) {
            e.stopPropagation();
            var userId = $(this).closest('[data-user-id]').data('user-id');
            likeUser(userId, $(this));
        });

        $(document).on('click', '.wpmatch-message-btn', function(e) {
            e.stopPropagation();
            var userId = $(this).closest('[data-user-id]').data('user-id');
            startConversation(userId);
        });

        // Adjust filters button
        $(document).on('click', '.wpmatch-adjust-filters', function() {
            $('html, body').animate({
                scrollTop: $('.wpmatch-search-filters').offset().top - 20
            }, 500);
        });

        // Enter key search
        $('.wpmatch-filter-input').on('keypress', function(e) {
            if (e.which === 13) {
                performSearch();
            }
        });
    }

    // Initialize distance slider
    function initDistanceSlider() {
        var slider = $('.wpmatch-slider');
        var initialValue = slider.val() || 25;
        $('.wpmatch-distance-value').text(initialValue + ' miles');
        searchFilters.distance = initialValue;
    }

    // Load saved filters from localStorage
    function loadSavedFilters() {
        var saved = localStorage.getItem('wpmatch_search_filters');
        if (saved) {
            try {
                searchFilters = JSON.parse(saved);
                applyFiltersToForm();
            } catch (e) {
                console.log('Could not load saved filters');
            }
        }
    }

    // Apply saved filters to form
    function applyFiltersToForm() {
        Object.keys(searchFilters).forEach(function(key) {
            var value = searchFilters[key];
            var $element = $('[name="' + key + '"]');

            if ($element.length) {
                if ($element.is('select') || $element.is('input[type="text"]') || $element.is('input[type="number"]')) {
                    $element.val(value);
                } else if ($element.is('input[type="range"]')) {
                    $element.val(value);
                    $('.wpmatch-distance-value').text(value + ' miles');
                }
            }
        });

        // Apply interest filters
        if (searchFilters.interests) {
            searchFilters.interests.forEach(function(interest) {
                $('input[name="interests[]"][value="' + interest + '"]').prop('checked', true);
            });
        }
    }

    // Perform search
    function performSearch() {
        if (isLoading) return;

        collectFilters();
        saveFiltersToStorage();

        isLoading = true;
        showLoading();

        $.ajax({
            url: wpmatchSearch.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpmatch_perform_search',
                nonce: wpmatchSearch.nonce,
                filters: searchFilters
            },
            success: function(response) {
                isLoading = false;
                hideLoading();

                if (response.success) {
                    searchResults = response.data;
                    displayResults();
                } else {
                    showError(response.data || 'Search failed');
                }
            },
            error: function() {
                isLoading = false;
                hideLoading();
                showError('Network error. Please try again.');
            }
        });
    }

    // Collect all filters from form
    function collectFilters() {
        searchFilters = {};

        // Basic filters
        $('.wpmatch-filter-input, .wpmatch-filter-select').each(function() {
            var name = $(this).attr('name');
            var value = $(this).val();
            if (value && value.trim() !== '') {
                searchFilters[name] = value.trim();
            }
        });

        // Distance slider
        searchFilters.distance = $('.wpmatch-slider').val();

        // Age range
        var ageMin = $('[name="age_min"]').val();
        var ageMax = $('[name="age_max"]').val();
        if (ageMin) searchFilters.age_min = parseInt(ageMin);
        if (ageMax) searchFilters.age_max = parseInt(ageMax);

        // Interests
        updateInterestFilters();
    }

    // Update interest filters
    function updateInterestFilters() {
        var selected = [];
        $('.wpmatch-interest-checkbox input[type="checkbox"]:checked').each(function() {
            selected.push($(this).val());
        });
        searchFilters.interests = selected;
    }

    // Save filters to localStorage
    function saveFiltersToStorage() {
        try {
            localStorage.setItem('wpmatch_search_filters', JSON.stringify(searchFilters));
        } catch (e) {
            console.log('Could not save filters');
        }
    }

    // Clear all filters
    function clearFilters() {
        searchFilters = {};

        // Clear form inputs
        $('.wpmatch-filter-input').val('');
        $('.wpmatch-filter-select').val('');
        $('.wpmatch-interest-checkbox input[type="checkbox"]').prop('checked', false);

        // Reset distance slider
        $('.wpmatch-slider').val(25);
        $('.wpmatch-distance-value').text('25 miles');

        // Clear localStorage
        localStorage.removeItem('wpmatch_search_filters');

        // Clear results
        clearResults();
    }

    // Save current search
    function saveSearch() {
        if (Object.keys(searchFilters).length === 0) {
            alert(wpMatchSearch.strings.pleaseSetFilters);
            return;
        }

        var searchName = prompt(wpMatchSearch.strings.enterSearchName);
        if (!searchName) return;

        $.ajax({
            url: wpmatchSearch.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpmatch_save_search',
                nonce: wpmatchSearch.nonce,
                name: searchName,
                filters: searchFilters
            },
            success: function(response) {
                if (response.success) {
                    alert(wpMatchSearch.strings.searchSavedSuccess);
                } else {
                    alert(wpMatchSearch.strings.searchSaveFailed + ' ' + (response.data || wpMatchSearch.strings.unknownError));
                }
            },
            error: function() {
                alert(wpMatchSearch.strings.networkError);
            }
        });
    }

    // Display search results
    function displayResults() {
        if (!searchResults || searchResults.length === 0) {
            showNoResults();
            return;
        }

        updateResultsCount(searchResults.length);
        renderResults();
        showResultsSection();
    }

    // Render search results
    function renderResults() {
        var $grid = $('.wpmatch-results-grid');
        $grid.empty();

        searchResults.forEach(function(user) {
            var cardHtml = createResultCard(user);
            $grid.append(cardHtml);
        });
    }

    // Create result card element
    function createResultCard(user) {
        var compatibility = Math.round(user.compatibility || 0);
        var interests = (user.interests || []).slice(0, 4);
        var interestTags = interests.map(function(interest) {
            return '<span class="wpmatch-interest-tag">' + interest + '</span>';
        }).join('');

        var badgeHtml = '';
        if (user.verified) {
            badgeHtml = '<div class="wpmatch-result-badge">Verified</div>';
        } else if (user.premium) {
            badgeHtml = '<div class="wpmatch-result-badge">Premium</div>';
        }

        var onlineBadge = user.online ? '<div class="wpmatch-online-badge">Online</div>' : '';

        var cardHtml = '<div class="wpmatch-result-card" data-user-id="' + user.id + '">' +
            '<div class="wpmatch-result-photo" style="background-image: url(' + (user.photo || wpmatchSearch.defaultPhoto) + ');">' +
                badgeHtml +
                onlineBadge +
            '</div>' +
            '<div class="wpmatch-result-info">' +
                '<div class="wpmatch-result-header">' +
                    '<div class="wpmatch-result-name">' + user.name + '</div>' +
                    '<div class="wpmatch-result-age">' + user.age + '</div>' +
                '</div>' +
                '<div class="wpmatch-result-location">' + (user.location || 'Location not specified') + '</div>' +
                '<div class="wpmatch-result-compatibility">' +
                    '<div class="wpmatch-compatibility-label">' + compatibility + '% Match</div>' +
                    '<div class="wpmatch-compatibility-bar">' +
                        '<div class="wpmatch-compatibility-fill" style="width: ' + compatibility + '%"></div>' +
                    '</div>' +
                '</div>' +
                '<div class="wpmatch-result-interests">' + interestTags + '</div>' +
                '<div class="wpmatch-result-actions">' +
                    '<button class="wpmatch-action-btn wpmatch-like-btn">Like</button>' +
                    '<button class="wpmatch-action-btn wpmatch-message-btn">Message</button>' +
                '</div>' +
            '</div>' +
        '</div>';

        return $(cardHtml);
    }

    // Sort results
    function sortResults() {
        if (!searchResults || searchResults.length === 0) return;

        searchResults.sort(function(a, b) {
            switch (currentSort) {
                case 'compatibility':
                    return (b.compatibility || 0) - (a.compatibility || 0);
                case 'distance':
                    return (parseFloat(a.distance) || 999) - (parseFloat(b.distance) || 999);
                case 'age':
                    return (a.age || 0) - (b.age || 0);
                case 'newest':
                    return new Date(b.joined_date || 0) - new Date(a.joined_date || 0);
                case 'active':
                    if (a.online && !b.online) return -1;
                    if (!a.online && b.online) return 1;
                    return new Date(b.last_seen || 0) - new Date(a.last_seen || 0);
                default:
                    return 0;
            }
        });

        renderResults();
    }

    // Update results count
    function updateResultsCount(count) {
        $('.wpmatch-results-count').text(count + ' result' + (count !== 1 ? 's' : '') + ' found');
    }

    // Show results section
    function showResultsSection() {
        $('.wpmatch-search-results').show();
        $('html, body').animate({
            scrollTop: $('.wpmatch-search-results').offset().top - 20
        }, 500);
    }

    // Show no results state
    function showNoResults() {
        $('.wpmatch-search-results').show();

        var noResultsHtml = '<div class="wpmatch-no-results">' +
            '<h3>No matches found</h3>' +
            '<p>We couldn\'t find anyone matching your criteria. Try adjusting your filters to see more results.</p>' +
            '<button class="wpmatch-adjust-filters">Adjust Filters</button>' +
        '</div>';

        $('.wpmatch-search-results').html(noResultsHtml);

        $('html, body').animate({
            scrollTop: $('.wpmatch-search-results').offset().top - 20
        }, 500);
    }

    // Show loading state
    function showLoading() {
        $('.wpmatch-search-btn').prop('disabled', true).html('<div class="wpmatch-loading-spinner" style="display: inline-block; width: 16px; height: 16px; margin-right: 8px;"></div>Searching...');

        var loadingHtml = '<div class="wpmatch-loading">' +
            '<div class="wpmatch-loading-spinner"></div>' +
            '<p>Searching for your perfect matches...</p>' +
        '</div>';

        $('.wpmatch-search-results').html(loadingHtml).show();

        $('html, body').animate({
            scrollTop: $('.wpmatch-search-results').offset().top - 20
        }, 500);
    }

    // Hide loading state
    function hideLoading() {
        $('.wpmatch-search-btn').prop('disabled', false).html('🔍 Search');
    }

    // Clear results
    function clearResults() {
        $('.wpmatch-search-results').hide().empty();
        searchResults = [];
    }

    // Show error message
    function showError(message) {
        var errorHtml = '<div class="wpmatch-no-results">' +
            '<h3>Search Error</h3>' +
            '<p>' + message + '</p>' +
            '<button class="wpmatch-search-btn">Try Again</button>' +
        '</div>';

        $('.wpmatch-search-results').html(errorHtml).show();
    }

    // Like a user
    function likeUser(userId, $button) {
        var originalText = $button.text();
        $button.prop('disabled', true).text('Liking...');

        $.ajax({
            url: wpmatchSearch.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpmatch_like_user',
                nonce: wpmatchSearch.nonce,
                user_id: userId
            },
            success: function(response) {
                if (response.success) {
                    $button.text('Liked!').removeClass('wpmatch-like-btn').addClass('liked');

                    if (response.data.match) {
                        showMatchNotification(response.data.user);
                    }

                    setTimeout(function() {
                        $button.text('Message').removeClass('liked').addClass('wpmatch-message-btn').prop('disabled', false);
                        $button.off('click').on('click', function(e) {
                            e.stopPropagation();
                            startConversation(userId);
                        });
                    }, 2000);
                } else {
                    $button.text(originalText).prop('disabled', false);
                    alert(wpMatchSearch.strings.failedToLikeUser + ' ' + (response.data || wpMatchSearch.strings.unknownError));
                }
            },
            error: function() {
                $button.text(originalText).prop('disabled', false);
                alert(wpMatchSearch.strings.networkErrorTryAgain);
            }
        });
    }

    // Show match notification
    function showMatchNotification(userData) {
        var matchHtml = '<div class="wpmatch-match-notification" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 9999; display: flex; align-items: center; justify-content: center;">' +
            '<div style="background: white; padding: 40px; border-radius: 20px; text-align: center; max-width: 400px;">' +
                '<h2 style="color: #e91e63; margin-bottom: 20px;">It\'s a Match! 🎉</h2>' +
                '<img src="' + (userData.photo || wpmatchSearch.defaultPhoto) + '" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; margin-bottom: 20px;">' +
                '<h3>' + userData.name + '</h3>' +
                '<p style="margin-bottom: 30px;">You both liked each other!</p>' +
                '<div style="display: flex; gap: 15px; justify-content: center;">' +
                    '<button class="wpmatch-match-message" style="background: #e91e63; color: white; border: none; padding: 12px 24px; border-radius: 25px; cursor: pointer;">Send Message</button>' +
                    '<button class="wpmatch-match-continue" style="background: #f0f0f0; color: #333; border: none; padding: 12px 24px; border-radius: 25px; cursor: pointer;">Keep Searching</button>' +
                '</div>' +
            '</div>' +
        '</div>';

        $('body').append(matchHtml);

        $('.wpmatch-match-continue, .wpmatch-match-notification').on('click', function(e) {
            if (e.target === this) {
                $('.wpmatch-match-notification').remove();
            }
        });

        $('.wpmatch-match-message').on('click', function() {
            startConversation(userData.id);
            $('.wpmatch-match-notification').remove();
        });
    }

    // View user profile
    function viewProfile(userId) {
        window.location.href = wpmatchSearch.profileUrl + '?user=' + userId;
    }

    // Start conversation with user
    function startConversation(userId) {
        window.location.href = wpmatchSearch.messageUrl + '?user=' + userId;
    }

    // Initialize the interface
    initSearchInterface();
});