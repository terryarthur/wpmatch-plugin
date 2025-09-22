<?php
/**
 * WPMatch User Dashboard Template
 *
 * This template provides a comprehensive user dashboard for profile management,
 * settings, matches, and account overview with desktop-optimized responsive design.
 *
 * @package WPMatch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_user_id = get_current_user_id();
if ( ! $current_user_id ) {
	wp_redirect( wp_login_url() );
	exit;
}

// Get user data
$user_data    = get_userdata( $current_user_id );
$user_profile = $this->get_user_profile_data( $current_user_id );
$user_stats   = $this->get_user_stats( $current_user_id );
?>

<div id="wpmatch-dashboard-container" class="wpmatch-dashboard-interface">
	<div class="dashboard-layout">
		<!-- Dashboard Sidebar -->
		<div class="dashboard-sidebar">
			<div class="user-profile-summary">
				<div class="profile-avatar">
					<img id="dashboard-avatar" src="<?php echo esc_url( $user_profile['avatar'] ?? '/wp-content/plugins/wpmatch/public/images/default-avatar.png' ); ?>" alt="<?php echo esc_attr( $user_data->display_name ); ?>">
					<div class="online-status online"></div>
				</div>
				<div class="profile-info">
					<h3><?php echo esc_html( $user_data->display_name ); ?></h3>
					<p class="profile-status"><?php echo esc_html( $user_profile['status'] ?? 'Active member' ); ?></p>
					<div class="profile-completion">
						<div class="completion-bar">
							<div class="completion-fill" style="width: <?php echo esc_attr( $user_profile['completion'] ?? 50 ); ?>%"></div>
						</div>
						<span class="completion-text"><?php echo esc_html( $user_profile['completion'] ?? 50 ); ?>% Complete</span>
					</div>
				</div>
			</div>

			<nav class="dashboard-navigation">
				<ul class="nav-menu">
					<li class="nav-item active" data-section="overview">
						<a href="#overview" class="nav-link">
							<span class="nav-icon">📊</span>
							<span class="nav-text"><?php esc_html_e( 'Overview', 'wpmatch' ); ?></span>
						</a>
					</li>
					<li class="nav-item" data-section="profile">
						<a href="#profile" class="nav-link">
							<span class="nav-icon">👤</span>
							<span class="nav-text"><?php esc_html_e( 'Edit Profile', 'wpmatch' ); ?></span>
						</a>
					</li>
					<li class="nav-item" data-section="photos">
						<a href="#photos" class="nav-link">
							<span class="nav-icon">📷</span>
							<span class="nav-text"><?php esc_html_e( 'My Photos', 'wpmatch' ); ?></span>
						</a>
					</li>
					<li class="nav-item" data-section="matches">
						<a href="#matches" class="nav-link">
							<span class="nav-icon">💘</span>
							<span class="nav-text"><?php esc_html_e( 'Matches', 'wpmatch' ); ?></span>
							<span class="nav-badge"><?php echo esc_html( $user_stats['new_matches'] ?? 0 ); ?></span>
						</a>
					</li>
					<li class="nav-item" data-section="messages">
						<a href="#messages" class="nav-link">
							<span class="nav-icon">💬</span>
							<span class="nav-text"><?php esc_html_e( 'Messages', 'wpmatch' ); ?></span>
							<span class="nav-badge"><?php echo esc_html( $user_stats['unread_messages'] ?? 0 ); ?></span>
						</a>
					</li>
					<li class="nav-item" data-section="preferences">
						<a href="#preferences" class="nav-link">
							<span class="nav-icon">⚙️</span>
							<span class="nav-text"><?php esc_html_e( 'Preferences', 'wpmatch' ); ?></span>
						</a>
					</li>
					<li class="nav-item" data-section="membership">
						<a href="#membership" class="nav-link">
							<span class="nav-icon">⭐</span>
							<span class="nav-text"><?php esc_html_e( 'Membership', 'wpmatch' ); ?></span>
						</a>
					</li>
					<li class="nav-item" data-section="privacy">
						<a href="#privacy" class="nav-link">
							<span class="nav-icon">🔒</span>
							<span class="nav-text"><?php esc_html_e( 'Privacy', 'wpmatch' ); ?></span>
						</a>
					</li>
				</ul>
			</nav>
		</div>

		<!-- Dashboard Content -->
		<div class="dashboard-content">
			<!-- Overview Section -->
			<div id="overview-section" class="dashboard-section active">
				<div class="section-header">
					<h2><?php esc_html_e( 'Dashboard Overview', 'wpmatch' ); ?></h2>
					<p><?php esc_html_e( 'Your WPMatch activity at a glance', 'wpmatch' ); ?></p>
				</div>

				<div class="stats-grid">
					<div class="stat-card">
						<div class="stat-icon">👁️</div>
						<div class="stat-info">
							<h3><?php echo esc_html( $user_stats['profile_views'] ?? 0 ); ?></h3>
							<p><?php esc_html_e( 'Profile Views', 'wpmatch' ); ?></p>
						</div>
					</div>
					<div class="stat-card">
						<div class="stat-icon">💘</div>
						<div class="stat-info">
							<h3><?php echo esc_html( $user_stats['total_matches'] ?? 0 ); ?></h3>
							<p><?php esc_html_e( 'Total Matches', 'wpmatch' ); ?></p>
						</div>
					</div>
					<div class="stat-card">
						<div class="stat-icon">💬</div>
						<div class="stat-info">
							<h3><?php echo esc_html( $user_stats['conversations'] ?? 0 ); ?></h3>
							<p><?php esc_html_e( 'Conversations', 'wpmatch' ); ?></p>
						</div>
					</div>
					<div class="stat-card">
						<div class="stat-icon">⭐</div>
						<div class="stat-info">
							<h3><?php echo esc_html( $user_stats['likes_received'] ?? 0 ); ?></h3>
							<p><?php esc_html_e( 'Likes Received', 'wpmatch' ); ?></p>
						</div>
					</div>
				</div>

				<div class="dashboard-cards">
					<div class="dashboard-card">
						<div class="card-header">
							<h3><?php esc_html_e( 'Recent Activity', 'wpmatch' ); ?></h3>
							<a href="#" class="view-all-link"><?php esc_html_e( 'View All', 'wpmatch' ); ?></a>
						</div>
						<div class="activity-list" id="recent-activity">
							<div class="loading-activity">
								<div class="loading-spinner"></div>
								<p><?php esc_html_e( 'Loading recent activity...', 'wpmatch' ); ?></p>
							</div>
						</div>
					</div>

					<div class="dashboard-card">
						<div class="card-header">
							<h3><?php esc_html_e( 'Profile Completion', 'wpmatch' ); ?></h3>
						</div>
						<div class="completion-tasks" id="completion-tasks">
							<!-- Profile completion tasks will be loaded here -->
						</div>
					</div>

					<div class="dashboard-card">
						<div class="card-header">
							<h3><?php esc_html_e( 'Quick Actions', 'wpmatch' ); ?></h3>
						</div>
						<div class="quick-actions">
							<button class="action-btn primary" data-action="discover">
								<span class="btn-icon">🔍</span>
								<?php esc_html_e( 'Discover Matches', 'wpmatch' ); ?>
							</button>
							<button class="action-btn secondary" data-action="edit-profile">
								<span class="btn-icon">✏️</span>
								<?php esc_html_e( 'Edit Profile', 'wpmatch' ); ?>
							</button>
							<button class="action-btn tertiary" data-action="add-photos">
								<span class="btn-icon">📷</span>
								<?php esc_html_e( 'Add Photos', 'wpmatch' ); ?>
							</button>
						</div>
					</div>
				</div>
			</div>

			<!-- Profile Section -->
			<div id="profile-section" class="dashboard-section">
				<div class="section-header">
					<h2><?php esc_html_e( 'Edit Profile', 'wpmatch' ); ?></h2>
					<p><?php esc_html_e( 'Update your profile information and preferences', 'wpmatch' ); ?></p>
				</div>

				<form id="profile-edit-form" class="profile-form">
					<div class="form-sections">
						<div class="form-section">
							<h3><?php esc_html_e( 'Basic Information', 'wpmatch' ); ?></h3>
							<div class="form-grid">
								<div class="form-group">
									<label for="display_name"><?php esc_html_e( 'Display Name', 'wpmatch' ); ?></label>
									<input type="text" id="display_name" name="display_name" value="<?php echo esc_attr( $user_data->display_name ); ?>" required>
								</div>
								<div class="form-group">
									<label for="age"><?php esc_html_e( 'Age', 'wpmatch' ); ?></label>
									<input type="number" id="age" name="age" value="<?php echo esc_attr( $user_profile['age'] ?? '' ); ?>" min="18" max="99">
								</div>
								<div class="form-group">
									<label for="location"><?php esc_html_e( 'Location', 'wpmatch' ); ?></label>
									<input type="text" id="location" name="location" value="<?php echo esc_attr( $user_profile['location'] ?? '' ); ?>">
								</div>
								<div class="form-group">
									<label for="occupation"><?php esc_html_e( 'Occupation', 'wpmatch' ); ?></label>
									<input type="text" id="occupation" name="occupation" value="<?php echo esc_attr( $user_profile['occupation'] ?? '' ); ?>">
								</div>
							</div>
						</div>

						<div class="form-section">
							<h3><?php esc_html_e( 'About Me', 'wpmatch' ); ?></h3>
							<div class="form-group">
								<label for="about_me"><?php esc_html_e( 'Tell us about yourself', 'wpmatch' ); ?></label>
								<textarea id="about_me" name="about_me" rows="5" maxlength="500" placeholder="<?php esc_attr_e( 'Write something interesting about yourself...', 'wpmatch' ); ?>"><?php echo esc_textarea( $user_profile['about_me'] ?? '' ); ?></textarea>
								<div class="char-counter">
									<span id="about-char-count"><?php echo strlen( $user_profile['about_me'] ?? '' ); ?></span>/500
								</div>
							</div>
						</div>

						<div class="form-section">
							<h3><?php esc_html_e( 'Personal Details', 'wpmatch' ); ?></h3>
							<div class="form-grid">
								<div class="form-group">
									<label for="height"><?php esc_html_e( 'Height', 'wpmatch' ); ?></label>
									<select id="height" name="height">
										<option value=""><?php esc_html_e( 'Select height', 'wpmatch' ); ?></option>
										<?php for ( $i = 48; $i <= 84; $i++ ) : ?>
											<option value="<?php echo esc_attr( $i ); ?>" <?php selected( $user_profile['height'] ?? '', $i ); ?>>
												<?php echo esc_html( floor( $i / 12 ) . "'" . ( $i % 12 ) . '"' ); ?>
											</option>
										<?php endfor; ?>
									</select>
								</div>
								<div class="form-group">
									<label for="education"><?php esc_html_e( 'Education', 'wpmatch' ); ?></label>
									<select id="education" name="education">
										<option value=""><?php esc_html_e( 'Select education level', 'wpmatch' ); ?></option>
										<option value="high_school" <?php selected( $user_profile['education'] ?? '', 'high_school' ); ?>><?php esc_html_e( 'High School', 'wpmatch' ); ?></option>
										<option value="some_college" <?php selected( $user_profile['education'] ?? '', 'some_college' ); ?>><?php esc_html_e( 'Some College', 'wpmatch' ); ?></option>
										<option value="bachelors" <?php selected( $user_profile['education'] ?? '', 'bachelors' ); ?>><?php esc_html_e( 'Bachelor\'s Degree', 'wpmatch' ); ?></option>
										<option value="masters" <?php selected( $user_profile['education'] ?? '', 'masters' ); ?>><?php esc_html_e( 'Master\'s Degree', 'wpmatch' ); ?></option>
										<option value="doctorate" <?php selected( $user_profile['education'] ?? '', 'doctorate' ); ?>><?php esc_html_e( 'Doctorate', 'wpmatch' ); ?></option>
									</select>
								</div>
								<div class="form-group">
									<label for="lifestyle"><?php esc_html_e( 'Lifestyle', 'wpmatch' ); ?></label>
									<select id="lifestyle" name="lifestyle">
										<option value=""><?php esc_html_e( 'Select lifestyle', 'wpmatch' ); ?></option>
										<option value="active" <?php selected( $user_profile['lifestyle'] ?? '', 'active' ); ?>><?php esc_html_e( 'Active', 'wpmatch' ); ?></option>
										<option value="social" <?php selected( $user_profile['lifestyle'] ?? '', 'social' ); ?>><?php esc_html_e( 'Social', 'wpmatch' ); ?></option>
										<option value="quiet" <?php selected( $user_profile['lifestyle'] ?? '', 'quiet' ); ?>><?php esc_html_e( 'Quiet', 'wpmatch' ); ?></option>
										<option value="adventurous" <?php selected( $user_profile['lifestyle'] ?? '', 'adventurous' ); ?>><?php esc_html_e( 'Adventurous', 'wpmatch' ); ?></option>
									</select>
								</div>
								<div class="form-group">
									<label for="looking_for"><?php esc_html_e( 'Looking For', 'wpmatch' ); ?></label>
									<select id="looking_for" name="looking_for">
										<option value=""><?php esc_html_e( 'What are you looking for?', 'wpmatch' ); ?></option>
										<option value="relationship" <?php selected( $user_profile['looking_for'] ?? '', 'relationship' ); ?>><?php esc_html_e( 'Long-term relationship', 'wpmatch' ); ?></option>
										<option value="dating" <?php selected( $user_profile['looking_for'] ?? '', 'dating' ); ?>><?php esc_html_e( 'Dating', 'wpmatch' ); ?></option>
										<option value="friends" <?php selected( $user_profile['looking_for'] ?? '', 'friends' ); ?>><?php esc_html_e( 'Friends', 'wpmatch' ); ?></option>
										<option value="casual" <?php selected( $user_profile['looking_for'] ?? '', 'casual' ); ?>><?php esc_html_e( 'Something casual', 'wpmatch' ); ?></option>
									</select>
								</div>
							</div>
						</div>
					</div>

					<div class="form-actions">
						<button type="submit" class="btn btn-primary">
							<span class="btn-text"><?php esc_html_e( 'Save Changes', 'wpmatch' ); ?></span>
							<span class="btn-loading" style="display: none;">
								<span class="spinner"></span>
								<?php esc_html_e( 'Saving...', 'wpmatch' ); ?>
							</span>
						</button>
						<button type="button" class="btn btn-secondary" id="cancel-edit">
							<?php esc_html_e( 'Cancel', 'wpmatch' ); ?>
						</button>
					</div>

					<?php wp_nonce_field( 'wpmatch_update_profile', 'wpmatch_profile_nonce' ); ?>
				</form>
			</div>

			<!-- Photos Section -->
			<div id="photos-section" class="dashboard-section">
				<div class="section-header">
					<h2><?php esc_html_e( 'My Photos', 'wpmatch' ); ?></h2>
					<p><?php esc_html_e( 'Manage your profile photos and make a great first impression', 'wpmatch' ); ?></p>
				</div>

				<div class="photos-grid" id="photos-grid">
					<!-- Photos will be loaded here -->
					<div class="loading-photos">
						<div class="loading-spinner"></div>
						<p><?php esc_html_e( 'Loading your photos...', 'wpmatch' ); ?></p>
					</div>
				</div>

				<div class="photo-upload-area">
					<input type="file" id="photo-upload-input" multiple accept="image/*" style="display: none;">
					<div class="upload-dropzone" id="upload-dropzone">
						<div class="upload-icon">📷</div>
						<h3><?php esc_html_e( 'Add New Photos', 'wpmatch' ); ?></h3>
						<p><?php esc_html_e( 'Drag and drop photos here or click to browse', 'wpmatch' ); ?></p>
						<button type="button" class="btn btn-primary" id="browse-photos">
							<?php esc_html_e( 'Browse Photos', 'wpmatch' ); ?>
						</button>
					</div>
				</div>
			</div>

			<!-- Other sections will be implemented similarly -->
			<div id="matches-section" class="dashboard-section">
				<div class="section-header">
					<h2><?php esc_html_e( 'My Matches', 'wpmatch' ); ?></h2>
					<p><?php esc_html_e( 'Connect with people who liked your profile', 'wpmatch' ); ?></p>
				</div>
				<div class="coming-soon">
					<div class="coming-soon-icon">💘</div>
					<h3><?php esc_html_e( 'Matches functionality coming soon!', 'wpmatch' ); ?></h3>
					<p><?php esc_html_e( 'We\'re working on bringing you an amazing matching experience.', 'wpmatch' ); ?></p>
				</div>
			</div>

			<div id="messages-section" class="dashboard-section">
				<div class="section-header">
					<h2><?php esc_html_e( 'Messages', 'wpmatch' ); ?></h2>
					<p><?php esc_html_e( 'Connect and chat with your matches', 'wpmatch' ); ?></p>
				</div>
				<div class="redirect-info">
					<div class="redirect-icon">💬</div>
					<h3><?php esc_html_e( 'Ready to start chatting?', 'wpmatch' ); ?></h3>
					<p><?php esc_html_e( 'Access our full messaging experience with all your conversations.', 'wpmatch' ); ?></p>
					<a href="/messages" class="btn btn-primary"><?php esc_html_e( 'Go to Messages', 'wpmatch' ); ?></a>
				</div>
			</div>

			<div id="preferences-section" class="dashboard-section">
				<div class="section-header">
					<h2><?php esc_html_e( 'Preferences', 'wpmatch' ); ?></h2>
					<p><?php esc_html_e( 'Customize your matching and notification preferences', 'wpmatch' ); ?></p>
				</div>
				<div class="coming-soon">
					<div class="coming-soon-icon">⚙️</div>
					<h3><?php esc_html_e( 'Preferences panel coming soon!', 'wpmatch' ); ?></h3>
					<p><?php esc_html_e( 'Fine-tune your experience with advanced preference controls.', 'wpmatch' ); ?></p>
				</div>
			</div>

			<div id="membership-section" class="dashboard-section">
				<div class="section-header">
					<h2><?php esc_html_e( 'Membership', 'wpmatch' ); ?></h2>
					<p><?php esc_html_e( 'Manage your subscription and unlock premium features', 'wpmatch' ); ?></p>
				</div>
				<div class="coming-soon">
					<div class="coming-soon-icon">⭐</div>
					<h3><?php esc_html_e( 'Premium features coming soon!', 'wpmatch' ); ?></h3>
					<p><?php esc_html_e( 'Unlock exclusive features and enhance your dating experience.', 'wpmatch' ); ?></p>
				</div>
			</div>

			<div id="privacy-section" class="dashboard-section">
				<div class="section-header">
					<h2><?php esc_html_e( 'Privacy & Security', 'wpmatch' ); ?></h2>
					<p><?php esc_html_e( 'Control your privacy settings and account security', 'wpmatch' ); ?></p>
				</div>
				<div class="coming-soon">
					<div class="coming-soon-icon">🔒</div>
					<h3><?php esc_html_e( 'Privacy controls coming soon!', 'wpmatch' ); ?></h3>
					<p><?php esc_html_e( 'Advanced privacy and security features for your peace of mind.', 'wpmatch' ); ?></p>
				</div>
			</div>
		</div>
	</div>
</div>

<style>
.wpmatch-dashboard-interface {
	max-width: 1400px;
	margin: 0 auto;
	padding: 20px;
	font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
	background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
	min-height: 100vh;
	box-sizing: border-box;
}

.dashboard-layout {
	display: grid;
	grid-template-columns: 300px 1fr;
	gap: 30px;
	min-height: calc(100vh - 40px);
}

/* Dashboard Sidebar */
.dashboard-sidebar {
	background: white;
	border-radius: 20px;
	padding: 30px;
	box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
	height: fit-content;
	position: sticky;
	top: 20px;
}

