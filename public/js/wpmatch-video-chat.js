/**
 * WPMatch Video Chat JavaScript
 *
 * Handles WebRTC video calling functionality including peer connections,
 * media streams, signaling, and UI interactions.
 *
 * @package WPMatch
 * @since 1.5.0
 */

(function($) {
    'use strict';

    class WPMatchVideoChat {
        constructor() {
            this.localStream = null;
            this.remoteStream = null;
            this.peerConnection = null;
            this.roomId = null;
            this.callerId = null;
            this.calleeId = null;
            this.isInitiator = false;
            this.callTimer = null;
            this.callDuration = 0;
            this.mediaConstraints = {
                video: {
                    width: { ideal: 1280 },
                    height: { ideal: 720 },
                    facingMode: 'user'
                },
                audio: {
                    echoCancellation: true,
                    noiseSuppression: true,
                    autoGainControl: true
                }
            };

            this.init();
        }

        /**
         * Initialize video chat.
         */
        init() {
            this.bindEvents();
            this.checkForIncomingCalls();
            this.setupBeforeUnload();
        }

        /**
         * Bind UI events.
         */
        bindEvents() {
            // Video call button on profiles.
            $(document).on('click', '.btn-video-call', (e) => {
                e.preventDefault();
                const userId = $(e.currentTarget).data('user-id');
                this.initiateCall(userId);
            });

            // Modal controls.
            $('#wpmatch-video-modal').on('click', '.btn-accept-call', () => {
                this.acceptCall();
            });

            $('#wpmatch-video-modal').on('click', '.btn-decline-call', () => {
                this.declineCall();
            });

            $('#wpmatch-video-modal').on('click', '.btn-end-call', () => {
                this.endCall();
            });

            // Media controls.
            $('#wpmatch-video-modal').on('click', '.btn-audio', () => {
                this.toggleAudio();
            });

            $('#wpmatch-video-modal').on('click', '.btn-video', () => {
                this.toggleVideo();
            });

            $('#wpmatch-video-modal').on('click', '.btn-screen-share', () => {
                this.toggleScreenShare();
            });

            $('#wpmatch-video-modal').on('click', '.btn-switch-camera', () => {
                this.switchCamera();
            });

            $('#wpmatch-video-modal').on('click', '.btn-fullscreen', () => {
                this.toggleFullscreen();
            });

            // Modal controls.
            $('#wpmatch-video-modal').on('click', '.btn-minimize', () => {
                this.minimizeModal();
            });

            $('#wpmatch-video-modal').on('click', '.btn-close-modal', () => {
                if (this.peerConnection) {
                    if (confirm('Are you sure you want to end the call?')) {
                        this.endCall();
                    }
                } else {
                    this.closeModal();
                }
            });
        }

        /**
         * Initiate a video call.
         */
        async initiateCall(calleeId) {
            this.calleeId = calleeId;
            this.isInitiator = true;

            // Show modal in calling state.
            this.showModal('calling');

            try {
                // Get user media first.
                await this.getUserMedia();

                // Make API call to initiate.
                const response = await this.apiCall('/video/call/initiate', {
                    callee_id: calleeId,
                    call_type: 'video'
                });

                if (response.success) {
                    this.roomId = response.data.room_id;
                    this.setupPeerConnection();
                    await this.createOffer();
                } else {
                    this.showError(response.message);
                    this.closeModal();
                }
            } catch (error) {
                console.error('Error initiating call:', error);
                this.showError(wpMatchVideo.strings.permissionDenied);
                this.closeModal();
            }
        }

        /**
         * Accept incoming call.
         */
        async acceptCall() {
            this.isInitiator = false;

            $('#incoming-call').hide();
            $('.video-status .status-text').text(wpMatchVideo.strings.connecting);

            try {
                await this.getUserMedia();

                const response = await this.apiCall('/video/call/accept', {
                    room_id: this.roomId
                });

                if (response.success) {
                    this.setupPeerConnection();
                    // Wait for offer from caller.
                    this.waitForOffer();
                }
            } catch (error) {
                console.error('Error accepting call:', error);
                this.showError(wpMatchVideo.strings.networkError);
                this.closeModal();
            }
        }

        /**
         * Decline incoming call.
         */
        async declineCall() {
            await this.apiCall('/video/call/decline', {
                room_id: this.roomId,
                reason: 'user_declined'
            });

            this.closeModal();
        }

        /**
         * End the call.
         */
        async endCall() {
            if (this.roomId) {
                await this.apiCall('/video/call/end', {
                    room_id: this.roomId,
                    reason: 'user_ended'
                });
            }

            this.cleanup();
            this.showCallEnded();

            setTimeout(() => {
                this.closeModal();
            }, 2000);
        }

        /**
         * Get user media.
         */
        async getUserMedia() {
            try {
                this.localStream = await navigator.mediaDevices.getUserMedia(this.mediaConstraints);

                const localVideo = document.getElementById('local-video');
                if (localVideo) {
                    localVideo.srcObject = this.localStream;
                }
            } catch (error) {
                console.error('Error getting user media:', error);
                throw error;
            }
        }

        /**
         * Set up peer connection.
         */
        setupPeerConnection() {
            const configuration = {
                iceServers: wpMatchVideo.iceServers
            };

            this.peerConnection = new RTCPeerConnection(configuration);

            // Add local stream tracks.
            if (this.localStream) {
                this.localStream.getTracks().forEach(track => {
                    this.peerConnection.addTrack(track, this.localStream);
                });
            }

            // Handle remote stream.
            this.peerConnection.ontrack = (event) => {
                const remoteVideo = document.getElementById('remote-video');
                if (remoteVideo && event.streams[0]) {
                    remoteVideo.srcObject = event.streams[0];
                    this.onCallConnected();
                }
            };

            // Handle ICE candidates.
            this.peerConnection.onicecandidate = (event) => {
                if (event.candidate) {
                    this.sendIceCandidate(event.candidate);
                }
            };

            // Handle connection state changes.
            this.peerConnection.onconnectionstatechange = () => {
                console.log('Connection state:', this.peerConnection.connectionState);

                switch (this.peerConnection.connectionState) {
                    case 'connected':
                        this.onCallConnected();
                        break;
                    case 'disconnected':
                    case 'failed':
                        this.handleConnectionFailure();
                        break;
                    case 'closed':
                        this.cleanup();
                        break;
                }
            };
        }

        /**
         * Create and send offer.
         */
        async createOffer() {
            try {
                const offer = await this.peerConnection.createOffer();
                await this.peerConnection.setLocalDescription(offer);

                await this.apiCall('/video/offer', {
                    room_id: this.roomId,
                    offer: offer
                });

                // Wait for answer.
                this.waitForAnswer();
            } catch (error) {
                console.error('Error creating offer:', error);
            }
        }

        /**
         * Create and send answer.
         */
        async createAnswer(offer) {
            try {
                await this.peerConnection.setRemoteDescription(offer);
                const answer = await this.peerConnection.createAnswer();
                await this.peerConnection.setLocalDescription(answer);

                await this.apiCall('/video/answer', {
                    room_id: this.roomId,
                    answer: answer
                });
            } catch (error) {
                console.error('Error creating answer:', error);
            }
        }

        /**
         * Send ICE candidate.
         */
        async sendIceCandidate(candidate) {
            await this.apiCall('/video/ice-candidate', {
                room_id: this.roomId,
                candidate: candidate
            });
        }

        /**
         * Wait for offer from caller.
         */
        waitForOffer() {
            const checkForOffer = setInterval(async () => {
                // In production, use WebSocket or long polling.
                // For now, polling via transient check.
                const response = await $.get(wpMatchVideo.ajaxUrl, {
                    action: 'wpmatch_get_signal',
                    signal_type: 'offer',
                    room_id: this.roomId,
                    nonce: wpMatchVideo.nonce
                });

                if (response.data && response.data.offer) {
                    clearInterval(checkForOffer);
                    await this.createAnswer(response.data.offer);
                }
            }, 1000);

            // Timeout after 30 seconds.
            setTimeout(() => {
                clearInterval(checkForOffer);
            }, 30000);
        }

        /**
         * Wait for answer from callee.
         */
        waitForAnswer() {
            const checkForAnswer = setInterval(async () => {
                const response = await $.get(wpMatchVideo.ajaxUrl, {
                    action: 'wpmatch_get_signal',
                    signal_type: 'answer',
                    room_id: this.roomId,
                    nonce: wpMatchVideo.nonce
                });

                if (response.data && response.data.answer) {
                    clearInterval(checkForAnswer);
                    await this.peerConnection.setRemoteDescription(response.data.answer);
                }
            }, 1000);

            setTimeout(() => {
                clearInterval(checkForAnswer);
            }, 30000);
        }

        /**
         * Handle call connected.
         */
        onCallConnected() {
            $('#video-placeholder').hide();
            $('.video-status .status-text').text(wpMatchVideo.strings.connected);
            this.startCallTimer();
        }

        /**
         * Start call timer.
         */
        startCallTimer() {
            this.callDuration = 0;
            $('.call-timer').show();

            this.callTimer = setInterval(() => {
                this.callDuration++;
                const minutes = Math.floor(this.callDuration / 60);
                const seconds = this.callDuration % 60;
                $('.call-timer').text(
                    `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`
                );
            }, 1000);
        }

        /**
         * Toggle audio mute.
         */
        toggleAudio() {
            if (!this.localStream) return;

            const audioTracks = this.localStream.getAudioTracks();
            audioTracks.forEach(track => {
                track.enabled = !track.enabled;
            });

            const btn = $('.btn-audio');
            const isMuted = !audioTracks[0].enabled;
            btn.attr('data-muted', isMuted);
            btn.find('i').toggleClass('fa-microphone fa-microphone-slash');
        }

        /**
         * Toggle video on/off.
         */
        toggleVideo() {
            if (!this.localStream) return;

            const videoTracks = this.localStream.getVideoTracks();
            videoTracks.forEach(track => {
                track.enabled = !track.enabled;
            });

            const btn = $('.btn-video');
            const isOff = !videoTracks[0].enabled;
            btn.attr('data-off', isOff);
            btn.find('i').toggleClass('fa-video fa-video-slash');
        }

        /**
         * Toggle screen sharing.
         */
        async toggleScreenShare() {
            if (this.isScreenSharing) {
                this.stopScreenShare();
            } else {
                await this.startScreenShare();
            }
        }

        /**
         * Start screen sharing.
         */
        async startScreenShare() {
            try {
                const screenStream = await navigator.mediaDevices.getDisplayMedia({
                    video: true,
                    audio: false
                });

                const screenTrack = screenStream.getVideoTracks()[0];
                const sender = this.peerConnection.getSenders().find(
                    s => s.track && s.track.kind === 'video'
                );

                if (sender) {
                    sender.replaceTrack(screenTrack);
                }

                screenTrack.onended = () => {
                    this.stopScreenShare();
                };

                this.isScreenSharing = true;
                $('.btn-screen-share').addClass('active');
            } catch (error) {
                console.error('Error sharing screen:', error);
            }
        }

        /**
         * Stop screen sharing.
         */
        stopScreenShare() {
            if (!this.isScreenSharing) return;

            const videoTrack = this.localStream.getVideoTracks()[0];
            const sender = this.peerConnection.getSenders().find(
                s => s.track && s.track.kind === 'video'
            );

            if (sender && videoTrack) {
                sender.replaceTrack(videoTrack);
            }

            this.isScreenSharing = false;
            $('.btn-screen-share').removeClass('active');
        }

        /**
         * Switch camera (mobile).
         */
        async switchCamera() {
            // Implementation for mobile camera switching.
            console.log('Switch camera not implemented');
        }

        /**
         * Toggle fullscreen.
         */
        toggleFullscreen() {
            const modal = document.getElementById('wpmatch-video-modal');

            if (!document.fullscreenElement) {
                modal.requestFullscreen();
                $('.btn-fullscreen i').removeClass('fa-expand').addClass('fa-compress');
            } else {
                document.exitFullscreen();
                $('.btn-fullscreen i').removeClass('fa-compress').addClass('fa-expand');
            }
        }

        /**
         * Check for incoming calls.
         */
        checkForIncomingCalls() {
            // Poll for incoming calls every 5 seconds.
            setInterval(async () => {
                if (this.peerConnection) return; // Already in call.

                // Check for call notifications via API.
                // In production, use WebSocket for real-time.
            }, 5000);
        }

        /**
         * Show incoming call.
         */
        showIncomingCall(callData) {
            this.roomId = callData.room_id;
            this.callerId = callData.caller_id;

            $('#incoming-call .caller-avatar').attr('src', callData.caller_avatar);
            $('#incoming-call .caller-name').text(callData.caller_name);

            this.showModal('incoming');

            // Play ringtone.
            this.playRingtone();
        }

        /**
         * Show modal.
         */
        showModal(state) {
            const modal = $('#wpmatch-video-modal');
            modal.show();

            switch (state) {
                case 'calling':
                    $('#video-placeholder').show();
                    $('#incoming-call').hide();
                    $('.video-status .status-text').text(wpMatchVideo.strings.calling);
                    break;
                case 'incoming':
                    $('#video-placeholder').hide();
                    $('#incoming-call').show();
                    break;
                case 'connected':
                    $('#video-placeholder').hide();
                    $('#incoming-call').hide();
                    $('.video-status .status-text').text(wpMatchVideo.strings.connected);
                    break;
            }
        }

        /**
         * Close modal.
         */
        closeModal() {
            $('#wpmatch-video-modal').hide();
            this.cleanup();
        }

        /**
         * Minimize modal.
         */
        minimizeModal() {
            $('#wpmatch-video-modal').addClass('minimized');
        }

        /**
         * Show call ended message.
         */
        showCallEnded() {
            $('.video-status .status-text').text(wpMatchVideo.strings.callEnded);
        }

        /**
         * Show error message.
         */
        showError(message) {
            $('.video-status .status-text').text(message).addClass('error');
        }

        /**
         * Play ringtone.
         */
        playRingtone() {
            // Implementation for ringtone.
        }

        /**
         * Handle connection failure.
         */
        handleConnectionFailure() {
            this.showError(wpMatchVideo.strings.networkError);
            setTimeout(() => {
                this.endCall();
            }, 3000);
        }

        /**
         * Set up before unload handler.
         */
        setupBeforeUnload() {
            window.addEventListener('beforeunload', () => {
                if (this.peerConnection) {
                    this.endCall();
                }
            });
        }

        /**
         * Clean up resources.
         */
        cleanup() {
            if (this.callTimer) {
                clearInterval(this.callTimer);
            }

            if (this.localStream) {
                this.localStream.getTracks().forEach(track => {
                    track.stop();
                });
                this.localStream = null;
            }

            if (this.peerConnection) {
                this.peerConnection.close();
                this.peerConnection = null;
            }

            const localVideo = document.getElementById('local-video');
            const remoteVideo = document.getElementById('remote-video');

            if (localVideo) localVideo.srcObject = null;
            if (remoteVideo) remoteVideo.srcObject = null;

            this.roomId = null;
            this.callerId = null;
            this.calleeId = null;
            this.isInitiator = false;
            this.callDuration = 0;
        }

        /**
         * Make API call.
         */
        async apiCall(endpoint, data = {}) {
            const response = await $.ajax({
                url: wpMatchVideo.apiUrl + endpoint,
                method: 'POST',
                headers: {
                    'X-WP-Nonce': wpMatchVideo.nonce
                },
                data: JSON.stringify(data),
                contentType: 'application/json'
            });

            return response;
        }
    }

    // Initialize when DOM ready.
    $(document).ready(() => {
        window.wpMatchVideoChat = new WPMatchVideoChat();
    });

})(jQuery);