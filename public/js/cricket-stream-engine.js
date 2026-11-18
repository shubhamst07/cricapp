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
        this.onStateChange = options.onStateChange || (() => {});
        this.onError = options.onError || (() => {});
    }

    async start(whipUrl, videoPreviewElement) {
        if (whipUrl) this.whipUrl = whipUrl;
        if (!this.whipUrl) throw new Error('WHIP endpoint URL is required');

        try {
            // 1. Acquire Phone Camera & Microphone
            const constraints = {
                video: {
                    facingMode: { ideal: this.facingMode },
                    width: { ideal: 1280, max: 1920 },
                    height: { ideal: 720, max: 1080 },
                    frameRate: { ideal: 30, max: 30 }
                },
                audio: {
                    echoCancellation: true,
                    noiseSuppression: true,
                    autoGainControl: true
                }
            };

            this.localStream = await navigator.mediaDevices.getUserMedia(constraints);
            if (videoPreviewElement) {
                videoPreviewElement.srcObject = this.localStream;
                videoPreviewElement.muted = true; // Avoid feedback on broadcaster phone
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

            // 5. Wait for ICE gathering to complete so SDP has all host/srflx candidates
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

    async stop() {
        this.isBroadcasting = false;

        // Release MediaMTX WHIP session via DELETE
        if (this.whipSessionUrl) {
            try {
                await fetch(this.whipSessionUrl, { method: 'DELETE' });
            } catch (e) {
                // Ignore network errors on session release
            }
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
        if (this.isBroadcasting && this.whipUrl) {
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