.user-profile-summary {
	text-align: center;
	padding-bottom: 30px;
	border-bottom: 1px solid #e2e8f0;
	margin-bottom: 30px;
}

.profile-avatar {
	position: relative;
	width: 80px;
	height: 80px;
	margin: 0 auto 15px auto;
}

.profile-avatar img {
	width: 100%;
	height: 100%;
	border-radius: 50%;
	object-fit: cover;
	border: 3px solid #667eea;
}

.online-status {
	position: absolute;
	bottom: 5px;
	right: 5px;
	width: 16px;
	height: 16px;
	border-radius: 50%;
	border: 3px solid white;
}

.online-status.online {
	background: #48bb78;
}

.profile-info h3 {
	margin: 0 0 5px 0;
	font-size: 20px;
	font-weight: 600;
	color: #1a202c;
}

.profile-status {
	color: #718096;
	font-size: 14px;
	margin: 0 0 15px 0;
}

.profile-completion {
	background: #f7fafc;
	padding: 15px;
	border-radius: 12px;
	margin-top: 15px;
}

.completion-bar {
	height: 8px;
	background: #e2e8f0;
	border-radius: 4px;
	overflow: hidden;
	margin-bottom: 8px;
}

.completion-fill {
	height: 100%;
	background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
	border-radius: 4px;
	transition: width 0.3s ease;
}

