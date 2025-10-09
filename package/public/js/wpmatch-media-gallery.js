/**
 * WPMatch Media Gallery JavaScript
 *
 * Handles photo/video upload, management, and interactions.
 */

(function($) {
    'use strict';

    // Check if WPMatchMedia config is available
    if (typeof window.WPMatchMedia === 'undefined') {
        console.error('WPMatchMedia configuration not found');
        return;
    }

    const MediaGallery = {

        // Configuration
        config: window.WPMatchMedia,

        // State
        state: {
            isUploading: false,
            currentMediaType: 'photo',
            dragCounter: 0,
            sortableEnabled: false
        },

        // Initialize
        init: function() {
            this.bindEvents();
            this.initDropzones();
            this.initModals();
            this.checkUploadSupport();
        },

        // Bind event handlers
        bindEvents: function() {
            const self = this;

            // Tab switching
            $('.upload-tab').on('click', function(e) {
                e.preventDefault();
                const type = $(this).data('type');
                self.switchUploadTab(type);
            });

            // File selection buttons
            $('#select-photos').on('click', function() {
                $('#photo-input').click();
            });

            $('#select-videos').on('click', function() {
                $('#video-input').click();
            });

            // File input changes
            $('#photo-input').on('change', function() {
                self.handleFileSelection(this.files, 'photo');
            });

            $('#video-input').on('change', function() {
                self.handleFileSelection(this.files, 'video');
            });

            // Media actions
            $('.set-primary').on('click', function() {
                const mediaId = $(this).data('media-id');
                self.setPrimaryMedia(mediaId);
            });

            $('.delete-media').on('click', function() {
                const mediaId = $(this).data('media-id');
                self.deleteMedia(mediaId, $(this).closest('.media-item'));
            });

            $('.view-fullsize').on('click', function() {
                const url = $(this).data('url');
                self.showImageModal(url);
            });

            $('.play-video').on('click', function() {
                const url = $(this).data('url');
                self.showVideoModal(url);
            });

            // Reorder buttons
            $('#reorder-photos').on('click', function() {
                self.toggleSortable('photos');
            });

            $('#reorder-videos').on('click', function() {
                self.toggleSortable('videos');
            });

            // Modal close
            $('#modal-close, #modal-overlay').on('click', function() {
                self.closeModal();
            });

            // Keyboard shortcuts
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    self.closeModal();
                }
            });

            // Video thumbnail generation
            $('.video-thumbnail').on('loadedmetadata', function() {
                this.currentTime = 2; // Seek to 2 seconds for thumbnail
            });
        },

        // Initialize drag and drop zones
        initDropzones: function() {
            const self = this;

            // Photo dropzone
            const photoDropzone = $('#photo-dropzone')[0];
            if (photoDropzone) {
                this.setupDropzone(photoDropzone, 'photo');
            }

            // Video dropzone
            const videoDropzone = $('#video-dropzone')[0];
            if (videoDropzone) {
                this.setupDropzone(videoDropzone, 'video');
            }
        },

        // Setup individual dropzone
        setupDropzone: function(element, type) {
            const self = this;

            // Prevent default drag behaviors
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                element.addEventListener(eventName, function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                });
            });

            // Highlight drop zone when item is dragged over it
            ['dragenter', 'dragover'].forEach(eventName => {
                element.addEventListener(eventName, function() {
                    if (self.canAcceptUploads(type)) {
                        element.classList.add('dragover');
                    }
                });
            });

            ['dragleave', 'drop'].forEach(eventName => {
                element.addEventListener(eventName, function() {
                    element.classList.remove('dragover');
                });
            });

            // Handle dropped files
            element.addEventListener('drop', function(e) {
                if (self.canAcceptUploads(type)) {
                    const files = e.dataTransfer.files;
                    self.handleFileSelection(files, type);
                }
            });

            // Click to upload
            element.addEventListener('click', function() {
                if (type === 'photo') {
                    $('#photo-input').click();
                } else {
                    $('#video-input').click();
                }
            });
        },

        // Initialize modals
        initModals: function() {
            // Modal backdrop click handling is already handled in bindEvents
        },

        // Check upload support
        checkUploadSupport: function() {
            // Check for FileAPI support
            if (!window.File || !window.FileReader || !window.FileList || !window.Blob) {
                $('.upload-dropzone').hide();
                $('.upload-actions').prepend('<div class="upload-not-supported">' +
                    'File upload not supported in your browser. Please use a modern browser.' +
                    '</div>');
            }
        },

        // Switch upload tab
        switchUploadTab: function(type) {
            $('.upload-tab').removeClass('active');
            $('.upload-panel').removeClass('active');

            $(`.upload-tab[data-type="${type}"]`).addClass('active');
            $(`.${type}-upload`).addClass('active');

            this.state.currentMediaType = type;
        },

        // Handle file selection
        handleFileSelection: function(files, type) {
            if (!files || files.length === 0) {
                return;
            }

            // Check if uploads are allowed
            if (!this.canAcceptUploads(type)) {
                this.showNotification('error', this.config.strings.limitExceeded);
                return;
            }

            // Validate and upload files
            const validFiles = [];

            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                const validation = this.validateFile(file, type);

                if (validation.valid) {
                    validFiles.push(file);
                } else {
                    this.showNotification('error', validation.message);
                }
            }

            if (validFiles.length > 0) {
                this.uploadFiles(validFiles, type);
            }
        },

        // Validate file
        validateFile: function(file, type) {
            const maxSize = type === 'photo' ?
                this.config.limits.maxPhotoSize :
                this.config.limits.maxVideoSize;

            const allowedTypes = type === 'photo' ?
                ['image/jpeg', 'image/png', 'image/gif', 'image/webp'] :
                ['video/mp4', 'video/webm', 'video/ogg'];

            // Check file size
            if (file.size > maxSize) {
                return {
                    valid: false,
                    message: `${file.name}: ${this.config.strings.fileTooLarge}`
                };
            }

            // Check file type
            if (!allowedTypes.includes(file.type)) {
                return {
                    valid: false,
                    message: `${file.name}: ${this.config.strings.invalidFileType}`
                };
            }

            return { valid: true };
        },

        // Check if uploads are allowed
        canAcceptUploads: function(type) {
            const currentCount = this.config.counts[type === 'photo' ? 'photos' : 'videos'];
            const maxCount = this.config.limits[type === 'photo' ? 'maxPhotos' : 'maxVideos'];

            return currentCount < maxCount && !this.state.isUploading;
        },

        // Upload files
        uploadFiles: function(files, type) {
            if (this.state.isUploading) {
                return;
            }

            this.state.isUploading = true;
            this.showUploadProgress(true);

            const totalFiles = files.length;
            let uploadedFiles = 0;
            let failedFiles = 0;

            // Upload files sequentially to avoid server overload
            this.uploadFilesSequentially(files, type, 0, totalFiles, uploadedFiles, failedFiles);
        },

        // Upload files sequentially
        uploadFilesSequentially: function(files, type, index, totalFiles, uploadedFiles, failedFiles) {
            if (index >= totalFiles) {
                // All files processed
                this.state.isUploading = false;
                this.showUploadProgress(false);

                if (uploadedFiles > 0) {
                    this.showNotification('success', `${uploadedFiles} file(s) uploaded successfully!`);
                    this.refreshMediaGallery();
                }

                if (failedFiles > 0) {
                    this.showNotification('error', `${failedFiles} file(s) failed to upload.`);
                }
                return;
            }

            const file = files[index];
            const formData = new FormData();
            formData.append('action', 'wpmatch_upload_media');
            formData.append('media_file', file);
            formData.append('media_type', type);
            formData.append('nonce', this.config.nonces.upload);

            const progressPercent = Math.round(((index + 1) / totalFiles) * 100);
            this.updateUploadProgress(progressPercent, `Uploading ${file.name}...`);

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: (response) => {
                    if (response.success) {
                        uploadedFiles++;
                        this.config.counts[type === 'photo' ? 'photos' : 'videos']++;
                    } else {
                        failedFiles++;
                        console.error('Upload failed:', response.data?.message);
                    }
                },
                error: (xhr, status, error) => {
                    failedFiles++;
                    console.error('Upload error:', error);
                },
                complete: () => {
                    // Continue with next file
                    this.uploadFilesSequentially(files, type, index + 1, totalFiles, uploadedFiles, failedFiles);
                }
            });
        },

        // Show/hide upload progress
        showUploadProgress: function(show) {
            if (show) {
                $('#upload-progress').show();
            } else {
                $('#upload-progress').hide();
                this.updateUploadProgress(0, '');
            }
        },

        // Update upload progress
        updateUploadProgress: function(percent, text) {
            $('#upload-progress .progress-fill').css('width', percent + '%');
            $('#upload-progress .progress-text').text(text);
        },

        // Set primary media
        setPrimaryMedia: function(mediaId) {
            const self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpmatch_set_primary_media',
                    media_id: mediaId,
                    nonce: this.config.nonces.setPrimary
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotification('success', 'Primary media updated!');
                        self.refreshMediaGallery();
                    } else {
                        self.showNotification('error', response.data?.message || 'Failed to update primary media.');
                    }
                },
                error: function() {
                    self.showNotification('error', 'Failed to update primary media.');
                }
            });
        },

        // Delete media
        deleteMedia: function(mediaId, element) {
            if (!confirm(this.config.strings.confirmDelete)) {
                return;
            }

            const self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpmatch_delete_media',
                    media_id: mediaId,
                    nonce: this.config.nonces.delete
                },
                success: function(response) {
                    if (response.success) {
                        element.fadeOut(300, function() {
                            $(this).remove();
                            self.checkEmptyState();
                        });
                        self.showNotification('success', 'Media deleted successfully!');

                        // Update counters
                        const isPhoto = element.hasClass('photo-item');
                        if (isPhoto) {
                            self.config.counts.photos--;
                        } else {
                            self.config.counts.videos--;
                        }
                    } else {
                        self.showNotification('error', response.data?.message || 'Failed to delete media.');
                    }
                },
                error: function() {
                    self.showNotification('error', 'Failed to delete media.');
                }
            });
        },

        // Toggle sortable mode
        toggleSortable: function(type) {
            const grid = $(`#${type}-grid`);
            const button = $(`#reorder-${type}`);

            if (this.state.sortableEnabled) {
                // Disable sortable
                grid.sortable('destroy');
                button.html('<i class="fas fa-sort"></i> ' + (type === 'photos' ? 'Reorder' : 'Reorder'));
                button.removeClass('active');
                this.state.sortableEnabled = false;
                $('.media-item').removeClass('sortable-mode');
            } else {
                // Enable sortable
                grid.sortable({
                    items: '.media-item',
                    cursor: 'move',
                    tolerance: 'pointer',
                    helper: function(e, item) {
                        return item.clone().addClass('sortable-helper');
                    },
                    start: function(e, ui) {
                        ui.item.addClass('sorting');
                    },
                    stop: function(e, ui) {
                        ui.item.removeClass('sorting');
                        const mediaIds = [];
                        grid.find('.media-item').each(function() {
                            mediaIds.push($(this).data('media-id'));
                        });
                        MediaGallery.saveMediaOrder(mediaIds);
                    }
                });

                button.html('<i class="fas fa-check"></i> Done');
                button.addClass('active');
                this.state.sortableEnabled = true;
                $('.media-item').addClass('sortable-mode');
            }
        },

        // Save media order
        saveMediaOrder: function(mediaIds) {
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpmatch_reorder_media',
                    media_ids: mediaIds,
                    nonce: this.config.nonces.reorder
                },
                success: function(response) {
                    if (!response.success) {
                        console.error('Failed to save media order:', response.data?.message);
                    }
                },
                error: function() {
                    console.error('Failed to save media order');
                }
            });
        },

        // Show image in modal
        showImageModal: function(imageUrl) {
            const modalBody = $('#modal-body');
            modalBody.html(`<img src="${imageUrl}" alt="Full size image" class="modal-image">`);
            $('#media-modal').fadeIn(300);
            $('body').addClass('modal-open');
        },

        // Show video in modal
        showVideoModal: function(videoUrl) {
            const modalBody = $('#modal-body');
            modalBody.html(`
                <video controls class="modal-video">
                    <source src="${videoUrl}" type="video/mp4">
                    Your browser does not support the video tag.
                </video>
            `);
            $('#media-modal').fadeIn(300);
            $('body').addClass('modal-open');
        },

        // Close modal
        closeModal: function() {
            $('#media-modal').fadeOut(300);
            $('body').removeClass('modal-open');

            // Stop any playing videos
            $('#modal-body video').each(function() {
                this.pause();
            });
        },

        // Show notification
        showNotification: function(type, message) {
            // Remove existing notifications
            $('.wpmatch-notification').remove();

            // Create notification element
            const notification = $(`
                <div class="wpmatch-notification ${type}">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                    <span>${message}</span>
                    <button type="button" class="notification-close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `);

            // Add to page
            $('body').append(notification);

            // Show with animation
            setTimeout(() => {
                notification.addClass('show');
            }, 100);

            // Auto hide after 5 seconds
            setTimeout(() => {
                notification.removeClass('show');
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 5000);

            // Manual close
            notification.find('.notification-close').on('click', function() {
                notification.removeClass('show');
                setTimeout(() => {
                    notification.remove();
                }, 300);
            });
        },

        // Refresh media gallery
        refreshMediaGallery: function() {
            // In a real implementation, you would reload the gallery content
            // For now, just reload the page to see updates
            setTimeout(() => {
                location.reload();
            }, 1000);
        },

        // Check empty state
        checkEmptyState: function() {
            $('.media-grid').each(function() {
                const grid = $(this);
                if (grid.find('.media-item').length === 0) {
                    const type = grid.hasClass('photos-grid') ? 'photos' : 'videos';
                    const emptyMessage = type === 'photos' ?
                        'No photos uploaded yet.' :
                        'No videos uploaded yet.';

                    grid.html(`
                        <div class="no-media">
                            <i class="fas fa-${type === 'photos' ? 'images' : 'video'}"></i>
                            <p>${emptyMessage}</p>
                        </div>
                    `);
                }
            });
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        MediaGallery.init();
    });

    // Expose MediaGallery to global scope for debugging
    window.WPMatchMediaGallery = MediaGallery;

})(jQuery);