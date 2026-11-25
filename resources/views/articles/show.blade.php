@extends('layouts.app')

@section('title', $article->title . ' | CricApp News')

@section('header')
    <div class="flex items-center gap-3">
        <a href="{{ route('articles.index') }}"
           class="p-2 rounded-xl bg-slate-100 dark:bg-white/10 text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-200 dark:hover:bg-white/20 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
            </svg>
        </a>
        <div class="min-w-0">
            <div class="flex items-center gap-2 text-xs font-bold text-slate-500 dark:text-slate-400">
                <a href="{{ route('articles.index') }}" class="hover:text-amber-500 dark:hover:text-amber-400 transition">Articles</a>
                <span>›</span>
                <span class="text-amber-500 dark:text-amber-400 truncate">{{ $article->category }}</span>
            </div>
            <h2 class="font-black text-lg sm:text-xl text-slate-900 dark:text-white truncate mt-0.5">
                {{ $article->title }}
            </h2>
        </div>
    </div>
@endsection

@section('content')
@php
    $translations = $translations ?? [
        'en' => [
            'title' => $article->title,
            'category' => $article->category,
            'excerpt' => $article->excerpt,
            'content' => $article->content,
        ],
        'hi' => [
            'title' => $article->title_hi ?: $article->title,
            'category' => $article->category_hi ?: $article->category,
            'excerpt' => $article->excerpt_hi ?: $article->excerpt,
            'content' => $article->content_hi ?: $article->content,
        ],
        'hinglish' => [
            'title' => $article->title_hinglish ?: $article->title,
            'category' => $article->category_hinglish ?: $article->category,
            'excerpt' => $article->excerpt_hinglish ?: $article->excerpt,
            'content' => $article->content_hinglish ?: $article->content,
        ],
    ];

    $renderMarkdown = function($contentStr) {
        $lines = explode("\n", (string) $contentStr);
        $html = '';
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (str_starts_with($trimmed, '### ')) {
                $html .= '<h3 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white pt-5 pb-1.5 border-b border-slate-200 dark:border-white/10 flex items-center gap-2"><span>🏏</span><span>' . e(substr($trimmed, 4)) . '</span></h3>';
            } elseif (str_starts_with($trimmed, '## ')) {
                $html .= '<h2 class="text-2xl sm:text-3xl font-black text-amber-500 dark:text-amber-400 pt-6 pb-2">' . e(substr($trimmed, 3)) . '</h2>';
            } elseif (str_starts_with($trimmed, '# ')) {
                $html .= '<h1 class="text-3xl sm:text-4xl font-black text-slate-900 dark:text-white pt-7 pb-3">' . e(substr($trimmed, 2)) . '</h1>';
            } elseif (str_starts_with($trimmed, '- ')) {
                $html .= '<li class="ml-5 list-disc text-slate-700 dark:text-slate-300 leading-relaxed">' . e(substr($trimmed, 2)) . '</li>';
            } elseif (!empty($trimmed)) {
                $html .= '<p class="text-slate-700 dark:text-slate-300 leading-relaxed text-sm sm:text-base">' . nl2br(e($trimmed)) . '</p>';
            }
        }
        return $html;
    };
@endphp

