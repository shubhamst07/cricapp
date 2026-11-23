@extends('layouts.app')

@section('title', $article->title . ' | CricApp News')

@section('header')
    <div class="flex items-center gap-3">
        <a href="{{ route('articles.index') }}"
           class="p-2 rounded-xl bg-slate-100 dark:bg-white/10 text-slate-700 dark:text-slate-300 hover:text-white hover:bg-slate-200 dark:hover:bg-white/20 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
            </svg>
        </a>
        <div class="min-w-0">
            <div class="flex items-center gap-2 text-xs font-bold text-slate-400">
                <a href="{{ route('articles.index') }}" class="hover:text-amber-400 transition">Articles</a>
                <span>›</span>
                <span class="text-amber-400 truncate">{{ $article->category }}</span>
            </div>
            <h2 class="font-black text-lg sm:text-xl text-slate-900 dark:text-white truncate mt-0.5">
                {{ $article->title }}
            </h2>
        </div>
    </div>
@endsection

@section('content')
<div class="py-6 sm:py-8 bg-slate-950 min-h-screen text-slate-100 font-sans" x-data="{ copied: false }">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

        @if(session('success'))
            <div class="p-4 rounded-2xl bg-emerald-500/15 border border-emerald-500/30 text-emerald-300 text-xs sm:text-sm font-bold flex items-center gap-2.5 shadow-lg">
                <span class="text-lg">✅</span>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        <!-- ============================================================== -->
        <!-- ARTICLE HEADER CARD -->
        <!-- ============================================================== -->
        <article class="p-5 sm:p-8 rounded-3xl bg-[#131b2e] border border-white/10 shadow-2xl space-y-6">
            <!-- Badges Row & Admin Actions -->
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 pb-4">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider border {{ $article->category_color }}">
                        {{ $article->category }}
                    </span>
                    @if($article->is_featured)
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30">
                            ⭐ Featured
                        </span>
                    @endif
                    <span class="text-xs text-slate-400">
                        ⏱️ {{ $article->estimated_read_time }} min read
                    </span>
                    <span class="text-slate-600">•</span>
                    <span class="text-xs text-slate-400">
                        👁️ {{ $article->views_count }} views
                    </span>
                </div>

                <!-- Admin Action Controls -->
                @if($isAdmin)
                    <div class="flex items-center gap-2">
                        <a href="{{ route('articles.edit', $article) }}" 
                           class="px-3 py-1.5 rounded-xl bg-blue-600/20 hover:bg-blue-600/30 border border-blue-500/30 text-blue-300 text-xs font-bold transition flex items-center gap-1.5">
                            <span>✏️ Edit</span>
                        </a>
                        <form action="{{ route('articles.destroy', $article) }}" method="POST" 
                              onsubmit="return confirm('Are you sure you want to delete this article?');" class="inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" 
                                    class="px-3 py-1.5 rounded-xl bg-rose-600/20 hover:bg-rose-600/30 border border-rose-500/30 text-rose-300 text-xs font-bold transition flex items-center gap-1.5">
                                <span>🗑️ Delete</span>
                            </button>
                        </form>
                    </div>
                @endif
            </div>

            <!-- Title & Excerpt -->
            <div class="space-y-3">
                <h1 class="text-2xl sm:text-4xl font-black text-white leading-tight">
                    {{ $article->title }}
                </h1>
                @if($article->excerpt)
                    <p class="text-sm sm:text-base text-slate-300 leading-relaxed italic border-l-4 border-amber-500 pl-4 py-1">
                        {{ $article->excerpt }}
                    </p>
                @endif
            </div>

            <!-- Author & Date Ribbon -->
            <div class="flex items-center justify-between border-y border-white/10 py-3.5 flex-wrap gap-3">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-amber-500 to-orange-500 text-slate-950 font-black text-sm flex items-center justify-center shrink-0 shadow-md">
                        {{ strtoupper(substr($article->author_name ?: 'A', 0, 1)) }}
                    </div>
                    <div>
                        <div class="font-bold text-sm text-white flex items-center gap-1.5">
                            <span>{{ $article->author_name ?: 'LiveCricket Staff' }}</span>
                            <span class="w-3.5 h-3.5 rounded-full bg-blue-500 text-white text-[9px] flex items-center justify-center font-bold">✓</span>
                        </div>
                        <div class="text-xs text-slate-400">
                            Published on {{ $article->formatted_date }}
                        </div>
                    </div>
                </div>

                <!-- 1-Tap Social Share Bar -->
                @php
                    $shareText = urlencode("🏏 Read: {$article->title}\n\n" . route('articles.show', $article->slug));
                    $shareUrl = urlencode(route('articles.show', $article->slug));
                @endphp
                <div class="flex items-center gap-2">
                    <a href="https://api.whatsapp.com/send?text={{ $shareText }}" target="_blank"
                       class="px-3.5 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition flex items-center gap-1.5 shadow-md">
                        <span>💬 WhatsApp</span>
                    </a>
                    <a href="https://twitter.com/intent/tweet?text={{ $shareText }}" target="_blank"
                       class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-white text-xs font-bold transition flex items-center gap-1">
                        <span>𝕏 Post</span>
                    </a>
                    <button type="button" 
                            @click="navigator.clipboard.writeText('{{ route('articles.show', $article->slug) }}'); copied = true; setTimeout(() => copied = false, 2500)"
                            class="px-3 py-1.5 rounded-xl bg-white/5 hover:bg-white/10 text-slate-300 text-xs font-bold transition flex items-center gap-1">
                        <span x-text="copied ? '✅ Copied!' : '🔗 Copy'"></span>
                    </button>
                </div>
            </div>

            <!-- Featured Image -->
            <div class="rounded-2xl overflow-hidden border border-white/10 shadow-xl max-h-[460px]">
                <img src="{{ $article->cover_image_url }}" 
                     alt="{{ $article->title }}" 
                     class="w-full h-full object-cover max-h-[460px]" />
            </div>

            <!-- Article Formatted Content -->
            <div class="prose prose-invert max-w-none text-slate-200 leading-relaxed text-sm sm:text-base space-y-5 pt-2">
                @php
                    // Clean line-based formatting supporting markdown headers and paragraphs
                    $lines = explode("\n", $article->content);
                @endphp

                @foreach($lines as $line)
                    @php $trimmed = trim($line); @endphp
                    @if(str_starts_with($trimmed, '### '))
                        <h3 class="text-xl sm:text-2xl font-black text-white pt-4 pb-1 border-b border-white/10 flex items-center gap-2">
                            <span>🏏</span>
                            <span>{{ substr($trimmed, 4) }}</span>
                        </h3>
                    @elseif(str_starts_with($trimmed, '## '))
                        <h2 class="text-2xl sm:text-3xl font-black text-amber-400 pt-5 pb-1">
                            {{ substr($trimmed, 3) }}
                        </h2>
                    @elseif(str_starts_with($trimmed, '# '))
                        <h1 class="text-3xl sm:text-4xl font-black text-white pt-6 pb-2">
                            {{ substr($trimmed, 2) }}
                        </h1>
                    @elseif(str_starts_with($trimmed, '- '))
                        <li class="ml-4 list-disc text-slate-300">
                            {{ substr($trimmed, 2) }}
                        </li>
                    @elseif(!empty($trimmed))
                        <p class="text-slate-300 leading-relaxed">
                            {!! nl2br(e($trimmed)) !!}
                        </p>
                    @endif
                @endforeach
            </div>

            <!-- Tags Section -->
            @if($article->tags)
                <div class="pt-6 border-t border-white/10 flex items-center gap-2 flex-wrap text-xs">
                    <span class="text-slate-400 font-bold">🏷️ Tags:</span>
                    @foreach(explode(',', $article->tags) as $tag)
                        @if(trim($tag))
                            <span class="px-2.5 py-1 rounded-lg bg-white/5 text-slate-300 border border-white/5 font-mono">
                                #{{ trim($tag) }}
                            </span>
                        @endif
                    @endforeach
                </div>
            @endif

            <!-- Bottom Share CTA -->
            <div class="p-5 rounded-2xl bg-gradient-to-r from-amber-500/10 via-orange-500/10 to-amber-600/10 border border-amber-500/30 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h4 class="font-black text-sm text-white">Loved this cricket story?</h4>
                    <p class="text-xs text-slate-400">Share it with your teammates and cricket circle!</p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="https://api.whatsapp.com/send?text={{ $shareText }}" target="_blank"
                       class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs transition flex items-center gap-2 shadow-md">
                        <span>💬 Share on WhatsApp</span>
                    </a>
                </div>
            </div>
        </article>

        <!-- ============================================================== -->
        <!-- RELATED STORIES GRID -->
        <!-- ============================================================== -->
        @if($relatedArticles->count() > 0)
            <div class="space-y-4 pt-4">
                <h3 class="font-black text-xl text-white flex items-center gap-2">
                    <span>📚 More Recommended Stories</span>
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                    @foreach($relatedArticles as $related)
                        <a href="{{ route('articles.show', $related->slug) }}" 
                           class="rounded-2xl bg-[#131b2e] border border-white/10 overflow-hidden shadow-lg hover:border-amber-500/40 transition group flex flex-col justify-between">
                            <div>
                                <div class="h-36 overflow-hidden bg-slate-900">
                                    <img src="{{ $related->cover_image_url }}" 
                                         alt="{{ $related->title }}" 
                                         class="w-full h-full object-cover group-hover:scale-105 transition duration-500" />
                                </div>
                                <div class="p-4 space-y-1.5">
                                    <div class="flex items-center gap-1.5 text-[10px] text-amber-400 font-bold">
                                        <span>{{ $related->category }}</span>
                                        <span>•</span>
                                        <span>{{ $related->estimated_read_time }} min</span>
                                    </div>
                                    <h4 class="font-bold text-sm text-white group-hover:text-amber-400 transition line-clamp-2 leading-snug">
                                        {{ $related->title }}
                                    </h4>
                                </div>
                            </div>
                            <div class="p-4 pt-0 text-[11px] text-slate-500">
                                {{ $related->formatted_date }}
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

    </div>
</div>
@endsection
