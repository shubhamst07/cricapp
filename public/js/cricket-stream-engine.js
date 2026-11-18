/**
 * LiveCricket MediaMTX Stream Engine
 * High-performance, self-hosted WebRTC WHIP/WHEP & HLS Live Streaming.
 * Zero Agora SDK dependencies, zero monthly per-minute billing, ultra-low latency (< 500ms).
 */

class CricketStreamBroadcaster {
    constructor(options = {}) {
        this.whipUrl = options.whipUrl || '';
        this.pc = null;
        this.localStream = null;
        this.whipSessionUrl = null;
        this.isBroadcasting = false;
        this.facingMode = options.facingMode || 'environment';
        this.micMuted = false;
        this.isVirtualCamera = false;
        this.virtualAnimationId = null;
        this.onStateChange = options.onStateChange || (() => {});
        this.onError = options.onError || (() => {});
    }

    async start(whipUrl, videoPreviewElement) {
        if (whipUrl) this.whipUrl = whipUrl;
        if (!this.whipUrl) throw new Error('WHIP endpoint URL is required');

        try {
            // 1. Acquire Camera & Microphone (or Virtual Cricket Ground Simulator if no hardware found)
            this.localStream = await this.acquireMediaStream();

            if (videoPreviewElement) {
                videoPreviewElement.srcObject = this.localStream;
                videoPreviewElement.muted = true; // Avoid audio feedback
                await videoPreviewElement.play().catch(() => {});
            }

            // 2. Setup RTCPeerConnection with Google STUN servers
            this.pc = new RTCPeerConnection({
                iceServers: [
                    { urls: 'stun:stun.l.google.com:19302' },
                    { urls: 'stun:stun1.l.google.com:19302' }
                ],
                bundlePolicy: 'max-bundle'
            });

            this.pc.onconnectionstatechange = () => {
                const state = this.pc ? this.pc.connectionState : 'closed';
                this.onStateChange(state);
            };

            // 3. Add audio and video tracks to WebRTC peer connection
            this.localStream.getTracks().forEach(track => {
                this.pc.addTrack(track, this.localStream);
            });

            // 4. Create SDP offer and set local description
            const offer = await this.pc.createOffer();
            await this.pc.setLocalDescription(offer);

            // 5. Wait for ICE gathering to complete so SDP has all candidates
            await this._waitForIceGathering(this.pc);

            // 6. POST SDP offer to MediaMTX WHIP endpoint
            const res = await fetch(this.whipUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/sdp'
                },
                body: this.pc.localDescription.sdp
            });

            if (!res.ok) {
                const errText = await res.text().catch(() => '');
                throw new Error(`MediaMTX WHIP publish rejected (${res.status}): ${errText}`);
            }

            const answerSdp = await res.text();
            await this.pc.setRemoteDescription(new RTCSessionDescription({
                type: 'answer',
                sdp: answerSdp
            }));

            // Store session URL from Location header for clean termination
            const loc = res.headers.get('Location');
            if (loc) {
                this.whipSessionUrl = new URL(loc, this.whipUrl).href;
            } else {
                this.whipSessionUrl = this.whipUrl;
            }