.completion-text {
	font-size: 12px;
	font-weight: 600;
	color: #4a5568;
}

.dashboard-navigation {
	margin-top: 30px;
}

.nav-menu {
	list-style: none;
	padding: 0;
	margin: 0;
}

.nav-item {
	margin-bottom: 8px;
}

.nav-link {
	display: flex;
	align-items: center;
	padding: 12px 16px;
	border-radius: 12px;
	text-decoration: none;
	color: #4a5568;
	transition: all 0.2s ease;
	position: relative;
}

.nav-link:hover {
	background: #f7fafc;
	color: #667eea;
}

.nav-item.active .nav-link {
	background: #667eea;
	color: white;
}

.nav-icon {
	font-size: 18px;
	margin-right: 12px;
	width: 20px;
	text-align: center;
}

.nav-text {
	flex: 1;
	font-weight: 500;
}

.nav-badge {
	background: #e53e3e;
	color: white;
	font-size: 11px;
	font-weight: 600;
	padding: 2px 6px;
	border-radius: 10px;
	min-width: 18px;
	text-align: center;
}

.nav-item.active .nav-badge {
	background: rgba(255, 255, 255, 0.2);
}

/* Dashboard Content */
.dashboard-content {
	background: white;
	border-radius: 20px;
	box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
	overflow: hidden;
}

