/**
 * Push-To-Talk (PTT) Controller
 * Manages physical/touch/mouse/keyboard interactions, communication floor requests,
 * transmit timers, and audio hardware synchronization.
 */
class PTTController {
    constructor(elements, options) {
        this.button = elements.button;
        this.ring = elements.ring;
        this.label = elements.label;
        this.icon = elements.icon;
        this.hintText = elements.hintText;

        this.signalingClient = options.signalingClient;
        this.webrtcManager = options.webrtcManager;
        this.audioMonitor = options.audioMonitor;
        this.floorTimeoutMs = options.floorTimeoutMs || 30000;

        this.isPressed = false;
        this.isTransmitting = false;
        this.isChannelBusy = false;
        this.activeSpeaker = null;

        this.floorTimer = null;
        this.countdownInterval = null;
        this.floorTimeRemainingSec = 30;

        this.bindEvents();
    }

    bindEvents() {
        if (!this.button) return;

        // Pointer Events (Unified Mouse, Touch & Stylus)
        this.button.addEventListener('pointerdown', (e) => {
            e.preventDefault();
            this.handlePressStart();
        });

        this.button.addEventListener('pointerup', (e) => {
            e.preventDefault();
            this.handlePressEnd();
        });

        this.button.addEventListener('pointercancel', (e) => {
            e.preventDefault();
            this.handlePressEnd();
        });

        this.button.addEventListener('pointerleave', (e) => {
            if (this.isPressed) {
                e.preventDefault();
                this.handlePressEnd();
            }
        });

        // Touch event fallbacks for mobile browsers
        this.button.addEventListener('touchstart', (e) => {
            e.preventDefault();
            this.handlePressStart();
        }, { passive: false });

        this.button.addEventListener('touchend', (e) => {
            e.preventDefault();
            this.handlePressEnd();
        }, { passive: false });

        this.button.addEventListener('touchcancel', (e) => {
            e.preventDefault();
            this.handlePressEnd();
        }, { passive: false });

        // Keyboard Spacebar Navigation Support
        window.addEventListener('keydown', (e) => {
            // Ignore spacebar if user is typing in an input field
            const activeTag = document.activeElement ? document.activeElement.tagName.toLowerCase() : '';
            if (activeTag === 'input' || activeTag === 'textarea') {
                return;
            }

            if (e.code === 'Space' || e.key === ' ' || e.keyCode === 32) {
                e.preventDefault();
                if (!e.repeat) {
                    this.handlePressStart();
                }
            }
        });

        window.addEventListener('keyup', (e) => {
            const activeTag = document.activeElement ? document.activeElement.tagName.toLowerCase() : '';
            if (activeTag === 'input' || activeTag === 'textarea') {
                return;
            }

            if (e.code === 'Space' || e.key === ' ' || e.keyCode === 32) {
                e.preventDefault();
                this.handlePressEnd();
            }
        });

        // Prevent context menu on long press
        this.button.addEventListener('contextmenu', (e) => e.preventDefault());
    }

    /**
     * User initiates PTT hold
     */
    async handlePressStart() {
        if (this.isPressed) return;
        this.isPressed = true;

        // Quick client-side check if channel is already occupied
        if (this.isChannelBusy && this.activeSpeaker && this.activeSpeaker.peer_id !== this.signalingClient.peerId) {
            this.audioMonitor.playBusyTone();
            this.showBusyFeedback(`Channel is busy (${this.activeSpeaker.nickname} is speaking)`);
            return;
        }

        // Set pending UI
        this.button.setAttribute('aria-pressed', 'true');
        this.label.textContent = 'REQUESTING...';

        // Request communication floor from server (atomic lock verified)
        const response = await this.signalingClient.requestPtt();

        // If user released button while request was in-flight
        if (!this.isPressed) {
            if (response && response.status === 'granted') {
                await this.signalingClient.releasePtt();
            }
            return;
        }

        if (response && response.status === 'granted') {
            this.startTransmission();
        } else {
            // Channel Busy or Denied
            this.isPressed = false;
            this.button.setAttribute('aria-pressed', 'false');
            this.audioMonitor.playBusyTone();
            const speakerName = (response && response.speaker) ? response.speaker : 'Another user';
            this.showBusyFeedback(`CHANNEL BUSY (${speakerName} is speaking)`);
        }
    }

    /**
     * User releases PTT hold
     */
    async handlePressEnd() {
        if (!this.isPressed && !this.isTransmitting) return;
        this.isPressed = false;
        this.button.setAttribute('aria-pressed', 'false');

        if (this.isTransmitting) {
            this.stopTransmission();
            await this.signalingClient.releasePtt();
        } else {
            this.resetButtonUi();
        }
    }