            this.isBroadcasting = true;
            this.onStateChange('connected');
            return this.localStream;
        } catch (err) {
            this.stop();
            this.onError(err);
            throw err;
        }
    }

    async acquireMediaStream() {
        const constraints = {
            video: {
                facingMode: { ideal: this.facingMode },
                width: { ideal: 1280, max: 1920 },
                height: { ideal: 720, max: 1080 },
                frameRate: { ideal: 30, max: 30 }
            },
            audio: !this.micMuted ? {
                echoCancellation: true,
                noiseSuppression: true,
                autoGainControl: true
            } : false
        };

        // Check media devices availability
        let hasVideoInput = true;
        let hasAudioInput = true;
        if (navigator.mediaDevices && navigator.mediaDevices.enumerateDevices) {
            try {
                const devs = await navigator.mediaDevices.enumerateDevices();
                hasVideoInput = devs.some(d => d.kind === 'videoinput');
                hasAudioInput = devs.some(d => d.kind === 'audioinput');
            } catch (e) {
                // If permission is not yet granted, enumerateDevices might return empty labels
            }
        }

        // If no physical camera exists on device, directly use Virtual Cricket Simulator
        if (!hasVideoInput) {
            console.info('No physical webcam/camera detected on this device. Launching Virtual Cricket Ground Camera Simulator...');
            this.isVirtualCamera = true;
            return this.createVirtualCricketCameraStream();
        }

        try {
            // 1. Try full constraints (video + audio)
            return await navigator.mediaDevices.getUserMedia(constraints);
        } catch (firstErr) {
            console.warn('Initial getUserMedia notice:', firstErr.name, firstErr.message);

            // 2. Try looser video constraints if facingMode or resolution failed
            if (firstErr.name === 'OverconstrainedError' || firstErr.name === 'NotFoundError' || firstErr.name === 'NotReadableError') {
                try {
                    return await navigator.mediaDevices.getUserMedia({
                        video: true,
                        audio: hasAudioInput && !this.micMuted
                    });
                } catch (secondErr) {
                    // 3. Try video-only (in case mic was unplugged or denied)
                    try {
                        const vidStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
                        this.micMuted = true;
                        return vidStream;
                    } catch (thirdErr) {
                        // 4. If all physical camera attempts fail with NotFoundError, fall back to Virtual Cricket Simulator!
                        if (thirdErr.name === 'NotFoundError' || thirdErr.name === 'DevicesNotFoundError' || thirdErr.message?.includes('not found')) {
                            console.warn('Physical camera could not be opened. Using Virtual Cricket Camera Simulator fallback.');
                            this.isVirtualCamera = true;
                            return this.createVirtualCricketCameraStream();
                        }
                        throw thirdErr;
                    }
                }
            } else if (firstErr.name === 'NotAllowedError' || firstErr.name === 'PermissionDeniedError') {
                throw new Error('Camera permission was denied. Please allow camera access in your browser address bar.');
            }

            throw firstErr;
        }
    }

    createVirtualCricketCameraStream() {
        const canvas = document.createElement('canvas');
        canvas.width = 1280;
        canvas.height = 720;
        const ctx = canvas.getContext('2d');

        let frame = 0;
        const drawSimulator = () => {
            frame++;

            // Draw lush cricket ground outfield gradient
            const grad = ctx.createLinearGradient(0, 0, 0, 720);
            grad.addColorStop(0, '#0a2318');
            grad.addColorStop(0.3, '#166534');
            grad.addColorStop(0.7, '#15803d');
            grad.addColorStop(1, '#052e16');
            ctx.fillStyle = grad;
            ctx.fillRect(0, 0, 1280, 720);

            // Draw Outfield mowed grass strip patterns
            for (let i = 0; i < 1280; i += 80) {
                if ((i / 80) % 2 === 0) {
                    ctx.fillStyle = 'rgba(255, 255, 255, 0.04)';
                    ctx.fillRect(i, 0, 40, 720);
                }
            }

            // Draw Cricket Pitch in perspective
            ctx.fillStyle = '#c2a677';
            ctx.beginPath();
            ctx.moveTo(480, 180);
            ctx.lineTo(800, 180);
            ctx.lineTo(950, 720);
            ctx.lineTo(330, 720);
            ctx.closePath();
            ctx.fill();

            // Pitch texture
            ctx.strokeStyle = '#a48450';
            ctx.lineWidth = 1;
            for (let p = 220; p < 700; p += 60) {
                ctx.beginPath();
                ctx.moveTo(400, p);
                ctx.lineTo(880, p);
                ctx.stroke();
            }

            // Batting Crease lines
            ctx.strokeStyle = '#ffffff';
            ctx.lineWidth = 5;
            ctx.beginPath();
            ctx.moveTo(380, 560);
            ctx.lineTo(900, 560);
            ctx.stroke();

            // Bowling Crease lines
            ctx.beginPath();
            ctx.moveTo(490, 240);
            ctx.lineTo(790, 240);
            ctx.stroke();

            // Wickets / Stumps (Far end)
            ctx.fillStyle = '#ffffff';
            for (let s = 620; s <= 660; s += 15) {
                ctx.fillRect(s, 195, 4, 30);
            }
            ctx.fillRect(618, 193, 48, 4); // Bails

            // Wickets / Stumps (Near end)
            for (let s = 610; s <= 670; s += 20) {
                ctx.fillRect(s, 510, 8, 55);
            }
            ctx.fillRect(606, 506, 70, 6); // Bails

            // Animated Cricket Ball bouncing on pitch
            const progress = (frame % 120) / 120;
            const ballX = 640 + (progress - 0.5) * 140;
            const bounce = Math.abs(Math.sin(progress * Math.PI * 2));
            const ballY = 240 + progress * 320 - bounce * 60;
            const ballRadius = 10 + progress * 8;

            // Ball shadow
            ctx.fillStyle = 'rgba(0, 0, 0, 0.35)';
            ctx.beginPath();
            ctx.ellipse(ballX, 240 + progress * 320, ballRadius * 1.1, ballRadius * 0.4, 0, 0, Math.PI * 2);
            ctx.fill();

            // Red Cricket Ball
            ctx.fillStyle = '#dc2626';
            ctx.beginPath();
            ctx.arc(ballX, ballY, ballRadius, 0, Math.PI * 2);
            ctx.fill();
            ctx.strokeStyle = '#ffffff';
            ctx.lineWidth = 2;
            ctx.stroke();

            // Seam of ball
            ctx.strokeStyle = '#ffffff';
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.arc(ballX, ballY, ballRadius * 0.75, 0, Math.PI);
            ctx.stroke();

            // Simulator Overlay Card
            ctx.fillStyle = 'rgba(15, 23, 42, 0.9)';
            ctx.beginPath();
            if (ctx.roundRect) {
                ctx.roundRect(40, 40, 600, 120, 18);
            } else {
                ctx.rect(40, 40, 600, 120);
            }
            ctx.fill();
            ctx.strokeStyle = 'rgba(239, 68, 68, 0.8)';
            ctx.lineWidth = 2;
            ctx.stroke();

            // Status text
            ctx.fillStyle = '#ef4444';
            ctx.font = 'bold 20px system-ui, sans-serif';
            ctx.fillText('🔴 CRICKET GROUND SIMULATOR LIVE', 65, 76);

            ctx.fillStyle = '#f8fafc';
            ctx.font = 'bold 13px system-ui, sans-serif';
            ctx.fillText('✓ PC Simulator Active: Real WebRTC WHIP Stream Transmitting', 65, 104);

            ctx.fillStyle = '#94a3b8';
            ctx.font = '12px system-ui, sans-serif';
            ctx.fillText('📱 Open this match URL on your mobile phone to broadcast with phone camera!', 65, 130);

            if (this.isBroadcasting) {
                this.virtualAnimationId = requestAnimationFrame(drawSimulator);
            }
        };

        this.isBroadcasting = true;
        drawSimulator();

        // Capture canvas stream at 30 fps
        const videoStream = canvas.captureStream(30);

        // Add silent audio track via WebAudio
        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (AudioCtx) {
                const audioCtx = new AudioCtx();
                const osc = audioCtx.createOscillator();
                const gain = audioCtx.createGain();
                gain.gain.value = 0.0001;
                osc.connect(gain);
                const dest = audioCtx.createMediaStreamDestination();
                gain.connect(dest);
                osc.start();
                dest.stream.getAudioTracks().forEach(track => videoStream.addTrack(track));
            }
        } catch (e) {
            console.warn('Silent audio generator notice:', e);
        }

        return videoStream;
    }

    async stop() {
        this.isBroadcasting = false;

        if (this.virtualAnimationId) {
            cancelAnimationFrame(this.virtualAnimationId);
            this.virtualAnimationId = null;
        }

        // Release MediaMTX WHIP session via DELETE
        if (this.whipSessionUrl) {
            try {
                await fetch(this.whipSessionUrl, { method: 'DELETE' });
            } catch (e) {}
            this.whipSessionUrl = null;
        }

        // Close peer connection
        if (this.pc) {
            try { this.pc.close(); } catch (e) {}
            this.pc = null;
        }

        // Stop camera and mic tracks
        if (this.localStream) {
            this.localStream.getTracks().forEach(track => {
                try { track.stop(); } catch (e) {}
            });
            this.localStream = null;
        }

        this.onStateChange('stopped');
    }

    toggleMute() {
        if (!this.localStream) return false;
        this.micMuted = !this.micMuted;
        this.localStream.getAudioTracks().forEach(track => {
            track.enabled = !this.micMuted;
        });
        return this.micMuted;
    }

    async flipCamera(videoPreviewElement) {
        this.facingMode = (this.facingMode === 'environment') ? 'user' : 'environment';
        if (this.isBroadcasting && this.whipUrl && !this.isVirtualCamera) {
            await this.stop();
            await this.start(this.whipUrl, videoPreviewElement);
        }
        return this.facingMode;
    }

    _waitForIceGathering(pc) {
        return new Promise((resolve) => {
            if (pc.iceGatheringState === 'complete') {
                resolve();
            } else {
                const check = () => {
                    if (pc.iceGatheringState === 'complete') {
                        pc.removeEventListener('icegatheringstatechange', check);
                        resolve();
                    }
                };
                pc.addEventListener('icegatheringstatechange', check);
                setTimeout(resolve, 800); // 800ms safety timeout
            }
        });
    }
}