.dashboard-section {
	padding: 40px;
	display: none;
}

.dashboard-section.active {
	display: block;
}

.section-header {
	margin-bottom: 30px;
}

.section-header h2 {
	margin: 0 0 8px 0;
	font-size: 28px;
	font-weight: 700;
	color: #1a202c;
}

.section-header p {
	margin: 0;
	color: #718096;
	font-size: 16px;
}

/* Stats Grid */
.stats-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
	gap: 20px;
	margin-bottom: 40px;
}

.stat-card {
	background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
	color: white;
	padding: 24px;
	border-radius: 16px;
	display: flex;
	align-items: center;
	gap: 16px;
}

.stat-icon {
	font-size: 32px;
	opacity: 0.9;
}

.stat-info h3 {
	margin: 0;
	font-size: 28px;
	font-weight: 700;
}

.stat-info p {
	margin: 4px 0 0 0;
	font-size: 14px;
	opacity: 0.9;
}

/* Dashboard Cards */
.dashboard-cards {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
	gap: 30px;
}

.dashboard-card {
	background: #f8fafc;
	border-radius: 16px;
	padding: 24px;
	border: 1px solid #e2e8f0;
}

.card-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 20px;
}

.card-header h3 {
	margin: 0;
	font-size: 18px;
	font-weight: 600;
	color: #1a202c;
}

