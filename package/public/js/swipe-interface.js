/**
 * WPMatch Swipe Interface JavaScript
 *
 * Handles swipe gestures, animations, and interactions for the Tinder-style interface.
 *
 * @package WPMatch
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * WPMatch Swipe Controller
     */
    window.WPMatchSwipe = function() {
        // Configuration
        this.config = {
            swipeThreshold: wpmatchSwipe.swipe_threshold || 100,
            rotationFactor: wpmatchSwipe.rotation_factor || 0.2,
            animationDuration: wpmatchSwipe.animation_duration || 300,
            maxRotation: 20,
            swipeOutDistance: 500
        };

        // State management
        this.state = {
            isAnimating: false,
            currentCardIndex: 0,
            swipeHistory: [],
            canUndo: false,
            remainingSuperlikes: 5,
            lastSwipeTime: 0
        };

        // Elements
        this.elements = {
            container: null,
            cardStack: null,
            cards: [],
            actionButtons: {},
            celebration: null,
            loading: null
        };

        // Initialize
        this.init();
    };

    /**
     * Initialize the swipe interface
     */
    WPMatchSwipe.prototype.init = function() {
        this.cacheElements();
        this.setupGestures();
        this.bindEvents();
        this.loadInitialCards();
        this.setupKeyboardShortcuts();
    };

    /**
     * Cache DOM elements
     */
    WPMatchSwipe.prototype.cacheElements = function() {
        this.elements.container = $('.wpmatch-swipe-container');
        this.elements.cardStack = $('.wpmatch-card-stack');
        this.elements.cards = $('.wpmatch-card').toArray();
        this.elements.actionButtons = {
            pass: $('.wpmatch-action-pass'),
            like: $('.wpmatch-action-like'),
            superLike: $('.wpmatch-action-super-like'),
            undo: $('.wpmatch-action-undo')
        };
        this.elements.celebration = $('.wpmatch-match-celebration');
        this.elements.loading = $('.wpmatch-loading-state');
    };

    /**
     * Setup touch/mouse gestures using native events
     */
    WPMatchSwipe.prototype.setupGestures = function() {
        var self = this;

        this.elements.cards.forEach(function(card, index) {
            if (index > 0) return; // Only setup gestures for top card

            var startX = 0, startY = 0, deltaX = 0, deltaY = 0;
            var isDragging = false, startTime = 0;

            // Mouse events
            card.addEventListener('mousedown', function(e) {
                if (self.state.isAnimating) return;
                startGesture(e.clientX, e.clientY);
                e.preventDefault();
            });

            document.addEventListener('mousemove', function(e) {
                if (!isDragging || self.state.isAnimating) return;
                moveGesture(e.clientX, e.clientY);
                e.preventDefault();
            });

            document.addEventListener('mouseup', function(e) {
                if (!isDragging || self.state.isAnimating) return;
                endGesture();
            });

            // Touch events
            card.addEventListener('touchstart', function(e) {
                if (self.state.isAnimating || e.touches.length > 1) return;
                var touch = e.touches[0];
                startGesture(touch.clientX, touch.clientY);
                e.preventDefault();
            });

            card.addEventListener('touchmove', function(e) {
                if (!isDragging || self.state.isAnimating || e.touches.length > 1) return;
                var touch = e.touches[0];
                moveGesture(touch.clientX, touch.clientY);
                e.preventDefault();
            });

            card.addEventListener('touchend', function(e) {
                if (!isDragging || self.state.isAnimating) return;
                endGesture();
                e.preventDefault();
            });

            function startGesture(x, y) {
                startX = x;
                startY = y;
                isDragging = true;
                startTime = Date.now();
                card.style.transition = 'none';
            }

            function moveGesture(x, y) {
                deltaX = x - startX;
                deltaY = y - startY;
                var rotation = deltaX * self.config.rotationFactor;

                // Limit rotation
                rotation = Math.max(-self.config.maxRotation, Math.min(self.config.maxRotation, rotation));

                // Apply transform
                self.transformCard(card, deltaX, deltaY, rotation);

                // Update visual feedback
                self.updateSwipeIndicators(card, deltaX, deltaY);
            }

            function endGesture() {
                isDragging = false;
                var endTime = Date.now();
                var duration = endTime - startTime;
                var velocityX = duration > 0 ? deltaX / duration : 0;

                // Determine action based on position and velocity
                var action = self.determineAction(deltaX, deltaY, velocityX);

                if (action) {
                    self.performSwipe(card, action);
                } else {
                    // Snap back to center
                    self.resetCard(card);
                }
            }
        });
    };

    /**
     * Transform card position and rotation
     */
    WPMatchSwipe.prototype.transformCard = function(card, x, y, rotation) {
        var transform = 'translate3d(' + x + 'px, ' + y + 'px, 0) rotate(' + rotation + 'deg)';
        card.style.transform = transform;
        card.style.webkitTransform = transform;
    };

    /**
     * Update swipe indicators based on drag position
     */
    WPMatchSwipe.prototype.updateSwipeIndicators = function(card, deltaX, deltaY) {
        var $card = $(card);
        var threshold = this.config.swipeThreshold;

        // Remove all indicator classes
        $card.find('.wpmatch-swipe-indicator').removeClass('visible');

        // Show appropriate indicator
        if (deltaX > threshold) {
            $card.find('.like-indicator').addClass('visible');
            var opacity = Math.min(1, (deltaX - threshold) / threshold);
            $card.find('.like-indicator').css('opacity', opacity);
        } else if (deltaX < -threshold) {
            $card.find('.nope-indicator').addClass('visible');
            var opacity = Math.min(1, (-deltaX - threshold) / threshold);
            $card.find('.nope-indicator').css('opacity', opacity);
        } else if (deltaY < -threshold / 2) {
            $card.find('.super-indicator').addClass('visible');
            var opacity = Math.min(1, (-deltaY - threshold / 2) / (threshold / 2));
            $card.find('.super-indicator').css('opacity', opacity);
        }
    };

    /**
     * Determine swipe action based on position
     */
    WPMatchSwipe.prototype.determineAction = function(deltaX, deltaY, velocityX) {
        var threshold = this.config.swipeThreshold;

        // Check for super like (swipe up)
        if (deltaY < -threshold / 2 && Math.abs(deltaX) < threshold) {
            return 'super_like';
        }

        // Check for like (swipe right)
        if (deltaX > threshold || velocityX > 0.5) {
            return 'like';
        }

        // Check for pass (swipe left)
        if (deltaX < -threshold || velocityX < -0.5) {
            return 'pass';
        }

        return null;
    };

    /**
     * Perform swipe action
     */
    WPMatchSwipe.prototype.performSwipe = function(card, action) {
        if (this.state.isAnimating) return;

        var self = this;
        this.state.isAnimating = true;

        var $card = $(card);
        var userId = $card.data('user-id');

        // Add animation class
        $card.addClass('swiping-' + action.replace('_', '-'));

        // Determine exit position
        var exitX = 0, exitY = 0, rotation = 0;

        switch (action) {
            case 'like':
                exitX = this.config.swipeOutDistance;
                rotation = this.config.maxRotation;
                break;
            case 'pass':
                exitX = -this.config.swipeOutDistance;
                rotation = -this.config.maxRotation;
                break;
            case 'super_like':
                exitY = -this.config.swipeOutDistance;
                break;
        }

        // Animate card exit
        this.animateCardExit($card, exitX, exitY, rotation, function() {
            // Send swipe to server
            self.sendSwipeToServer(userId, action);

            // Remove card from DOM
            $card.remove();

            // Advance to next card
            self.advanceCard();

            // Update state
            self.state.swipeHistory.push({
                userId: userId,
                action: action,
                timestamp: Date.now()
            });

            // Enable undo button
            self.updateUndoButton(true);

            self.state.isAnimating = false;
        });
    };

    /**
     * Animate card exit
     */
    WPMatchSwipe.prototype.animateCardExit = function($card, x, y, rotation, callback) {
        var duration = this.config.animationDuration;

        $card.css({
            'transition': 'transform ' + duration + 'ms ease-out, opacity ' + duration + 'ms ease-out',
            'transform': 'translate3d(' + x + 'px, ' + y + 'px, 0) rotate(' + rotation + 'deg)',
            'opacity': '0'
        });

        setTimeout(callback, duration);
    };

    /**
     * Reset card to center position
     */
    WPMatchSwipe.prototype.resetCard = function(card) {
        var $card = $(card);

        $card.css({
            'transition': 'transform 200ms ease-out',
            'transform': 'translate3d(0, 0, 0) rotate(0deg)'
        });

        // Hide indicators
        $card.find('.wpmatch-swipe-indicator').removeClass('visible').css('opacity', 0);
    };

    /**
     * Advance to next card
     */
    WPMatchSwipe.prototype.advanceCard = function() {
        this.state.currentCardIndex++;

        // Re-cache cards
        this.elements.cards = $('.wpmatch-card').toArray();

        if (this.elements.cards.length > 0) {
            // Setup gestures for new top card
            this.setupGestures();

            // Load more cards if running low
            if (this.elements.cards.length < 3) {
                this.loadMoreCards();
            }
        } else {
            // No more cards
            this.showEmptyState();
        }
    };

    /**
     * Send swipe to server
     */
    WPMatchSwipe.prototype.sendSwipeToServer = function(userId, action) {
        var self = this;

        $.ajax({
            url: wpmatchSwipe.ajax_url,
            type: 'POST',
            data: {
                action: 'wpmatch_process_swipe',
                nonce: wpmatchSwipe.nonce,
                target_user_id: userId,
                swipe_type: action
            },
            success: function(response) {
                if (response.success) {
                    if (response.data.is_match) {
                        self.showMatchCelebration(response.data);
                    }

                    // Update super likes count if applicable
                    if (action === 'super_like') {
                        self.state.remainingSuperlikes--;
                        self.updateSuperLikeButton();
                    }
                } else {
                    // Handle error
                    self.showError(response.data.message || wpmatchSwipe.strings.error);
                }
            },
            error: function() {
                self.showError(wpmatchSwipe.strings.error);
            }
        });
    };

    /**
     * Bind button events
     */
    WPMatchSwipe.prototype.bindEvents = function() {
        var self = this;

        // Pass button
        this.elements.actionButtons.pass.on('click', function() {
            if (self.elements.cards.length > 0 && !self.state.isAnimating) {
                self.performSwipe(self.elements.cards[0], 'pass');
            }
        });

        // Like button
        this.elements.actionButtons.like.on('click', function() {
            if (self.elements.cards.length > 0 && !self.state.isAnimating) {
                self.performSwipe(self.elements.cards[0], 'like');
            }
        });

        // Super like button
        this.elements.actionButtons.superLike.on('click', function() {
            if (self.elements.cards.length > 0 && !self.state.isAnimating && self.state.remainingSuperlikes > 0) {
                self.performSwipe(self.elements.cards[0], 'super_like');
            }
        });

        // Undo button
        this.elements.actionButtons.undo.on('click', function() {
            if (!$(this).prop('disabled')) {
                self.performUndo();
            }
        });

        // Photo navigation
        $(document).on('click', '.wpmatch-card-photo-wrapper', function(e) {
            var $card = $(this).closest('.wpmatch-card');
            self.navigatePhotos($card, e.pageX > $(this).width() / 2 ? 'next' : 'prev');
        });

        // Match celebration buttons
        $('.btn-keep-swiping').on('click', function() {
            self.hideMatchCelebration();
        });

        $('.btn-send-message').on('click', function() {
            var matchId = $(this).data('match-id');
            window.location.href = '/messages?match=' + matchId;
        });
    };

    /**
     * Setup keyboard shortcuts
     */
    WPMatchSwipe.prototype.setupKeyboardShortcuts = function() {
        var self = this;

        $(document).on('keydown', function(e) {
            if (self.state.isAnimating || self.elements.cards.length === 0) return;

            switch (e.key) {
                case 'ArrowLeft':
                case 'a':
                case 'A':
                    e.preventDefault();
                    self.performSwipe(self.elements.cards[0], 'pass');
                    break;
                case 'ArrowRight':
                case 'd':
                case 'D':
                    e.preventDefault();
                    self.performSwipe(self.elements.cards[0], 'like');
                    break;
                case 'ArrowUp':
                case 'w':
                case 'W':
                    e.preventDefault();
                    if (self.state.remainingSuperlikes > 0) {
                        self.performSwipe(self.elements.cards[0], 'super_like');
                    }
                    break;
                case 'z':
                case 'Z':
                case 'Backspace':
                    e.preventDefault();
                    if (self.state.canUndo) {
                        self.performUndo();
                    }
                    break;
            }
        });
    };

    /**
     * Perform undo action
     */
    WPMatchSwipe.prototype.performUndo = function() {
        if (this.state.swipeHistory.length === 0) return;

        var self = this;
        var lastSwipe = this.state.swipeHistory.pop();

        $.ajax({
            url: wpmatchSwipe.ajax_url,
            type: 'POST',
            data: {
                action: 'wpmatch_undo_swipe',
                nonce: wpmatchSwipe.nonce,
                user_id: lastSwipe.userId
            },
            success: function(response) {
                if (response.success) {
                    // Reload the card
                    self.reloadCard(lastSwipe.userId);

                    // Update undo button
                    self.updateUndoButton(self.state.swipeHistory.length > 0);

                    // Restore super like if applicable
                    if (lastSwipe.action === 'super_like') {
                        self.state.remainingSuperlikes++;
                        self.updateSuperLikeButton();
                    }
                }
            }
        });
    };

    /**
     * Show match celebration
     */
    WPMatchSwipe.prototype.showMatchCelebration = function(matchData) {
        var $celebration = this.elements.celebration;

        // Update content
        $('.celebration-message').text(matchData.message || 'You and ' + matchData.matched_user_name + ' liked each other!');
        $('.btn-send-message').data('match-id', matchData.match_id);

        // Show celebration with animation
        $celebration.fadeIn(300);
        $('.celebration-hearts .heart').each(function(index) {
            $(this).css('animation-delay', (index * 0.2) + 's');
        });
    };

    /**
     * Hide match celebration
     */
    WPMatchSwipe.prototype.hideMatchCelebration = function() {
        this.elements.celebration.fadeOut(300);
    };

    /**
     * Load more cards via AJAX
     */
    WPMatchSwipe.prototype.loadMoreCards = function() {
        var self = this;

        $.ajax({
            url: wpmatchSwipe.ajax_url,
            type: 'POST',
            data: {
                action: 'wpmatch_load_more_cards',
                nonce: wpmatchSwipe.nonce,
                offset: this.state.currentCardIndex
            },
            success: function(response) {
                if (response.success && response.data.cards) {
                    self.appendCards(response.data.cards);
                }
            }
        });
    };

    /**
     * Append new cards to stack
     */
    WPMatchSwipe.prototype.appendCards = function(cardsHtml) {
        this.elements.cardStack.append(cardsHtml);
        this.cacheElements();
    };

    /**
     * Show empty state
     */
    WPMatchSwipe.prototype.showEmptyState = function() {
        this.elements.cardStack.html($('.wpmatch-empty-state').clone().show());
    };

    /**
     * Update undo button state
     */
    WPMatchSwipe.prototype.updateUndoButton = function(enabled) {
        this.state.canUndo = enabled;
        this.elements.actionButtons.undo.prop('disabled', !enabled);
    };

    /**
     * Update super like button
     */
    WPMatchSwipe.prototype.updateSuperLikeButton = function() {
        if (this.state.remainingSuperlikes <= 0) {
            this.elements.actionButtons.superLike.addClass('disabled');
        } else {
            this.elements.actionButtons.superLike.removeClass('disabled');
        }
    };

    /**
     * Navigate photos in card
     */
    WPMatchSwipe.prototype.navigatePhotos = function($card, direction) {
        // Implementation for photo navigation
        var $dots = $card.find('.photo-dot');
        var $activeDot = $dots.filter('.active');
        var currentIndex = $activeDot.data('photo-index') || 0;
        var newIndex = direction === 'next' ? currentIndex + 1 : currentIndex - 1;

        if (newIndex >= 0 && newIndex < $dots.length) {
            $dots.removeClass('active');
            $dots.eq(newIndex).addClass('active');

            // Update photo (would need additional implementation)
        }
    };

    /**
     * Show error message
     */
    WPMatchSwipe.prototype.showError = function(message) {
        // Simple alert for now, could be replaced with better UI
        alert(message);
    };

    /**
     * Load initial cards
     */
    WPMatchSwipe.prototype.loadInitialCards = function() {
        // Cards are already loaded from PHP, just ensure they're ready
        if (this.elements.cards.length === 0) {
            this.showEmptyState();
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        if ($('.wpmatch-swipe-container').length > 0) {
            window.wpmatchSwipeInstance = new WPMatchSwipe();
        }
    });

})(jQuery);