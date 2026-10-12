/**
 * WebRTC Audio Mesh Manager
 * Handles microphone acquisition, RTCPeerConnection mesh topology,
 * audio track muting/unmuting for floor control, and remote audio playback.
 */
class WebRTCManager {
    constructor(config, onSignalCallback) {
        this.config = config;
        this.onSignal = onSignalCallback; // Function to dispatch signal payloads to other peers
        this.localStream = null;
        this.localAudioTrack = null;
        this.peerConnections = new Map(); // peerId -> RTCPeerConnection
        this.remoteAudioElements = new Map(); // peerId -> HTMLAudioElement
        this.iceCandidatesQueue = new Map(); // peerId -> RTCIceCandidate[]
        this.micPermissionGranted = false;
        this.isMuted = false; // Output mute
        this.iceServers = config.iceServers && config.iceServers.length > 0
            ? config.iceServers
            : [{ urls: 'stun:stun.l.google.com:19302' }, { urls: 'stun:stun1.l.google.com:19302' }];

        // Global audio unlock listener on user interaction
        this.bindAudioUnlockListener();
    }

    bindAudioUnlockListener() {
        const unlock = () => {
            this.unlockAllAudio();
        };
        window.addEventListener('pointerdown', unlock, { passive: true });
        window.addEventListener('keydown', unlock, { passive: true });
        window.addEventListener('touchstart', unlock, { passive: true });
    }

    unlockAllAudio() {
        this.remoteAudioElements.forEach(audioEl => {
            if (audioEl && audioEl.paused) {
                audioEl.play().catch(() => {});
            }
        });

        if (window.audioMonitor && window.audioMonitor.audioCtx) {
            if (window.audioMonitor.audioCtx.state === 'suspended') {
                window.audioMonitor.audioCtx.resume().catch(() => {});
            }
        }
    }

