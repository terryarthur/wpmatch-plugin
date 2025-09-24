<?php
/**
 * Photo moderation item template
 *
 * @var object $item Photo item data
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$uploaded_date = human_time_diff( strtotime( $item->uploaded_at ) );
?>

<div class="moderation-item photo-item" data-item-id="<?php echo esc_attr( $item->id ); ?>">
	<div class="item-checkbox">
		<input type="checkbox" class="moderation-checkbox" value="<?php echo esc_attr( $item->id ); ?>">
	</div>

	<div class="item-photo">
		<img src="<?php echo esc_url( $item->file_path ); ?>" alt="User photo" class="photo-preview" onclick="showPhotoModal('<?php echo esc_url( $item->file_path ); ?>')">
		<?php if ( $item->is_primary ) : ?>
			<div class="primary-badge"><?php esc_html_e( 'Primary', 'wpmatch' ); ?></div>
		<?php endif; ?>
	</div>

	<div class="item-content">
		<div class="item-header">
			<h4 class="item-title"><?php printf( esc_html__( 'Photo by %s', 'wpmatch' ), esc_html( $item->display_name ) ); ?></h4>
			<div class="item-meta">
				<span class="upload-date"><?php printf( esc_html__( 'Uploaded %s ago', 'wpmatch' ), esc_html( $uploaded_date ) ); ?></span>
				<span class="file-info"><?php printf( esc_html__( 'User ID: %d', 'wpmatch' ), $item->user_id ); ?></span>
			</div>
		</div>

		<div class="photo-analysis">
			<div class="analysis-row">
				<strong><?php esc_html_e( 'File Type:', 'wpmatch' ); ?></strong>
				<span><?php echo esc_html( strtoupper( pathinfo( $item->file_path, PATHINFO_EXTENSION ) ) ); ?></span>
			</div>
			<div class="analysis-row">
				<strong><?php esc_html_e( 'Status:', 'wpmatch' ); ?></strong>
				<span class="status-badge status-<?php echo esc_attr( $item->moderation_status ); ?>">
					<?php echo esc_html( ucfirst( $item->moderation_status ) ); ?>
				</span>
			</div>
			<?php if ( $item->moderation_notes ) : ?>
				<div class="moderation-notes">
					<strong><?php esc_html_e( 'Notes:', 'wpmatch' ); ?></strong>
					<p><?php echo esc_html( $item->moderation_notes ); ?></p>
				</div>
			<?php endif; ?>
		</div>

		<div class="quick-checks">
			<h5><?php esc_html_e( 'Quick Assessment', 'wpmatch' ); ?></h5>
			<div class="check-items">
				<label>
					<input type="checkbox" class="assessment-check" data-check="appropriate">
					<?php esc_html_e( 'Appropriate content', 'wpmatch' ); ?>
				</label>
				<label>
					<input type="checkbox" class="assessment-check" data-check="clear">
					<?php esc_html_e( 'Clear/good quality', 'wpmatch' ); ?>
				</label>
				<label>
					<input type="checkbox" class="assessment-check" data-check="face_visible">
					<?php esc_html_e( 'Face clearly visible', 'wpmatch' ); ?>
				</label>
				<label>
					<input type="checkbox" class="assessment-check" data-check="authentic">
					<?php esc_html_e( 'Appears authentic', 'wpmatch' ); ?>
				</label>
			</div>
		</div>
	</div>

	<div class="item-actions">
		<div class="action-buttons">
			<button type="button" class="action-btn approve" onclick="moderateItem(<?php echo esc_attr( $item->id ); ?>, 'approve', 'photo')">
				<span class="dashicons dashicons-yes-alt"></span>
				<?php esc_html_e( 'Approve', 'wpmatch' ); ?>
			</button>
			<button type="button" class="action-btn reject" onclick="moderateItem(<?php echo esc_attr( $item->id ); ?>, 'reject', 'photo')">
				<span class="dashicons dashicons-dismiss"></span>
				<?php esc_html_e( 'Reject', 'wpmatch' ); ?>
			</button>
			<button type="button" class="action-btn view-user" onclick="showUserProfile(<?php echo esc_attr( $item->user_id ); ?>)">
				<span class="dashicons dashicons-admin-users"></span>
				<?php esc_html_e( 'View User', 'wpmatch' ); ?>
			</button>
		</div>

		<div class="rejection-reasons" style="display: none;">
			<h5><?php esc_html_e( 'Rejection Reason', 'wpmatch' ); ?></h5>
			<select class="rejection-reason">
				<option value=""><?php esc_html_e( 'Select reason...', 'wpmatch' ); ?></option>
				<option value="inappropriate"><?php esc_html_e( 'Inappropriate content', 'wpmatch' ); ?></option>
				<option value="quality"><?php esc_html_e( 'Poor quality', 'wpmatch' ); ?></option>
				<option value="fake"><?php esc_html_e( 'Appears fake/filtered', 'wpmatch' ); ?></option>
				<option value="not_person"><?php esc_html_e( 'Not a person', 'wpmatch' ); ?></option>
				<option value="multiple_people"><?php esc_html_e( 'Multiple people', 'wpmatch' ); ?></option>
				<option value="other"><?php esc_html_e( 'Other', 'wpmatch' ); ?></option>
			</select>
			<textarea class="rejection-notes" placeholder="<?php esc_attr_e( 'Additional notes (optional)', 'wpmatch' ); ?>"></textarea>
		</div>
	</div>
</div>

<!-- Photo Modal -->
<div id="photo-modal" class="photo-modal" style="display: none;">
	<div class="photo-modal-content">
		<span class="photo-close" onclick="closePhotoModal()">&times;</span>
		<img id="modal-photo" src="" alt="Full size photo">
	</div>
</div>

<style>
.photo-item {
	position: relative;
}

.item-photo {
	position: relative;
	width: 120px;
	height: 120px;
	flex-shrink: 0;
}

.photo-preview {
	width: 100%;
	height: 100%;
	object-fit: cover;
	border-radius: 8px;
	cursor: pointer;
	transition: transform 0.3s ease;
}

.photo-preview:hover {
	transform: scale(1.05);
}

.primary-badge {
	position: absolute;
	top: 5px;
	right: 5px;
	background: #28a745;
	color: white;
	padding: 2px 6px;
	border-radius: 4px;
	font-size: 10px;
	font-weight: 600;
}

.photo-analysis {
	margin-bottom: 15px;
}

.analysis-row {
	display: flex;
	gap: 8px;
	margin-bottom: 5px;
}

.status-badge {
	padding: 2px 8px;
	border-radius: 12px;
	font-size: 11px;
	font-weight: 600;
	text-transform: uppercase;
}

.status-pending { background: #fff3cd; color: #856404; }
.status-approved { background: #d4edda; color: #155724; }
.status-rejected { background: #f8d7da; color: #721c24; }

.moderation-notes p {
	margin: 5px 0 0 0;
	padding: 10px;
	background: #f8f9fa;
	border-radius: 4px;
	font-size: 14px;
}

.quick-checks {
	border-top: 1px solid #eee;
	padding-top: 15px;
}

.quick-checks h5 {
	margin: 0 0 10px 0;
	color: #2c3e50;
	font-size: 14px;
}

.check-items {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
	gap: 8px;
}

.check-items label {
	display: flex;
	align-items: center;
	gap: 8px;
	font-size: 13px;
	cursor: pointer;
}

.rejection-reasons {
	margin-top: 15px;
	padding-top: 15px;
	border-top: 1px solid #eee;
}

.rejection-reasons h5 {
	margin: 0 0 10px 0;
	color: #dc3545;
	font-size: 14px;
}

.rejection-reason {
	width: 100%;
	padding: 8px;
	border-radius: 4px;
	border: 1px solid #ddd;
	margin-bottom: 10px;
}

.rejection-notes {
	width: 100%;
	min-height: 60px;
	padding: 8px;
	border-radius: 4px;
	border: 1px solid #ddd;
	resize: vertical;
}

/* Photo Modal Styles */
.photo-modal {
	position: fixed;
	z-index: 10000;
	left: 0;
	top: 0;
	width: 100%;
	height: 100%;
	background-color: rgba(0, 0, 0, 0.9);
	display: flex;
	align-items: center;
	justify-content: center;
}

