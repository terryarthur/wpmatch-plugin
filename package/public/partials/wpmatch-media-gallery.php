<?php
/**
 * Media Gallery Interface
 *
 * Provides interface for users to manage their photos and videos.
 *
 * @package WPMatch
 * @since 1.8.0
 */

// Security check
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_user_id = get_current_user_id();
if ( ! $current_user_id ) {
	echo '<p>' . esc_html__( 'Please log in to manage your media.', 'wpmatch' ) . '</p>';
	return;
}

// Get media settings
$max_photos = get_option( 'wpmatch_max_photos', 6 );
$max_videos = get_option( 'wpmatch_max_videos', 3 );
$max_photo_size = get_option( 'wpmatch_max_photo_size', 10 ); // MB
$max_video_size = get_option( 'wpmatch_max_video_size', 100 ); // MB

// Get user's media
if ( class_exists( 'WPMatch_User_Media' ) ) {
	$media_manager = new WPMatch_User_Media( 'wpmatch', '1.8.0' );
	$user_photos = $media_manager->get_user_media( $current_user_id, 'photo' );
	$user_videos = $media_manager->get_user_media( $current_user_id, 'video' );
} else {
	$user_photos = array();
	$user_videos = array();
}

$photo_count = count( $user_photos );
$video_count = count( $user_videos );
?>