<div class="py-6 sm:py-8 bg-slate-50 dark:bg-[#080d1a] min-h-screen text-slate-900 dark:text-slate-100 font-sans transition-colors duration-200" 
     x-data="{ 
         copied: false, 
         translating: false,
         lang: localStorage.getItem('cricapp_article_lang') || 'en',
         translations: {{ Js::from($translations) }},
         hasDevanagari(str) {
             return /[\u0900-\u097F]/.test(str || '');
         },
         async setLang(l) {
             this.lang = l;
             localStorage.setItem('cricapp_article_lang', l);

             if (l === 'hi') {
                 if (!this.hasDevanagari(this.translations.hi?.title) || !this.hasDevanagari(this.translations.hi?.content)) {
                     await this.translateToHindiClient();
                 }
             } else if (l === 'hinglish') {
                 if (!this.translations.hinglish?.title || this.translations.hinglish.title === this.translations.en?.title) {
                     if (!this.hasDevanagari(this.translations.hi?.title)) {
                         await this.translateToHindiClient();
                     }
                     this.generateHinglishClient();
                 }
             }
         },
         async callTranslateApi(text) {
             if (!text || !text.trim()) return '';
             text = text.trim();
             try {
                 const url = 'https://translate.googleapis.com/translate_a/single?client=gtx&sl=en&tl=hi&dt=t&q=' + encodeURIComponent(text);
                 const res = await fetch(url);
                 if (res.ok) {
                     const data = await res.json();
                     if (data && data[0]) {
                         return data[0].map(item => item[0]).join('');
                     }
                 }
             } catch (e) {
                 try {
                     const mmUrl = 'https://api.mymemory.translated.net/get?q=' + encodeURIComponent(text) + '&langpair=en|hi';
                     const mmRes = await fetch(mmUrl);
                     if (mmRes.ok) {
                         const mmJson = await mmRes.json();
                         if (mmJson.responseData?.translatedText) {
                             return mmJson.responseData.translatedText;
                         }
                     }
                 } catch (err) {}
             }
             return text;
         },
         async translateToHindiClient() {
             this.translating = true;
             try {
                 // 1. Translate Title if English
                 if (!this.hasDevanagari(this.translations.hi?.title)) {
                     const hiTitle = await this.callTranslateApi(this.translations.en?.title);
                     if (hiTitle && this.hasDevanagari(hiTitle)) {
                         this.translations.hi.title = hiTitle;
                     }
                 }

                 // 2. Translate Excerpt if English
                 if (!this.hasDevanagari(this.translations.hi?.excerpt)) {
                     const hiExcerpt = await this.callTranslateApi(this.translations.en?.excerpt);
                     if (hiExcerpt && this.hasDevanagari(hiExcerpt)) {
                         this.translations.hi.excerpt = hiExcerpt;
                     }
                 }

                 // 3. Translate Content if container has English
                 const hiDiv = document.getElementById('article-content-hi');
                 const enDiv = document.getElementById('article-content-en');
                 if (hiDiv && enDiv && (!this.translations.hi?.content || !this.hasDevanagari(hiDiv.textContent))) {
                     const nodes = Array.from(enDiv.querySelectorAll('p, h1, h2, h3, h4, li, blockquote'));
                     for (const el of nodes) {
                         const raw = el.innerText.trim();
                         if (raw && !this.hasDevanagari(raw)) {
                             const translated = await this.callTranslateApi(raw);
                             if (translated && this.hasDevanagari(translated)) {
                                 el.dataset.hi = translated;
                             }
                         }
                     }
                     // Clone translated structure
                     const clone = enDiv.cloneNode(true);
                     clone.id = 'article-content-hi-inner';
                     clone.querySelectorAll('[data-hi]').forEach(el => {
                         el.innerText = el.dataset.hi;
                     });
                     hiDiv.innerHTML = clone.innerHTML;
                     this.translations.hi.content = clone.innerHTML;
                 }

                 // 4. Save to database in background
                 fetch('{{ route('articles.save-translations', $article) }}', {
                     method: 'POST',
                     headers: {
                         'Content-Type': 'application/json',
                         'Accept': 'application/json'
                     },
                     body: JSON.stringify({
                         title_hi: this.translations.hi?.title,
                         excerpt_hi: this.translations.hi?.excerpt,
                         content_hi: this.translations.hi?.content
                     })
                 }).catch(() => {});

             } catch (e) {
                 console.warn('Live translation error:', e);
             } finally {
                 this.translating = false;
             }
         },
         generateHinglishClient() {
             if (this.translations.hi?.title && this.hasDevanagari(this.translations.hi.title)) {
                 this.translations.hinglish.title = this.translations.hi.title;
             }
         }
     }">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

        @if(session('success'))
            <div class="p-4 rounded-2xl bg-emerald-500/15 border border-emerald-500/30 text-emerald-600 dark:text-emerald-300 text-xs sm:text-sm font-bold flex items-center gap-2.5 shadow-sm dark:shadow-lg">
                <span class="text-lg">✅</span>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        <!-- ============================================================== -->
        <!-- 3-LANGUAGE SELECTOR BAR -->
        <!-- ============================================================== -->
        <div class="p-3 sm:p-4 rounded-2xl bg-white dark:bg-[#131b2e] border border-slate-200 dark:border-white/10 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <span class="text-base">🌐</span>
                <div>
                    <div class="text-xs font-black uppercase tracking-wider text-slate-900 dark:text-white">
                        Article Language / भाषा चुनें
                    </div>
                    <div class="text-[11px] text-slate-500 dark:text-slate-400">
                        Read this cricket story in your preferred language
                    </div>
                </div>
            </div>

            <!-- Language Buttons & Translating Pulse -->
            <div class="flex flex-wrap items-center gap-2">
                <div x-show="translating" x-cloak class="flex items-center gap-1.5 text-xs font-bold text-amber-500 animate-pulse bg-amber-500/10 px-2.5 py-1 rounded-lg border border-amber-500/20">
                    <span class="animate-spin">⚙️</span>
                    <span>अनुवाद हो रहा है... (Translating)</span>
                </div>

                <div class="inline-flex items-center p-1 rounded-xl bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-white/10">
                    <button type="button" @click="setLang('en')"
                            :class="lang === 'en' ? 'bg-amber-500 text-slate-950 font-black shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white font-bold'"
                            class="px-3.5 py-1.5 rounded-lg text-xs transition flex items-center gap-1.5 cursor-pointer">
                        <span>🇬🇧 English</span>
                    </button>
                    <button type="button" @click="setLang('hi')"
                            :class="lang === 'hi' ? 'bg-amber-500 text-slate-950 font-black shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white font-bold'"
                            class="px-3.5 py-1.5 rounded-lg text-xs transition flex items-center gap-1.5 cursor-pointer">
                        <span>🇮🇳 हिंदी</span>
                    </button>
                    <button type="button" @click="setLang('hinglish')"
                            :class="lang === 'hinglish' ? 'bg-amber-500 text-slate-950 font-black shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white font-bold'"
                            class="px-3.5 py-1.5 rounded-lg text-xs transition flex items-center gap-1.5 cursor-pointer">
                        <span>🏏 Hinglish</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- ARTICLE HEADER CARD -->
        <!-- ============================================================== -->
        <article class="p-5 sm:p-8 rounded-3xl bg-white dark:bg-[#131b2e] border border-slate-200 dark:border-white/10 shadow-sm dark:shadow-2xl space-y-6 transition-colors">
            <!-- Badges Row & Admin Actions -->
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 dark:border-white/10 pb-4">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider border {{ $article->category_color }}"
                          x-text="translations[lang]?.category || '{{ $article->category }}'">
                        {{ $article->category }}
                    </span>
                    @if($article->is_featured)
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-500/15 text-amber-600 dark:text-amber-300 border border-amber-500/30">
                            ⭐ Featured
                        </span>
                    @endif
                    <span class="text-xs text-slate-500 dark:text-slate-400">
                        ⏱️ {{ $article->estimated_read_time }} min read
                    </span>
                    <span class="text-slate-300 dark:text-slate-600">•</span>
                    <span class="text-xs text-slate-500 dark:text-slate-400">
                        👁️ {{ $article->views_count }} views
                    </span>
                </div>

                <!-- Admin Action Controls -->
                @if($isAdmin)
                    <div class="flex items-center gap-2">
                        <a href="{{ route('articles.edit', $article) }}" 
                           class="px-3 py-1.5 rounded-xl bg-blue-500/10 hover:bg-blue-500/20 border border-blue-500/20 text-blue-600 dark:text-blue-300 text-xs font-bold transition flex items-center gap-1.5">
                            <span>✏️ Edit</span>
                        </a>
                        <form action="{{ route('articles.destroy', $article) }}" method="POST" 
                               onsubmit="return confirm('Are you sure you want to delete this article?');" class="inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" 
                                    class="px-3 py-1.5 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/20 text-rose-600 dark:text-rose-300 text-xs font-bold transition flex items-center gap-1.5">
                                <span>🗑️ Delete</span>
                            </button>
                        </form>
                    </div>
                @endif
            </div>

            <!-- Title & Excerpt (Reactive with selected language) -->
            <div class="space-y-3">
                <h1 class="text-2xl sm:text-4xl font-black text-slate-900 dark:text-white leading-tight"
                    x-text="translations[lang]?.title || '{{ addslashes($article->title) }}'">
                    {{ $article->title }}
                </h1>
                
                <p class="text-sm sm:text-base text-slate-600 dark:text-slate-300 leading-relaxed italic border-l-4 border-amber-500 pl-4 py-1"
                   x-text="translations[lang]?.excerpt || '{{ addslashes($article->excerpt) }}'">
                    {{ $article->excerpt }}
                </p>
            </div>

            <!-- Author & Date Ribbon -->
            <div class="flex items-center justify-between border-y border-slate-200 dark:border-white/10 py-3.5 flex-wrap gap-3">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-amber-500 to-orange-500 text-slate-950 font-black text-sm flex items-center justify-center shrink-0 shadow-md">
                        {{ strtoupper(substr($article->author_name ?: 'A', 0, 1)) }}
                    </div>
                    <div>
                        <div class="font-bold text-sm text-slate-900 dark:text-white flex items-center gap-1.5">
                            <span>{{ $article->author_name ?: 'LiveCricket Staff' }}</span>
                            <span class="w-3.5 h-3.5 rounded-full bg-blue-500 text-white text-[9px] flex items-center justify-center font-bold">✓</span>
                        </div>
                        <div class="text-xs text-slate-500 dark:text-slate-400">
                            Published on {{ $article->formatted_date }}
                        </div>
                    </div>
                </div>

                <!-- 1-Tap Social Share Bar -->
                @php
                    $shareText = urlencode("🏏 Read: {$article->title}\n\n" . route('articles.show', $article->slug));
                @endphp
                <div class="flex items-center gap-2">
                    <a href="https://api.whatsapp.com/send?text={{ $shareText }}" target="_blank"
                       class="px-3.5 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition flex items-center gap-1.5 shadow-sm">
                        <span>💬 WhatsApp</span>
                    </a>
                    <a href="https://twitter.com/intent/tweet?text={{ $shareText }}" target="_blank"
                       class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-white text-xs font-bold transition flex items-center gap-1 shadow-sm">
                        <span>𝕏 Post</span>
                    </a>
                    <button type="button" 
                            @click="navigator.clipboard.writeText('{{ route('articles.show', $article->slug) }}'); copied = true; setTimeout(() => copied = false, 2500)"
                            class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-white/5 dark:hover:bg-white/10 text-slate-700 dark:text-slate-300 text-xs font-bold transition flex items-center gap-1 border border-slate-200 dark:border-transparent">
                        <span x-text="copied ? '✅ Copied!' : '🔗 Copy'"></span>
                    </button>
                </div>
            </div>

            <!-- Featured Image with Fallback onerror -->
            <div class="rounded-2xl overflow-hidden border border-slate-200 dark:border-white/10 shadow-md dark:shadow-xl max-h-[460px] bg-slate-100 dark:bg-slate-900">
                <img src="{{ $article->cover_image_url }}" 
                     alt="{{ $article->title }}" 
                     onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1540747913346-19e32dc3e97e?auto=format&fit=crop&w=1200&q=80';"
                     class="w-full h-full object-cover max-h-[460px]" />
            </div>

            <!-- Article Formatted Content in 3 Languages -->
            <div class="max-w-none text-slate-800 dark:text-slate-200 leading-relaxed space-y-5 pt-2">
                <!-- English Content -->
                <div x-show="lang === 'en'" class="space-y-5" id="article-content-en">
                    {!! $renderMarkdown($translations['en']['content'] ?? $article->content) !!}
                </div>

                <!-- Hindi (हिंदी) Content -->
                <div x-show="lang === 'hi'" x-cloak class="space-y-5 font-sans" id="article-content-hi">
                    {!! $renderMarkdown($translations['hi']['content'] ?? $article->content) !!}
                </div>

                <!-- Hinglish (Cricket Roman Hindi) Content -->
                <div x-show="lang === 'hinglish'" x-cloak class="space-y-5 font-sans" id="article-content-hinglish">
                    {!! $renderMarkdown($translations['hinglish']['content'] ?? $article->content) !!}
                </div>
            </div>

            <!-- Tags Section -->
            @if($article->tags)
                <div class="pt-6 border-t border-slate-200 dark:border-white/10 flex items-center gap-2 flex-wrap text-xs">
                    <span class="text-slate-500 dark:text-slate-400 font-bold">🏷️ Tags:</span>
                    @foreach(explode(',', $article->tags) as $tag)
                        @if(trim($tag))
                            <span class="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-white/5 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-white/5 font-mono">
                                #{{ trim($tag) }}
                            </span>
                        @endif
                    @endforeach
                </div>
            @endif

            <!-- Bottom Share CTA -->
            <div class="p-5 rounded-2xl bg-gradient-to-r from-amber-500/10 via-orange-500/10 to-amber-600/10 border border-amber-500/30 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h4 class="font-black text-sm text-slate-900 dark:text-white">Loved this cricket story?</h4>
                    <p class="text-xs text-slate-600 dark:text-slate-400">Share it with your teammates and cricket circle!</p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="https://api.whatsapp.com/send?text={{ $shareText }}" target="_blank"
                       class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs transition flex items-center gap-2 shadow-sm">
                        <span>💬 Share on WhatsApp</span>
                    </a>
                </div>
            </div>
        </article>

        <!-- ============================================================== -->
        <!-- RELATED STORIES -->
        <!-- ============================================================== -->
        @if($relatedArticles->isNotEmpty())
            <div class="space-y-4 pt-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg sm:text-xl font-black text-slate-900 dark:text-white flex items-center gap-2">
                        <span>🔥</span>
                        <span>More Cricket Stories & News</span>
                    </h3>
                    <a href="{{ route('articles.index') }}" 
                       class="text-xs font-bold text-amber-500 hover:text-amber-400 flex items-center gap-1 transition">
                        <span>View All</span>
                        <span>→</span>
                    </a>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    @foreach($relatedArticles as $rel)
                        <article class="group rounded-2xl bg-white dark:bg-[#131b2e] border border-slate-200 dark:border-white/10 hover:border-amber-500/40 transition duration-300 overflow-hidden shadow-sm dark:shadow-md hover:shadow-lg flex flex-col justify-between">
                            <div>
                                <div class="relative aspect-video overflow-hidden bg-slate-950">
                                    <img src="{{ $rel->cover_image_url }}" 
                                         alt="{{ $rel->title }}" 
                                         onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1540747913346-19e32dc3e97e?auto=format&fit=crop&w=1200&q=80';"
                                         class="w-full h-full object-cover group-hover:scale-105 transition duration-500" />
                                    <div class="absolute top-2.5 left-2.5">
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-slate-950/80 backdrop-blur-md text-amber-400 border border-white/10">
                                            {{ $rel->category }}
                                        </span>
                                    </div>
                                </div>
                                <div class="p-4 space-y-2">
                                    <h4 class="text-xs sm:text-sm font-black text-slate-900 dark:text-white group-hover:text-amber-500 dark:group-hover:text-amber-400 transition line-clamp-2 leading-snug">
                                        <a href="{{ route('articles.show', $rel->slug) }}">
                                            {{ $rel->title }}
                                        </a>
                                    </h4>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400 line-clamp-2 leading-relaxed">
                                        {{ $rel->excerpt }}
                                    </p>
                                </div>
                            </div>
                            <div class="px-4 pb-4 pt-1 flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400 border-t border-slate-100 dark:border-white/5">
                                <span>⏱️ {{ $rel->estimated_read_time }} min read</span>
                                <a href="{{ route('articles.show', $rel->slug) }}" 
                                   class="text-amber-500 font-bold hover:text-amber-400 inline-flex items-center gap-1">
                                    <span>Read</span>
                                    <span>→</span>
                                </a>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        @endif

    </div>
</div>
@endsection
