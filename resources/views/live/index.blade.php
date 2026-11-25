@extends('layouts.app')

@section('title', 'CricApp - Real-Time Cricket Scores, Ball-by-Ball & Updates')

@section('content')
    <div id="live-scores-root"
         data-live='@json($live)'
         data-scheduled='@json($scheduled)'
         data-completed='@json($completed)'
         data-cache-ttl='{{ ($cacheTtl["current_matches"] ?? 900) / 60 }}'
         data-initial-tab='{{ $tab ?? "live" }}'
         data-initial-section='{{ $section ?? "international" }}'>
        {{-- Animated Bat & Ball Loader while React mounts --}}
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
            <x-cricket-loader inline="true" label="Loading live match center & scorecards..." />
        </div>
    </div>

    {{-- 📰 Featured Cricket News & Articles Showcase on Home Page --}}
    @if(isset($articles) && $articles->count() > 0)
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-12 pt-4">
            <div class="p-6 sm:p-8 rounded-3xl bg-white dark:bg-gradient-to-br dark:from-[#0e1628] dark:via-[#111c33] dark:to-[#0e1628] border border-slate-200 dark:border-white/10 shadow-sm dark:shadow-2xl space-y-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-slate-200 dark:border-white/10">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30">
                                📰 Cricket Magazine
                            </span>
                            <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                        </div>
                        <h2 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white mt-1.5 tracking-tight flex items-center gap-2">
                            <span>Latest Cricket News & Tactical Stories</span>
                        </h2>
                        <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-400 mt-1">
                            Catch up on deep tactical breakdowns, coaching secrets, and player perspectives.
                        </p>
                    </div>

                    <div class="flex items-center gap-2.5 shrink-0">
                        <a href="{{ route('articles.index') }}" 
                           class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-black text-xs uppercase tracking-wider shadow-lg shadow-amber-500/20 transition transform active:scale-95">
                            <span>Browse All Stories (20+)</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                            </svg>
                        </a>
                    </div>
                </div>

                <!-- Articles Grid -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    @foreach($articles as $art)
                        <article class="group rounded-2xl bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-white/10 hover:border-amber-500/40 transition duration-300 overflow-hidden shadow-sm dark:shadow-lg hover:shadow-xl dark:hover:shadow-2xl flex flex-col justify-between">
                            <div>
                                <!-- Image with Category Badge -->
                                <div class="relative aspect-video overflow-hidden bg-slate-950">
                                    <img src="{{ $art->cover_image_url }}" 
                                         alt="{{ $art->title }}" 
                                         onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1540747913346-19e32dc3e97e?auto=format&fit=crop&w=1200&q=80';"
                                         class="w-full h-full object-cover group-hover:scale-105 transition duration-500" 
                                         loading="lazy" />
                                    <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/20 to-transparent"></div>
                                    <span class="absolute top-3 left-3 px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider bg-slate-950/80 border border-white/20 text-amber-300 backdrop-blur-md">
                                        {{ $art->category }}
                                    </span>
                                    <span class="absolute bottom-3 right-3 text-[10px] text-slate-300 font-bold bg-slate-950/70 px-2 py-0.5 rounded-md backdrop-blur-sm">
                                        ⏱️ {{ $art->reading_time }} min read
                                    </span>
                                </div>

                                <!-- Text Content -->
                                <div class="p-5 space-y-2.5">
                                    <h3 class="text-sm sm:text-base font-black text-slate-900 dark:text-white group-hover:text-amber-600 dark:group-hover:text-amber-400 transition line-clamp-2 leading-snug">
                                        <a href="{{ route('articles.show', $art->slug) }}">
                                            {{ $art->title }}
                                        </a>
                                    </h3>
                                    <p class="text-xs text-slate-600 dark:text-slate-400 line-clamp-2 leading-relaxed">
                                        {{ $art->excerpt }}
                                    </p>
                                </div>
                            </div>

                            <!-- Card Footer -->
                            <div class="px-5 pb-5 pt-2 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400 border-t border-slate-100 dark:border-white/5">
                                <span class="font-bold text-slate-700 dark:text-slate-300 truncate max-w-[140px]">
                                    ✍️ {{ $art->author_name }}
                                </span>
                                <a href="{{ route('articles.show', $art->slug) }}" 
                                   class="text-amber-600 dark:text-amber-400 font-black hover:text-amber-500 dark:hover:text-amber-300 inline-flex items-center gap-1 group-hover:translate-x-0.5 transition">
                                    <span>Read Story</span>
                                    <span>→</span>
                                </a>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
@endsection
