/**
 * WPMatch Voice Notes JavaScript
 *
 * @package WPMatch
 * @since 1.6.0
 */

(function($) {
    'use strict';

    /**
     * Voice Notes object
     */
    const WPMatchVoiceNotes = {

        /**
         * Media recorder instance
         */
        mediaRecorder: null,

        /**
         * Audio stream
         */
        audioStream: null,

        /**
         * Recorded audio chunks
         */
        audioChunks: [],

        /**
         * Recording timer
         */
        recordingTimer: null,

        /**
         * Recording start time
         */
        recordingStartTime: null,

        /**
         * Audio context for visualization
         */
        audioContext: null,

        /**
         * Analyzer node
         */
        analyzerNode: null,

        /**
         * Visualization data
         */
        dataArray: null,

        /**
         * Current quality setting
         */
        recordingQuality: 'medium',

        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.loadVoiceNotes();
            this.checkMicrophonePermission();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            // Recording controls
            $(document).on('click', '.btn-record', this.startRecording);
            $(document).on('click', '.btn-stop', this.stopRecording);
            $(document).on('click', '.btn-play', this.playRecording);
            $(document).on('click', '.btn-pause', this.pauseRecording);
            $(document).on('click', '.btn-delete', this.deleteRecording);
            $(document).on('click', '.btn-send', this.sendVoiceNote);

            // Player controls
            $(document).on('click', '.player-btn', this.handlePlayerControl);
            $(document).on('click', '.player-progress', this.seekAudio);

            // Quality settings
            $(document).on('click', '.quality-btn', this.changeQuality);

            // Filters
            $(document).on('click', '.filter-btn', this.filterVoiceNotes);

            // Actions
            $(document).on('click', '.action-btn', this.handleVoiceNoteAction);

            // Progress bar interaction
            $(document).on('mousedown', '.progress-handle', this.startDragging);
            $(document).on('mousemove', this.handleDragging);
            $(document).on('mouseup', this.stopDragging);

            // Keyboard shortcuts
            $(document).on('keydown', this.handleKeyboardShortcuts);
        },

        /**
         * Check microphone permission
         */
        checkMicrophonePermission: function() {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                this.showError('Voice recording is not supported in this browser');
                return;
            }

            navigator.permissions.query({name: 'microphone'}).then(permission => {
                if (permission.state === 'denied') {
                    this.showError('Microphone access denied. Please enable microphone access in your browser settings.');
                }
            }).catch(() => {
                // Permissions API not supported, try getUserMedia
                this.testMicrophoneAccess();
            });
        },

        /**
         * Test microphone access
         */
        testMicrophoneAccess: function() {
            navigator.mediaDevices.getUserMedia({ audio: true })
                .then(stream => {
                    // Stop the test stream
                    stream.getTracks().forEach(track => track.stop());
                    this.updateRecorderStatus('Ready to record');
                })
                .catch(error => {
                    this.showError('Unable to access microphone: ' + error.message);
                });
        },

        /**
         * Start recording
         */
        startRecording: function(e) {
            e.preventDefault();

            if (WPMatchVoiceNotes.mediaRecorder && WPMatchVoiceNotes.mediaRecorder.state === 'recording') {
                return;
            }

            const constraints = WPMatchVoiceNotes.getRecordingConstraints();

            navigator.mediaDevices.getUserMedia(constraints)
                .then(stream => {
                    WPMatchVoiceNotes.audioStream = stream;
                    WPMatchVoiceNotes.setupRecorder(stream);
                    WPMatchVoiceNotes.startVisualization(stream);
                })
                .catch(error => {
                    WPMatchVoiceNotes.showError('Failed to start recording: ' + error.message);
                });
        },

        /**
         * Get recording constraints based on quality
         */
        getRecordingConstraints: function() {
            const qualitySettings = {
                low: {
                    audio: {
                        sampleRate: 22050,
                        channelCount: 1,
                        echoCancellation: true,
                        noiseSuppression: true
                    }
                },
                medium: {
                    audio: {
                        sampleRate: 44100,
                        channelCount: 1,
                        echoCancellation: true,
                        noiseSuppression: true
                    }
                },
                high: {
                    audio: {
                        sampleRate: 48000,
                        channelCount: 2,
                        echoCancellation: true,
                        noiseSuppression: true,
                        autoGainControl: true
                    }
                }
            };

            return qualitySettings[this.recordingQuality] || qualitySettings.medium;
        },

        /**
         * Setup media recorder
         */
        setupRecorder: function(stream) {
            this.audioChunks = [];

            const options = {
                mimeType: this.getSupportedMimeType(),
                audioBitsPerSecond: this.getAudioBitrate()
            };

            this.mediaRecorder = new MediaRecorder(stream, options);

            this.mediaRecorder.ondataavailable = (event) => {
                if (event.data.size > 0) {
                    this.audioChunks.push(event.data);
                }
            };

            this.mediaRecorder.onstop = () => {
                this.processRecording();
            };

            this.mediaRecorder.onerror = (event) => {
                this.showError('Recording error: ' + event.error);
            };

            this.mediaRecorder.start(100); // Collect data every 100ms
            this.recordingStartTime = Date.now();
            this.startRecordingTimer();
            this.updateRecorderUI('recording');
        },

        /**
         * Get supported MIME type
         */
        getSupportedMimeType: function() {
            const types = [
                'audio/webm;codecs=opus',
                'audio/webm',
                'audio/mp4',
                'audio/wav'
            ];

            for (let type of types) {
                if (MediaRecorder.isTypeSupported(type)) {
                    return type;
                }
            }

            return '';
        },

        /**
         * Get audio bitrate based on quality
         */
        getAudioBitrate: function() {
            const bitrates = {
                low: 64000,
                medium: 128000,
                high: 256000
            };

            return bitrates[this.recordingQuality] || bitrates.medium;
        },

        /**
         * Start visualization
         */
        startVisualization: function(stream) {
            try {
                this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
                const source = this.audioContext.createMediaStreamSource(stream);
                this.analyzerNode = this.audioContext.createAnalyser();

                this.analyzerNode.fftSize = 256;
                source.connect(this.analyzerNode);

                const bufferLength = this.analyzerNode.frequencyBinCount;
                this.dataArray = new Uint8Array(bufferLength);

                this.animateVisualization();
            } catch (error) {
                console.warn('Visualization not available:', error);
            }
        },

        /**
         * Animate visualization
         */
        animateVisualization: function() {
            if (!this.analyzerNode || !this.mediaRecorder || this.mediaRecorder.state !== 'recording') {
                return;
            }

            this.analyzerNode.getByteFrequencyData(this.dataArray);
            this.updateVisualizationBars();

            requestAnimationFrame(() => this.animateVisualization());
        },

        /**
         * Update visualization bars
         */
        updateVisualizationBars: function() {
            const $bars = $('.visualizer-bar');
            if ($bars.length === 0) {
                this.createVisualizationBars();
                return;
            }

            const step = Math.floor(this.dataArray.length / $bars.length);

            $bars.each((index, bar) => {
                const dataIndex = index * step;
                const amplitude = this.dataArray[dataIndex] || 0;
                const height = Math.max(4, (amplitude / 255) * 40);

                $(bar).css('height', height + 'px');

                if (amplitude > 100) {
                    $(bar).addClass('active');
                } else {
                    $(bar).removeClass('active');
                }
            });
        },

        /**
         * Create visualization bars
         */
        createVisualizationBars: function() {
            const $visualizer = $('.visualizer-bars');
            if ($visualizer.length === 0) return;

            $visualizer.empty();

            for (let i = 0; i < 20; i++) {
                $visualizer.append('<div class="visualizer-bar" style="height: 4px;"></div>');
            }
        },

        /**
         * Start recording timer
         */
        startRecordingTimer: function() {
            this.recordingTimer = setInterval(() => {
                const elapsed = Date.now() - this.recordingStartTime;
                this.updateRecordingTimer(elapsed);
            }, 100);
        },

        /**
         * Update recording timer
         */
        updateRecordingTimer: function(elapsed) {
            const minutes = Math.floor(elapsed / 60000);
            const seconds = Math.floor((elapsed % 60000) / 1000);
            const centiseconds = Math.floor((elapsed % 1000) / 10);

            const timeString = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}.${centiseconds.toString().padStart(2, '0')}`;
            $('.recording-timer').text(timeString).addClass('pulsing');
        },

        /**
         * Stop recording
         */
        stopRecording: function(e) {
            e.preventDefault();

            if (!WPMatchVoiceNotes.mediaRecorder || WPMatchVoiceNotes.mediaRecorder.state !== 'recording') {
                return;
            }

            WPMatchVoiceNotes.mediaRecorder.stop();
            WPMatchVoiceNotes.stopRecordingTimer();
            WPMatchVoiceNotes.stopAudioStream();
            WPMatchVoiceNotes.updateRecorderUI('stopped');
        },

        /**
         * Stop recording timer
         */
        stopRecordingTimer: function() {
            if (this.recordingTimer) {
                clearInterval(this.recordingTimer);
                this.recordingTimer = null;
            }
            $('.recording-timer').removeClass('pulsing');
        },

        /**
         * Stop audio stream
         */
        stopAudioStream: function() {
            if (this.audioStream) {
                this.audioStream.getTracks().forEach(track => track.stop());
                this.audioStream = null;
            }

            if (this.audioContext) {
                this.audioContext.close();
                this.audioContext = null;
            }
        },

        /**
         * Process recording
         */
        processRecording: function() {
            if (this.audioChunks.length === 0) {
                this.showError('No audio data recorded');
                return;
            }

            const audioBlob = new Blob(this.audioChunks, {
                type: this.getSupportedMimeType()
            });

            this.createAudioPreview(audioBlob);
            this.currentRecording = audioBlob;
            this.updateRecorderUI('ready_to_send');
        },

        /**
         * Create audio preview
         */
        createAudioPreview: function(audioBlob) {
            const audioUrl = URL.createObjectURL(audioBlob);
            const audio = new Audio(audioUrl);

            audio.addEventListener('loadedmetadata', () => {
                const duration = Math.round(audio.duration);
                const size = this.formatFileSize(audioBlob.size);

                this.updateRecordingInfo(duration, size);
            });

            this.previewAudio = audio;
        },

        /**
         * Update recording info
         */
        updateRecordingInfo: function(duration, size) {
            const minutes = Math.floor(duration / 60);
            const seconds = duration % 60;
            const durationText = `${minutes}:${seconds.toString().padStart(2, '0')}`;

            $('.recorder-status .status-text').text('Recording ready');
            $('.recording-timer').text(durationText);

            // Show file info
            this.showRecordingInfo(durationText, size);
        },

        /**
         * Show recording info
         */
        showRecordingInfo: function(duration, size) {
            const $info = $('.recording-info');
            if ($info.length === 0) {
                $('.voice-recorder').append(`
                    <div class="recording-info">
                        <div class="info-item">
                            <span class="info-label">Duration:</span>
                            <span class="info-value duration">${duration}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Size:</span>
                            <span class="info-value size">${size}</span>
                        </div>
                    </div>
                `);
            } else {
                $info.find('.duration').text(duration);
                $info.find('.size').text(size);
            }
        },

        /**
         * Play recording
         */
        playRecording: function(e) {
            e.preventDefault();

            if (!WPMatchVoiceNotes.previewAudio) return;

            WPMatchVoiceNotes.previewAudio.play();
            WPMatchVoiceNotes.updateRecorderUI('playing');

            WPMatchVoiceNotes.previewAudio.addEventListener('ended', () => {
                WPMatchVoiceNotes.updateRecorderUI('ready_to_send');
            });
        },

        /**
         * Pause recording
         */
        pauseRecording: function(e) {
            e.preventDefault();

            if (!WPMatchVoiceNotes.previewAudio) return;

            WPMatchVoiceNotes.previewAudio.pause();
            WPMatchVoiceNotes.updateRecorderUI('ready_to_send');
        },

        /**
         * Delete recording
         */
        deleteRecording: function(e) {
            e.preventDefault();

            if (!confirm(wpmatch_voice_notes.strings.delete_confirm)) return;

            WPMatchVoiceNotes.currentRecording = null;
            WPMatchVoiceNotes.previewAudio = null;
            WPMatchVoiceNotes.audioChunks = [];

            $('.recording-info').remove();
            WPMatchVoiceNotes.updateRecorderUI('ready');
            WPMatchVoiceNotes.updateRecordingTimer(0);
        },

        /**
         * Send voice note
         */
        sendVoiceNote: function(e) {
            e.preventDefault();

            if (!WPMatchVoiceNotes.currentRecording) {
                WPMatchVoiceNotes.showError('No recording to send');
                return;
            }

            const recipientId = $(e.target).data('recipient-id') || $('.voice-notes-container').data('recipient-id');
            if (!recipientId) {
                WPMatchVoiceNotes.showError('No recipient specified');
                return;
            }

            WPMatchVoiceNotes.uploadVoiceNote(WPMatchVoiceNotes.currentRecording, recipientId);
        },

        /**
         * Upload voice note
         */
        uploadVoiceNote: function(audioBlob, recipientId) {
            const formData = new FormData();
            formData.append('voice_note', audioBlob, 'voice_note.webm');
            formData.append('recipient_id', recipientId);
            formData.append('duration', Math.round(this.previewAudio.duration));

            // Show upload progress
            this.showUploadProgress();

            $.ajax({
                url: wpmatch_voice_notes.rest_url + 'wpmatch/v1/voice-notes/upload',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: {
                    'X-WP-Nonce': wpmatch_voice_notes.nonce
                },
                xhr: function() {
                    const xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener('progress', function(e) {
                        if (e.lengthComputable) {
                            const percentComplete = (e.loaded / e.total) * 100;
                            WPMatchVoiceNotes.updateUploadProgress(percentComplete);
                        }
                    });
                    return xhr;
                },
                success: function(response) {
                    WPMatchVoiceNotes.hideUploadProgress();

                    if (response.success) {
                        WPMatchVoiceNotes.showNotification(wpmatch_voice_notes.strings.send_success, 'success');
                        WPMatchVoiceNotes.deleteRecording({ preventDefault: () => {} });
                        WPMatchVoiceNotes.loadVoiceNotes();

                        // Trigger achievement check
                        $(document).trigger('wpmatch:action', ['voice_message_sent', {
                            recipient_id: recipientId,
                            duration: Math.round(WPMatchVoiceNotes.previewAudio.duration)
                        }]);
                    } else {
                        WPMatchVoiceNotes.showError(response.message || wpmatch_voice_notes.strings.send_error);
                    }
                },
                error: function() {
                    WPMatchVoiceNotes.hideUploadProgress();
                    WPMatchVoiceNotes.showError(wpmatch_voice_notes.strings.upload_error);
                }
            });
        },

        /**
         * Show upload progress
         */
        showUploadProgress: function() {
            const $progress = $('.upload-progress');
            if ($progress.length === 0) {
                $('.voice-recorder').append(`
                    <div class="upload-progress">
                        <div class="upload-status">Uploading voice note...</div>
                        <div class="upload-progress-bar">
                            <div class="upload-progress-fill" style="width: 0%"></div>
                        </div>
                        <div class="upload-percentage">0%</div>
                    </div>
                `);
            }

            $progress.show();
        },

        /**
         * Update upload progress
         */
        updateUploadProgress: function(percentage) {
            $('.upload-progress-fill').css('width', percentage + '%');
            $('.upload-percentage').text(Math.round(percentage) + '%');
        },

        /**
         * Hide upload progress
         */
        hideUploadProgress: function() {
            $('.upload-progress').fadeOut(() => {
                $('.upload-progress').remove();
            });
        },

        /**
         * Update recorder UI
         */
        updateRecorderUI: function(state) {
            const $recorder = $('.voice-recorder');

            // Reset all button states
            $('.recorder-btn').prop('disabled', false);
            $('.btn-record').removeClass('recording');

            switch (state) {
                case 'recording':
                    $('.status-text').text('Recording...');
                    $('.btn-record').addClass('recording').prop('disabled', true);
                    $('.btn-play, .btn-pause, .btn-send').prop('disabled', true);
                    break;

                case 'stopped':
                    $('.status-text').text('Processing...');
                    $('.recorder-btn').prop('disabled', true);
                    break;

                case 'ready_to_send':
                    $('.status-text').text('Ready to send');
                    $('.btn-record, .btn-stop').prop('disabled', false);
                    $('.btn-play, .btn-delete, .btn-send').prop('disabled', false);
                    break;

                case 'playing':
                    $('.btn-play').prop('disabled', true);
                    $('.btn-pause').prop('disabled', false);
                    break;

                case 'ready':
                default:
                    $('.status-text').text('Ready to record');
                    $('.btn-record').prop('disabled', false);
                    $('.btn-stop, .btn-play, .btn-pause, .btn-delete, .btn-send').prop('disabled', true);
                    break;
            }
        },

        /**
         * Update recorder status
         */
        updateRecorderStatus: function(status) {
            $('.status-text').text(status);
        },

        /**
         * Change quality
         */
        changeQuality: function(e) {
            e.preventDefault();

            const $btn = $(this);
            const quality = $btn.data('quality');

            $('.quality-btn').removeClass('active');
            $btn.addClass('active');

            WPMatchVoiceNotes.recordingQuality = quality;
            WPMatchVoiceNotes.showNotification(`Recording quality set to ${quality}`, 'info');
        },

        /**
         * Load voice notes
         */
        loadVoiceNotes: function(filter = 'all') {
            const $list = $('.voice-notes-list');
            this.showLoadingState($list);

            $.ajax({
                url: wpmatch_voice_notes.rest_url + 'wpmatch/v1/voice-notes/list',
                type: 'GET',
                data: {
                    filter: filter,
                    limit: 20
                },
                headers: {
                    'X-WP-Nonce': wpmatch_voice_notes.nonce
                },
                success: function(response) {
                    if (response.success) {
                        WPMatchVoiceNotes.renderVoiceNotes(response.data);
                    } else {
                        WPMatchVoiceNotes.showError(response.message);
                    }
                },
                error: function() {
                    WPMatchVoiceNotes.showError(wpmatch_voice_notes.strings.load_error);
                }
            });
        },

        /**
         * Render voice notes
         */
        renderVoiceNotes: function(voiceNotes) {
            const $container = $('.voice-notes-list .voice-notes-container');
            if ($container.length === 0) {
                $('.voice-notes-list').append('<div class="voice-notes-container"></div>');
            }

            const $list = $('.voice-notes-container');
            $list.empty();

            if (voiceNotes.length === 0) {
                $list.html(`
                    <div class="no-voice-notes">
                        <p>${wpmatch_voice_notes.strings.no_voice_notes}</p>
                    </div>
                `);
                return;
            }

            voiceNotes.forEach(note => {
                const $item = this.createVoiceNoteItem(note);
                $list.append($item);
            });
        },

        /**
         * Create voice note item
         */
        createVoiceNoteItem: function(note) {
            const isOwn = note.sender_id == wpmatch_voice_notes.current_user_id;
            const duration = this.formatDuration(note.duration);
            const size = this.formatFileSize(note.file_size);

            return $(`
                <div class="voice-note-item ${isOwn ? 'sent' : 'received'}" data-note-id="${note.note_id}">
                    <div class="voice-note-header">
                        <div class="voice-note-sender">
                            <img src="${note.sender_avatar || wpmatch_voice_notes.default_avatar}" alt="${note.sender_name}" class="sender-avatar">
                            <div class="sender-info">
                                <h5>${note.sender_name}</h5>
                                <p>${isOwn ? 'You' : 'Received'}</p>
                            </div>
                        </div>
                        <div class="voice-note-time">${this.formatTime(note.created_at)}</div>
                    </div>

                    <div class="voice-note-player">
                        <div class="player-controls">
                            <button class="player-btn play-btn" data-url="${note.file_url}">
                                <span class="play-icon">▶</span>
                                <span class="pause-icon" style="display: none;">⏸</span>
                            </button>
                            <div class="player-progress">
                                <div class="progress-filled" style="width: 0%"></div>
                                <div class="progress-handle" style="left: 0%"></div>
                            </div>
                            <div class="player-time">0:00 / ${duration}</div>
                        </div>
                        <div class="player-info">
                            <span class="voice-duration">${duration}</span>
                            <span class="voice-size">${size}</span>
                        </div>
                    </div>

                    <div class="voice-note-actions">
                        <button class="action-btn like-btn" data-action="like">
                            <span>👍</span> Like <span class="count">${note.likes || 0}</span>
                        </button>
                        <button class="action-btn heart-btn" data-action="heart">
                            <span>❤️</span> Love <span class="count">${note.hearts || 0}</span>
                        </button>
                        <button class="action-btn download-btn" data-action="download" data-url="${note.file_url}">
                            <span>⬇️</span> Download
                        </button>
                        ${isOwn ? '<button class="action-btn delete-btn" data-action="delete"><span>🗑️</span> Delete</button>' : ''}
                    </div>
                </div>
            `);
        },

        /**
         * Handle player control
         */
        handlePlayerControl: function(e) {
            e.preventDefault();

            const $btn = $(this);
            const $item = $btn.closest('.voice-note-item');
            const audioUrl = $btn.data('url');

            if (!audioUrl) return;

            const audio = WPMatchVoiceNotes.getOrCreateAudio(audioUrl, $item);
            const $playIcon = $btn.find('.play-icon');
            const $pauseIcon = $btn.find('.pause-icon');

            if (audio.paused) {
                // Pause all other audio
                WPMatchVoiceNotes.pauseAllAudio();

                audio.play();
                $playIcon.hide();
                $pauseIcon.show();

                WPMatchVoiceNotes.startProgressTracking($item, audio);
            } else {
                audio.pause();
                $playIcon.show();
                $pauseIcon.hide();
            }
        },

        /**
         * Get or create audio element
         */
        getOrCreateAudio: function(url, $item) {
            let audio = $item.data('audio');

            if (!audio) {
                audio = new Audio(url);
                $item.data('audio', audio);

                audio.addEventListener('ended', () => {
                    const $btn = $item.find('.player-btn');
                    $btn.find('.play-icon').show();
                    $btn.find('.pause-icon').hide();
                    $item.find('.progress-filled').css('width', '0%');
                    $item.find('.progress-handle').css('left', '0%');
                });

                audio.addEventListener('loadedmetadata', () => {
                    const duration = WPMatchVoiceNotes.formatDuration(audio.duration);
                    $item.find('.player-time').text(`0:00 / ${duration}`);
                });
            }

            return audio;
        },

        /**
         * Start progress tracking
         */
        startProgressTracking: function($item, audio) {
            const updateProgress = () => {
                if (audio.paused) return;

                const progress = (audio.currentTime / audio.duration) * 100;
                $item.find('.progress-filled').css('width', progress + '%');
                $item.find('.progress-handle').css('left', progress + '%');

                const currentTime = WPMatchVoiceNotes.formatDuration(audio.currentTime);
                const totalTime = WPMatchVoiceNotes.formatDuration(audio.duration);
                $item.find('.player-time').text(`${currentTime} / ${totalTime}`);

                requestAnimationFrame(updateProgress);
            };

            updateProgress();
        },

        /**
         * Pause all audio
         */
        pauseAllAudio: function() {
            $('.voice-note-item').each(function() {
                const audio = $(this).data('audio');
                if (audio && !audio.paused) {
                    audio.pause();
                    $(this).find('.play-icon').show();
                    $(this).find('.pause-icon').hide();
                }
            });
        },

        /**
         * Seek audio
         */
        seekAudio: function(e) {
            e.preventDefault();

            const $progress = $(this);
            const $item = $progress.closest('.voice-note-item');
            const audio = $item.data('audio');

            if (!audio) return;

            const rect = $progress[0].getBoundingClientRect();
            const clickX = e.clientX - rect.left;
            const width = rect.width;
            const percentage = clickX / width;

            audio.currentTime = audio.duration * percentage;
        },

        /**
         * Handle voice note action
         */
        handleVoiceNoteAction: function(e) {
            e.preventDefault();

            const $btn = $(this);
            const action = $btn.data('action');
            const $item = $btn.closest('.voice-note-item');
            const noteId = $item.data('note-id');

            switch (action) {
                case 'like':
                case 'heart':
                    WPMatchVoiceNotes.reactToVoiceNote(noteId, action, $btn);
                    break;

                case 'download':
                    WPMatchVoiceNotes.downloadVoiceNote($btn.data('url'));
                    break;

                case 'delete':
                    WPMatchVoiceNotes.deleteVoiceNote(noteId, $item);
                    break;
            }
        },

        /**
         * React to voice note
         */
        reactToVoiceNote: function(noteId, reaction, $btn) {
            $.ajax({
                url: wpmatch_voice_notes.rest_url + 'wpmatch/v1/voice-notes/react',
                type: 'POST',
                data: {
                    note_id: noteId,
                    reaction: reaction
                },
                headers: {
                    'X-WP-Nonce': wpmatch_voice_notes.nonce
                },
                success: function(response) {
                    if (response.success) {
                        const $count = $btn.find('.count');
                        $count.text(response.data.count);
                        $btn.addClass(reaction === 'like' ? 'liked' : 'hearted');

                        WPMatchVoiceNotes.showNotification('Reaction added!', 'success');
                    } else {
                        WPMatchVoiceNotes.showError(response.message);
                    }
                },
                error: function() {
                    WPMatchVoiceNotes.showError(wpmatch_voice_notes.strings.reaction_error);
                }
            });
        },

        /**
         * Download voice note
         */
        downloadVoiceNote: function(url) {
            const link = document.createElement('a');
            link.href = url;
            link.download = 'voice_note.webm';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        },

        /**
         * Delete voice note
         */
        deleteVoiceNote: function(noteId, $item) {
            if (!confirm(wpmatch_voice_notes.strings.delete_note_confirm)) return;

            $.ajax({
                url: wpmatch_voice_notes.rest_url + 'wpmatch/v1/voice-notes/delete',
                type: 'POST',
                data: {
                    note_id: noteId
                },
                headers: {
                    'X-WP-Nonce': wpmatch_voice_notes.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $item.fadeOut(() => $item.remove());
                        WPMatchVoiceNotes.showNotification('Voice note deleted', 'success');
                    } else {
                        WPMatchVoiceNotes.showError(response.message);
                    }
                },
                error: function() {
                    WPMatchVoiceNotes.showError(wpmatch_voice_notes.strings.delete_error);
                }
            });
        },

        /**
         * Filter voice notes
         */
        filterVoiceNotes: function(e) {
            e.preventDefault();

            const $btn = $(this);
            const filter = $btn.data('filter');

            $('.filter-btn').removeClass('active');
            $btn.addClass('active');

            WPMatchVoiceNotes.loadVoiceNotes(filter);
        },

        /**
         * Handle keyboard shortcuts
         */
        handleKeyboardShortcuts: function(e) {
            if (e.target.tagName.toLowerCase() === 'input' || e.target.tagName.toLowerCase() === 'textarea') {
                return;
            }

            switch (e.key) {
                case 'r':
                case 'R':
                    e.preventDefault();
                    $('.btn-record').click();
                    break;

                case 's':
                case 'S':
                    e.preventDefault();
                    $('.btn-stop').click();
                    break;

                case ' ':
                    e.preventDefault();
                    $('.btn-play').click();
                    break;
            }
        },

        /**
         * Format duration
         */
        formatDuration: function(seconds) {
            const minutes = Math.floor(seconds / 60);
            const remainingSeconds = Math.floor(seconds % 60);
            return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
        },

        /**
         * Format file size
         */
        formatFileSize: function(bytes) {
            if (bytes === 0) return '0 B';

            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));

            return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
        },

        /**
         * Format time
         */
        formatTime: function(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const diff = now - date;
            const hours = diff / (1000 * 60 * 60);

            if (hours < 1) {
                const minutes = Math.floor(diff / (1000 * 60));
                return `${minutes}m ago`;
            } else if (hours < 24) {
                return `${Math.floor(hours)}h ago`;
            } else {
                return date.toLocaleDateString();
            }
        },

        /**
         * Show loading state
         */
        showLoadingState: function($container) {
            $container.html(`
                <div class="voice-loading">
                    <div class="loading-spinner"></div>
                    <span>${wpmatch_voice_notes.strings.loading}</span>
                </div>
            `);
        },

        /**
         * Show error
         */
        showError: function(message) {
            $('.voice-error').remove();

            const $error = $(`
                <div class="voice-error">
                    <span class="error-icon">⚠️</span>
                    ${message}
                </div>
            `);

            $('.voice-recorder').after($error);

            setTimeout(() => {
                $error.fadeOut(() => $error.remove());
            }, 5000);
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

            // Auto hide after 3 seconds
            setTimeout(() => {
                $notification.fadeOut(() => $notification.remove());
            }, 3000);

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
        WPMatchVoiceNotes.init();
    });

})(jQuery);