<div id="wpmatch-media-gallery" class="wpmatch-media-gallery">

	<!-- Media Gallery Header -->
	<div class="media-gallery-header">
		<h3><?php esc_html_e( 'My Photos & Videos', 'wpmatch' ); ?></h3>
		<p class="media-limits">
			<?php
			echo sprintf(
				esc_html__( 'Photos: %d/%d | Videos: %d/%d', 'wpmatch' ),
				$photo_count,
				$max_photos,
				$video_count,
				$max_videos
			);
			?>
		</p>
	</div>

	<!-- Media Upload Section -->
	<div class="media-upload-section">
		<div class="upload-tabs">
			<button class="upload-tab active" data-type="photo">
				<i class="fas fa-camera"></i> <?php esc_html_e( 'Upload Photos', 'wpmatch' ); ?>
			</button>
			<button class="upload-tab" data-type="video">
				<i class="fas fa-video"></i> <?php esc_html_e( 'Upload Videos', 'wpmatch' ); ?>
			</button>
		</div>

		<!-- Photo Upload -->
		<div class="upload-panel photo-upload active">
			<div class="upload-dropzone" id="photo-dropzone">
				<div class="dropzone-content">
					<i class="fas fa-cloud-upload-alt"></i>
					<h4><?php esc_html_e( 'Drop photos here or click to upload', 'wpmatch' ); ?></h4>
					<p><?php echo sprintf( esc_html__( 'Maximum %d photos, %dMB each', 'wpmatch' ), $max_photos, $max_photo_size ); ?></p>
					<p class="supported-formats"><?php esc_html_e( 'Supported: JPG, PNG, GIF, WebP', 'wpmatch' ); ?></p>
				</div>
				<input type="file" id="photo-input" accept="image/*" multiple style="display: none;">
			</div>

			<?php if ( $photo_count < $max_photos ): ?>
			<div class="upload-actions">
				<button type="button" class="btn btn-primary" id="select-photos">
					<i class="fas fa-plus"></i> <?php esc_html_e( 'Select Photos', 'wpmatch' ); ?>
				</button>
			</div>
			<?php else: ?>
			<p class="upload-limit-reached"><?php esc_html_e( 'Photo limit reached. Delete some photos to upload new ones.', 'wpmatch' ); ?></p>
			<?php endif; ?>
		</div>

		<!-- Video Upload -->
		<div class="upload-panel video-upload">
			<div class="upload-dropzone" id="video-dropzone">
				<div class="dropzone-content">
					<i class="fas fa-video"></i>
					<h4><?php esc_html_e( 'Drop videos here or click to upload', 'wpmatch' ); ?></h4>
					<p><?php echo sprintf( esc_html__( 'Maximum %d videos, %dMB each', 'wpmatch' ), $max_videos, $max_video_size ); ?></p>
					<p class="supported-formats"><?php esc_html_e( 'Supported: MP4, WebM, OGG', 'wpmatch' ); ?></p>
				</div>
				<input type="file" id="video-input" accept="video/*" multiple style="display: none;">
			</div>

			<?php if ( $video_count < $max_videos ): ?>
			<div class="upload-actions">
				<button type="button" class="btn btn-primary" id="select-videos">
					<i class="fas fa-plus"></i> <?php esc_html_e( 'Select Videos', 'wpmatch' ); ?>
				</button>
			</div>
			<?php else: ?>
			<p class="upload-limit-reached"><?php esc_html_e( 'Video limit reached. Delete some videos to upload new ones.', 'wpmatch' ); ?></p>
			<?php endif; ?>
		</div>
	</div>

	<!-- Upload Progress -->
	<div id="upload-progress" class="upload-progress" style="display: none;">
		<div class="progress-bar">
			<div class="progress-fill"></div>
		</div>
		<p class="progress-text"></p>
	</div>

	<!-- Photo Gallery -->
	<div class="media-section photos-section">
		<h4>
			<?php esc_html_e( 'My Photos', 'wpmatch' ); ?>
			<?php if ( $photo_count > 1 ): ?>
			<button type="button" class="btn btn-small btn-secondary" id="reorder-photos">
				<i class="fas fa-sort"></i> <?php esc_html_e( 'Reorder', 'wpmatch' ); ?>
			</button>
			<?php endif; ?>
		</h4>

		<div class="media-grid photos-grid" id="photos-grid">
			<?php if ( empty( $user_photos ) ): ?>
			<div class="no-media">
				<i class="fas fa-images"></i>
				<p><?php esc_html_e( 'No photos uploaded yet.', 'wpmatch' ); ?></p>
				<p><?php esc_html_e( 'Add some photos to make your profile more attractive!', 'wpmatch' ); ?></p>
			</div>
			<?php else: ?>
				<?php foreach ( $user_photos as $photo ): ?>
				<div class="media-item photo-item" data-media-id="<?php echo esc_attr( $photo['media_id'] ); ?>">
					<div class="media-thumbnail">
						<img src="<?php echo esc_url( $photo['sizes']['medium']['url'] ?? $photo['url'] ); ?>"
						     alt="<?php esc_attr_e( 'User photo', 'wpmatch' ); ?>"
						     loading="lazy">

						<?php if ( $photo['is_primary'] ): ?>
						<div class="primary-badge">
							<i class="fas fa-star"></i>
							<span><?php esc_html_e( 'Primary', 'wpmatch' ); ?></span>
						</div>
						<?php endif; ?>

						<?php if ( ! $photo['is_verified'] ): ?>
						<div class="verification-badge pending">
							<i class="fas fa-clock"></i>
							<span><?php esc_html_e( 'Pending', 'wpmatch' ); ?></span>
						</div>
						<?php elseif ( 'rejected' === $photo['verification_status'] ): ?>
						<div class="verification-badge rejected">
							<i class="fas fa-times"></i>
							<span><?php esc_html_e( 'Rejected', 'wpmatch' ); ?></span>
						</div>
						<?php else: ?>
						<div class="verification-badge verified">
							<i class="fas fa-check"></i>
							<span><?php esc_html_e( 'Verified', 'wpmatch' ); ?></span>
						</div>
						<?php endif; ?>
					</div>

					<div class="media-actions">
						<?php if ( ! $photo['is_primary'] ): ?>
						<button type="button" class="btn-icon set-primary"
								data-media-id="<?php echo esc_attr( $photo['media_id'] ); ?>"
								title="<?php esc_attr_e( 'Set as primary photo', 'wpmatch' ); ?>">
							<i class="fas fa-star"></i>
						</button>
						<?php endif; ?>

						<button type="button" class="btn-icon view-fullsize"
								data-media-id="<?php echo esc_attr( $photo['media_id'] ); ?>"
								data-url="<?php echo esc_url( $photo['url'] ); ?>"
								title="<?php esc_attr_e( 'View full size', 'wpmatch' ); ?>">
							<i class="fas fa-search-plus"></i>
						</button>

						<button type="button" class="btn-icon delete-media"
								data-media-id="<?php echo esc_attr( $photo['media_id'] ); ?>"
								title="<?php esc_attr_e( 'Delete photo', 'wpmatch' ); ?>">
							<i class="fas fa-trash"></i>
						</button>
					</div>

					<div class="media-info">
						<p class="file-size"><?php echo esc_html( $photo['file_size_formatted'] ); ?></p>
						<p class="upload-date"><?php echo esc_html( mysql2date( 'M j, Y', $photo['created_at'] ) ); ?></p>
					</div>
				</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
	</div>

	<!-- Video Gallery -->
	<div class="media-section videos-section">
		<h4>
			<?php esc_html_e( 'My Videos', 'wpmatch' ); ?>
			<?php if ( $video_count > 1 ): ?>
			<button type="button" class="btn btn-small btn-secondary" id="reorder-videos">
				<i class="fas fa-sort"></i> <?php esc_html_e( 'Reorder', 'wpmatch' ); ?>
			</button>
			<?php endif; ?>
		</h4>

		<div class="media-grid videos-grid" id="videos-grid">
			<?php if ( empty( $user_videos ) ): ?>
			<div class="no-media">
				<i class="fas fa-video"></i>
				<p><?php esc_html_e( 'No videos uploaded yet.', 'wpmatch' ); ?></p>
				<p><?php esc_html_e( 'Share a video introduction to stand out!', 'wpmatch' ); ?></p>
			</div>
			<?php else: ?>
				<?php foreach ( $user_videos as $video ): ?>
				<div class="media-item video-item" data-media-id="<?php echo esc_attr( $video['media_id'] ); ?>">
					<div class="media-thumbnail">
						<video src="<?php echo esc_url( $video['url'] ); ?>"
						       preload="metadata"
						       muted
						       class="video-thumbnail">
						</video>
						<div class="play-overlay">
							<i class="fas fa-play"></i>
						</div>

						<?php if ( $video['is_primary'] ): ?>
						<div class="primary-badge">
							<i class="fas fa-star"></i>
							<span><?php esc_html_e( 'Primary', 'wpmatch' ); ?></span>
						</div>
						<?php endif; ?>

						<?php if ( ! $video['is_verified'] ): ?>
						<div class="verification-badge pending">
							<i class="fas fa-clock"></i>
							<span><?php esc_html_e( 'Pending', 'wpmatch' ); ?></span>
						</div>
						<?php elseif ( 'rejected' === $video['verification_status'] ): ?>
						<div class="verification-badge rejected">
							<i class="fas fa-times"></i>
							<span><?php esc_html_e( 'Rejected', 'wpmatch' ); ?></span>
						</div>
						<?php else: ?>
						<div class="verification-badge verified">
							<i class="fas fa-check"></i>
							<span><?php esc_html_e( 'Verified', 'wpmatch' ); ?></span>
						</div>
						<?php endif; ?>
					</div>

					<div class="media-actions">
						<?php if ( ! $video['is_primary'] ): ?>
						<button type="button" class="btn-icon set-primary"
								data-media-id="<?php echo esc_attr( $video['media_id'] ); ?>"
								title="<?php esc_attr_e( 'Set as primary video', 'wpmatch' ); ?>">
							<i class="fas fa-star"></i>
						</button>
						<?php endif; ?>

						<button type="button" class="btn-icon play-video"
								data-media-id="<?php echo esc_attr( $video['media_id'] ); ?>"
								data-url="<?php echo esc_url( $video['url'] ); ?>"
								title="<?php esc_attr_e( 'Play video', 'wpmatch' ); ?>">
							<i class="fas fa-play"></i>
						</button>

						<button type="button" class="btn-icon delete-media"
								data-media-id="<?php echo esc_attr( $video['media_id'] ); ?>"
								title="<?php esc_attr_e( 'Delete video', 'wpmatch' ); ?>">
							<i class="fas fa-trash"></i>
						</button>
					</div>

					<div class="media-info">
						<p class="file-size"><?php echo esc_html( $video['file_size_formatted'] ); ?></p>
						<p class="upload-date"><?php echo esc_html( mysql2date( 'M j, Y', $video['created_at'] ) ); ?></p>
					</div>
				</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
	</div>
