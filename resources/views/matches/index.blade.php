@extends('layouts.app')

@section('header')
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20 mb-1">
                ⚡ CricHeroes Match Arena
            </div>
            <h2 class="font-black text-2xl sm:text-3xl text-slate-900 dark:text-white leading-tight">My Cricket Matches</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Start local matches, score ball-by-ball, and broadcast live</p>
        </div>

        <button type="button"
                id="btn-open-create-match"
                onclick="window.dispatchEvent(new CustomEvent('open-create-match'))"
                class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-500 hover:to-rose-500 text-white font-extrabold text-sm shadow-md shadow-red-600/25 transition transform active:scale-95 cursor-pointer">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
            </svg>
            <span>Start A Match</span>
        </button>
    </div>
@endsection

@section('content')
<div class="py-6 sm:py-8 bg-slate-900 min-h-screen text-slate-100 font-sans"
     @open-create-match.window="createMatchModal = true"
     x-data="{
    createMatchModal: false,
    scoreModal: false,
    activeMatch: null,
    scoreInning: 1,
    scoreBattingTeam: '',
    scoreRuns: 0,
    scoreWickets: 0,
    scoreOvers: '0.0',
    scoreStatus: 'live',
    scoreResult: '',
    isSavingScore: false,
    toastMsg: '',

    // Match Squad Builder state
    matchTeam1Name: 'Royal Strikers',
    matchTeam2Name: 'Thunder Titans',
    activeSquadTab: 1, // 1 for team 1, 2 for team 2
    team1Squad: [],
    team2Squad: [],
    playerSearchQuery: '',
    isSearchingPlayer: false,
    searchedPlayer: null,
    searchNotFound: false,
    newPlayerName: '',
    newPlayerPhone: '',
    newPlayerRole: 'All-Rounder',
    isCaptainTag: false,
    isWkTag: false,

    openManualAdd(defaultName = '') {
        this.searchedPlayer = null;
        this.searchNotFound = true;
        this.newPlayerName = defaultName || this.playerSearchQuery.trim();
        this.newPlayerPhone = '';
        this.newPlayerRole = 'All-Rounder';
    },

    showToast(msg) {
        this.toastMsg = msg;
        setTimeout(() => { this.toastMsg = ''; }, 3000);
    },
    openScorer(match) {
        this.activeMatch = match;
        const currentScore = match.scores && match.scores.length > 0
            ? match.scores[match.scores.length - 1]
            : null;

        if (currentScore) {
            this.scoreInning = currentScore.inning_number;
            this.scoreBattingTeam = currentScore.batting_team;
            this.scoreRuns = currentScore.runs;
            this.scoreWickets = currentScore.wickets;
            this.scoreOvers = currentScore.overs || '0.0';
        } else {
            this.scoreInning = 1;
            this.scoreBattingTeam = match.team1_name;
            this.scoreRuns = 0;
            this.scoreWickets = 0;
            this.scoreOvers = '0.0';
        }
        this.scoreStatus = match.status;
        this.scoreResult = match.match_result || '';
        this.scoreModal = true;
    },
    addRuns(r) {
        this.scoreRuns = parseInt(this.scoreRuns || 0) + r;
    },
    addWicket() {
        if (this.scoreWickets < 10) {
            this.scoreWickets = parseInt(this.scoreWickets || 0) + 1;
        }
    },
    async saveQuickScore() {
        if (!this.activeMatch) return;
        this.isSavingScore = true;
        try {
            const res = await fetch('/matches/' + this.activeMatch.id + '/quick-score', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    inning_number: this.scoreInning,
                    batting_team: this.scoreBattingTeam,
                    runs: parseInt(this.scoreRuns),
                    wickets: parseInt(this.scoreWickets),
                    overs: this.scoreOvers,
                    status: this.scoreStatus,
                    match_result: this.scoreResult
                })
            });

            const data = await res.json();
            if (res.ok && data.status === 'success') {
                this.showToast('Score updated live! ⚡');
                setTimeout(() => { window.location.reload(); }, 600);
            } else {
                this.showToast(data.message || 'Error updating score');
            }
        } catch(e) {
            console.error(e);
            this.showToast('Failed to save score');
        } finally {
            this.isSavingScore = false;
        }
    },

    // WhatsApp / Phone Player Lookup
    async searchPlayerWhatsApp() {
        const query = this.playerSearchQuery.trim();
        if (!query) {
            this.showToast('Please enter a WhatsApp number or name');
            return;
        }

        this.isSearchingPlayer = true;
        this.searchedPlayer = null;
        this.searchNotFound = false;

        try {
            const res = await fetch('/api/players/lookup?query=' + encodeURIComponent(query));
            const data = await res.json();

            if (data.status === 'success' && data.found) {
                this.searchedPlayer = data.player;
                this.newPlayerName = data.player.name;
                this.newPlayerRole = data.player.role || 'All-Rounder';
            } else {
                this.searchNotFound = true;
                this.newPlayerName = isNaN(query) ? query : '';
            }
        } catch(e) {
            console.error(e);
            this.showToast('Error searching player');
        } finally {
            this.isSearchingPlayer = false;
        }
    },

    // Add Player to Current Active Team Squad
    addPlayerToSquad() {
        const name = (this.newPlayerName || (this.searchedPlayer ? this.searchedPlayer.name : '')).trim();
        if (!name) {
            this.showToast('Player name is required');
            return;
        }

        const phone = this.searchedPlayer ? this.searchedPlayer.phone : (this.newPlayerPhone.trim() || (!isNaN(this.playerSearchQuery.trim()) ? this.playerSearchQuery.trim() : null));

        const playerObj = {
            player_name: name,
            player_phone: phone,
            user_id: this.searchedPlayer ? this.searchedPlayer.user_id : null,
            player_role: this.searchedPlayer ? this.searchedPlayer.role : this.newPlayerRole,
            is_captain: this.isCaptainTag,
            is_wicketkeeper: this.isWkTag,
            stats: this.searchedPlayer ? this.searchedPlayer.stats : null,
            avatar: this.searchedPlayer ? this.searchedPlayer.avatar : null,
        };

        if (this.activeSquadTab === 1) {
            this.team1Squad.push(playerObj);
            this.showToast(name + ' added to ' + (this.matchTeam1Name || 'Team 1') + ' squad! 🏏');
        } else {
            this.team2Squad.push(playerObj);
            this.showToast(name + ' added to ' + (this.matchTeam2Name || 'Team 2') + ' squad! 🏏');
        }

        // Reset search
        this.searchedPlayer = null;
        this.searchNotFound = false;
        this.playerSearchQuery = '';
        this.newPlayerName = '';
        this.newPlayerPhone = '';
        this.isCaptainTag = false;
        this.isWkTag = false;
    },

    removePlayerFromSquad(teamNum, index) {
        if (teamNum === 1) {
            this.team1Squad.splice(index, 1);
        } else {
            this.team2Squad.splice(index, 1);
        }
    }
}">
    <style>
        .ch-card {
            background: #131b2e;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 18px;
            overflow: hidden;
            transition: all 0.2s ease-in-out;
        }
        .ch-card:hover {
            border-color: rgba(239, 68, 68, 0.35);
            transform: translateY(-2px);
            box-shadow: 0 12px 28px -10px rgba(0, 0, 0, 0.5);
        }
        .ch-pill-active {
            background: #dc2626;
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
        }
        [x-cloak] { display: none !important; }
    </style>

    <!-- Floating Toast -->
    <div x-cloak x-show="toastMsg" x-transition
         class="fixed bottom-6 right-6 z-50 bg-gradient-to-r from-emerald-600 to-teal-600 text-white px-5 py-3 rounded-2xl shadow-2xl font-bold text-sm flex items-center gap-2 border border-white/20">
        <span class="w-2 h-2 rounded-full bg-white animate-pulse"></span>
        <span x-text="toastMsg"></span>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        @if(session('status'))
            <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm font-bold flex items-center gap-2">
                <span>✓ {{ session('status') }}</span>
            </div>
        @endif

        <!-- ============================================== -->
        <!-- CRICHEROES STATS & PERFORMANCE BANNER -->
        <!-- ============================================== -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4">
            <div class="p-4 rounded-2xl bg-[#131b2e] border border-white/5 flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-blue-500/10 text-blue-400 flex items-center justify-center font-black text-xl shrink-0">
                    🏏
                </div>
                <div>
                    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Total Matches</div>
                    <div class="text-2xl font-black text-white leading-none mt-0.5">{{ $stats['total'] }}</div>
                </div>
            </div>

            <div class="p-4 rounded-2xl bg-[#131b2e] border border-white/5 flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-red-500/10 text-red-400 flex items-center justify-center font-black text-xl shrink-0 relative">
                    <span class="w-2.5 h-2.5 rounded-full bg-red-500 animate-ping absolute top-2 right-2"></span>
                    🔴
                </div>
                <div>
                    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Live Scoring</div>
                    <div class="text-2xl font-black text-red-400 leading-none mt-0.5">{{ $stats['live'] }}</div>
                </div>
            </div>

            <div class="p-4 rounded-2xl bg-[#131b2e] border border-white/5 flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center font-black text-xl shrink-0">
                    ⏳
                </div>
                <div>
                    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Upcoming</div>
                    <div class="text-2xl font-black text-amber-400 leading-none mt-0.5">{{ $stats['upcoming'] }}</div>
                </div>
            </div>

            <div class="p-4 rounded-2xl bg-[#131b2e] border border-white/5 flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center font-black text-xl shrink-0">
                    🏆
                </div>
                <div>
                    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Completed</div>
                    <div class="text-2xl font-black text-emerald-400 leading-none mt-0.5">{{ $stats['completed'] }}</div>
                </div>
            </div>
        </div>

        <!-- ============================================== -->
        <!-- SEARCH & NETWORK FILTER BAR -->
        <!-- ============================================== -->
        <div class="bg-[#131b2e] border border-white/10 rounded-2xl p-4 sm:p-5 shadow-xl space-y-4">
            <form method="GET" action="{{ route('matches.index') }}" class="flex flex-col sm:flex-row items-center gap-3">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <input type="hidden" name="scope" value="{{ $scope }}">

                <div class="relative flex-1 w-full">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </span>
                    <input type="text"
                           name="search"
                           value="{{ $search }}"
                           placeholder="Search by Tournament, Player Name, Ground / Venue, or Team..."
                           class="w-full bg-slate-950 border border-white/10 rounded-xl pl-10 pr-10 py-2.5 text-xs sm:text-sm text-white placeholder-slate-500 focus:ring-2 focus:ring-red-500 focus:outline-none">
                    @if(!empty($search))
                        <a href="{{ route('matches.index', ['tab' => $tab, 'scope' => $scope]) }}"
                           class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-white text-xs font-bold"
                           title="Clear search">
                            ✕
                        </a>
                    @endif
                </div>

                <div class="flex items-center gap-2 w-full sm:w-auto">
                    <button type="submit"
                            class="flex-1 sm:flex-initial px-5 py-2.5 rounded-xl bg-red-600 hover:bg-red-500 text-white font-extrabold text-xs shadow-md transition">
                        Search
                    </button>
                    @if(!empty($search))
                        <a href="{{ route('matches.index', ['tab' => $tab, 'scope' => $scope]) }}"
                           class="px-3 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-slate-300 text-xs font-bold transition">
                            Reset
                        </a>
                    @endif
                </div>
            </form>

            <div class="pt-3 border-t border-white/5 flex flex-wrap items-center justify-between gap-3 text-xs">
                @if(auth()->check())
                    <div class="flex items-center gap-2">
                        <span class="text-slate-400 font-bold uppercase tracking-wider text-[10px]">Filter Scope:</span>
                        <a href="{{ route('matches.index', ['tab' => $tab, 'scope' => 'all', 'search' => $search]) }}"
                           class="px-3 py-1.5 rounded-xl font-bold transition flex items-center gap-1.5 {{ $scope === 'all' ? 'bg-red-600 text-white shadow-sm' : 'bg-white/5 hover:bg-white/10 text-slate-300' }}">
                            <span>🌍 All Community Matches</span>
                            <span class="px-1.5 py-0.2 rounded-md bg-black/30 text-[10px]">{{ $stats['total'] }}</span>
                        </a>
                        <a href="{{ route('matches.index', ['tab' => $tab, 'scope' => 'my', 'search' => $search]) }}"
                           class="px-3 py-1.5 rounded-xl font-bold transition flex items-center gap-1.5 {{ $scope === 'my' ? 'bg-red-600 text-white shadow-sm' : 'bg-white/5 hover:bg-white/10 text-slate-300' }}">
                            <span>👥 My Matches & Network</span>
                            <span class="px-1.5 py-0.2 rounded-md bg-black/30 text-[10px]">{{ $stats['my_network_count'] ?? 0 }}</span>
                        </a>
                    </div>
                @else
                    <div class="text-[11px] text-slate-400">
                        <span>Showing public community matches. Anyone can view live scorecards!</span>
                    </div>
                @endif

                @if(!empty($search))
                    <div class="text-xs text-amber-300 font-medium flex items-center gap-2">
                        <span>Showing matches for "<strong>{{ $search }}</strong>" ({{ $matches->total() }} matches found)</span>
                        <a href="{{ route('matches.index', ['tab' => $tab, 'scope' => $scope]) }}" class="text-slate-400 hover:text-white underline text-[11px]">Clear</a>
                    </div>
                @endif
            </div>
        </div>

        <!-- ============================================== -->
        <!-- CRICHEROES TABS & QUICK START BUTTON -->
        <!-- ============================================== -->
        <div class="flex items-center gap-2 overflow-x-auto pb-2 scrollbar-none">
            <a href="{{ route('matches.index', ['tab' => 'all', 'scope' => $scope, 'search' => $search]) }}"
               class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shrink-0 {{ $tab === 'all' ? 'ch-pill-active' : 'bg-white/5 hover:bg-white/10 text-slate-300' }}">
                <span>All Status</span>
                <span class="px-1.5 py-0.2 rounded-md bg-black/20 text-[10px]">{{ $stats['total'] }}</span>
            </a>

            <a href="{{ route('matches.index', ['tab' => 'live', 'scope' => $scope, 'search' => $search]) }}"
               class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shrink-0 {{ $tab === 'live' ? 'ch-pill-active' : 'bg-white/5 hover:bg-white/10 text-slate-300' }}">
                <span class="w-2 h-2 rounded-full bg-red-400 animate-pulse"></span>
                <span>🔴 Live</span>
                <span class="px-1.5 py-0.2 rounded-md bg-black/20 text-[10px]">{{ $stats['live'] }}</span>
            </a>

            <a href="{{ route('matches.index', ['tab' => 'upcoming', 'scope' => $scope, 'search' => $search]) }}"
               class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shrink-0 {{ $tab === 'upcoming' ? 'ch-pill-active' : 'bg-white/5 hover:bg-white/10 text-slate-300' }}">
                <span>⏳ Upcoming</span>
                <span class="px-1.5 py-0.2 rounded-md bg-black/20 text-[10px]">{{ $stats['upcoming'] }}</span>
            </a>

            <a href="{{ route('matches.index', ['tab' => 'completed', 'scope' => $scope, 'search' => $search]) }}"
               class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shrink-0 {{ $tab === 'completed' ? 'ch-pill-active' : 'bg-white/5 hover:bg-white/10 text-slate-300' }}">
                <span>🏆 Completed</span>
                <span class="px-1.5 py-0.2 rounded-md bg-black/20 text-[10px]">{{ $stats['completed'] }}</span>
            </a>

            @if(auth()->check())
                <button type="button"
                        @click="createMatchModal = true"
                        class="ml-auto shrink-0 px-3.5 py-2 rounded-xl bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-500 hover:to-rose-500 text-white font-black text-xs flex items-center gap-1.5 shadow-md shadow-red-600/20 active:scale-95 transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>+ Start Match</span>
                </button>
            @else
                <a href="{{ route('login') }}"
                   class="ml-auto shrink-0 px-3.5 py-2 rounded-xl bg-white/10 hover:bg-white/20 text-white font-bold text-xs flex items-center gap-1.5 transition">
                    <span>Sign In to Host Match 🏏</span>
                </a>
            @endif
        </div>

        <!-- ============================================== -->
        <!-- MATCHES LIST (CricHeroes Signature Cards) -->
        <!-- ============================================== -->
        @if($matches->count() > 0)
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">
                @foreach($matches as $match)
                    @php
                        $score1 = $match->getScoreForTeam($match->team1_name);
                        $score2 = $match->getScoreForTeam($match->team2_name);

                        $team1Initial = strtoupper(substr($match->team1_name, 0, 2));
                        $team2Initial = strtoupper(substr($match->team2_name, 0, 2));

                        $isOwner = auth()->check() && (auth()->id() === $match->user_id);
                        $isPlayer = auth()->check() && in_array($match->id, $userPlayingIds ?? []);
                        $isNetwork = auth()->check() && in_array($match->id, $followingMatchIds ?? []);
                    @endphp

                    <div class="ch-card flex flex-col justify-between">

                        <!-- 1. Match Header Bar (Tournament / Venue / Status) -->
                        <div class="px-5 py-3 border-b border-white/5 bg-black/20 flex flex-wrap items-center justify-between gap-2">
                            <div class="min-w-0 pr-2">
                                <div class="text-[11px] font-black text-rose-400 uppercase tracking-wider truncate flex items-center gap-2">
                                    <span class="truncate">{{ $match->tournament_name ?? 'Friendly Match' }}</span>
                                    @if($isOwner)
                                        <span class="px-2 py-0.2 rounded-full text-[9px] font-black bg-blue-600/30 text-blue-300 border border-blue-500/40 shrink-0">👑 You Hosted</span>
                                    @elseif($isPlayer)
                                        <span class="px-2 py-0.2 rounded-full text-[9px] font-black bg-amber-600/30 text-amber-300 border border-amber-500/40 shrink-0">🏏 In Squad</span>
                                    @elseif($isNetwork)
                                        <span class="px-2 py-0.2 rounded-full text-[9px] font-black bg-purple-600/30 text-purple-300 border border-purple-500/40 shrink-0">👥 Network</span>
                                    @else
                                        <span class="px-2 py-0.2 rounded-full text-[9px] font-medium bg-white/5 text-slate-400 border border-white/10 shrink-0">🌍 Community</span>
                                    @endif
                                </div>
                                <div class="text-[11px] text-slate-400 flex flex-wrap items-center gap-1.5 mt-0.5">
                                    <span>{{ $match->total_overs }} Overs • {{ $match->ball_type }}</span>
                                    <span>•</span>
                                    <span>{{ $match->match_date->format('d M Y') }}</span>
                                    @if($match->venue)
                                        <span>•</span>
                                        <span class="truncate">📍 {{ $match->venue }}</span>
                                    @endif
                                </div>
                            </div>

                            <!-- Status Badge -->
                            <div class="shrink-0 flex items-center gap-1.5">
                                @if($match->stream_url)
                                    <a href="{{ route('matches.broadcast', $match) }}"
                                       class="px-2 py-0.5 rounded-full text-[10px] font-black bg-rose-600/30 text-rose-300 border border-rose-500/40 flex items-center gap-1 hover:bg-rose-600 hover:text-white transition"
                                       title="Watch Live Stream with Score Overlay">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-400 animate-ping"></span>
                                        <span>🔴 Stream</span>
                                    </a>
                                @endif
                                @if($match->status === 'live')
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-black bg-red-600/20 text-red-400 border border-red-500/30 flex items-center gap-1 animate-pulse">
                                        <span class="w-1.5 h-1.5 rounded-full bg-red-400"></span>
                                        LIVE
                                    </span>
                                @elseif($match->status === 'completed')
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-600/20 text-emerald-400 border border-emerald-500/30">
                                        COMPLETED
                                    </span>
                                @elseif($match->status === 'scheduled')
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-600/20 text-amber-400 border border-amber-500/30">
                                        UPCOMING
                                    </span>
                                @else
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-700 text-slate-300">
                                        {{ ucfirst($match->status) }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <!-- 2. Teams & Scorecard Body (CricHeroes Style) -->
                        <div class="p-5 space-y-4 flex-1">

                            <!-- Team 1 Row -->
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-blue-600 to-indigo-600 flex items-center justify-center font-black text-xs text-white shadow-md shrink-0">
                                        {{ $team1Initial }}
                                    </div>
                                    <div class="min-w-0">
                                        <div class="font-extrabold text-white text-sm sm:text-base truncate">
                                            {{ $match->team1_name }}
                                        </div>
                                    </div>
                                </div>

                                <div class="text-right shrink-0">
                                    @if($score1)
                                        <div class="font-black text-white text-base sm:text-lg">
                                            {{ $score1->runs }}/{{ $score1->wickets }}
                                        </div>
                                        <div class="text-[11px] font-mono text-slate-400">
                                            ({{ $score1->overs ?? '0.0' }} ov)
                                        </div>
                                    @else
                                        <div class="text-xs font-semibold text-slate-500 italic">
                                            Yet to bat
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <!-- Team 2 Row -->
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-rose-600 to-amber-600 flex items-center justify-center font-black text-xs text-white shadow-md shrink-0">
                                        {{ $team2Initial }}
                                    </div>
                                    <div class="min-w-0">
                                        <div class="font-extrabold text-white text-sm sm:text-base truncate">
                                            {{ $match->team2_name }}
                                        </div>
                                    </div>
                                </div>

                                <div class="text-right shrink-0">
                                    @if($score2)
                                        <div class="font-black text-white text-base sm:text-lg">
                                            {{ $score2->runs }}/{{ $score2->wickets }}
                                        </div>
                                        <div class="text-[11px] font-mono text-slate-400">
                                            ({{ $score2->overs ?? '0.0' }} ov)
                                        </div>
                                    @else
                                        <div class="text-xs font-semibold text-slate-500 italic">
                                            Yet to bat
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <!-- Result or Match Situation Strip -->
                            <div class="pt-3 border-t border-white/5 text-xs">
                                @if(!empty($match->match_result))
                                    <div class="font-bold text-amber-300 flex items-center gap-1.5">
                                        <span>📢</span>
                                        <span class="truncate">{{ $match->match_result }}</span>
                                    </div>
                                @elseif(!empty($match->toss_decision))
                                    <div class="text-slate-300 flex items-center gap-1.5">
                                        <span>🪙</span>
                                        <span class="truncate">{{ $match->toss_decision }}</span>
                                    </div>
                                @else
                                    <div class="text-slate-400 flex items-center gap-1.5">
                                        <span>📍</span>
                                        <span class="truncate">{{ $match->venue ?? 'Ground venue to be decided' }}</span>
                                    </div>
                                @endif

                                @if(!empty($match->player_of_match))
                                    <div class="mt-1 text-[11px] font-bold text-emerald-400 flex items-center gap-1">
                                        <span>🏆 Player of Match:</span>
                                        <span>{{ $match->player_of_match }}</span>
                                    </div>
                                @endif
                            </div>

                            @if($match->user)
                                <div class="pt-2 text-[11px] text-slate-400 flex items-center justify-between border-t border-white/5">
                                    <span class="flex items-center gap-1.5 truncate">
                                        <span>Organized by:</span>
                                        <a href="{{ route('profile.user', $match->user_id) }}" class="text-blue-400 hover:underline font-bold truncate">
                                            {{ $match->user->name }}
                                        </a>
                                    </span>
                                    <span class="text-slate-500 font-mono text-[10px] shrink-0">#{{ $match->id }}</span>
                                </div>
                            @endif

                        </div>

                        <!-- 3. Bottom Actions Strip (Permissions-Aware) -->
                        <div class="px-5 py-3 border-t border-white/5 bg-black/30 flex flex-wrap items-center justify-between gap-2 text-xs font-bold">
                            @if($isOwner)
                                {{-- 👑 MATCH OWNER ACTIONS --}}
                                <div class="flex flex-wrap items-center gap-2">
                                    <button type="button"
                                            @click="openScorer({{ json_encode($match) }})"
                                            class="px-3 py-1.5 rounded-lg bg-red-600 hover:bg-red-500 text-white transition flex items-center gap-1.5 shadow-sm">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                        <span>Score Match</span>
                                    </button>

                                    <a href="{{ route('matches.show', $match) }}"
                                       class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/20 text-white transition">
                                        Scorecard & Lineups
                                    </a>

                                    <a href="{{ route('matches.broadcast', $match) }}"
                                       class="px-2.5 py-1.5 rounded-lg bg-red-600/20 hover:bg-red-600/30 border border-red-500/30 text-red-300 hover:text-white transition flex items-center gap-1 shadow-sm"
                                       title="Open In-App Camera Broadcast with Live Score Overlay">
                                        <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse"></span>
                                        <span>Go Live 🎥</span>
                                    </a>

                                    <form action="{{ route('matches.rematch', $match) }}" method="POST" onsubmit="return confirm('Start a fresh Rematch with identical squads? 🏏');" class="inline">
                                        @csrf
                                        <button type="submit"
                                                class="px-3 py-1.5 rounded-lg bg-emerald-600/20 hover:bg-emerald-600/30 border border-emerald-500/40 text-emerald-300 hover:text-white transition flex items-center gap-1.5 shadow-sm"
                                                title="Start a fresh Rematch with identical squads without selecting players again">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                            </svg>
                                            <span>Rematch 🔁</span>
                                        </button>
                                    </form>
                                </div>

                                <div class="flex items-center gap-2 text-slate-400">
                                    <a href="{{ route('matches.edit', $match) }}" class="p-1.5 hover:text-white hover:bg-white/10 rounded-lg transition" title="Edit Match Settings">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                    </a>

                                    <form action="{{ route('matches.destroy', $match) }}" method="POST" onsubmit="return confirm('Delete this match permanently?');" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-1.5 hover:text-red-400 hover:bg-red-500/10 rounded-lg transition" title="Delete Match">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            @else
                                {{-- 👥 SPECTATOR / COMMUNITY VIEW (Anyone can watch scores, nobody can tamper!) --}}
                                <div class="flex flex-wrap items-center justify-between w-full gap-2">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('matches.show', $match) }}"
                                           class="px-4 py-2 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-extrabold text-xs transition flex items-center gap-1.5 shadow-md shadow-emerald-600/20">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                            </svg>
                                            <span>View Live Scorecard 📊</span>
                                        </a>

                                        <a href="{{ route('matches.broadcast', $match) }}"
                                           class="px-3 py-2 rounded-xl bg-white/10 hover:bg-white/20 text-white text-xs font-bold transition flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-red-400 animate-pulse"></span>
                                            <span>Watch Broadcast 📺</span>
                                        </a>
                                    </div>

                                    <div class="text-[11px] text-slate-400 flex items-center gap-1 font-normal">
                                        <span>👀 Real-Time Scorecard</span>
                                    </div>
                                </div>
                            @endif
                        </div>

                    </div>
                @endforeach
            </div>

            <div class="mt-6">
                {{ $matches->links() }}
            </div>
        @else
            <!-- Empty State -->
            <div class="bg-[#131b2e] border border-white/5 rounded-3xl p-10 sm:p-16 text-center max-w-lg mx-auto">
                <div class="w-20 h-20 rounded-full bg-red-600/10 border-2 border-dashed border-red-500/30 flex items-center justify-center text-4xl mx-auto mb-4">
                    🏏
                </div>
                @if(!empty($search))
                    <h3 class="text-xl font-black text-white">No Matches Found</h3>
                    <p class="text-slate-400 text-xs sm:text-sm mt-1.5 leading-relaxed">
                        No matches matched your search "<strong>{{ $search }}</strong>". Try searching for a different tournament, player, ground, or team.
                    </p>
                    <a href="{{ route('matches.index', ['tab' => $tab, 'scope' => $scope]) }}"
                       class="mt-6 inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-white font-bold text-xs transition">
                        Clear Search Filter ✕
                    </a>
                @else
                    <h3 class="text-xl font-black text-white">No Matches in this Category</h3>
                    <p class="text-slate-400 text-xs sm:text-sm mt-1.5 leading-relaxed">
                        Start a local gully or tournament match right now with custom overs, ball type, and WhatsApp squad additions.
                    </p>
                    @if(auth()->check())
                        <button @click="createMatchModal = true"
                                class="mt-6 inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-500 hover:to-rose-500 text-white font-extrabold text-sm shadow-lg shadow-red-600/30 transition">
                            + Start A Match
                        </button>
                    @else
                        <a href="{{ route('login') }}"
                           class="mt-6 inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-500 hover:to-rose-500 text-white font-extrabold text-sm shadow-lg shadow-red-600/30 transition">
                            Sign In to Host A Match
                        </a>
                    @endif
                @endif
            </div>
        @endif

    </div>

    <!-- ============================================== -->
    <!-- CRICHEROES QUICK SCORER MODAL -->
    <!-- ============================================== -->
    <div x-cloak x-show="scoreModal" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-black/80 backdrop-blur-md"
         @keydown.escape.window="scoreModal = false">
        <div class="relative bg-slate-900 border border-white/10 rounded-2xl max-w-md w-full p-6 shadow-2xl"
             @click.away="scoreModal = false">

            <div class="flex items-center justify-between pb-4 border-b border-white/10 mb-4">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-red-400">⚡ LIVE CRICHEROES SCORER</span>
                    <h3 class="text-lg font-black text-white" x-text="activeMatch ? activeMatch.team1_name + ' vs ' + activeMatch.team2_name : 'Score Match'"></h3>
                </div>
                <button @click="scoreModal = false" class="text-slate-400 hover:text-white">✕</button>
            </div>

            <!-- Inning & Batting Team Selector -->
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-2">
                    <button type="button"
                            @click="scoreInning = 1; scoreBattingTeam = activeMatch.team1_name"
                            :class="scoreInning === 1 ? 'bg-red-600 text-white font-bold' : 'bg-white/5 text-slate-300'"
                            class="py-2 rounded-xl text-xs transition border border-white/10">
                        Innings 1 (<span x-text="activeMatch ? activeMatch.team1_name : ''"></span>)
                    </button>
                    <button type="button"
                            @click="scoreInning = 2; scoreBattingTeam = activeMatch.team2_name"
                            :class="scoreInning === 2 ? 'bg-red-600 text-white font-bold' : 'bg-white/5 text-slate-300'"
                            class="py-2 rounded-xl text-xs transition border border-white/10">
                        Innings 2 (<span x-text="activeMatch ? activeMatch.team2_name : ''"></span>)
                    </button>
                </div>

                <!-- Live Scoreboard Display -->
                <div class="p-4 rounded-xl bg-black/40 border border-white/10 text-center">
                    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                        Batting: <span class="text-white" x-text="scoreBattingTeam"></span>
                    </div>
                    <div class="text-4xl font-black text-white my-2 font-mono">
                        <span x-text="scoreRuns">0</span>/<span x-text="scoreWickets">0</span>
                    </div>
                    <div class="text-xs font-mono text-slate-400">
                        Overs: <span class="text-amber-400 font-bold" x-text="scoreOvers">0.0</span>
                    </div>
                </div>

                <!-- Fast Run Buttons (+1, +2, +4, +6, Wicket) -->
                <div>
                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Fast Score Buttons</div>
                    <div class="grid grid-cols-5 gap-2 text-sm font-extrabold">
                        <button type="button" @click="addRuns(1)" class="py-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-white active:scale-95 transition">+1</button>
                        <button type="button" @click="addRuns(2)" class="py-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-white active:scale-95 transition">+2</button>
                        <button type="button" @click="addRuns(4)" class="py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white active:scale-95 transition">4️⃣</button>
                        <button type="button" @click="addRuns(6)" class="py-2.5 rounded-xl bg-purple-600 hover:bg-purple-500 text-white active:scale-95 transition">6️⃣</button>
                        <button type="button" @click="addWicket()" class="py-2.5 rounded-xl bg-rose-600 hover:bg-rose-500 text-white active:scale-95 transition">W ☝️</button>
                    </div>
                </div>

                <!-- Detailed Manual Inputs -->
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 mb-1">Runs</label>
                        <input type="number" x-model="scoreRuns" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-sm text-white font-mono font-bold">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 mb-1">Wickets</label>
                        <input type="number" x-model="scoreWickets" min="0" max="10" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-sm text-white font-mono font-bold">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 mb-1">Overs (e.g. 14.2)</label>
                        <input type="text" x-model="scoreOvers" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-sm text-white font-mono font-bold">
                    </div>
                </div>

                <!-- Match Status & Situation -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-400 mb-1">Match State</label>
                    <select x-model="scoreStatus" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-xs text-white">
                        <option value="live">🔴 Live In-Progress</option>
                        <option value="completed">🏆 Completed (Finished)</option>
                        <option value="scheduled">⏳ Scheduled</option>
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-400 mb-1">Situation / Result</label>
                    <input type="text" x-model="scoreResult" placeholder="e.g. Royal Strikers need 12 off 8 balls"
                           class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-xs text-white">
                </div>

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-white/10">
                    <button type="button" @click="scoreModal = false" class="px-4 py-2 text-xs font-bold text-slate-400 hover:text-white">
                        Cancel
                    </button>
                    <button type="button" @click="saveQuickScore()"
                            class="px-6 py-2.5 rounded-xl bg-red-600 hover:bg-red-500 text-white font-extrabold text-xs shadow-lg transition">
                        <span x-show="!isSavingScore">Update Score ⚡</span>
                        <span x-show="isSavingScore">Saving...</span>
                    </button>
                </div>
            </div>

        </div>
    </div>

    <!-- ============================================================== -->
    <!-- START A MATCH MODAL (With CricHeroes WhatsApp Player Search) -->
    <!-- ============================================================== -->
    <div x-cloak x-show="createMatchModal" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-3 sm:p-4 bg-black/85 backdrop-blur-md"
         @keydown.escape.window="createMatchModal = false">
        <div class="relative bg-slate-900 border border-white/10 rounded-2xl max-w-2xl w-full p-5 sm:p-7 shadow-2xl my-8 max-h-[90vh] overflow-y-auto"
             @click.away="createMatchModal = false">

            <div class="flex items-center justify-between pb-3 border-b border-white/10 mb-4">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-red-400">CRICHEROES TOURNAMENT CREATOR</span>
                    <h3 class="text-lg font-black text-white">Start Match & Add Squad (XI)</h3>
                </div>
                <button @click="createMatchModal = false" class="text-slate-400 hover:text-white p-1 rounded-lg">✕</button>
            </div>

            <form action="{{ route('matches.store') }}" method="POST" class="space-y-4">
                @csrf

                <!-- Hidden inputs for Squad Players -->
                <input type="hidden" name="team1_players" :value="JSON.stringify(team1Squad)">
                <input type="hidden" name="team2_players" :value="JSON.stringify(team2Squad)">

                <!-- Tournament Name -->
                <div>
                    <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1">Tournament / Trophy Name</label>
                    <input type="text" name="tournament_name" placeholder="e.g. Gully Premier League 2026" required
                           class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2 text-sm text-white focus:ring-2 focus:ring-red-500 focus:outline-none">
                </div>

                <!-- Teams Row -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1">Team 1 Name</label>
                        <input type="text" name="team1_name" x-model="matchTeam1Name" placeholder="e.g. Royal Strikers" required
                               class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2 text-sm text-white focus:ring-2 focus:ring-red-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1">Team 2 Name</label>
                        <input type="text" name="team2_name" x-model="matchTeam2Name" placeholder="e.g. Thunder Titans" required
                               class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2 text-sm text-white focus:ring-2 focus:ring-red-500 focus:outline-none">
                    </div>
                </div>

                <!-- Overs & Ball Type -->
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1">Overs</label>
                        <select name="total_overs" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-xs text-white">
                            <option value="6">6 Overs</option>
                            <option value="10">10 Overs</option>
                            <option value="12">12 Overs</option>
                            <option value="15">15 Overs</option>
                            <option value="20" selected>20 Overs (T20)</option>
                            <option value="50">50 Overs (ODI)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1">Ball Type</label>
                        <select name="ball_type" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-xs text-white">
                            <option value="Tennis Ball" selected>🎾 Tennis Ball</option>
                            <option value="Leather Ball">🏏 Leather Ball</option>
                            <option value="Tape Ball">⚡ Tape Ball</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1">Status</label>
                        <select name="status" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-xs text-white">
                            <option value="live">🔴 Live Now</option>
                            <option value="scheduled" selected>⏳ Scheduled</option>
                            <option value="completed">🏆 Completed</option>
                        </select>
                    </div>
                </div>

                <!-- Venue & Date -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1">Venue / Ground</label>
                        <input type="text" name="venue" placeholder="e.g. Shivaji Park Ground"
                               class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2 text-sm text-white focus:ring-2 focus:ring-red-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1">Match Date</label>
                        <input type="date" name="match_date" value="{{ date('Y-m-d') }}" required
                               class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2 text-sm text-white focus:ring-2 focus:ring-red-500 focus:outline-none">
                    </div>
                </div>

                <!-- Live Stream Link (YouTube / Facebook) -->
                <div>
                    <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1 flex items-center justify-between">
                        <span class="flex items-center gap-1.5 text-rose-400">
                            <span>🔴 Live Stream URL (Optional)</span>
                        </span>
                        <span class="text-[10px] text-slate-400 font-normal">YouTube / Facebook Live Link</span>
                    </label>
                    <input type="url" name="stream_url" placeholder="https://www.youtube.com/watch?v=... or https://fb.watch/..."
                           class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2 text-sm text-white placeholder-slate-500 focus:ring-2 focus:ring-red-500 focus:outline-none">
                </div>

                <!-- ============================================================== -->
                <!-- CRICHEROES SQUAD BUILDER & WHATSAPP LOOKUP SECTION -->
                <!-- ============================================================== -->
                <div class="pt-4 border-t border-white/10 space-y-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="text-xs font-black uppercase text-emerald-400 flex items-center gap-1.5">
                                <span>📱 ADD PLAYERS VIA WHATSAPP / PHONE</span>
                            </span>
                            <p class="text-[11px] text-slate-400">Enter WhatsApp number to auto-fetch player past records & stats.</p>
                        </div>
                    </div>

                    <!-- Team Squad Switcher Tabs -->
                    <div class="flex items-center gap-2 p-1 bg-black/40 rounded-xl border border-white/5">
                        <button type="button"
                                @click="activeSquadTab = 1"
                                :class="activeSquadTab === 1 ? 'bg-red-600 text-white font-bold' : 'text-slate-400 hover:text-white'"
                                class="flex-1 py-1.5 rounded-lg text-xs transition flex items-center justify-center gap-1.5">
                            <span x-text="matchTeam1Name || 'Team 1'"></span>
                            <span class="px-1.5 py-0.2 rounded-full bg-black/30 text-[10px]" x-text="team1Squad.length"></span>
                        </button>
                        <button type="button"
                                @click="activeSquadTab = 2"
                                :class="activeSquadTab === 2 ? 'bg-red-600 text-white font-bold' : 'text-slate-400 hover:text-white'"
                                class="flex-1 py-1.5 rounded-lg text-xs transition flex items-center justify-center gap-1.5">
                            <span x-text="matchTeam2Name || 'Team 2'"></span>
                            <span class="px-1.5 py-0.2 rounded-full bg-black/30 text-[10px]" x-text="team2Squad.length"></span>
                        </button>
                    </div>

                    <!-- WhatsApp / Phone Search Bar -->
                    <div class="flex items-center gap-2">
                        <div class="relative flex-1">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-emerald-400 text-sm">
                                💬
                            </span>
                            <input type="text" x-model="playerSearchQuery"
                                   @keydown.enter.prevent="searchPlayerWhatsApp()"
                                   placeholder="Enter WhatsApp/Phone (e.g. 9876543210, 9812345678) or Name"
                                   class="w-full bg-slate-950 border border-white/10 rounded-xl pl-9 pr-3 py-2 text-xs text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                        </div>
                        <button type="button" @click="searchPlayerWhatsApp()"
                                class="px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shrink-0 transition flex items-center gap-1.5 shadow-md">
                            <span x-show="!isSearchingPlayer">Fetch Record 🔍</span>
                            <span x-show="isSearchingPlayer" class="animate-spin text-xs">⏳</span>
                        </button>
                        <button type="button" @click="openManualAdd(playerSearchQuery)"
                                title="Directly add a new player without database lookup"
                                class="px-3 py-2 rounded-xl bg-white/10 hover:bg-white/20 text-slate-300 hover:text-white font-bold text-xs shrink-0 transition flex items-center gap-1 border border-white/10">
                            <span>+ Manual Add</span>
                        </button>
                    </div>

                    <!-- 1. Player Found Card with Past Stats -->
                    <template x-if="searchedPlayer">
                        <div class="p-3.5 rounded-xl bg-gradient-to-r from-slate-950 to-indigo-950/70 border border-emerald-500/30 animate-in fade-in space-y-2.5">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <img :src="searchedPlayer.avatar" class="w-10 h-10 rounded-full object-cover border border-emerald-400">
                                    <div>
                                        <div class="font-extrabold text-white text-xs flex items-center gap-1">
                                            <span x-text="searchedPlayer.name"></span>
                                            <span class="text-blue-400 text-[10px]">✓</span>
                                        </div>
                                        <div class="text-[11px] text-emerald-400 font-mono" x-text="searchedPlayer.phone"></div>
                                    </div>
                                </div>
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30" x-text="searchedPlayer.role"></span>
                            </div>

                            <!-- Past Cricket Career Stats (CricHeroes Record) -->
                            <div class="grid grid-cols-4 gap-1.5 p-2 rounded-lg bg-black/40 text-[10px] text-center border border-white/5">
                                <div>
                                    <div class="text-slate-400 uppercase font-bold text-[9px]">Matches</div>
                                    <div class="font-black text-white text-xs" x-text="searchedPlayer.stats.matches"></div>
                                </div>
                                <div>
                                    <div class="text-slate-400 uppercase font-bold text-[9px]">Runs (HS)</div>
                                    <div class="font-black text-amber-300 text-xs" x-text="searchedPlayer.stats.runs + ' (' + searchedPlayer.stats.highest_score + ')'"></div>
                                </div>
                                <div>
                                    <div class="text-slate-400 uppercase font-bold text-[9px]">Avg / SR</div>
                                    <div class="font-black text-white text-xs" x-text="searchedPlayer.stats.batting_avg + ' / ' + searchedPlayer.stats.strike_rate"></div>
                                </div>
                                <div>
                                    <div class="text-slate-400 uppercase font-bold text-[9px]">Wickets (BBI)</div>
                                    <div class="font-black text-rose-400 text-xs" x-text="searchedPlayer.stats.wickets + ' (' + searchedPlayer.stats.best_bowling + ')'"></div>
                                </div>
                            </div>

                            <!-- Captain / WK checkbox and Add button -->
                            <div class="flex items-center justify-between pt-1">
                                <div class="flex items-center gap-3 text-xs text-slate-300">
                                    <label class="flex items-center gap-1 cursor-pointer">
                                        <input type="checkbox" x-model="isCaptainTag" class="rounded bg-slate-900 border-white/20 text-red-600 focus:ring-0">
                                        <span>Captain (C)</span>
                                    </label>
                                    <label class="flex items-center gap-1 cursor-pointer">
                                        <input type="checkbox" x-model="isWkTag" class="rounded bg-slate-900 border-white/20 text-amber-500 focus:ring-0">
                                        <span>Wicketkeeper (WK)</span>
                                    </label>
                                </div>
                                <button type="button" @click="addPlayerToSquad()"
                                        class="px-3.5 py-1.5 rounded-lg bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-500 text-white text-xs font-black shadow-md transition">
                                    + Add to Playing XI
                                </button>
                            </div>

                            <!-- Option to add a fresh player instead of matched profile -->
                            <div class="pt-2 border-t border-white/10 flex items-center justify-between text-xs">
                                <span class="text-slate-400 text-[11px]">
                                    Want to add a different "<span class="text-white font-bold" x-text="playerSearchQuery || searchedPlayer.name"></span>"?
                                </span>
                                <button type="button"
                                        @click="openManualAdd(playerSearchQuery || searchedPlayer.name)"
                                        class="text-amber-400 hover:text-amber-300 font-bold underline text-[11px] flex items-center gap-1 transition">
                                    <span>➕ Create new player instead</span>
                                </button>
                            </div>
                        </div>
                    </template>

                    <!-- 2. Player Not Found in DB / Quick Manual Add Card -->
                    <template x-if="searchNotFound">
                        <div class="p-3.5 rounded-xl bg-white/5 border border-amber-500/30 animate-in fade-in space-y-2 text-xs">
                            <div class="flex items-center justify-between text-amber-300 font-bold">
                                <div class="flex items-center gap-1.5">
                                    <span>⚡</span>
                                    <span>New Player: Enter details to add to this squad:</span>
                                </div>
                                <button type="button" @click="searchNotFound = false" class="text-slate-400 hover:text-white text-xs">✕ Cancel</button>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                <input type="text" x-model="newPlayerName" placeholder="Player Full Name *"
                                       class="bg-slate-950 border border-white/10 rounded-lg px-2.5 py-1.5 text-xs text-white focus:ring-2 focus:ring-red-500 focus:outline-none">
                                <input type="text" x-model="newPlayerPhone" placeholder="Phone/WhatsApp (Optional)"
                                       class="bg-slate-950 border border-white/10 rounded-lg px-2.5 py-1.5 text-xs text-white focus:ring-2 focus:ring-red-500 focus:outline-none">
                                <select x-model="newPlayerRole" class="bg-slate-950 border border-white/10 rounded-lg px-2.5 py-1.5 text-xs text-white">
                                    <option value="Top-Order Batter">Top-Order Batter</option>
                                    <option value="Middle-Order Batter">Middle-Order Batter</option>
                                    <option value="All-Rounder" selected>All-Rounder</option>
                                    <option value="Fast Bowler">Fast Bowler</option>
                                    <option value="Spin Bowler">Spin Bowler</option>
                                    <option value="Wicketkeeper Batter">Wicketkeeper Batter</option>
                                </select>
                            </div>
                            <div class="flex items-center justify-between pt-1">
                                <div class="flex items-center gap-3 text-slate-300">
                                    <label class="flex items-center gap-1 cursor-pointer">
                                        <input type="checkbox" x-model="isCaptainTag" class="rounded bg-slate-900 border-white/20 text-red-600">
                                        <span>Captain (C)</span>
                                    </label>
                                    <label class="flex items-center gap-1 cursor-pointer">
                                        <input type="checkbox" x-model="isWkTag" class="rounded bg-slate-900 border-white/20 text-amber-500">
                                        <span>WK</span>
                                    </label>
                                </div>
                                <button type="button" @click="addPlayerToSquad()"
                                        class="px-3.5 py-1.5 rounded-lg bg-red-600 hover:bg-red-500 text-white font-bold text-xs shadow-md transition">
                                    + Add to Playing XI
                                </button>
                            </div>
                        </div>
                                <div class="flex items-center gap-3 text-slate-300">
                                    <label class="flex items-center gap-1 cursor-pointer">
                                        <input type="checkbox" x-model="isCaptainTag" class="rounded bg-slate-900 border-white/20 text-red-600">
                                        <span>Captain (C)</span>
                                    </label>
                                    <label class="flex items-center gap-1 cursor-pointer">
                                        <input type="checkbox" x-model="isWkTag" class="rounded bg-slate-900 border-white/20 text-amber-500">
                                        <span>WK</span>
                                    </label>
                                </div>
                                <button type="button" @click="addPlayerToSquad()"
                                        class="px-3 py-1 rounded-lg bg-red-600 hover:bg-red-500 text-white font-bold text-xs">
                                    + Add Player
                                </button>
                            </div>
                        </div>
                    </template>

                    <!-- 3. Current Squad List Display -->
                    <div class="space-y-2">
                        <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider flex items-center justify-between">
                            <span>Playing Squad: <span class="text-white" x-text="activeSquadTab === 1 ? (matchTeam1Name || 'Team 1') : (matchTeam2Name || 'Team 2')"></span></span>
                            <span class="text-slate-400" x-text="(activeSquadTab === 1 ? team1Squad.length : team2Squad.length) + ' players'"></span>
                        </div>

                        <!-- Team 1 Squad Chips -->
                        <div x-show="activeSquadTab === 1" class="flex flex-wrap gap-2">
                            <template x-if="team1Squad.length === 0">
                                <div class="text-[11px] text-slate-500 italic py-1">No players added to this team yet. Search WhatsApp above to add.</div>
                            </template>
                            <template x-for="(p, index) in team1Squad" :key="index">
                                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-blue-600/20 border border-blue-500/30 text-xs text-white">
                                    <span class="font-bold" x-text="p.player_name"></span>
                                    <template x-if="p.is_captain"><span class="px-1 rounded bg-red-600 text-[9px] font-black">C</span></template>
                                    <template x-if="p.is_wicketkeeper"><span class="px-1 rounded bg-amber-600 text-[9px] font-black">WK</span></template>
                                    <button type="button" @click="removePlayerFromSquad(1, index)" class="text-slate-400 hover:text-red-400 ml-1">✕</button>
                                </div>
                            </template>
                        </div>

                        <!-- Team 2 Squad Chips -->
                        <div x-show="activeSquadTab === 2" class="flex flex-wrap gap-2">
                            <template x-if="team2Squad.length === 0">
                                <div class="text-[11px] text-slate-500 italic py-1">No players added to this team yet. Search WhatsApp above to add.</div>
                            </template>
                            <template x-for="(p, index) in team2Squad" :key="index">
                                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-rose-600/20 border border-rose-500/30 text-xs text-white">
                                    <span class="font-bold" x-text="p.player_name"></span>
                                    <template x-if="p.is_captain"><span class="px-1 rounded bg-red-600 text-[9px] font-black">C</span></template>
                                    <template x-if="p.is_wicketkeeper"><span class="px-1 rounded bg-amber-600 text-[9px] font-black">WK</span></template>
                                    <button type="button" @click="removePlayerFromSquad(2, index)" class="text-slate-400 hover:text-red-400 ml-1">✕</button>
                                </div>
                            </template>
                        </div>
                    </div>

                </div>

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-white/10">
                    <button type="button" @click="createMatchModal = false" class="px-4 py-2 text-xs font-bold text-slate-400 hover:text-white">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-500 hover:to-rose-500 text-white font-extrabold text-xs shadow-lg shadow-red-600/30 transition">
                        Create & Start Match 🏏
                    </button>
                </div>
            </form>

        </div>
    </div>

</div>
@endsection