    /**
     * Floor granted -> start talking
     */
    startTransmission() {
        this.isTransmitting = true;

        // Enable microphone
        const micOk = this.webrtcManager.enableMicrophone();
        if (!micOk) {
            this.webrtcManager.showMicWarning('Microphone permission is required to transmit voice.');
        }

        // Play chirp
        this.audioMonitor.playTxStartChirp();

        // Visualizer local stream
        if (this.webrtcManager.localStream) {
            this.audioMonitor.attachStream(this.webrtcManager.localStream);
        }

        // Update UI
        this.ring.classList.add('is-transmitting');
        this.ring.classList.remove('is-busy');
        this.label.textContent = 'TRANSMITTING';
        this.icon.className = 'fa-solid fa-microphone-lines fa-2x text-white';
        this.hintText.textContent = 'You are on the air. Release to listen.';

        // Start countdown timer for maximum speaking duration
        this.startFloorTimer();
    }

    /**
     * Floor released -> stop talking
     */
    stopTransmission() {
        this.isTransmitting = false;

        // Disable microphone
        this.webrtcManager.disableMicrophone();

        // Detach visualizer from local stream
        this.audioMonitor.detachStream();

        // Play Roger Beep
        this.audioMonitor.playRogerBeep();

        // Clear timers
        this.clearFloorTimer();

        // Reset UI
        this.resetButtonUi();
    }

    /**
     * Start 30s timeout countdown
     */
    startFloorTimer() {
        this.clearFloorTimer();
        this.floorTimeRemainingSec = Math.floor(this.floorTimeoutMs / 1000);

        const progressContainer = document.getElementById('floorProgressBarContainer');
        const progressBar = document.getElementById('floorProgressBar');
        const countdownText = document.getElementById('floorCountdownText');

        if (progressContainer) progressContainer.classList.remove('d-none');
        if (countdownText) {
            countdownText.classList.remove('d-none');
            countdownText.textContent = `${this.floorTimeRemainingSec}s remaining`;
        }

        const totalSec = this.floorTimeRemainingSec;

        this.countdownInterval = setInterval(() => {
            this.floorTimeRemainingSec--;
            if (countdownText) {
                countdownText.textContent = `${this.floorTimeRemainingSec}s remaining`;
            }
            if (progressBar) {
                const pct = (this.floorTimeRemainingSec / totalSec) * 100;
                progressBar.style.width = `${pct}%`;
            }

            if (this.floorTimeRemainingSec <= 0) {
                this.handleFloorTimeout();
            }
        }, 1000);
    }

    clearFloorTimer() {
        if (this.countdownInterval) {
            clearInterval(this.countdownInterval);
            this.countdownInterval = null;
        }
        const progressContainer = document.getElementById('floorProgressBarContainer');
        const countdownText = document.getElementById('floorCountdownText');
        if (progressContainer) progressContainer.classList.add('d-none');
        if (countdownText) countdownText.classList.add('d-none');
    }

    handleFloorTimeout() {
        this.clearFloorTimer();
        if (this.isTransmitting) {
            this.stopTransmission();
            this.signalingClient.releasePtt();
            this.showBusyFeedback('Talk time ended (30s limit)');
        }
    }

    /**
     * Update button when another user is speaking
     */
    updateSpeakerState(speaker) {
        this.activeSpeaker = speaker;
        const myPeerId = this.signalingClient.peerId;

        if (speaker && speaker.peer_id !== myPeerId) {
            this.isChannelBusy = true;
            this.ring.classList.add('is-busy');
            this.ring.classList.remove('is-transmitting');
            this.label.textContent = 'CHANNEL BUSY';
            this.icon.className = 'fa-solid fa-lock fa-2x text-warning';
            this.hintText.textContent = `${speaker.nickname} is currently speaking`;
        } else if (!this.isTransmitting) {
            this.isChannelBusy = false;
            this.resetButtonUi();
        }
    }

    resetButtonUi() {
        this.ring.classList.remove('is-transmitting', 'is-busy');
        this.label.textContent = 'HOLD TO TALK';
        this.icon.className = 'fa-solid fa-microphone fa-2x';
        this.hintText.textContent = 'Press & hold button or Spacebar to transmit';
    }

    showBusyFeedback(msg) {
        this.ring.classList.add('is-busy');
        this.label.textContent = 'CHANNEL BUSY';
        this.hintText.textContent = msg;

        setTimeout(() => {
            if (!this.isChannelBusy && !this.isTransmitting) {
                this.resetButtonUi();
            }
        }, 2500);
    }
}

window.PTTController = PTTController;
