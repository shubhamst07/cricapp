@extends('layouts.app')

@section('title', 'Cricket News, Coaching & Tactical Stories | CricApp.in')
@section('meta_description', 'Read expert cricket match reports, tactical breakdowns, coaching drills, grassroots tournament coverage, and cricket news stories on CricApp.in.')
@section('canonical', route('articles.index'))

@section('header')
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-bold text-amber-500 dark:text-amber-400 uppercase tracking-wider">
                <span>📰 Cricket Magazine & Editorial</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white mt-1 tracking-tight">
                News, Coaching & Stories
            </h1>
            <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-400 mt-1">
                Match analysis, coaching drills, grassroots tournaments & expert cricket perspectives.
            </p>
        </div>

        @if($isAdmin)
            <div class="flex items-center gap-2">
                <a href="{{ route('articles.create') }}" 
                   class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-400 hover:to-orange-400 text-slate-950 font-black text-xs uppercase tracking-wider shadow-lg shadow-amber-500/25 transition transform active:scale-95">
                    <span>✍️ Write Article</span>
                </a>
            </div>
        @endif
    </div>
@endsection

@section('content')
<div class="py-6 sm:py-8 bg-slate-50 dark:bg-[#080d1a] min-h-screen text-slate-900 dark:text-slate-100 font-sans transition-colors duration-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

        @if(session('success'))
            <div class="p-4 rounded-2xl bg-emerald-500/15 border border-emerald-500/30 text-emerald-600 dark:text-emerald-300 text-xs sm:text-sm font-bold flex items-center gap-2.5 shadow-sm dark:shadow-lg">
                <span class="text-lg">✅</span>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        <!-- Filter & Search Bar -->
        <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-[#131b2e] border border-slate-200 dark:border-white/10 shadow-sm dark:shadow-xl space-y-4 transition-colors">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <!-- Search Box -->
                <form action="{{ route('articles.index') }}" method="GET" class="flex-1 max-w-md">
                    @if($category)
                        <input type="hidden" name="category" value="{{ $category }}" />
                    @endif
                    <div class="relative">
                        <input type="text" name="q" value="{{ $search }}" 
                               placeholder="Search articles, tactics, news..."
                               class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-white/10 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 text-xs sm:text-sm focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition" />
                        <span class="absolute left-3 top-3 text-slate-400 dark:text-slate-500 text-sm">🔍</span>
                        @if($search)
                            <a href="{{ route('articles.index', ['category' => $category]) }}" 
                               class="absolute right-3 top-2.5 text-xs text-slate-400 hover:text-slate-700 dark:hover:text-white">✕</a>
                        @endif
                    </div>
                </form>

                <!-- Category Pills (Horizontally scrollable on mobile) -->
                <div class="flex items-center gap-2 overflow-x-auto pb-1 no-scrollbar text-xs">
                    <a href="{{ route('articles.index', ['q' => $search]) }}"
                       class="px-3.5 py-2 rounded-xl font-bold whitespace-nowrap transition {{ empty($category) || strtolower($category) === 'all' ? 'bg-amber-500 text-slate-950 shadow-md' : 'bg-slate-100 hover:bg-slate-200 dark:bg-slate-900 dark:hover:bg-white/10 text-slate-700 dark:text-slate-300' }}">
                        All Stories
                    </a>
                    @foreach($categories as $cat)
                        <a href="{{ route('articles.index', ['category' => $cat, 'q' => $search]) }}"
                           class="px-3.5 py-2 rounded-xl font-bold whitespace-nowrap transition {{ $category === $cat ? 'bg-amber-500 text-slate-950 shadow-md' : 'bg-slate-100 hover:bg-slate-200 dark:bg-slate-900 dark:hover:bg-white/10 text-slate-700 dark:text-slate-300' }}">
                            {{ $cat }}
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- FEATURED HERO ARTICLE (WHEN NO FILTER ACTIVE & PAGE 1) -->
        <!-- ============================================================== -->
        @if($featuredArticle && empty($category) && empty($search) && $articles->currentPage() === 1)
            <div class="rounded-3xl bg-white dark:bg-gradient-to-br dark:from-[#1a233a] dark:via-[#131b2e] dark:to-slate-950 border border-slate-200 dark:border-white/10 overflow-hidden shadow-sm dark:shadow-2xl relative group transition-colors">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-0">
                    <!-- Image -->
                    <div class="lg:col-span-7 relative min-h-[260px] sm:min-h-[360px] overflow-hidden bg-slate-100 dark:bg-slate-900">
                        <img src="{{ $featuredArticle->cover_image_url }}" 
                             alt="{{ $featuredArticle->title }}" 
                             onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1540747913346-19e32dc3e97e?auto=format&fit=crop&w=1200&q=80';"
                             class="w-full h-full object-cover group-hover:scale-105 transition duration-500" />
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-950/80 via-slate-950/20 to-transparent lg:hidden"></div>
                    </div>

                    <!-- Text / Content -->
                    <div class="lg:col-span-5 p-6 sm:p-8 flex flex-col justify-between space-y-4">
                        <div class="space-y-3">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider border {{ $featuredArticle->category_color }}">
                                    ⭐ FEATURED STORY
                                </span>
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-white/10 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-transparent">
                                    {{ $featuredArticle->category }}
                                </span>
                                <span class="text-[11px] text-slate-500 dark:text-slate-400">
                                    ⏱️ {{ $featuredArticle->estimated_read_time }} min read
                                </span>
                            </div>

                            <h2 class="text-xl sm:text-2xl lg:text-3xl font-black text-slate-900 dark:text-white leading-tight group-hover:text-amber-500 dark:group-hover:text-amber-400 transition">
                                <a href="{{ route('articles.show', $featuredArticle->slug) }}">
                                    {{ $featuredArticle->title }}
                                </a>
                            </h2>

                            <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-300 leading-relaxed line-clamp-3">
                                {{ $featuredArticle->excerpt }}
                            </p>
                        </div>

                        <div class="pt-4 border-t border-slate-100 dark:border-white/10 flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-full bg-amber-500 text-slate-950 font-black text-xs flex items-center justify-center shadow-md">
                                    {{ strtoupper(substr($featuredArticle->author_name ?: 'A', 0, 1)) }}
                                </div>
                                <div>
                                    <div class="text-xs font-bold text-slate-900 dark:text-white">{{ $featuredArticle->author_name ?: 'LiveCricket Staff' }}</div>
                                    <div class="text-[10px] text-slate-500 dark:text-slate-400">{{ $featuredArticle->formatted_date }}</div>
                                </div>
                            </div>

                            <a href="{{ route('articles.show', $featuredArticle->slug) }}" 
                               class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-black text-xs transition shadow-md">
                                <span>Read Story</span>
                                <span>➔</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- ============================================================== -->
        <!-- ARTICLES GRID -->
        <!-- ============================================================== -->
        <div class="space-y-5">
            <div class="flex items-center justify-between border-b border-slate-200 dark:border-white/10 pb-3">
                <h3 class="font-black text-lg text-slate-900 dark:text-white flex items-center gap-2">
                    <span>🏏 Latest Articles & Stories</span>
                </h3>
            </div>

            @if($articles->count() > 0)
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($articles as $article)
                        <article class="rounded-2xl bg-white dark:bg-[#131b2e] border border-slate-200 dark:border-white/10 overflow-hidden shadow-sm hover:shadow-md dark:shadow-xl flex flex-col justify-between hover:border-amber-500/50 transition group">
                            <div>
                                <!-- Image Banner -->
                                <a href="{{ route('articles.show', $article->slug) }}" class="block relative h-48 overflow-hidden bg-slate-100 dark:bg-slate-900">
                                    <img src="{{ $article->cover_image_url }}" 
                                         alt="{{ $article->title }}" 
                                         onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1540747913346-19e32dc3e97e?auto=format&fit=crop&w=1200&q=80';"
                                         class="w-full h-full object-cover group-hover:scale-105 transition duration-500" />
                                    <div class="absolute top-3 left-3">
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider backdrop-blur-md border shadow-md {{ $article->category_color }}">
                                            {{ $article->category }}
                                        </span>
                                    </div>
                                    <div class="absolute bottom-2 right-3 px-2 py-0.5 rounded-lg bg-black/60 backdrop-blur-sm text-[10px] text-white font-mono">
                                        ⏱️ {{ $article->estimated_read_time }} min read
                                    </div>
                                </a>

                                <!-- Body -->
                                <div class="p-5 space-y-2.5">
                                    <div class="text-[11px] text-slate-500 dark:text-slate-400 flex items-center gap-2">
                                        <span>📅 {{ $article->formatted_date }}</span>
                                        <span>•</span>
                                        <span>👁️ {{ $article->views_count }} views</span>
                                    </div>

                                    <h4 class="font-black text-base sm:text-lg text-slate-900 dark:text-white group-hover:text-amber-500 dark:group-hover:text-amber-400 transition line-clamp-2 leading-snug">
                                        <a href="{{ route('articles.show', $article->slug) }}">
                                            {{ $article->title }}
                                        </a>
                                    </h4>

                                    <p class="text-xs text-slate-600 dark:text-slate-400 line-clamp-2 leading-relaxed">
                                        {{ $article->excerpt }}
                                    </p>
                                </div>
                            </div>

                            <!-- Footer -->
                            <div class="p-4 bg-slate-50 dark:bg-slate-900/60 border-t border-slate-100 dark:border-white/5 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <div class="w-6 h-6 rounded-full bg-slate-200 dark:bg-slate-800 border border-slate-300 dark:border-white/10 text-slate-700 dark:text-white font-bold text-[10px] flex items-center justify-center">
                                        {{ strtoupper(substr($article->author_name ?: 'A', 0, 1)) }}
                                    </div>
                                    <span class="text-xs text-slate-700 dark:text-slate-300 font-medium truncate max-w-[130px]">
                                        {{ $article->author_name ?: 'Author' }}
                                    </span>
                                </div>

                                <a href="{{ route('articles.show', $article->slug) }}" 
                                   class="text-xs font-bold text-amber-500 dark:text-amber-400 hover:text-amber-600 dark:hover:text-amber-300 hover:underline flex items-center gap-1">
                                    <span>Read</span>
                                    <span>➔</span>
                                </a>
                            </div>
                        </article>
                    @endforeach
                </div>

                <!-- Pagination -->
                <div class="pt-4">
                    {{ $articles->links() }}
                </div>
            @else
                <div class="py-16 text-center rounded-3xl bg-white dark:bg-[#131b2e] border border-slate-200 dark:border-white/10 space-y-4 shadow-sm dark:shadow-xl">
                    <div class="text-5xl">🏏</div>
                    <h3 class="font-black text-lg text-slate-900 dark:text-white">No articles found in this category</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 max-w-sm mx-auto">
                        Try clearing your search query or switching categories to browse more cricket stories.
                    </p>
                    <a href="{{ route('articles.index') }}" 
                       class="inline-block px-5 py-2.5 rounded-xl bg-amber-500 text-slate-950 font-black text-xs hover:bg-amber-400 transition">
                        View All Stories
                    </a>
                </div>
            @endif
        </div>

    </div>
</div>
@endsection