.photo-modal-content {
	position: relative;
	max-width: 90%;
	max-height: 90%;
}

#modal-photo {
	max-width: 100%;
	max-height: 100%;
	object-fit: contain;
	border-radius: 8px;
}

.photo-close {
	position: absolute;
	top: -40px;
	right: 0;
	color: white;
	font-size: 30px;
	font-weight: bold;
	cursor: pointer;
	padding: 5px;
}

.photo-close:hover {
	opacity: 0.7;
}

@media (max-width: 768px) {
	.item-photo {
		width: 100px;
		height: 100px;
	}

	.check-items {
		grid-template-columns: 1fr;
	}
}
</style>

<script>
function showPhotoModal(photoUrl) {
	document.getElementById('modal-photo').src = photoUrl;
	document.getElementById('photo-modal').style.display = 'block';
}

function closePhotoModal() {
	document.getElementById('photo-modal').style.display = 'none';
}

// Show/hide rejection reasons when reject button is clicked
document.addEventListener('DOMContentLoaded', function() {
	document.querySelectorAll('.action-btn.reject').forEach(button => {
		button.addEventListener('click', function() {
			const rejectionSection = this.closest('.item-actions').querySelector('.rejection-reasons');
			if (rejectionSection) {
				rejectionSection.style.display = rejectionSection.style.display === 'none' ? 'block' : 'none';
			}
		});
	});
});
</script>