.view-all-link {
	color: #667eea;
	text-decoration: none;
	font-size: 14px;
	font-weight: 500;
}

.view-all-link:hover {
	text-decoration: underline;
}

.loading-activity {
	display: flex;
	flex-direction: column;
	align-items: center;
	padding: 40px 20px;
	color: #718096;
}

.loading-spinner {
	width: 24px;
	height: 24px;
	border: 2px solid #e2e8f0;
	border-top: 2px solid #667eea;
	border-radius: 50%;
	animation: spin 1s linear infinite;
	margin-bottom: 12px;
}

@keyframes spin {
	0% { transform: rotate(0deg); }
	100% { transform: rotate(360deg); }
}

.quick-actions {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.action-btn {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 12px 16px;
	border: none;
	border-radius: 12px;
	font-size: 14px;
	font-weight: 500;
	cursor: pointer;
	transition: all 0.2s ease;
	text-decoration: none;
}

.action-btn.primary {
	background: #667eea;
	color: white;
}

.action-btn.primary:hover {
	background: #5a67d8;
	transform: translateY(-1px);
}

.action-btn.secondary {
	background: #f7fafc;
	color: #4a5568;
	border: 1px solid #e2e8f0;
}

.action-btn.secondary:hover {
	background: white;
	border-color: #667eea;
	color: #667eea;
}

.action-btn.tertiary {
	background: white;
	color: #667eea;
	border: 1px solid #667eea;
}

.action-btn.tertiary:hover {
	background: #667eea;
	color: white;
}

.btn-icon {
	font-size: 16px;
}

/* Form Styles */
.profile-form {
	max-width: 800px;
}

.form-sections {
	margin-bottom: 40px;
}

.form-section {
	background: #f8fafc;
	border-radius: 16px;
	padding: 30px;
	margin-bottom: 30px;
	border: 1px solid #e2e8f0;
}

.form-section h3 {
	margin: 0 0 24px 0;
	font-size: 20px;
	font-weight: 600;
	color: #1a202c;
}

.form-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
	gap: 20px;
}

.form-group {
	display: flex;
	flex-direction: column;
}

.form-group label {
	font-weight: 500;
	margin-bottom: 8px;
	color: #374151;
}

.form-group input,
.form-group select,
.form-group textarea {
	padding: 12px 16px;
	border: 1px solid #d1d5db;
	border-radius: 8px;
	font-size: 14px;
	transition: all 0.2s ease;
	background: white;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
	outline: none;
	border-color: #667eea;
	box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.char-counter {
	margin-top: 8px;
	font-size: 12px;
	color: #6b7280;
	text-align: right;
}

.form-actions {
	display: flex;
	gap: 16px;
	padding-top: 30px;
	border-top: 1px solid #e2e8f0;
}

.btn {
	padding: 12px 24px;
	border: none;
	border-radius: 8px;
	font-size: 14px;
	font-weight: 500;
	cursor: pointer;
	transition: all 0.2s ease;
	display: flex;
	align-items: center;
	gap: 8px;
}

.btn-primary {
	background: #667eea;
	color: white;
}

.btn-primary:hover {
	background: #5a67d8;
	transform: translateY(-1px);
}

.btn-secondary {
	background: #f8fafc;
	color: #4a5568;
	border: 1px solid #e2e8f0;
}

.btn-secondary:hover {
	background: white;
	border-color: #667eea;
}

.spinner {
	width: 16px;
	height: 16px;
	border: 2px solid rgba(255, 255, 255, 0.3);
	border-top: 2px solid white;
	border-radius: 50%;
	animation: spin 1s linear infinite;
}

/* Photos Section */
.photos-grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
	gap: 20px;
	margin-bottom: 40px;
}

.loading-photos {
	grid-column: 1 / -1;
	display: flex;
	flex-direction: column;
	align-items: center;
	padding: 60px 20px;
	color: #718096;
}

.photo-upload-area {
	background: #f8fafc;
	border-radius: 16px;
	padding: 40px;
	border: 2px dashed #d1d5db;
	text-align: center;
}

.upload-dropzone {
	transition: all 0.2s ease;
}

.upload-dropzone:hover {
	border-color: #667eea;
	background: #eef2ff;
}

.upload-dropzone.dragover {
	border-color: #667eea;
	background: #eef2ff;
	transform: scale(1.02);
}

.upload-icon {
	font-size: 48px;
	margin-bottom: 16px;
	opacity: 0.7;
}

.upload-dropzone h3 {
	margin: 0 0 8px 0;
	font-size: 20px;
	color: #374151;
}

.upload-dropzone p {
	margin: 0 0 20px 0;
	color: #6b7280;
}

/* Coming Soon & Redirect Styles */
.coming-soon,
.redirect-info {
	text-align: center;
	padding: 80px 40px;
	color: #718096;
}

.coming-soon-icon,
.redirect-icon {
	font-size: 64px;
	margin-bottom: 20px;
	opacity: 0.7;
}

.coming-soon h3,
.redirect-info h3 {
	margin: 0 0 12px 0;
	font-size: 24px;
	color: #374151;
}

.coming-soon p,
.redirect-info p {
	margin: 0 0 24px 0;
	font-size: 16px;
}

/* Responsive Design */
@media (max-width: 1024px) {
	.dashboard-layout {
		grid-template-columns: 250px 1fr;
		gap: 20px;
	}

	.dashboard-sidebar {
		padding: 20px;
	}

	.dashboard-section {
		padding: 30px;
	}
}

