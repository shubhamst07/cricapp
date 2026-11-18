@extends('layouts.app')

@section('title', 'Find Cricket Players & Match Meetups | LiveCricket')

@section('header')
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-500/10 text-amber-500 dark:text-amber-400 border border-amber-500/20 mb-1">
                🏏 Community & Player Finder • खिलाड़ी चाहिए
            </div>
            <h2 class="font-black text-2xl sm:text-3xl text-slate-900 dark:text-white leading-tight">
                Cricket Match Meetups
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                Sunday ko cricket khelna hai aur players chahiye? Find nearby players, join weekend matches, or create a local cricket meetup.
            </p>
        </div>

        <div class="flex items-center gap-2.5">
            @auth
                <a href="{{ route('meetups.create') }}"
                   class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 via-orange-500 to-amber-600 hover:from-amber-400 hover:to-orange-500 text-slate-950 font-black text-xs sm:text-sm shadow-lg shadow-amber-500/25 transition transform active:scale-95 cursor-pointer">
                    <span>➕ Need Players / Post Match</span>
                </a>
            @else
                <a href="{{ route('login') }}"
                   class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 text-slate-950 font-black text-xs sm:text-sm shadow-md transition">
                    <span>Sign In to Post Match</span>
                </a>
            @endauth
        </div>
    </div>
@endsection

