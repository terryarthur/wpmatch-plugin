jQuery(document).ready(function($) {
    'use strict';

    var currentCardIndex = 0;
    var cards = [];
    var isDragging = false;
    var startX = 0;
    var startY = 0;
    var currentX = 0;
    var currentY = 0;

    // Initialize swipe interface
    function initSwipeInterface() {
        loadNextCards();
        bindEvents();
    }

    // Load next set of cards
    function loadNextCards() {
        $.ajax({
            url: wpmatchSwipe.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpmatch_get_swipe_cards',
                nonce: wpmatchSwipe.nonce,
                offset: currentCardIndex
            },
            success: function(response) {
                if (response.success && response.data.length > 0) {
                    cards = response.data;
                    renderCards();
                } else {
                    showNoMoreCards();
                }
            },
            error: function() {
                showError('Failed to load profiles. Please try again.');
            }
        });
    }

    // Render cards in the stack
    function renderCards() {
        var $cardStack = $('.wpmatch-card-stack');
        $cardStack.empty();

        cards.forEach(function(card, index) {
            if (index < 3) { // Show max 3 cards in stack
                var $card = createCardElement(card, index);
                $cardStack.append($card);
            }
        });

        // Apply stacking effect
        $('.wpmatch-card').each(function(index) {
            var scale = 1 - (index * 0.05);
            var translateY = index * 10;
            $(this).css({
                'transform': 'scale(' + scale + ') translateY(' + translateY + 'px)',
                'z-index': 10 - index
            });
        });
    }

    // Create card element
    function createCardElement(cardData, index) {
        var interests = cardData.interests ? cardData.interests.slice(0, 3) : [];
        var interestTags = interests.map(function(interest) {
            return '<span class="wpmatch-interest-tag">' + interest + '</span>';
        }).join('');

        var cardHtml = '<div class="wpmatch-card" data-user-id="' + cardData.id + '" data-index="' + index + '">' +
            '<div class="wpmatch-card-image" style="background-image: url(' + (cardData.photo || wpmatchSwipe.defaultPhoto) + ');">' +
                '<div class="wpmatch-card-overlay">' +
                    '<div class="wpmatch-card-name">' + cardData.name + ', ' + cardData.age + '</div>' +
                    '<div class="wpmatch-card-location">' + (cardData.location || 'Location not specified') + '</div>' +
                '</div>' +
            '</div>' +
            '<div class="wpmatch-card-info">' +
                '<div class="wpmatch-card-bio">' + (cardData.bio || 'No bio available') + '</div>' +
                '<div class="wpmatch-card-interests">' + interestTags + '</div>' +
            '</div>' +
            '<div class="wpmatch-swipe-hint wpmatch-hint-like">LIKE</div>' +
            '<div class="wpmatch-swipe-hint wpmatch-hint-nope">NOPE</div>' +
            '<div class="wpmatch-swipe-hint wpmatch-hint-super">SUPER LIKE</div>' +
        '</div>';

        return $(cardHtml);
    }

    // Bind events
    function bindEvents() {
        // Touch/mouse events for dragging
        $(document).on('mousedown touchstart', '.wpmatch-card:first-child', handleStart);
        $(document).on('mousemove touchmove', handleMove);
        $(document).on('mouseup touchend', handleEnd);

        // Action button events
        $('.wpmatch-btn-pass').on('click', function() {
            swipeCard('left');
        });

        $('.wpmatch-btn-like').on('click', function() {
            swipeCard('right');
        });

        $('.wpmatch-btn-super').on('click', function() {
            swipeCard('up');
        });

        // Reload button
        $('.wpmatch-reload-btn').on('click', function() {
            currentCardIndex = 0;
            loadNextCards();
        });
    }

    // Handle start of drag
    function handleStart(e) {
        e.preventDefault();
        var event = e.originalEvent.touches ? e.originalEvent.touches[0] : e.originalEvent;

        isDragging = true;
        startX = event.clientX;
        startY = event.clientY;

        $(e.currentTarget).addClass('dragging');
    }

    // Handle drag movement
    function handleMove(e) {
        if (!isDragging) return;

        e.preventDefault();
        var event = e.originalEvent.touches ? e.originalEvent.touches[0] : e.originalEvent;

        currentX = event.clientX - startX;
        currentY = event.clientY - startY;

        var $activeCard = $('.wpmatch-card:first-child');
        var rotation = currentX * 0.1;
        var opacity = 1 - Math.abs(currentX) / 300;

        $activeCard.css({
            'transform': 'translateX(' + currentX + 'px) translateY(' + currentY + 'px) rotate(' + rotation + 'deg)',
            'opacity': opacity
        });

        // Show appropriate hint
        showSwipeHint(currentX, currentY);
    }

    // Handle end of drag
    function handleEnd(e) {
        if (!isDragging) return;

        isDragging = false;
        var $activeCard = $('.wpmatch-card:first-child');
        $activeCard.removeClass('dragging');

        var threshold = 100;
        var superThreshold = 80;

        if (Math.abs(currentX) > threshold || Math.abs(currentY) > superThreshold) {
            if (currentY < -superThreshold) {
                swipeCard('up'); // Super like
            } else if (currentX > threshold) {
                swipeCard('right'); // Like
            } else if (currentX < -threshold) {
                swipeCard('left'); // Pass
            }
        } else {
            // Snap back to center
            $activeCard.css({
                'transform': 'translateX(0) translateY(0) rotate(0deg)',
                'opacity': 1
            });
            hideSwipeHints();
        }

        currentX = 0;
        currentY = 0;
    }

    // Show swipe hint based on direction
    function showSwipeHint(x, y) {
        var $hints = $('.wpmatch-swipe-hint');
        $hints.css('opacity', 0);

        if (y < -80) {
            $('.wpmatch-hint-super').css('opacity', 1);
        } else if (x > 50) {
            $('.wpmatch-hint-like').css('opacity', 1);
        } else if (x < -50) {
            $('.wpmatch-hint-nope').css('opacity', 1);
        }
    }

    // Hide all swipe hints
    function hideSwipeHints() {
        $('.wpmatch-swipe-hint').css('opacity', 0);
    }

    // Swipe card in direction
    function swipeCard(direction) {
        var $activeCard = $('.wpmatch-card:first-child');
        var userId = $activeCard.data('user-id');

        if (!userId) return;

        var action = '';
        var transform = '';

        switch (direction) {
            case 'left':
                action = 'pass';
                transform = 'translateX(-100%) rotate(-30deg)';
                $activeCard.addClass('swiped-left');
                break;
            case 'right':
                action = 'like';
                transform = 'translateX(100%) rotate(30deg)';
                $activeCard.addClass('swiped-right');
                break;
            case 'up':
                action = 'super_like';
                transform = 'translateY(-100%) scale(1.1)';
                $activeCard.addClass('swiped-up');
                break;
        }

        // Animate card exit
        $activeCard.css({
            'transform': transform,
            'opacity': 0
        });

        // Send action to server
        sendSwipeAction(userId, action);

        // Remove card and show next one
        setTimeout(function() {
            $activeCard.remove();
            cards.shift();

            if (cards.length === 0) {
                loadNextCards();
            } else {
                // Re-apply stacking to remaining cards
                $('.wpmatch-card').each(function(index) {
                    var scale = 1 - (index * 0.05);
                    var translateY = index * 10;
                    $(this).css({
                        'transform': 'scale(' + scale + ') translateY(' + translateY + 'px)',
                        'z-index': 10 - index
                    });
                });
            }
        }, 300);

        hideSwipeHints();
    }

    // Send swipe action to server
    function sendSwipeAction(userId, action) {
        $.ajax({
            url: wpmatchSwipe.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpmatch_swipe_action',
                nonce: wpmatchSwipe.nonce,
                user_id: userId,
                swipe_action: action
            },
            success: function(response) {
                if (response.success && response.data.match) {
                    showMatchNotification(response.data.user);
                }
            },
            error: function() {
                // Silently fail - don't interrupt user experience
                console.log('Failed to record swipe action');
            }
        });
    }

    // Show match notification
    function showMatchNotification(userData) {
        var matchHtml = '<div class="wpmatch-match-notification" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 9999; display: flex; align-items: center; justify-content: center;">' +
            '<div style="background: white; padding: 40px; border-radius: 20px; text-align: center; max-width: 400px;">' +
                '<h2 style="color: #e91e63; margin-bottom: 20px;">It\'s a Match! 🎉</h2>' +
                '<img src="' + (userData.photo || wpmatchSwipe.defaultPhoto) + '" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; margin-bottom: 20px;">' +
                '<h3>' + userData.name + '</h3>' +
                '<p style="margin-bottom: 30px;">You both liked each other!</p>' +
                '<div style="display: flex; gap: 15px; justify-content: center;">' +
                    '<button class="wpmatch-match-message" style="background: #e91e63; color: white; border: none; padding: 12px 24px; border-radius: 25px; cursor: pointer;">Send Message</button>' +
                    '<button class="wpmatch-match-continue" style="background: #f0f0f0; color: #333; border: none; padding: 12px 24px; border-radius: 25px; cursor: pointer;">Keep Swiping</button>' +
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
            window.location.href = wpmatchSwipe.messageUrl + '?user=' + userData.id;
        });
    }

    // Show no more cards state
    function showNoMoreCards() {
        $('.wpmatch-card-stack').html(
            '<div class="wpmatch-no-more-cards">' +
                '<h3>No more profiles!</h3>' +
                '<p>You\'ve seen all available profiles. Check back later for new people, or adjust your preferences to see more matches.</p>' +
                '<button class="wpmatch-reload-btn">Reload</button>' +
            '</div>'
        );
    }

    // Show error message
    function showError(message) {
        $('.wpmatch-card-stack').html(
            '<div class="wpmatch-no-more-cards">' +
                '<h3>Oops!</h3>' +
                '<p>' + message + '</p>' +
                '<button class="wpmatch-reload-btn">Try Again</button>' +
            '</div>'
        );
    }

    // Initialize when page loads
    initSwipeInterface();
});