@media (max-width: 768px) {
	.wpmatch-dashboard-interface {
		padding: 10px;
	}

	.dashboard-layout {
		grid-template-columns: 1fr;
		gap: 20px;
	}

	.dashboard-sidebar {
		position: static;
		order: 2;
	}

	.dashboard-content {
		order: 1;
	}

	.dashboard-section {
		padding: 20px;
	}

	.section-header h2 {
		font-size: 24px;
	}

	.stats-grid {
		grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
		gap: 15px;
	}

	.stat-card {
		padding: 16px;
		flex-direction: column;
		text-align: center;
	}

	.stat-icon {
		font-size: 24px;
	}

	.stat-info h3 {
		font-size: 24px;
	}

	.dashboard-cards {
		grid-template-columns: 1fr;
		gap: 20px;
	}

	.form-grid {
		grid-template-columns: 1fr;
		gap: 15px;
	}

	.form-actions {
		flex-direction: column;
	}

	.photos-grid {
		grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
		gap: 15px;
	}
}

@media (max-width: 480px) {
	.dashboard-sidebar {
		padding: 15px;
	}

	.dashboard-section {
		padding: 15px;
	}

	.section-header h2 {
		font-size: 20px;
	}

	.stats-grid {
		grid-template-columns: 1fr;
	}

	.form-section {
		padding: 20px;
	}

	.upload-dropzone {
		padding: 30px 20px;
	}

	.upload-icon {
		font-size: 32px;
	}

	.upload-dropzone h3 {
		font-size: 18px;
	}
}
</style>

<script>
class DashboardInterface {
	constructor() {
		this.currentSection = 'overview';
		this.userProfile = {};
		this.init();
	}

	init() {
		this.bindEvents();
		this.loadSection('overview');
		this.loadRecentActivity();
		this.loadCompletionTasks();
		this.loadUserPhotos();
	}

	bindEvents() {
		// Navigation
		document.querySelectorAll('.nav-item').forEach(item => {
			item.addEventListener('click', (e) => {
				e.preventDefault();
				const section = item.dataset.section;
				this.switchSection(section);
			});
		});

		// Profile form
		const profileForm = document.getElementById('profile-edit-form');
		if (profileForm) {
			profileForm.addEventListener('submit', (e) => {
				e.preventDefault();
				this.saveProfile();
			});
		}

		// Character counter for about me
		const aboutTextarea = document.getElementById('about_me');
		if (aboutTextarea) {
			aboutTextarea.addEventListener('input', () => {
				this.updateCharCounter();
			});
		}

		// Photo upload
		const photoInput = document.getElementById('photo-upload-input');
		const browseBtn = document.getElementById('browse-photos');
		const dropzone = document.getElementById('upload-dropzone');

		if (browseBtn) {
			browseBtn.addEventListener('click', () => {
				photoInput.click();
			});
		}

		if (photoInput) {
			photoInput.addEventListener('change', (e) => {
				this.handlePhotoUpload(e.target.files);
			});
		}

		if (dropzone) {
			// Drag and drop functionality
			dropzone.addEventListener('dragover', (e) => {
				e.preventDefault();
				dropzone.classList.add('dragover');
			});

			dropzone.addEventListener('dragleave', () => {
				dropzone.classList.remove('dragover');
			});

			dropzone.addEventListener('drop', (e) => {
				e.preventDefault();
				dropzone.classList.remove('dragover');
				this.handlePhotoUpload(e.dataTransfer.files);
			});
		}

		// Quick actions
		document.querySelectorAll('.action-btn').forEach(btn => {
			btn.addEventListener('click', (e) => {
				const action = btn.dataset.action;
				this.handleQuickAction(action);
			});
		});

		// Cancel edit
		const cancelBtn = document.getElementById('cancel-edit');
		if (cancelBtn) {
			cancelBtn.addEventListener('click', () => {
				this.switchSection('overview');
			});
		}
	}

	switchSection(section) {
		// Update navigation
		document.querySelectorAll('.nav-item').forEach(item => {
			item.classList.remove('active');
		});
		document.querySelector(`[data-section="${section}"]`).classList.add('active');

		// Update content
		document.querySelectorAll('.dashboard-section').forEach(sec => {
			sec.classList.remove('active');
		});
		document.getElementById(`${section}-section`).classList.add('active');

		this.currentSection = section;

		// Load section-specific data
		this.loadSection(section);
	}

	loadSection(section) {
		switch (section) {
			case 'overview':
				this.loadRecentActivity();
				break;
			case 'photos':
				this.loadUserPhotos();
				break;
			// Add more cases as needed
		}
	}

