@extends('layouts.app')

@section('title', 'Write New Cricket Article | CricApp Admin')

@section('header')
    <div class="flex items-center gap-3">
        <a href="{{ route('articles.index') }}"
           class="p-2 rounded-xl bg-slate-100 dark:bg-white/10 text-slate-700 dark:text-slate-300 hover:text-white hover:bg-slate-200 dark:hover:bg-white/20 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
            </svg>
        </a>
        <div>
            <h2 class="font-black text-xl text-slate-900 dark:text-white">
                ✍️ Write New Cricket Article
            </h2>
            <p class="text-xs text-slate-500">Publish news, coaching guides, match reviews or tournament updates.</p>
        </div>
    </div>
@endsection

@section('content')
<div class="py-6 sm:py-8 bg-slate-50 dark:bg-[#080d1a] min-h-screen text-slate-900 dark:text-slate-100 font-sans transition-colors duration-200">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 space-y-6">

        @if($errors->any())
            <div class="p-4 rounded-2xl bg-rose-500/15 border border-rose-500/30 text-rose-600 dark:text-rose-300 text-xs sm:text-sm font-bold space-y-1">
                <div class="font-black">⚠️ Please fix the following errors:</div>
                <ul class="list-disc pl-5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- 🤖 AI Humanize Cricket Article Bot Widget -->
        <div class="p-6 sm:p-7 rounded-3xl bg-white dark:bg-gradient-to-br dark:from-indigo-950/70 dark:via-slate-900/90 dark:to-amber-950/30 border border-amber-500/30 shadow-sm dark:shadow-2xl relative overflow-hidden backdrop-blur-md space-y-5 transition-colors">
            <!-- Decorative Glow Accents -->
            <div class="absolute -top-12 -right-12 w-48 h-48 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute -bottom-12 -left-12 w-48 h-48 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>

            <div class="relative flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-2xl bg-gradient-to-tr from-amber-400 to-orange-500 flex items-center justify-center text-slate-950 text-xl font-black shadow-lg shadow-amber-500/25">
                        🤖
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="font-black text-base sm:text-lg text-slate-900 dark:text-white tracking-tight">
                                AI Humanize Cricket Article Bot
                            </h3>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-500/15 border border-emerald-500/30 text-emerald-400 flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span> Ready
                            </span>
                        </div>
                        <p class="text-xs text-slate-400 mt-0.5">
                            Apna topic/idea likho ya neeche kisi chip par tap karo — ye bot 100% human tone, headings, quotes aur tags ke sath article draft karke form auto-fill kar dega.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Topic Prompt Input -->
            <div class="space-y-2">
                <label for="ai_topic_input" class="block text-xs font-black uppercase tracking-wider text-amber-300">
                    Enter Topic / Idea (English or Hinglish)
                </label>
                <div class="relative">
                    <input type="text" id="ai_topic_input" 
                           placeholder="e.g. Why Rohit Sharma's Powerplay Aggression Changed Indian Cricket Forever..." 
                           class="w-full pl-4 pr-10 py-3 rounded-2xl bg-slate-900/90 border border-amber-500/30 text-white placeholder-slate-500 text-sm focus:border-amber-400 focus:ring-2 focus:ring-amber-400/30 transition shadow-inner" />
                    <button type="button" onclick="document.getElementById('ai_topic_input').value=''" 
                            title="Clear input"
                            class="absolute right-3 top-3 text-slate-400 hover:text-white transition text-xs font-bold">
                        ✕
                    </button>
                </div>

                <!-- Trending / Suggested Chips -->
                <div class="flex flex-wrap items-center gap-1.5 pt-1">
                    <span class="text-[11px] font-bold text-slate-400 mr-1">Trending Topics:</span>
                    <button type="button" onclick="setAiPrompt(this.innerText)" 
                            class="px-2.5 py-1 rounded-lg bg-white/5 hover:bg-amber-500/20 border border-white/10 hover:border-amber-400/40 text-[11px] text-slate-300 hover:text-amber-200 transition">
                        👑 Virat Kohli Run Chase Psychology
                    </button>
                    <button type="button" onclick="setAiPrompt(this.innerText)" 
                            class="px-2.5 py-1 rounded-lg bg-white/5 hover:bg-amber-500/20 border border-white/10 hover:border-amber-400/40 text-[11px] text-slate-300 hover:text-amber-200 transition">
                        🚀 Rohit Sharma Powerplay Intent
                    </button>
                    <button type="button" onclick="setAiPrompt(this.innerText)" 
                            class="px-2.5 py-1 rounded-lg bg-white/5 hover:bg-amber-500/20 border border-white/10 hover:border-amber-400/40 text-[11px] text-slate-300 hover:text-amber-200 transition">
                        🎯 Jasprit Bumrah Yorker Science
                    </button>
                    <button type="button" onclick="setAiPrompt(this.innerText)" 
                            class="px-2.5 py-1 rounded-lg bg-white/5 hover:bg-amber-500/20 border border-white/10 hover:border-amber-400/40 text-[11px] text-slate-300 hover:text-amber-200 transition">
                        🏏 Gully & Himachal Grassroots Cricket
                    </button>
                    <button type="button" onclick="setAiPrompt(this.innerText)" 
                            class="px-2.5 py-1 rounded-lg bg-white/5 hover:bg-amber-500/20 border border-white/10 hover:border-amber-400/40 text-[11px] text-slate-300 hover:text-amber-200 transition">
                        🏆 IPL 2026 Mega Auction Gamechangers
                    </button>
                    <button type="button" onclick="setAiPrompt(this.innerText)" 
                            class="px-2.5 py-1 rounded-lg bg-white/5 hover:bg-amber-500/20 border border-white/10 hover:border-amber-400/40 text-[11px] text-slate-300 hover:text-amber-200 transition">
                        📖 Master the Cover Drive: 3 Rules
                    </button>
                </div>
            </div>

            <!-- Controls: Tone, Category, and Generate Trigger -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-1">
                <div>
                    <label for="ai_tone" class="block text-[11px] font-black uppercase tracking-wider text-slate-300 mb-1">
                        Narrative Tone
                    </label>
                    <select id="ai_tone" 
                            class="w-full px-3 py-2.5 rounded-xl bg-slate-900/90 border border-white/10 text-white text-xs focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition">
                        <option value="narrative" selected>📖 Storytelling & Passionate Narrative</option>
                        <option value="tactical">🎯 Tactical & Technical Breakdown</option>
                        <option value="editorial">🔥 Expert Opinion & Editorial</option>
                    </select>
                </div>

                <div>
                    <label for="ai_category_pref" class="block text-[11px] font-black uppercase tracking-wider text-slate-300 mb-1">
                        Category Mode
                    </label>
                    <select id="ai_category_pref" 
                            class="w-full px-3 py-2.5 rounded-xl bg-slate-900/90 border border-white/10 text-white text-xs focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition">
                        <option value="auto" selected>⚡ Auto-Detect from Topic</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat }}">{{ $cat }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-end">
                    <button type="button" id="btn_generate_article" onclick="generateHumanArticle()" 
                            class="w-full px-5 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 via-amber-400 to-orange-500 hover:from-amber-400 hover:to-orange-400 text-slate-950 font-black text-xs uppercase tracking-wider shadow-lg shadow-amber-500/25 transition transform active:scale-95 flex items-center justify-center gap-2">
                        <span id="btn_generate_icon">✨</span>
                        <span id="btn_generate_text">Generate Human Article</span>
                    </button>
                </div>
            </div>

            <!-- Live Status & Feedback -->
            <div id="ai_status_box" class="hidden p-3.5 rounded-2xl text-xs font-bold transition"></div>
        </div>

        <form id="article_create_form" action="{{ route('articles.store') }}" method="POST" enctype="multipart/form-data" 
              class="p-6 sm:p-8 rounded-3xl bg-white dark:bg-[#131b2e] border border-slate-200 dark:border-white/10 shadow-sm dark:shadow-2xl space-y-5 transition-colors">
            @csrf

            <!-- Article Title -->
            <div>
                <label for="title" class="block text-xs font-black uppercase tracking-wider text-slate-300 mb-1.5">
                    Article Title <span class="text-rose-400">*</span>
                </label>
                <input type="text" id="title" name="title" value="{{ old('title') }}" required
                       placeholder="e.g. Master the Off-Drive: 3 Golden Rules by Top Coaches"
                       class="w-full px-4 py-3 rounded-xl bg-slate-900 border border-white/10 text-white placeholder-slate-500 text-sm focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition" />
            </div>

            <!-- Category & Status -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="category" class="block text-xs font-black uppercase tracking-wider text-slate-300 mb-1.5">
                        Category <span class="text-rose-400">*</span>
                    </label>
                    <select id="category" name="category" required
                            class="w-full px-4 py-3 rounded-xl bg-slate-900 border border-white/10 text-white text-sm focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition">
                        @foreach($categories as $cat)
                            <option value="{{ $cat }}" {{ old('category') === $cat ? 'selected' : '' }}>
                                {{ $cat }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="status" class="block text-xs font-black uppercase tracking-wider text-slate-300 mb-1.5">
                        Publication Status <span class="text-rose-400">*</span>
                    </label>
                    <select id="status" name="status" required
                            class="w-full px-4 py-3 rounded-xl bg-slate-900 border border-white/10 text-white text-sm focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition">
                        <option value="published" {{ old('status', 'published') === 'published' ? 'selected' : '' }}>🟢 Publish Immediately</option>
                        <option value="draft" {{ old('status') === 'draft' ? 'selected' : '' }}>🟡 Save as Draft</option>
                    </select>
                </div>
            </div>

            <!-- Cover Image (Upload or URL) -->
            <div class="p-4 rounded-2xl bg-slate-900/60 border border-white/5 space-y-3">
                <label class="block text-xs font-black uppercase tracking-wider text-slate-300">
                    Cover / Featured Image
                </label>
                
                <div>
                    <span class="text-[11px] text-slate-400 block mb-1">Option A: Paste Direct Image URL (Web link)</span>
                    <input type="url" name="featured_image_url" id="featured_image_url" value="{{ old('featured_image_url') }}"
                           placeholder="https://images.unsplash.com/... or https://..."
                           oninput="previewImageUrl(this.value)"
                           class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-white/10 text-white placeholder-slate-500 text-xs focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition" />
                </div>

                <div>
                    <span class="text-[11px] text-slate-400 block mb-1">Option B: Upload Image from Device</span>
                    <input type="file" name="featured_image_file" id="featured_image_file" accept="image/*"
                           onchange="previewImageFile(event)"
                           class="w-full text-xs text-slate-400 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-amber-500 file:text-slate-950 hover:file:bg-amber-400 cursor-pointer" />
                </div>

                <!-- Instant Live Preview Box -->
                <div id="image_preview_box" class="hidden items-center gap-3 p-3 bg-slate-900/90 rounded-xl border border-amber-500/30">
                    <img id="image_live_preview" src="" alt="Selected Preview" 
                         onerror="this.src='https://images.unsplash.com/photo-1540747913346-19e32dc3e97e?auto=format&fit=crop&w=1200&q=80';"
                         class="w-24 h-16 rounded-lg object-cover border border-white/10" />
                    <div>
                        <div class="text-xs font-bold text-amber-400 flex items-center gap-1.5">
                            <span>✅ Image Selected & Ready to Upload</span>
                        </div>
                        <div id="image_preview_name" class="text-[11px] text-slate-400 truncate max-w-xs"></div>
                    </div>
                </div>

                <p class="text-[11px] text-slate-500">Leave blank to use a sleek cricket stock image automatically.</p>
            </div>

            <!-- Short Excerpt / Summary -->
            <div>
                <label for="excerpt" class="block text-xs font-black uppercase tracking-wider text-slate-300 mb-1.5">
                    Short Excerpt / Preview Summary
                </label>
                <textarea id="excerpt" name="excerpt" rows="2"
                          placeholder="A quick 1-2 sentence preview for cards and social sharing..."
                          class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-white/10 text-white placeholder-slate-500 text-sm focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition">{{ old('excerpt') }}</textarea>
            </div>

            <!-- Full Article Content -->
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label for="content" class="block text-xs font-black uppercase tracking-wider text-slate-300">
                        Article Content <span class="text-rose-400">*</span>
                    </label>
                    <span class="text-[11px] text-slate-500 font-mono">Use ### headings, **bold**, - bullets, [text](url)</span>
                </div>
                @include('articles.partials.internal-link-helper', ['linkableArticles' => $linkableArticles ?? collect()])
                <textarea id="content" name="content" rows="12" required
                          placeholder="Write your article story here... You can use headings like:&#10;&#10;### 1. Warm-up and Footwork&#10;Body paragraph text here...&#10;&#10;### 2. Follow-through&#10;More text here..."
                          class="w-full px-4 py-3 rounded-xl bg-slate-900 border border-white/10 text-white placeholder-slate-500 text-sm leading-relaxed focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition font-mono">{{ old('content') }}</textarea>
            </div>

            <!-- Author & Tags -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="author_name" class="block text-xs font-black uppercase tracking-wider text-slate-300 mb-1.5">
                        Author Name
                    </label>
                    <input type="text" id="author_name" name="author_name" value="{{ old('author_name', Auth::user()->name) }}"
                           placeholder="e.g. Shubham Thakur / Staff"
                           class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-white/10 text-white text-sm focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition" />
                </div>

                <div>
                    <label for="tags" class="block text-xs font-black uppercase tracking-wider text-slate-300 mb-1.5">
                        Tags (comma separated)
                    </label>
                    <input type="text" id="tags" name="tags" value="{{ old('tags') }}"
                           placeholder="e.g. T20, Coaching, Batting, Sunday Cricket"
                           class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-white/10 text-white text-sm focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition" />
                </div>
            </div>

            <!-- Featured Checkbox -->
            <div class="pt-2">
                <label class="flex items-center gap-3 p-3 rounded-xl bg-slate-900 border border-white/5 cursor-pointer hover:border-amber-500/30 transition">
                    <input type="checkbox" name="is_featured" value="1" {{ old('is_featured') ? 'checked' : '' }}
                           class="w-4 h-4 rounded text-amber-500 focus:ring-amber-400 bg-slate-800 border-white/20" />
                    <span class="text-xs font-bold text-slate-200">
                        ⭐ Pin this as a Featured Story (Displays prominently on top of magazine)
                    </span>
                </label>
            </div>

            <!-- Submit Button -->
            <div class="pt-4 border-t border-white/10 flex items-center justify-end gap-3">
                <a href="{{ route('articles.index') }}" 
                   class="px-5 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 text-slate-300 font-bold text-xs transition">
                    Cancel
                </a>
                <button type="submit" 
                        class="px-6 py-3 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-400 hover:to-orange-400 text-slate-950 font-black text-xs uppercase tracking-wider shadow-lg shadow-amber-500/25 transition transform active:scale-95">
                    🚀 Publish Story Now
                </button>
            </div>
        </form>

    </div>
</div>

<script>
    function setAiPrompt(topic) {
        // Strip out leading emoji if present
        const clean = topic.replace(/^[\p{Emoji}\s]+/u, '').trim();
        const input = document.getElementById('ai_topic_input');
        input.value = clean;
        input.focus();
    }

    async function generateHumanArticle() {
        const input = document.getElementById('ai_topic_input');
        const toneSelect = document.getElementById('ai_tone');
        const catSelect = document.getElementById('ai_category_pref');
        const btn = document.getElementById('btn_generate_article');
        const btnText = document.getElementById('btn_generate_text');
        const btnIcon = document.getElementById('btn_generate_icon');
        const statusBox = document.getElementById('ai_status_box');

        const promptVal = input.value.trim();
        if (!promptVal) {
            input.focus();
            input.classList.add('ring-2', 'ring-rose-500');
            setTimeout(() => input.classList.remove('ring-2', 'ring-rose-500'), 1500);
            return;
        }

        const toneVal = toneSelect ? toneSelect.value : 'narrative';
        const catVal = catSelect ? catSelect.value : 'auto';

        // Set Loading state
        btn.disabled = true;
        btn.classList.add('opacity-75', 'cursor-not-allowed');
        btnIcon.innerHTML = `<svg class="animate-spin h-3.5 w-3.5 text-slate-950" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>`;
        btnText.innerText = 'Crafting Human Story...';

        statusBox.className = 'p-3.5 rounded-2xl text-xs font-bold bg-amber-500/15 border border-amber-500/30 text-amber-300 flex items-center gap-2 transition';
        statusBox.innerHTML = `
            <span class="animate-spin">⏳</span>
            <span>AI Bot is analyzing tactical angles, player quotes, and human narrative structure... Please wait a moment.</span>
        `;
        statusBox.classList.remove('hidden');

        try {
            const response = await fetch('{{ route("articles.generate") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    prompt: promptVal,
                    tone: toneVal,
                    category: catVal !== 'auto' ? catVal : null
                })
            });

            const result = await response.json();

            if (response.ok && result.success && result.data) {
                const art = result.data;

                // Populate Form Fields
                const titleField = document.getElementById('title');
                if (titleField && art.title) titleField.value = art.title;

                const categoryField = document.getElementById('category');
                if (categoryField && art.category) {
                    for (let opt of categoryField.options) {
                        if (opt.value.toLowerCase() === art.category.toLowerCase()) {
                            opt.selected = true;
                            break;
                        }
                    }
                }

                const imgUrlField = document.querySelector('input[name="featured_image_url"]');
                if (imgUrlField && art.featured_image_url) imgUrlField.value = art.featured_image_url;

                const excerptField = document.getElementById('excerpt');
                if (excerptField && art.excerpt) excerptField.value = art.excerpt;

                const contentField = document.getElementById('content');
                if (contentField && art.content) contentField.value = art.content;

                const tagsField = document.getElementById('tags');
                if (tagsField && art.tags) tagsField.value = art.tags;

                // Flash success box
                statusBox.className = 'p-4 rounded-2xl text-xs font-bold bg-emerald-500/15 border border-emerald-500/40 text-emerald-300 space-y-1 transition';
                statusBox.innerHTML = `
                    <div class="flex items-center gap-2 text-sm font-black text-emerald-200">
                        <span>✨</span> <span>Humanized Article Successfully Generated!</span>
                    </div>
                    <p class="text-xs text-slate-300">
                        Title, Cover Image, Excerpt, Content (with tactical headings ###) aur Tags neeche form me populate ho chuke hain. Aap publish karne se pehle review ya edit kar sakte hain.
                    </p>
                `;

                // Scroll to form smoothly
                const formElem = document.getElementById('article_create_form');
                if (formElem) {
                    formElem.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            } else {
                throw new Error(result.message || 'Server error while generating article');
            }
        } catch (err) {
            statusBox.className = 'p-3.5 rounded-2xl text-xs font-bold bg-rose-500/15 border border-rose-500/30 text-rose-300 flex items-center gap-2 transition';
            statusBox.innerHTML = `
                <span>⚠️</span>
                <span>Error generating article: ${err.message || 'Something went wrong'}. Please retry.</span>
            `;
        } finally {
            btn.disabled = false;
            btn.classList.remove('opacity-75', 'cursor-not-allowed');
            btnIcon.innerHTML = '✨';
            btnText.innerText = 'Generate Human Article';
        }
    }

    function previewImageUrl(url) {
        const box = document.getElementById('image_preview_box');
        const img = document.getElementById('image_live_preview');
        const name = document.getElementById('image_preview_name');
        if (url && (url.startsWith('http://') || url.startsWith('https://'))) {
            img.src = url;
            name.innerText = 'Direct Web Link';
            box.classList.remove('hidden');
            box.classList.add('flex');
        } else {
            const fileInput = document.getElementById('featured_image_file');
            if (!fileInput || !fileInput.files.length) {
                box.classList.add('hidden');
                box.classList.remove('flex');
            }
        }
    }

    function previewImageFile(event) {
        const file = event.target.files[0];
        const box = document.getElementById('image_preview_box');
        const img = document.getElementById('image_live_preview');
        const name = document.getElementById('image_preview_name');
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                img.src = e.target.result;
                name.innerText = file.name + ' (' + Math.round(file.size / 1024) + ' KB)';
                box.classList.remove('hidden');
                box.classList.add('flex');
            };
            reader.readAsDataURL(file);
        }
    }
</script>
@endsection
