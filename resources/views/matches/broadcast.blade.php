@extends('layouts.app')

@section('header')
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('matches.show', $match) }}" class="p-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-white/10 dark:hover:bg-white/15 text-slate-700 dark:text-slate-200 transition" title="Back to Scorecard">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>
            <div>
                <span class="text-[11px] font-black uppercase text-red-600 dark:text-rose-400 tracking-wider flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-red-600 animate-ping"></span>
                    {{ $isOwner ? 'CRICHEROES LIVE BROADCAST STUDIO' : 'CRICKET LIVE STREAM • AUDIENCE WATCH' }}
                </span>
                <h2 class="font-black text-xl sm:text-2xl text-slate-900 dark:text-white leading-tight">
                    {{ $match->team1_name }} vs {{ $match->team2_name }}
                </h2>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('matches.show', $match) }}" class="px-3.5 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-white/10 dark:hover:bg-white/20 text-slate-700 dark:text-slate-200 font-bold text-xs border border-slate-200 dark:border-white/10 transition flex items-center gap-1.5">
                <span>📋 Full Scorecard</span>
            </a>
        </div>
    </div>
@endsection

@section('content')
<!-- Agora WebRTC Live Streaming SDK for Real-time In-App Phone Camera Broadcast -->
<script src="https://download.agora.io/sdk/release/AgoraRTC_N-4.20.0.js"></script>

