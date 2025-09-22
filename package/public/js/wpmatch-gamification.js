/**
 * WPMatch Gamification JavaScript
 *
 * @package WPMatch
 * @since 1.6.0
 */

(function($) {
    'use strict';

    /**
     * Gamification object
     */
    const WPMatchGamification = {

        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.loadDashboard();
            this.startPeriodicUpdates();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            // Leaderboard tabs
            $(document).on('click', '.leaderboard-tab', this.switchLeaderboardTab);

            // Reward filters
            $(document).on('click', '.filter-btn', this.filterRewards);

            // Claim reward
            $(document).on('click', '.claim-reward-btn', this.claimReward);

            // View all achievements
            $(document).on('click', '.view-all-achievements', this.viewAllAchievements);

            // Refresh data
            $(document).on('click', '.refresh-gamification', this.refreshDashboard);
        },

        /**
         * Load gamification dashboard
         */
        loadDashboard: function() {
            const $dashboard = $('.wpmatch-gamification-dashboard');
            if ($dashboard.length === 0) return;

            this.showLoading($dashboard);

            // Load different sections in parallel
            Promise.all([
                this.loadUserProgress(),
                this.loadAchievements(),
                this.loadDailyChallenges(),
                this.loadLeaderboard(),
                this.loadRewards(),
                this.loadStreaks()
            ]).then(() => {
                this.hideLoading($dashboard);
                this.initializeAnimations();
            }).catch((error) => {
                console.error('Error loading gamification dashboard:', error);
                this.hideLoading($dashboard);
                this.showError($dashboard, 'Failed to load gamification data');
            });
        },

        /**
         * Load user progress
         */
        loadUserProgress: function() {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: wpmatch_gamification.rest_url + 'wpmatch/v1/gamification/user-progress',
                    type: 'GET',
                    headers: {
                        'X-WP-Nonce': wpmatch_gamification.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            WPMatchGamification.renderUserProgress(response.data);
                            resolve(response.data);
                        } else {
                            reject(response.message);
                        }
                    },
                    error: function() {
                        reject('Network error');
                    }
                });
            });
        },

        /**
         * Render user progress
         */
        renderUserProgress: function(data) {
            // Update level badge
            $('.user-level-badge .level-text').text(`Level ${data.level}`);

            // Update progress cards
            $('.progress-value.points').text(data.points.toLocaleString());
            $('.progress-value.level').text(data.level);
            $('.progress-value.streak').text(data.login_streak);
            $('.progress-value.achievements').text(data.completed_achievements);

            // Update progress bars
            const levelProgress = ((data.points % 1000) / 1000) * 100;
            $('.progress-overview .progress-fill').css('width', levelProgress + '%');
            $('.progress-text').text(`${data.points % 1000}/1000 points to next level`);
        },

        /**
         * Load achievements
         */
        loadAchievements: function() {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: wpmatch_gamification.rest_url + 'wpmatch/v1/gamification/achievements',
                    type: 'GET',
                    headers: {
                        'X-WP-Nonce': wpmatch_gamification.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            WPMatchGamification.renderAchievements(response.data);
                            resolve(response.data);
                        } else {
                            reject(response.message);
                        }
                    },
                    error: function() {
                        reject('Network error');
                    }
                });
            });
        },

        /**
         * Render achievements
         */
        renderAchievements: function(achievements) {
            const $grid = $('.achievements-grid');
            $grid.empty();

            // Show only first 6 achievements on dashboard
            const displayAchievements = achievements.slice(0, 6);

            displayAchievements.forEach(achievement => {
                const isCompleted = achievement.completed_at !== null;
                const progressPercent = (achievement.current_progress / achievement.target_value) * 100;

                const $card = $(`
                    <div class="achievement-card ${isCompleted ? 'completed' : ''}">
                        <div class="achievement-icon">${achievement.icon || '🏆'}</div>
                        <h4 class="achievement-title">${achievement.title}</h4>
                        <p class="achievement-description">${achievement.description}</p>
                        <div class="achievement-progress">
                            ${achievement.current_progress}/${achievement.target_value}
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: ${Math.min(progressPercent, 100)}%"></div>
                            </div>
                        </div>
                        <div class="achievement-reward">+${achievement.reward_points} points</div>
                    </div>
                `);

                $grid.append($card);
            });
        },

        /**
         * Load daily challenges
         */
        loadDailyChallenges: function() {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: wpmatch_gamification.rest_url + 'wpmatch/v1/gamification/daily-challenges',
                    type: 'GET',
                    headers: {
                        'X-WP-Nonce': wpmatch_gamification.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            WPMatchGamification.renderDailyChallenges(response.data);
                            resolve(response.data);
                        } else {
                            reject(response.message);
                        }
                    },
                    error: function() {
                        reject('Network error');
                    }
                });
            });
        },

        /**
         * Render daily challenges
         */
        renderDailyChallenges: function(challenges) {
            const $grid = $('.challenges-grid');
            $grid.empty();

            challenges.forEach(challenge => {
                const progressPercent = (challenge.current_progress / challenge.target_value) * 100;
                const timeLeft = this.calculateTimeLeft(challenge.expires_at);

                const $card = $(`
                    <div class="challenge-card">
                        <div class="challenge-header">
                            <span class="challenge-type">${challenge.challenge_type}</span>
                            <span class="challenge-timer">${timeLeft}</span>
                        </div>
                        <h4 class="challenge-title">${challenge.title}</h4>
                        <p class="challenge-description">${challenge.description}</p>
                        <div class="challenge-progress">
                            <div>${challenge.current_progress}/${challenge.target_value}</div>
                            <div class="challenge-progress-bar">
                                <div class="challenge-progress-fill" style="width: ${Math.min(progressPercent, 100)}%"></div>
                            </div>
                        </div>
                        <div class="challenge-reward">Reward: +${challenge.reward_points} points</div>
                    </div>
                `);

                $grid.append($card);
            });
        },

        /**
         * Calculate time left
         */
        calculateTimeLeft: function(expiryDate) {
            const now = new Date();
            const expiry = new Date(expiryDate);
            const diff = expiry - now;

            if (diff <= 0) return 'Expired';

            const hours = Math.floor(diff / (1000 * 60 * 60));
            const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));

            return `${hours}h ${minutes}m`;
        },

        /**
         * Load leaderboard
         */
        loadLeaderboard: function(type = 'weekly') {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: wpmatch_gamification.rest_url + 'wpmatch/v1/gamification/leaderboard',
                    type: 'GET',
                    data: { type: type },
                    headers: {
                        'X-WP-Nonce': wpmatch_gamification.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            WPMatchGamification.renderLeaderboard(response.data);
                            resolve(response.data);
                        } else {
                            reject(response.message);
                        }
                    },
                    error: function() {
                        reject('Network error');
                    }
                });
            });
        },

        /**
         * Render leaderboard
         */
        renderLeaderboard: function(leaderboard) {
            const $list = $('.leaderboard-list');
            $list.empty();

            leaderboard.forEach((user, index) => {
                const rank = index + 1;
                const isCurrentUser = user.user_id == wpmatch_gamification.current_user_id;

                const $item = $(`
                    <div class="leaderboard-item ${isCurrentUser ? 'current-user' : ''}">
                        <div class="leaderboard-rank rank-${rank <= 3 ? rank : 'other'}">${rank}</div>
                        <div class="leaderboard-user">
                            <img src="${user.avatar || wpmatch_gamification.default_avatar}" alt="${user.display_name}" class="leaderboard-avatar">
                            <div class="leaderboard-info">
                                <h4>${user.display_name}</h4>
                                <p>Level ${user.level}</p>
                            </div>
                        </div>
                        <div class="leaderboard-score">${user.score.toLocaleString()}</div>
                    </div>
                `);

                $list.append($item);
            });
        },

        /**
         * Switch leaderboard tab
         */
        switchLeaderboardTab: function(e) {
            e.preventDefault();

            const $tab = $(this);
            const type = $tab.data('type');

            // Update active tab
            $('.leaderboard-tab').removeClass('active');
            $tab.addClass('active');

            // Load leaderboard data
            WPMatchGamification.loadLeaderboard(type);
        },

        /**
         * Load rewards
         */
        loadRewards: function() {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: wpmatch_gamification.rest_url + 'wpmatch/v1/gamification/rewards',
                    type: 'GET',
                    headers: {
                        'X-WP-Nonce': wpmatch_gamification.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            WPMatchGamification.renderRewards(response.data);
                            resolve(response.data);
                        } else {
                            reject(response.message);
                        }
                    },
                    error: function() {
                        reject('Network error');
                    }
                });
            });
        },

        /**
         * Render rewards
         */
        renderRewards: function(rewards) {
            const $grid = $('.rewards-grid');
            $grid.empty();

            rewards.forEach(reward => {
                const canClaim = reward.user_points >= reward.cost && !reward.claimed;

                const $card = $(`
                    <div class="reward-card ${reward.claimed ? 'claimed' : ''}">
                        <div class="reward-icon">${reward.icon || '🎁'}</div>
                        <h4 class="reward-title">${reward.title}</h4>
                        <p class="reward-description">${reward.description}</p>
                        <div class="reward-cost">
                            <span>💎</span> ${reward.cost} points
                        </div>
                        <button class="claim-reward-btn"
                                data-reward-id="${reward.reward_id}"
                                ${!canClaim || reward.claimed ? 'disabled' : ''}>
                            ${reward.claimed ? 'Claimed' : 'Claim Reward'}
                        </button>
                    </div>
                `);

                $grid.append($card);
            });
        },

        /**
         * Filter rewards
         */
        filterRewards: function(e) {
            e.preventDefault();

            const $btn = $(this);
            const filter = $btn.data('filter');

            // Update active filter
            $('.filter-btn').removeClass('active');
            $btn.addClass('active');

            // Filter reward cards
            $('.reward-card').each(function() {
                const $card = $(this);
                let show = true;

                switch(filter) {
                    case 'available':
                        show = !$card.hasClass('claimed') && !$card.find('.claim-reward-btn').prop('disabled');
                        break;
                    case 'claimed':
                        show = $card.hasClass('claimed');
                        break;
                    case 'locked':
                        show = $card.find('.claim-reward-btn').prop('disabled') && !$card.hasClass('claimed');
                        break;
                    default:
                        show = true;
                }

                $card.toggle(show);
            });
        },

        /**
         * Claim reward
         */
        claimReward: function(e) {
            e.preventDefault();

            const $btn = $(this);
            const rewardId = $btn.data('reward-id');

            if ($btn.prop('disabled')) return;

            // Show loading state
            const originalText = $btn.text();
            $btn.text('Claiming...').prop('disabled', true);

            $.ajax({
                url: wpmatch_gamification.rest_url + 'wpmatch/v1/gamification/claim-reward',
                type: 'POST',
                data: {
                    reward_id: rewardId
                },
                headers: {
                    'X-WP-Nonce': wpmatch_gamification.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Update UI
                        $btn.text('Claimed').addClass('claimed');
                        $btn.closest('.reward-card').addClass('claimed');

                        // Show success message
                        WPMatchGamification.showNotification('Reward claimed successfully!', 'success');

                        // Update user progress
                        WPMatchGamification.loadUserProgress();
                    } else {
                        $btn.text(originalText).prop('disabled', false);
                        WPMatchGamification.showNotification(response.message || 'Failed to claim reward', 'error');
                    }
                },
                error: function() {
                    $btn.text(originalText).prop('disabled', false);
                    WPMatchGamification.showNotification('Network error. Please try again.', 'error');
                }
            });
        },

        /**
         * Load streaks
         */
        loadStreaks: function() {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: wpmatch_gamification.rest_url + 'wpmatch/v1/gamification/streaks',
                    type: 'GET',
                    headers: {
                        'X-WP-Nonce': wpmatch_gamification.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            WPMatchGamification.renderStreaks(response.data);
                            resolve(response.data);
                        } else {
                            reject(response.message);
                        }
                    },
                    error: function() {
                        reject('Network error');
                    }
                });
            });
        },

        /**
         * Render streaks
         */
        renderStreaks: function(streaks) {
            const $grid = $('.streaks-grid');
            $grid.empty();

            const streakIcons = {
                'login': '📅',
                'message': '💬',
                'match': '💕',
                'profile_view': '👀'
            };

            Object.keys(streaks).forEach(streakType => {
                const streak = streaks[streakType];
                const icon = streakIcons[streakType] || '🔥';

                const $item = $(`
                    <div class="streak-item">
                        <div class="streak-icon">${icon}</div>
                        <div class="streak-title">${this.formatStreakType(streakType)}</div>
                        <div class="streak-count">${streak.current_streak}</div>
                        <div class="streak-status">${streak.is_active ? 'Active' : 'Broken'}</div>
                    </div>
                `);

                $grid.append($item);
            });
        },

        /**
         * Format streak type
         */
        formatStreakType: function(type) {
            return type.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
        },

        /**
         * View all achievements
         */
        viewAllAchievements: function(e) {
            e.preventDefault();

            // Create modal or navigate to achievements page
            // For now, just show more achievements
            $('.achievements-grid').addClass('show-all');
            WPMatchGamification.loadAchievements().then(achievements => {
                WPMatchGamification.renderAchievements(achievements);
            });
        },

        /**
         * Refresh dashboard
         */
        refreshDashboard: function(e) {
            e.preventDefault();
            WPMatchGamification.loadDashboard();
        },

        /**
         * Start periodic updates
         */
        startPeriodicUpdates: function() {
            // Update every 30 seconds
            setInterval(() => {
                this.loadUserProgress();
                this.loadDailyChallenges();
            }, 30000);
        },

        /**
         * Initialize animations
         */
        initializeAnimations: function() {
            // Animate achievement cards
            $('.achievement-card').each(function(index) {
                $(this).css('animation-delay', (index * 0.1) + 's');
            });

            // Animate progress bars
            setTimeout(() => {
                $('.progress-fill, .challenge-progress-fill').each(function() {
                    const width = $(this).css('width');
                    $(this).css('width', '0').animate({width: width}, 1000);
                });
            }, 500);
        },

        /**
         * Show loading state
         */
        showLoading: function($container) {
            $container.addClass('loading');
        },

        /**
         * Hide loading state
         */
        hideLoading: function($container) {
            $container.removeClass('loading');
        },

        /**
         * Show error message
         */
        showError: function($container, message) {
            const $error = $(`
                <div class="gamification-error">
                    <p>${message}</p>
                    <button class="refresh-gamification">Try Again</button>
                </div>
            `);
            $container.append($error);
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
        WPMatchGamification.init();
    });

    /**
     * Trigger achievement check on various actions
     */
    $(document).on('wpmatch:action', function(e, action, data) {
        // Trigger achievement check when user performs actions
        $.ajax({
            url: wpmatch_gamification.rest_url + 'wpmatch/v1/gamification/trigger-achievement',
            type: 'POST',
            data: {
                action: action,
                data: data
            },
            headers: {
                'X-WP-Nonce': wpmatch_gamification.nonce
            },
            success: function(response) {
                if (response.success && response.data.achievements_unlocked) {
                    // Show achievement notifications
                    response.data.achievements_unlocked.forEach(achievement => {
                        WPMatchGamification.showNotification(
                            `🏆 Achievement Unlocked: ${achievement.title}`,
                            'achievement'
                        );
                    });

                    // Refresh user progress
                    WPMatchGamification.loadUserProgress();
                }
            }
        });
    });

})(jQuery);