	async loadRecentActivity() {
		const activityContainer = document.getElementById('recent-activity');
		if (!activityContainer) return;

		try {
			const response = await fetch(wpmatch_ajax.ajax_url, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: new URLSearchParams({
					action: 'wpmatch_get_recent_activity',
					nonce: wpmatch_ajax.nonce
				})
			});

			const data = await response.json();

			if (data.success) {
				this.renderRecentActivity(data.data.activities);
			} else {
				activityContainer.innerHTML = '<p>No recent activity</p>';
			}
		} catch (error) {
			console.error('Error loading recent activity:', error);
			activityContainer.innerHTML = '<p>Error loading activity</p>';
		}
	}

	renderRecentActivity(activities) {
		const activityContainer = document.getElementById('recent-activity');

		if (activities.length === 0) {
			activityContainer.innerHTML = `
				<div style="text-align: center; padding: 20px; color: #718096;">
					<p>No recent activity</p>
				</div>
			`;
			return;
		}

		const activitiesHTML = activities.map(activity => `
			<div class="activity-item">
				<div class="activity-icon">${activity.icon}</div>
				<div class="activity-content">
					<p class="activity-text">${activity.text}</p>
					<span class="activity-time">${this.formatTime(activity.time)}</span>
				</div>
			</div>
		`).join('');

		activityContainer.innerHTML = activitiesHTML;
	}

	async loadCompletionTasks() {
		const tasksContainer = document.getElementById('completion-tasks');
		if (!tasksContainer) return;

		// Mock completion tasks for demo
		const tasks = [
			{ id: 'add_photos', text: 'Add more photos', completed: false, points: 20 },
			{ id: 'complete_bio', text: 'Complete your bio', completed: true, points: 15 },
			{ id: 'verify_email', text: 'Verify your email', completed: true, points: 10 },
			{ id: 'add_interests', text: 'Add your interests', completed: false, points: 10 }
		];

		const tasksHTML = tasks.map(task => `
			<div class="completion-task ${task.completed ? 'completed' : ''}">
				<div class="task-icon">${task.completed ? '✅' : '⭕'}</div>
				<div class="task-content">
					<span class="task-text">${task.text}</span>
					<span class="task-points">+${task.points}%</span>
				</div>
			</div>
		`).join('');

		tasksContainer.innerHTML = tasksHTML;
	}

	async loadUserPhotos() {
		const photosGrid = document.getElementById('photos-grid');
		if (!photosGrid) return;

		try {
			const response = await fetch(wpmatch_ajax.ajax_url, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: new URLSearchParams({
					action: 'wpmatch_get_user_photos',
					nonce: wpmatch_ajax.nonce
				})
			});

			const data = await response.json();

			if (data.success) {
				this.renderUserPhotos(data.data.photos);
			} else {
				photosGrid.innerHTML = '<p>No photos uploaded yet</p>';
			}
		} catch (error) {
			console.error('Error loading photos:', error);
			photosGrid.innerHTML = '<p>Error loading photos</p>';
		}
	}

	renderUserPhotos(photos) {
		const photosGrid = document.getElementById('photos-grid');

		if (photos.length === 0) {
			photosGrid.innerHTML = `
				<div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: #718096;">
					<div style="font-size: 48px; margin-bottom: 16px;">📷</div>
					<h3>No photos yet</h3>
					<p>Upload some photos to complete your profile and attract more matches!</p>
				</div>
			`;
			return;
		}

		const photosHTML = photos.map((photo, index) => `
			<div class="photo-item ${photo.is_primary ? 'primary' : ''}" data-photo-id="${photo.id}">
				<img src="${photo.url}" alt="Profile photo ${index + 1}">
				<div class="photo-overlay">
					<div class="photo-actions">
						${!photo.is_primary ? `<button class="photo-btn primary-btn" data-action="set-primary" title="Set as primary">⭐</button>` : '<span class="primary-badge">Primary</span>'}
						<button class="photo-btn delete-btn" data-action="delete" title="Delete photo">🗑️</button>
					</div>
				</div>
			</div>
		`).join('');

		photosGrid.innerHTML = photosHTML;

		// Bind photo action events
		photosGrid.querySelectorAll('.photo-btn').forEach(btn => {
			btn.addEventListener('click', (e) => {
				e.stopPropagation();
				const action = btn.dataset.action;
				const photoItem = btn.closest('.photo-item');
				const photoId = photoItem.dataset.photoId;
				this.handlePhotoAction(action, photoId);
			});
		});
	}

	async handlePhotoUpload(files) {
		const validFiles = Array.from(files).filter(file => {
			return file.type.startsWith('image/') && file.size <= 5 * 1024 * 1024; // 5MB limit
		});

		if (validFiles.length === 0) {
			this.showNotification('Please select valid image files (max 5MB each)', 'error');
			return;
		}

		for (const file of validFiles) {
			await this.uploadSinglePhoto(file);
		}

		// Reload photos after upload
		this.loadUserPhotos();
	}

	async uploadSinglePhoto(file) {
		const formData = new FormData();
		formData.append('photo', file);
		formData.append('action', 'wpmatch_upload_photo');
		formData.append('nonce', wpmatch_ajax.nonce);

		try {
			const response = await fetch(wpmatch_ajax.ajax_url, {
				method: 'POST',
				body: formData
			});

			const data = await response.json();

			if (data.success) {
				this.showNotification('Photo uploaded successfully!', 'success');
			} else {
				this.showNotification(data.data.message || 'Upload failed', 'error');
			}
		} catch (error) {
			console.error('Error uploading photo:', error);
			this.showNotification('Upload failed', 'error');
		}
	}

	async handlePhotoAction(action, photoId) {
		try {
			const response = await fetch(wpmatch_ajax.ajax_url, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: new URLSearchParams({
					action: `wpmatch_photo_${action.replace('-', '_')}`,
					photo_id: photoId,
					nonce: wpmatch_ajax.nonce
				})
			});

			const data = await response.json();

			if (data.success) {
				this.showNotification(data.data.message, 'success');
				this.loadUserPhotos(); // Reload photos
			} else {
				this.showNotification(data.data.message || 'Action failed', 'error');
			}
		} catch (error) {
			console.error('Error handling photo action:', error);
			this.showNotification('Action failed', 'error');
		}
	}

	async saveProfile() {
		const form = document.getElementById('profile-edit-form');
		const submitBtn = form.querySelector('button[type="submit"]');
		const btnText = submitBtn.querySelector('.btn-text');
		const btnLoading = submitBtn.querySelector('.btn-loading');

		// Show loading state
		btnText.style.display = 'none';
		btnLoading.style.display = 'flex';
		submitBtn.disabled = true;

		try {
			const formData = new FormData(form);
			formData.append('action', 'wpmatch_update_profile');

			const response = await fetch(wpmatch_ajax.ajax_url, {
				method: 'POST',
				body: formData
			});

			const data = await response.json();

			if (data.success) {
				this.showNotification('Profile updated successfully!', 'success');
				// Update profile completion
				this.updateProfileCompletion();
			} else {
				this.showNotification(data.data.message || 'Update failed', 'error');
			}
		} catch (error) {
			console.error('Error saving profile:', error);
			this.showNotification('Save failed', 'error');
		} finally {
			// Reset button state
			btnText.style.display = 'inline';
			btnLoading.style.display = 'none';
			submitBtn.disabled = false;
		}
	}

	updateCharCounter() {
		const textarea = document.getElementById('about_me');
		const counter = document.getElementById('about-char-count');
		if (textarea && counter) {
			counter.textContent = textarea.value.length;
		}
	}

	async updateProfileCompletion() {
		try {
			const response = await fetch(wpmatch_ajax.ajax_url, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: new URLSearchParams({
					action: 'wpmatch_get_profile_completion',
					nonce: wpmatch_ajax.nonce
				})
			});

			const data = await response.json();

			if (data.success) {
				const completion = data.data.completion;
				const completionFill = document.querySelector('.completion-fill');
				const completionText = document.querySelector('.completion-text');

				if (completionFill && completionText) {
					completionFill.style.width = completion + '%';
					completionText.textContent = completion + '% Complete';
				}
			}
		} catch (error) {
			console.error('Error updating completion:', error);
		}
	}

	handleQuickAction(action) {
		switch (action) {
			case 'discover':
				window.location.href = '/discover';
				break;
			case 'edit-profile':
				this.switchSection('profile');
				break;
			case 'add-photos':
				this.switchSection('photos');
				break;
		}
	}

	formatTime(timestamp) {
		const date = new Date(timestamp);
		const now = new Date();
		const diff = now - date;

		if (diff < 60000) {
			return 'now';
		} else if (diff < 3600000) {
			const minutes = Math.floor(diff / 60000);
			return `${minutes}m ago`;
		} else if (diff < 86400000) {
			const hours = Math.floor(diff / 3600000);
			return `${hours}h ago`;
		} else {
			const days = Math.floor(diff / 86400000);
			return `${days}d ago`;
		}
	}

	showNotification(message, type = 'info') {
		const notification = document.createElement('div');
		notification.className = `notification notification-${type}`;
		notification.style.cssText = `
			position: fixed;
			top: 20px;
			right: 20px;
			background: ${type === 'error' ? '#fee' : type === 'success' ? '#f0fff4' : '#eff'};
			color: ${type === 'error' ? '#c53030' : type === 'success' ? '#38a169' : '#2d3748'};
			padding: 15px 20px;
			border-radius: 8px;
			border-left: 4px solid ${type === 'error' ? '#e53e3e' : type === 'success' ? '#48bb78' : '#667eea'};
			box-shadow: 0 4px 12px rgba(0,0,0,0.1);
			z-index: 1000;
			max-width: 300px;
			transform: translateX(100%);
			transition: transform 0.3s ease;
		`;
		notification.textContent = message;

		document.body.appendChild(notification);

		setTimeout(() => {
			notification.style.transform = 'translateX(0)';
		}, 100);

		setTimeout(() => {
			notification.style.transform = 'translateX(100%)';
			setTimeout(() => {
				if (notification.parentNode) {
					notification.parentNode.removeChild(notification);
				}
			}, 300);
		}, 5000);
	}
}