class CricketStreamViewer {
    constructor(options = {}) {
        this.whepUrl = options.whepUrl || '';
        this.hlsUrl = options.hlsUrl || '';
        this.pc = null;
        this.hlsInstance = null;
        this.mode = 'idle'; // 'webrtc', 'hls', 'idle'
        this.videoElement = null;
        this.onModeChange = options.onModeChange || (() => {});
        this.onError = options.onError || (() => {});
    }

    async play(whepUrl, hlsUrl, videoElement) {
        if (whepUrl) this.whepUrl = whepUrl;
        if (hlsUrl) this.hlsUrl = hlsUrl;
        if (videoElement) this.videoElement = videoElement;

        if (!this.videoElement) throw new Error('Video element is required for playback');

        // Stop any existing stream
        this.stop();

        // 1. Attempt Sub-second WebRTC via WHEP first
        if (this.whepUrl && window.RTCPeerConnection) {
            try {
                await this._playWhep(this.whepUrl, this.videoElement);
                this.mode = 'webrtc';
                this.onModeChange('webrtc');
                return;
            } catch (whepErr) {
                console.warn('WHEP WebRTC playback notice, initiating HLS fallback:', whepErr);
            }
        }

        // 2. Automatic Fallback to HLS
        if (this.hlsUrl) {
            this._playHls(this.hlsUrl, this.videoElement);
            this.mode = 'hls';
            this.onModeChange('hls');
        } else {
            throw new Error('Neither WebRTC WHEP nor HLS streams could be connected.');
        }
    }