@section('content')
<div class="py-6 sm:py-8 bg-slate-950 min-h-screen text-slate-100 font-sans" x-data="{
    circleModal: false,
    circleCity: '{{ $city ?: 'Shimla' }}',
    circleName: '',
    circleDesc: '',
    circleWhatsApp: ''
}">

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        @if(session('success'))
            <div class="p-4 rounded-2xl bg-emerald-500/15 border border-emerald-500/30 text-emerald-300 text-xs sm:text-sm font-bold flex items-center gap-2.5 shadow-lg">
                <span class="text-lg">✅</span>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if(session('error'))
            <div class="p-4 rounded-2xl bg-rose-500/15 border border-rose-500/30 text-rose-300 text-xs sm:text-sm font-bold flex items-center gap-2.5 shadow-lg">
                <span class="text-lg">⚠️</span>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        @if(session('info'))
            <div class="p-4 rounded-2xl bg-blue-500/15 border border-blue-500/30 text-blue-300 text-xs sm:text-sm font-bold flex items-center gap-2.5 shadow-lg">
                <span class="text-lg">ℹ️</span>
                <span>{{ session('info') }}</span>
            </div>
        @endif

        <!-- ============================================================== -->
        <!-- SEARCH & FILTER BAR -->
        <!-- ============================================================== -->
        <div class="p-4 sm:p-5 rounded-3xl bg-[#131b2e] border border-white/10 shadow-2xl space-y-4">
            
            <!-- City Search Input & Filter Tabs -->
            <form method="GET" action="{{ route('meetups.index') }}" class="space-y-3">
                <div class="flex flex-col sm:flex-row items-center gap-2.5">
                    <!-- Search Input -->
                    <div class="relative flex-1 w-full">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            🔍
                        </span>
                        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search by match title, ground, or area (e.g. Ridge Ground, Sunday Friendly)..."
                               class="w-full pl-10 pr-4 py-2.5 rounded-2xl bg-slate-900 border border-white/10 text-white placeholder-slate-400 text-xs sm:text-sm focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition" />
                    </div>

                    <!-- City Select / Text -->
                    <div class="w-full sm:w-48">
                        <input type="text" name="city" value="{{ $city }}" placeholder="Filter City (e.g. Shimla)"
                               class="w-full px-3.5 py-2.5 rounded-2xl bg-slate-900 border border-white/10 text-white placeholder-slate-400 text-xs sm:text-sm focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition" />
                    </div>

                    <!-- Submit Filter -->
                    <button type="submit" 
                            class="w-full sm:w-auto px-5 py-2.5 rounded-2xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-black text-xs uppercase tracking-wider transition shadow-md">
                        Filter Matches
                    </button>
                    @if(request()->hasAny(['q', 'city', 'when', 'ball_type']))
                        <a href="{{ route('meetups.index') }}" class="text-xs text-slate-400 hover:text-white font-bold px-2 py-1">
                            ✕ Reset
                        </a>
                    @endif
                </div>

                <!-- Timing Pills (When to play) -->
                <div class="flex flex-wrap items-center gap-1.5 pt-1 text-xs">
                    <span class="text-slate-400 text-[11px] font-bold mr-1">When:</span>
                    <a href="{{ route('meetups.index', array_merge(request()->query(), ['when' => 'all'])) }}"
                       class="px-3 py-1 rounded-xl transition font-bold {{ $when === 'all' || empty($when) ? 'bg-amber-500 text-slate-950 font-black shadow' : 'bg-white/5 text-slate-300 hover:bg-white/10' }}">
                        All Upcoming
                    </a>
                    <a href="{{ route('meetups.index', array_merge(request()->query(), ['when' => 'weekend'])) }}"
                       class="px-3 py-1 rounded-xl transition font-bold {{ $when === 'weekend' ? 'bg-amber-500 text-slate-950 font-black shadow' : 'bg-white/5 text-slate-300 hover:bg-white/10' }}">
                        🏏 This Weekend (Sun/Sat)
                    </a>
                    <a href="{{ route('meetups.index', array_merge(request()->query(), ['when' => 'tomorrow'])) }}"
                       class="px-3 py-1 rounded-xl transition font-bold {{ $when === 'tomorrow' ? 'bg-amber-500 text-slate-950 font-black shadow' : 'bg-white/5 text-slate-300 hover:bg-white/10' }}">
                        ⚡ Tomorrow
                    </a>
                    <a href="{{ route('meetups.index', array_merge(request()->query(), ['when' => 'today'])) }}"
                       class="px-3 py-1 rounded-xl transition font-bold {{ $when === 'today' ? 'bg-amber-500 text-slate-950 font-black shadow' : 'bg-white/5 text-slate-300 hover:bg-white/10' }}">
                        🔴 Today
                    </a>
                </div>

                <!-- Ball Type Pills -->
                <div class="flex flex-wrap items-center gap-1.5 pt-1 text-xs border-t border-white/5">
                    <span class="text-slate-400 text-[11px] font-bold mr-1">Ball / Format:</span>
                    <a href="{{ route('meetups.index', array_merge(request()->query(), ['ball_type' => 'all'])) }}"
                       class="px-2.5 py-1 rounded-lg transition {{ $ballType === 'all' || empty($ballType) ? 'bg-white/20 text-white font-bold' : 'text-slate-400 hover:text-white' }}">
                        All Types
                    </a>
                    <a href="{{ route('meetups.index', array_merge(request()->query(), ['ball_type' => 'tennis'])) }}"
                       class="px-2.5 py-1 rounded-lg transition {{ $ballType === 'tennis' ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30 font-bold' : 'text-slate-400 hover:text-white' }}">
                        🎾 Tennis Ball
                    </a>
                    <a href="{{ route('meetups.index', array_merge(request()->query(), ['ball_type' => 'leather'])) }}"
                       class="px-2.5 py-1 rounded-lg transition {{ $ballType === 'leather' ? 'bg-rose-500/20 text-rose-300 border border-rose-500/30 font-bold' : 'text-slate-400 hover:text-white' }}">
                        🏏 Leather Ball
                    </a>
                    <a href="{{ route('meetups.index', array_merge(request()->query(), ['ball_type' => 'box_turf'])) }}"
                       class="px-2.5 py-1 rounded-lg transition {{ $ballType === 'box_turf' ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 font-bold' : 'text-slate-400 hover:text-white' }}">
                        🏟️ Box Turf
                    </a>
                </div>
            </form>

            <!-- Popular Cities Quick Selection -->
            @if($popularCities && $popularCities->count() > 0)
                <div class="pt-2 flex flex-wrap items-center gap-1.5 text-xs">
                    <span class="text-slate-400 text-[11px] font-bold">📍 Cities:</span>
                    @foreach($popularCities as $c)
                        <a href="{{ route('meetups.index', ['city' => $c]) }}"
                           class="px-3 py-1 rounded-full text-xs font-mono font-bold transition {{ strtolower($city ?? '') === strtolower($c) ? 'bg-amber-500 text-slate-950 shadow' : 'bg-slate-900 hover:bg-slate-800 text-slate-300 border border-white/10' }}">
                            {{ $c }}
                        </a>
                    @endforeach
                </div>
            @endif

        </div>

        <!-- ============================================================== -->
        <!-- LOCAL CRICKET CIRCLES (COMMUNITY GROUPS) -->
        <!-- ============================================================== -->
        @if($circles && $circles->count() > 0)
            <div class="p-5 rounded-3xl bg-gradient-to-br from-indigo-950/40 via-slate-900 to-[#131b2e] border border-indigo-500/20 shadow-2xl space-y-3">
                <div class="flex items-center justify-between pb-2 border-b border-white/10">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">👥</span>
                        <div>
                            <h3 class="font-black text-sm sm:text-base text-white">Local Cricket Circles & Clubs</h3>
                            <p class="text-[11px] text-slate-400">Join your city's cricket group for recurring weekend matches & player chats</p>
                        </div>
                    </div>
                    @auth
                        <button type="button" @click="circleModal = true" class="px-3 py-1.5 rounded-xl bg-white/10 hover:bg-white/20 text-white font-bold text-xs transition">
                            + New Circle
                        </button>
                    @endauth
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 pt-1">
                    @foreach($circles as $circle)
                        <div class="p-3.5 rounded-2xl bg-black/40 border border-white/10 flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <div class="font-black text-xs sm:text-sm text-white truncate flex items-center gap-1.5">
                                    <span>🏏</span>
                                    <span class="truncate">{{ $circle->name }}</span>
                                </div>
                                <div class="text-[10px] text-slate-400 mt-0.5 flex items-center gap-1.5">
                                    <span>📍 {{ $circle->city }}</span>
                                    <span>•</span>
                                    <span class="text-emerald-400 font-mono font-bold">{{ $circle->members_count ?? $circle->memberCount() }} Members</span>
                                </div>
                            </div>

                            <div class="shrink-0">
                                @auth
                                    @if($circle->isUserMember(auth()->id()))
                                        <form method="POST" action="{{ route('circles.leave', $circle) }}">
                                            @csrf
                                            <button type="submit" class="px-3 py-1.5 rounded-xl bg-white/10 hover:bg-rose-950/60 text-slate-300 hover:text-rose-300 font-bold text-xs transition border border-white/10">
                                                Joined ✓
                                            </button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('circles.join', $circle) }}">
                                            @csrf
                                            <button type="submit" class="px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs transition shadow-md">
                                                + Join
                                            </button>
                                        </form>
                                    @endif
                                @else
                                    <a href="{{ route('login') }}" class="px-3 py-1.5 rounded-xl bg-indigo-600/30 text-indigo-300 text-xs font-bold">
                                        Join
                                    </a>
                                @endauth
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- ============================================================== -->
        <!-- MATCH MEETUPS CARDS GRID -->
        <!-- ============================================================== -->
        <div>
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    <span class="text-xl">📅</span>
                    <h3 class="font-black text-lg sm:text-xl text-white">Upcoming Cricket Meetups</h3>
                    <span class="px-2.5 py-0.5 rounded-full bg-amber-500/20 text-amber-300 font-mono font-bold text-xs">
                        {{ $meetups->total() }} Matches
                    </span>
                </div>
            </div>

            @if($meetups->count() > 0)
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($meetups as $meetup)
                        <div class="rounded-3xl bg-[#131b2e] border border-white/10 hover:border-amber-500/40 transition shadow-xl overflow-hidden flex flex-col justify-between group">
                            
                            <!-- Card Header & Status -->
                            <div class="p-4 sm:p-5 space-y-3">
                                
                                <!-- Top Row: Timing Badge & Slots Counter -->
                                <div class="flex items-center justify-between gap-2">
                                    <span class="px-3 py-1 rounded-xl bg-slate-900 border border-white/10 text-white font-mono text-xs font-bold flex items-center gap-1.5">
                                        <span>{{ $meetup->timing_badge }}</span>
                                        <span class="text-slate-400">•</span>
                                        <span class="text-amber-400 font-extrabold">{{ $meetup->match_time }}</span>
                                    </span>

                                    <!-- Slots Remaining Badge -->
                                    @if($meetup->isFull())
                                        <span class="px-2.5 py-1 rounded-full bg-slate-800 text-slate-400 font-bold text-[10px] uppercase border border-white/10">
                                            🏆 Squad Full ({{ $meetup->activeParticipants->count() }}/{{ $meetup->total_players }})
                                        </span>
                                    @else
                                        <span class="px-2.5 py-1 rounded-full bg-emerald-500/20 text-emerald-400 font-black text-[11px] border border-emerald-500/30 flex items-center gap-1.5 shadow-sm animate-pulse">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                            <span>{{ $meetup->slotsLeft() }} Slots Needed!</span>
                                        </span>
                                    @endif
                                </div>

                                <!-- Match Title -->
                                <div>
                                    <a href="{{ route('meetups.show', $meetup) }}" class="font-black text-base sm:text-lg text-white hover:text-amber-400 transition line-clamp-1">
                                        {{ $meetup->title }}
                                    </a>
                                    <div class="flex items-center gap-1.5 text-xs text-slate-300 font-medium mt-1 truncate">
                                        <span>📍</span>
                                        <span class="text-amber-400 font-bold">{{ $meetup->city }}</span>
                                        <span>•</span>
                                        <span class="truncate text-slate-400">{{ $meetup->location_name }}</span>
                                        <a href="{{ $meetup->google_maps_link }}" target="_blank"
                                           class="text-[11px] text-emerald-400 hover:text-emerald-300 font-bold ml-1 hover:underline inline-flex items-center gap-0.5"
                                           title="Open in Google Maps">
                                            <span>🗺️ Map ↗</span>
                                        </a>
                                    </div>
                                </div>

                                <!-- Match Specs Tags -->
                                <div class="flex flex-wrap items-center gap-1.5 pt-1 text-[11px]">
                                    <span class="px-2 py-0.5 rounded-md bg-white/5 border border-white/10 text-slate-300 font-mono">
                                        @if($meetup->ball_type === 'tennis') 🎾 Tennis Ball
                                        @elseif($meetup->ball_type === 'leather') 🏏 Leather Ball
                                        @elseif($meetup->ball_type === 'box_turf') 🏟️ Box Turf
                                        @else ⚾ {{ ucfirst($meetup->ball_type) }}
                                        @endif
                                    </span>

                                    <span class="px-2 py-0.5 rounded-md bg-white/5 border border-white/10 text-slate-300 font-mono">
                                        👥 {{ $meetup->activeParticipants->count() }} Joined
                                    </span>

                                    @if($meetup->cost_per_player)
                                        <span class="px-2 py-0.5 rounded-md bg-amber-500/10 border border-amber-500/20 text-amber-300 font-mono font-bold">
                                            💰 {{ $meetup->cost_per_player }}
                                        </span>
                                    @endif
                                </div>

                                <!-- Organizer Snippet -->
                                <div class="pt-2 border-t border-white/5 flex items-center justify-between text-xs text-slate-400">
                                    @if($meetup->user_id)
                                        <a href="{{ route('profile.user', $meetup->user_id) }}" 
                                           title="View {{ $meetup->user->name ?? 'Organizer' }}'s Profile"
                                           class="flex items-center gap-2 min-w-0 hover:text-amber-400 transition group/host">
                                            <div class="w-6 h-6 rounded-full bg-indigo-600/30 border border-indigo-400/30 flex items-center justify-center text-[10px] font-black text-indigo-300 shrink-0 group-hover/host:border-amber-400">
                                                {{ strtoupper(substr($meetup->user->name ?? 'C', 0, 1)) }}
                                            </div>
                                            <span class="truncate text-[11px]">By <b class="text-slate-200 group-hover/host:text-amber-400 transition">{{ $meetup->user->name ?? 'Captain' }}</b></span>
                                        </a>
                                    @else
                                        <div class="flex items-center gap-2 min-w-0">
                                            <div class="w-6 h-6 rounded-full bg-indigo-600/30 border border-indigo-400/30 flex items-center justify-center text-[10px] font-black text-indigo-300 shrink-0">
                                                {{ strtoupper(substr($meetup->user->name ?? 'C', 0, 1)) }}
                                            </div>
                                            <span class="truncate text-[11px]">By <b class="text-slate-200">{{ $meetup->user->name ?? 'Captain' }}</b></span>
                                        </div>
                                    @endif
                                    <span class="text-[10px] text-slate-500 font-mono">{{ $meetup->created_at->diffForHumans() }}</span>
                                </div>

                            </div>

                            <!-- Card Footer: Quick Actions -->
                            <div class="p-3 bg-white/5 border-t border-white/5 flex items-center justify-between gap-2">
                                <a href="{{ route('meetups.show', $meetup) }}" 
                                   class="flex-1 py-2 px-4 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-400 hover:to-orange-400 text-slate-950 font-black text-xs text-center transition shadow-md flex items-center justify-center gap-1.5">
                                    <span>View Squad & Join</span>
                                    <span>➔</span>
                                </a>

                                @if($meetup->whats_app_url)
                                    <a href="{{ $meetup->whats_app_url }}" target="_blank" rel="noopener noreferrer" 
                                       class="py-2 px-3 rounded-xl bg-emerald-600/20 hover:bg-emerald-600/40 border border-emerald-500/40 text-emerald-400 font-bold text-xs transition flex items-center justify-center gap-1"
                                       title="Chat on WhatsApp">
                                        <span>💬</span>
                                    </a>
                                @endif
                            </div>

                        </div>
                    @endforeach
                </div>

                <div class="pt-6">
                    {{ $meetups->links() }}
                </div>
            @else
                <!-- Empty State -->
                <div class="p-10 rounded-3xl bg-[#131b2e] border border-dashed border-white/15 text-center space-y-3 max-w-lg mx-auto">
                    <div class="text-5xl mb-2">🏏</div>
                    <h3 class="font-black text-lg text-white">No Upcoming Match Meetups Found</h3>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        Are you planning to play this weekend or need players for your team? Create a match meetup in seconds and let nearby cricket players join you!
                    </p>
                    <div class="pt-2">
                        @auth
                            <a href="{{ route('meetups.create') }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-black text-xs uppercase tracking-wider shadow-lg transition">
                                <span>➕ Post Your Match Meetup</span>
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-black text-xs uppercase tracking-wider shadow-lg transition">
                                <span>Sign In to Post Match</span>
                            </a>
                        @endauth
                    </div>
                </div>
            @endif
        </div>

    </div>

    <!-- ============================================================== -->
    <!-- MODAL: CREATE NEW CRICKET CIRCLE -->
    <!-- ============================================================== -->
    <div x-cloak x-show="circleModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100">
        <div class="bg-[#131b2e] border border-white/15 rounded-3xl p-6 max-w-md w-full space-y-4 shadow-2xl text-slate-100"
             @click.away="circleModal = false">
            <div class="flex items-center justify-between pb-3 border-b border-white/10">
                <div class="flex items-center gap-2">
                    <span class="text-xl">👥</span>
                    <h3 class="font-black text-base text-white">Create City Cricket Circle</h3>
                </div>
                <button type="button" @click="circleModal = false" class="text-slate-400 hover:text-white font-bold text-xs">✕</button>
            </div>

            <form method="POST" action="{{ route('circles.store') }}" class="space-y-3.5 text-xs">
                @csrf
                <div>
                    <label class="block text-slate-300 font-bold mb-1">Circle / Club Name *</label>
                    <input type="text" name="name" required placeholder="e.g. Shimla Weekend Cricketers"
                           class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-white/10 text-white placeholder-slate-500 focus:border-amber-400 text-xs" />
                </div>

                <div>
                    <label class="block text-slate-300 font-bold mb-1">City / Region *</label>
                    <input type="text" name="city" required placeholder="e.g. Shimla" x-model="circleCity"
                           class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-white/10 text-white placeholder-slate-500 focus:border-amber-400 text-xs" />
                </div>

                <div>
                    <label class="block text-slate-300 font-bold mb-1">WhatsApp Group Link (Optional)</label>
                    <input type="url" name="whatsapp_link" placeholder="https://chat.whatsapp.com/..."
                           class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-white/10 text-white placeholder-slate-500 focus:border-amber-400 text-xs" />
                </div>

                <div>
                    <label class="block text-slate-300 font-bold mb-1">Short Description</label>
                    <textarea name="description" rows="2" placeholder="Local group for players who play friendly tennis & box cricket matches every Sunday..."
                              class="w-full px-3.5 py-2 rounded-xl bg-slate-900 border border-white/10 text-white placeholder-slate-500 focus:border-amber-400 text-xs"></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-white/10">
                    <button type="button" @click="circleModal = false" class="px-4 py-2 rounded-xl text-slate-400 hover:text-white font-bold">Cancel</button>
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-black transition shadow">
                        Create Circle 🚀
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
