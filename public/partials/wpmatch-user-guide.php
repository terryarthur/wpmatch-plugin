<?php
/**
 * User guide template for dating site members
 *
 * @package WPMatch
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wpmatch-public">
	<div class="wpmatch-user-guide">
		<header class="guide-header">
			<h1><?php esc_html_e( '💕 How to Use Our Dating Site', 'wpmatch' ); ?></h1>
			<p><?php esc_html_e( 'Welcome to our dating community! Here\'s everything you need to know to find your perfect match.', 'wpmatch' ); ?></p>
		</header>

		<div class="guide-tabs">
			<nav class="guide-tab-nav">
				<button type="button" class="guide-tab-button active" data-tab="getting-started"><?php esc_html_e( 'Getting Started', 'wpmatch' ); ?></button>
				<button type="button" class="guide-tab-button" data-tab="profile-tips"><?php esc_html_e( 'Profile Tips', 'wpmatch' ); ?></button>
				<button type="button" class="guide-tab-button" data-tab="swiping"><?php esc_html_e( 'Swiping Guide', 'wpmatch' ); ?></button>
				<button type="button" class="guide-tab-button" data-tab="matches"><?php esc_html_e( 'Your Matches', 'wpmatch' ); ?></button>
				<button type="button" class="guide-tab-button" data-tab="safety"><?php esc_html_e( 'Stay Safe', 'wpmatch' ); ?></button>
			</nav>

			<!-- Getting Started Tab -->
			<div class="guide-tab-content active" id="getting-started">
				<div class="guide-section">
					<h2><?php esc_html_e( '🚀 Getting Started', 'wpmatch' ); ?></h2>
					<p><?php esc_html_e( 'Follow these simple steps to start your dating journey:', 'wpmatch' ); ?></p>

					<div class="guide-steps">
						<div class="guide-step">
							<span class="step-icon">1️⃣</span>
							<div class="step-content">
								<h3><?php esc_html_e( 'Complete Your Profile', 'wpmatch' ); ?></h3>
								<p><?php esc_html_e( 'A complete profile gets 10x more matches! Fill out your age, location, interests, and write a compelling "About Me" section.', 'wpmatch' ); ?></p>
								<?php if ( is_user_logged_in() ) : ?>
									<a href="/profile/edit/" class="guide-button"><?php esc_html_e( 'Edit My Profile', 'wpmatch' ); ?></a>
								<?php endif; ?>
							</div>
						</div>

						<div class="guide-step">
							<span class="step-icon">2️⃣</span>
							<div class="step-content">
								<h3><?php esc_html_e( 'Upload Great Photos', 'wpmatch' ); ?></h3>
								<p><?php esc_html_e( 'Photos are everything! Upload at least 3-5 high-quality photos that show your personality. Include a clear face shot and some full-body photos.', 'wpmatch' ); ?></p>
							</div>
						</div>

						<div class="guide-step">
							<span class="step-icon">3️⃣</span>
							<div class="step-content">
								<h3><?php esc_html_e( 'Set Your Preferences', 'wpmatch' ); ?></h3>
								<p><?php esc_html_e( 'Tell us who you\'re looking for! Set your preferred age range, distance, and other important criteria.', 'wpmatch' ); ?></p>
							</div>
						</div>

						<div class="guide-step">
							<span class="step-icon">4️⃣</span>
							<div class="step-content">
								<h3><?php esc_html_e( 'Start Swiping!', 'wpmatch' ); ?></h3>
								<p><?php esc_html_e( 'Browse potential matches and swipe right on people you like. When someone you liked also likes you back, it\'s a match!', 'wpmatch' ); ?></p>
								<?php if ( is_user_logged_in() ) : ?>
									<a href="/browse/" class="guide-button"><?php esc_html_e( 'Start Browsing', 'wpmatch' ); ?></a>
								<?php endif; ?>
							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- Profile Tips Tab -->
			<div class="guide-tab-content" id="profile-tips">
				<div class="guide-section">
					<h2><?php esc_html_e( '✨ Profile Tips That Work', 'wpmatch' ); ?></h2>

					<h3><?php esc_html_e( '📸 Photo Guidelines', 'wpmatch' ); ?></h3>
					<div class="tips-grid">
						<div class="tip-card">
							<h4><?php esc_html_e( '✅ DO', 'wpmatch' ); ?></h4>
							<ul>
								<li><?php esc_html_e( 'Use recent photos (within 1 year)', 'wpmatch' ); ?></li>
								<li><?php esc_html_e( 'Show your face clearly', 'wpmatch' ); ?></li>
								<li><?php esc_html_e( 'Smile genuinely', 'wpmatch' ); ?></li>
								<li><?php esc_html_e( 'Include variety (close-up, full body, activity)', 'wpmatch' ); ?></li>
								<li><?php esc_html_e( 'Use good lighting', 'wpmatch' ); ?></li>
							</ul>
						</div>

						<div class="tip-card">
							<h4><?php esc_html_e( '❌ DON\'T', 'wpmatch' ); ?></h4>
							<ul>
								<li><?php esc_html_e( 'Use group photos as your main pic', 'wpmatch' ); ?></li>
								<li><?php esc_html_e( 'Hide behind sunglasses in every photo', 'wpmatch' ); ?></li>
								<li><?php esc_html_e( 'Use heavily filtered photos', 'wpmatch' ); ?></li>
								<li><?php esc_html_e( 'Include your ex in photos', 'wpmatch' ); ?></li>
								<li><?php esc_html_e( 'Use blurry or pixelated images', 'wpmatch' ); ?></li>
							</ul>
						</div>
					</div>

					<h3><?php esc_html_e( '📝 Bio Writing Tips', 'wpmatch' ); ?></h3>
					<div class="bio-tips">
						<div class="bio-example">
							<h4><?php esc_html_e( '💡 Good Bio Example:', 'wpmatch' ); ?></h4>
							<blockquote>
								<?php esc_html_e( '"Adventure seeker who loves hiking and trying new restaurants. Professional chef by day, amateur painter by night. Looking for someone to share spontaneous weekend trips and deep conversations over coffee. Dog lover (my golden retriever Max approves all dates first!) 🐕"', 'wpmatch' ); ?>
							</blockquote>
						</div>

						<div class="bio-tips-list">
							<h4><?php esc_html_e( 'What makes this work:', 'wpmatch' ); ?></h4>
							<ul>
								<li><?php esc_html_e( 'Shows personality and hobbies', 'wpmatch' ); ?></li>
								<li><?php esc_html_e( 'Mentions what they\'re looking for', 'wpmatch' ); ?></li>
								<li><?php esc_html_e( 'Adds humor with the dog comment', 'wpmatch' ); ?></li>
								<li><?php esc_html_e( 'Gives conversation starters', 'wpmatch' ); ?></li>
								<li><?php esc_html_e( 'Positive and upbeat tone', 'wpmatch' ); ?></li>
							</ul>
						</div>
					</div>
				</div>
			</div>

			<!-- Swiping Guide Tab -->
			<div class="guide-tab-content" id="swiping">
				<div class="guide-section">
					<h2><?php esc_html_e( '👆 Master the Art of Swiping', 'wpmatch' ); ?></h2>

					<div class="swiping-actions">
						<div class="swipe-action">
							<span class="action-icon">👈</span>
							<h3><?php esc_html_e( 'Swipe Left = Pass', 'wpmatch' ); ?></h3>
							<p><?php esc_html_e( 'Not interested? Swipe left or tap the ❌ button. They won\'t know you passed on them.', 'wpmatch' ); ?></p>
						</div>

						<div class="swipe-action">
							<span class="action-icon">👉</span>
							<h3><?php esc_html_e( 'Swipe Right = Like', 'wpmatch' ); ?></h3>
							<p><?php esc_html_e( 'Interested? Swipe right or tap the ❤️ button. If they like you back, it\'s a match!', 'wpmatch' ); ?></p>
						</div>

						<div class="swipe-action">
							<span class="action-icon">⭐</span>
							<h3><?php esc_html_e( 'Super Like', 'wpmatch' ); ?></h3>
							<p><?php esc_html_e( 'Really interested? Use a Super Like! This shows them you\'re extra interested before they decide on you.', 'wpmatch' ); ?></p>
						</div>
					</div>

					<div class="swiping-tips">
						<h3><?php esc_html_e( '💡 Swiping Strategy Tips', 'wpmatch' ); ?></h3>
						<ul>
							<li><?php esc_html_e( 'Take time to read their profile, not just photos', 'wpmatch' ); ?></li>
							<li><?php esc_html_e( 'Don\'t swipe right on everyone - it hurts your match quality', 'wpmatch' ); ?></li>
							<li><?php esc_html_e( 'Save Super Likes for profiles you\'re genuinely excited about', 'wpmatch' ); ?></li>
							<li><?php esc_html_e( 'Look for common interests and values, not just looks', 'wpmatch' ); ?></li>
							<li><?php esc_html_e( 'Check their "Looking For" section to see if you\'re compatible', 'wpmatch' ); ?></li>
						</ul>
					</div>

					<div class="daily-limits">
						<h3><?php esc_html_e( '⏰ Daily Limits', 'wpmatch' ); ?></h3>
						<p><?php esc_html_e( 'Free members get limited swipes per day to encourage thoughtful choices. Premium members get unlimited swipes!', 'wpmatch' ); ?></p>
					</div>
				</div>
			</div>

			<!-- Matches Tab -->
			<div class="guide-tab-content" id="matches">
				<div class="guide-section">
					<h2><?php esc_html_e( '💕 Understanding Your Matches', 'wpmatch' ); ?></h2>

					<h3><?php esc_html_e( 'What is a Match?', 'wpmatch' ); ?></h3>
					<p><?php esc_html_e( 'A match happens when two people like each other! When you swipe right on someone and they also swipe right on you (or already have), you both get notified that it\'s a match.', 'wpmatch' ); ?></p>

					<h3><?php esc_html_e( 'What Happens After a Match?', 'wpmatch' ); ?></h3>
					<div class="match-steps">
						<div class="match-step">
							<span class="step-number">1</span>
							<div class="step-content">
								<h4><?php esc_html_e( 'You Both Get Notified', 'wpmatch' ); ?></h4>
								<p><?php esc_html_e( 'Both users receive a notification about the new match.', 'wpmatch' ); ?></p>
							</div>
						</div>

						<div class="match-step">
							<span class="step-number">2</span>
							<div class="step-content">
								<h4><?php esc_html_e( 'Start a Conversation', 'wpmatch' ); ?></h4>
								<p><?php esc_html_e( 'Either person can send the first message. Don\'t wait too long!', 'wpmatch' ); ?></p>
							</div>
						</div>

						<div class="match-step">
							<span class="step-number">3</span>
							<div class="step-content">
								<h4><?php esc_html_e( 'Get to Know Each Other', 'wpmatch' ); ?></h4>
								<p><?php esc_html_e( 'Chat, find common interests, and see if there\'s a real connection.', 'wpmatch' ); ?></p>
							</div>
						</div>

						<div class="match-step">
							<span class="step-number">4</span>
							<div class="step-content">
								<h4><?php esc_html_e( 'Take It Offline', 'wpmatch' ); ?></h4>
								<p><?php esc_html_e( 'When you\'re both comfortable, suggest meeting in person!', 'wpmatch' ); ?></p>
							</div>
						</div>
					</div>

					<?php if ( is_user_logged_in() ) : ?>
						<div class="view-matches">
							<a href="/matches/" class="guide-button"><?php esc_html_e( 'View My Matches', 'wpmatch' ); ?></a>
						</div>
					<?php endif; ?>

					<h3><?php esc_html_e( '💬 Starting Great Conversations', 'wpmatch' ); ?></h3>
					<div class="conversation-tips">
						<div class="good-openers">
							<h4><?php esc_html_e( '✅ Good Conversation Starters:', 'wpmatch' ); ?></h4>
							<ul>
								<li><?php esc_html_e( '"I noticed you love hiking! What\'s your favorite trail?"', 'wpmatch' ); ?></li>
								<li><?php esc_html_e( '"Your dog is adorable! What\'s their name?"', 'wpmatch' ); ?></li>
								<li><?php esc_html_e( '"I see you\'re into photography. Did you take that sunset shot?"', 'wpmatch' ); ?></li>
								<li><?php esc_html_e( '"We both love Italian food! Any restaurant recommendations?"', 'wpmatch' ); ?></li>
							</ul>
						</div>

						<div class="bad-openers">
							<h4><?php esc_html_e( '❌ Avoid These:', 'wpmatch' ); ?></h4>
							<ul>
								<li><?php esc_html_e( '"Hey" or "Hi" (too generic)', 'wpmatch' ); ?></li>
								<li><?php esc_html_e( '"You\'re hot" (too forward)', 'wpmatch' ); ?></li>
								<li><?php esc_html_e( 'Copy-paste messages', 'wpmatch' ); ?></li>
								<li><?php esc_html_e( 'Inappropriate or sexual comments', 'wpmatch' ); ?></li>
							</ul>
						</div>
					</div>
				</div>
			</div>

			<!-- Safety Tab -->
			<div class="guide-tab-content" id="safety">
				<div class="guide-section">
					<h2><?php esc_html_e( '🛡️ Stay Safe While Dating', 'wpmatch' ); ?></h2>
					<p><?php esc_html_e( 'Your safety is our top priority. Here are essential safety tips for online dating:', 'wpmatch' ); ?></p>

					<div class="safety-tips">
						<div class="safety-category">
							<h3><?php esc_html_e( '🔒 Protect Your Personal Information', 'wpmatch' ); ?></h3>
							<ul>
								<li><?php esc_html_e( 'Never share your full name, address, or workplace in your profile', 'wpmatch' ); ?></li>
								<li><?php esc_html_e( 'Use the in-app messaging until you feel comfortable', 'wpmatch' ); ?></li>
								<li><?php esc_html_e( 'Don\'t share financial information or send money', 'wpmatch' ); ?></li>
								<li><?php esc_html_e( 'Be cautious about sharing social media profiles too early', 'wpmatch' ); ?></li>
							</ul>
						</div>

						<div class="safety-category">
							<h3><?php esc_html_e( '🚨 Red Flags to Watch For', 'wpmatch' ); ?></h3>
							<ul>
								<li><?php esc_html_e( 'Asks for money or financial help', 'wpmatch' ); ?></li>
								<li><?php esc_html_e( 'Refuses to talk on the phone or video chat', 'wpmatch' ); ?></li>
								<li><?php esc_html_e( 'Pushes to meet immediately or seems too good to be true', 'wpmatch' ); ?></li>
								<li><?php esc_html_e( 'Gets angry when you set boundaries', 'wpmatch' ); ?></li>
								<li><?php esc_html_e( 'Stories don\'t add up or seem inconsistent', 'wpmatch' ); ?></li>
							</ul>
						</div>

						<div class="safety-category">
							<h3><?php esc_html_e( '☕ First Date Safety', 'wpmatch' ); ?></h3>
							<ul>
								<li><?php esc_html_e( 'Meet in a public place for the first few dates', 'wpmatch' ); ?></li>
								<li><?php esc_html_e( 'Tell a friend where you\'re going and when to expect you back', 'wpmatch' ); ?></li>
								<li><?php esc_html_e( 'Drive yourself or use your own transportation', 'wpmatch' ); ?></li>
								<li><?php esc_html_e( 'Trust your instincts - if something feels off, leave', 'wpmatch' ); ?></li>
								<li><?php esc_html_e( 'Don\'t feel pressured to drink alcohol', 'wpmatch' ); ?></li>
							</ul>
						</div>

						<div class="safety-category">
							<h3><?php esc_html_e( '🚫 Report & Block Features', 'wpmatch' ); ?></h3>
							<p><?php esc_html_e( 'We have zero tolerance for inappropriate behavior. Use these features to stay safe:', 'wpmatch' ); ?></p>
							<ul>
								<li><?php esc_html_e( 'Block users who make you uncomfortable', 'wpmatch' ); ?></li>
								<li><?php esc_html_e( 'Report suspicious or inappropriate behavior', 'wpmatch' ); ?></li>
								<li><?php esc_html_e( 'Our moderation team reviews all reports quickly', 'wpmatch' ); ?></li>
							</ul>
						</div>
					</div>

					<div class="emergency-resources">
						<h3><?php esc_html_e( '🆘 Emergency Resources', 'wpmatch' ); ?></h3>
						<p><?php esc_html_e( 'If you ever feel unsafe:', 'wpmatch' ); ?></p>
						<ul>
							<li><?php esc_html_e( 'Call emergency services (911 in US)', 'wpmatch' ); ?></li>
							<li><?php esc_html_e( 'Contact local authorities', 'wpmatch' ); ?></li>
							<li><?php esc_html_e( 'Reach out to trusted friends or family', 'wpmatch' ); ?></li>
						</ul>
					</div>
				</div>
			</div>
		</div>

		<footer class="guide-footer">
			<p><?php esc_html_e( 'Have questions? Contact our support team for help with your dating journey!', 'wpmatch' ); ?></p>
			<?php if ( ! is_user_logged_in() ) : ?>
				<div class="guide-cta">
					<a href="/register/" class="guide-button primary"><?php esc_html_e( 'Join Our Community', 'wpmatch' ); ?></a>
				</div>
			<?php endif; ?>
		</footer>
	</div>
</div>

<style>
.wpmatch-user-guide {
	max-width: 800px;
	margin: 0 auto;
	font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.guide-header {
	text-align: center;
	margin-bottom: 2rem;
	padding: 2rem;
	background: linear-gradient(135deg, #fd297b 0%, #ff655b 100%);
	color: white;
	border-radius: 12px;
}

.guide-header h1 {
	margin: 0 0 1rem 0;
	font-size: 2.5rem;
}

.guide-tabs .guide-tab-nav {
	display: flex;
	border-bottom: 1px solid #eee;
	margin-bottom: 2rem;
	overflow-x: auto;
}

.guide-tab-button {
	padding: 1rem 1.5rem;
	border: none;
	background: none;
	cursor: pointer;
	border-bottom: 2px solid transparent;
	transition: all 0.3s ease;
	white-space: nowrap;
}

.guide-tab-button.active {
	border-bottom-color: #fd297b;
	color: #fd297b;
	font-weight: 600;
}

.guide-tab-content {
	display: none;
}

.guide-tab-content.active {
	display: block;
}

.guide-steps, .match-steps {
	display: grid;
	gap: 1.5rem;
	margin: 2rem 0;
}

.guide-step, .match-step {
	display: flex;
	align-items: flex-start;
	gap: 1rem;
	padding: 1.5rem;
	background: #f8f9fa;
	border-radius: 12px;
	border-left: 4px solid #fd297b;
}

.step-icon {
	font-size: 2rem;
	line-height: 1;
}

.step-number {
	display: flex;
	align-items: center;
	justify-content: center;
	width: 2.5rem;
	height: 2.5rem;
	background: #fd297b;
	color: white;
	border-radius: 50%;
	font-weight: bold;
	flex-shrink: 0;
}

.guide-button {
	display: inline-block;
	padding: 0.75rem 1.5rem;
	background: #fd297b;
	color: white;
	text-decoration: none;
	border-radius: 8px;
	font-weight: 600;
	margin-top: 1rem;
}

.tips-grid {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 2rem;
	margin: 1.5rem 0;
}

.tip-card {
	padding: 1.5rem;
	border: 1px solid #ddd;
	border-radius: 12px;
}

.tip-card h4 {
	margin-top: 0;
	color: #333;
}

.bio-example {
	background: #f0f8ff;
	padding: 1.5rem;
	border-radius: 12px;
	margin: 1rem 0;
}

.bio-example blockquote {
	margin: 0;
	font-style: italic;
	font-size: 1.1rem;
	line-height: 1.6;
}

.swiping-actions {
	display: grid;
	gap: 1.5rem;
	margin: 2rem 0;
}

.swipe-action {
	display: flex;
	align-items: center;
	gap: 1rem;
	padding: 1.5rem;
	background: white;
	border: 2px solid #f0f0f0;
	border-radius: 12px;
}

.action-icon {
	font-size: 3rem;
}

.safety-tips {
	display: grid;
	gap: 2rem;
	margin: 2rem 0;
}

.safety-category {
	padding: 1.5rem;
	background: #fff3cd;
	border-left: 4px solid #ffc107;
	border-radius: 8px;
}

.emergency-resources {
	background: #f8d7da;
	border: 1px solid #f5c6cb;
	border-radius: 8px;
	padding: 1.5rem;
	margin-top: 2rem;
}

.guide-footer {
	text-align: center;
	margin-top: 3rem;
	padding: 2rem;
	background: #f8f9fa;
	border-radius: 12px;
}

@media (max-width: 768px) {
	.tips-grid {
		grid-template-columns: 1fr;
	}

	.guide-tab-nav {
		flex-wrap: wrap;
	}

	.guide-header h1 {
		font-size: 2rem;
	}
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
	// Tab functionality
	const tabButtons = document.querySelectorAll('.guide-tab-button');
	const tabContents = document.querySelectorAll('.guide-tab-content');

	tabButtons.forEach(button => {
		button.addEventListener('click', function() {
			const targetTab = this.getAttribute('data-tab');

			// Remove active class from all buttons and contents
			tabButtons.forEach(btn => btn.classList.remove('active'));
			tabContents.forEach(content => content.classList.remove('active'));

			// Add active class to clicked button and corresponding content
			this.classList.add('active');
			document.getElementById(targetTab).classList.add('active');
		});
	});
});
</script>