    async _playWhep(whepUrl, videoEl) {
        this.pc = new RTCPeerConnection({
            iceServers: [
                { urls: 'stun:stun.l.google.com:19302' },
                { urls: 'stun:stun1.l.google.com:19302' }
            ],
            bundlePolicy: 'max-bundle'
        });

        this.pc.addTransceiver('video', { direction: 'recvonly' });
        this.pc.addTransceiver('audio', { direction: 'recvonly' });

        this.pc.ontrack = (event) => {
            if (videoEl && event.streams && event.streams[0]) {
                videoEl.srcObject = event.streams[0];
                videoEl.play().catch(e => console.log('Autoplay caught:', e));
            }
        };

        const offer = await this.pc.createOffer();
        await this.pc.setLocalDescription(offer);

        await this._waitForIceGathering(this.pc);

        const res = await fetch(whepUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/sdp'
            },
            body: this.pc.localDescription.sdp
        });

        if (!res.ok) {
            throw new Error(`WHEP endpoint returned status ${res.status}`);
        }

        const answerSdp = await res.text();
        await this.pc.setRemoteDescription(new RTCSessionDescription({
            type: 'answer',
            sdp: answerSdp
        }));
    }

    _playHls(hlsUrl, videoEl) {
        if (videoEl.canPlayType('application/vnd.apple.mpegurl')) {
            // Native Safari / iOS HLS
            videoEl.src = hlsUrl;
            videoEl.play().catch(e => console.log('Native HLS play error:', e));
        } else if (typeof Hls !== 'undefined' && Hls.isSupported()) {
            this.hlsInstance = new Hls({
                enableWorker: true,
                lowLatencyMode: true,
                backBufferLength: 30
            });
            this.hlsInstance.loadSource(hlsUrl);
            this.hlsInstance.attachMedia(videoEl);
            this.hlsInstance.on(Hls.Events.MANIFEST_PARSED, () => {
                videoEl.play().catch(e => console.log('HLS play error:', e));
            });
            this.hlsInstance.on(Hls.Events.ERROR, (event, data) => {
                if (data.fatal) {
                    console.warn('HLS fatal error:', data);
                }
            });
        } else {
            console.error('HLS is not supported in this browser environment.');
        }
    }

    stop() {
        if (this.pc) {
            try { this.pc.close(); } catch (e) {}
            this.pc = null;
        }

        if (this.hlsInstance) {
            try { this.hlsInstance.destroy(); } catch (e) {}
            this.hlsInstance = null;
        }

        if (this.videoElement) {
            if (this.videoElement.srcObject) {
                this.videoElement.srcObject = null;
            }
            if (this.videoElement.src) {
                this.videoElement.removeAttribute('src');
                this.videoElement.load();
            }
        }

        this.mode = 'idle';
        this.onModeChange('idle');
    }

    _waitForIceGathering(pc) {
        return new Promise((resolve) => {
            if (pc.iceGatheringState === 'complete') {
                resolve();
            } else {
                const check = () => {
                    if (pc.iceGatheringState === 'complete') {
                        pc.removeEventListener('icegatheringstatechange', check);
                        resolve();
                    }
                };
                pc.addEventListener('icegatheringstatechange', check);
                setTimeout(resolve, 800);
            }
        });
    }
}

// Attach globally
window.CricketStreamBroadcaster = CricketStreamBroadcaster;
window.CricketStreamViewer = CricketStreamViewer;