<div class="py-4 sm:py-6 bg-slate-950 min-h-screen text-slate-100 font-sans select-none" x-data="{
    // Broadcast Source ('camera' or 'youtube')
    streamSource: '{{ !empty($match->embed_stream_url) ? 'youtube' : 'camera' }}',
    streamUrlInput: '{{ addslashes($match->stream_url ?? '') }}',
    embedStreamUrl: '{{ addslashes($match->embed_stream_url ?? '') }}',
    editStreamModal: false,
    activeStreamTab: 'camera',

    // In-App WebRTC Camera Broadcast States
    agoraAppId: '{{ $agoraAppId }}',
    agoraChannel: '{{ $agoraChannel }}',
    agoraToken: '{{ $agoraToken ?? '' }}',
    broadcastMode: 'agora', // 'agora' or 'webrtc_p2p'
    isCameraLive: {{ $isCameraLive ? 'true' : 'false' }},
    agoraClient: null,
    localAudioTrack: null,
    localVideoTrack: null,
    hasAgoraStream: false,
    viewerConnected: false,
    streamActive: {{ $isCameraLive ? 'true' : 'false' }},
    isBroadcasting: false,

    // WebRTC Direct P2P States (Zero-Token Fallback for Phone Camera Broadcast)
    viewerId: 'v_' + Math.random().toString(36).substring(2, 9) + '_' + Date.now().toString(36),
    p2pClient: null,
    hasP2pStream: false,
    audienceMuted: true,
    webrtcPeerConnections: {},

    // Live Match Recording & Storage States
    isRecordingStream: false,
    recordingSeconds: 0,
    recordedBytes: 0,
    recordingTimer: null,
    mediaRecorder: null,
    recordedChunks: [],
    showRecordingSavedModal: false,
    savedVideoBlobUrl: null,
    savedVideoDownloadName: '',
    isUploadingRecording: false,
    uploadProgressText: '',
    savedVideoServerUrl: '{{ addslashes($match->stream_url ?? '') }}',
    videoLoadFailed: false,
    isDirectVideo: {{ $match->is_direct_video ? 'true' : 'false' }},

    formatRecordedSize(bytes) {
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    },

    facingMode: 'environment', // 'environment' for rear cricket ground camera, 'user' for selfie
    micMuted: false,
    isBroadcaster: {{ $isOwner ? 'true' : 'false' }},
    showKeypadDrawer: false,
    eventBannerText: '',
    eventBannerType: '', // 'four', 'six', 'wicket', 'over'

    // Live Match Scores
    team1Name: '{{ addslashes($match->team1_name) }}',
    team2Name: '{{ addslashes($match->team2_name) }}',
    totalOvers: {{ (int) ($match->total_overs ?: 20) }},
    activeInning: {{ $activeInningNum }},
    activeBattingTeam: '{{ $activeInningNum == 1 ? addslashes($match->team1_name) : addslashes($match->team2_name) }}',
    activeBowlingTeam: '{{ $activeInningNum == 1 ? addslashes($match->team2_name) : addslashes($match->team1_name) }}',
    matchStatus: '{{ $match->status }}',
    matchResult: '{{ addslashes($match->match_result ?? '') }}',

    scoreRuns: {{ $currentScoreObj ? $currentScoreObj->runs : 0 }},
    scoreWickets: {{ $currentScoreObj ? $currentScoreObj->wickets : 0 }},
    scoreOvers: '{{ $currentScoreObj ? ($currentScoreObj->overs ?? '0.0') : '0.0' }}',

    striker: {{ json_encode($striker) }},
    nonStriker: {{ json_encode($nonStriker) }},
    bowler: {{ json_encode($bowler) }},
    thisOver: {{ json_encode($state['this_over'] ?? []) }},
    commentaryList: {{ json_encode($state['commentary'] ?? []) }},
    showCommentaryDrawer: false,

    // Players Selection Modals
    playerModalOpen: false,
    playerModalMode: 'all', // 'all', 'striker', 'non_striker', 'bowler'
    selectedStrikerId: '{{ $state['striker_id'] ?? '' }}',
    selectedNonStrikerId: '{{ $state['non_striker_id'] ?? '' }}',
    selectedBowlerId: '{{ $state['bowler_id'] ?? '' }}',

    // Wicket Modal
    wicketModalOpen: false,
    wicketHowOut: 'bowled',
    wicketNextBatterId: '',

    // Toast
    toastMsg: '',
    showToast(msg) {
        this.toastMsg = msg;
        setTimeout(() => { this.toastMsg = ''; }, 3200);
    },

    triggerBanner(type, text) {
        this.eventBannerType = type;
        this.eventBannerText = text;
        setTimeout(() => {
            this.eventBannerText = '';
            this.eventBannerType = '';
        }, 3200);
    },

    // Parse YouTube, Facebook, Twitch to Embeddable Player
    parseStreamEmbed(url) {
        if (!url) return '';
        url = url.trim();
        // YouTube
        const ytMatch = url.match(/(?:youtube\.com\/(?:watch\?v=|live\/|embed\/|v\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/i);
        if (ytMatch) {
            return 'https://www.youtube-nocookie.com/embed/' + ytMatch[1] + '?autoplay=1&mute=1&playsinline=1&rel=0';
        }
        // Facebook
        if (url.includes('facebook.com') || url.includes('fb.watch')) {
            return 'https://www.facebook.com/plugins/video.php?href=' + encodeURIComponent(url) + '&show_text=false&autoplay=true';
        }
        // Twitch
        const twitchMatch = url.match(/twitch\.tv\/([a-zA-Z0-9_]+)/i);
        if (twitchMatch) {
            const host = window.location.hostname || 'localhost';
            return 'https://player.twitch.tv/?channel=' + twitchMatch[1] + '&parent=' + host;
        }
        return url;
    },

    async saveStreamUrl() {
        if (!this.streamUrlInput) {
            this.embedStreamUrl = '';
            this.streamSource = 'camera';
            this.editStreamModal = false;
            return;
        }
        this.embedStreamUrl = this.parseStreamEmbed(this.streamUrlInput);
        this.streamSource = 'youtube';
        this.editStreamModal = false;

        try {
            await fetch('{{ route('matches.update', $match) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    _method: 'PUT',
                    team1_name: this.team1Name,
                    team2_name: this.team2Name,
                    match_date: '{{ $match->match_date->format('Y-m-d') }}',
                    status: this.matchStatus,
                    stream_url: this.streamUrlInput
                })
            });
            this.showToast('Live stream URL saved! 🔴');
        } catch(e) {
            console.error(e);
        }
    },

    // Camera Broadcaster Engine (In-App Real-time Live Stream)
    formatRecordingTime(secs) {
        const m = Math.floor(secs / 60).toString().padStart(2, '0');
        const s = (secs % 60).toString().padStart(2, '0');
        return `${m}:${s}`;
    },

    startRecording() {
        if (typeof MediaRecorder === 'undefined') {
            console.warn('MediaRecorder not supported on this browser or device.');
            return;
        }

        let streamToRecord = null;
        if (window.localMediaStream) {
            streamToRecord = window.localMediaStream;
        } else if (this.localVideoTrack) {
            try {
                const tracks = [this.localVideoTrack.getMediaStreamTrack()];
                if (this.localAudioTrack) {
                    tracks.push(this.localAudioTrack.getMediaStreamTrack());
                }
                streamToRecord = new MediaStream(tracks);
            } catch(e) {
                console.warn('Could not extract Agora tracks for recording:', e);
            }
        }

        if (!streamToRecord) {
            console.warn('No active camera media stream available to record.');
            return;
        }

        let options = {
            videoBitsPerSecond: 800000, // 800 Kbps - High compression, crisp 720p/480p cricket video (fits ~20-25 mins under 100 MB!)
            audioBitsPerSecond: 64000   // 64 Kbps - Clear voice commentary
        };
        const candidates = [
            'video/webm;codecs=vp8,opus',
            'video/webm;codecs=vp8',
            'video/webm',
            'video/mp4;codecs=avc1,mp4a.40.2',
            'video/mp4'
        ];
        for (const type of candidates) {
            if (MediaRecorder.isTypeSupported(type)) {
                options.mimeType = type;
                break;
            }
        }

        try {
            this.recordedChunks = [];
            this.recordedBytes = 0;
            this.mediaRecorder = new MediaRecorder(streamToRecord, options);

            this.mediaRecorder.ondataavailable = (event) => {
                if (event.data && event.data.size > 0) {
                    this.recordedChunks.push(event.data);
                    this.recordedBytes += event.data.size;
                }
            };

            this.mediaRecorder.onstop = async () => {
                clearInterval(this.recordingTimer);
                this.isRecordingStream = false;

                if (this.recordedChunks.length === 0) return;

                const mime = this.mediaRecorder.mimeType || 'video/webm';
                const extension = mime.includes('mp4') ? 'mp4' : 'webm';
                const finalBlob = new Blob(this.recordedChunks, { type: mime });

                this.savedVideoBlobUrl = URL.createObjectURL(finalBlob);
                this.savedVideoDownloadName = 'Match_{{ $match->id }}_LiveRecording_' + Date.now() + '.' + extension;
                this.showRecordingSavedModal = true;

                // Auto-upload recording to server so match replay is saved permanently
                await this.uploadRecordingToServer(finalBlob, this.savedVideoDownloadName);
            };

            this.mediaRecorder.start(2000); // 2-second chunks
            this.isRecordingStream = true;
            this.recordingSeconds = 0;
            if (this.recordingTimer) clearInterval(this.recordingTimer);
            this.recordingTimer = setInterval(() => {
                this.recordingSeconds++;
            }, 1000);

            this.showToast('⏺️ Match recording started automatically!');
        } catch(e) {
            console.warn('Failed to start MediaRecorder:', e);
        }
    },

    stopRecording() {
        if (this.mediaRecorder && this.mediaRecorder.state !== 'inactive') {
            try {
                this.mediaRecorder.stop();
            } catch(e) {
                console.warn('Error stopping MediaRecorder:', e);
            }
        }
        clearInterval(this.recordingTimer);
        this.isRecordingStream = false;
    },

    async uploadRecordingToServer(blob, filename) {
        this.isUploadingRecording = true;
        this.uploadProgressText = 'Uploading match recording to server... ⏳';

        try {
            const formData = new FormData();
            formData.append('video', blob, filename);

            const res = await fetch('{{ route('matches.upload-recording', $match) }}', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: formData
            });

            const data = await res.json();
            if (res.ok && data.status === 'success') {
                this.uploadProgressText = '✅ Match video saved to app! Replay is now available to all users.';
                this.savedVideoServerUrl = data.video_url;
                this.isDirectVideo = true;
                this.showToast('📹 Match replay saved to platform!');
            } else {
                this.uploadProgressText = '⚠️ Server upload notice: ' + (data.message || 'Please use download button below to keep your copy.');
            }
        } catch(err) {
            console.error('Upload recording error:', err);
            this.uploadProgressText = '⚠️ Server upload could not complete. You can download the video directly to your phone below!';
        } finally {
            this.isUploadingRecording = false;
        }
    },

    async startCamera() {
        try {
            if (this.isBroadcasting) return;

            // 1. Try Agora WebRTC Live Broadcast (ONLY if BOTH App ID and Token are configured)
            if (typeof AgoraRTC !== 'undefined' && this.agoraAppId && this.agoraToken) {
                try {
                    this.agoraClient = AgoraRTC.createClient({ mode: 'live', codec: 'vp8' });
                    await this.agoraClient.setClientRole('host');
                    await this.agoraClient.join(this.agoraAppId, this.agoraChannel, this.agoraToken || null, 0);

                    const [audioTrack, videoTrack] = await AgoraRTC.createMicrophoneAndCameraTracks(
                        { AEC: true, ANS: true },
                        { facingMode: this.facingMode, encoderConfig: '720p_1' }
                    );
                    this.localAudioTrack = audioTrack;
                    this.localVideoTrack = videoTrack;

                    await this.agoraClient.publish([audioTrack, videoTrack]);

                    const previewDiv = document.getElementById('broadcasterAgoraPreview');
                    if (previewDiv) {
                        videoTrack.play(previewDiv);
                    }
                    this.hasAgoraStream = true;
                    this.broadcastMode = 'agora';
                } catch(agoraErr) {
                    console.warn('Agora WebRTC connect notice (will use Direct WebRTC P2P stream):', agoraErr);
                    this.hasAgoraStream = false;
                    if (this.agoraClient) {
                        try { await this.agoraClient.leave(); } catch(e) {}
                        this.agoraClient = null;
                    }
                }
            }

            // Always acquire local camera media stream (for live preview, recording & Direct WebRTC fallback)
            if (!window.localMediaStream) {
                const constraints = {
                    video: {
                        facingMode: { ideal: this.facingMode },
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    },
                    audio: !this.micMuted
                };
                const stream = await navigator.mediaDevices.getUserMedia(constraints);
                window.localMediaStream = stream;
                const videoElem = document.getElementById('broadcastVideo');
                if (videoElem) {
                    videoElem.srcObject = stream;
                    videoElem.play();
                }
            }

            this.streamActive = true;
            this.isBroadcasting = true;
            this.isCameraLive = true;

            if (!this.hasAgoraStream) {
                this.broadcastMode = 'webrtc_p2p';
            }

            // Start automatic recording of live camera stream
            this.startRecording();

            // Notify backend that Broadcaster is live with camera
            await fetch('{{ route('matches.stream-signal', $match) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ is_camera_live: true, heartbeat: true })
            });

            this.showToast(this.broadcastMode === 'agora' ? '🔴 YOU ARE LIVE! (Agora Cloud Stream)' : '🔴 YOU ARE LIVE! (Direct WebRTC Low Latency Stream)');
        } catch(err) {
            console.error('Camera access error:', err);
            this.showToast('⚠️ Camera permission denied or device not found.');
        }
    },

    async handlePendingViewerOffers(offers) {
        if (!this.streamActive || !window.localMediaStream || this.broadcastMode !== 'webrtc_p2p') return;

        const answersToSend = {};
        for (const [vId, offerData] of Object.entries(offers || {})) {
            if (this.webrtcPeerConnections[vId] && this.webrtcPeerConnections[vId].connectionState === 'connected') {
                continue;
            }

            try {
                const pc = new RTCPeerConnection({
                    iceServers: [
                        { urls: 'stun:stun.l.google.com:19302' },
                        { urls: 'stun:stun1.l.google.com:19302' }
                    ]
                });

                window.localMediaStream.getTracks().forEach(track => {
                    pc.addTrack(track, window.localMediaStream);
                });

                await pc.setRemoteDescription(new RTCSessionDescription({ type: 'offer', sdp: offerData.offer }));
                const answer = await pc.createAnswer();
                await pc.setLocalDescription(answer);

                // Wait for ICE gathering to complete so SDP has all candidates
                await new Promise(resolve => {
                    if (pc.iceGatheringState === 'complete') {
                        resolve();
                    } else {
                        const checkState = () => {
                            if (pc.iceGatheringState === 'complete') {
                                pc.removeEventListener('icegatheringstatechange', checkState);
                                resolve();
                            }
                        };
                        pc.addEventListener('icegatheringstatechange', checkState);
                        setTimeout(resolve, 800);
                    }
                });

                answersToSend[vId] = pc.localDescription.sdp;
                this.webrtcPeerConnections[vId] = pc;
            } catch(e) {
                console.warn('P2P answer error for viewer ' + vId, e);
            }
        }

        if (Object.keys(answersToSend).length > 0) {
            await fetch('{{ route('matches.stream-signal', $match) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ webrtc_answers: answersToSend })
            });
        }
    },

    async stopCamera() {
        try {
            // Stop recording & finalize video
            this.stopRecording();

            // Stop all P2P peer connections
            for (const vId in this.webrtcPeerConnections) {
                try { this.webrtcPeerConnections[vId].close(); } catch(e) {}
            }
            this.webrtcPeerConnections = {};

            if (this.localAudioTrack) {
                this.localAudioTrack.stop();
                this.localAudioTrack.close();
                this.localAudioTrack = null;
            }
            if (this.localVideoTrack) {
                this.localVideoTrack.stop();
                this.localVideoTrack.close();
                this.localVideoTrack = null;
            }
            if (this.agoraClient) {
                await this.agoraClient.leave();
                this.agoraClient = null;
            }
            if (window.localMediaStream) {
                window.localMediaStream.getTracks().forEach(track => track.stop());
                window.localMediaStream = null;
            }
            const videoElem = document.getElementById('broadcastVideo');
            if (videoElem) videoElem.srcObject = null;

            this.streamActive = false;
            this.isBroadcasting = false;
            this.isCameraLive = false;
            this.hasAgoraStream = false;

            // Notify backend that stream ended
            await fetch('{{ route('matches.stream-signal', $match) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ is_camera_live: false })
            });

            this.showToast('⏹️ Broadcast stopped.');
        } catch(e) {
            console.error(e);
        }
    },

    async toggleCameraFlip() {
        this.facingMode = (this.facingMode === 'environment') ? 'user' : 'environment';
        if (this.streamActive) {
            await this.stopCamera();
            await this.startCamera();
        }
    },

    toggleMic() {
        this.micMuted = !this.micMuted;
        if (this.localAudioTrack) {
            this.localAudioTrack.setEnabled(!this.micMuted);
        }
        if (window.localMediaStream) {
            const audioTracks = window.localMediaStream.getAudioTracks();
            audioTracks.forEach(t => t.enabled = !this.micMuted);
        }
        this.showToast(this.micMuted ? '🔇 Microphone muted' : '🎙️ Microphone on');
    },

    // Audience In-App Live Stream Subscriber (Agora Cloud + Direct WebRTC P2P Fallback)
    async connectAudienceStream(data) {
        if (this.viewerConnected || this.isBroadcaster) return;

        const appId = data.agora_app_id || this.agoraAppId;
        const channel = data.agora_channel || this.agoraChannel;
        const token = data.agora_token || this.agoraToken;

        let agoraAttempted = false;
        if (typeof AgoraRTC !== 'undefined' && appId && channel && token) {
            try {
                this.agoraClient = AgoraRTC.createClient({ mode: 'live', codec: 'vp8' });
                await this.agoraClient.setClientRole('audience');

                this.agoraClient.on('user-published', async (user, mediaType) => {
                    await this.agoraClient.subscribe(user, mediaType);
                    if (mediaType === 'video') {
                        const container = document.getElementById('audienceAgoraVideo');
                        if (container) {
                            user.videoTrack.play(container);
                            this.hasAgoraStream = true;
                        }
                    }
                    if (mediaType === 'audio') {
                        try {
                            await user.audioTrack.play();
                            this.audienceMuted = false;
                        } catch(e) {
                            this.audienceMuted = true;
                        }
                    }
                });

                this.agoraClient.on('user-unpublished', (user, mediaType) => {
                    if (mediaType === 'video') {
                        this.hasAgoraStream = false;
                    }
                });

                await this.agoraClient.join(appId, channel, token || null, 0);
                this.viewerConnected = true;
                agoraAttempted = true;
            } catch(e) {
                console.warn('Audience Agora stream connect notice (initiating Direct WebRTC P2P fallback):', e);
                this.agoraClient = null;
                agoraAttempted = false;
            }
        }

        // If Agora is not connected or failed, initiate Direct WebRTC P2P immediately
        if (!agoraAttempted) {
            await this.initP2pAudience();
        }
    },

    async initP2pAudience() {
        if (this.p2pClient) return;

        try {
            const pc = new RTCPeerConnection({
                iceServers: [
                    { urls: 'stun:stun.l.google.com:19302' },
                    { urls: 'stun:stun1.l.google.com:19302' }
                ]
            });
            this.p2pClient = pc;

            pc.addTransceiver('video', { direction: 'recvonly' });
            pc.addTransceiver('audio', { direction: 'recvonly' });

            pc.ontrack = (event) => {
                const videoElem = document.getElementById('audienceP2pVideo');
                if (videoElem && event.streams && event.streams[0]) {
                    videoElem.srcObject = event.streams[0];
                    videoElem.play().catch(e => {
                        console.warn('Autoplay prevented unmuted play, muting video:', e);
                        videoElem.muted = true;
                        videoElem.play();
                        this.audienceMuted = true;
                    });
                    this.hasP2pStream = true;
                }
            };

            const offer = await pc.createOffer();
            await pc.setLocalDescription(offer);

            // Wait for ICE gathering
            await new Promise(resolve => {
                if (pc.iceGatheringState === 'complete') {
                    resolve();
                } else {
                    const checkState = () => {
                        if (pc.iceGatheringState === 'complete') {
                            pc.removeEventListener('icegatheringstatechange', checkState);
                            resolve();
                        }
                    };
                    pc.addEventListener('icegatheringstatechange', checkState);
                    setTimeout(resolve, 800);
                }
            });

            await fetch('{{ route('matches.stream-signal', $match) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ viewer_id: this.viewerId, webrtc_offer: pc.localDescription.sdp })
            });

            this.viewerConnected = true;
        } catch(e) {
            console.error('Failed to init P2P audience stream:', e);
        }
    },

    unmuteAudienceAudio() {
        const videoElem = document.getElementById('audienceP2pVideo');
        if (videoElem) {
            videoElem.muted = false;
            this.audienceMuted = false;
            this.showToast('🔊 Commentary Audio Unmuted');
        }
    },

    toggleFullscreen() {
        const container = document.getElementById('broadcastArenaContainer');
        if (!document.fullscreenElement) {
            container.requestFullscreen().catch(err => alert(err.message));
        } else {
            document.exitFullscreen();
        }
    },

    // Mini Live Scorer Dispatcher
    isRecording: false,
    async recordBall(action, payload = {}) {
        if (this.isRecording) return;
        this.isRecording = true;
        try {
            const res = await fetch('{{ route('matches.ball-by-ball', $match) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify(Object.assign({ action: action }, payload))
            });

            const data = await res.json();
            if (res.ok && data.status === 'success') {
                if (action === 'run') {
                    if (payload.runs === 4) this.triggerBanner('four', '🏏 FOUR RUNS!');
                    else if (payload.runs === 6) this.triggerBanner('six', '💥 MAXIMUM SIX!');
                } else if (action === 'wicket') {
                    this.triggerBanner('wicket', '☝️ WICKET! OUT!');
                }

                if (data.over_completed) {
                    setTimeout(() => { 
                        this.triggerBanner('over', '🔔 OVER COMPLETED');
                        setTimeout(() => { this.openPlayerModal('bowler'); }, 1400);
                    }, 1200);
                }

                this.syncState(data);
                this.showToast(data.message || 'Ball recorded');
            } else {
                this.showToast(data.message || 'Scoring error');
                if (data.needs_openers) {
                    this.openPlayerModal('all');
                } else if (data.needs_new_bowler) {
                    this.openPlayerModal('bowler');
                }
            }
        } catch(e) {
            console.error(e);
        } finally {
            this.isRecording = false;
        }
    },

    openPlayerModal(mode = 'all') {
        this.playerModalMode = mode;
        this.selectedStrikerId = this.striker ? this.striker.id : '{{ $state['striker_id'] ?? '' }}';
        this.selectedNonStrikerId = this.nonStriker ? this.nonStriker.id : '{{ $state['non_striker_id'] ?? '' }}';
        this.selectedBowlerId = this.bowler ? this.bowler.id : '{{ $state['bowler_id'] ?? '' }}';
        this.playerModalOpen = true;
    },

    async submitPlayerSelection() {
        if (this.playerModalMode === 'bowler') {
            if (!this.selectedBowlerId) {
                this.showToast('⚠️ Please select a bowler!');
                return;
            }
            await this.recordBall('select_bowler', { bowler_id: this.selectedBowlerId });
        } else {
            if (!this.selectedStrikerId || !this.selectedNonStrikerId) {
                this.showToast('⚠️ Please select both striker and non-striker!');
                return;
            }
            if (this.selectedStrikerId == this.selectedNonStrikerId) {
                this.showToast('⚠️ Striker and Non-Striker must be different players!');
                return;
            }
            await this.recordBall('select_openers', {
                striker_id: this.selectedStrikerId,
                non_striker_id: this.selectedNonStrikerId,
                bowler_id: this.selectedBowlerId || (this.bowler ? this.bowler.id : null)
            });
        }
        this.playerModalOpen = false;
    },

    async submitWicket() {
        await this.recordBall('wicket', {
            how_out: this.wicketHowOut,
            next_batsman_id: this.wicketNextBatterId || null
        });
        this.wicketModalOpen = false;
        this.wicketNextBatterId = '';
    },

    syncState(data) {
        if (data.score) {
            this.scoreRuns = data.score.runs;
            this.scoreWickets = data.score.wickets;
            this.scoreOvers = data.score.overs;
        }
        if (data.striker) this.striker = data.striker;
        if (data.non_striker) this.nonStriker = data.non_striker;
        if (data.bowler) this.bowler = data.bowler;
        if (data.state && data.state.this_over) this.thisOver = data.state.this_over;
        if (data.state && data.state.commentary) this.commentaryList = data.state.commentary;
        if (data.commentary) this.commentaryList = data.commentary;
        if (data.match) {
            this.matchStatus = data.match.status;
            this.matchResult = data.match.match_result;
        }
    },

    // Background State Poller (Auto-syncs overlay & live stream with broadcaster)
    startPolling() {
        setInterval(async () => {
            try {
                const res = await fetch('{{ route('matches.broadcast.state', $match) }}');
                const data = await res.json();
                if (data.status === 'success') {
                    const prevRuns = this.scoreRuns;
                    const prevWickets = this.scoreWickets;

                    this.scoreRuns = data.current_score ? data.current_score.runs : 0;
                    this.scoreWickets = data.current_score ? data.current_score.wickets : 0;
                    this.scoreOvers = data.current_score ? data.current_score.overs : '0.0';
                    this.striker = data.striker;
                    this.nonStriker = data.nonStriker;
                    this.bowler = data.bowler;
                    this.thisOver = data.this_over || [];
                    if (data.commentary) this.commentaryList = data.commentary;
                    this.matchStatus = data.match_status;
                    this.matchResult = data.match_result;

                    // Broadcaster Heartbeat & P2P Viewer Handling
                    if (this.isBroadcaster && this.streamActive) {
                        // Send live heartbeat
                        fetch('{{ route('matches.stream-signal', $match) }}', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                            body: JSON.stringify({ heartbeat: true })
                        }).catch(() => {});

                        // Answer incoming viewer P2P offers
                        if (data.webrtc_offers) {
                            this.handlePendingViewerOffers(data.webrtc_offers);
                        }
                    }

                    // Sync In-App Phone Camera Live Stream State
                    if (data.is_camera_live) {
                        this.isCameraLive = true;
                        if (!this.isBroadcaster && !this.viewerConnected) {
                            this.connectAudienceStream(data);
                        }

                        // Audience: check for broadcaster's P2P answer
                        if (this.p2pClient && this.p2pClient.signalingState === 'have-local-offer' && data.webrtc_answers && data.webrtc_answers[this.viewerId]) {
                            try {
                                await this.p2pClient.setRemoteDescription(new RTCSessionDescription({
                                    type: 'answer',
                                    sdp: data.webrtc_answers[this.viewerId]
                                }));
                            } catch(ansErr) {
                                console.warn('P2P answer setting error:', ansErr);
                            }
                        }
                    } else {
                        if (!this.isBroadcaster && this.isCameraLive) {
                            this.isCameraLive = false;
                            this.viewerConnected = false;
                            this.hasAgoraStream = false;
                            this.hasP2pStream = false;
                            if (this.agoraClient) {
                                try { await this.agoraClient.leave(); } catch(e) {}
                                this.agoraClient = null;
                            }
                            if (this.p2pClient) {
                                try { this.p2pClient.close(); } catch(e) {}
                                this.p2pClient = null;
                            }
                        }
                    }

                    if (data.embed_stream_url && data.embed_stream_url !== this.embedStreamUrl) {
                        this.embedStreamUrl = data.embed_stream_url;
                    }

                    if (data.stream_url && data.stream_url !== this.savedVideoServerUrl) {
                        this.savedVideoServerUrl = data.stream_url;
                    }
                    if (typeof data.is_direct_video !== 'undefined') {
                        this.isDirectVideo = data.is_direct_video;
                    }

                    // Trigger banner if remote score updated to 4, 6 or Wicket
                    if (this.scoreWickets > prevWickets && prevWickets !== 0) {
                        this.triggerBanner('wicket', '☝️ WICKET! OUT!');
                    } else if (this.scoreRuns - prevRuns === 4) {
                        this.triggerBanner('four', '🏏 FOUR RUNS!');
                    } else if (this.scoreRuns - prevRuns === 6) {
                        this.triggerBanner('six', '💥 MAXIMUM SIX!');
                    }
                }
            } catch(e) {
                // Ignore silent network drops
            }
        }, 1800);
    }
}" x-init="startPolling(); if (!isBroadcaster && isCameraLive) { connectAudienceStream({ agora_app_id: agoraAppId, agora_channel: agoraChannel, agora_token: agoraToken }); } window.addEventListener('beforeunload', () => { if (isBroadcaster && streamActive) { navigator.sendBeacon('{{ route('matches.stream-signal', $match) }}', new Blob([JSON.stringify({ is_camera_live: false, _token: '{{ csrf_token() }}' })], { type: 'application/json' })); } });">

    <!-- Floating Notification -->
    <div x-cloak x-show="toastMsg" x-transition 
         class="fixed top-5 left-1/2 -translate-x-1/2 z-50 bg-slate-900/90 text-white px-5 py-2.5 rounded-full shadow-2xl font-bold text-xs flex items-center gap-2 border border-emerald-500/40 backdrop-blur-md">
        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
        <span x-text="toastMsg"></span>
    </div>

    <!-- Modal: Broadcast Settings & Stream Options (Broadcaster Only) -->
    @if($isOwner)
    <div x-cloak x-show="editStreamModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100">
        <div class="bg-[#131b2e] border border-white/15 rounded-3xl p-6 max-w-lg w-full space-y-4 shadow-2xl">
            <div class="flex items-center justify-between pb-3 border-b border-white/10">
                <div class="flex items-center gap-2 font-black text-white text-base">
                    <span class="text-xl">📡</span>
                    <span>Live Broadcast Studio Settings</span>
                </div>
                <button type="button" @click="editStreamModal = false" class="text-slate-400 hover:text-white font-bold text-sm">✕</button>
            </div>

            <!-- Tabs: Phone Camera vs External Stream -->
            <div class="grid grid-cols-2 gap-2 p-1 bg-black/40 rounded-2xl border border-white/10 text-xs font-bold">
                <button type="button" @click="activeStreamTab = 'camera'"
                        :class="activeStreamTab === 'camera' ? 'bg-red-600 text-white shadow-md' : 'text-slate-400 hover:text-white'"
                        class="py-2 rounded-xl transition flex items-center justify-center gap-1.5">
                    <span>📱 In-App Phone Camera</span>
                </button>
                <button type="button" @click="activeStreamTab = 'youtube'"
                        :class="activeStreamTab === 'youtube' ? 'bg-red-600 text-white shadow-md' : 'text-slate-400 hover:text-white'"
                        class="py-2 rounded-xl transition flex items-center justify-center gap-1.5">
                    <span>🔴 YouTube / FB Live</span>
                </button>
            </div>

            <!-- Tab 1: In-App Camera Details -->
            <div x-show="activeStreamTab === 'camera'" class="space-y-3 text-xs">
                <div class="p-3.5 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 space-y-1">
                    <span class="font-extrabold flex items-center gap-1.5">
                        <span>✓</span>
                        <span>Zero External App Needed!</span>
                    </span>
                    <p class="text-[11px] text-slate-300">
                        Organizers can broadcast live directly from their mobile phone camera (rear ground view or selfie). All users watching will see the real-time video with the TV scoreboard overlay on top.
                    </p>
                </div>

                <div class="p-3 rounded-xl bg-white/5 border border-white/5 text-[11px] space-y-1">
                    <span class="text-slate-400 font-bold uppercase block">Agora WebRTC Cloud Relay:</span>
                    <div class="flex items-center justify-between text-slate-300">
                        <span>Channel Name:</span>
                        <code class="font-mono text-amber-300 font-bold" x-text="agoraChannel"></code>
                    </div>
                    <div class="flex items-center justify-between text-slate-300">
                        <span>Status:</span>
                        <span class="text-emerald-400 font-bold flex items-center gap-1">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                            <span>Ultra-Low Latency Ready (0.3s)</span>
                        </span>
                    </div>
                </div>

                <div class="pt-2">
                    <button type="button" @click="editStreamModal = false; streamSource = 'camera'; if(!streamActive) startCamera();"
                            class="w-full py-2.5 rounded-xl bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-500 text-white font-black text-xs uppercase tracking-wider shadow-lg shadow-red-600/30 transition flex items-center justify-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-white animate-ping"></span>
                        <span x-text="streamActive ? 'Return to Camera Broadcast ➔' : 'Start Phone Camera Live Now 🔴'"></span>
                    </button>
                </div>
            </div>

            <!-- Tab 2: External Stream (YouTube / Facebook / Twitch) -->
            <div x-show="activeStreamTab === 'youtube'" class="space-y-3 text-xs">
                <p class="text-slate-300 leading-relaxed">
                    If you are already streaming on YouTube Live or Facebook Live, paste the video URL below to embed it with the real-time TV scorecard overlay.
                </p>
                <div>
                    <label class="block font-bold text-slate-300 uppercase tracking-wider mb-1">Live Stream URL</label>
                    <input type="url" x-model="streamUrlInput" 
                           placeholder="https://www.youtube.com/watch?v=... or https://facebook.com/.../videos/..."
                           class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2.5 text-xs text-white focus:ring-2 focus:ring-red-500 focus:outline-none">
                </div>
                <div class="p-3 rounded-xl bg-white/5 border border-white/5 text-[11px] text-slate-400 space-y-1">
                    <div class="font-bold text-white">Supported Platforms:</div>
                    <div>• <b>YouTube Live:</b> Watch link (`youtube.com/watch?v=...`) or Share link (`youtu.be/...`)</div>
                    <div>• <b>Facebook Live:</b> Video page link (`facebook.com/.../videos/...`)</div>
                    <div>• <b>Twitch:</b> Channel link (`twitch.tv/...`)</div>
                </div>
                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" @click="editStreamModal = false" class="px-4 py-2 rounded-xl text-slate-400 hover:text-white font-bold text-xs">
                        Cancel
                    </button>
                    <button type="button" @click="saveStreamUrl()" class="px-5 py-2 rounded-xl bg-red-600 hover:bg-red-500 text-white font-bold text-xs shadow-md">
                        Save & Load Stream ➔
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- ============================================================== -->
    <!-- MODAL: SELECT BATSMEN & BOWLER (LIVE BROADCAST STUDIO) -->
    <!-- ============================================================== -->
    @if($isOwner)
    <div x-cloak x-show="playerModalOpen" 
         class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-black/80 backdrop-blur-md"
         @keydown.escape.window="playerModalOpen = false"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95">
        <div class="relative bg-slate-900 border border-white/20 rounded-3xl max-w-md w-full p-6 shadow-2xl space-y-5 text-slate-100"
             @click.away="playerModalOpen = false">
            
            <div class="flex items-center justify-between pb-3 border-b border-white/10">
                <div class="flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-lg bg-emerald-600 text-white font-black flex items-center justify-center text-sm">🏏</span>
                    <div>
                        <h3 class="font-black text-base sm:text-lg text-white" x-text="playerModalMode === 'bowler' ? 'Change Bowler 🎯' : 'Select Batsmen & Bowler 🏏'"></h3>
                        <p class="text-[11px] text-slate-400" x-text="playerModalMode === 'bowler' ? 'Select bowler from {{ addslashes($activeBowlingTeam) }}' : 'Select active players for live TV broadcast overlay'"></p>
                    </div>
                </div>
                <button type="button" @click="playerModalOpen = false" class="text-slate-400 hover:text-white font-bold p-1">✕</button>
            </div>

            <div class="space-y-4 text-xs">
                <!-- Striker Selection (shown if mode !== 'bowler') -->
                <div x-show="playerModalMode !== 'bowler'">
                    <label class="block text-emerald-400 font-extrabold mb-1.5 uppercase tracking-wider text-[10px] flex items-center justify-between">
                        <span>🏏 Striker (Batter 1 - On Strike)</span>
                        <span class="text-slate-400 font-normal">from {{ $activeBattingTeam }}</span>
                    </label>
                    <select x-model="selectedStrikerId" class="w-full bg-slate-950 border border-white/15 focus:border-emerald-500 rounded-xl px-3.5 py-2.5 text-xs text-white">
                        <option value="">-- Choose Striker --</option>
                        @foreach($battingSquad as $p)
                            <option value="{{ $p->id }}">{{ $p->player_name }} ({{ $p->player_role }})</option>
                        @endforeach
                    </select>
                </div>

                <!-- Non-Striker Selection (shown if mode !== 'bowler') -->
                <div x-show="playerModalMode !== 'bowler'">
                    <label class="block text-indigo-300 font-extrabold mb-1.5 uppercase tracking-wider text-[10px] flex items-center justify-between">
                        <span>🏃 Non-Striker (Batter 2)</span>
                        <span class="text-slate-400 font-normal">from {{ $activeBattingTeam }}</span>
                    </label>
                    <select x-model="selectedNonStrikerId" class="w-full bg-slate-950 border border-white/15 focus:border-indigo-500 rounded-xl px-3.5 py-2.5 text-xs text-white">
                        <option value="">-- Choose Non-Striker --</option>
                        @foreach($battingSquad as $p)
                            <option value="{{ $p->id }}">{{ $p->player_name }} ({{ $p->player_role }})</option>
                        @endforeach
                    </select>
                </div>

                <!-- Bowler Selection -->
                <div>
                    <label class="block text-rose-400 font-extrabold mb-1.5 uppercase tracking-wider text-[10px] flex items-center justify-between">
                        <span>🎯 Active Bowler</span>
                        <span class="text-slate-400 font-normal">from {{ $activeBowlingTeam }}</span>
                    </label>
                    <select x-model="selectedBowlerId" class="w-full bg-slate-950 border border-white/15 focus:border-rose-500 rounded-xl px-3.5 py-2.5 text-xs text-white">
                        <option value="">-- Choose Bowler --</option>
                        @foreach($bowlingSquad as $p)
                            <option value="{{ $p->id }}">{{ $p->player_name }} ({{ $p->player_role }})</option>
                        @endforeach
                    </select>
                </div>

                @if($battingSquad->isEmpty() || $bowlingSquad->isEmpty())
                    <div class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-300 text-[11px] flex items-start gap-2">
                        <span class="text-base">ℹ️</span>
                        <div>
                            Players squad empty? Go to <a href="{{ route('matches.show', $match) }}" class="underline font-bold text-white hover:text-amber-200">Full Scorecard</a> to add players to {{ $match->team1_name }} and {{ $match->team2_name }}.
                        </div>
                    </div>
                @endif
            </div>

            <div class="flex items-center justify-end gap-3 pt-3 border-t border-white/10">
                <button type="button" @click="playerModalOpen = false" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-400 hover:text-white">Cancel</button>
                <button type="button" @click="submitPlayerSelection()" 
                        class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-black text-xs shadow-lg shadow-emerald-600/30 transition">
                    Confirm & Update Overlay ⚡
                </button>
            </div>
        </div>
    </div>

    <!-- ============================================================== -->
    <!-- MODAL: WICKET & NEXT BATTER SELECTOR -->
    <!-- ============================================================== -->
    <div x-cloak x-show="wicketModalOpen" 
         class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-black/80 backdrop-blur-md"
         @keydown.escape.window="wicketModalOpen = false"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95">
        <div class="relative bg-slate-900 border border-white/20 rounded-3xl max-w-md w-full p-6 shadow-2xl space-y-5 text-slate-100"
             @click.away="wicketModalOpen = false">
            
            <div class="flex items-center justify-between pb-3 border-b border-white/10">
                <div class="flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-lg bg-rose-600 text-white font-black flex items-center justify-center text-sm">☝️</span>
                    <div>
                        <h3 class="font-black text-base sm:text-lg text-white">Wicket! Dismissal & Next Batter</h3>
                        <p class="text-[11px] text-slate-400">Record dismissal and bring in the new batsman</p>
                    </div>
                </div>
                <button type="button" @click="wicketModalOpen = false" class="text-slate-400 hover:text-white font-bold p-1">✕</button>
            </div>

            <div class="space-y-4 text-xs">
                <!-- How Out -->
                <div>
                    <label class="block text-rose-300 font-extrabold mb-1.5 uppercase tracking-wider text-[10px]">How Out</label>
                    <div class="grid grid-cols-3 gap-1.5">
                        <button type="button" @click="wicketHowOut = 'bowled'" 
                                :class="wicketHowOut === 'bowled' ? 'bg-rose-600 text-white font-black border-rose-500' : 'bg-slate-950 text-slate-300 border-white/10'" 
                                class="py-2 px-2 rounded-xl border text-center transition">Bowled</button>
                        <button type="button" @click="wicketHowOut = 'caught'" 
                                :class="wicketHowOut === 'caught' ? 'bg-rose-600 text-white font-black border-rose-500' : 'bg-slate-950 text-slate-300 border-white/10'" 
                                class="py-2 px-2 rounded-xl border text-center transition">Caught</button>
                        <button type="button" @click="wicketHowOut = 'lbw'" 
                                :class="wicketHowOut === 'lbw' ? 'bg-rose-600 text-white font-black border-rose-500' : 'bg-slate-950 text-slate-300 border-white/10'" 
                                class="py-2 px-2 rounded-xl border text-center transition">LBW</button>
                        <button type="button" @click="wicketHowOut = 'run_out'" 
                                :class="wicketHowOut === 'run_out' ? 'bg-rose-600 text-white font-black border-rose-500' : 'bg-slate-950 text-slate-300 border-white/10'" 
                                class="py-2 px-2 rounded-xl border text-center transition">Run Out</button>
                        <button type="button" @click="wicketHowOut = 'stumped'" 
                                :class="wicketHowOut === 'stumped' ? 'bg-rose-600 text-white font-black border-rose-500' : 'bg-slate-950 text-slate-300 border-white/10'" 
                                class="py-2 px-2 rounded-xl border text-center transition">Stumped</button>
                        <button type="button" @click="wicketHowOut = 'hit_wicket'" 
                                :class="wicketHowOut === 'hit_wicket' ? 'bg-rose-600 text-white font-black border-rose-500' : 'bg-slate-950 text-slate-300 border-white/10'" 
                                class="py-2 px-2 rounded-xl border text-center transition">Hit Wicket</button>
                    </div>
                </div>

                <!-- Next Batsman -->
                <div>
                    <label class="block text-emerald-400 font-extrabold mb-1.5 uppercase tracking-wider text-[10px]">Next Batsman In (from {{ $activeBattingTeam }})</label>
                    <select x-model="wicketNextBatterId" class="w-full bg-slate-950 border border-white/15 focus:border-emerald-500 rounded-xl px-3.5 py-2.5 text-xs text-white">
                        <option value="">-- Choose Next Batsman --</option>
                        @foreach($availableNextBatsmen as $p)
                            <option value="{{ $p->id }}">{{ $p->player_name }} ({{ $p->player_role }})</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-3 border-t border-white/10">
                <button type="button" @click="wicketModalOpen = false" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-400 hover:text-white">Cancel</button>
                <button type="button" @click="submitWicket()" 
                        class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-rose-600 to-red-600 hover:from-rose-500 hover:to-red-500 text-white font-black text-xs shadow-lg shadow-rose-600/30 transition">
                    Confirm Wicket ☝️
                </button>
            </div>
        </div>
    </div>
    @endif

    <!-- ============================================================== -->
    <!-- MODAL: MATCH RECORDING SAVED (REPLAY & DOWNLOAD) -->
    <!-- ============================================================== -->
    <div x-cloak x-show="showRecordingSavedModal" 
         class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-black/80 backdrop-blur-md"
         @keydown.escape.window="showRecordingSavedModal = false"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95">
        <div class="relative bg-slate-900 border border-white/20 rounded-3xl max-w-lg w-full p-6 shadow-2xl space-y-4 text-slate-100"
             @click.away="showRecordingSavedModal = false">
            
            <div class="flex items-center justify-between pb-3 border-b border-white/10">
                <div class="flex items-center gap-2.5">
                    <span class="w-9 h-9 rounded-xl bg-emerald-500/20 text-emerald-400 font-black flex items-center justify-center text-lg border border-emerald-500/30">📹</span>
                    <div>
                        <h3 class="font-black text-base sm:text-lg text-white">Live Broadcast Saved! 🏏</h3>
                        <p class="text-[11px] text-slate-400">Match video recorded & saved directly to this app</p>
                    </div>
                </div>
                <button type="button" @click="showRecordingSavedModal = false" class="text-slate-400 hover:text-white font-bold p-1">✕</button>
            </div>

            <!-- Video Preview -->
            <template x-if="savedVideoBlobUrl">
                <div class="rounded-2xl overflow-hidden bg-black border border-white/10 aspect-video flex items-center justify-center shadow-inner">
                    <video :src="savedVideoBlobUrl" controls playsinline class="w-full h-full object-contain"></video>
                </div>
            </template>

            <!-- Upload & Storage Status -->
            <div class="p-3.5 rounded-2xl bg-white/5 border border-white/10 space-y-1.5 text-xs">
                <div class="flex items-center gap-2">
                    <template x-if="isUploadingRecording">
                        <span class="w-2.5 h-2.5 rounded-full bg-amber-400 animate-ping"></span>
                    </template>
                    <template x-if="!isUploadingRecording">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-400"></span>
                    </template>
                    <span class="font-bold text-slate-200" x-text="uploadProgressText"></span>
                </div>
                <p class="text-[11px] text-slate-400 leading-relaxed">
                    This match video is automatically saved so any viewer can re-watch the full match anytime from the 
                    <a href="{{ route('matches.show', $match) }}" class="underline text-emerald-400 font-bold">Scorecard</a> or Broadcast Arena. You can also save a direct copy to your mobile phone gallery below!
                </p>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row items-center gap-2.5 pt-2">
                <a :href="savedVideoBlobUrl" :download="savedVideoDownloadName" 
                   class="w-full sm:flex-1 py-3 px-4 rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-black text-xs text-center shadow-lg shadow-emerald-600/30 transition flex items-center justify-center gap-2">
                    <span>📥 Download to Phone Gallery</span>
                </a>
                <button type="button" @click="showRecordingSavedModal = false" 
                        class="w-full sm:w-auto px-5 py-3 rounded-2xl bg-white/10 hover:bg-white/20 text-slate-300 hover:text-white font-bold text-xs transition">
                    Done & Close
                </button>
            </div>
        </div>
    </div>

    <div class="max-w-6xl mx-auto px-2 sm:px-4 space-y-4">

        <!-- ============================================================== -->
        <!-- MAIN BROADCAST ARENA CONTAINER (FULLSCREEN READY) -->
        <!-- ============================================================== -->
        <div id="broadcastArenaContainer" 
             class="relative w-full aspect-video sm:rounded-3xl overflow-hidden bg-black shadow-2xl border border-white/10 flex flex-col justify-between">

            <!-- 1. BROADCASTER DEVICE LIVE CAMERA PREVIEW -->
            @if($isOwner)
                <div id="broadcasterAgoraPreview" x-show="streamSource === 'camera' && hasAgoraStream" class="absolute inset-0 w-full h-full object-cover z-0"></div>
                <video x-show="streamSource === 'camera' && !hasAgoraStream && streamActive" id="broadcastVideo" autoplay playsinline muted 
                       class="absolute inset-0 w-full h-full object-cover z-0"></video>
            @endif

            <!-- 2. AUDIENCE LIVE IN-APP CAMERA STREAM (Real-time Video from Organizer's Phone Camera: Agora + Direct WebRTC P2P) -->
            @if(!$isOwner)
                <div x-show="isCameraLive" class="absolute inset-0 w-full h-full z-0 overflow-hidden bg-black flex items-center justify-center">
                    <!-- Agora Cloud Stream Container -->
                    <div id="audienceAgoraVideo" x-show="hasAgoraStream" class="w-full h-full object-cover"></div>

                    <!-- Direct WebRTC P2P Stream Video -->
                    <video id="audienceP2pVideo" x-show="!hasAgoraStream && hasP2pStream" autoplay playsinline muted 
                           class="w-full h-full object-cover"></video>

                    <!-- Stream Connecting / Buffering Loader -->
                    <div x-show="!hasAgoraStream && !hasP2pStream" class="flex flex-col items-center justify-center p-6 text-center z-10">
                        <div class="w-12 h-12 rounded-full border-4 border-red-500 border-t-transparent animate-spin mb-3"></div>
                        <div class="text-sm font-black text-white flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-red-500 animate-ping"></span>
                            <span>Connecting to Live Video Feed...</span>
                        </div>
                        <p class="text-xs text-slate-400 mt-1 max-w-xs">Receiving broadcaster's live phone camera stream with low latency</p>
                    </div>

                    <!-- Unmute Audio Floating Pill -->
                    <div x-cloak x-show="(hasAgoraStream || hasP2pStream) && audienceMuted" 
                         class="absolute bottom-16 sm:bottom-20 left-1/2 -translate-x-1/2 z-20 pointer-events-auto">
                        <button type="button" @click="unmuteAudienceAudio()"
                                class="px-4 py-2 rounded-full bg-red-600/95 hover:bg-red-500 text-white font-extrabold text-xs shadow-2xl flex items-center gap-2 border border-white/30 backdrop-blur-md transition transform active:scale-95 animate-bounce">
                            <span>🔊</span>
                            <span>Tap to Unmute Audio</span>
                        </button>
                    </div>
                </div>
            @endif

            <!-- 3. EMBEDDED YOUTUBE / FACEBOOK LIVE STREAM (Shown when YouTube/FB link is set) -->
            <template x-if="embedStreamUrl && (streamSource === 'youtube' || (!isCameraLive && !{{ $isOwner ? 'true' : 'false' }}))">
                <iframe :src="embedStreamUrl" 
                        class="absolute inset-0 w-full h-full z-0 border-0 pointer-events-auto" 
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" 
                        allowfullscreen></iframe>
            </template>

            <!-- 4. RECORDED MATCH VIDEO REPLAY (Full match video saved permanently) -->
            <template x-if="isDirectVideo && savedVideoServerUrl && !streamActive && !isCameraLive">
                <div class="absolute inset-0 w-full h-full z-0 flex items-center justify-center bg-black">
                    <video x-show="!videoLoadFailed" :src="savedVideoServerUrl" x-on:error="videoLoadFailed = true" controls playsinline class="w-full h-full object-contain pointer-events-auto"></video>
                    <div x-show="videoLoadFailed" class="flex flex-col items-center justify-center p-6 text-center z-10 text-slate-400">
                        <div class="text-3xl mb-2">📹</div>
                        <div class="text-sm font-bold text-white mb-1">Previous Recording Stored in Cloud</div>
                        <p class="text-xs max-w-sm">Video replay bandwidth limit reached on cloud storage. As soon as the match organizer starts a new live broadcast, the real-time live stream will appear here!</p>
                    </div>
                    <div x-show="!videoLoadFailed" class="absolute top-3 right-3 z-10 px-3 py-1.5 rounded-full bg-slate-900/90 backdrop-blur-md border border-emerald-500/40 text-emerald-400 text-xs font-black flex items-center gap-2 pointer-events-none shadow-xl">
                        <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                        <span>📼 MATCH VIDEO REPLAY</span>
                    </div>
                </div>
            </template>

            @if($isOwner)
                <!-- Button to Go Live when a Replay is currently loaded -->
                <div x-cloak x-show="isDirectVideo && savedVideoServerUrl && !streamActive && streamSource === 'camera'" 
                     class="absolute top-14 left-4 z-20 pointer-events-auto">
                    <button type="button" @click="startCamera()" 
                            class="px-4 py-2 rounded-2xl bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-500 text-white font-black text-xs uppercase tracking-wider shadow-2xl flex items-center gap-2 border border-white/20">
                        <span class="w-2 h-2 rounded-full bg-white animate-ping"></span>
                        <span>Go Live (Camera) 🔴</span>
                    </button>
                </div>

                <!-- Broadcaster Camera Idle / Not Started Placeholder -->
                <div x-show="streamSource === 'camera' && !streamActive && !(isDirectVideo && savedVideoServerUrl)" 
                     class="absolute inset-0 z-0 bg-gradient-to-br from-slate-950 via-slate-900 to-indigo-950 flex flex-col items-center justify-center p-6 text-center">
                    <div class="w-16 h-16 rounded-full bg-red-600/10 border-2 border-red-500/30 flex items-center justify-center text-3xl mb-3 shadow-inner">
                        📹
                    </div>
                    <h3 class="text-xl sm:text-2xl font-black text-white">Live Camera Broadcast Studio</h3>
                    <p class="text-slate-400 text-xs sm:text-sm max-w-md mt-1">
                        Go live directly using your <b class="text-white">Phone Camera</b> so all audience members can watch this match in real-time, or connect a YouTube / Facebook stream.
                    </p>
                    <div class="flex flex-wrap items-center justify-center gap-3 mt-4">
                        <button type="button" @click="startCamera()" 
                                class="px-5 py-2.5 rounded-2xl bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-500 text-white font-black text-xs uppercase tracking-wider shadow-xl shadow-red-600/30 transition flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-white animate-ping"></span>
                            <span>🔴 Go Live (Phone Camera)</span>
                        </button>
                        <button type="button" @click="editStreamModal = true" 
                                class="px-5 py-2.5 rounded-2xl bg-white/10 hover:bg-white/20 border border-white/20 text-white font-black text-xs uppercase tracking-wider transition flex items-center gap-2">
                            <span>⚙️ Stream Settings / YouTube</span>
                        </button>
                    </div>
                </div>

                <!-- Broadcaster YouTube URL Missing Placeholder -->
                <div x-show="streamSource === 'youtube' && !embedStreamUrl" 
                     class="absolute inset-0 z-0 bg-gradient-to-br from-slate-950 via-slate-900 to-indigo-950 flex flex-col items-center justify-center p-6 text-center">
                    <div class="w-16 h-16 rounded-full bg-red-600/10 border-2 border-red-500/30 flex items-center justify-center text-3xl mb-2 text-red-500 shadow-inner">
                        📺
                    </div>
                    <h3 class="text-xl font-black text-white">No Live Stream Link Connected</h3>
                    <p class="text-slate-400 text-xs max-w-sm mt-1">Enter a YouTube Live or Facebook Live stream link to broadcast to all audience with TV graphics overlay.</p>
                    <button type="button" @click="editStreamModal = true" 
                            class="mt-4 px-5 py-2.5 rounded-2xl bg-red-600 hover:bg-red-500 text-white font-black text-xs uppercase tracking-wider shadow-xl shadow-red-600/30 transition">
                        + Add Live Stream Link 🔗
                    </button>
                </div>
            @else
                <!-- AUDIENCE / VIEWER WAITING SCREEN (When neither in-app camera nor YouTube is active) -->
                <div x-show="!isCameraLive && !embedStreamUrl && !(isDirectVideo && savedVideoServerUrl)" 
                     class="absolute inset-0 z-0 bg-gradient-to-br from-slate-950 via-slate-900 to-indigo-950 flex flex-col items-center justify-center p-6 text-center">
                    <div class="w-16 h-16 rounded-full bg-amber-500/10 border-2 border-amber-500/30 flex items-center justify-center text-3xl mb-3 shadow-inner">
                        📺
                    </div>
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-500/20 text-amber-300 text-xs font-bold mb-2">
                        <span class="w-2 h-2 rounded-full bg-amber-400 animate-ping"></span>
                        <span>Broadcast Waiting</span>
                    </div>
                    <h3 class="text-xl sm:text-2xl font-black text-white">Live Video Stream Has Not Started Yet</h3>
                    <p class="text-slate-400 text-xs sm:text-sm max-w-md mt-1 leading-relaxed">
                        The match organizer has not started the live video broadcast yet. As soon as they go live with their phone camera or stream link, the live video will appear here automatically with real-time TV scoreboard!
                    </p>
                    <div class="flex items-center gap-3 mt-4">
                        <a href="{{ route('matches.show', $match) }}" class="px-5 py-2.5 rounded-2xl bg-white/10 hover:bg-white/20 border border-white/20 text-white font-bold text-xs transition">
                            📋 View Full Scorecard
                        </a>
                        @guest
                            <a href="{{ route('login') }}" class="px-4 py-2.5 rounded-2xl bg-red-600/30 hover:bg-red-600/50 border border-red-500/40 text-red-300 font-bold text-xs transition">
                                🔐 Match Organizer? Log In
                            </a>
                        @endguest
                    </div>
                </div>
            @endif

            <!-- ============================================================== -->
            <!-- TV GRAPHICS OVERLAY: TOP BAR (HEADER & LIVE BADGES) -->
            <!-- ============================================================== -->
            <div class="relative z-10 p-2.5 sm:p-4 flex items-start justify-between bg-gradient-to-b from-black/85 via-black/50 to-transparent pointer-events-none">
                <!-- Match Title & Live Badge -->
                <div class="flex flex-wrap items-center gap-2 pointer-events-auto">
                    <span class="px-2.5 py-1 rounded-full bg-red-600 text-white font-black text-[10px] sm:text-xs tracking-wider uppercase flex items-center gap-1.5 shadow-lg shadow-red-600/50 animate-pulse">
                        <span class="w-2 h-2 rounded-full bg-white"></span>
                        LIVE
                    </span>

                    <template x-if="isBroadcaster && streamActive">
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black tracking-wider uppercase flex items-center gap-1 border border-white/20 shadow"
                              :class="broadcastMode === 'agora' ? 'bg-indigo-600 text-white' : 'bg-emerald-600 text-white'"
                              x-text="broadcastMode === 'agora' ? '☁️ Agora Cloud' : '📡 Direct WebRTC'"></span>
                    </template>

                    <template x-if="!isBroadcaster && (hasAgoraStream || hasP2pStream)">
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black tracking-wider uppercase flex items-center gap-1 bg-emerald-600 text-white border border-white/20 shadow">
                            <span class="w-1.5 h-1.5 rounded-full bg-white animate-ping"></span>
                            <span x-text="hasAgoraStream ? '☁️ Cloud Relay' : '⚡ Real-time HD'"></span>
                        </span>
                    </template>

                    <!-- Live In-App Recording Pulse Indicator -->
                    <template x-if="isRecordingStream">
                        <span class="px-2.5 py-1 rounded-full bg-rose-700/95 text-white font-black font-mono text-[10px] sm:text-xs tracking-wider uppercase flex items-center gap-1.5 shadow-lg shadow-rose-700/50 animate-pulse">
                            <span class="w-2 h-2 rounded-full bg-white animate-ping"></span>
                            <span>REC <span x-text="formatRecordingTime(recordingSeconds)"></span> (<span x-text="formatRecordedSize(recordedBytes)"></span>)</span>
                        </span>
                    </template>

                    @if($isOwner)
                        <!-- Source Switcher (Camera vs YouTube/Facebook) - ONLY FOR BROADCASTER -->
                        <div class="flex items-center gap-1 p-0.5 bg-black/60 backdrop-blur-md rounded-xl border border-white/10 text-[10px] font-bold">
                            <button type="button" 
                                    @click="streamSource = 'camera'"
                                    :class="streamSource === 'camera' ? 'bg-red-600 text-white shadow' : 'text-slate-400 hover:text-white'"
                                    class="px-2.5 py-1 rounded-lg transition flex items-center gap-1">
                                <span>📷 Camera</span>
                            </button>
                            <button type="button" 
                                    @click="streamSource = 'youtube'; if (!embedStreamUrl) editStreamModal = true;"
                                    :class="streamSource === 'youtube' ? 'bg-red-600 text-white shadow' : 'text-slate-400 hover:text-white'"
                                    class="px-2.5 py-1 rounded-lg transition flex items-center gap-1">
                                <span>🔴 YouTube / FB</span>
                            </button>
                            <button type="button" @click="editStreamModal = true" class="p-1 px-1.5 text-slate-400 hover:text-white rounded-lg hover:bg-white/10 transition" title="Change Stream Link">
                                ⚙️
                            </button>
                        </div>
                    @endif

                    <div class="hidden sm:flex bg-black/60 backdrop-blur-md px-3 py-1 rounded-xl border border-white/10 text-[11px] sm:text-xs items-center">
                        <span class="text-amber-400 font-extrabold uppercase">{{ $match->tournament_name ?? 'Tournament Match' }}</span>
                        <span class="text-slate-400 mx-1.5">•</span>
                        <span class="text-slate-300 font-mono">{{ $match->total_overs }} Overs</span>
                    </div>
                </div>

                <!-- Floating Broadcaster Control Bar -->
                <div class="flex items-center gap-1 sm:gap-1.5 bg-black/70 backdrop-blur-md p-1.5 rounded-2xl border border-white/10 shadow-xl pointer-events-auto">
                    @if($isOwner)
                        <template x-if="streamSource === 'camera'">
                            <div class="flex items-center gap-1">
                                <button type="button" @click="streamActive ? stopCamera() : startCamera()" 
                                        :class="streamActive ? 'bg-red-600 text-white' : 'bg-emerald-600 text-white'"
                                        class="px-2.5 py-1 rounded-xl font-black text-[10px] sm:text-xs uppercase transition flex items-center gap-1">
                                    <span x-text="streamActive ? '⏹️ Stop' : '🔴 Go Live'"></span>
                                </button>

                                <button type="button" @click="toggleCameraFlip()" 
                                        class="p-1.5 rounded-xl hover:bg-white/10 text-slate-300 hover:text-white transition"
                                        title="Flip Camera (Rear/Front)">
                                    <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                </button>

                                <button type="button" @click="toggleMic()" 
                                        :class="micMuted ? 'text-rose-400' : 'text-emerald-400'"
                                        class="p-1.5 rounded-xl hover:bg-white/10 transition"
                                        :title="micMuted ? 'Unmute Mic' : 'Mute Mic'">
                                    <span x-text="micMuted ? '🔇' : '🎙️'"></span>
                                </button>
                            </div>
                        </template>

                        <!-- Quick Keypad Drawer Toggle -->
                        <button type="button" @click="showKeypadDrawer = !showKeypadDrawer" 
                                :class="showKeypadDrawer ? 'bg-amber-500 text-slate-950 font-black' : 'bg-white/10 text-white'"
                                class="px-2 py-1 rounded-xl text-[10px] sm:text-xs font-bold transition flex items-center gap-1 shadow-sm">
                            <span>⚡ Scorer</span>
                        </button>
                    @endif

                    <button type="button" @click="toggleFullscreen()" 
                            class="p-1.5 rounded-xl hover:bg-white/10 text-slate-300 hover:text-white transition"
                            title="Toggle Fullscreen">
                        <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-5h-4m4 0v4m0-4l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- ============================================================== -->
            <!-- EVENT BANNER POPUP ANIMATION (4, 6, WICKET, OVER COMPLETE) -->
            <!-- ============================================================== -->
            <div x-cloak x-show="eventBannerText" 
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 scale-75"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-75"
                 class="absolute inset-0 z-20 flex items-center justify-center pointer-events-none p-4">
                <div :class="{
                        'from-blue-600 via-indigo-600 to-blue-800 border-blue-400': eventBannerType === 'four',
                        'from-purple-600 via-amber-500 to-purple-800 border-amber-300': eventBannerType === 'six',
                        'from-rose-700 via-red-600 to-rose-900 border-rose-400': eventBannerType === 'wicket',
                        'from-emerald-600 via-teal-600 to-emerald-800 border-emerald-400': eventBannerType === 'over'
                     }"
                     class="px-8 py-5 rounded-3xl bg-gradient-to-r text-white text-2xl sm:text-4xl font-black italic tracking-wider shadow-2xl border-4 text-center transform rotate-[-2deg] animate-bounce">
                    <span x-text="eventBannerText"></span>
                </div>
            </div>

            <!-- ============================================================== -->
            <!-- TV GRAPHICS OVERLAY: SIGNATURE LOWER THIRD (BOTTOM SCOREBOARD) -->
            <!-- ============================================================== -->
            <div class="relative z-10 p-2 sm:p-4 bg-gradient-to-t from-black/95 via-black/85 to-transparent pointer-events-none">
                <div class="bg-gradient-to-r from-slate-900/95 via-slate-950/95 to-indigo-950/95 backdrop-blur-md rounded-2xl border-2 border-white/15 p-2 sm:p-3 shadow-2xl grid grid-cols-1 md:grid-cols-12 gap-2 sm:gap-3 items-center pointer-events-auto">
                    
                    <!-- Left Block: Team Name & Giant Live Score (Cols: 4) -->
                    <div class="md:col-span-4 flex items-center justify-between border-b md:border-b-0 md:border-r border-white/10 pb-2 md:pb-0 md:pr-3">
                        <div>
                            <div class="text-[10px] font-black uppercase text-amber-400 tracking-wider flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                <span x-text="activeBattingTeam"></span>
                            </div>
                            <div class="flex items-baseline gap-2 mt-0.5">
                                <span class="text-2xl sm:text-3xl font-black font-mono text-white" x-text="scoreRuns + '/' + scoreWickets"></span>
                                <span class="text-xs font-mono font-bold text-slate-300">
                                    <span x-text="scoreOvers"></span> / <span x-text="totalOvers"></span> ov
                                </span>
                            </div>
                        </div>

                        <div class="text-right font-mono">
                            <span class="text-[9px] uppercase font-bold text-slate-400 block">CRR</span>
                            <span class="text-xs font-extrabold text-emerald-400" 
                                  x-text="(scoreOvers && parseFloat(scoreOvers) > 0 ? (scoreRuns / (Math.floor(parseFloat(scoreOvers)) + (parseFloat(scoreOvers)%1)*1.6666)).toFixed(1) : '0.0')"></span>
                        </div>
                    </div>

                    <!-- Middle Block: Active Batsmen on Crease (Cols: 5) -->
                    <div class="md:col-span-5 grid grid-cols-2 gap-2 border-b md:border-b-0 md:border-r border-white/10 pb-2 md:pb-0 md:pr-3 text-[11px]">
                        <!-- Striker -->
                        <div @if($isOwner) @click="openPlayerModal('all')" @endif 
                             class="p-1.5 rounded-xl bg-emerald-950/40 border border-emerald-500/40 truncate @if($isOwner) cursor-pointer hover:border-emerald-400 hover:bg-emerald-900/50 transition group @endif"
                             @if($isOwner) title="Click to Change / Select Striker" @endif>
                            <div class="flex items-center justify-between text-[9px] font-bold text-emerald-400 mb-0.5">
                                <span>🏏 STRIKER</span>
                                @if($isOwner)
                                    <span class="group-hover:hidden">ON STRIKE</span>
                                    <span class="hidden group-hover:inline text-[8px] bg-emerald-500/30 px-1 rounded text-white font-mono">✎ CHANGE</span>
                                @else
                                    <span>ON STRIKE</span>
                                @endif
                            </div>
                            <div class="font-black text-white truncate" x-text="striker ? striker.player_name : 'Select Striker'"></div>
                            <div class="font-mono text-[10px] text-amber-300 font-bold mt-0.5" 
                                 x-text="striker ? (striker.runs_scored + '* (' + striker.balls_faced + 'b)') : '0* (0b)'"></div>
                        </div>

                        <!-- Non-Striker -->
                        <div @if($isOwner) @click="openPlayerModal('all')" @endif 
                             class="p-1.5 rounded-xl bg-black/40 border border-white/10 truncate @if($isOwner) cursor-pointer hover:border-indigo-400 hover:bg-indigo-950/40 transition group @endif"
                             @if($isOwner) title="Click to Change / Select Non-Striker" @endif>
                            <div class="flex items-center justify-between text-[9px] font-bold text-slate-400 mb-0.5">
                                <span>NON-STRIKER</span>
                                @if($isOwner)
                                    <span class="hidden group-hover:inline text-[8px] bg-indigo-500/30 px-1 rounded text-indigo-200 font-mono">✎ CHANGE</span>
                                @endif
                            </div>
                            <div class="font-black text-slate-200 truncate" x-text="nonStriker ? nonStriker.player_name : 'Select Non-Striker'"></div>
                            <div class="font-mono text-[10px] text-slate-300 font-bold mt-0.5" 
                                 x-text="nonStriker ? (nonStriker.runs_scored + ' (' + nonStriker.balls_faced + 'b)') : '0 (0b)'"></div>
                        </div>
                    </div>

                    <!-- Right Block: Bowler Figures & This Over Timeline (Cols: 3) -->
                    <div class="md:col-span-3 text-[11px] space-y-1">
                        <div @if($isOwner) @click="openPlayerModal('bowler')" @endif 
                             class="p-1 rounded-lg @if($isOwner) cursor-pointer hover:bg-rose-950/40 border border-transparent hover:border-rose-500/40 transition group @endif"
                             @if($isOwner) title="Click to Change / Select Bowler" @endif>
                            <div class="flex items-center justify-between">
                                <span class="text-[9px] font-bold text-rose-400 uppercase">🎯 BOWLER</span>
                                @if($isOwner)
                                    <span class="hidden group-hover:inline text-[8px] bg-rose-500/30 px-1 rounded text-rose-200 font-mono">✎ CHANGE</span>
                                    <span class="font-mono text-[10px] text-white font-extrabold group-hover:hidden" 
                                          x-text="bowler ? (bowler.wickets_taken + '/' + bowler.runs_conceded + ' (' + bowler.overs_bowled + ' ov)') : '0/0 (0.0)'"></span>
                                @else
                                    <span class="font-mono text-[10px] text-white font-extrabold" 
                                          x-text="bowler ? (bowler.wickets_taken + '/' + bowler.runs_conceded + ' (' + bowler.overs_bowled + ' ov)') : '0/0 (0.0)'"></span>
                                @endif
                            </div>
                            <div class="font-black text-white truncate text-xs" x-text="bowler ? bowler.player_name : 'Select Bowler'"></div>
                        </div>

                        <!-- This Over Ticker Balls -->
                        <div class="flex items-center gap-1 overflow-x-auto pt-0.5">
                            <span class="text-[9px] font-bold text-slate-400 uppercase mr-1">Over:</span>
                            <template x-for="(ball, idx) in thisOver" :key="idx">
                                <span :class="{
                                        'bg-blue-600 text-white': ball === '4',
                                        'bg-purple-600 text-white': ball === '6',
                                        'bg-rose-600 text-white font-black': ball === 'W',
                                        'bg-amber-600 text-white': ball.includes('WD') || ball.includes('NB'),
                                        'bg-slate-800 text-slate-300': ball === '0',
                                        'bg-slate-700 text-white': ball !== '0' && ball !== '4' && ball !== '6' && ball !== 'W' && !ball.includes('WD') && !ball.includes('NB')
                                      }"
                                      class="px-1.5 py-0.5 rounded text-[9px] font-mono font-bold shrink-0 shadow-sm" 
                                      x-text="ball"></span>
                            </template>
                            <template x-if="!thisOver || thisOver.length === 0">
                                <span class="text-[10px] text-slate-500 font-mono">Yet to start</span>
                            </template>
                        </div>
                    </div>

                </div>
            </div>

            <!-- ============================================================== -->
            <!-- FLOATING MINI SCORER DRAWER (FOR SINGLE OPERATOR STREAMING) -->
            <!-- ============================================================== -->
            @if($isOwner)
                <div x-cloak x-show="showKeypadDrawer" 
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 translate-y-4"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-start="opacity-100 translate-y-0"
                     x-transition:leave-end="opacity-0 translate-y-4"
                     class="absolute bottom-28 left-4 right-4 z-30 p-3 rounded-2xl bg-slate-950/95 border-2 border-amber-500/50 backdrop-blur-xl shadow-2xl max-w-lg mx-auto space-y-2 pointer-events-auto">
                    <div class="flex items-center justify-between text-xs pb-1 border-b border-white/10">
                        <span class="font-black text-amber-400 flex items-center gap-1.5">
                            <span>⚡ Quick Live Scorer (Tap to update live broadcast)</span>
                        </span>
                        <button type="button" @click="showKeypadDrawer = false" class="text-slate-400 hover:text-white font-bold">✕ Close</button>
                    </div>

                    <!-- Row 1: Runs -->
                    <div class="grid grid-cols-6 gap-1.5 text-xs font-black font-mono">
                        <button type="button" @click="recordBall('run', { runs: 0 })" class="py-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-white">0</button>
                        <button type="button" @click="recordBall('run', { runs: 1 })" class="py-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-white">1</button>
                        <button type="button" @click="recordBall('run', { runs: 2 })" class="py-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-white">2</button>
                        <button type="button" @click="recordBall('run', { runs: 3 })" class="py-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-white">3</button>
                        <button type="button" @click="recordBall('run', { runs: 4 })" class="py-2.5 rounded-xl bg-blue-600 text-white">4 🏏</button>
                        <button type="button" @click="recordBall('run', { runs: 6 })" class="py-2.5 rounded-xl bg-purple-600 text-white">6 💥</button>
                    </div>

                    <!-- Row 2: Extras & Wicket -->
                    <div class="grid grid-cols-4 gap-1.5 text-[10px] font-bold">
                        <button type="button" @click="recordBall('wide', { runs: 1 })" class="py-2 rounded-xl bg-amber-600/30 text-amber-300 border border-amber-500/40 hover:bg-amber-600/40 transition">Wide (WD)</button>
                        <button type="button" @click="recordBall('no_ball', { runs: 1 })" class="py-2 rounded-xl bg-orange-600/30 text-orange-300 border border-orange-500/40 hover:bg-orange-600/40 transition">No Ball (NB)</button>
                        <button type="button" @click="recordBall('swap_strike')" class="py-2 rounded-xl bg-white/10 text-slate-300 hover:bg-white/20 transition">Swap Strike ⇄</button>
                        <button type="button" @click="wicketModalOpen = true" class="py-2 rounded-xl bg-red-600 hover:bg-red-500 text-white font-black shadow-lg shadow-red-600/40 transition">☝️ Wicket</button>
                    </div>

                    <!-- Row 3: Player Management -->
                    <div class="grid grid-cols-2 gap-1.5 text-[11px] font-extrabold pt-1.5 border-t border-white/10">
                        <button type="button" @click="openPlayerModal('all')" class="py-2 px-3 rounded-xl bg-emerald-600/25 hover:bg-emerald-600/40 text-emerald-300 border border-emerald-500/40 flex items-center justify-center gap-1.5 transition shadow-sm">
                            <span>🏏 Change Batsmen</span>
                        </button>
                        <button type="button" @click="openPlayerModal('bowler')" class="py-2 px-3 rounded-xl bg-rose-600/25 hover:bg-rose-600/40 text-rose-300 border border-rose-500/40 flex items-center justify-center gap-1.5 transition shadow-sm">
                            <span>🎯 Change Bowler</span>
                        </button>
                    </div>
                </div>
            @endif

        </div>

        <!-- Broadcast Info & Guide Card -->
        <div class="p-5 rounded-2xl bg-[#131b2e] border border-white/10 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs">
            <div class="flex items-center gap-3">
                <span class="text-2xl">📡</span>
                <div>
                    <div class="text-white font-extrabold text-sm flex items-center gap-2">
                        <span>{{ $isOwner ? 'CricHeroes Live Broadcast Studio' : 'Cricket Live Match Broadcast' }}</span>
                        <span class="px-2 py-0.5 rounded-md bg-emerald-500/20 text-emerald-400 font-mono text-[10px] font-bold border border-emerald-500/30">
                            Auto-Sync Active
                        </span>
                    </div>
                    <div class="text-slate-400 mt-0.5">
                        {{ $isOwner 
                            ? 'The TV lower-third scoreboard overlay automatically updates in real time over both Device Camera and YouTube/Facebook streams.'
                            : 'Watch real-time live ball-by-ball updates with official TV scoreboard overlay synchronized with ground play.' }}
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <button type="button" @click="showCommentaryDrawer = !showCommentaryDrawer" 
                        class="px-3.5 py-2 rounded-xl bg-purple-600/20 hover:bg-purple-600/30 border border-purple-500/40 text-purple-300 hover:text-white font-bold transition flex items-center gap-1.5">
                    <span>🎙️ Commentary</span>
                    <span class="px-1.5 py-0.2 rounded-full bg-black/40 text-[10px] font-mono" x-text="commentaryList.length"></span>
                </button>
                @if($isOwner)
                    <button type="button" @click="editStreamModal = true" class="px-3.5 py-2 rounded-xl bg-red-600/20 hover:bg-red-600/30 border border-red-500/40 text-red-300 hover:text-white font-bold transition flex items-center gap-1.5">
                        <span>🔗 Stream Link</span>
                    </button>
                @endif
                <a href="{{ route('matches.show', $match) }}" class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/20 text-white font-bold transition">
                    Full Scorecard ➔
                </a>
            </div>
        </div>

        <!-- Live Commentary Expandable Slide-Down Card in Broadcast -->
        <div x-cloak x-show="showCommentaryDrawer" x-transition class="p-6 rounded-3xl bg-[#131b2e] border border-white/10 shadow-2xl space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-white/10">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-red-500 animate-pulse"></span>
                    <h4 class="font-black text-sm text-white">🎙️ Live Ball-by-Ball Commentary Stream</h4>
                </div>
                <button type="button" @click="showCommentaryDrawer = false" class="text-slate-400 hover:text-white text-xs font-bold">✕ Close</button>
            </div>

            <div class="space-y-2.5 max-h-96 overflow-y-auto pr-1">
                <template x-if="commentaryList.length === 0">
                    <div class="py-8 text-center text-slate-500 text-xs italic">
                        No commentary recorded yet. Deliveries will update here automatically in real time.
                    </div>
                </template>

                <template x-for="item in commentaryList" :key="item.id || (item.ball + '-' + item.time)">
                    <div class="p-3.5 rounded-2xl bg-white/5 border border-white/5 flex items-start gap-3 text-xs">
                        <div class="shrink-0 text-center w-12">
                            <span class="px-2 py-0.5 rounded-md bg-black/40 font-mono font-bold text-xs text-slate-200 block" x-text="item.ball"></span>
                            <span class="text-[9px] text-slate-500 block mt-0.5 truncate" x-text="item.time"></span>
                        </div>
                        <div class="shrink-0 w-7 h-7 rounded-lg flex items-center justify-center font-mono font-black text-xs shadow-md"
                             :class="{
                                 'bg-rose-600 text-white shadow-rose-600/40': item.badge_type === 'wicket' || item.badge === 'W',
                                 'bg-purple-600 text-white shadow-purple-600/40': item.badge_type === 'six' || item.badge === '6',
                                 'bg-blue-600 text-white shadow-blue-600/40': item.badge_type === 'four' || item.badge === '4',
                                 'bg-amber-600 text-white shadow-amber-600/40': item.badge_type === 'extra',
                                 'bg-slate-800 text-slate-300 border border-white/10': item.badge_type === 'dot' || item.badge === '0',
                                 'bg-emerald-600/30 text-emerald-300 border border-emerald-500/30': item.badge_type === 'run' || (!['wicket','six','four','extra','dot'].includes(item.badge_type))
                             }">
                            <span x-text="item.badge"></span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-1.5">
                                <span class="font-black text-white text-xs truncate" x-text="item.heading"></span>
                                <template x-if="item.shot">
                                    <span class="px-2 py-0.2 rounded-full bg-emerald-500/20 text-emerald-300 text-[9px] font-bold border border-emerald-500/30" x-text="item.shot"></span>
                                </template>
                            </div>
                            <p class="text-[11px] text-slate-300 mt-0.5 leading-snug" x-text="item.text"></p>
                        </div>
                    </div>
                </template>
            </div>
        </div>

    </div>
</div>
@endsection
