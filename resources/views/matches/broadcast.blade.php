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
                    CRICHEROES LIVE BROADCAST STUDIO
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
<div class="py-4 sm:py-6 bg-slate-950 min-h-screen text-slate-100 font-sans select-none" x-data="{
    // Broadcast Source ('camera' or 'youtube')
    streamSource: '{{ !empty($match->embed_stream_url) ? 'youtube' : 'camera' }}',
    streamUrlInput: '{{ addslashes($match->stream_url ?? '') }}',
    embedStreamUrl: '{{ addslashes($match->embed_stream_url ?? '') }}',
    editStreamModal: false,

    // Camera Stream States
    streamActive: false,
    facingMode: 'environment', // 'environment' for rear cricket ground camera, 'user' for selfie
    micMuted: true,
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

    // Toast
    toastMsg: '',
    showToast(msg) {
        this.toastMsg = msg;
        setTimeout(() => { this.toastMsg = ''; }, 3000);
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

    // Camera Broadcaster Engine
    async startCamera() {
        try {
            if (window.localMediaStream) {
                window.localMediaStream.getTracks().forEach(track => track.stop());
            }

            const constraints = {
                video: {
                    facingMode: { ideal: this.facingMode },
                    width: { ideal: 1920 },
                    height: { ideal: 1080 }
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
            this.streamActive = true;
            this.showToast('🔴 Camera Live! Ground streaming active.');
        } catch(err) {
            console.error('Camera access error:', err);
            this.showToast('⚠️ Camera permission denied or device not found.');
        }
    },

    stopCamera() {
        if (window.localMediaStream) {
            window.localMediaStream.getTracks().forEach(track => track.stop());
            window.localMediaStream = null;
        }
        const videoElem = document.getElementById('broadcastVideo');
        if (videoElem) videoElem.srcObject = null;
        this.streamActive = false;
        this.showToast('⏹️ Broadcast stopped.');
    },

    async toggleCameraFlip() {
        this.facingMode = (this.facingMode === 'environment') ? 'user' : 'environment';
        if (this.streamActive) {
            await this.startCamera();
        }
    },

    toggleMic() {
        this.micMuted = !this.micMuted;
        if (window.localMediaStream) {
            const audioTracks = window.localMediaStream.getAudioTracks();
            audioTracks.forEach(t => t.enabled = !this.micMuted);
        }
        this.showToast(this.micMuted ? '🔇 Microphone muted' : '🎙️ Microphone on');
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
                    setTimeout(() => { this.triggerBanner('over', '🔔 OVER COMPLETED'); }, 1200);
                }

                this.syncState(data);
            } else {
                this.showToast(data.message || 'Scoring error');
            }
        } catch(e) {
            console.error(e);
        } finally {
            this.isRecording = false;
        }
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
        if (data.match) {
            this.matchStatus = data.match.status;
            this.matchResult = data.match.match_result;
        }
    },

    // Background State Poller (Auto-syncs overlay with scorer)
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
                    this.matchStatus = data.match_status;
                    this.matchResult = data.match_result;

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
}" x-init="startPolling()">

    <!-- Floating Notification -->
    <div x-cloak x-show="toastMsg" x-transition 
         class="fixed top-5 left-1/2 -translate-x-1/2 z-50 bg-slate-900/90 text-white px-5 py-2.5 rounded-full shadow-2xl font-bold text-xs flex items-center gap-2 border border-emerald-500/40 backdrop-blur-md">
        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
        <span x-text="toastMsg"></span>
    </div>

    <!-- Modal: Edit / Add YouTube / Facebook Live Stream URL -->
    <div x-cloak x-show="editStreamModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100">
        <div class="bg-[#131b2e] border border-white/15 rounded-3xl p-6 max-w-lg w-full space-y-4 shadow-2xl">
            <div class="flex items-center justify-between pb-3 border-b border-white/10">
                <div class="flex items-center gap-2 font-black text-white text-base">
                    <span class="text-xl">📺</span>
                    <span>Set YouTube / Facebook Live Stream</span>
                </div>
                <button type="button" @click="editStreamModal = false" class="text-slate-400 hover:text-white font-bold text-sm">✕</button>
            </div>

            <div class="space-y-3 text-xs">
                <p class="text-slate-300">
                    Paste YouTube Live or Facebook Live URL here. The live video will play inside the studio with the TV scoreboard overlay updating in real-time!
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

    <div class="max-w-6xl mx-auto px-2 sm:px-4 space-y-4">

        <!-- ============================================================== -->
        <!-- MAIN BROADCAST ARENA CONTAINER (FULLSCREEN READY) -->
        <!-- ============================================================== -->
        <div id="broadcastArenaContainer" 
             class="relative w-full aspect-video sm:rounded-3xl overflow-hidden bg-black shadow-2xl border border-white/10 flex flex-col justify-between">

            <!-- 1. LIVE VIDEO CAMERA STREAM -->
            <video x-show="streamSource === 'camera'" id="broadcastVideo" autoplay playsinline muted 
                   class="absolute inset-0 w-full h-full object-cover z-0"></video>

            <!-- 2. EMBEDDED YOUTUBE / FACEBOOK LIVE STREAM -->
            <template x-if="streamSource === 'youtube' && embedStreamUrl">
                <iframe :src="embedStreamUrl" 
                        class="absolute inset-0 w-full h-full z-0 border-0 pointer-events-auto" 
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" 
                        allowfullscreen></iframe>
            </template>

            <!-- Camera Idle / Not Started Placeholder -->
            <div x-show="streamSource === 'camera' && !streamActive" 
                 class="absolute inset-0 z-0 bg-gradient-to-br from-slate-950 via-slate-900 to-indigo-950 flex flex-col items-center justify-center p-6 text-center">
                <div class="w-16 h-16 rounded-full bg-red-600/10 border-2 border-red-500/30 flex items-center justify-center text-3xl mb-3 shadow-inner">
                    📹
                </div>
                <h3 class="text-xl sm:text-2xl font-black text-white">Live Camera Broadcast Idle</h3>
                <p class="text-slate-400 text-xs sm:text-sm max-w-md mt-1">
                    Click <b class="text-white">"Start Camera Stream"</b> below to start broadcasting ground action from your device camera.
                </p>
                <button type="button" @click="startCamera()" 
                        class="mt-4 px-6 py-2.5 rounded-2xl bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-500 text-white font-black text-xs sm:text-sm uppercase tracking-wider shadow-xl shadow-red-600/30 transition flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-white animate-ping"></span>
                    <span>Start Camera Stream 🔴</span>
                </button>
            </div>

            <!-- YouTube URL Missing Placeholder -->
            <div x-show="streamSource === 'youtube' && !embedStreamUrl" 
                 class="absolute inset-0 z-0 bg-gradient-to-br from-slate-950 via-slate-900 to-indigo-950 flex flex-col items-center justify-center p-6 text-center">
                <div class="w-16 h-16 rounded-full bg-red-600/10 border-2 border-red-500/30 flex items-center justify-center text-3xl mb-2 text-red-500 shadow-inner">
                    📺
                </div>
                <h3 class="text-xl font-black text-white">No YouTube / Facebook Live Link Added</h3>
                <p class="text-slate-400 text-xs max-w-sm mt-1">Enter a YouTube or Facebook Live stream link to watch with TV graphics overlay.</p>
                <button type="button" @click="editStreamModal = true" 
                        class="mt-4 px-5 py-2.5 rounded-2xl bg-red-600 hover:bg-red-500 text-white font-black text-xs uppercase tracking-wider shadow-xl shadow-red-600/30 transition">
                    + Add Live Stream Link 🔗
                </button>
            </div>

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

                    <!-- Source Switcher (Camera vs YouTube/Facebook) -->
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

                    <div class="hidden sm:flex bg-black/60 backdrop-blur-md px-3 py-1 rounded-xl border border-white/10 text-[11px] sm:text-xs items-center">
                        <span class="text-amber-400 font-extrabold uppercase">{{ $match->tournament_name ?? 'Tournament Match' }}</span>
                        <span class="text-slate-400 mx-1.5">•</span>
                        <span class="text-slate-300 font-mono">{{ $match->total_overs }} Overs</span>
                    </div>
                </div>

                <!-- Floating Broadcaster Control Bar -->
                <div class="flex items-center gap-1 sm:gap-1.5 bg-black/70 backdrop-blur-md p-1.5 rounded-2xl border border-white/10 shadow-xl pointer-events-auto">
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

                    <button type="button" @click="toggleFullscreen()" 
                            class="p-1.5 rounded-xl hover:bg-white/10 text-slate-300 hover:text-white transition"
                            title="Toggle Fullscreen">
                        <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-5h-4m4 0v4m0-4l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                        </svg>
                    </button>

                    @if($isOwner)
                        <!-- Quick Keypad Drawer Toggle -->
                        <button type="button" @click="showKeypadDrawer = !showKeypadDrawer" 
                                :class="showKeypadDrawer ? 'bg-amber-500 text-slate-950 font-black' : 'bg-white/10 text-white'"
                                class="px-2 py-1 rounded-xl text-[10px] sm:text-xs font-bold transition flex items-center gap-1 shadow-sm">
                            <span>⚡ Scorer</span>
                        </button>
                    @endif
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
                        <div class="p-1.5 rounded-xl bg-emerald-950/40 border border-emerald-500/40 truncate">
                            <div class="flex items-center justify-between text-[9px] font-bold text-emerald-400 mb-0.5">
                                <span>🏏 STRIKER</span>
                                <span>ON STRIKE</span>
                            </div>
                            <div class="font-black text-white truncate" x-text="striker ? striker.player_name : 'Striker'"></div>
                            <div class="font-mono text-[10px] text-amber-300 font-bold mt-0.5" 
                                 x-text="striker ? (striker.runs_scored + '* (' + striker.balls_faced + 'b)') : '0* (0b)'"></div>
                        </div>

                        <!-- Non-Striker -->
                        <div class="p-1.5 rounded-xl bg-black/40 border border-white/10 truncate">
                            <div class="text-[9px] font-bold text-slate-400 mb-0.5">NON-STRIKER</div>
                            <div class="font-black text-slate-200 truncate" x-text="nonStriker ? nonStriker.player_name : 'Non-Striker'"></div>
                            <div class="font-mono text-[10px] text-slate-300 font-bold mt-0.5" 
                                 x-text="nonStriker ? (nonStriker.runs_scored + ' (' + nonStriker.balls_faced + 'b)') : '0 (0b)'"></div>
                        </div>
                    </div>

                    <!-- Right Block: Bowler Figures & This Over Timeline (Cols: 3) -->
                    <div class="md:col-span-3 text-[11px] space-y-1">
                        <div class="flex items-center justify-between">
                            <span class="text-[9px] font-bold text-rose-400 uppercase">🎯 BOWLER</span>
                            <span class="font-mono text-[10px] text-white font-extrabold" 
                                  x-text="bowler ? (bowler.wickets_taken + '/' + bowler.runs_conceded + ' (' + bowler.overs_bowled + ' ov)') : '0/0 (0.0)'"></span>
                        </div>
                        <div class="font-black text-white truncate text-xs" x-text="bowler ? bowler.player_name : 'Select Bowler'"></div>

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
                        <button type="button" @click="recordBall('wide', { runs: 1 })" class="py-2 rounded-xl bg-amber-600/30 text-amber-300 border border-amber-500/40">Wide (WD)</button>
                        <button type="button" @click="recordBall('no_ball', { runs: 1 })" class="py-2 rounded-xl bg-orange-600/30 text-orange-300 border border-orange-500/40">No Ball (NB)</button>
                        <button type="button" @click="recordBall('swap_strike')" class="py-2 rounded-xl bg-white/10 text-slate-300">Swap Strike ⇄</button>
                        <button type="button" @click="recordBall('wicket')" class="py-2 rounded-xl bg-red-600 text-white font-black">☝️ Wicket</button>
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
                        <span>CricHeroes Live Broadcast Studio</span>
                        <span class="px-2 py-0.5 rounded-md bg-emerald-500/20 text-emerald-400 font-mono text-[10px] font-bold border border-emerald-500/30">
                            Auto-Sync Active
                        </span>
                    </div>
                    <div class="text-slate-400 mt-0.5">
                        The TV lower-third scoreboard overlay automatically updates in real time over both Device Camera and YouTube/Facebook streams.
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <button type="button" @click="editStreamModal = true" class="px-3.5 py-2 rounded-xl bg-red-600/20 hover:bg-red-600/30 border border-red-500/40 text-red-300 hover:text-white font-bold transition flex items-center gap-1.5">
                    <span>🔗 Stream Link</span>
                </button>
                <a href="{{ route('matches.show', $match) }}" class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/20 text-white font-bold transition">
                    Full Scorecard ➔
                </a>
            </div>
        </div>

    </div>
</div>
@endsection