// Additional CSS for new components
const additionalCSS = `
.activity-item {
	display: flex;
	align-items: center;
	padding: 12px 0;
	border-bottom: 1px solid #e2e8f0;
}

.activity-item:last-child {
	border-bottom: none;
}

.activity-icon {
	font-size: 20px;
	margin-right: 12px;
	width: 32px;
	text-align: center;
}

.activity-content {
	flex: 1;
}

.activity-text {
	margin: 0 0 4px 0;
	font-size: 14px;
	color: #374151;
}

.activity-time {
	font-size: 12px;
	color: #6b7280;
}

.completion-task {
	display: flex;
	align-items: center;
	padding: 12px 0;
	border-bottom: 1px solid #e2e8f0;
}

.completion-task:last-child {
	border-bottom: none;
}

.completion-task.completed {
	opacity: 0.7;
}

.task-icon {
	font-size: 16px;
	margin-right: 12px;
	width: 24px;
	text-align: center;
}

.task-content {
	flex: 1;
	display: flex;
	justify-content: space-between;
	align-items: center;
}

.task-text {
	font-size: 14px;
	color: #374151;
}

.task-points {
	font-size: 12px;
	font-weight: 600;
	color: #667eea;
}

.completion-task.completed .task-text {
	text-decoration: line-through;
}

.photo-item {
	position: relative;
	border-radius: 12px;
	overflow: hidden;
	aspect-ratio: 1;
	background: #f3f4f6;
}

.photo-item img {
	width: 100%;
	height: 100%;
	object-fit: cover;
}

.photo-item.primary {
	border: 3px solid #667eea;
}

.photo-overlay {
	position: absolute;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	background: rgba(0, 0, 0, 0.5);
	display: flex;
	align-items: center;
	justify-content: center;
	opacity: 0;
	transition: opacity 0.2s ease;
}

.photo-item:hover .photo-overlay {
	opacity: 1;
}

.photo-actions {
	display: flex;
	gap: 8px;
}

.photo-btn {
	width: 36px;
	height: 36px;
	border: none;
	border-radius: 50%;
	background: rgba(255, 255, 255, 0.9);
	color: #374151;
	font-size: 14px;
	cursor: pointer;
	transition: all 0.2s ease;
	display: flex;
	align-items: center;
	justify-content: center;
}

.photo-btn:hover {
	background: white;
	transform: scale(1.1);
}

.primary-badge {
	background: #667eea;
	color: white;
	padding: 4px 8px;
	border-radius: 12px;
	font-size: 10px;
	font-weight: 600;
}
`;

// Inject additional CSS
const style = document.createElement('style');
style.textContent = additionalCSS;
document.head.appendChild(style);

// Initialize dashboard when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
	if (typeof wpmatch_ajax !== 'undefined') {
		new DashboardInterface();
	}
});
</script>