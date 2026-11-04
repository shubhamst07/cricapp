<x-app-layout>
    <div class="insta-profile-root" x-data="{
        activeTab: 'posts',
        editModalOpen: false,
        cropperModalOpen: false,
        selectedPost: null,
        isFollowing: {{ $isFollowing ? 'true' : 'false' }},
        followersCount: {{ $user->followers_count ?? 0 }},
        followingCount: {{ $user->following_count ?? 0 }},
        isFollowLoading: false,
        avatarPreview: '{{ $user->avatar_url }}',
        toastMsg: '',
        showToast(msg) {
            this.toastMsg = msg;
            setTimeout(() => { this.toastMsg = ''; }, 3000);
        },

        // WhatsApp Profile Cropper State
        cropImgObj: null,
        cropScale: 1.0,
        cropRotation: 0,
        cropOffsetX: 0,
        cropOffsetY: 0,
        isDragging: false,
        dragStartX: 0,
        dragStartY: 0,
        isSavingAvatar: false,
        croppedAvatarBase64: '',

        openCropper(file) {
            if (!file) return;
            const reader = new FileReader();
            reader.onload = (e) => {
                const img = new Image();
                img.onload = () => {
                    this.cropImgObj = img;
                    this.cropScale = 1.0;
                    this.cropRotation = 0;
                    this.cropOffsetX = 0;
                    this.cropOffsetY = 0;
                    this.cropperModalOpen = true;
                    this.$nextTick(() => {
                        this.renderCropCanvas();
                    });
                };
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        },

        renderCropCanvas() {
            const canvas = this.$refs.cropCanvas;
            if (!canvas || !this.cropImgObj) return;
            const ctx = canvas.getContext('2d');
            const width = canvas.width;
            const height = canvas.height;

            ctx.clearRect(0, 0, width, height);

            ctx.save();
            ctx.translate(width / 2 + this.cropOffsetX, height / 2 + this.cropOffsetY);
            ctx.rotate((this.cropRotation * Math.PI) / 180);
            ctx.scale(this.cropScale, this.cropScale);

            const imgW = this.cropImgObj.naturalWidth || this.cropImgObj.width;
            const imgH = this.cropImgObj.naturalHeight || this.cropImgObj.height;
            const baseFit = Math.max(width / imgW, height / imgH);
            const drawW = imgW * baseFit;
            const drawH = imgH * baseFit;

            ctx.drawImage(this.cropImgObj, -drawW / 2, -drawH / 2, drawW, drawH);
            ctx.restore();

            // WhatsApp Style Mask: dark overlay outside center circle
            const radius = Math.min(width, height) * 0.42;
            const centerX = width / 2;
            const centerY = height / 2;

            ctx.save();
            ctx.fillStyle = 'rgba(10, 15, 29, 0.75)';
            ctx.beginPath();
            ctx.rect(0, 0, width, height);
            ctx.arc(centerX, centerY, radius, 0, Math.PI * 2, true);
            ctx.closePath();
            ctx.fill();

            // WhatsApp circular guideline border
            ctx.strokeStyle = '#10b981';
            ctx.lineWidth = 2.5;
            ctx.beginPath();
            ctx.arc(centerX, centerY, radius, 0, Math.PI * 2);
            ctx.stroke();

            // Subtle dashed circle guides
            ctx.strokeStyle = 'rgba(255, 255, 255, 0.2)';
            ctx.setLineDash([4, 4]);
            ctx.lineWidth = 1;
            ctx.beginPath();
            ctx.arc(centerX, centerY, radius * 0.6, 0, Math.PI * 2);
            ctx.stroke();
            ctx.restore();
        },

        onCanvasMouseDown(e) {
            this.isDragging = true;
            this.dragStartX = e.clientX - this.cropOffsetX;
            this.dragStartY = e.clientY - this.cropOffsetY;
        },
        onCanvasMouseMove(e) {
            if (!this.isDragging) return;
            this.cropOffsetX = e.clientX - this.dragStartX;
            this.cropOffsetY = e.clientY - this.dragStartY;
            this.renderCropCanvas();
        },
        onCanvasMouseUp() {
            this.isDragging = false;
        },
        onTouchStart(e) {
            if (e.touches.length === 1) {
                this.isDragging = true;
                this.dragStartX = e.touches[0].clientX - this.cropOffsetX;
                this.dragStartY = e.touches[0].clientY - this.cropOffsetY;
            }
        },
        onTouchMove(e) {
            if (!this.isDragging || e.touches.length !== 1) return;
            this.cropOffsetX = e.touches[0].clientX - this.dragStartX;
            this.cropOffsetY = e.touches[0].clientY - this.dragStartY;
            this.renderCropCanvas();
        },
        onTouchEnd() {
            this.isDragging = false;
        },
        rotateClockwise() {
            this.cropRotation = (this.cropRotation + 90) % 360;
            this.renderCropCanvas();
        },
        resetCrop() {
            this.cropScale = 1.0;
            this.cropRotation = 0;
            this.cropOffsetX = 0;
            this.cropOffsetY = 0;
            this.renderCropCanvas();
        },
        async saveCroppedAvatar() {
            if (!this.cropImgObj || this.isSavingAvatar) return;
            this.isSavingAvatar = true;

            try {
                const srcCanvas = this.$refs.cropCanvas;
                const width = srcCanvas.width;
                const height = srcCanvas.height;
                const radius = Math.min(width, height) * 0.42;

                const outCanvas = document.createElement('canvas');
                const outSize = 400;
                outCanvas.width = outSize;
                outCanvas.height = outSize;
                const outCtx = outCanvas.getContext('2d');

                outCtx.beginPath();
                outCtx.arc(outSize / 2, outSize / 2, outSize / 2, 0, Math.PI * 2);
                outCtx.clip();

                const scaleFactor = outSize / (radius * 2);
                outCtx.translate(outSize / 2 + this.cropOffsetX * scaleFactor, outSize / 2 + this.cropOffsetY * scaleFactor);
                outCtx.rotate((this.cropRotation * Math.PI) / 180);
                outCtx.scale(this.cropScale * scaleFactor, this.cropScale * scaleFactor);

                const imgW = this.cropImgObj.naturalWidth || this.cropImgObj.width;
                const imgH = this.cropImgObj.naturalHeight || this.cropImgObj.height;
                const baseFit = Math.max(width / imgW, height / imgH);
                const drawW = imgW * baseFit;
                const drawH = imgH * baseFit;

                outCtx.drawImage(this.cropImgObj, -drawW / 2, -drawH / 2, drawW, drawH);

                const dataUrl = outCanvas.toDataURL('image/jpeg', 0.88);
                this.croppedAvatarBase64 = dataUrl;
                this.avatarPreview = dataUrl;

                const res = await fetch('{{ route('profile.avatar.update') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ cropped_avatar: dataUrl })
                });

                const json = await res.json();
                if (json.status === 'success') {
                    this.showToast('Profile picture saved successfully! 📸');
                } else {
                    this.showToast(json.message || 'Avatar saved');
                }
                this.cropperModalOpen = false;
            } catch (err) {
                console.error(err);
                this.showToast('Failed to save profile picture');
            } finally {
                this.isSavingAvatar = false;
            }
        },

        async toggleFollow(targetUserId) {
            if (this.isFollowLoading) return;
            this.isFollowLoading = true;
            try {
                const res = await fetch('/users/' + targetUserId + '/follow', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });

                if (res.status === 401) {
                    window.location.href = '{{ route('login') }}';
                    return;
                }

                const data = await res.json();
                if (data.status === 'success') {
                    this.isFollowing = data.following;
                    this.followersCount = data.followers_count;
                    this.showToast(data.message);
                } else {
                    this.showToast(data.message || 'Something went wrong');
                }
            } catch (e) {
                console.error(e);
                this.showToast('Failed to update follow status');
            } finally {
                this.isFollowLoading = false;
            }
        },
        previewAvatar(e) {
            const file = e.target.files[0];
            if (file) {
                this.avatarPreview = URL.createObjectURL(file);
            }
        },
        shareProfile() {
            const url = window.location.href;
            if (navigator.share) {
                navigator.share({
                    title: '{{ $user->name }} ({{ $user->handle }}) on LiveCricket',
                    url: url
                }).catch(() => {});
            } else {
                navigator.clipboard.writeText(url);
                this.showToast('Profile link copied to clipboard! 📋');
            }
        }
    }">
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

            .insta-profile-root {
                font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                background-color: #090d16;
                color: #f1f5f9;
                min-height: calc(100vh - 65px);
            }

            .story-ring-lg {
                background: linear-gradient(45deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%);
            }

            .tab-btn.active {
                border-top: 2px solid #ef4444;
                color: #ffffff;
            }

            .post-overlay {
                background: rgba(0, 0, 0, 0.6);
                opacity: 0;
                transition: opacity 0.2s ease-in-out;
            }

            .post-grid-item:hover .post-overlay {
                opacity: 1;
            }

            [x-cloak] { display: none !important; }
        </style>

        <!-- Floating Toast -->
        <div x-cloak x-show="toastMsg" x-transition 
             class="fixed bottom-6 right-6 z-50 bg-gradient-to-r from-emerald-600 to-teal-600 text-white px-5 py-3 rounded-2xl shadow-2xl font-bold text-sm flex items-center gap-2 border border-white/20">
            <span x-text="toastMsg"></span>
        </div>

        <div class="max-w-4xl mx-auto px-4 py-8 md:py-12">
            
            <!-- Instagram Header Profile Block -->
            <div class="flex flex-col sm:flex-row items-center sm:items-start gap-6 sm:gap-12 pb-8 border-b border-white/10">
                
                <!-- Profile Avatar with Story Ring -->
                <div class="relative shrink-0">
                    <div class="w-28 h-28 sm:w-36 sm:h-36 rounded-full p-[3px] story-ring-lg shadow-xl shadow-rose-950/20">
                        <div class="w-full h-full rounded-full bg-[#090d16] p-[3px] overflow-hidden">
                            <img :src="avatarPreview" alt="{{ $user->name }}" class="w-full h-full object-cover rounded-full">
                        </div>
                    </div>
                    @if($isOwner)
                        <button @click="$refs.avatarFileInput.click()" 
                                class="absolute bottom-0 right-0 bg-emerald-600 hover:bg-emerald-500 text-white p-2.5 rounded-full shadow-lg border-2 border-[#090d16] transition transform hover:scale-110 cursor-pointer"
                                title="Change Profile Picture (WhatsApp Style Crop)">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </button>
                        <input type="file" x-ref="avatarFileInput" accept="image/jpeg,image/png,image/jpg,image/webp" class="hidden"
                               @change="if($event.target.files[0]) { openCropper($event.target.files[0]); } $event.target.value = '';">
                    @endif
                </div>

                <!-- Info & Actions -->
                <div class="flex-1 text-center sm:text-left min-w-0">
                    
                    <!-- Username & Action Buttons Row -->
                    <div class="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-5 mb-3">
                        <div class="flex items-center justify-center sm:justify-start gap-2">
                            <h1 class="text-xl sm:text-2xl font-black text-white tracking-tight truncate">{{ $user->handle }}</h1>
                            <span class="inline-flex items-center text-blue-400" title="Verified Cricket Fan">
                                <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24">
                                    <path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10 10-4.5 10-10S17.5 2 12 2zm-1.9 14.7l-4.1-4.1 1.4-1.4 2.7 2.7 6.4-6.4 1.4 1.4-7.8 7.8z"/>
                                </svg>
                            </span>
                        </div>

                        <div class="flex items-center justify-center gap-2">
                            @if($isOwner)
                                <button @click="editModalOpen = true" 
                                        class="px-4 py-1.5 rounded-lg bg-white/10 hover:bg-white/20 text-white text-sm font-semibold transition border border-white/15">
                                    Edit Profile
                                </button>
                                <button @click="shareProfile()" 
                                        class="p-2 rounded-lg bg-white/10 hover:bg-white/20 text-white transition border border-white/15" title="Share Profile">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                                    </svg>
                                </button>
                            @else
                                <button @click="toggleFollow({{ $user->id }})" 
                                        :class="isFollowing ? 'bg-white/10 hover:bg-rose-500/20 text-white border border-white/20' : 'bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-bold'"
                                        class="px-6 py-1.5 rounded-lg text-sm transition transform active:scale-95 shadow-md flex items-center gap-1.5">
                                    <span x-show="!isFollowLoading" x-text="isFollowing ? 'Following' : 'Follow'"></span>
                                    <span x-show="isFollowLoading" class="animate-spin text-xs">⏳</span>
                                </button>
                                <a href="mailto:{{ $user->email }}" 
                                   class="px-4 py-1.5 rounded-lg bg-white/10 hover:bg-white/20 text-white text-sm font-semibold transition border border-white/15">
                                    Message
                                </a>
                                <button @click="shareProfile()" 
                                        class="p-2 rounded-lg bg-white/10 hover:bg-white/20 text-white transition border border-white/15" title="Share Profile">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                                    </svg>
                                </button>
                            @endif
                        </div>
                    </div>

                    <!-- CricHeroes Player Role Badges -->
                    <div class="flex flex-wrap items-center justify-center sm:justify-start gap-1.5 mb-3">
                        <span class="px-2.5 py-0.5 rounded-md text-[11px] font-black bg-red-600 text-white shadow-xs">
                            🏏 {{ $user->player_role ?? 'All-Rounder' }}
                        </span>
                        <span class="px-2 py-0.5 rounded-md text-[11px] font-semibold bg-white/10 text-slate-300 border border-white/10">
                            {{ $user->batting_style ?? 'Right Handed Bat' }}
                        </span>
                        <span class="px-2 py-0.5 rounded-md text-[11px] font-semibold bg-white/10 text-slate-300 border border-white/10">
                            {{ $user->bowling_style ?? 'Right Arm Medium' }}
                        </span>
                    </div>

                    <!-- Follower / Post Stats Row -->
                    <div class="flex items-center justify-center sm:justify-start gap-8 my-3 text-sm">
                        <div>
                            <span class="font-extrabold text-white text-base">{{ $posts->count() }}</span> 
                            <span class="text-slate-400">posts</span>
                        </div>
                        <div>
                            <span class="font-extrabold text-white text-base" x-text="followersCount">{{ $user->followers_count ?? 0 }}</span> 
                            <span class="text-slate-400">followers</span>
                        </div>
                        <div>
                            <span class="font-extrabold text-white text-base" x-text="followingCount">{{ $user->following_count ?? 0 }}</span> 
                            <span class="text-slate-400">following</span>
                        </div>
                    </div>

                    <!-- Name & Bio details -->
                    <div class="space-y-1.5 text-sm">
                        <div class="font-bold text-white flex items-center justify-center sm:justify-start gap-2">
                            <span>{{ $user->name }}</span>
                            @if($user->favorite_team)
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30">
                                    🏆 {{ $user->favorite_team }}
                                </span>
                            @endif
                        </div>

                        <!-- WhatsApp Phone Number -->
                        @if($user->phone)
                            <div class="flex items-center justify-center sm:justify-start gap-1.5 text-xs text-emerald-400 font-bold">
                                <span>💬 WhatsApp:</span>
                                <span>{{ $user->phone }}</span>
                            </div>
                        @endif

                        <!-- Multi-line Bio -->
                        @if($user->bio)
                            <p class="text-slate-300 whitespace-pre-line leading-relaxed max-w-lg mt-1 text-xs sm:text-sm">
                                {{ $user->bio }}
                            </p>
                        @endif
                    </div>

                </div>
            </div>

            <!-- Suggested Creators to Follow -->
            @if($suggestedUsers->isNotEmpty())
                <div class="py-5 border-b border-white/10">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Suggested Cricket Creators</span>
                        <a href="{{ route('dashboard') }}" class="text-xs font-semibold text-blue-400 hover:text-blue-300">View Feed</a>
                    </div>
                    <div class="flex items-center gap-4 overflow-x-auto pb-2 scrollbar-thin">
                        @foreach($suggestedUsers as $suggested)
                            <div class="shrink-0 flex flex-col items-center p-3 rounded-2xl bg-white/5 border border-white/10 hover:border-white/20 transition w-36 text-center">
                                <a href="{{ route('profile.user', $suggested->id) }}" class="group">
                                    <img src="{{ $suggested->avatar_url }}" alt="{{ $suggested->name }}" 
                                         class="w-14 h-14 rounded-full object-cover mx-auto ring-2 ring-indigo-500/40 group-hover:scale-105 transition">
                                    <div class="font-bold text-white text-xs mt-2 truncate w-28">{{ $suggested->name }}</div>
                                    <div class="text-[10px] text-slate-400 truncate w-28">{{ $suggested->handle }}</div>
                                </a>
                                <a href="{{ route('profile.user', $suggested->id) }}" 
                                   class="mt-2.5 w-full py-1 text-[11px] font-bold rounded-lg bg-blue-600 hover:bg-blue-500 text-white transition">
                                    View Profile
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Navigation Tabs (Posts | CricHeroes Career Stats | Saved) -->
            <div class="flex items-center justify-center gap-8 sm:gap-14 text-xs font-bold uppercase tracking-widest text-slate-400 mt-2 border-b border-white/10">
                <button @click="activeTab = 'posts'" 
                        :class="activeTab === 'posts' ? 'active' : ''"
                        class="tab-btn py-3.5 flex items-center gap-2 hover:text-white transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                    </svg>
                    <span>Posts ({{ $posts->count() }})</span>
                </button>

                <button @click="activeTab = 'stats'" 
                        :class="activeTab === 'stats' ? 'active' : ''"
                        class="tab-btn py-3.5 flex items-center gap-2 hover:text-white transition">
                    <span>🏏 CricHeroes Stats</span>
                </button>

                <button @click="activeTab = 'saved'" 
                        :class="activeTab === 'saved' ? 'active' : ''"
                        class="tab-btn py-3.5 flex items-center gap-2 hover:text-white transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
                    </svg>
                    <span>Saved</span>
                </button>
            </div>

            <!-- TAB 1: Posts Grid (Instagram 3-Column Square Layout) -->
            <div x-show="activeTab === 'posts'" class="pt-6">
                @if($posts->isEmpty())
                    <div class="text-center py-16 px-4">
                        <div class="w-16 h-16 rounded-full border-2 border-dashed border-white/20 flex items-center justify-center mx-auto text-slate-500 mb-3">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                            </svg>
                        </div>
                        <h3 class="text-white font-bold text-base">No Posts Yet</h3>
                        <p class="text-slate-400 text-xs mt-1">When {{ $isOwner ? 'you create' : $user->name . ' creates' }} cricket posts or memes, they will appear here.</p>
                        @if($isOwner)
                            <a href="{{ route('dashboard') }}" class="inline-block mt-4 px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold shadow-lg transition">
                                Create Your First Post
                            </a>
                        @endif
                    </div>
                @else
                    <div class="grid grid-cols-3 gap-1 sm:gap-4">
                        @foreach($posts as $post)
                            <div class="post-grid-item relative aspect-square bg-slate-900 overflow-hidden cursor-pointer rounded-sm sm:rounded-xl group"
                                 @click="selectedPost = {{ json_encode([
                                     'id' => $post->id,
                                     'content' => $post->content,
                                     'media_url' => $post->media_url,
                                     'insta_url' => $post->insta_url,
                                     'likes_count' => $post->likes_count,
                                     'comments_count' => $post->comments->count(),
                                     'created_at' => $post->created_at->diffForHumans(),
                                     'user_name' => $post->user_name,
                                     'team_tag' => $post->team_tag,
                                 ]) }}">
                                
                                @if($post->media_url)
                                    <img src="{{ $post->media_url }}" alt="Cricket Post" class="w-full h-full object-cover transition duration-300 group-hover:scale-105">
                                @else
                                    <div class="w-full h-full p-3 sm:p-4 bg-gradient-to-br from-slate-900 to-indigo-950/60 flex flex-col justify-between text-left">
                                        <div class="text-[10px] font-bold text-blue-400">🏏 {{ $post->team_tag ?? 'CRICKET' }}</div>
                                        <p class="text-xs sm:text-sm font-semibold text-slate-200 line-clamp-4 leading-snug">
                                            {{ $post->content }}
                                        </p>
                                        <div class="text-[10px] text-slate-400 font-mono">{{ $post->created_at->diffForHumans() }}</div>
                                    </div>
                                @endif

                                @if($post->insta_url)
                                    <div class="absolute top-2 right-2 text-white drop-shadow-md">
                                        <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24">
                                            <path d="M10 8.64L15.27 12 10 15.36V8.64M8 5v14l11-7L8 5z"/>
                                        </svg>
                                    </div>
                                @endif

                                <div class="post-overlay absolute inset-0 flex items-center justify-center gap-6 text-white font-bold text-sm sm:text-base">
                                    <div class="flex items-center gap-1.5 drop-shadow-lg">
                                        <svg class="w-5 h-5 fill-current text-white" viewBox="0 0 24 24">
                                            <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                                        </svg>
                                        <span>{{ $post->likes_count }}</span>
                                    </div>
                                    <div class="flex items-center gap-1.5 drop-shadow-lg">
                                        <svg class="w-5 h-5 fill-current text-white" viewBox="0 0 24 24">
                                            <path d="M21.99 4c0-1.1-.89-2-1.99-2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h14l4 4-.01-18z"/>
                                        </svg>
                                        <span>{{ $post->comments->count() }}</span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- ============================================================== -->
            <!-- TAB 2: CRICHEROES CAREER STATS & PASSPORT -->
            <!-- ============================================================== -->
            <div x-show="activeTab === 'stats'" class="pt-6 space-y-6">
                
                <!-- CricHeroes Player Passport Banner -->
                <div class="p-6 rounded-3xl bg-[#131b2e] border border-white/10 shadow-2xl relative overflow-hidden">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-5 border-b border-white/10">
                        <div>
                            <span class="text-[10px] font-black uppercase text-red-400 tracking-wider">CRICHEROES VERIFIED PASSPORT</span>
                            <h3 class="text-xl font-black text-white mt-0.5">{{ $user->name }}</h3>
                            <div class="text-xs text-slate-400 mt-1">
                                <span>{{ $user->player_role ?? 'All-Rounder' }}</span>
                                <span>•</span>
                                <span>{{ $user->batting_style ?? 'Right Handed Bat' }}</span>
                                <span>•</span>
                                <span>{{ $user->bowling_style ?? 'Right Arm Medium' }}</span>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <span class="px-3 py-1 rounded-full text-xs font-black bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                                🏏 {{ $user->matches_played }} Matches Played
                            </span>
                        </div>
                    </div>

                    <!-- Batting & Bowling Stats Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mt-6">
                        
                        <!-- Batting Stats Card -->
                        <div class="p-5 rounded-2xl bg-black/40 border border-white/5 space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-black uppercase text-amber-300 flex items-center gap-1.5">
                                    <span>🏏 BATTING CAREER</span>
                                </span>
                                <span class="text-[11px] font-mono text-slate-400">Total Runs: <b class="text-white">{{ $user->total_runs }}</b></span>
                            </div>

                            <div class="grid grid-cols-3 gap-2.5 text-center">
                                <div class="p-2.5 rounded-xl bg-white/5">
                                    <div class="text-[10px] font-bold text-slate-400 uppercase">Runs</div>
                                    <div class="text-lg font-black text-white mt-0.5">{{ $user->total_runs }}</div>
                                </div>
                                <div class="p-2.5 rounded-xl bg-white/5">
                                    <div class="text-[10px] font-bold text-slate-400 uppercase">Highest</div>
                                    <div class="text-lg font-black text-amber-400 mt-0.5">{{ $user->highest_score }}</div>
                                </div>
                                <div class="p-2.5 rounded-xl bg-white/5">
                                    <div class="text-[10px] font-bold text-slate-400 uppercase">Average</div>
                                    <div class="text-lg font-black text-white mt-0.5">{{ $user->batting_avg }}</div>
                                </div>
                                <div class="p-2.5 rounded-xl bg-white/5">
                                    <div class="text-[10px] font-bold text-slate-400 uppercase">Strike Rate</div>
                                    <div class="text-lg font-black text-rose-400 mt-0.5">{{ $user->strike_rate }}</div>
                                </div>
                                <div class="p-2.5 rounded-xl bg-white/5">
                                    <div class="text-[10px] font-bold text-slate-400 uppercase">50s</div>
                                    <div class="text-lg font-black text-white mt-0.5">{{ $user->fifties }}</div>
                                </div>
                                <div class="p-2.5 rounded-xl bg-white/5">
                                    <div class="text-[10px] font-bold text-slate-400 uppercase">100s</div>
                                    <div class="text-lg font-black text-white mt-0.5">{{ $user->hundreds }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Bowling Stats Card -->
                        <div class="p-5 rounded-2xl bg-black/40 border border-white/5 space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-black uppercase text-blue-400 flex items-center gap-1.5">
                                    <span>⚡ BOWLING CAREER</span>
                                </span>
                                <span class="text-[11px] font-mono text-slate-400">Total Wkts: <b class="text-white">{{ $user->wickets_taken }}</b></span>
                            </div>

                            <div class="grid grid-cols-3 gap-2.5 text-center">
                                <div class="p-2.5 rounded-xl bg-white/5">
                                    <div class="text-[10px] font-bold text-slate-400 uppercase">Wickets</div>
                                    <div class="text-lg font-black text-rose-400 mt-0.5">{{ $user->wickets_taken }}</div>
                                </div>
                                <div class="p-2.5 rounded-xl bg-white/5">
                                    <div class="text-[10px] font-bold text-slate-400 uppercase">Best Figures</div>
                                    <div class="text-lg font-black text-amber-400 mt-0.5">{{ $user->best_bowling }}</div>
                                </div>
                                <div class="p-2.5 rounded-xl bg-white/5">
                                    <div class="text-[10px] font-bold text-slate-400 uppercase">Economy</div>
                                    <div class="text-lg font-black text-white mt-0.5">{{ $user->economy_rate }}</div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Recent Match Performances List -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between px-1">
                        <span class="text-xs font-black uppercase text-slate-300 tracking-wider">CricHeroes Match History & Performance</span>
                        <a href="{{ route('matches.index') }}" class="text-xs font-bold text-red-400 hover:text-red-300">View All Matches →</a>
                    </div>

                    @if(isset($matchPerformances) && $matchPerformances->isNotEmpty())
                        <div class="space-y-3">
                            @foreach($matchPerformances as $mp)
                                <div class="p-4 rounded-2xl bg-[#131b2e] border border-white/10 hover:border-red-500/30 transition flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="text-[11px] font-black text-rose-400 uppercase">
                                            {{ $mp->match->tournament_name ?? 'Cricket Match' }}
                                        </div>
                                        <div class="font-extrabold text-white text-sm sm:text-base mt-0.5">
                                            {{ $mp->match->team1_name }} vs {{ $mp->match->team2_name }}
                                        </div>
                                        <div class="text-[11px] text-slate-400 mt-1">
                                            <span>Team: <b>{{ $mp->team_name }}</b></span>
                                            @if($mp->is_captain)<span class="ml-1 px-1 rounded bg-red-600 text-[9px] font-black text-white">C</span>@endif
                                            @if($mp->is_wicketkeeper)<span class="ml-1 px-1 rounded bg-amber-600 text-[9px] font-black text-white">WK</span>@endif
                                            <span>• {{ $mp->match->match_date->format('d M Y') }}</span>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-4 text-xs font-mono shrink-0">
                                        <div class="p-2 rounded-xl bg-white/5 text-center">
                                            <div class="text-[10px] font-bold text-slate-400">Batting</div>
                                            <div class="font-extrabold text-amber-300 text-sm mt-0.5">
                                                {{ $mp->runs_scored }}* ({{ $mp->balls_faced }}b)
                                            </div>
                                            <div class="text-[9px] text-slate-400">{{ $mp->fours }}x4 • {{ $mp->sixes }}x6</div>
                                        </div>

                                        @if($mp->overs_bowled && $mp->overs_bowled !== '0.0')
                                            <div class="p-2 rounded-xl bg-white/5 text-center">
                                                <div class="text-[10px] font-bold text-slate-400">Bowling</div>
                                                <div class="font-extrabold text-rose-400 text-sm mt-0.5">
                                                    {{ $mp->wickets_taken }}/{{ $mp->runs_conceded }}
                                                </div>
                                                <div class="text-[9px] text-slate-400">({{ $mp->overs_bowled }} ov)</div>
                                            </div>
                                        @endif

                                        <a href="{{ route('matches.show', $mp->match_id) }}" 
                                           class="px-3 py-1.5 rounded-lg bg-red-600 hover:bg-red-500 text-white font-bold text-xs">
                                            Match →
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="p-8 rounded-2xl bg-white/5 border border-white/5 text-center text-slate-400 text-xs">
                            No match appearances recorded yet. Play or add this player to a match in CricHeroes Arena!
                        </div>
                    @endif
                </div>

            </div>

            <!-- TAB 3: Saved Posts Placeholder -->
            <div x-show="activeTab === 'saved'" class="pt-6 text-center py-16 text-slate-400">
                <div class="w-16 h-16 rounded-full border-2 border-dashed border-white/20 flex items-center justify-center mx-auto text-slate-500 mb-3">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
                    </svg>
                </div>
                <h3 class="text-white font-bold text-base">Save Your Favorite Posts</h3>
                <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">Click the bookmark icon on any post in the Cricket Feed to save photos and match moments here.</p>
                <a href="{{ route('dashboard') }}" class="inline-block mt-4 px-4 py-2 rounded-xl bg-white/10 hover:bg-white/20 text-white text-xs font-semibold transition border border-white/10">
                    Explore Cricket Feed
                </a>
            </div>

        </div>

        <!-- ============================================== -->
        <!-- POST DETAIL MODAL -->
        <!-- ============================================== -->
        <div x-cloak x-show="selectedPost" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-black/80 backdrop-blur-md"
             @keydown.escape.window="selectedPost = null">
            <div class="relative bg-slate-900 border border-white/10 rounded-2xl max-w-2xl w-full overflow-hidden shadow-2xl flex flex-col max-h-[90vh]"
                 @click.away="selectedPost = null">
                
                <div class="flex items-center justify-between p-4 border-b border-white/10">
                    <div class="flex items-center gap-3">
                        <img src="{{ $user->avatar_url }}" class="w-9 h-9 rounded-full object-cover">
                        <div>
                            <div class="font-bold text-white text-sm">{{ $user->name }}</div>
                            <div class="text-[11px] text-slate-400" x-text="selectedPost ? selectedPost.created_at : ''"></div>
                        </div>
                    </div>
                    <button @click="selectedPost = null" class="text-slate-400 hover:text-white p-1 rounded-lg">✕</button>
                </div>

                <div class="overflow-y-auto flex-1">
                    <template x-if="selectedPost && selectedPost.media_url">
                        <div class="bg-black flex items-center justify-center max-h-96 overflow-hidden">
                            <img :src="selectedPost.media_url" class="max-h-96 w-full object-contain">
                        </div>
                    </template>
                    
                    <div class="p-5">
                        <p class="text-slate-200 text-sm whitespace-pre-line leading-relaxed" x-text="selectedPost ? selectedPost.content : ''"></p>

                        <div class="flex items-center justify-between mt-5 pt-4 border-t border-white/10 text-xs text-slate-400 font-semibold">
                            <div class="flex items-center gap-4">
                                <span class="flex items-center gap-1 text-rose-400">
                                    ❤️ <span x-text="selectedPost ? selectedPost.likes_count : 0"></span> likes
                                </span>
                                <span class="flex items-center gap-1 text-sky-400">
                                    💬 <span x-text="selectedPost ? selectedPost.comments_count : 0"></span> comments
                                </span>
                            </div>
                            <a :href="'/dashboard'" class="text-blue-400 hover:underline">
                                View in Feed
                            </a>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- ============================================== -->
        <!-- EDIT PROFILE MODAL (With CricHeroes Player Specs) -->
        <!-- ============================================== -->
        @if($isOwner)
            <div x-cloak x-show="editModalOpen" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-black/80 backdrop-blur-md"
                 @keydown.escape.window="editModalOpen = false">
                <div class="relative bg-slate-900 border border-white/10 rounded-2xl max-w-lg w-full p-6 shadow-2xl max-h-[90vh] overflow-y-auto"
                     @click.away="editModalOpen = false">
                    
                    <div class="flex items-center justify-between pb-4 border-b border-white/10 mb-5">
                        <h2 class="text-lg font-black text-white flex items-center gap-2">
                            <span>Edit Cricket Profile & Specs</span>
                        </h2>
                        <button @click="editModalOpen = false" class="text-slate-400 hover:text-white">✕</button>
                    </div>

                    <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                        @csrf

                        <!-- Profile Photo Picker -->
                        <div class="flex items-center gap-4 p-3 rounded-xl bg-white/5 border border-white/10">
                            <img :src="avatarPreview" class="w-16 h-16 rounded-full object-cover ring-2 ring-emerald-500 shadow-md">
                            <div>
                                <input type="hidden" name="cropped_avatar" :value="croppedAvatarBase64">
                                <label class="cursor-pointer px-3.5 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition inline-flex items-center gap-1.5 shadow">
                                    <span>📸 Crop Photo (WhatsApp Style)</span>
                                    <input type="file" accept="image/jpeg,image/png,image/jpg,image/webp" class="hidden"
                                           @change="if($event.target.files[0]) { openCropper($event.target.files[0]); } $event.target.value = '';">
                                </label>
                                <p class="text-[10px] text-slate-400 mt-1">Circle crop, pan, and zoom before saving.</p>
                            </div>
                        </div>

                        <!-- Name -->
                        <div>
                            <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1">Display Name</label>
                            <input type="text" name="name" value="{{ old('name', $user->name) }}" required
                                   class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2 text-sm text-white focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        </div>

                        <!-- Username Handle -->
                        <div>
                            <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1">Username Handle</label>
                            <div class="flex items-center bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2">
                                <span class="text-slate-500 font-bold mr-1">@</span>
                                <input type="text" name="username" value="{{ old('username', ltrim($user->username ?? '', '@')) }}"
                                       class="w-full bg-transparent border-0 text-sm text-white focus:ring-0 focus:outline-none p-0">
                            </div>
                        </div>

                        <!-- WhatsApp Phone Number -->
                        <div>
                            <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1">WhatsApp / Phone Number</label>
                            <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" placeholder="+91 98765 43210"
                                   class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2 text-sm text-white focus:ring-2 focus:ring-blue-500 focus:outline-none">
                            <p class="text-[10px] text-slate-400 mt-1">Scorers can find you and fetch your career records using this WhatsApp number!</p>
                        </div>

                        <!-- Cricket Player Specs -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1">Role</label>
                                <select name="player_role" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-xs text-white">
                                    <option value="All-Rounder" {{ $user->player_role === 'All-Rounder' ? 'selected' : '' }}>All-Rounder</option>
                                    <option value="Top-Order Batter" {{ $user->player_role === 'Top-Order Batter' ? 'selected' : '' }}>Top-Order Batter</option>
                                    <option value="Middle-Order Batter" {{ $user->player_role === 'Middle-Order Batter' ? 'selected' : '' }}>Middle-Order Batter</option>
                                    <option value="Fast Bowler" {{ $user->player_role === 'Fast Bowler' ? 'selected' : '' }}>Fast Bowler</option>
                                    <option value="Spin Bowler" {{ $user->player_role === 'Spin Bowler' ? 'selected' : '' }}>Spin Bowler</option>
                                    <option value="Wicketkeeper Batter" {{ $user->player_role === 'Wicketkeeper Batter' ? 'selected' : '' }}>Wicketkeeper Batter</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1">Batting Style</label>
                                <select name="batting_style" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-xs text-white">
                                    <option value="Right Handed Bat" {{ $user->batting_style === 'Right Handed Bat' ? 'selected' : '' }}>Right Handed Bat</option>
                                    <option value="Left Handed Bat" {{ $user->batting_style === 'Left Handed Bat' ? 'selected' : '' }}>Left Handed Bat</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1">Bowling Style</label>
                                <select name="bowling_style" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-xs text-white">
                                    <option value="Right Arm Fast" {{ $user->bowling_style === 'Right Arm Fast' ? 'selected' : '' }}>Right Arm Fast</option>
                                    <option value="Right Arm Medium Fast" {{ $user->bowling_style === 'Right Arm Medium Fast' ? 'selected' : '' }}>Right Arm Medium</option>
                                    <option value="Right Arm Spin" {{ $user->bowling_style === 'Right Arm Spin' ? 'selected' : '' }}>Right Arm Spin</option>
                                    <option value="Left Arm Fast" {{ $user->bowling_style === 'Left Arm Fast' ? 'selected' : '' }}>Left Arm Fast</option>
                                    <option value="Left Arm Spin" {{ $user->bowling_style === 'Left Arm Spin' ? 'selected' : '' }}>Left Arm Spin</option>
                                    <option value="None" {{ $user->bowling_style === 'None' ? 'selected' : '' }}>None (Pure Bat/WK)</option>
                                </select>
                            </div>
                        </div>

                        <!-- Favorite Team -->
                        <div>
                            <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1">Favorite Team / Club</label>
                            <input type="text" name="favorite_team" value="{{ old('favorite_team', $user->favorite_team) }}" placeholder="e.g. India / CSK / RCB"
                                   class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2 text-sm text-white focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        </div>

                        <!-- Bio -->
                        <div>
                            <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1">Bio</label>
                            <textarea name="bio" rows="3" placeholder="Tell other cricket fans about yourself..."
                                      class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2 text-sm text-white focus:ring-2 focus:ring-blue-500 focus:outline-none resize-none">{{ old('bio', $user->bio) }}</textarea>
                        </div>

                        <input type="hidden" name="email" value="{{ $user->email }}">

                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-white/10">
                            <button type="button" @click="editModalOpen = false" 
                                    class="px-4 py-2 rounded-xl text-slate-400 hover:text-white text-xs font-bold transition">
                                Cancel
                            </button>
                            <button type="submit" 
                                    class="px-6 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-extrabold shadow-lg shadow-blue-600/30 transition">
                                Save Profile
                            </button>
                        </div>
                    </form>

                </div>
            </div>
        @endif

        <!-- ==================================================== -->
        <!-- WHATSAPP-STYLE CIRCULAR PROFILE PICTURE CROPPER MODAL -->
        <!-- ==================================================== -->
        @if($isOwner)
            <div x-cloak x-show="cropperModalOpen"
                 class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/85 backdrop-blur-md"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95">

                <div class="relative bg-[#0d1322] border border-white/15 rounded-3xl max-w-md w-full overflow-hidden shadow-2xl flex flex-col"
                     @click.away="if(!isSavingAvatar) cropperModalOpen = false">

                    <!-- Cropper Modal Header -->
                    <div class="flex items-center justify-between p-4 border-b border-white/10 bg-[#090d16]">
                        <div class="flex items-center gap-2.5">
                            <div class="w-9 h-9 rounded-xl bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center text-emerald-400 text-lg">
                                ✂️
                            </div>
                            <div>
                                <h3 class="text-sm font-black text-white leading-tight">Crop Profile Picture</h3>
                                <p class="text-[10px] text-slate-400">WhatsApp-style circular crop, pan & zoom</p>
                            </div>
                        </div>
                        <button type="button" @click="cropperModalOpen = false" class="text-slate-400 hover:text-white p-1 rounded-lg text-sm font-bold">
                            ✕
                        </button>
                    </div>

                    <!-- Cropper Canvas Viewport -->
                    <div class="p-4 sm:p-5 flex flex-col items-center justify-center bg-black/50">
                        <div class="relative rounded-2xl overflow-hidden border border-white/10 shadow-2xl select-none touch-none cursor-grab active:cursor-grabbing bg-slate-950"
                             style="width: 300px; height: 300px;">
                            <canvas x-ref="cropCanvas"
                                    width="300"
                                    height="300"
                                    class="w-full h-full block"
                                    @mousedown="onCanvasMouseDown($event)"
                                    @mousemove="onCanvasMouseMove($event)"
                                    @mouseup="onCanvasMouseUp()"
                                    @mouseleave="onCanvasMouseUp()"
                                    @touchstart="onTouchStart($event)"
                                    @touchmove="onTouchMove($event)"
                                    @touchend="onTouchEnd()">
                            </canvas>

                            <!-- Hint Badge Floating -->
                            <div class="absolute bottom-2 left-1/2 -translate-x-1/2 pointer-events-none">
                                <span class="px-2.5 py-0.5 rounded-full text-[9px] font-bold bg-black/75 text-slate-300 backdrop-blur border border-white/10 shadow">
                                    🖐️ Drag to reposition photo
                                </span>
                            </div>
                        </div>

                        <!-- Zoom Slider Control -->
                        <div class="w-full max-w-xs mt-4 space-y-1.5">
                            <div class="flex items-center justify-between text-xs text-slate-300 font-bold">
                                <span class="flex items-center gap-1.5 text-slate-400 text-[11px]">
                                    <span>🔍</span> Zoom
                                </span>
                                <span class="text-emerald-400 text-[11px]" x-text="Math.round(cropScale * 100) + '%'">100%</span>
                            </div>
                            <div class="flex items-center gap-2.5">
                                <button type="button"
                                        @click="cropScale = Math.max(1, +(cropScale - 0.1).toFixed(2)); renderCropCanvas();"
                                        class="w-7 h-7 rounded-lg bg-white/10 hover:bg-white/20 text-white font-black text-sm flex items-center justify-center transition">
                                    -
                                </button>
                                <input type="range"
                                       min="1.0"
                                       max="3.5"
                                       step="0.05"
                                       x-model.number="cropScale"
                                       @input="renderCropCanvas()"
                                       class="flex-1 h-2 bg-slate-800 rounded-lg appearance-none cursor-pointer accent-emerald-500">
                                <button type="button"
                                        @click="cropScale = Math.min(3.5, +(cropScale + 0.1).toFixed(2)); renderCropCanvas();"
                                        class="w-7 h-7 rounded-lg bg-white/10 hover:bg-white/20 text-white font-black text-sm flex items-center justify-center transition">
                                    +
                                </button>
                            </div>
                        </div>

                        <!-- Rotate & Reset Action Buttons -->
                        <div class="flex items-center gap-2 mt-3">
                            <button type="button"
                                    @click="rotateClockwise()"
                                    class="px-3 py-1 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-xs font-bold text-slate-300 hover:text-white flex items-center gap-1 transition">
                                <span>🔄 Rotate 90°</span>
                            </button>
                            <button type="button"
                                    @click="resetCrop()"
                                    class="px-3 py-1 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-xs font-bold text-slate-400 hover:text-slate-200 transition">
                                <span>↺ Reset</span>
                            </button>
                        </div>
                    </div>

                    <!-- Modal Actions Footer -->
                    <div class="p-3.5 sm:p-4 border-t border-white/10 bg-[#090d16] flex items-center justify-between gap-3">
                        <button type="button"
                                @click="cropperModalOpen = false"
                                :disabled="isSavingAvatar"
                                class="px-4 py-2 rounded-xl text-slate-400 hover:text-white text-xs font-bold transition">
                            Cancel
                        </button>

                        <button type="button"
                                @click="saveCroppedAvatar()"
                                :disabled="isSavingAvatar"
                                class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white text-xs font-black shadow-lg shadow-emerald-600/30 flex items-center gap-2 transition transform active:scale-95 disabled:opacity-50">
                            <span x-show="!isSavingAvatar">Done • Set DP 📸</span>
                            <span x-show="isSavingAvatar" x-cloak class="flex items-center gap-2">
                                <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                </svg>
                                <span>Saving to TiDB...</span>
                            </span>
                        </button>
                    </div>

                </div>
            </div>
        @endif

    </div>
</x-app-layout>
