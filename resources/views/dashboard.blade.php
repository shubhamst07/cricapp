<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-amber-500 via-rose-500 to-purple-600 p-0.5 shadow-md flex items-center justify-center">
                    <div class="w-full h-full bg-slate-900 rounded-[14px] flex items-center justify-center text-white">
                        <svg class="w-5 h-5 text-rose-400" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M2 10.5a1.5 1.5 0 113 0v6a1.5 1.5 0 01-3 0v-6zM6 10.333v5.43a2 2 0 001.106 1.79l.05.025A4 4 0 008.943 18h5.416a2 2 0 001.962-1.608l1.2-6A2 2 0 0015.56 8H12V4a2 2 0 00-2-2 1 1 0 00-1 1v.667a4 4 0 01-.8 2.4L6.8 7.933a2 2 0 00-.8 2.4z"/>
                        </svg>
                    </div>
                </div>
                <div>
                    <h2 class="text-lg sm:text-xl font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
                        <span>Cricket Buzz & Social Feed</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-rose-500/20 text-rose-600 dark:text-rose-400 border border-rose-500/30 uppercase tracking-wider">LIVE FEED</span>
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Scroll trending match moments, memes, reels & fan banter</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('live-scores.index') }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold text-white bg-slate-800 hover:bg-slate-700 border border-white/10 transition shadow-sm">
                    <svg class="w-3.5 h-3.5 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    <span>Match Center</span>
                </a>
            </div>
        </div>
    </x-slot>

    <div class="insta-feed-page" x-data="{
        activeSection: 'feed', // 'feed' or 'arena'
        feedFilter: 'all', // 'all', 'meme', 'instagram', 'text'
        selectedMatch: 'all',
        searchQuery: '',
        showCreateModal: false,
        newMatchId: '{{ $cricbuzzMatches->first()['id'] ?? '147942' }}',
        newPostContent: '',
        newPostName: localStorage.getItem('cricket_fan_name') || '{{ Auth::user()->name ?? '' }}',
        newPostInsta: '',
        newPostMedia: '',
        selectedFile: null,
        previewUrl: null,
        isPosting: false,
        toastMsg: null,
        showToast(msg) {
            this.toastMsg = msg;
            setTimeout(() => { this.toastMsg = null; }, 3000);
        },
        likedPosts: JSON.parse(localStorage.getItem('cricket_liked_posts') || '[]'),
        savedPosts: JSON.parse(localStorage.getItem('cricket_saved_posts') || '[]'),
        openComments: {},
        newCommentText: {},
        submittingComment: {},
        toggleComments(postId) {
            this.openComments[postId] = !this.openComments[postId];
        },
        toggleSave(postId) {
            if (this.savedPosts.includes(postId)) {
                this.savedPosts = this.savedPosts.filter(id => id !== postId);
                this.showToast('Post removed from saved bookmarks');
            } else {
                this.savedPosts.push(postId);
                this.showToast('Post saved to bookmarks! 🔖');
            }
            localStorage.setItem('cricket_saved_posts', JSON.stringify(this.savedPosts));
        },
        async likePost(id) {
            if (this.likedPosts.includes(id)) return;
            this.likedPosts.push(id);
            localStorage.setItem('cricket_liked_posts', JSON.stringify(this.likedPosts));
            try {
                const res = await fetch('/api/posts/' + id + '/like', { method: 'POST', headers: { 'Accept': 'application/json' } });
                if (res.ok) {
                    const data = await res.json();
                    const el = document.getElementById('like-count-' + id);
                    if (el) el.innerText = data.likes_count;
                }
            } catch(e) {}
            this.showToast('Liked post! ❤️');
        },
        sharePost(id, matchName) {
            const url = window.location.origin + '/live-scores/' + id + '?tab=buzz';
            if (navigator.share) {
                navigator.share({
                    title: 'Cricket Buzz - ' + matchName,
                    text: 'Check out this cricket post on LiveCricket!',
                    url: url
                }).catch(() => {});
            } else {
                navigator.clipboard.writeText(url);
                this.showToast('Post link copied to clipboard! 📋');
            }
        },
        async submitComment(postId) {
            const text = (this.newCommentText[postId] || '').trim();
            if (!text) return;

            this.submittingComment[postId] = true;
            try {
                const author = (this.newPostName || '').trim() || 'Cricket Fan';
                localStorage.setItem('cricket_fan_name', author);

                const res = await fetch('/api/posts/' + postId + '/comments', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        comment: text,
                        user_name: author
                    })
                });

                if (res.ok) {
                    const resJson = await res.json();
                    if (resJson.status === 'success' && resJson.data) {
                        const listEl = document.getElementById('comments-list-' + postId);
                        if (listEl) {
                            const newCommentHtml = `
                                <div class='flex items-start gap-2.5 p-2.5 rounded-xl bg-white/5 border border-white/5 text-xs animate-in fade-in'>
                                    <div class='w-7 h-7 rounded-lg bg-rose-600 text-white font-black text-[10px] flex items-center justify-center shrink-0'>
                                        ${(resJson.data.user_name || 'C').charAt(0).toUpperCase()}
                                    </div>
                                    <div class='min-w-0 flex-1'>
                                        <div class='flex items-center justify-between'>
                                            <span class='font-bold text-white text-[11px]'>${resJson.data.user_name}</span>
                                            <span class='text-[10px] text-slate-400'>Just now</span>
                                        </div>
                                        <p class='text-slate-200 mt-0.5 text-xs leading-relaxed'>${resJson.data.comment}</p>
                                    </div>
                                </div>
                            `;
                            listEl.insertAdjacentHTML('afterbegin', newCommentHtml);
                        }

                        const badgeEl = document.getElementById('comment-count-' + postId);
                        if (badgeEl) {
                            badgeEl.innerText = resJson.comments_count;
                        }

                        this.newCommentText[postId] = '';
                        this.openComments[postId] = true;
                        this.showToast('Comment posted! 💬');
                    }
                }
            } catch (err) {
                console.error(err);
            } finally {
                this.submittingComment[postId] = false;
            }
        },
        handleFileChange(e) {
            const file = e.target.files[0];
            if (!file) return;
            this.selectedFile = file;
            this.previewUrl = URL.createObjectURL(file);
            this.newPostMedia = '';
        },
        removeFile() {
            if (this.previewUrl) URL.revokeObjectURL(this.previewUrl);
            this.selectedFile = null;
            this.previewUrl = null;
            if (this.$refs.fileInput) this.$refs.fileInput.value = '';
        },
        async submitNewPost() {
            if (!this.newPostContent.trim()) return;
            this.isPosting = true;
            try {
                const author = this.newPostName.trim() || 'Cricket Fan';
                localStorage.setItem('cricket_fan_name', author);

                const formData = new FormData();
                formData.append('content', this.newPostContent);
                formData.append('user_name', author);
                if (this.newPostInsta.trim()) formData.append('insta_url', this.newPostInsta.trim());
                if (this.newPostMedia.trim()) formData.append('media_url', this.newPostMedia.trim());
                if (this.selectedFile) {
                    formData.append('image', this.selectedFile);
                    formData.append('post_type', 'meme');
                }

                const res = await fetch('/api/matches/' + this.newMatchId + '/posts', {
                    method: 'POST',
                    headers: { 
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    credentials: 'same-origin',
                    body: formData
                });

                if (res.ok) {
                    this.showToast('Your post is live in Cricket Feed! 🔥');
                    setTimeout(() => { window.location.reload(); }, 600);
                }
            } catch(e) {
                console.error(e);
            } finally {
                this.isPosting = false;
            }
        }
    }">
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap');

            .insta-feed-page {
                font-family: 'Outfit', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
                min-height: 100vh;
                background: #090d16;
                color: #f8fafc;
                padding-bottom: 5rem;
            }

            .story-ring {
                background: linear-gradient(45deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%);
            }

            .insta-card {
                background: #111827;
                border: 1px solid rgba(255, 255, 255, 0.08);
                border-radius: 20px;
                overflow: hidden;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
                transition: transform 0.2s ease, border-color 0.2s ease;
            }

            .insta-card:hover {
                border-color: rgba(255, 255, 255, 0.15);
            }

            .heart-pop {
                animation: pop 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            }
            @keyframes pop {
                0% { transform: scale(1); }
                50% { transform: scale(1.3); }
                100% { transform: scale(1); }
            }

            /* Custom subtle scrollbar */
            .stories-scroll::-webkit-scrollbar {
                display: none;
            }
            .stories-scroll {
                -ms-overflow-style: none;
                scrollbar-width: none;
            }
        </style>

        <!-- Floating Toast Notification -->
        <div
            x-show="toastMsg"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-4"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 translate-y-4"
            class="fixed bottom-6 right-6 z-50 px-4 py-2.5 bg-slate-900 border border-indigo-500/50 text-white text-xs font-bold rounded-2xl shadow-2xl flex items-center gap-2 backdrop-blur-md"
            style="display: none;"
        >
            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
            <span x-text="toastMsg"></span>
        </div>

        <div class="max-w-4xl mx-auto px-3 sm:px-6 pt-5 space-y-6">
            <!-- ==================== TOP SECTION: CRICKET STORIES / MATCH CAROUSEL ==================== -->
            <div class="bg-slate-900/80 border border-white/10 rounded-2xl p-4 backdrop-blur-md shadow-xl">
                <div class="flex items-center justify-between mb-3 px-1">
                    <span class="text-xs font-black uppercase text-slate-400 tracking-wider flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-rose-500 animate-ping"></span>
                        Trending Match Stories
                    </span>
                    <span class="text-[11px] text-slate-500 font-bold">Tap to filter match feed</span>
                </div>

                <div class="flex items-center gap-4 overflow-x-auto stories-scroll pb-1">
                    <!-- 'All Matches' Story -->
                    <button
                        type="button"
                        @click="selectedMatch = 'all'"
                        class="flex flex-col items-center gap-1.5 shrink-0 group cursor-pointer focus:outline-none"
                    >
                        <div class="w-16 h-16 rounded-full p-0.5 transition group-hover:scale-105"
                             :class="selectedMatch === 'all' ? 'story-ring' : 'bg-slate-700'">
                            <div class="w-full h-full rounded-full bg-slate-900 flex items-center justify-center text-white text-xs font-black border-2 border-slate-900">
                                ALL 🏏
                            </div>
                        </div>
                        <span class="text-[11px] font-bold truncate max-w-[70px] text-center"
                              :class="selectedMatch === 'all' ? 'text-white' : 'text-slate-400'">
                            All Feed
                        </span>
                    </button>

                    <!-- Stories for Distinct Cricbuzz Matches -->
                    @php
                        $storyMatches = $cricbuzzMatches->take(12);
                    @endphp
                    @foreach($storyMatches as $sm)
                        @php
                            $teams = $sm['teams'] ?? [$sm['team1'] ?? 'Team A', $sm['team2'] ?? 'Team B'];
                            $abbr = count($teams) >= 2 ? strtoupper(substr($teams[0], 0, 3)) . 'v' . strtoupper(substr($teams[1], 0, 3)) : 'CRIC';
                            $isLive = strtolower($sm['status'] ?? '') === 'live';
                            $sName = $sm['name'] ?? $sm['title'] ?? 'Cricket Match';
                        @endphp
                        <button
                            type="button"
                            @click="selectedMatch = '{{ $sm['id'] }}'"
                            class="flex flex-col items-center gap-1.5 shrink-0 group cursor-pointer focus:outline-none"
                            title="{{ $sName }}"
                        >
                            <div class="w-16 h-16 rounded-full p-0.5 transition group-hover:scale-105 relative"
                                 :class="selectedMatch === '{{ $sm['id'] }}' ? 'story-ring' : 'bg-gradient-to-tr from-amber-500 via-rose-500 to-purple-600'">
                                <div class="w-full h-full rounded-full bg-slate-900 flex items-center justify-center text-white text-[11px] font-black border-2 border-slate-900">
                                    {{ $abbr }}
                                </div>
                                @if($isLive)
                                    <span class="absolute -bottom-1 left-1/2 -translate-x-1/2 px-1.5 py-0.2 bg-rose-600 text-[9px] font-black rounded-md text-white border border-slate-900 shadow-sm">
                                        LIVE
                                    </span>
                                @endif
                            </div>
                            <span class="text-[11px] font-bold truncate max-w-[76px] text-center"
                                  :class="selectedMatch === '{{ $sm['id'] }}' ? 'text-rose-400' : 'text-slate-400'">
                                {{ Str::limit($sName, 11, '') }}
                            </span>
                        </button>
                    @endforeach
                </div>
            </div>

            <!-- ==================== INSTAGRAM-STYLE FEED CONTAINER (CENTERED) ==================== -->
            <div class="max-w-2xl mx-auto space-y-6">
                <!-- Feed Filter & Quick Creator Bar -->
                <div class="bg-slate-900/80 border border-white/10 rounded-2xl p-3.5 backdrop-blur-md shadow-xl flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="flex items-center gap-1.5 overflow-x-auto pb-1 sm:pb-0 scrollbar-none">
                        <button
                            type="button"
                            @click="feedFilter = 'all'"
                            :class="feedFilter === 'all' ? 'bg-gradient-to-r from-rose-600 to-pink-600 text-white font-black shadow-md' : 'text-slate-400 hover:text-white hover:bg-white/5'"
                            class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer shrink-0"
                        >
                            Explore All
                        </button>
                        <button
                            type="button"
                            @click="feedFilter = 'meme'"
                            :class="feedFilter === 'meme' ? 'bg-gradient-to-r from-rose-600 to-pink-600 text-white font-black shadow-md' : 'text-slate-400 hover:text-white hover:bg-white/5'"
                            class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer shrink-0"
                        >
                            Memes & Pics 😂
                        </button>
                        <button
                            type="button"
                            @click="feedFilter = 'instagram'"
                            :class="feedFilter === 'instagram' ? 'bg-gradient-to-r from-rose-600 to-pink-600 text-white font-black shadow-md' : 'text-slate-400 hover:text-white hover:bg-white/5'"
                            class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer shrink-0"
                        >
                            Reels 🎬
                        </button>
                        <button
                            type="button"
                            @click="feedFilter = 'text'"
                            :class="feedFilter === 'text' ? 'bg-gradient-to-r from-rose-600 to-pink-600 text-white font-black shadow-md' : 'text-slate-400 hover:text-white hover:bg-white/5'"
                            class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer shrink-0"
                        >
                            Fan Takes 💬
                        </button>
                    </div>

                    <button
                        type="button"
                        @click="showCreateModal = true"
                        class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white rounded-xl text-xs font-black shadow-md transition cursor-pointer"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                        <span>Share Meme / Post</span>
                    </button>
                </div>

                <!-- Active Match Filter Reset Banner -->
                <div x-show="selectedMatch !== 'all'" class="p-3 bg-indigo-950/60 border border-indigo-500/30 rounded-2xl flex items-center justify-between text-xs text-indigo-200 animate-in fade-in">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-indigo-400"></span>
                        <span>Filtering feed for selected match story</span>
                    </div>
                    <button type="button" @click="selectedMatch = 'all'" class="font-bold text-white hover:underline cursor-pointer">
                        ✕ Clear Filter
                    </button>
                </div>

                <!-- ==================== THE MAIN SOCIAL POSTS STREAM ==================== -->
                @forelse($allPosts as $post)
                    @php
                        $avatarLetters = strtoupper(substr($post->user_name, 0, 1));
                        $isMeme = $post->post_type === 'meme' || !empty($post->media_url);
                        $isReel = $post->post_type === 'instagram' || !empty($post->insta_url);
                        $postSearchText = strtolower($post->content . ' ' . $post->user_name . ' ' . $post->match_name . ' ' . $post->series_name);
                    @endphp

                    <article
                        x-show="(feedFilter === 'all' || '{{ $post->post_type }}' === feedFilter || (feedFilter === 'meme' && '{{ $isMeme ? '1' : '0' }}' === '1') || (feedFilter === 'instagram' && '{{ $isReel ? '1' : '0' }}' === '1')) && (selectedMatch === 'all' || selectedMatch === '{{ $post->match_id }}')"
                        class="insta-card group"
                    >
                        <!-- 1. Post Header: User, Verified Badge, Match Tag, Options -->
                        <div class="p-4 flex items-center justify-between border-b border-white/5">
                            <a href="{{ $post->user_id ? route('profile.user', $post->user_id) : route('profile.show') }}" class="flex items-center gap-3 hover:opacity-85 transition group/user">
                                <!-- Profile Story Ring -->
                                <div class="w-10 h-10 rounded-full p-0.5 story-ring shrink-0">
                                    <div class="w-full h-full rounded-full bg-slate-900 flex items-center justify-center text-white font-black text-xs border border-slate-900">
                                        {{ $avatarLetters }}
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5">
                                        <h3 class="font-black text-white text-xs sm:text-sm truncate leading-none group-hover/user:text-blue-400 transition">
                                            {{ $post->user_name }}
                                        </h3>
                                        <span class="w-3.5 h-3.5 rounded-full bg-blue-500 text-white text-[9px] flex items-center justify-center font-bold shrink-0">✓</span>
                                    </div>
                                    <div class="flex items-center gap-1.5 text-[11px] text-slate-400 mt-1">
                                        <span class="font-bold text-indigo-400 truncate max-w-[200px]" title="{{ $post->match_name }}">
                                            🏏 {{ $post->match_name }}
                                        </span>
                                        <span>•</span>
                                        <span>{{ $post->created_at->diffForHumans() }}</span>
                                    </div>
                                </div>
                            </a>

                            <!-- Match Center Link / 3 dots -->
                            <a
                                href="{{ route('live-scores.show', $post->match_id) }}"
                                class="p-2 rounded-xl text-slate-400 hover:text-white hover:bg-white/5 transition text-xs font-bold"
                                title="Open Live Match Center"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path></svg>
                            </a>
                        </div>

                        <!-- 2. Post Caption / Text -->
                        @if(!empty($post->content))
                            <div class="px-4 py-3 text-xs sm:text-sm text-slate-200 leading-relaxed font-normal whitespace-pre-line">
                                {{ $post->content }}
                            </div>
                        @endif

                        <!-- 3. Post Media: Big Photo / Meme OR Instagram Reel Video Player -->
                        @if($post->media_url && empty($post->insta_url))
                            <!-- Standard Meme / Photo -->
                            <div
                                class="bg-black/80 relative overflow-hidden flex items-center justify-center"
                                @dblclick="likePost({{ $post->id }})"
                            >
                                <img
                                    src="{{ $post->media_url }}"
                                    alt="Cricket Meme"
                                    class="w-full max-h-[500px] object-cover"
                                    loading="lazy"
                                    onerror="this.style.display='none'"
                                />
                                @if($post->post_type === 'meme')
                                    <div class="absolute top-3 left-3 px-2.5 py-1 bg-black/75 backdrop-blur-md rounded-lg text-[10px] font-bold text-amber-300 border border-amber-300/30">
                                        😂 Match Meme
                                    </div>
                                @endif
                            </div>
                        @elseif(!empty($post->insta_url))
                            <!-- Instagram Reel Video Card -->
                            <div class="p-4 bg-gradient-to-br from-pink-950/20 via-purple-950/20 to-slate-900 border-y border-white/5">
                                <a
                                    href="{{ $post->insta_url }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="block rounded-2xl overflow-hidden aspect-[9/10] sm:aspect-video bg-black/90 relative group/reel shadow-xl"
                                >
                                    <img
                                        src="{{ $post->media_url ?: 'https://images.unsplash.com/photo-1540747913346-19e32dc3e97e?w=800&auto=format&fit=crop&q=80' }}"
                                        alt="Instagram Reel Cover"
                                        class="w-full h-full object-cover opacity-85 group-hover/reel:scale-105 transition duration-300"
                                    />
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/30 to-transparent flex flex-col justify-between p-4">
                                        <div class="flex items-center justify-between">
                                            <span class="px-2.5 py-1 bg-black/70 backdrop-blur-md rounded-xl text-xs font-bold text-white flex items-center gap-1.5 border border-white/20">
                                                <span class="w-2 h-2 rounded-full bg-rose-500 animate-ping"></span>
                                                Instagram Reel 🎬
                                            </span>
                                            <span class="px-2.5 py-1 bg-white/20 backdrop-blur-md rounded-xl text-xs font-bold text-white group-hover/reel:bg-rose-600 transition">
                                                Watch on IG ↗
                                            </span>
                                        </div>

                                        <div class="self-center">
                                            <div class="w-14 h-14 rounded-full bg-white/95 text-rose-600 shadow-2xl flex items-center justify-center group-hover/reel:scale-110 transition duration-200">
                                                <svg class="w-6 h-6 fill-rose-600 ml-0.5" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                            </div>
                                        </div>

                                        <div class="flex items-center justify-between text-xs text-white/90">
                                            <span class="font-bold flex items-center gap-1">
                                                <span>♫</span> Original Audio • Trending
                                            </span>
                                            <span class="text-pink-300 font-bold group-hover/reel:underline">
                                                Tap to Open Reel ↗
                                            </span>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        @endif

                        <!-- 4. Instagram Action Bar: Heart, Comment, Share, Bookmark -->
                        <div class="p-4 space-y-3">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-4">
                                    <!-- LIKE BUTTON (Heart) -->
                                    <button
                                        type="button"
                                        @click="likePost({{ $post->id }})"
                                        class="flex items-center gap-1.5 text-slate-300 hover:text-rose-500 transition cursor-pointer group/like focus:outline-none"
                                        :class="likedPosts.includes({{ $post->id }}) ? 'text-rose-500 font-bold' : ''"
                                    >
                                        <svg
                                            class="w-6 h-6 transition-transform group-hover/like:scale-125"
                                            :class="likedPosts.includes({{ $post->id }}) ? 'fill-rose-500 text-rose-500 heart-pop' : 'fill-none stroke-current'"
                                            stroke-width="2"
                                            viewBox="0 0 24 24"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                                        </svg>
                                        <span id="like-count-{{ $post->id }}" class="text-xs font-black">{{ $post->likes_count }}</span>
                                    </button>

                                    <!-- COMMENT BUTTON (Speech Bubble) -->
                                    <button
                                        type="button"
                                        @click="toggleComments({{ $post->id }})"
                                        class="flex items-center gap-1.5 text-slate-300 hover:text-indigo-400 transition cursor-pointer group/cmt focus:outline-none"
                                        :class="openComments[{{ $post->id }}] ? 'text-indigo-400 font-bold' : ''"
                                    >
                                        <svg class="w-6 h-6 transition-transform group-hover/cmt:scale-125 fill-none stroke-current" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                        </svg>
                                        <span id="comment-count-{{ $post->id }}" class="text-xs font-black">{{ $post->comments_count ?? $post->comments->count() }}</span>
                                    </button>

                                    <!-- SHARE BUTTON (Paper Plane) -->
                                    <button
                                        type="button"
                                        @click="sharePost({{ $post->id }}, '{{ addslashes($post->match_name) }}')"
                                        class="text-slate-300 hover:text-emerald-400 transition cursor-pointer group/share focus:outline-none"
                                        title="Share this post"
                                    >
                                        <svg class="w-6 h-6 transition-transform group-hover/share:scale-125 fill-none stroke-current" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                        </svg>
                                    </button>
                                </div>

                                <!-- BOOKMARK BUTTON -->
                                <button
                                    type="button"
                                    @click="toggleSave({{ $post->id }})"
                                    class="text-slate-400 hover:text-amber-400 transition cursor-pointer focus:outline-none"
                                    :class="savedPosts.includes({{ $post->id }}) ? 'text-amber-400' : ''"
                                >
                                    <svg class="w-6 h-6" :class="savedPosts.includes({{ $post->id }}) ? 'fill-amber-400' : 'fill-none stroke-current'" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"></path>
                                    </svg>
                                </button>
                            </div>

                            <!-- Liked By Summary -->
                            <div class="text-[11px] font-bold text-slate-400">
                                Liked by <span class="text-white font-black" id="like-summary-{{ $post->id }}">{{ $post->likes_count }}</span> cricket fans
                            </div>

                            <!-- ==================== 5. COMMENTS SECTION (INSTAGRAM STYLE) ==================== -->
                            <!-- Toggle Comments trigger text -->
                            <div class="pt-1">
                                <button
                                    type="button"
                                    @click="toggleComments({{ $post->id }})"
                                    class="text-xs font-bold text-slate-400 hover:text-indigo-400 transition cursor-pointer"
                                >
                                    <span x-show="!openComments[{{ $post->id }}]">
                                        View all {{ $post->comments_count ?? $post->comments->count() }} comments...
                                    </span>
                                    <span x-show="openComments[{{ $post->id }}]">
                                        Hide comments ▲
                                    </span>
                                </button>
                            </div>

                            <!-- Comments Drawer & Add Comment Form -->
                            <div
                                x-show="openComments[{{ $post->id }}]"
                                x-transition:enter="transition ease-out duration-150"
                                x-transition:enter-start="opacity-0 -translate-y-2"
                                x-transition:enter-end="opacity-100 translate-y-0"
                                class="space-y-3 pt-2"
                            >
                                <!-- Existing Comments List -->
                                <div id="comments-list-{{ $post->id }}" class="space-y-2 max-h-56 overflow-y-auto pr-1">
                                    @forelse($post->comments as $comment)
                                        <div class="flex items-start gap-2.5 p-2 rounded-xl bg-white/5 border border-white/5 text-xs">
                                            <div class="w-7 h-7 rounded-lg bg-indigo-600 text-white font-black text-[10px] flex items-center justify-center shrink-0">
                                                {{ strtoupper(substr($comment->user_name, 0, 1)) }}
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-center justify-between">
                                                    <span class="font-bold text-white text-[11px]">{{ $comment->user_name }}</span>
                                                    <span class="text-[10px] text-slate-400">{{ $comment->created_at->diffForHumans() }}</span>
                                                </div>
                                                <p class="text-slate-200 mt-0.5 text-xs leading-relaxed">{{ $comment->comment }}</p>
                                            </div>
                                        </div>
                                    @empty
                                        <p class="text-center text-xs text-slate-500 py-2">No comments yet. Be the first to share your take!</p>
                                    @endforelse
                                </div>

                                <!-- Add Comment Input Box (Instagram style) -->
                                <form @submit.prevent="submitComment({{ $post->id }})" class="flex items-center gap-2 pt-2 border-t border-white/10">
                                    <div class="w-7 h-7 rounded-full bg-slate-800 text-white font-bold text-[10px] flex items-center justify-center shrink-0 border border-white/10">
                                        {{ strtoupper(substr(Auth::user()->name ?? 'C', 0, 1)) }}
                                    </div>
                                    <input
                                        type="text"
                                        placeholder="Add a comment on this match..."
                                        x-model="newCommentText[{{ $post->id }}]"
                                        class="flex-1 bg-slate-800/80 border border-white/10 rounded-xl px-3 py-1.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                        required
                                    />
                                    <button
                                        type="submit"
                                        :disabled="submittingComment[{{ $post->id }}] || !(newCommentText[{{ $post->id }}] || '').trim()"
                                        class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-black shadow-xs transition disabled:opacity-40 cursor-pointer"
                                    >
                                        <span x-text="submittingComment[{{ $post->id }}] ? '...' : 'Post'"></span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="bg-slate-900/80 border border-white/10 rounded-2xl p-12 text-center text-slate-400 space-y-3">
                        <div class="w-16 h-16 rounded-full bg-white/5 flex items-center justify-center text-3xl mx-auto">
                            🏏
                        </div>
                        <h4 class="text-base font-bold text-white">No cricket posts in this category yet</h4>
                        <p class="text-xs text-slate-400 max-w-sm mx-auto">Tap the button below to be the first to drop a match meme or thought!</p>
                        <button
                            type="button"
                            @click="showCreateModal = true"
                            class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition cursor-pointer"
                        >
                            Post Meme / Update
                        </button>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- ==================== MODAL: CREATE POST / MEME ==================== -->
        <div
            x-show="showCreateModal"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-50 overflow-y-auto bg-black/80 backdrop-blur-sm flex items-center justify-center p-4"
            style="display: none;"
        >
            <div
                @click.away="showCreateModal = false"
                class="bg-slate-900 border border-white/10 rounded-3xl max-w-lg w-full p-6 text-white space-y-4 shadow-2xl relative"
            >
                <div class="flex items-center justify-between pb-3 border-b border-white/10">
                    <div class="flex items-center gap-2">
                        <span class="w-8 h-8 rounded-xl bg-gradient-to-tr from-amber-500 via-rose-500 to-purple-600 flex items-center justify-center text-white text-sm">
                            📸
                        </span>
                        <div>
                            <h3 class="font-black text-base text-white">Create Cricket Feed Post</h3>
                            <p class="text-[11px] text-slate-400">Share your match take, meme, or reel</p>
                        </div>
                    </div>
                    <button type="button" @click="showCreateModal = false" class="text-slate-400 hover:text-white p-1 text-sm font-bold">
                        ✕
                    </button>
                </div>

                <form @submit.prevent="submitNewPost" class="space-y-4">
                    <!-- Select Match -->
                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1">Select Cricket Match</label>
                        <select
                            x-model="newMatchId"
                            class="w-full bg-slate-800 border border-white/10 rounded-xl px-3.5 py-2.5 text-xs text-white focus:outline-none focus:ring-2 focus:ring-rose-500 cursor-pointer"
                            required
                        >
                            @foreach($cricbuzzMatches as $cm)
                                <option value="{{ $cm['id'] }}">
                                    {{ $cm['name'] }} ({{ $cm['series'] ?? 'Cricbuzz' }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Author Name -->
                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1">Your Name / Handle</label>
                        <input
                            type="text"
                            placeholder="e.g. Cricket Fanatic / @viratfan"
                            x-model="newPostName"
                            class="w-full bg-slate-800 border border-white/10 rounded-xl px-3.5 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-rose-500"
                        />
                    </div>

                    <!-- Caption -->
                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1">Post Caption & Thoughts</label>
                        <textarea
                            rows="3"
                            placeholder="What's happening in this match? Drop a meme review or reaction..."
                            x-model="newPostContent"
                            class="w-full bg-slate-800 border border-white/10 rounded-xl px-3.5 py-2.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-rose-500 resize-none"
                            required
                        ></textarea>
                    </div>

                    <!-- Photo Upload / Meme Selection -->
                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1">Photo / Meme (Device File or URL)</label>
                        <input
                            type="file"
                            accept="image/*"
                            x-ref="fileInput"
                            @change="handleFileChange"
                            class="hidden"
                            id="feed-photo-upload"
                        />

                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                @click="$refs.fileInput.click()"
                                class="inline-flex items-center gap-1.5 px-3 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition cursor-pointer shrink-0 shadow-sm"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                <span>Select Photo</span>
                            </button>
                            <span class="text-xs text-slate-400">or</span>
                            <input
                                type="url"
                                placeholder="paste image URL..."
                                x-model="newPostMedia"
                                :disabled="!!selectedFile"
                                class="flex-1 bg-slate-800 border border-white/10 rounded-xl px-3 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-rose-500 disabled:opacity-50"
                            />
                        </div>

                        <!-- Preview Box -->
                        <template x-if="previewUrl">
                            <div class="mt-2.5 p-2 bg-slate-800/80 border border-indigo-500/40 rounded-xl flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <img :src="previewUrl" class="w-12 h-12 rounded-lg object-cover border border-white/10" />
                                    <div class="text-[11px]">
                                        <p class="font-bold text-white truncate max-w-[200px]" x-text="selectedFile?.name"></p>
                                        <p class="text-slate-400" x-text="(selectedFile?.size / 1024).toFixed(1) + ' KB'"></p>
                                    </div>
                                </div>
                                <button type="button" @click="removeFile" class="text-rose-400 hover:text-rose-300 p-1 text-sm font-bold">✕</button>
                            </div>
                        </template>
                    </div>

                    <!-- Instagram Link (Optional) -->
                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1">Instagram Reel Link (Optional)</label>
                        <input
                            type="url"
                            placeholder="https://instagram.com/reel/..."
                            x-model="newPostInsta"
                            class="w-full bg-slate-800 border border-white/10 rounded-xl px-3.5 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-pink-500"
                        />
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-3 border-t border-white/10 flex justify-end gap-2">
                        <button
                            type="button"
                            @click="showCreateModal = false"
                            class="px-4 py-2 rounded-xl text-xs font-bold text-slate-400 hover:text-white transition"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="isPosting || !newPostContent.trim()"
                            class="px-5 py-2.5 bg-gradient-to-r from-rose-600 to-pink-600 hover:from-rose-700 hover:to-pink-700 text-white rounded-xl text-xs font-bold shadow-lg shadow-rose-500/20 transition disabled:opacity-50 cursor-pointer"
                        >
                            <span x-text="isPosting ? 'Publishing...' : 'Share Post'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
