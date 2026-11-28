@extends('layouts.app')

@section('title', 'CricApp Reel Studio | 9:16 Video Maker')
@section('robots', 'noindex, nofollow')

@section('header')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <a href="{{ $article ? route('articles.show', $article->slug) : route('articles.index') }}"
               class="p-2 rounded-xl bg-slate-100 dark:bg-white/10 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-white/20 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <div>
                <h2 class="font-black text-xl text-slate-900 dark:text-white flex items-center gap-2">
                    <span>🎬 CricApp Reel Studio</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-500/15 text-emerald-500 border border-emerald-500/30">
                        9:16 Free Generator
                    </span>
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    @if($article)
                        Loaded from article: <strong class="text-slate-700 dark:text-slate-200">{{ \Illuminate\Support\Str::limit($article->title, 70) }}</strong>
                    @else
                        Create Instagram Reels &amp; YouTube Shorts from any CricApp article or custom script — zero paid subscriptions.
                    @endif
                </p>
            </div>
        </div>

        @if($recentArticles->isNotEmpty())
            <div class="flex items-center gap-2 w-full sm:w-auto">
                <select id="reel-article-picker"
                        class="flex-1 sm:w-64 px-3 py-2 rounded-xl bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-white/10 text-xs font-semibold text-slate-800 dark:text-slate-200">
                    <option value="">Load from published article...</option>
                    @foreach($recentArticles as $item)
                        <option value="{{ $item->id }}" @selected($article && $article->id === $item->id)>
                            {{ \Illuminate\Support\Str::limit($item->title, 58) }}
                        </option>
                    @endforeach
                </select>
                <button type="button" id="btn-load-article"
                        class="shrink-0 px-3.5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-black uppercase tracking-wider transition">
                    Load
                </button>
            </div>
        @endif
    </div>
@endsection

@section('content')
@php
    $social = $socialStatus ?? ['instagram' => [], 'youtube' => []];
    $igConnected = !empty($social['instagram']['connected']);
    $ytConnected = !empty($social['youtube']['connected']);
    $igOAuthReady = !empty($social['instagram']['oauth_ready']);
    $ytOAuthReady = !empty($social['youtube']['oauth_ready']);
    $returnQuery = ['return_to' => url()->full()];
