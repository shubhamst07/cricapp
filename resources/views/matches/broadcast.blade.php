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
<!-- MediaMTX WebRTC (WHIP / WHEP) Engine & HLS Fallback -->
<script src="{{ asset('js/cricket-stream-engine.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>

<div class="py-4 sm:py-6 bg-slate-950 min-h-screen text-slate-100 font-sans select-none" x-data="{
    // Broadcast Source ('camera' or 'youtube')
    streamSource: '{{ !empty($match->embed_stream_url) ? 'youtube' : 'camera' }}',
    streamUrlInput: '{{ addslashes($match->stream_url ?? '') }}',
    embedStreamUrl: '{{ addslashes($match->embed_stream_url ?? '') }}',
    editStreamModal: false,
    activeStreamTab: 'camera',

    // MediaMTX Live Streaming Endpoints (Self-Hosted, Ultra-Low Latency < 500ms)
    streamChannel: '{{ $streamChannel }}',
    whipUrl: '{{ $whipUrl }}',
    whepUrl: '{{ $whepUrl }}',
    hlsUrl: '{{ $hlsUrl }}',
    broadcastMode: 'mediamtx_webrtc',
    isCameraLive: {{ $isCameraLive ? 'true' : 'false' }},
    streamActive: {{ $isCameraLive ? 'true' : 'false' }},
    isBroadcasting: false,
    viewerConnected: false,
    isConnectingAudience: false,
    hasLiveStream: false,
    broadcasterEngine: null,
    viewerEngine: null,
    streamEngineMode: 'idle', // 'webrtc' or 'hls'
    audienceMuted: false,
    facingMode: 'environment',
    micMuted: false,

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

    // Match Videos Gallery States
    matchVideos: {{ json_encode($videos ?? $match->videos) }},
    activeVideoUrl: '{{ addslashes($match->stream_url ?? '') }}',
    activeVideoTitle: '{{ addslashes(($videos[0]['title'] ?? null) ?: 'Match Video Replay') }}',
    showScoreboardOverlayOnVideo: true,
    hideMobileScoreboard: false,
    videoDeletingId: null,

    fixVideoUrl(url) {
        if (!url) return '';
        if (url.includes('imagekit.io') && (url.includes('.webm') || url.includes('.mp4') || url.includes('.mov')) && !url.includes('tr=orig')) {
            return url.includes('?') ? (url + '&tr=orig') : (url + '?tr=orig');
        }
        return url;
    },

    selectAndPlayVideo(video) {
        const url = this.fixVideoUrl(video.url);
        this.activeVideoUrl = url;
        this.activeVideoTitle = video.title || 'Match Replay';
        this.savedVideoServerUrl = url;
        this.isDirectVideo = true;
        this.videoLoadFailed = false;
        this.streamSource = 'camera';
        const arena = document.getElementById('broadcastArenaContainer');
        if (arena) arena.scrollIntoView({ behavior: 'smooth', block: 'center' });
        this.showToast('▶ Playing: ' + (video.title || 'Match Video'));
    },

    async deleteMatchVideo(video) {
        if (!confirm('Are you sure you want to permanently delete this match video: ' + (video.title || 'Match Video') + '?')) {
            return;
        }
        this.videoDeletingId = video.id;
        try {
            const res = await fetch('/matches/{{ $match->id }}/recordings/' + video.id, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            });
            const data = await res.json();
            if (res.ok && data.status === 'success') {
                this.matchVideos = data.videos || [];
                if (this.savedVideoServerUrl === video.url) {
                    this.savedVideoServerUrl = data.current_stream_url || '';
                    this.activeVideoUrl = data.current_stream_url || '';
                }
                this.showToast('🗑️ Video deleted successfully!');
            } else {
                this.showToast('⚠️ Delete failed: ' + (data.message || 'Permission denied'));
            }
        } catch(e) {
            console.error(e);
            this.showToast('⚠️ Delete error. Please try again.');
        } finally {
            this.videoDeletingId = null;
        }
    },

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

        let streamToRecord = window.localMediaStream;
        if (!streamToRecord) {
            const videoElem = document.getElementById('broadcastVideo');
            if (videoElem && videoElem.srcObject) {
                streamToRecord = videoElem.srcObject;
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
            formData.append('title', 'Match Replay (' + this.team1Name + ' vs ' + this.team2Name + ') #' + (this.matchVideos.length + 1));
            formData.append('score_snapshot', this.team1Name + ' vs ' + this.team2Name + ' • ' + this.scoreRuns + '/' + this.scoreWickets + ' (' + this.scoreOvers + ' ov)');
            formData.append('duration', this.formatRecordingTime(this.recordingSeconds));

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
                this.activeVideoUrl = data.video_url;
                if (data.videos) {
                    this.matchVideos = data.videos;
                }
                this.isDirectVideo = true;
                this.videoLoadFailed = false;
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

            const videoElem = document.getElementById('broadcastVideo');
            
            // Build dynamic WHIP URL
            let whipEndpoint = this.whipUrl;
            if (!whipEndpoint || whipEndpoint.includes('localhost') || whipEndpoint.includes('127.0.0.1')) {
                const currentHost = window.location.hostname;
                const port = '{{ config('services.mediamtx.webrtc_port', 8889) }}';
                whipEndpoint = `${window.location.protocol}//${currentHost}:${port}/${this.streamChannel}/whip`;
            }

            this.broadcasterEngine = new CricketStreamBroadcaster({
                whipUrl: whipEndpoint,
                facingMode: this.facingMode,
                onStateChange: (state) => {
                    console.log('MediaMTX WHIP state:', state);
                },
                onError: (err) => {
                    console.error('MediaMTX broadcast error:', err);
                }
            });

            const stream = await this.broadcasterEngine.start(whipEndpoint, videoElem);
            window.localMediaStream = stream;

            this.streamActive = true;
            this.isBroadcasting = true;
            this.isCameraLive = true;
            this.broadcastMode = 'mediamtx_webrtc';

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

            this.showToast('🔴 YOU ARE LIVE! (Self-Hosted MediaMTX Ultra-Low Latency)');
        } catch(err) {
            console.error('Camera access / WHIP broadcast error:', err);
            this.showToast('⚠️ Could not start broadcast: ' + (err.message || 'Camera permission denied'));
        }
    },

    async stopCamera() {
        try {
            // Stop recording & finalize video
            this.stopRecording();

            if (this.broadcasterEngine) {
                await this.broadcasterEngine.stop();
                this.broadcasterEngine = null;
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
        const videoElem = document.getElementById('broadcastVideo');
        if (this.broadcasterEngine) {
            this.facingMode = await this.broadcasterEngine.flipCamera(videoElem);
            this.showToast(this.facingMode === 'user' ? '🤳 Front Camera' : '🏟️ Ground Camera');
        } else {
            this.facingMode = (this.facingMode === 'environment') ? 'user' : 'environment';
        }
    },

    toggleMic() {
        if (this.broadcasterEngine) {
            this.micMuted = this.broadcasterEngine.toggleMute();
        } else {
            this.micMuted = !this.micMuted;
        }
        this.showToast(this.micMuted ? '🔇 Microphone muted' : '🎙️ Microphone on');
    },

    // Audience In-App Live Stream Subscriber (MediaMTX WebRTC WHEP + HLS Fallback)
    async connectAudienceStream(data) {
        if (this.viewerConnected || this.isBroadcaster || this.isConnectingAudience) return;
        this.isConnectingAudience = true;

        try {
            const videoElem = document.getElementById('audienceVideoPlayer');
            if (!videoElem) {
                this.isConnectingAudience = false;
                return;
            }

            const currentHost = window.location.hostname;
            const webrtcPort = '{{ config('services.mediamtx.webrtc_port', 8889) }}';
            const hlsPort = '{{ config('services.mediamtx.hls_port', 8888) }}';
            const channel = data?.stream_channel || this.streamChannel;

            let whepEndpoint = data?.whep_url || this.whepUrl;
            if (!whepEndpoint || whepEndpoint.includes('localhost') || whepEndpoint.includes('127.0.0.1')) {
                whepEndpoint = `${window.location.protocol}//${currentHost}:${webrtcPort}/${channel}/whep`;
            }

            let hlsEndpoint = data?.hls_url || this.hlsUrl;
            if (!hlsEndpoint || hlsEndpoint.includes('localhost') || hlsEndpoint.includes('127.0.0.1')) {
                hlsEndpoint = `${window.location.protocol}//${currentHost}:${hlsPort}/${channel}/index.m3u8`;
            }

            if (!this.viewerEngine) {
                this.viewerEngine = new CricketStreamViewer({
                    onModeChange: (mode) => {
                        this.streamEngineMode = mode;
                        console.log('Stream playback mode:', mode);
                    }
                });
            }

            await this.viewerEngine.play(whepEndpoint, hlsEndpoint, videoElem);
            this.viewerConnected = true;
            this.streamActive = true;
            this.hasLiveStream = true;
        } catch(e) {
            console.warn('Audience stream connection notice:', e);
            this.viewerConnected = false;
        } finally {
            this.isConnectingAudience = false;
        }
    },

    unmuteAudienceAudio() {
        const videoElem = document.getElementById('audienceVideoPlayer');
        if (videoElem) {
            videoElem.muted = false;
            videoElem.play().catch(() => {});
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
                    if (data.videos) this.matchVideos = data.videos;

                    // Broadcaster Heartbeat & P2P Viewer Handling
                    if (this.isBroadcaster && this.streamActive) {
                        // Send live heartbeat
                        fetch('{{ route('matches.stream-signal', $match) }}', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                            body: JSON.stringify({ heartbeat: true })
                        }).catch(() => {});

                    }

                    // Sync MediaMTX Live Stream State for Audience
                    if (data.is_camera_live) {
                        this.isCameraLive = true;
                        if (!this.isBroadcaster && !this.viewerConnected && !this.isConnectingAudience) {
                            this.connectAudienceStream(data);
                        }
                    } else {
                        if (!this.isBroadcaster && this.isCameraLive) {
                            this.isCameraLive = false;
                            this.viewerConnected = false;
                            this.hasLiveStream = false;
                            if (this.viewerEngine) {
                                this.viewerEngine.stop();
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
}" x-init="startPolling(); if (!isBroadcaster && isCameraLive) { connectAudienceStream({ stream_channel: streamChannel, whip_url: whipUrl, whep_url: whepUrl, hls_url: hlsUrl }); } window.addEventListener('beforeunload', () => { if (isBroadcaster && streamActive) { navigator.sendBeacon('{{ route('matches.stream-signal', $match) }}', new Blob([JSON.stringify({ is_camera_live: false, _token: '{{ csrf_token() }}' })], { type: 'application/json' })); } });">

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
                    <span class="text-slate-400 font-bold uppercase block">MediaMTX Live Streaming Server:</span>
                    <div class="flex items-center justify-between text-slate-300">
                        <span>Stream Path:</span>
                        <code class="font-mono text-emerald-300 font-bold" x-text="streamChannel"></code>
                    </div>
                    <div class="flex items-center justify-between text-slate-300">
                        <span>Protocols:</span>
                        <span class="text-indigo-300 font-bold">WHIP (WebRTC) + WHEP + HLS</span>
                    </div>
                    <div class="flex items-center justify-between text-slate-300">
                        <span>Latency:</span>
                        <span class="text-emerald-400 font-bold flex items-center gap-1">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                            <span>Ultra-Low Sub-Second (< 500ms)</span>
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
                <video x-show="streamSource === 'camera' && streamActive" id="broadcastVideo" autoplay playsinline muted 
                       class="absolute inset-0 w-full h-full object-cover z-0"></video>
            @endif

            <!-- 2. AUDIENCE LIVE IN-APP CAMERA STREAM (MediaMTX WebRTC WHEP + HLS Fallback) -->
            @if(!$isOwner)
                <div x-show="isCameraLive" class="absolute inset-0 w-full h-full z-0 overflow-hidden bg-black flex items-center justify-center">
                    <video id="audienceVideoPlayer" autoplay playsinline 
                           class="absolute inset-0 w-full h-full object-cover"></video>

                    <!-- Stream Connecting / Buffering Loader -->
                    <div x-show="!viewerConnected" class="absolute inset-0 flex flex-col items-center justify-center p-6 text-center z-10 bg-black">
                        <div class="w-12 h-12 rounded-full border-4 border-red-500 border-t-transparent animate-spin mb-3"></div>
                        <div class="text-sm font-black text-white flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-red-500 animate-ping"></span>
                            <span>Connecting to Live Video Feed...</span>
                        </div>
                        <p class="text-xs text-slate-400 mt-1 max-w-xs">Connecting to MediaMTX ultra-low latency WebRTC stream</p>
                    </div>

                    <!-- Unmute Audio Floating Pill -->
                    <div x-cloak x-show="viewerConnected && audienceMuted" 
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
                    <video x-show="!videoLoadFailed" :src="fixVideoUrl(savedVideoServerUrl)" x-on:error="videoLoadFailed = true" controls playsinline class="w-full h-full object-contain pointer-events-auto"></video>
                    
                    <!-- App Logo & TV Replay Watermark Overlay -->
                    <div x-show="!videoLoadFailed" class="absolute top-3 left-3 z-10 flex items-center gap-2 px-3 py-1.5 rounded-full bg-slate-950/85 backdrop-blur-md border border-white/20 shadow-2xl pointer-events-none">
                        <span class="text-sm">🏏</span>
                        <span class="font-black text-xs text-white tracking-wider">LiveCricket</span>
                        <span class="px-1.5 py-0.5 rounded bg-red-600 text-white font-mono font-bold text-[9px] uppercase tracking-widest">REPLAY HD</span>
                    </div>

                    <!-- TV Scoreboard Overlay on Video Replay -->
                    <div x-show="!videoLoadFailed && showScoreboardOverlayOnVideo" 
                         class="absolute bottom-2 left-2 right-2 sm:bottom-3 sm:left-3 sm:right-3 z-10 p-1.5 sm:p-3 rounded-xl sm:rounded-2xl bg-slate-950/90 backdrop-blur-md border border-white/20 shadow-2xl flex items-center justify-between gap-1.5 sm:gap-2 pointer-events-auto">
                        <!-- Scoreboard Left: Teams & Runs -->
                        <div class="flex items-center gap-1.5 sm:gap-3 text-[11px] sm:text-sm font-black text-white min-w-0">
                            <span class="truncate max-w-[80px] sm:max-w-[110px]" x-text="team1Name"></span>
                            <span class="px-1.5 sm:px-2 py-0.5 rounded bg-emerald-600 text-white font-mono text-[10px] sm:text-xs font-black shadow shrink-0" x-text="scoreRuns + '/' + scoreWickets"></span>
                            <span class="text-slate-400 text-[10px] sm:text-xs font-mono shrink-0" x-text="'(' + scoreOvers + ' ov)'"></span>
                            <span class="text-slate-500 font-bold text-[10px] sm:text-xs">vs</span>
                            <span class="text-slate-300 truncate max-w-[80px] sm:max-w-[110px]" x-text="team2Name"></span>
                        </div>

                        <!-- Scoreboard Right: Batsmen / Bowler -->
                        <div class="hidden md:flex items-center gap-3 text-xs text-slate-300">
                            <template x-if="striker">
                                <div class="flex items-center gap-1">
                                    <span class="text-amber-400 font-bold">🏏 *<span x-text="striker.player_name"></span></span>
                                    <span class="font-mono text-white" x-text="striker.runs_scored + '(' + striker.balls_faced + ')'"></span>
                                </div>
                            </template>
                            <template x-if="bowler">
                                <div class="flex items-center gap-1 border-l border-white/20 pl-3">
                                    <span class="text-rose-400 font-bold">🎯 <span x-text="bowler.player_name"></span></span>
                                    <span class="font-mono text-white" x-text="bowler.wickets_taken + '/' + bowler.runs_conceded"></span>
                                </div>
                            </template>
                        </div>

                        <!-- Toggle Overlay Button -->
                        <button type="button" @click="showScoreboardOverlayOnVideo = false" 
                                class="text-[9px] sm:text-[10px] px-2 py-1 rounded-lg bg-white/10 hover:bg-white/20 text-slate-300 font-bold shrink-0">
                            ✕ Hide
                        </button>
                    </div>

                    <!-- Button to Re-Show Scoreboard Overlay if hidden -->
                    <div x-show="!videoLoadFailed && !showScoreboardOverlayOnVideo" 
                         class="absolute bottom-2 right-2 sm:bottom-3 sm:right-3 z-10 pointer-events-auto">
                        <button type="button" @click="showScoreboardOverlayOnVideo = true" 
                                class="px-2.5 sm:px-3 py-1 sm:py-1.5 rounded-full bg-slate-900/90 text-white text-[10px] sm:text-xs font-bold border border-white/20 shadow-xl backdrop-blur-md hover:bg-slate-800 flex items-center gap-1.5">
                            <span>📊 Show TV Scoreboard</span>
                        </button>
                    </div>

                    <!-- Offline / Fallback screen when video fails or not playable -->
                    <div x-show="videoLoadFailed" class="flex flex-col items-center justify-center p-6 text-center z-10 text-slate-400">
                        <div class="w-14 h-14 rounded-full bg-red-600/10 border-2 border-red-500/30 flex items-center justify-center text-3xl mb-2 text-red-500">
                            📹
                        </div>
                        <div class="text-sm font-bold text-white mb-1">Live Camera Stream is Currently Offline</div>
                        <p class="text-xs max-w-sm text-slate-400 mb-4 leading-relaxed">The match organizer has not started live camera broadcasting yet. As soon as they go live, the video feed will appear here automatically in real time!</p>
                        <div class="flex items-center gap-3">
                            @if($isOwner)
                                <button type="button" @click="startCamera()" class="px-5 py-2.5 rounded-2xl bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-500 text-white font-black text-xs uppercase tracking-wider flex items-center gap-2 shadow-xl shadow-red-600/30">
                                    <span class="w-2 h-2 rounded-full bg-white animate-ping"></span>
                                    <span>🔴 Start Live Camera Broadcast</span>
                                </button>
                            @else
                                @guest
                                    <a href="{{ route('login') }}" class="px-4 py-2.5 rounded-2xl bg-red-600/30 hover:bg-red-600/50 border border-red-500/40 text-red-300 font-bold text-xs transition flex items-center gap-1.5">
                                        <span>🔐</span>
                                        <span>Match Organizer? Log In to Go Live</span>
                                    </a>
                                @endguest
                            @endif
                        </div>
                    </div>
                    <div x-show="!videoLoadFailed" class="absolute top-3 right-3 z-10 px-3 py-1.5 rounded-full bg-slate-900/90 backdrop-blur-md border border-emerald-500/40 text-emerald-400 text-xs font-black flex items-center gap-2 pointer-events-none shadow-xl">
                        <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                        <span x-text="activeVideoTitle || '📼 MATCH VIDEO REPLAY'"></span>
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
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black tracking-wider uppercase flex items-center gap-1 bg-emerald-600 text-white border border-white/20 shadow">
                            <span>⚡ MediaMTX WebRTC</span>
                        </span>
                    </template>

                    <template x-if="!isBroadcaster && (viewerConnected || isCameraLive)">
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black tracking-wider uppercase flex items-center gap-1 bg-emerald-600 text-white border border-white/20 shadow">
                            <span class="w-1.5 h-1.5 rounded-full bg-white animate-ping"></span>
                            <span x-text="streamEngineMode === 'hls' ? '⚡ HLS Live' : '⚡ Sub-Second HD (<0.5s)'"></span>
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
            <div class="relative z-10 p-1.5 sm:p-4 bg-gradient-to-t from-black/95 via-black/75 to-transparent pointer-events-none">
                
                <!-- 1. MOBILE COMPACT TICKER (SCREEN < md): Only ~32px, Leaves 100% of Pitch Clear! -->
                <div x-show="!hideMobileScoreboard" class="block md:hidden bg-gradient-to-r from-slate-950/90 via-slate-900/90 to-indigo-950/90 backdrop-blur-md rounded-xl border border-white/20 px-2.5 py-1.5 shadow-2xl pointer-events-auto">
                    <div class="flex items-center justify-between gap-1.5 text-[11px]">
                        <!-- Left: Live Score -->
                        <div class="flex items-center gap-1.5 font-mono min-w-0">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse shrink-0"></span>
                            <span class="font-extrabold text-white truncate max-w-[80px]" x-text="activeBattingTeam"></span>
                            <span class="font-black text-amber-300 shrink-0" x-text="scoreRuns + '/' + scoreWickets"></span>
                            <span class="text-[10px] text-slate-400 font-bold shrink-0" x-text="'(' + scoreOvers + ' ov)'"></span>
                        </div>

                        <!-- Right: Striker / Bowler Snapshot & Collapse Button -->
                        <div class="flex items-center gap-1.5 shrink-0">
                            <template x-if="striker">
                                <span class="text-[10px] text-emerald-300 font-bold font-mono">
                                    🏏*<span x-text="striker.player_name ? striker.player_name.split(' ')[0] : ''"></span> <span class="text-white" x-text="striker.runs_scored"></span>
                                </span>
                            </template>
                            <template x-if="bowler">
                                <span class="text-[10px] text-rose-300 font-bold font-mono border-l border-white/20 pl-1.5">
                                    🎯<span x-text="bowler.player_name ? bowler.player_name.split(' ')[0] : ''"></span> <span class="text-white" x-text="bowler.wickets_taken + '/' + bowler.runs_conceded"></span>
                                </span>
                            </template>
                            <button type="button" @click="hideMobileScoreboard = true" 
                                    class="ml-1 px-1.5 py-0.5 rounded-lg bg-white/10 hover:bg-white/20 text-slate-300 hover:text-white text-[9px] font-bold" 
                                    title="Hide Overlay / Full Pitch View">
                                ✕
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Minimized floating badge when user tapped ✕ on mobile -->
                <div x-show="hideMobileScoreboard" class="block md:hidden pointer-events-auto">
                    <button type="button" @click="hideMobileScoreboard = false" 
                            class="px-2.5 py-1 rounded-full bg-slate-950/90 border border-white/20 text-white text-[10px] font-bold shadow-xl backdrop-blur-md flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span x-text="scoreRuns + '/' + scoreWickets + ' (' + scoreOvers + ' ov)'"></span>
                        <span class="text-emerald-400">📊 Show</span>
                    </button>
                </div>

                <!-- 2. TABLET & DESKTOP FULL TV GRAPHICS SCOREBOARD (SCREEN >= md) -->
                <div class="hidden md:grid bg-gradient-to-r from-slate-900/95 via-slate-950/95 to-indigo-950/95 backdrop-blur-md rounded-2xl border-2 border-white/15 p-2 sm:p-3 shadow-2xl md:grid-cols-12 gap-2 sm:gap-3 items-center pointer-events-auto">
                    
                    <!-- Left Block: Team Name & Giant Live Score (Cols: 4) -->
                    <div class="md:col-span-4 flex items-center justify-between border-r border-white/10 pr-3">
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
                    <div class="md:col-span-5 grid grid-cols-2 gap-2 border-r border-white/10 pr-3 text-[11px]">
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

                    <!-- Row 2: Extras, Wicket & Undo -->
                    <div class="grid grid-cols-5 gap-1.5 text-[10px] font-bold">
                        <button type="button" @click="recordBall('wide', { runs: 1 })" class="py-2 rounded-xl bg-amber-600/30 text-amber-300 border border-amber-500/40 hover:bg-amber-600/40 transition">Wide (WD)</button>
                        <button type="button" @click="recordBall('no_ball', { runs: 1 })" class="py-2 rounded-xl bg-orange-600/30 text-orange-300 border border-orange-500/40 hover:bg-orange-600/40 transition">No Ball (NB)</button>
                        <button type="button" @click="recordBall('swap_strike')" class="py-2 rounded-xl bg-white/10 text-slate-300 hover:bg-white/20 transition">Rotate ⇄</button>
                        <button type="button" @click="wicketModalOpen = true" class="py-2 rounded-xl bg-red-600 hover:bg-red-500 text-white font-black shadow-lg shadow-red-600/40 transition">☝️ Out</button>
                        <button type="button" @click="recordBall('undo')" class="py-2 rounded-xl bg-rose-950/70 hover:bg-rose-900/80 text-rose-300 border border-rose-500/50 font-black transition flex items-center justify-center gap-1 shadow-sm" title="Undo last delivery">
                            <span>↩️ Undo</span>
                        </button>
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

        <!-- Mobile Live Match Details Card (Below Video on Phones: Never Blocks the Pitch!) -->
        <div class="block md:hidden p-3.5 rounded-2xl bg-[#131b2e] border border-white/10 shadow-xl space-y-2.5">
            <!-- Row 1: Striker & Non-Striker -->
            <div class="grid grid-cols-2 gap-2 text-xs">
                <!-- Striker -->
                <div @if($isOwner) @click="openPlayerModal('all')" @endif 
                     class="p-2 rounded-xl bg-emerald-950/40 border border-emerald-500/40 truncate @if($isOwner) cursor-pointer @endif">
                    <div class="flex items-center justify-between text-[9px] font-bold text-emerald-400 mb-0.5">
                        <span>🏏 ON STRIKE</span>
                        @if($isOwner)<span class="text-[8px] bg-emerald-500/30 px-1 rounded text-white font-mono">✎</span>@endif
                    </div>
                    <div class="font-black text-white truncate text-xs" x-text="striker ? striker.player_name : 'Select Striker'"></div>
                    <div class="font-mono text-[11px] text-amber-300 font-bold mt-0.5" 
                         x-text="striker ? (striker.runs_scored + '* (' + striker.balls_faced + 'b)') : '0* (0b)'"></div>
                </div>

                <!-- Non-Striker -->
                <div @if($isOwner) @click="openPlayerModal('all')" @endif 
                     class="p-2 rounded-xl bg-black/40 border border-white/10 truncate @if($isOwner) cursor-pointer @endif">
                    <div class="flex items-center justify-between text-[9px] font-bold text-slate-400 mb-0.5">
                        <span>NON-STRIKER</span>
                        @if($isOwner)<span class="text-[8px] bg-indigo-500/30 px-1 rounded text-indigo-200 font-mono">✎</span>@endif
                    </div>
                    <div class="font-black text-slate-200 truncate text-xs" x-text="nonStriker ? nonStriker.player_name : 'Select Non-Striker'"></div>
                    <div class="font-mono text-[11px] text-slate-300 font-bold mt-0.5" 
                         x-text="nonStriker ? (nonStriker.runs_scored + ' (' + nonStriker.balls_faced + 'b)') : '0 (0b)'"></div>
                </div>
            </div>

            <!-- Row 2: Bowler & Over Timeline -->
            <div class="p-2.5 rounded-xl bg-black/40 border border-white/10 flex items-center justify-between gap-2 text-xs">
                <div @if($isOwner) @click="openPlayerModal('bowler')" @endif 
                     class="min-w-0 @if($isOwner) cursor-pointer @endif">
                    <div class="flex items-center gap-1.5 text-[9px] font-bold text-rose-400 uppercase">
                        <span>🎯 BOWLER</span>
                        @if($isOwner)<span class="text-[8px] bg-rose-500/30 px-1 rounded text-rose-200 font-mono">✎</span>@endif
                    </div>
                    <div class="font-black text-white truncate text-xs mt-0.5" x-text="bowler ? bowler.player_name : 'Select Bowler'"></div>
                    <div class="font-mono text-[10px] text-slate-300 font-bold" 
                         x-text="bowler ? (bowler.wickets_taken + '/' + bowler.runs_conceded + ' (' + bowler.overs_bowled + ' ov)') : '0/0 (0.0)'"></div>
                </div>

                <!-- This Over Balls -->
                <div class="text-right shrink-0">
                    <span class="text-[9px] font-bold text-slate-400 uppercase block mb-1">This Over</span>
                    <div class="flex items-center gap-1">
                        <template x-for="(ball, idx) in thisOver" :key="idx">
                            <span :class="{
                                    'bg-blue-600 text-white': ball === '4',
                                    'bg-purple-600 text-white': ball === '6',
                                    'bg-rose-600 text-white font-black': ball === 'W',
                                    'bg-amber-600 text-white': ball.includes('WD') || ball.includes('NB'),
                                    'bg-slate-800 text-slate-300': ball === '0',
                                    'bg-slate-700 text-white': ball !== '0' && ball !== '4' && ball !== '6' && ball !== 'W' && !ball.includes('WD') && !ball.includes('NB')
                                  }"
                                  class="px-1.5 py-0.5 rounded text-[9px] font-mono font-bold shadow-sm" 
                                  x-text="ball"></span>
                        </template>
                        <template x-if="!thisOver || thisOver.length === 0">
                            <span class="text-[10px] text-slate-500 font-mono">Yet to start</span>
                        </template>
                    </div>
                </div>
            </div>
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

        <!-- ============================================================== -->
        <!-- MATCH VIDEOS & REPLAYS GALLERY SECTION -->
        <!-- ============================================================== -->
        <div class="p-5 sm:p-6 rounded-3xl bg-[#131b2e] border border-white/10 shadow-2xl space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-white/10">
                <div class="flex items-center gap-3">
                    <span class="text-2xl">🎬</span>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-base sm:text-lg font-black text-white">Match Videos & Replays</h3>
                            <span class="px-2.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-400 font-mono text-xs font-bold border border-emerald-500/30" 
                                  x-text="matchVideos.length + ' Videos'"></span>
                        </div>
                        <p class="text-slate-400 text-xs mt-0.5">
                            Watch all recorded match streams, overs highlights, and video replays with TV Scoreboard overlay.
                        </p>
                    </div>
                </div>

                @if($isOwner)
                    <div class="text-xs text-amber-300 flex items-center gap-1.5 bg-amber-500/10 px-3 py-1.5 rounded-xl border border-amber-500/20 font-bold">
                        <span>🛡️</span>
                        <span>Organizer Controls Active: Delete Unwanted Videos</span>
                    </div>
                @endif
            </div>

            <!-- Videos Grid -->
            <template x-if="matchVideos.length > 0">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3.5 pt-1">
                    <template x-for="(video, index) in matchVideos" :key="video.id || index">
                        <div class="rounded-2xl bg-black/40 border border-white/10 hover:border-emerald-500/40 transition overflow-hidden flex flex-col justify-between group shadow-lg">
                            <!-- Card Body -->
                            <div class="p-4 space-y-2.5">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-10 h-10 rounded-xl bg-red-600/20 border border-red-500/30 flex items-center justify-center text-xl text-red-400 shrink-0 shadow-inner">
                                            📹
                                        </div>
                                        <div>
                                            <h4 class="font-black text-xs sm:text-sm text-white line-clamp-1 group-hover:text-emerald-400 transition" x-text="video.title || ('Match Video #' + (matchVideos.length - index))"></h4>
                                            <div class="text-[10px] text-slate-400 font-mono" x-text="video.created_at || 'Recorded Video'"></div>
                                        </div>
                                    </div>
                                    <span class="px-2 py-0.5 rounded-md bg-white/10 text-slate-300 font-mono font-bold text-[10px]" x-text="video.duration || 'Replay'"></span>
                                </div>

                                <!-- Score Snapshot Badge -->
                                <template x-if="video.score_snapshot">
                                    <div class="px-2.5 py-1.5 rounded-lg bg-white/5 border border-white/5 text-[11px] text-slate-300 flex items-center gap-1.5 font-mono">
                                        <span>🏏</span>
                                        <span class="truncate" x-text="video.score_snapshot"></span>
                                    </div>
                                </template>

                                <!-- File Size / Overlay Feature -->
                                <div class="flex items-center justify-between text-[10px] text-slate-400 font-mono pt-1">
                                    <span x-text="'Size: ' + (video.file_size || 'Cloud Stream')"></span>
                                    <span class="text-emerald-400 flex items-center gap-1 font-bold">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                        <span>TV Scoreboard + Logo</span>
                                    </span>
                                </div>
                            </div>

                            <!-- Card Footer Actions -->
                            <div class="p-3 bg-white/5 border-t border-white/5 flex items-center justify-between gap-2">
                                <button type="button" @click="selectAndPlayVideo(video)"
                                        class="flex-1 py-2 px-3 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-extrabold text-xs shadow-md transition flex items-center justify-center gap-1.5">
                                    <span>▶</span>
                                    <span>Watch Replay</span>
                                </button>

                                <a :href="fixVideoUrl(video.url)" :download="'Match_{{ $match->id }}_video_' + (video.id || index) + '.webm'" target="_blank"
                                   class="py-2 px-3 rounded-xl bg-white/10 hover:bg-white/20 text-slate-200 hover:text-white font-bold text-xs transition flex items-center justify-center" title="Download video to gallery">
                                    <span>📥</span>
                                </a>

                                @if($isOwner)
                                    <button type="button" @click="deleteMatchVideo(video)" :disabled="videoDeletingId === video.id"
                                            class="py-2 px-3 rounded-xl bg-rose-600/20 hover:bg-rose-600/40 border border-rose-500/40 text-rose-300 hover:text-rose-100 font-bold text-xs transition flex items-center justify-center gap-1" title="Delete video (Organizer Only)">
                                        <template x-if="videoDeletingId === video.id">
                                            <span class="w-3.5 h-3.5 rounded-full border-2 border-rose-400 border-t-transparent animate-spin"></span>
                                        </template>
                                        <template x-if="videoDeletingId !== video.id">
                                            <span>🗑️</span>
                                        </template>
                                    </button>
                                @endif
                            </div>
                        </div>
                    </template>
                </div>
            </template>

            <!-- Empty State -->
            <template x-if="matchVideos.length === 0">
                <div class="py-10 text-center space-y-2 border border-dashed border-white/10 rounded-2xl bg-black/20 p-6">
                    <div class="text-4xl mb-1">📹</div>
                    <div class="text-sm font-bold text-white">No Match Videos Recorded Yet</div>
                    <p class="text-xs text-slate-400 max-w-sm mx-auto">
                        @if($isOwner)
                            Start a live phone camera broadcast above. When you stop broadcasting, the recorded video will automatically save here with TV scoreboard overlay for all audience members!
                        @else
                            When the match organizer broadcasts with phone camera, recorded match videos will appear here for you to watch anytime!
                        @endif
                    </p>
                </div>
            </template>
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
