/**
 * Main Application Coordinator
 * Connects WebRTC, Signaling, PTT Controller, and Audio Monitor into unified Channel experience.
 */
document.addEventListener('DOMContentLoaded', async () => {
    const config = window.WALKIE_CONFIG;
    if (!config) return;

    // 1. Initialize Audio Level Monitor & FX
    const audioMonitor = new AudioLevelMonitor('audioVisualizerCanvas');
    window.audioMonitor = audioMonitor;

    // 2. Initialize WebRTC Manager
    let signalingClient = null;
    const webrtcManager = new WebRTCManager(config, (targetPeerId, data) => {
        if (signalingClient) {
            signalingClient.sendSignal(targetPeerId, data);
        }
    });
    window.webrtcManager = webrtcManager;

    // 3. Initialize Signaling Client
    signalingClient = new SignalingClient(config);
    window.signalingClient = signalingClient;

    // 4. Initialize PTT Controller
    const pttController = new PTTController({
        button: document.getElementById('pttButton'),
        ring: document.getElementById('pttRing'),
        label: document.getElementById('pttLabel'),
        icon: document.getElementById('pttIcon'),
        hintText: document.getElementById('pttHintText')
    }, {
        signalingClient: signalingClient,
        webrtcManager: webrtcManager,
        audioMonitor: audioMonitor,
        floorTimeoutMs: config.floorTimeoutMs || 30000
    });

    // -------------------------------------------------------------------------
    // Wire Signaling Callbacks
    // -------------------------------------------------------------------------

    // Connection Status updates
    signalingClient.onStatusChanged = (status) => {
        const badge = document.getElementById('connectionBadge');
        const led = document.getElementById('statusBarLed');
        if (!badge || !led) return;

        badge.className = 'wt-badge-status wt-status-' + status;
        badge.textContent = status.toUpperCase();

        led.className = 'wt-led-dot';
        if (status === 'connected') {
            led.classList.add('wt-led-green');
        } else if (status === 'connecting') {
            led.classList.add('wt-led-amber', 'pulse');
        } else if (status === 'reconnecting') {
            led.classList.add('wt-led-amber', 'pulse');
        } else {
            led.classList.add('wt-led-red');
        }
    };

    // Hello Acknowledged -> establish mesh with active peers
    signalingClient.onHelloCompleted = (helloData) => {
        if (helloData.peers) {
            webrtcManager.connectToPeers(helloData.peers);
        }
    };

    // WebRTC Signal Forwarded from remote peer
    signalingClient.onRemoteSignal = (fromPeerId, data) => {
        webrtcManager.handleRemoteSignal(fromPeerId, data);
    };

    // Active Speaker floor changed
    signalingClient.onSpeakerChanged = (speaker) => {
        window.currentSpeakerId = speaker ? speaker.peer_id : null;
        updateSpeakerBanner(speaker, config.peerId);
        pttController.updateSpeakerState(speaker);
        updateParticipantsSpeakingState(speaker);
    };

    // Participants list updated
    signalingClient.onPeersUpdated = (peers) => {
        updateParticipantList(peers, config.peerId, config.maxPeers);
        // Connect to any new peers who recently joined
        webrtcManager.connectToPeers(peers);
    };

    // -------------------------------------------------------------------------
    // UI Helpers
    // -------------------------------------------------------------------------

    function updateSpeakerBanner(speaker, myPeerId) {
        const banner = document.getElementById('speakerBanner');
        const icon = document.getElementById('speakerIcon');
        const text = document.getElementById('speakerText');
        if (!banner || !text) return;

        banner.className = 'wt-speaker-banner mb-3 text-center p-3 rounded-3';

        if (!speaker) {
            banner.classList.add('wt-state-idle');
            icon.innerHTML = '<i class="fa-solid fa-radio text-secondary"></i>';
            text.textContent = 'Waiting for a speaker';
            text.className = 'wt-speaker-text fw-bold text-light';
            audioMonitor.detachStream();
        } else if (speaker.peer_id === myPeerId) {
            banner.classList.add('wt-state-talking-self');
            icon.innerHTML = '<i class="fa-solid fa-tower-broadcast text-danger fa-beat-fade"></i>';
            text.textContent = 'You are transmitting';
            text.className = 'wt-speaker-text fw-bold text-danger';
        } else {
            banner.classList.add('wt-state-talking-other');
            icon.innerHTML = '<i class="fa-solid fa-volume-high text-info fa-fade"></i>';
            text.textContent = `${speaker.nickname} is talking`;
            text.className = 'wt-speaker-text fw-bold text-info';

            // Attach incoming audio stream to visualizer if available
            const remoteAudio = webrtcManager.remoteAudioElements.get(speaker.peer_id);
            if (remoteAudio && remoteAudio.srcObject) {
                audioMonitor.attachStream(remoteAudio.srcObject);
            }
        }
    }

    function updateParticipantsSpeakingState(speaker) {
        document.querySelectorAll('.wt-participant-item').forEach(item => {
            const peerId = item.id.replace('peer-', '');
            const stateEl = item.querySelector('.wt-peer-state');
            const dotEl = item.querySelector('.wt-status-dot');

            if (speaker && speaker.peer_id === peerId) {
                item.classList.add('is-speaking');
                if (stateEl) {
                    stateEl.textContent = 'SPEAKING';
                    stateEl.className = 'wt-peer-state text-danger';
                }
                if (dotEl) {
                    dotEl.className = 'wt-status-dot wt-dot-speaking';
                }
            } else {
                item.classList.remove('is-speaking');
                if (stateEl) {
                    stateEl.textContent = 'LISTENING';
                    stateEl.className = 'wt-peer-state text-success small';
                }
                if (dotEl) {
                    dotEl.className = 'wt-status-dot wt-dot-online';
                }
            }
        });
    }

    function updateParticipantList(peers, myPeerId, maxPeers) {
        const list = document.getElementById('participantList');
        const countBadge = document.getElementById('participantCountBadge');
        const lcdUserCount = document.getElementById('roomUserCount');
        if (!list) return;

        const count = peers.length;
        if (countBadge) countBadge.textContent = `${count} / ${maxPeers}`;
        if (lcdUserCount) lcdUserCount.textContent = `${count} ${count === 1 ? 'USER' : 'USERS'}`;

        list.innerHTML = '';
        peers.forEach(peer => {
            const isMe = peer.peer_id === myPeerId;
            const li = document.createElement('li');
            li.id = `peer-${peer.peer_id}`;
            li.className = 'wt-participant-item d-flex align-items-center justify-content-between p-2 rounded-2 mb-1';

            li.innerHTML = `
                <div class="d-flex align-items-center gap-2">
                    <span class="wt-status-dot wt-dot-online"></span>
                    <span class="fw-semibold text-light">${escapeHtml(peer.nickname)}</span>
                    ${isMe ? '<span class="badge bg-secondary text-uppercase wt-badge-you">YOU</span>' : ''}
                </div>
                <span class="wt-peer-state text-success small">LISTENING</span>
            `;

            list.appendChild(li);
        });

        // Re-apply speaker badge if active
        if (window.currentSpeakerId) {
            updateParticipantsSpeakingState({ peer_id: window.currentSpeakerId });
        }
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // -------------------------------------------------------------------------
    // Deck Controls & Bottom Buttons
    // -------------------------------------------------------------------------

    // Toggle Audio FX / Roger Beep
    const btnAudioFx = document.getElementById('btnToggleAudioFx');
    if (btnAudioFx) {
        btnAudioFx.addEventListener('click', () => {
            audioMonitor.soundFxEnabled = !audioMonitor.soundFxEnabled;
            btnAudioFx.classList.toggle('active', audioMonitor.soundFxEnabled);
            const icon = document.getElementById('audioFxIcon');
            if (icon) {
                icon.className = audioMonitor.soundFxEnabled ? 'fa-solid fa-volume-high' : 'fa-solid fa-volume-xmark';
            }
        });
    }

    // Toggle Incoming Output Mute
    const btnMute = document.getElementById('btnToggleMute');
    if (btnMute) {
        btnMute.addEventListener('click', () => {
            const isMuted = webrtcManager.toggleOutputMute();
            btnMute.classList.toggle('active', isMuted);
            const icon = document.getElementById('muteIcon');
            if (icon) {
                icon.className = isMuted ? 'fa-solid fa-bell-slash text-danger' : 'fa-solid fa-bell';
            }
        });
    }

    // Close Mic Alert Banner
    const btnCloseMicAlert = document.getElementById('btnCloseMicAlert');
    if (btnCloseMicAlert) {
        btnCloseMicAlert.addEventListener('click', () => {
            const banner = document.getElementById('micAlertBanner');
            if (banner) banner.classList.add('d-none');
        });
    }

    // Leave Button
    const leaveForm = document.getElementById('leaveForm');
    if (leaveForm) {
        leaveForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            await signalingClient.leave();
            webrtcManager.destroy();
            leaveForm.submit();
        });
    }

    // -------------------------------------------------------------------------
    // Pre-warm Microphone & Start Signaling
    // -------------------------------------------------------------------------
    try {
        await webrtcManager.initLocalAudio();
    } catch (e) {
        console.warn('Microphone pre-warm error:', e);
    }

    // Start signaling connection
    await signalingClient.start();
});
