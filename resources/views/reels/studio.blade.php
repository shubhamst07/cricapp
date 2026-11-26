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
<div id="reel-studio-root"
     data-project="{{ json_encode($project, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}"
     data-curated-images="{{ json_encode($curatedImages, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}"
     data-tts-url="{{ route('reels.tts') }}"
     data-convert-url="{{ route('reels.convert-mp4') }}"
     data-proxy-url="{{ route('reels.proxy-image') }}"
     data-studio-url="{{ route('reels.index') }}"
     class="py-6 sm:py-8 bg-slate-950 min-h-screen text-slate-100 font-sans">

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
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
                              class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-slate-800 text-slate-400 border border-white/10">
                            No Voiceover
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="sm:col-span-2">
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">AI Narrator Voice (Free)</label>
                            <select id="reel-voice"
                                    class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-white/10 text-xs font-bold text-white">
                                @foreach($voiceOptions as $voice)
                                    <option value="{{ $voice['id'] }}">{{ $voice['label'] }}</option>
                                @endforeach
                            </select>
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
                            Clear Voice
                        </button>
                    </div>

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
                    <div class="flex items-center justify-between gap-2">
                        <div>
                            <h3 class="text-sm font-black uppercase tracking-wider text-white">
                                🎞️ Reel Storyboard Slides
                            </h3>
                            <p class="text-xs text-slate-400">Edit text, images &amp; spoken lines. Generating AI Voiceover automatically tunes slide timing.</p>
                        </div>
                        <button type="button" id="btn-add-slide"
                                class="px-3.5 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 text-xs font-black uppercase tracking-wider transition cursor-pointer">
                            + Add Slide
                        </button>
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
                                <input type="checkbox" id="reel-captions-toggle" checked class="rounded text-emerald-500 bg-slate-950 border-white/20">
                                <span>Animated Word Captions</span>
                            </label>
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" id="reel-convert-mp4-toggle" checked class="rounded text-emerald-500 bg-slate-950 border-white/20">
                                <span>Convert to IG/Shorts MP4</span>
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