    /**
     * Request microphone access early, but keep audio track muted (enabled = false)
     */
    async initLocalAudio() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            console.warn('WebRTC getUserMedia is not supported in this browser environment.');
            this.showMicWarning('Your browser does not support WebRTC audio access.');
            return false;
        }

        try {
            const constraints = {
                audio: {
                    echoCancellation: true,
                    noiseSuppression: true,
                    autoGainControl: true
                },
                video: false
            };

            this.localStream = await navigator.mediaDevices.getUserMedia(constraints);
            const tracks = this.localStream.getAudioTracks();
            if (tracks.length > 0) {
                this.localAudioTrack = tracks[0];
                // Start with microphone disabled until PTT is pressed
                this.localAudioTrack.enabled = false;
                this.micPermissionGranted = true;
                this.updateMicUi(false);

                // Add track to any already created peer connections
                this.syncLocalTrackToConnections();

                return true;
            }
        } catch (err) {
            console.warn('Microphone permission denied or device unavailable:', err);
            this.micPermissionGranted = false;
            this.showMicWarning('Microphone permission is required to talk. You can still listen.');
            this.updateMicUi(false);
            return false;
        }
    }

    syncLocalTrackToConnections() {
        if (!this.localAudioTrack || !this.localStream) return;
        this.peerConnections.forEach(pc => {
            const senders = pc.getSenders();
            const hasTrack = senders.some(s => s.track === this.localAudioTrack);
            if (!hasTrack) {
                try {
                    pc.addTrack(this.localAudioTrack, this.localStream);
                } catch (e) {
                    console.warn('Failed to add track to existing pc:', e);
                }
            }
        });
    }

    updateMicUi(isTransmitting) {
        const text = document.getElementById('micStatusText');
        if (!text) return;
        if (!this.micPermissionGranted) {
            text.textContent = 'MIC BLOCKED';
            text.className = 'wt-lcd-dim text-danger';
        } else if (isTransmitting) {
            text.textContent = 'MIC ON (TX)';
            text.className = 'wt-lcd-dim text-warning fw-bold';
        } else {
            text.textContent = 'MIC STANDBY';
            text.className = 'wt-lcd-dim';
        }
    }

    showMicWarning(msg) {
        const banner = document.getElementById('micAlertBanner');
        const text = document.getElementById('micAlertText');
        if (banner && text) {
            text.textContent = msg;
            banner.classList.remove('d-none');
        }
    }

    /**
     * Enable microphone track when floor is granted
     */
    enableMicrophone() {
        if (this.localAudioTrack && this.micPermissionGranted) {
            this.localAudioTrack.enabled = true;
            this.updateMicUi(true);
            return true;
        }
        return false;
    }

    /**
     * Disable microphone track when floor is released
     */
    disableMicrophone() {
        if (this.localAudioTrack) {
            this.localAudioTrack.enabled = false;
            this.updateMicUi(false);
        }
    }

    /**
     * Create or retrieve peer connection for a remote peer
     */
    getOrCreatePeerConnection(remotePeerId) {
        if (this.peerConnections.has(remotePeerId)) {
            return this.peerConnections.get(remotePeerId);
        }

        const rtcConfig = {
            iceServers: this.iceServers,
            iceCandidatePoolSize: 2
        };

        const pc = new RTCPeerConnection(rtcConfig);

        // Add local track if available
        if (this.localStream && this.localAudioTrack) {
            pc.addTrack(this.localAudioTrack, this.localStream);
        }

        // ICE candidate handler
        pc.onicecandidate = (event) => {
            if (event.candidate && this.onSignal) {
                const candJson = event.candidate.toJSON ? event.candidate.toJSON() : {
                    candidate: event.candidate.candidate,
                    sdpMid: event.candidate.sdpMid,
                    sdpMLineIndex: event.candidate.sdpMLineIndex
                };
                this.onSignal(remotePeerId, {
                    type: 'candidate',
                    candidate: candJson
                });
            }
        };

        // Incoming audio track handler (supports standard streams or fallback MediaStream)
        pc.ontrack = (event) => {
            let stream = (event.streams && event.streams[0]) ? event.streams[0] : new MediaStream([event.track]);
            this.attachRemoteStream(remotePeerId, stream);
        };

        // Connection state logging & cleanup
        pc.onconnectionstatechange = () => {
            if (pc.connectionState === 'failed' || pc.connectionState === 'closed') {
                console.log(`Connection to ${remotePeerId} is ${pc.connectionState}`);
            }
        };

        this.peerConnections.set(remotePeerId, pc);
        return pc;
    }

    /**
     * Connect to all existing peers in the room
     * Deterministic rule: peer with alphabetically higher ID initiates offer
     */
    connectToPeers(peerList) {
        peerList.forEach(peer => {
            const remotePeerId = peer.peer_id;
            if (remotePeerId !== this.config.peerId) {
                // If this client should initiate offer
                if (this.config.peerId > remotePeerId) {
                    this.initiateOffer(remotePeerId);
                } else {
                    // Create passive connection to wait for incoming offer
                    this.getOrCreatePeerConnection(remotePeerId);
                }
            }
        });
    }

    /**
     * Create WebRTC Offer and send to remote peer
     */
    async initiateOffer(remotePeerId) {
        try {
            const pc = this.getOrCreatePeerConnection(remotePeerId);
            const offer = await pc.createOffer({
                offerToReceiveAudio: true,
                offerToReceiveVideo: false
            });
            await pc.setLocalDescription(offer);

            if (this.onSignal) {
                const sdpStr = pc.localDescription.sdp || offer.sdp;
                this.onSignal(remotePeerId, {
                    type: 'offer',
                    sdp: sdpStr
                });
            }
        } catch (err) {
            console.error(`Error creating offer for ${remotePeerId}:`, err);
        }
    }

    /**
     * Handle incoming WebRTC signaling message from remote peer
     */
    async handleRemoteSignal(remotePeerId, data) {
        if (!data || !data.type) return;

        try {
            const pc = this.getOrCreatePeerConnection(remotePeerId);

            if (data.type === 'offer') {
                const sdpStr = typeof data.sdp === 'string' ? data.sdp : (data.sdp && data.sdp.sdp ? data.sdp.sdp : '');
                if (!sdpStr) return;

                await pc.setRemoteDescription(new RTCSessionDescription({
                    type: 'offer',
                    sdp: sdpStr
                }));

                // Process any queued ICE candidates
                if (this.iceCandidatesQueue.has(remotePeerId)) {
                    const queue = this.iceCandidatesQueue.get(remotePeerId);
                    for (const candidate of queue) {
                        try {
                            await pc.addIceCandidate(new RTCIceCandidate(candidate));
                        } catch (e) {}
                    }
                    this.iceCandidatesQueue.delete(remotePeerId);
                }

                const answer = await pc.createAnswer();
                await pc.setLocalDescription(answer);

                if (this.onSignal) {
                    const answerSdpStr = pc.localDescription.sdp || answer.sdp;
                    this.onSignal(remotePeerId, {
                        type: 'answer',
                        sdp: answerSdpStr
                    });
                }
            } else if (data.type === 'answer') {
                const sdpStr = typeof data.sdp === 'string' ? data.sdp : (data.sdp && data.sdp.sdp ? data.sdp.sdp : '');
                if (!sdpStr) return;

                await pc.setRemoteDescription(new RTCSessionDescription({
                    type: 'answer',
                    sdp: sdpStr
                }));

                // Process queued ICE candidates
                if (this.iceCandidatesQueue.has(remotePeerId)) {
                    const queue = this.iceCandidatesQueue.get(remotePeerId);
                    for (const candidate of queue) {
                        try {
                            await pc.addIceCandidate(new RTCIceCandidate(candidate));
                        } catch (e) {}
                    }
                    this.iceCandidatesQueue.delete(remotePeerId);
                }
            } else if (data.type === 'candidate' && data.candidate) {
                if (pc.remoteDescription && pc.remoteDescription.type) {
                    try {
                        await pc.addIceCandidate(new RTCIceCandidate(data.candidate));
                    } catch (e) {}
                } else {
                    // Queue until remote description is ready
                    if (!this.iceCandidatesQueue.has(remotePeerId)) {
                        this.iceCandidatesQueue.set(remotePeerId, []);
                    }
                    this.iceCandidatesQueue.get(remotePeerId).push(data.candidate);
                }
            }
        } catch (err) {
            console.error(`Error handling signal from ${remotePeerId}:`, err);
        }
    }

    /**
     * Attach incoming remote audio stream to an audio playback element
     */
    attachRemoteStream(remotePeerId, stream) {
        let audioEl = this.remoteAudioElements.get(remotePeerId);
        if (!audioEl) {
            audioEl = document.createElement('audio');
            audioEl.autoplay = true;
            audioEl.playsInline = true;
            audioEl.muted = this.isMuted;
            document.body.appendChild(audioEl);
            this.remoteAudioElements.set(remotePeerId, audioEl);
        }
        audioEl.srcObject = stream;
        audioEl.play().catch(() => {});

        // If audio level monitor exists and this peer is currently speaking, attach stream
        if (window.audioMonitor && window.currentSpeakerId === remotePeerId) {
            window.audioMonitor.attachStream(stream);
        }
    }

    /**
     * Remove peer connection when peer leaves
     */
    removePeer(remotePeerId) {
        if (this.peerConnections.has(remotePeerId)) {
            const pc = this.peerConnections.get(remotePeerId);
            try { pc.close(); } catch (e) {}
            this.peerConnections.delete(remotePeerId);
        }

        if (this.remoteAudioElements.has(remotePeerId)) {
            const audioEl = this.remoteAudioElements.get(remotePeerId);
            try {
                audioEl.pause();
                audioEl.srcObject = null;
                audioEl.remove();
            } catch (e) {}
            this.remoteAudioElements.delete(remotePeerId);
        }

        this.iceCandidatesQueue.delete(remotePeerId);
    }

    /**
     * Toggle incoming audio mute
     */
    toggleOutputMute() {
        this.isMuted = !this.isMuted;
        this.remoteAudioElements.forEach(audioEl => {
            audioEl.muted = this.isMuted;
        });
        return this.isMuted;
    }

    /**
     * Clean up all WebRTC media and connections
     */
    destroy() {
        this.disableMicrophone();

        if (this.localStream) {
            this.localStream.getTracks().forEach(t => t.stop());
            this.localStream = null;
        }

        this.peerConnections.forEach(pc => {
            try { pc.close(); } catch (e) {}
        });
        this.peerConnections.clear();

        this.remoteAudioElements.forEach(el => {
            try {
                el.pause();
                el.srcObject = null;
                el.remove();
            } catch (e) {}
        });
        this.remoteAudioElements.clear();
    }
}

window.WebRTCManager = WebRTCManager;