@endphp
<div id="reel-studio-root"
     data-project="{{ json_encode($project, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}"
     data-curated-images="{{ json_encode($curatedImages, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}"
     data-social-status="{{ json_encode($social, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}"
     data-refresh-url="{{ route('reels.refresh') }}"
     data-tts-url="{{ route('reels.tts') }}"
     data-translate-url="{{ route('reels.translate-script') }}"
     data-convert-url="{{ route('reels.convert-mp4') }}"
     data-publish-url="{{ route('reels.publish') }}"
     data-proxy-url="{{ route('reels.proxy-image') }}"
     data-studio-url="{{ route('reels.index') }}"
     class="py-6 sm:py-8 bg-slate-950 min-h-screen text-slate-100 font-sans">

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @if(session('social_success'))
            <div class="mb-5 px-4 py-3 rounded-2xl bg-emerald-500/15 border border-emerald-500/30 text-emerald-200 text-xs sm:text-sm font-bold flex items-center justify-between gap-3">
                <span>✅ {{ session('social_success') }}</span>
            </div>
        @endif
        @if(session('social_error'))
            <div class="mb-5 px-4 py-3 rounded-2xl bg-rose-500/15 border border-rose-500/30 text-rose-200 text-xs sm:text-sm font-bold">
                ⚠️ {{ session('social_error') }}
            </div>
        @endif
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

            <!-- Left Column: Storyboard Slides + Voiceover + Caption -->
            <div class="lg:col-span-7 space-y-5 order-2 lg:order-1">

                <!-- 1. Voiceover & Music Mixer -->
                <div class="rounded-3xl bg-slate-900/75 border border-white/10 p-5 space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h3 class="text-sm font-black uppercase tracking-wider text-white flex items-center gap-2">
                                <span>🎙️ Voiceover &amp; Background Beat</span>
                            </h3>
                            <p class="text-xs text-slate-400">Free Indian-English &amp; Hindi AI voices, live mic recording, or custom audio upload.</p>
                        </div>
                        <span id="reel-voice-status"
                              class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-500/15 text-emerald-300 border border-emerald-500/30">
                            Auto AI Voiceover On
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="sm:col-span-2">
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">AI Narrator Voice (Free)</label>
                            <select id="reel-voice"
                                    class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-white/10 text-xs font-bold text-white">
                                @foreach($voiceOptions as $voice)
                                    <option value="{{ $voice['id'] }}" @selected($voice['id'] === ($project['voice'] ?? 'hi-IN-MadhurNeural'))>{{ $voice['label'] }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-[10px] text-emerald-400/90 font-semibold">
                                Voice change karne par neeche Spoken Voiceover &amp; Captions automatically Hinglish (Hindi voice) ya English (English voice) mein switch ho jaate hain.
                            </p>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Speaking Speed</label>
                            <select id="reel-voice-rate"
                                    class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-white/10 text-xs font-bold text-white">
                                <option value="+0%">Normal (1.0x)</option>
                                <option value="+5%" selected>Crisp (+5%)</option>
                                <option value="+12%">Fast Reel (+12%)</option>
                                <option value="+20%">Rapid (+20%)</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" id="btn-generate-tts"
                                class="px-4 py-2 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-slate-950 text-xs font-black uppercase tracking-wider shadow-lg shadow-emerald-500/20 transition cursor-pointer">
                            🎙️ Generate AI Voiceover
                        </button>
                        <button type="button" id="btn-record-mic"
                                class="px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 border border-white/10 text-xs font-bold text-white transition cursor-pointer">
                            🎤 Record Mic
                        </button>
                        <label class="px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 border border-white/10 text-xs font-bold text-slate-200 transition cursor-pointer">
                            📁 Upload Voice
                            <input type="file" id="input-upload-voice" accept="audio/*" class="hidden">
                        </label>
                        <button type="button" id="btn-clear-voice"
                                class="px-3 py-2 rounded-xl bg-slate-950 hover:bg-slate-800 border border-white/10 text-xs font-bold text-slate-400 transition cursor-pointer">
                            Mute Voice
                        </button>
                    </div>

                    <div id="reel-voice-feedback" class="hidden"></div>

                    <div class="pt-2 border-t border-white/10 grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
                        <div>
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Royalty-Free Stadium Beat</label>
                            <select id="reel-beat-style"
                                    class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-white/10 text-xs font-bold text-white">
                                <option value="pulse" selected>Pulse Groove (Built-in)</option>
                                <option value="hype">Matchday Hype (Built-in)</option>
                                <option value="off">No Beat / Voice Only</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Music Level (Auto-Ducks)</label>
                            <input type="range" id="reel-music-volume" min="0" max="100" value="25"
                                   class="w-full accent-emerald-500 cursor-pointer">
                        </div>
                        <div>
                            <label class="block text-center px-3 py-2 rounded-xl bg-slate-950 hover:bg-slate-800 border border-white/10 text-xs font-bold text-slate-300 transition cursor-pointer">
                                🎵 Upload BG Music
                                <input type="file" id="input-upload-music" accept="audio/*" class="hidden">
                            </label>
                        </div>
                    </div>
                </div>

                <!-- 2. Storyboard Slides -->
                <div class="rounded-3xl bg-slate-900/75 border border-white/10 p-5 space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h3 class="text-sm font-black uppercase tracking-wider text-white">
                                🎞️ Reel Storyboard Slides
                            </h3>
                            <p class="text-xs text-slate-400">Edit text, images &amp; spoken lines. Generating AI Voiceover automatically tunes slide timing.</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <button type="button" id="btn-refresh-reel"
                                    title="Nayi script angle aur naye background photos laayein"
                                    class="px-3.5 py-2 rounded-xl bg-gradient-to-r from-indigo-500 to-violet-500 hover:from-indigo-400 hover:to-violet-400 text-white text-xs font-black uppercase tracking-wider shadow-lg shadow-indigo-500/20 transition cursor-pointer">
                                🔄 Refresh Script &amp; BG
                            </button>
                            <button type="button" id="btn-shuffle-bg"
                                    title="Sirf background photos aur visual theme change karein"
                                    class="px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 border border-white/15 text-slate-200 text-xs font-bold transition cursor-pointer">
                                🖼️ Change BG
                            </button>
                            <button type="button" id="btn-add-slide"
                                    class="px-3.5 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 text-xs font-black uppercase tracking-wider transition cursor-pointer">
                                + Add Slide
                            </button>
                        </div>
                    </div>

                    <div id="reel-slides-list" class="space-y-3"></div>
                </div>

                <!-- 3. Ready Social Caption & Hashtags -->
                <div class="rounded-3xl bg-slate-900/75 border border-white/10 p-5 space-y-3">
                    <div class="flex items-center justify-between gap-2">
                        <div>
                            <h3 class="text-sm font-black uppercase tracking-wider text-white">📲 Instagram &amp; YouTube Shorts Caption</h3>
                            <p class="text-xs text-slate-400">Copy this description with link &amp; tags when uploading your Reel.</p>
                        </div>
                        <button type="button" id="btn-copy-caption"
                                class="px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 border border-white/10 text-xs font-bold text-white transition cursor-pointer">
                            📋 Copy Caption &amp; Tags
                        </button>
                    </div>
                    <textarea id="reel-social-caption" rows="5"
                              class="w-full px-3.5 py-2.5 rounded-2xl bg-slate-950 border border-white/10 text-xs text-slate-200 leading-relaxed"></textarea>
                </div>

            </div>

            <!-- Right Column: Live 9:16 Canvas Preview & Export -->
            <div class="lg:col-span-5 lg:sticky lg:top-6 space-y-4 order-1 lg:order-2">
                <div class="rounded-3xl bg-slate-900/85 border border-white/10 p-5 space-y-4 shadow-2xl">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Visual Theme</label>
                            <select id="reel-theme"
                                    class="px-3 py-1.5 rounded-xl bg-slate-950 border border-white/10 text-xs font-bold text-white">
                                <option value="stadium">Stadium Night (Emerald)</option>
                                <option value="crimson">Matchday Red (High Contrast)</option>
                                <option value="pitch">Gold &amp; Turf</option>
                            </select>
                        </div>

                        <div class="flex flex-col gap-1.5 text-xs font-bold text-slate-300">
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" id="reel-auto-voice-toggle" checked class="rounded text-emerald-500 bg-slate-950 border-white/20">
                                <span>Auto AI Voiceover</span>
                            </label>
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" id="reel-captions-toggle" checked class="rounded text-emerald-500 bg-slate-950 border-white/20">
                                <span>Animated Captions</span>
                            </label>
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" id="reel-convert-mp4-toggle" checked class="rounded text-emerald-500 bg-slate-950 border-white/20">
                                <span>Convert to IG MP4</span>
                            </label>
                        </div>
                    </div>

                    <!-- 9:16 Vertical Canvas -->
                    <div class="flex justify-center">
                        <canvas id="reel-canvas" width="720" height="1280"
                                class="aspect-[9/16] w-full max-w-[330px] rounded-2xl border border-white/15 shadow-2xl bg-[#050b18]"></canvas>
                    </div>

                    <!-- Scrubber -->
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between text-[11px] font-bold text-slate-400">
                            <span>Timeline</span>
                            <span id="reel-time-label">0.0s / 25.0s</span>
                        </div>
                        <input type="range" id="reel-scrub-bar" min="0" max="25" step="0.1" value="0.4"
                               class="w-full accent-emerald-500 cursor-pointer">
                    </div>

                    <!-- Playback & Export Actions -->
                    <div class="grid grid-cols-2 gap-3">
                        <button type="button" id="btn-preview-reel"
                                class="px-4 py-3 rounded-2xl bg-slate-800 hover:bg-slate-700 border border-white/15 text-white text-xs sm:text-sm font-black tracking-wide transition cursor-pointer">
                            ▶ Play Preview
                        </button>
                        <button type="button" id="btn-export-reel"
                                class="px-4 py-3 rounded-2xl bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-400 hover:to-orange-400 text-slate-950 text-xs sm:text-sm font-black tracking-wide shadow-lg shadow-amber-500/25 transition cursor-pointer">
                            🎬 Export Reel Video
                        </button>
                    </div>

                    <!-- 1-Click Publish to Instagram Reels & YouTube Shorts -->
                    <div class="rounded-2xl bg-slate-950/85 border border-indigo-500/25 p-4 space-y-3">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-xs font-black uppercase tracking-wider text-indigo-300">🚀 1-Click Auto-Post</span>
                            <span class="text-[10px] text-slate-400 font-semibold">Auto-converts MP4 &amp; publishes</span>
                        </div>

                        <div class="space-y-2 text-xs">
                            <!-- Instagram Channel Row -->
                            <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-slate-900/90 border border-white/10 px-3 py-2">
                                <label class="inline-flex items-center gap-2 font-bold {{ $igConnected ? 'text-pink-300 cursor-pointer' : 'text-slate-500 cursor-not-allowed' }}">
                                    <input type="checkbox" id="pub-check-instagram" value="instagram"
                                           @checked($igConnected) @disabled(!$igConnected)
                                           class="rounded text-pink-500 bg-slate-950 border-white/20">
                                    <span>Instagram Reels</span>
                                </label>
                                <div class="flex items-center gap-2">
                                    @if($igConnected)
                                        <span class="px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-300 text-[10px] font-bold border border-emerald-500/30">
                                            {{ $social['instagram']['account_name'] ?? 'Connected' }}
                                        </span>
                                        <form method="POST" action="{{ route('social.instagram.disconnect', $returnQuery) }}">
                                            @csrf
                                            <button type="submit" class="text-[10px] font-bold text-rose-400 hover:text-rose-300 underline cursor-pointer">Disconnect</button>
                                        </form>
                                    @elseif($igOAuthReady)
                                        <a href="{{ route('social.instagram.connect', $returnQuery) }}"
                                           class="px-2.5 py-1 rounded-lg bg-pink-600 hover:bg-pink-500 text-white text-[10px] font-black uppercase tracking-wider transition">
                                            Connect IG
                                        </a>
                                    @else
                                        <span class="text-[10px] text-amber-400 font-semibold">Set META_APP_ID/SECRET</span>
                                    @endif
                                </div>
                            </div>

                            <!-- YouTube Channel Row -->
                            <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-slate-900/90 border border-white/10 px-3 py-2">
                                <label class="inline-flex items-center gap-2 font-bold {{ $ytConnected ? 'text-rose-300 cursor-pointer' : 'text-slate-500 cursor-not-allowed' }}">
                                    <input type="checkbox" id="pub-check-youtube" value="youtube"
                                           @checked($ytConnected) @disabled(!$ytConnected)
                                           class="rounded text-rose-500 bg-slate-950 border-white/20">
                                    <span>YouTube Shorts</span>
                                </label>
                                <div class="flex items-center gap-2">
                                    @if($ytConnected)
                                        <span class="px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-300 text-[10px] font-bold border border-emerald-500/30">
                                            {{ $social['youtube']['account_name'] ?? 'Connected' }}
                                        </span>
                                        <form method="POST" action="{{ route('social.youtube.disconnect', $returnQuery) }}">
                                            @csrf
                                            <button type="submit" class="text-[10px] font-bold text-rose-400 hover:text-rose-300 underline cursor-pointer">Disconnect</button>
                                        </form>
                                    @elseif($ytOAuthReady)
                                        <a href="{{ route('social.youtube.connect', $returnQuery) }}"
                                           class="px-2.5 py-1 rounded-lg bg-rose-600 hover:bg-rose-500 text-white text-[10px] font-black uppercase tracking-wider transition">
                                            Connect YT
                                        </a>
                                    @else
                                        <span class="text-[10px] text-amber-400 font-semibold">Set YOUTUBE_CLIENT_ID/SECRET</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <select id="pub-youtube-privacy"
                                    class="px-2.5 py-2 rounded-xl bg-slate-900 border border-white/10 text-[11px] font-bold text-slate-200">
                                <option value="public" selected>YT: Public</option>
                                <option value="unlisted">YT: Unlisted</option>
                                <option value="private">YT: Private</option>
                            </select>
                            <button type="button" id="btn-publish-social"
                                    @disabled(!$igConnected && !$ytConnected)
                                    class="flex-1 px-4 py-2.5 rounded-xl bg-gradient-to-r from-pink-500 via-rose-500 to-amber-500 hover:from-pink-400 hover:to-amber-400 disabled:opacity-45 disabled:cursor-not-allowed text-white text-xs font-black uppercase tracking-wider shadow-lg shadow-rose-500/20 transition cursor-pointer">
                                🚀 1-Click Auto-Post Reel
                            </button>
                        </div>

                        @if(!$igConnected)
                            <details class="text-[11px] text-slate-400 pt-1">
                                <summary class="cursor-pointer font-semibold text-slate-300 hover:text-white">Or paste Meta Graph Token manually (quick connect)</summary>
                                <form method="POST" action="{{ route('social.instagram.manual-token', $returnQuery) }}" class="mt-2 flex items-center gap-2">
                                    @csrf
                                    <input type="password" name="access_token" required placeholder="Paste Meta User/Page Access Token..."
                                           class="flex-1 px-2.5 py-1.5 rounded-lg bg-slate-900 border border-white/10 text-xs text-white">
                                    <button type="submit" class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-[11px] font-bold cursor-pointer">Save</button>
                                </form>
                            </details>
                        @endif

                        <div id="reel-social-results" class="hidden space-y-1.5 text-xs"></div>
                    </div>

                    <!-- Status Notification -->
                    <div id="reel-status-box" class="hidden"></div>

                    <!-- Rendered Output Preview -->
                    <div id="reel-export-result" class="hidden pt-3 border-t border-white/10 space-y-3">
                        <div class="text-xs font-black uppercase tracking-wider text-emerald-400">
                            ✅ Rendered Reel Preview
                        </div>
                        <video id="reel-export-video" controls playsinline
                               class="w-full max-w-[260px] mx-auto rounded-xl border border-white/15 bg-black"></video>
                        <a id="reel-export-download" href="#" download="cricapp-reel.mp4"
                           class="block w-full text-center px-4 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 text-xs font-black uppercase tracking-wider transition">
                            ⬇️ Download Reel
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
