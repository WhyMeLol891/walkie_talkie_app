/**
 * AudioLevelMonitor & Sound FX Engine
 * Web Audio API AnalyserNode VU Meter & Synthesized Tactical Radio Audio Effects.
 */
class AudioLevelMonitor {
    constructor(canvasId) {
        this.canvas = document.getElementById(canvasId);
        this.ctx = this.canvas ? this.canvas.getContext('2d') : null;
        this.audioCtx = null;
        this.analyser = null;
        this.sourceNode = null;
        this.animationId = null;
        this.dataArray = null;
        this.soundFxEnabled = true;

        this.initCanvasDimensions();
        window.addEventListener('resize', () => this.initCanvasDimensions());
    }

    initCanvasDimensions() {
        if (!this.canvas) return;
        const rect = this.canvas.getBoundingClientRect();
        this.canvas.width = rect.width * (window.devicePixelRatio || 1);
        this.canvas.height = (rect.height || 60) * (window.devicePixelRatio || 1);
    }

    getAudioContext() {
        if (!this.audioCtx) {
            const AudioContextClass = window.AudioContext || window.webkitAudioContext;
            this.audioCtx = new AudioContextClass();
        }
        if (this.audioCtx.state === 'suspended') {
            this.audioCtx.resume();
        }
        return this.audioCtx;
    }

    /**
     * Attach an audio stream (microphone or incoming peer stream) to the visualizer
     */
    attachStream(stream) {
        try {
            const ctx = this.getAudioContext();
            if (!this.analyser) {
                this.analyser = ctx.createAnalyser();
                this.analyser.fftSize = 64;
                this.analyser.smoothingTimeConstant = 0.75;
                this.dataArray = new Uint8Array(this.analyser.frequencyBinCount);
            }

            if (this.sourceNode) {
                try { this.sourceNode.disconnect(); } catch (e) {}
            }

            this.sourceNode = ctx.createMediaStreamSource(stream);
            this.sourceNode.connect(this.analyser);

            if (!this.animationId) {
                this.startRenderLoop();
            }
        } catch (e) {
            console.warn('Unable to attach audio stream to visualizer:', e);
        }
    }

    detachStream() {
        if (this.sourceNode) {
            try { this.sourceNode.disconnect(); } catch (e) {}
            this.sourceNode = null;
        }
    }

    startRenderLoop() {
        const render = () => {
            this.renderVU();
            this.animationId = requestAnimationFrame(render);
        };
        this.animationId = requestAnimationFrame(render);
    }

    renderVU() {
        if (!this.ctx || !this.canvas) return;
        const width = this.canvas.width;
        const height = this.canvas.height;
        const ctx = this.ctx;

        ctx.clearRect(0, 0, width, height);

        let average = 0;
        if (this.analyser && this.dataArray && this.sourceNode) {
            this.analyser.getByteFrequencyData(this.dataArray);
            let sum = 0;
            for (let i = 0; i < this.dataArray.length; i++) {
                sum += this.dataArray[i];
            }
            average = sum / this.dataArray.length;
        }

        // Draw segmented LED audio bars
        const numBars = 24;
        const barSpacing = 4 * (window.devicePixelRatio || 1);
        const totalSpacing = barSpacing * (numBars - 1);
        const barWidth = (width - totalSpacing) / numBars;
        const activeBars = Math.round((average / 255) * numBars);

        for (let i = 0; i < numBars; i++) {
            const x = i * (barWidth + barSpacing);
            const isActive = i < activeBars;

            // Color scheme: Green (Normal) -> Yellow/Amber (Mid) -> Red (Peak)
            let fillColor = '#101720'; // Inactive background segment
            if (isActive) {
                if (i < numBars * 0.6) {
                    fillColor = '#00ff88'; // Normal green
                } else if (i < numBars * 0.85) {
                    fillColor = '#ffaa00'; // Warning amber
                } else {
                    fillColor = '#ff3344'; // Peak red
                }
            }

            ctx.fillStyle = fillColor;
            ctx.shadowBlur = isActive ? 8 : 0;
            ctx.shadowColor = fillColor;

            // Rounded LED pill
            const barH = height * 0.75;
            const y = (height - barH) / 2;
            this.roundRect(ctx, x, y, barWidth, barH, 2);
            ctx.fill();
        }

        // Reset shadow
        ctx.shadowBlur = 0;

        // Update dB display text if element exists
        const vuDisplay = document.getElementById('vuLevelDisplay');
        if (vuDisplay) {
            if (average > 10) {
                const db = Math.round((average / 255) * 40 - 40);
                vuDisplay.textContent = `${db} dB`;
            } else {
                vuDisplay.textContent = '-42 dB';
            }
        }
    }

    roundRect(ctx, x, y, w, h, r) {
        if (w < 2 * r) r = w / 2;
        if (h < 2 * r) r = h / 2;
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.arcTo(x + w, y, x + w, y + h, r);
        ctx.arcTo(x + w, y + h, x, y + h, r);
        ctx.arcTo(x, y + h, x, y, r);
        ctx.arcTo(x, y, x + w, y, r);
        ctx.closePath();
    }

    /**
     * Synthesize authentic tactical radio tones using Web Audio oscillators
     */
    playTxStartChirp() {
        if (!this.soundFxEnabled) return;
        try {
            const ctx = this.getAudioContext();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();

            osc.type = 'sine';
            osc.frequency.setValueAtTime(850, ctx.currentTime);
            osc.frequency.exponentialRampToValueAtTime(1200, ctx.currentTime + 0.08);

            gain.gain.setValueAtTime(0.12, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.09);

            osc.connect(gain);
            gain.connect(ctx.destination);

            osc.start();
            osc.stop(ctx.currentTime + 0.1);
        } catch (e) {}
    }

    playRogerBeep() {
        if (!this.soundFxEnabled) return;
        try {
            const ctx = this.getAudioContext();
            const now = ctx.currentTime;
            
            // Dual-tone Roger Beep
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();

            osc.type = 'sine';
            osc.frequency.setValueAtTime(1000, now);
            osc.frequency.setValueAtTime(1400, now + 0.08);

            gain.gain.setValueAtTime(0.15, now);
            gain.gain.exponentialRampToValueAtTime(0.01, now + 0.18);

            osc.connect(gain);
            gain.connect(ctx.destination);

            osc.start(now);
            osc.stop(now + 0.2);
        } catch (e) {}
    }

    playBusyTone() {
        if (!this.soundFxEnabled) return;
        try {
            const ctx = this.getAudioContext();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();

            osc.type = 'sawtooth';
            osc.frequency.setValueAtTime(320, ctx.currentTime);

            gain.gain.setValueAtTime(0.2, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.15);

            osc.connect(gain);
            gain.connect(ctx.destination);

            osc.start();
            osc.stop(ctx.currentTime + 0.16);
        } catch (e) {}
    }
}

window.AudioLevelMonitor = AudioLevelMonitor;
