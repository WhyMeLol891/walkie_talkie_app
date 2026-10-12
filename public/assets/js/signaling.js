/**
 * HTTP Signaling Client
 * Manages periodic polling, heartbeat maintenance, floor request/release,
 * signaling message dispatch, and auto-reconnection with exponential backoff.
 */
class SignalingClient {
    constructor(config) {
        this.config = config;
        this.signalUrl = config.signalUrl;
        this.token = config.token;
        this.peerId = config.peerId;
        this.channel = config.channel;
        this.nickname = config.nickname;

        this.seq = 0;
        this.isPolling = false;
        this.pollTimer = null;
        this.retryCount = 0;
        this.status = 'connecting'; // connecting, connected, reconnecting, disconnected

        // Callbacks
        this.onPeersUpdated = null;
        this.onSpeakerChanged = null;
        this.onRemoteSignal = null;
        this.onStatusChanged = null;
        this.onHelloCompleted = null;

        this.bindUnloadBeacon();
    }

    setStatus(newStatus) {
        if (this.status !== newStatus) {
            this.status = newStatus;
            if (this.onStatusChanged) {
                this.onStatusChanged(newStatus);
            }
        }
    }

    /**
     * Start signaling session by sending 'hello'
     */
    async start() {
        this.setStatus('connecting');
        try {
            const resp = await this.sendRequest({
                type: 'hello',
                token: this.token
            });

            if (resp && resp.status === 'ok') {
                this.seq = resp.sequence || 0;
                this.setStatus('connected');
                this.retryCount = 0;

                if (this.onHelloCompleted) {
                    this.onHelloCompleted(resp);
                }

                if (this.onPeersUpdated && resp.peers) {
                    this.onPeersUpdated(resp.peers);
                }

                if (this.onSpeakerChanged) {
                    this.onSpeakerChanged(resp.speaker);
                }

                this.startPollingLoop();
            } else {
                console.error('Signaling hello failed:', resp);
                this.setStatus('disconnected');
            }
        } catch (err) {
            console.error('Error in signaling start:', err);
            this.setStatus('reconnecting');
            this.scheduleReconnect();
        }
    }

    /**
     * Main polling loop (Heartbeat & Event Retrieval)
     */
    startPollingLoop() {
        if (this.isPolling) return;
        this.isPolling = true;

        const pollTick = async () => {
            if (!this.isPolling) return;

            try {
                const resp = await this.sendRequest({
                    type: 'poll',
                    token: this.token,
                    seq: this.seq
                });

                if (resp && resp.status === 'ok') {
                    // Update connection state
                    if (this.status !== 'connected') {
                        this.setStatus('connected');
                        this.retryCount = 0;
                    }

                    this.seq = Math.max(this.seq, resp.sequence || 0);

                    // Process incoming events
                    if (Array.isArray(resp.events) && resp.events.length > 0) {
                        this.handleEvents(resp.events);
                    }

                    // Update room state
                    if (this.onSpeakerChanged) {
                        this.onSpeakerChanged(resp.speaker);
                    }

                    if (this.onPeersUpdated && resp.peers) {
                        this.onPeersUpdated(resp.peers);
                    }

                    // Next poll in 900ms
                    this.pollTimer = setTimeout(pollTick, 900);
                } else {
                    this.handlePollFailure();
                }
            } catch (err) {
                this.handlePollFailure();
            }
        };

        this.pollTimer = setTimeout(pollTick, 900);
    }

    handlePollFailure() {
        this.retryCount++;
        this.setStatus('reconnecting');

        // Exponential backoff capped at 6 seconds
        const delay = Math.min(1000 * Math.pow(1.5, this.retryCount), 6000);
        if (this.isPolling) {
            this.pollTimer = setTimeout(() => {
                this.startPollingLoop();
            }, delay);
        }
    }

    scheduleReconnect() {
        this.retryCount++;
        const delay = Math.min(1500 * Math.pow(1.5, this.retryCount), 8000);
        setTimeout(() => {
            this.start();
        }, delay);
    }

    /**
     * Dispatch events received from polling
     */
    handleEvents(events) {
        for (const ev of events) {
            switch (ev.type) {
                case 'signal':
                    if (this.onRemoteSignal && ev.from_peer_id && ev.data) {
                        this.onRemoteSignal(ev.from_peer_id, ev.data);
                    }
                    break;
                case 'speaker_started':
                case 'speaker_stopped':
                    if (this.onSpeakerChanged) {
                        this.onSpeakerChanged(ev.type === 'speaker_started' ? {
                            peer_id: ev.peer_id,
                            nickname: ev.nickname,
                            started_at: ev.timestamp,
                            expires_at: ev.timestamp + ((ev.timeout_ms || 30000) / 1000)
                        } : null);
                    }
                    break;
            }
        }
    }

    /**
     * Request Push-to-Talk communication floor
     */
    async requestPtt() {
        try {
            return await this.sendRequest({
                type: 'ptt_request',
                token: this.token
            });
        } catch (e) {
            return { status: 'error', reason: 'NETWORK_ERROR' };
        }
    }

    /**
     * Release Push-to-Talk communication floor
     */
    async releasePtt() {
        try {
            return await this.sendRequest({
                type: 'ptt_release',
                token: this.token
            });
        } catch (e) {
            return { status: 'error' };
        }
    }

    /**
     * Send WebRTC signal payload to specific peer
     */
    async sendSignal(targetPeerId, data) {
        try {
            return await this.sendRequest({
                type: 'signal',
                token: this.token,
                target_peer_id: targetPeerId,
                data: data
            });
        } catch (e) {
            console.warn('Failed to send signal to ' + targetPeerId, e);
        }
    }

    /**
     * Notify server peer is leaving
     */
    async leave() {
        this.stop();
        try {
            await this.sendRequest({
                type: 'leave',
                token: this.token
            });
        } catch (e) {}
    }

    stop() {
        this.isPolling = false;
        if (this.pollTimer) {
            clearTimeout(this.pollTimer);
            this.pollTimer = null;
        }
        this.setStatus('disconnected');
    }

    /**
     * Generic POST JSON helper
     */
    async sendRequest(data) {
        const response = await fetch(this.signalUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(data)
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        return await response.json();
    }

    /**
     * SendBeacon cleanup on window close or reload
     */
    bindUnloadBeacon() {
        const sendLeaveBeacon = () => {
            if (navigator.sendBeacon) {
                const blob = new Blob([JSON.stringify({
                    type: 'leave',
                    token: this.token
                })], { type: 'application/json' });
                navigator.sendBeacon(this.signalUrl, blob);
            }
        };

        window.addEventListener('beforeunload', sendLeaveBeacon);
        window.addEventListener('pagehide', sendLeaveBeacon);
    }
}

window.SignalingClient = SignalingClient;