</div>

<!-- Media Modal -->
<div id="media-modal" class="modal media-modal" style="display: none;">
	<div class="modal-overlay" id="modal-overlay"></div>
	<div class="modal-content">
		<button type="button" class="modal-close" id="modal-close">
			<i class="fas fa-times"></i>
		</button>
		<div class="modal-body" id="modal-body">
			<!-- Dynamic content -->
		</div>
	</div>
</div>

<!-- Drag and Drop Sorting Helper -->
<div id="sortable-helper" class="sortable-helper" style="display: none;">
	<i class="fas fa-grip-vertical"></i>
	<span><?php esc_html_e( 'Drag to reorder', 'wpmatch' ); ?></span>
</div>

<script type="text/javascript">
// Pass data to JavaScript
window.WPMatchMedia = {
	ajaxUrl: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
	nonces: {
		upload: '<?php echo wp_create_nonce( 'wpmatch_upload_media' ); ?>',
		delete: '<?php echo wp_create_nonce( 'wpmatch_delete_media' ); ?>',
		reorder: '<?php echo wp_create_nonce( 'wpmatch_reorder_media' ); ?>',
		setPrimary: '<?php echo wp_create_nonce( 'wpmatch_set_primary_media' ); ?>'
	},
	limits: {
		maxPhotos: <?php echo absint( $max_photos ); ?>,
		maxVideos: <?php echo absint( $max_videos ); ?>,
		maxPhotoSize: <?php echo absint( $max_photo_size * 1024 * 1024 ); ?>,
		maxVideoSize: <?php echo absint( $max_video_size * 1024 * 1024 ); ?>
	},
	counts: {
		photos: <?php echo absint( $photo_count ); ?>,
		videos: <?php echo absint( $video_count ); ?>
	},
	strings: {
		uploading: '<?php echo esc_js( __( 'Uploading...', 'wpmatch' ) ); ?>',
		processing: '<?php echo esc_js( __( 'Processing...', 'wpmatch' ) ); ?>',
		complete: '<?php echo esc_js( __( 'Upload complete!', 'wpmatch' ) ); ?>',
		error: '<?php echo esc_js( __( 'Upload failed. Please try again.', 'wpmatch' ) ); ?>',
		confirmDelete: '<?php echo esc_js( __( 'Are you sure you want to delete this media?', 'wpmatch' ) ); ?>',
		fileTooLarge: '<?php echo esc_js( __( 'File is too large.', 'wpmatch' ) ); ?>',
		invalidFileType: '<?php echo esc_js( __( 'Invalid file type.', 'wpmatch' ) ); ?>',
		limitExceeded: '<?php echo esc_js( __( 'Upload limit exceeded.', 'wpmatch' ) ); ?>'
	}
};
</script>