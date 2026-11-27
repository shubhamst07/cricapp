@extends('layouts.app')

@section('title', 'Edit Article: ' . $article->title . ' | CricApp Admin')

@section('header')
    <div class="flex items-center gap-3">
        <a href="{{ route('articles.show', $article->slug) }}"
           class="p-2 rounded-xl bg-slate-100 dark:bg-white/10 text-slate-700 dark:text-slate-300 hover:text-white hover:bg-slate-200 dark:hover:bg-white/20 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
            </svg>
        </a>
        <div>
            <h2 class="font-black text-xl text-slate-900 dark:text-white">
                ✏️ Edit Article
            </h2>
            <p class="text-xs text-slate-500">Update article details, cover image, or formatting.</p>
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

        <form action="{{ route('articles.update', $article) }}" method="POST" enctype="multipart/form-data" 
              class="p-6 sm:p-8 rounded-3xl bg-white dark:bg-[#131b2e] border border-slate-200 dark:border-white/10 shadow-sm dark:shadow-2xl space-y-5 transition-colors">
            @csrf
            @method('PUT')

            <!-- Article Title -->
            <div>
                <label for="title" class="block text-xs font-black uppercase tracking-wider text-slate-300 mb-1.5">
                    Article Title <span class="text-rose-400">*</span>
                </label>
                <input type="text" id="title" name="title" value="{{ old('title', $article->title) }}" required
                       class="w-full px-4 py-3 rounded-xl bg-slate-900 border border-white/10 text-white text-sm focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition" />
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
                            <option value="{{ $cat }}" {{ old('category', $article->category) === $cat ? 'selected' : '' }}>
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
                        <option value="published" {{ old('status', $article->status) === 'published' ? 'selected' : '' }}>🟢 Published</option>
                        <option value="draft" {{ old('status', $article->status) === 'draft' ? 'selected' : '' }}>🟡 Draft</option>
                    </select>
                </div>
            </div>

            <!-- Cover Image (Upload or URL) -->
            <div class="p-4 rounded-2xl bg-slate-900/60 border border-white/5 space-y-3">
                <label class="block text-xs font-black uppercase tracking-wider text-slate-300">
                    Cover / Featured Image
                </label>
                
                @if($article->featured_image)
                    <div class="flex items-center gap-3 p-2 bg-slate-900 rounded-xl border border-white/5">
                        <img src="{{ $article->cover_image_url }}" alt="Preview" 
                             onerror="this.src='https://images.unsplash.com/photo-1540747913346-19e32dc3e97e?auto=format&fit=crop&w=1200&q=80';"
                             class="w-16 h-12 rounded-lg object-cover" />
                        <span class="text-xs text-slate-400 truncate">Current Image Set</span>
                    </div>
                @endif

                <div>
                    <span class="text-[11px] text-slate-400 block mb-1">Option A: Update Direct Image URL</span>
                    <input type="url" name="featured_image_url" id="featured_image_url" value="{{ old('featured_image_url', Str::startsWith($article->featured_image, ['http://', 'https://']) ? $article->featured_image : '') }}"
                           placeholder="https://..."
                           oninput="previewImageUrl(this.value)"
                           class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-white/10 text-white placeholder-slate-500 text-xs focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition" />
                </div>

                <div>
                    <span class="text-[11px] text-slate-400 block mb-1">Option B: Upload New Image File</span>
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
                            <span>✅ New Image Selected & Ready</span>
                        </div>
                        <div id="image_preview_name" class="text-[11px] text-slate-400 truncate max-w-xs"></div>
                    </div>
                </div>
            </div>

            <!-- Short Excerpt / Summary -->
            <div>
                <label for="excerpt" class="block text-xs font-black uppercase tracking-wider text-slate-300 mb-1.5">
                    Short Excerpt / Preview Summary
                </label>
                <textarea id="excerpt" name="excerpt" rows="2"
                          class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-white/10 text-white text-sm focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition">{{ old('excerpt', $article->excerpt) }}</textarea>
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
                <textarea id="content" name="content" rows="14" required
                          class="w-full px-4 py-3 rounded-xl bg-slate-900 border border-white/10 text-white text-sm leading-relaxed focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition font-mono">{{ old('content', $article->content) }}</textarea>
            </div>

            <!-- Author & Tags -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="author_name" class="block text-xs font-black uppercase tracking-wider text-slate-300 mb-1.5">
                        Author Name
                    </label>
                    <input type="text" id="author_name" name="author_name" value="{{ old('author_name', $article->author_name) }}"
                           class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-white/10 text-white text-sm focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition" />
                </div>

                <div>
                    <label for="tags" class="block text-xs font-black uppercase tracking-wider text-slate-300 mb-1.5">
                        Tags (comma separated)
                    </label>
                    <input type="text" id="tags" name="tags" value="{{ old('tags', $article->tags) }}"
                           class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-white/10 text-white text-sm focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition" />
                </div>
            </div>

            <!-- Featured Checkbox -->
            <div class="pt-2">
                <label class="flex items-center gap-3 p-3 rounded-xl bg-slate-900 border border-white/5 cursor-pointer hover:border-amber-500/30 transition">
                    <input type="checkbox" name="is_featured" value="1" {{ old('is_featured', $article->is_featured) ? 'checked' : '' }}
                           class="w-4 h-4 rounded text-amber-500 focus:ring-amber-400 bg-slate-800 border-white/20" />
                    <span class="text-xs font-bold text-slate-200">
                        ⭐ Pin this as a Featured Story
                    </span>
                </label>
            </div>

            <!-- Submit Button -->
            <div class="pt-4 border-t border-white/10 flex items-center justify-end gap-3">
                <a href="{{ route('articles.show', $article->slug) }}" 
                   class="px-5 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 text-slate-300 font-bold text-xs transition">
                    Cancel
                </a>
                <button type="submit" 
                        class="px-6 py-3 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-400 hover:to-orange-400 text-slate-950 font-black text-xs uppercase tracking-wider shadow-lg shadow-amber-500/25 transition transform active:scale-95">
                    Save Changes ✅
                </button>
            </div>
        </form>

    </div>
</div>

<script>
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
