@extends('layouts.app')

@section('title', ($match->team1_name ?? 'Team 1') . ' vs ' . ($match->team2_name ?? 'Team 2') . ' - Live Score | ' . config('app.name', 'LiveCricket'))

@section('header')
    @php
        $isOwner = auth()->check() && (auth()->id() === $match->user_id);
    @endphp
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('matches.index') }}" class="p-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-white/10 dark:hover:bg-white/15 text-slate-700 dark:text-slate-200 transition" title="Back to My Matches">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>
            <div>
                <span class="text-[11px] font-black uppercase text-red-600 dark:text-rose-400 tracking-wider">CRICHEROES MATCH ARENA</span>
                <h2 class="font-black text-xl sm:text-2xl text-slate-900 dark:text-white leading-tight">
                    {{ $match->team1_name }} vs {{ $match->team2_name }}
                </h2>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('matches.broadcast', $match) }}" class="px-3.5 py-1.5 rounded-lg bg-red-600 hover:bg-red-500 text-white text-xs font-black transition flex items-center gap-1.5 shadow-md shadow-red-600/30">
                <span class="w-2 h-2 rounded-full bg-white animate-pulse"></span>
                <span>{{ $isOwner ? 'Go Live 🎥' : 'Watch Broadcast 📺' }}</span>
            </a>
            @if($isOwner)
                <form method="POST" action="{{ route('matches.rematch', $match) }}" onsubmit="return confirm('Start Rematch with identical squads? 🏏');" class="inline">
                    @csrf
                    <button type="submit" class="px-3 py-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-xs font-bold transition flex items-center gap-1 shadow-sm">
                        <span>Rematch 🔁</span>
                    </button>
                </form>
                <a href="{{ route('matches.edit', $match) }}" class="px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold transition">
                    Match Settings
                </a>
                <form method="POST" action="{{ route('matches.destroy', $match) }}" onsubmit="return confirm('Delete this match?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="px-3 py-1.5 rounded-lg bg-red-50 hover:bg-red-100 text-red-600 text-xs font-bold transition">
                        Delete
                    </button>
                </form>
            @endif
        </div>
    </div>
@endsection

@section('content')
@php
    $isOwner = auth()->check() && (auth()->id() === $match->user_id);
    $score1 = $match->getScoreForTeam($match->team1_name);
    $score2 = $match->getScoreForTeam($match->team2_name);
    $activeInningNum = $state['batting_team_number'] ?? 1;
    $activeBattingTeam = $activeInningNum == 1 ? $match->team1_name : $match->team2_name;
    $activeBowlingTeam = $activeInningNum == 1 ? $match->team2_name : $match->team1_name;
    $currentScoreObj = $activeInningNum == 1 ? $score1 : $score2;

    $battingSquad = $activeInningNum == 1 ? $match->team1Players : $match->team2Players;

    $isPlayerOut = function($p) {
        if (!$p) return true;
        $d = strtolower(trim((string)$p->dismissal));
        return !empty($d) && $d !== 'not out';
    };

    // Ensure striker and non-striker are never dismissed players
    if ($isPlayerOut($striker)) {
        $striker = $battingSquad->first(fn($p) => !$isPlayerOut($p) && $p->id !== ($nonStriker?->id ?? null));
    }
    if ($isPlayerOut($nonStriker) || ($nonStriker && $striker && $nonStriker->id === $striker->id)) {
        $nonStriker = $battingSquad->first(fn($p) => !$isPlayerOut($p) && $p->id !== ($striker?->id ?? null));
    }

    $currentStrikerId = $striker?->id;
    $currentNonStrikerId = $nonStriker?->id;

    // Filter available next batsmen: exclude currently batting striker & non-striker, and exclude already dismissed players
    $availableNextBatsmen = $battingSquad->filter(function ($pl) use ($currentStrikerId, $currentNonStrikerId, $isPlayerOut) {
        if ($currentStrikerId && $pl->id == $currentStrikerId) return false;
        if ($currentNonStrikerId && $pl->id == $currentNonStrikerId) return false;
        return !$isPlayerOut($pl);
    })->values();

    $defaultNextBatterId = $availableNextBatsmen->first()?->id ?? '';

    $bowlingSquad = $activeInningNum == 1 ? $match->team2Players : $match->team1Players;

    // Ensure bowler belongs to the bowling squad of this match
    if (!$bowler || !$bowlingSquad->contains('id', $bowler->id)) {
        $bowler = $bowlingSquad->first(function($p) {
            $role = strtolower((string)$p->player_role);
            return str_contains($role, 'bowler') || str_contains($role, 'all-rounder');
        }) ?? $bowlingSquad->first();
    }

    $currentBowlerId = $bowler?->id;
    $availableBowlers = $bowlingSquad->filter(function($b) use ($currentBowlerId, $bowlingSquad) {
        if ($bowlingSquad->count() > 1 && $b->id == $currentBowlerId) return false;
        return true;
    })->values();
    $defaultNewBowlerId = $availableBowlers->first()?->id ?? ($bowlingSquad->first()?->id ?? '');

    $totalMatchOvers = (int) ($match->total_overs ?: 20);
    $ov1Parts = explode('.', (string) ($score1 ? ($score1->overs ?? '0.0') : '0.0'));
    $ballsIn1 = ((int) ($ov1Parts[0] ?? 0) * 6) + (int) ($ov1Parts[1] ?? 0);
    $isInning1Done = ($score1 && ($score1->status === 'completed' || $ballsIn1 >= ($totalMatchOvers * 6) || $score1->wickets >= 10));

    $isMatchDone = ($match->status === 'completed' || !empty($match->match_result));
@endphp

<div class="py-8 bg-slate-900 min-h-screen text-slate-100 font-sans" x-data="{
    scorecardInning: {{ $activeInningNum }},
    scoreInning: {{ $activeInningNum }},
    scoreBattingTeam: '{{ $activeBattingTeam }}',
    scoreRuns: {{ $currentScoreObj ? $currentScoreObj->runs : 0 }},
    scoreWickets: {{ $currentScoreObj ? $currentScoreObj->wickets : 0 }},
    scoreOvers: '{{ $currentScoreObj ? ($currentScoreObj->overs ?? '0.0') : '0.0' }}',
    scoreStatus: '{{ $match->status }}',
    scoreResult: '{{ $match->match_result ?? '' }}',
    toastMsg: '',
    showToast(msg) {
        this.toastMsg = msg;
        setTimeout(() => { this.toastMsg = ''; }, 3000);
    },

    // CricHeroes Authentic Real-Time State
    liveStriker: {{ json_encode($striker) }},
    liveNonStriker: {{ json_encode($nonStriker) }},
    liveBowler: {{ json_encode($bowler) }},
    thisOverBalls: {{ json_encode($state['this_over'] ?? []) }},
    isProcessingBall: false,
    wicketModalOpen: false,
    wicketHowOut: 'bowled',
    wicketNextBatterId: '{{ $defaultNextBatterId }}',
    bowlerModalOpen: {{ (!empty($state['needs_new_bowler']) && $isOwner) ? 'true' : 'false' }},
    needsNewBowler: {{ (!empty($state['needs_new_bowler']) && $isOwner) ? 'true' : 'false' }},
    newBowlerId: '{{ $defaultNewBowlerId }}',
    openersModalOpen: false,
    selectStrikerId: '{{ $state['striker_id'] ?? '' }}',
    selectNonStrikerId: '{{ $state['non_striker_id'] ?? '' }}',
    selectBowlerId: '{{ $state['bowler_id'] ?? '' }}',

    // Ball-by-Ball Commentary & Shot Direction System
    activeMainTab: 'scorecard',
    selectedShot: '',
    customCommentaryText: '',
    showCustomCommentaryInput: false,
    commentaryFilter: 'all',
    commentaryList: {{ json_encode($state['commentary'] ?? []) }},

    selectedShotLabel() {
        const labels = {
            'cover': 'Cover Drive 🏏',
            'cut': 'Square Cut ⚡',
            'pull': 'Pull Shot 💥',
            'straight': 'Straight Drive 🎯',
            'midwicket': 'Mid-Wicket 🔄',
            'sweep': 'Sweep 🧹',
            'edge': 'Edge / Cut 🧤'
        };
        return labels[this.selectedShot] || this.selectedShot;
    },

    filteredCommentary() {
        if (!this.commentaryList) return [];
        if (this.commentaryFilter === 'boundaries') {
            return this.commentaryList.filter(c => c.badge_type === 'four' || c.badge_type === 'six' || c.badge === '4' || c.badge === '6');
        }
        if (this.commentaryFilter === 'wickets') {
            return this.commentaryList.filter(c => c.badge_type === 'wicket' || c.badge === 'W');
        }
        return this.commentaryList;
    },

    // CricHeroes Ball-By-Ball Action Dispatcher
    async recordBallAction(action, payload = {}) {
        // Exclude setup/meta actions from active bowler and batsman requirement
        const isSetupAction = ['select_bowler', 'select_openers', 'switch_innings', 'undo'].includes(action);

        if (!isSetupAction) {
            // Attach selected shot and custom commentary if not specified
            if (!payload.shot && this.selectedShot) {
                payload.shot = this.selectedShot;
            }
            if (!payload.commentary && this.customCommentaryText) {
                payload.commentary = this.customCommentaryText;
            }

            // 1. Strict Bowler Check
            if (!this.liveBowler || !this.liveBowler.id) {
                this.showToast('⚠️ Please select a Bowler first!');
                this.bowlerModalOpen = true;
                return;
            }
            if (this.needsNewBowler) {
                this.showToast('⚠️ Over completed! Please select a Bowler for the next over.');
                this.bowlerModalOpen = true;
                return;
            }
            // 2. Strict Batsmen Check
            if (!this.liveStriker || !this.liveStriker.id || !this.liveNonStriker || !this.liveNonStriker.id) {
                this.showToast('⚠️ Please select Striker and Non-Striker first!');
                this.openersModalOpen = true;
                return;
            }
        }

        if (this.isProcessingBall) return;
        this.isProcessingBall = true;
        try {
            const res = await fetch('{{ route('matches.ball-by-ball', $match) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify(Object.assign({ action: action }, payload))
            });

            const data = await res.json();
            if (res.ok && data.status === 'success') {
                this.showToast(data.message);
                if (data.striker) this.liveStriker = data.striker;
                if (data.non_striker) this.liveNonStriker = data.non_striker;
                if (data.bowler) this.liveBowler = data.bowler;
                if (data.state && data.state.this_over) this.thisOverBalls = data.state.this_over;
                if (data.state && data.state.commentary) this.commentaryList = data.state.commentary;
                this.selectedShot = '';
                this.customCommentaryText = '';
                this.showCustomCommentaryInput = false;
                if (data.score) {
                    this.scoreRuns = data.score.runs;
                    this.scoreWickets = data.score.wickets;
                    this.scoreOvers = data.score.overs;
                }

                if (action === 'select_bowler') {
                    this.needsNewBowler = false;
                }

                // If wicket was taken and next batsman not yet selected, open wicket modal
                if (data.wicket && !payload.next_batsman_id) {
                    this.wicketModalOpen = true;
                }

                // If over completed, prompt next bowler
                if (data.over_completed) {
                    this.needsNewBowler = true;
                    this.bowlerModalOpen = true;
                }

                // Refresh page after short delay to keep all scorecard tables 100% updated
                setTimeout(() => { window.location.reload(); }, 600);
            } else {
                this.showToast(data.message || 'Error recording ball');
                if (data.needs_new_bowler) {
                    this.needsNewBowler = true;
                    this.bowlerModalOpen = true;
                }
                if (data.needs_openers) {
                    this.openersModalOpen = true;
                }
            }
        } catch(e) {
            console.error(e);
            this.showToast('Network error recording ball');
        } finally {
            this.isProcessingBall = false;
        }
    },

    submitWicket() {
        let nextId = this.wicketNextBatterId;
        if (!nextId) {
            nextId = '{{ $defaultNextBatterId }}';
        }
        this.recordBallAction('wicket', {
            how_out: this.wicketHowOut,
            next_batsman_id: nextId
        });
        this.wicketModalOpen = false;
    },

    submitNewBowler() {
        let bId = this.newBowlerId;
        if (!bId) {
            bId = '{{ $defaultNewBowlerId }}';
        }
        if (!bId) {
            this.showToast('Please select a bowler');
            return;
        }
        this.recordBallAction('select_bowler', {
            bowler_id: bId
        });
        this.bowlerModalOpen = false;
    },

    submitOpeners() {
        if (!this.selectStrikerId || !this.selectNonStrikerId || !this.selectBowlerId) {
            this.showToast('Please select Striker, Non-Striker, and Bowler');
            return;
        }
        this.recordBallAction('select_openers', {
            striker_id: this.selectStrikerId,
            non_striker_id: this.selectNonStrikerId,
            bowler_id: this.selectBowlerId
        });
        this.openersModalOpen = false;
    },

    // Manual quick-score fallback save
    isSavingManualScore: false,
    async saveManualScore() {
        this.isSavingManualScore = true;
        try {
            const res = await fetch('{{ route('matches.quick-score', $match) }}', {
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
                this.showToast('Scorecard updated! ⚡');
                setTimeout(() => { window.location.reload(); }, 600);
            } else {
                this.showToast(data.message || 'Failed to update score');
            }
        } catch(e) {
            console.error(e);
            this.showToast('Error saving score');
        } finally {
            this.isSavingManualScore = false;
        }
    },

    // Player Stats Scorer Modal State
    playerModalOpen: false,
    selectedPlayer: null,
    editRuns: 0,
    editBalls: 0,
    editFours: 0,
    editSixes: 0,
    editDismissal: 'not out',
    editOvers: '0.0',
    editMaidens: 0,
    editRunsConceded: 0,
    editWickets: 0,
    editSyncTeam: true,
    isSavingPlayer: false,

    openPlayerModal(p) {
        this.selectedPlayer = p;
        this.editRuns = parseInt(p.runs_scored || 0);
        this.editBalls = parseInt(p.balls_faced || 0);
        this.editFours = parseInt(p.fours || 0);
        this.editSixes = parseInt(p.sixes || 0);
        this.editDismissal = p.dismissal || 'not out';
        this.editOvers = p.overs_bowled || '0.0';
        this.editMaidens = parseInt(p.maidens || 0);
        this.editRunsConceded = parseInt(p.runs_conceded || 0);
        this.editWickets = parseInt(p.wickets_taken || 0);
        this.playerModalOpen = true;
    },

    async savePlayerStats() {
        if (!this.selectedPlayer) return;
        this.isSavingPlayer = true;
        try {
            const url = '/matches/{{ $match->id }}/players/' + this.selectedPlayer.id + '/stats';
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    runs_scored: parseInt(this.editRuns || 0),
                    balls_faced: parseInt(this.editBalls || 0),
                    fours: parseInt(this.editFours || 0),
                    sixes: parseInt(this.editSixes || 0),
                    dismissal: this.editDismissal,
                    overs_bowled: this.editOvers || '0.0',
                    maidens: parseInt(this.editMaidens || 0),
                    runs_conceded: parseInt(this.editRunsConceded || 0),
                    wickets_taken: parseInt(this.editWickets || 0),
                    sync_team_score: this.editSyncTeam
                })
            });

            const data = await res.json();
            if (res.ok && data.status === 'success') {
                this.showToast(data.message || 'Player stats updated! 🏏');
                this.playerModalOpen = false;
                setTimeout(() => { window.location.reload(); }, 600);
            } else {
                this.showToast(data.message || 'Failed to update player stats');
            }
        } catch(e) {
            console.error(e);
            this.showToast('Error saving player stats');
        } finally {
            this.isSavingPlayer = false;
        }
    }
}">
    <!-- Floating Toast -->
    <div x-cloak x-show="toastMsg" x-transition 
         class="fixed bottom-6 right-6 z-50 bg-gradient-to-r from-emerald-600 to-teal-600 text-white px-5 py-3 rounded-2xl shadow-2xl font-bold text-sm flex items-center gap-2 border border-white/20">
        <span class="w-2 h-2 rounded-full bg-white animate-pulse"></span>
        <span x-text="toastMsg"></span>
    </div>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        <!-- ============================================== -->
        <!-- CRICHEROES GIANT MATCH BANNER & SCORES -->
        <!-- ============================================== -->
        <div class="bg-[#131b2e] border border-white/10 rounded-3xl p-6 sm:p-8 shadow-2xl relative overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-2 pb-4 border-b border-white/10 text-xs">
                <div>
                    <span class="font-black text-rose-400 uppercase tracking-wider">{{ $match->tournament_name ?? 'Local Tournament' }}</span>
                    <span class="text-slate-400 ml-2">• {{ $match->total_overs }} Overs • {{ $match->ball_type }}</span>
                </div>
                <div>
                    @if($match->status === 'live')
                        <span class="px-3 py-1 rounded-full text-xs font-black bg-red-600/20 text-red-400 border border-red-500/30 flex items-center gap-1.5 animate-pulse">
                            <span class="w-2 h-2 rounded-full bg-red-500"></span> LIVE
                        </span>
                    @elseif($match->status === 'completed')
                        <span class="px-3 py-1 rounded-full text-xs font-bold bg-emerald-600/20 text-emerald-400 border border-emerald-500/30">
                            COMPLETED
                        </span>
                    @else
                        <span class="px-3 py-1 rounded-full text-xs font-bold bg-amber-600/20 text-amber-400 border border-amber-500/30">
                            UPCOMING
                        </span>
                    @endif
                </div>
            </div>

            <!-- Teams Big Scores Row -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 my-6">
                <!-- Team 1 -->
                <div class="p-5 rounded-2xl bg-white/5 border border-white/10 flex items-center justify-between"
                     :class="scorecardInning === 1 ? 'ring-2 ring-blue-500/40 bg-blue-900/10' : ''">
                    <div>
                        <span class="text-[10px] font-bold text-blue-400 uppercase">Team 1</span>
                        <div class="font-extrabold text-white text-lg sm:text-xl mt-0.5">{{ $match->team1_name }}</div>
                        <div class="text-xs text-slate-400 mt-1">Overs: {{ $score1->overs ?? '0.0' }} / {{ $match->total_overs }}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-3xl sm:text-4xl font-black text-white font-mono">
                            {{ $score1 ? "{$score1->runs}/{$score1->wickets}" : '0/0' }}
                        </div>
                    </div>
                </div>

                <!-- Team 2 -->
                <div class="p-5 rounded-2xl bg-white/5 border border-white/10 flex items-center justify-between"
                     :class="scorecardInning === 2 ? 'ring-2 ring-rose-500/40 bg-rose-900/10' : ''">
                    <div>
                        <span class="text-[10px] font-bold text-rose-400 uppercase">Team 2</span>
                        <div class="font-extrabold text-white text-lg sm:text-xl mt-0.5">{{ $match->team2_name }}</div>
                        <div class="text-xs text-slate-400 mt-1">Overs: {{ $score2->overs ?? '0.0' }} / {{ $match->total_overs }}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-3xl sm:text-4xl font-black text-white font-mono">
                            {{ $score2 ? "{$score2->runs}/{$score2->wickets}" : '0/0' }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Match Situation Strip -->
            <div class="pt-4 border-t border-white/10 flex flex-wrap items-center justify-between text-xs gap-3">
                <div class="font-bold text-amber-300 flex items-center gap-2">
                    <span>⚡</span>
                    <span>
                        @if(!empty($match->match_result))
                            {{ $match->match_result }}
                        @elseif($activeInningNum == 2)
                            {{ $match->team2_name }} need {{ max(0, (($score1->runs ?? 0) + 1) - ($score2->runs ?? 0)) }} runs to win (Target: {{ ($score1->runs ?? 0) + 1 }})
                        @elseif($isInning1Done)
                            1st Innings completed • {{ $match->team2_name }} need {{ ($score1->runs ?? 0) + 1 }} runs to win
                        @else
                            {{ $match->toss_decision ?: 'Match in progress' }}
                        @endif
                    </span>
                </div>
                <div class="text-slate-400 flex flex-wrap items-center gap-y-1 gap-x-2">
                    <span class="flex items-center gap-1">
                        <span>📍</span>
                        @if($match->ground_id)
                            <a href="{{ route('grounds.show', $match->ground_id) }}" class="text-emerald-400 hover:text-emerald-300 font-bold hover:underline transition">
                                {{ $match->venue ?? 'Ground Venue' }} 🏟️
                            </a>
                        @else
                            <span>{{ $match->venue ?? 'Ground Venue' }}</span>
                        @endif
                    </span>

                    @if($match->umpire_id && $match->umpire)
                        <span class="text-white/20">•</span>
                        <span class="flex items-center gap-1">
                            <span>⚖️ Umpire:</span>
                            <a href="{{ route('umpires.show', $match->umpire_id) }}" class="text-indigo-400 hover:text-indigo-300 font-bold hover:underline transition inline-flex items-center gap-1">
                                <span>{{ $match->umpire->name }}</span>
                                <span class="px-1.5 py-0.5 rounded-full bg-amber-500/20 text-amber-300 text-[10px]">⭐ {{ number_format($match->umpire->rating, 1) }}</span>
                            </a>
                        </span>
                    @elseif(!empty($match->umpire_name))
                        <span class="text-white/20">•</span>
                        <span>⚖️ Umpire: <strong>{{ $match->umpire_name }}</strong></span>
                    @endif
                </div>
            </div>

            @if($match->player_of_match)
                <div class="mt-3 p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-xs font-bold flex items-center gap-2">
                    <span>🏆 Player of the Match:</span>
                    <span>{{ $match->player_of_match }}</span>
                </div>
            @endif

            @if($match->umpire_id && $match->umpire && $match->status === 'completed')
                <div class="mt-3 p-3.5 rounded-2xl bg-indigo-950/40 border border-indigo-500/20 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs"
                     x-data="{
                        currentRating: {{ (float) ($match->umpire_rating ?: 0) }},
                        hoverRating: 0,
                        saving: false,
                        rated: {{ $match->umpire_rating ? 'true' : 'false' }},
                        async submitRating(val) {
                            this.saving = true;
                            try {
                                const res = await fetch('{{ route('matches.rate-umpire', $match) }}', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                        'Accept': 'application/json'
                                    },
                                    body: JSON.stringify({ rating: val })
                                });
                                const data = await res.json();
                                if (data.status === 'success') {
                                    this.currentRating = val;
                                    this.rated = true;
                                }
                            } catch(e) {}
                            this.saving = false;
                        }
                     }">
                    <div class="flex items-center gap-2">
                        <span class="p-1.5 rounded-lg bg-indigo-600/30 text-indigo-300">⚖️</span>
                        <div>
                            <span class="font-bold text-white block">Official Umpire: {{ $match->umpire->name }}</span>
                            <span class="text-[11px] text-slate-400" x-text="rated ? 'Thanks! You gave a ' + currentRating + '★ rating.' : 'Rate this match\'s umpiring performance'"></span>
                        </div>
                    </div>
                    <div class="flex items-center gap-1">
                        <template x-for="star in [1, 2, 3, 4, 5]" :key="star">
                            <button type="button"
                                    @mouseenter="hoverRating = star"
                                    @mouseleave="hoverRating = 0"
                                    @click="submitRating(star)"
                                    :disabled="saving"
                                    class="text-lg transition transform hover:scale-125 focus:outline-none"
                                    :class="(hoverRating || currentRating) >= star ? 'text-amber-400' : 'text-slate-600'">
                                ★
                            </button>
                        </template>
                        <span class="ml-1 text-[11px] font-bold text-amber-300" x-show="currentRating > 0" x-text="currentRating + ' / 5'"></span>
                    </div>
                </div>
            @endif
        </div>

        <!-- ============================================== -->
        <!-- LIVE STREAM BROADCAST (PHONE CAMERA / YOUTUBE) -->
        <!-- ============================================== -->
        @if($match->stream_url)
            <div class="bg-[#131b2e] border border-red-500/30 rounded-3xl p-5 sm:p-6 shadow-2xl relative overflow-hidden" x-data="{ streamOpen: true }">
                <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-white/10 text-xs">
                    <div class="flex items-center gap-2.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-red-500 animate-ping"></span>
                        <span class="font-black text-red-500 uppercase tracking-wider">LIVE STREAM BROADCAST</span>
                        <span class="text-slate-400">• Video Stream with TV Overlay</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('matches.broadcast', $match) }}" 
                           class="px-3.5 py-1.5 rounded-xl bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-500 hover:to-rose-500 text-white font-extrabold text-xs transition flex items-center gap-1.5 shadow-md shadow-red-600/30">
                            <span class="w-2 h-2 rounded-full bg-white animate-pulse"></span>
                            <span>TV Graphics Overlay 📺</span>
                        </a>
                        <button type="button" @click="streamOpen = !streamOpen" 
                                class="px-3 py-1.5 rounded-xl bg-white/10 hover:bg-white/20 text-xs font-bold text-slate-300 transition">
                            <span x-text="streamOpen ? 'Hide Video 🔼' : 'Watch Stream 🔽'"></span>
                        </button>
                    </div>
                </div>

                <div x-show="streamOpen" x-transition class="mt-4">
                    @if($match->embed_stream_url)
                        <div class="relative w-full aspect-video rounded-2xl overflow-hidden bg-black border border-white/10 shadow-2xl">
                            <iframe src="{{ $match->embed_stream_url }}" 
                                    class="w-full h-full border-0" 
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" 
                                    allowfullscreen>
                            </iframe>
                        </div>
                    @else
                        <div class="p-4 rounded-xl bg-white/5 border border-white/10 flex flex-wrap items-center justify-between gap-3">
                            <div class="text-xs text-slate-300">
                                <span>Stream Link:</span>
                                <a href="{{ $match->stream_url }}" target="_blank" class="text-blue-400 underline font-mono break-all ml-1.5">{{ $match->stream_url }}</a>
                            </div>
                            <a href="{{ $match->stream_url }}" target="_blank" class="px-3.5 py-1.5 rounded-xl bg-red-600 hover:bg-red-500 text-white text-xs font-bold transition flex items-center gap-1">
                                <span>Watch on External App</span>
                                <span>↗</span>
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        @elseif($match->status === 'live' || (Auth::check() && Auth::id() === $match->user_id))
            <div class="bg-gradient-to-r from-slate-900 via-[#131b2e] to-red-950/40 border border-red-500/20 rounded-3xl p-5 shadow-xl flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-2xl bg-red-600/20 border border-red-500/30 flex items-center justify-center text-2xl shrink-0">
                        📹
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm font-black text-white">Live Broadcast & Ground Camera Studio</h3>
                            <span class="px-2 py-0.5 rounded-md bg-red-600/20 text-red-400 text-[10px] font-black uppercase">Live Ready</span>
                        </div>
                        <p class="text-xs text-slate-400 mt-0.5">
                            {{ $isOwner ? 'Go live directly from your phone camera with TV graphics scoreboard overlay.' : 'Watch real-time live ground video stream with synchronized TV scoreboard.' }}
                        </p>
                    </div>
                </div>
                <a href="{{ route('matches.broadcast', $match) }}"
                   class="px-5 py-2.5 rounded-2xl bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-500 text-white font-extrabold text-xs transition flex items-center gap-2 shadow-lg shadow-red-600/30 shrink-0">
                    <span class="w-2 h-2 rounded-full bg-white animate-ping"></span>
                    <span>{{ $isOwner ? '🔴 Open Broadcast Studio' : '📺 Watch Live Stream' }}</span>
                </a>
            </div>
        @endif

        <!-- ============================================================== -->
        <!-- CRICHEROES AUTHENTIC LIVE SCORING ARENA (PITCH & KEYPAD) -->
        <!-- ============================================================== -->
        @if($isOwner)
            <div class="bg-[#131b2e] border-2 border-emerald-500/30 rounded-3xl p-6 sm:p-8 shadow-2xl space-y-6 relative overflow-hidden">
                <!-- Top Status Header -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-white/10">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-emerald-600 text-white font-black text-xl flex items-center justify-center shadow-lg shadow-emerald-600/30">
                            🏏
                        </div>
                        <div>
                            <span class="text-[10px] font-black uppercase text-emerald-400 tracking-wider flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                                CRICHEROES LIVE BALL-BY-BALL SCORER
                            </span>
                            <h3 class="font-black text-xl text-white mt-0.5">
                                {{ $activeBattingTeam }} Batting
                            </h3>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="button" @click="openersModalOpen = true"
                                class="px-3 py-1.5 rounded-xl bg-white/10 hover:bg-white/20 text-xs font-bold text-slate-200 transition flex items-center gap-1.5">
                            <span>🏏 Change Lineup / Openers</span>
                        </button>
                        <button type="button" @click="recordBallAction('undo')"
                                class="px-3 py-1.5 rounded-xl bg-rose-600/20 hover:bg-rose-600/30 text-rose-300 border border-rose-500/30 text-xs font-bold transition flex items-center gap-1">
                            <span>↩️ Undo Last Ball</span>
                        </button>
                    </div>
                </div>

                <!-- CricHeroes 22-Yards Active Pitch & Crease View -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- Striker (On Strike) -->
                    <div class="p-4 rounded-2xl bg-gradient-to-br from-emerald-950/40 via-slate-900 to-slate-950 border-2 border-emerald-500/50 shadow-lg relative">
                        <div class="flex items-center justify-between text-[11px] mb-1">
                            <span class="px-2 py-0.5 rounded-md bg-emerald-600 text-white font-black uppercase text-[9px] flex items-center gap-1 shadow-sm">
                                <span>🏏 ON STRIKE</span>
                            </span>
                            <span class="text-emerald-400 font-mono text-[10px] font-bold">STRIKER</span>
                        </div>
                        <div class="font-black text-white text-base sm:text-lg truncate mt-1" 
                             x-text="liveStriker ? liveStriker.player_name : 'Select Striker'"></div>
                        <div class="mt-2 text-xs font-mono flex items-baseline justify-between">
                            <span class="text-xl font-black text-amber-300" 
                                  x-text="liveStriker ? (liveStriker.runs_scored + '*' ) : '0*'"></span>
                            <span class="text-slate-400" 
                                  x-text="liveStriker ? ('(' + liveStriker.balls_faced + 'b) • ' + liveStriker.fours + 'x4 ' + liveStriker.sixes + 'x6') : '(0b)'"></span>
                        </div>
                        <div class="text-[10px] text-slate-400 mt-1 flex justify-between">
                            <span>SR: <b class="text-slate-200" x-text="liveStriker && liveStriker.balls_faced > 0 ? ((liveStriker.runs_scored / liveStriker.balls_faced) * 100).toFixed(1) : '0.0'"></b></span>
                            <button type="button" @click="openPlayerModal(liveStriker)" x-show="liveStriker" class="text-emerald-400 hover:underline">Edit ✏️</button>
                        </div>
                    </div>

                    <!-- Non-Striker (Runner End) -->
                    <div class="p-4 rounded-2xl bg-slate-900 border border-white/10 shadow-lg relative">
                        <div class="flex items-center justify-between text-[11px] mb-1">
                            <span class="px-2 py-0.5 rounded-md bg-white/10 text-slate-300 font-bold uppercase text-[9px]">
                                NON-STRIKER
                            </span>
                            <!-- Swap Strike Button -->
                            <button type="button" @click="recordBallAction('swap_strike')" 
                                    class="px-2 py-0.5 rounded bg-blue-600/30 hover:bg-blue-600/50 text-blue-300 text-[10px] font-bold transition flex items-center gap-1">
                                <span>🔄 Swap Strike</span>
                            </button>
                        </div>
                        <div class="font-black text-white text-base sm:text-lg truncate mt-1" 
                             x-text="liveNonStriker ? liveNonStriker.player_name : 'Select Non-Striker'"></div>
                        <div class="mt-2 text-xs font-mono flex items-baseline justify-between">
                            <span class="text-xl font-black text-slate-200" 
                                  x-text="liveNonStriker ? liveNonStriker.runs_scored : '0'"></span>
                            <span class="text-slate-400" 
                                  x-text="liveNonStriker ? ('(' + liveNonStriker.balls_faced + 'b) • ' + liveNonStriker.fours + 'x4 ' + liveNonStriker.sixes + 'x6') : '(0b)'"></span>
                        </div>
                        <div class="text-[10px] text-slate-400 mt-1 flex justify-between">
                            <span>SR: <b class="text-slate-200" x-text="liveNonStriker && liveNonStriker.balls_faced > 0 ? ((liveNonStriker.runs_scored / liveNonStriker.balls_faced) * 100).toFixed(1) : '0.0'"></b></span>
                            <button type="button" @click="openPlayerModal(liveNonStriker)" x-show="liveNonStriker" class="text-blue-400 hover:underline">Edit ✏️</button>
                        </div>
                    </div>

                    <!-- Active Bowler -->
                    <div class="p-4 rounded-2xl shadow-lg relative transition"
                         :class="(!liveBowler || !liveBowler.id || needsNewBowler) ? 'bg-amber-950/40 border-2 border-amber-500 shadow-amber-500/20 animate-pulse' : 'bg-slate-900 border border-rose-500/30'">
                        <div class="flex items-center justify-between text-[11px] mb-1">
                            <span class="px-2 py-0.5 rounded-md text-white font-black uppercase text-[9px] flex items-center gap-1 shadow-sm"
                                  :class="(!liveBowler || !liveBowler.id || needsNewBowler) ? 'bg-amber-600' : 'bg-rose-600'">
                                <span>🎯 BOWLER</span>
                            </span>
                            <button type="button" @click="bowlerModalOpen = true" 
                                    class="px-2.5 py-1 rounded text-[10px] font-bold transition flex items-center gap-1"
                                    :class="(!liveBowler || !liveBowler.id) ? 'bg-amber-500 text-slate-950 font-black shadow-md' : 'bg-rose-600/30 hover:bg-rose-600/50 text-rose-300'">
                                <span x-text="(!liveBowler || !liveBowler.id) ? '+ Select Bowler ⚠️' : (needsNewBowler ? 'New Bowler 🎯' : 'Change 🔄')"></span>
                            </button>
                        </div>
                        <div class="font-black text-white text-base sm:text-lg truncate mt-1 cursor-pointer" 
                             @click="(!liveBowler || !liveBowler.id || needsNewBowler) ? (bowlerModalOpen = true) : null"
                             x-text="liveBowler ? liveBowler.player_name : '⚠️ Tap to Select Bowler'"></div>
                        <div class="mt-2 text-xs font-mono flex items-baseline justify-between">
                            <span class="text-xl font-black text-rose-400" 
                                  x-text="liveBowler ? (liveBowler.wickets_taken + '/' + liveBowler.runs_conceded) : '0/0'"></span>
                            <span class="text-slate-400" 
                                  x-text="liveBowler ? ('(' + (liveBowler.overs_bowled || '0.0') + ' ov)') : '(0.0 ov)'"></span>
                        </div>
                        <div class="text-[10px] text-slate-400 mt-1 flex justify-between">
                            <span>M: <b class="text-slate-200" x-text="liveBowler ? liveBowler.maidens : 0"></b></span>
                            <button type="button" @click="openPlayerModal(liveBowler)" x-show="liveBowler" class="text-rose-400 hover:underline">Edit ✏️</button>
                        </div>
                    </div>
                </div>

                <!-- CricHeroes Current Over Timeline (Circles) -->
                <div class="p-4 rounded-2xl bg-black/40 border border-white/10 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-black uppercase text-slate-400 tracking-wider">THIS OVER:</span>
                        <div class="flex items-center gap-2 flex-wrap">
                            <template x-if="thisOverBalls.length === 0">
                                <span class="text-xs text-slate-500 italic">No balls bowled in this over yet.</span>
                            </template>
                            <template x-for="(ball, idx) in thisOverBalls" :key="idx">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center font-black text-xs font-mono shadow-md border"
                                     :class="{
                                         'bg-rose-600 text-white border-rose-400 animate-bounce': ball === 'W',
                                         'bg-purple-600 text-white border-purple-400': ball === '6',
                                         'bg-blue-600 text-white border-blue-400': ball === '4',
                                         'bg-amber-600 text-white border-amber-400 text-[10px]': ball.includes('WD') || ball.includes('NB'),
                                         'bg-slate-800 text-slate-300 border-white/10': ball === '0',
                                         'bg-slate-700 text-white border-white/20': ball !== '0' && ball !== '4' && ball !== '6' && ball !== 'W' && !ball.includes('WD') && !ball.includes('NB')
                                     }">
                                    <span x-text="ball"></span>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div class="text-xs font-mono text-right flex items-center justify-end gap-3">
                        <span class="text-slate-400">Total: <b class="text-white" x-text="scoreRuns + '/' + scoreWickets"></b></span>
                        <span class="px-2.5 py-1 rounded-lg bg-white/10 text-amber-300 font-bold">Over: <span x-text="scoreOvers"></span></span>
                    </div>
                </div>

                @if($isMatchDone)
                    <!-- Match Completed Trophy Card -->
                    <div class="p-6 sm:p-8 rounded-2xl bg-gradient-to-r from-amber-500/15 via-purple-500/15 to-emerald-500/15 border-2 border-amber-500/40 text-center space-y-3 shadow-2xl">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-amber-500/20 text-amber-300 text-3xl mb-1 shadow-inner">
                            🏆
                        </div>
                        <h3 class="text-xl sm:text-2xl font-black text-white tracking-wide">MATCH COMPLETED</h3>
                        <p class="text-base sm:text-lg font-bold text-amber-300">{{ $match->match_result ?: 'Match Finished' }}</p>
                        <div class="pt-2 flex flex-wrap items-center justify-center gap-3">
                            <span class="px-3.5 py-1.5 rounded-full bg-white/10 text-slate-300 text-xs font-semibold">Total Overs: {{ $match->total_overs }}</span>
                            <span class="px-3.5 py-1.5 rounded-full bg-emerald-500/20 text-emerald-300 text-xs font-bold border border-emerald-500/30">Official Result Recorded</span>
                        </div>
                        @if($isOwner)
                            <div class="pt-3">
                                <form method="POST" action="{{ route('matches.rematch', $match) }}" onsubmit="return confirm('Start a fresh Rematch with identical squads? 🏏');">
                                    @csrf
                                    <button type="submit" 
                                            class="px-6 py-3 rounded-2xl bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-400 hover:to-teal-500 active:scale-95 text-slate-950 font-black text-xs sm:text-sm uppercase tracking-wider shadow-xl shadow-emerald-500/25 transition inline-flex items-center gap-2">
                                        <span>Start Rematch (Same Squads) 🔁</span>
                                    </button>
                                </form>
                                <p class="text-[11px] text-slate-400 mt-1.5">No need to re-select players. A fresh match starts immediately with both squads ready.</p>
                            </div>
                        @endif
                    </div>
                @elseif($activeInningNum == 1 && $isInning1Done)
                    <!-- Innings 1 Break Card with Target & Start 2nd Innings button -->
                    <div class="p-6 sm:p-8 rounded-2xl bg-gradient-to-r from-blue-900/40 via-indigo-900/40 to-slate-800/80 border-2 border-blue-500/50 text-center space-y-4 shadow-2xl">
                        <div class="flex items-center justify-center gap-2">
                            <span class="px-3.5 py-1.5 rounded-full bg-blue-500/20 text-blue-300 border border-blue-400/30 text-xs font-black uppercase tracking-wider">
                                🔔 1st Innings Completed ({{ $score1->overs ?? '0.0' }} / {{ $match->total_overs }} Ov)
                            </span>
                        </div>

                        <div>
                            <div class="text-xs uppercase font-bold text-slate-400">{{ $match->team1_name }} Final Score</div>
                            <div class="text-3xl sm:text-4xl font-black font-mono text-white mt-1">
                                {{ $score1->runs ?? 0 }}/{{ $score1->wickets ?? 0 }}
                                <span class="text-sm sm:text-base font-sans font-normal text-slate-400">({{ $score1->overs ?? '0.0' }} ov)</span>
                            </div>
                        </div>

                        <div class="p-4 rounded-xl bg-black/40 border border-white/10 inline-block px-8">
                            <div class="text-xs text-slate-400 font-bold uppercase tracking-wider">Target for {{ $match->team2_name }}</div>
                            <div class="text-2xl sm:text-3xl font-black text-amber-400 font-mono mt-1">
                                {{ ($score1->runs ?? 0) + 1 }} runs in {{ $match->total_overs }} overs
                            </div>
                        </div>

                        <div class="pt-2">
                            <button type="button" 
                                    @click="recordBallAction('switch_innings')"
                                    :disabled="isProcessingBall"
                                    class="w-full sm:w-auto px-8 py-3.5 rounded-2xl bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-400 hover:to-teal-500 active:scale-95 text-slate-950 font-black text-sm uppercase tracking-wider shadow-xl shadow-emerald-500/25 transition inline-flex items-center justify-center gap-2">
                                <span>Start 2nd Innings</span>
                                <span class="text-lg">➔</span>
                            </button>
                            <p class="text-xs text-slate-400 mt-2">Click to start {{ $match->team2_name }}'s batting and scorecard.</p>
                        </div>
                    </div>
                @else
                    <!-- CricHeroes Signature Scorer Touch Keypad -->
                    <div class="space-y-3">
                    <!-- Missing Bowler Warning Banner -->
                    <div x-cloak x-show="!liveBowler || !liveBowler.id" 
                         @click="bowlerModalOpen = true"
                         class="cursor-pointer p-3.5 rounded-2xl bg-gradient-to-r from-amber-600/30 to-rose-600/30 border-2 border-amber-500 text-amber-200 text-xs font-bold flex items-center justify-between hover:brightness-110 transition shadow-lg shadow-amber-500/20">
                        <div class="flex items-center gap-2.5">
                            <span class="text-xl animate-bounce">⚠️</span>
                            <div>
                                <div class="text-white font-black text-sm">Bowler Not Selected!</div>
                                <div class="text-amber-300/80 text-[11px]">Cannot score ball without a bowler. Click here to select a bowler.</div>
                            </div>
                        </div>
                        <span class="px-3.5 py-1.5 rounded-xl bg-amber-500 text-slate-950 font-black text-xs shadow-md">Select Bowler 🎯</span>
                    </div>

                    <!-- Over Complete Bowler Banner -->
                    <div x-cloak x-show="needsNewBowler" 
                         @click="bowlerModalOpen = true"
                         class="cursor-pointer p-3.5 rounded-2xl bg-gradient-to-r from-blue-600/30 to-indigo-600/30 border-2 border-blue-500 text-blue-200 text-xs font-bold flex items-center justify-between hover:brightness-110 transition shadow-lg shadow-blue-500/20">
                        <div class="flex items-center gap-2.5">
                            <span class="text-xl animate-bounce">🎯</span>
                            <div>
                                <div class="text-white font-black text-sm">Over Complete! Select Next Bowler</div>
                                <div class="text-blue-200/80 text-[11px]">Select a bowler from the bowling squad for the next over.</div>
                            </div>
                        </div>
                        <span class="px-3.5 py-1.5 rounded-xl bg-blue-500 text-white font-black text-xs shadow-md">Choose Bowler 🎯</span>
                    </div>

                    <div class="text-[11px] font-black uppercase text-slate-400 tracking-wider">
                        TAP TO RECORD BALL (AUTO ROTATES STRIKE & ADVANCES OVERS)
                    </div>

                    <!-- Shot Direction & Commentary Preset Selector -->
                    <div class="p-3 rounded-2xl bg-black/40 border border-white/10 space-y-2">
                        <div class="flex items-center justify-between text-[11px]">
                            <div class="flex items-center gap-1.5 font-bold text-slate-300">
                                <span>🎙️ Shot Commentary:</span>
                                <span class="text-amber-400 font-mono font-bold" x-text="selectedShot ? ('[' + selectedShotLabel() + ']') : '[Auto Smart AI ⚡]'"></span>
                            </div>
                            <button type="button" @click="showCustomCommentaryInput = !showCustomCommentaryInput"
                                    class="text-[10px] text-blue-400 hover:text-blue-300 font-bold flex items-center gap-1 transition">
                                <span x-text="showCustomCommentaryInput ? '✕ Hide Note' : '✍️ Custom Note'"></span>
                            </button>
                        </div>

                        <!-- 1-Click Shot Direction Chips -->
                        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 text-[11px] scrollbar-none font-medium">
                            <button type="button" @click="selectedShot = ''"
                                    :class="selectedShot === '' ? 'bg-amber-500 text-slate-950 font-black shadow-md shadow-amber-500/30' : 'bg-white/5 hover:bg-white/10 text-slate-400'"
                                    class="px-2.5 py-1 rounded-lg shrink-0 transition text-xs">
                                ⚡ Auto
                            </button>
                            <button type="button" @click="selectedShot = (selectedShot === 'cover' ? '' : 'cover')"
                                    :class="selectedShot === 'cover' ? 'bg-emerald-500 text-slate-950 font-black shadow-md shadow-emerald-500/30' : 'bg-white/5 hover:bg-white/10 text-slate-300'"
                                    class="px-2.5 py-1 rounded-lg shrink-0 transition">
                                🏏 Cover Drive
                            </button>
                            <button type="button" @click="selectedShot = (selectedShot === 'cut' ? '' : 'cut')"
                                    :class="selectedShot === 'cut' ? 'bg-emerald-500 text-slate-950 font-black shadow-md shadow-emerald-500/30' : 'bg-white/5 hover:bg-white/10 text-slate-300'"
                                    class="px-2.5 py-1 rounded-lg shrink-0 transition">
                                ⚡ Square Cut
                            </button>
                            <button type="button" @click="selectedShot = (selectedShot === 'pull' ? '' : 'pull')"
                                    :class="selectedShot === 'pull' ? 'bg-emerald-500 text-slate-950 font-black shadow-md shadow-emerald-500/30' : 'bg-white/5 hover:bg-white/10 text-slate-300'"
                                    class="px-2.5 py-1 rounded-lg shrink-0 transition">
                                💥 Pull Shot
                            </button>
                            <button type="button" @click="selectedShot = (selectedShot === 'straight' ? '' : 'straight')"
                                    :class="selectedShot === 'straight' ? 'bg-emerald-500 text-slate-950 font-black shadow-md shadow-emerald-500/30' : 'bg-white/5 hover:bg-white/10 text-slate-300'"
                                    class="px-2.5 py-1 rounded-lg shrink-0 transition">
                                🎯 Straight Drive
                            </button>
                            <button type="button" @click="selectedShot = (selectedShot === 'midwicket' ? '' : 'midwicket')"
                                    :class="selectedShot === 'midwicket' ? 'bg-emerald-500 text-slate-950 font-black shadow-md shadow-emerald-500/30' : 'bg-white/5 hover:bg-white/10 text-slate-300'"
                                    class="px-2.5 py-1 rounded-lg shrink-0 transition">
                                🔄 Mid-Wicket
                            </button>
                            <button type="button" @click="selectedShot = (selectedShot === 'sweep' ? '' : 'sweep')"
                                    :class="selectedShot === 'sweep' ? 'bg-emerald-500 text-slate-950 font-black shadow-md shadow-emerald-500/30' : 'bg-white/5 hover:bg-white/10 text-slate-300'"
                                    class="px-2.5 py-1 rounded-lg shrink-0 transition">
                                🧹 Sweep
                            </button>
                            <button type="button" @click="selectedShot = (selectedShot === 'edge' ? '' : 'edge')"
                                    :class="selectedShot === 'edge' ? 'bg-emerald-500 text-slate-950 font-black shadow-md shadow-emerald-500/30' : 'bg-white/5 hover:bg-white/10 text-slate-300'"
                                    class="px-2.5 py-1 rounded-lg shrink-0 transition">
                                🧤 Edge / Cut
                            </button>
                        </div>

                        <!-- Optional Custom Note Input -->
                        <div x-show="showCustomCommentaryInput" x-cloak class="pt-1">
                            <input type="text" x-model="customCommentaryText" 
                                   placeholder="Optional: Type custom ball commentary (e.g. Magnificent upper-cut over third man!)"
                                   class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-1.5 text-xs text-white placeholder:text-slate-500 focus:border-amber-400 focus:ring-0">
                        </div>
                    </div>

                    <!-- Row 1: Primary Runs (Big Rounded Buttons) -->
                    <div class="grid grid-cols-6 gap-2 sm:gap-3 text-base sm:text-lg font-black font-mono">
                        <button type="button" @click="recordBallAction('run', { runs: 0 })"
                                class="py-4 rounded-2xl bg-white/10 hover:bg-white/20 active:scale-95 text-white transition flex flex-col items-center justify-center shadow-lg">
                            <span>0</span>
                            <span class="text-[9px] font-sans font-bold text-slate-400">DOT</span>
                        </button>
                        <button type="button" @click="recordBallAction('run', { runs: 1 })"
                                class="py-4 rounded-2xl bg-white/10 hover:bg-white/20 active:scale-95 text-white transition flex flex-col items-center justify-center shadow-lg">
                            <span>1</span>
                            <span class="text-[9px] font-sans font-bold text-slate-400">SINGLE</span>
                        </button>
                        <button type="button" @click="recordBallAction('run', { runs: 2 })"
                                class="py-4 rounded-2xl bg-white/10 hover:bg-white/20 active:scale-95 text-white transition flex flex-col items-center justify-center shadow-lg">
                            <span>2</span>
                            <span class="text-[9px] font-sans font-bold text-slate-400">DOUBLE</span>
                        </button>
                        <button type="button" @click="recordBallAction('run', { runs: 3 })"
                                class="py-4 rounded-2xl bg-white/10 hover:bg-white/20 active:scale-95 text-white transition flex flex-col items-center justify-center shadow-lg">
                            <span>3</span>
                            <span class="text-[9px] font-sans font-bold text-slate-400">THREE</span>
                        </button>
                        <button type="button" @click="recordBallAction('run', { runs: 4 })"
                                class="py-4 rounded-2xl bg-gradient-to-b from-blue-500 to-blue-700 hover:from-blue-400 hover:to-blue-600 active:scale-95 text-white transition flex flex-col items-center justify-center shadow-xl shadow-blue-600/30">
                            <span>4</span>
                            <span class="text-[9px] font-sans font-black text-blue-200">FOUR 🏏</span>
                        </button>
                        <button type="button" @click="recordBallAction('run', { runs: 6 })"
                                class="py-4 rounded-2xl bg-gradient-to-b from-purple-500 to-purple-700 hover:from-purple-400 hover:to-purple-600 active:scale-95 text-white transition flex flex-col items-center justify-center shadow-xl shadow-purple-600/30">
                            <span>6</span>
                            <span class="text-[9px] font-sans font-black text-purple-200">SIX 💥</span>
                        </button>
                    </div>

                    <!-- Row 2: Extras & Wicket Keypad -->
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-2 sm:gap-3 text-xs sm:text-sm font-bold">
                        <button type="button" @click="recordBallAction('wide', { runs: 1 })"
                                class="py-3.5 rounded-2xl bg-amber-600/20 hover:bg-amber-600/30 border border-amber-500/40 text-amber-300 active:scale-95 transition flex flex-col items-center justify-center">
                            <span>WD (Wide)</span>
                            <span class="text-[9px] text-amber-400/80">+1 Run (Ball Not Counted)</span>
                        </button>
                        <button type="button" @click="recordBallAction('no_ball', { runs: 1 })"
                                class="py-3.5 rounded-2xl bg-orange-600/20 hover:bg-orange-600/30 border border-orange-500/40 text-orange-300 active:scale-95 transition flex flex-col items-center justify-center">
                            <span>NB (No Ball)</span>
                            <span class="text-[9px] text-orange-400/80">+1 Run (Free Hit)</span>
                        </button>
                        <button type="button" @click="recordBallAction('bye', { runs: 1 })"
                                class="py-3.5 rounded-2xl bg-white/10 hover:bg-white/20 border border-white/10 text-white active:scale-95 transition flex flex-col items-center justify-center">
                            <span>Bye (1B)</span>
                            <span class="text-[9px] text-slate-400">Team run only</span>
                        </button>
                        <button type="button" @click="recordBallAction('leg_bye', { runs: 1 })"
                                class="py-3.5 rounded-2xl bg-white/10 hover:bg-white/20 border border-white/10 text-white active:scale-95 transition flex flex-col items-center justify-center">
                            <span>Leg Bye (1LB)</span>
                            <span class="text-[9px] text-slate-400">Team run only</span>
                        </button>
                        <button type="button" @click="wicketNextBatterId = '{{ $defaultNextBatterId }}'; wicketModalOpen = true"
                                class="col-span-2 sm:col-span-1 py-3.5 rounded-2xl bg-gradient-to-b from-rose-500 to-rose-700 hover:from-rose-400 hover:to-rose-600 active:scale-95 text-white transition flex flex-col items-center justify-center shadow-xl shadow-rose-600/40">
                            <span class="text-sm font-black">☝️ WICKET</span>
                            <span class="text-[9px] font-bold text-rose-200">Fall of Wicket</span>
                        </button>
                    </div>
                </div>
                @endif
            </div>
        @endif

        <!-- ============================================================== -->
        <!-- MASTER NAVIGATION TABS: SCORECARD | LIVE COMMENTARY | SQUADS -->
        <!-- ============================================================== -->
        <div class="flex items-center gap-2 p-1.5 bg-[#131b2e] border border-white/10 rounded-2xl shadow-xl">
            <button type="button" @click="activeMainTab = 'scorecard'"
                    :class="activeMainTab === 'scorecard' ? 'bg-gradient-to-r from-red-600 to-rose-600 text-white font-black shadow-lg shadow-red-600/30' : 'text-slate-400 hover:text-white font-bold'"
                    class="flex-1 py-3 px-3 sm:px-5 rounded-xl text-xs sm:text-sm transition flex items-center justify-center gap-2">
                <span>📊 Scorecard</span>
            </button>
            <button type="button" @click="activeMainTab = 'commentary'"
                    :class="activeMainTab === 'commentary' ? 'bg-gradient-to-r from-red-600 to-rose-600 text-white font-black shadow-lg shadow-red-600/30' : 'text-slate-400 hover:text-white font-bold'"
                    class="flex-1 py-3 px-3 sm:px-5 rounded-xl text-xs sm:text-sm transition flex items-center justify-center gap-2">
                <span>🎙️ Ball-by-Ball Commentary</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] bg-black/40 font-mono font-bold" x-text="commentaryList.length"></span>
            </button>
            <button type="button" @click="activeMainTab = 'squads'"
                    :class="activeMainTab === 'squads' ? 'bg-gradient-to-r from-red-600 to-rose-600 text-white font-black shadow-lg shadow-red-600/30' : 'text-slate-400 hover:text-white font-bold'"
                    class="flex-1 py-3 px-3 sm:px-5 rounded-xl text-xs sm:text-sm transition flex items-center justify-center gap-2">
                <span>👥 Squads ({{ $match->team1Players->count() + $match->team2Players->count() }})</span>
            </button>
        </div>

        <!-- ============================================================== -->
        <!-- TAB 1: CRICHEROES COMPLETE SCORECARD (INDIVIDUAL RUNS & WICKETS) -->
        <!-- ============================================================== -->
        <div x-show="activeMainTab === 'scorecard'" class="space-y-6">
        <div class="bg-[#131b2e] border border-white/10 rounded-3xl p-6 sm:p-8 shadow-2xl space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-white/10">
                <div>
                    <span class="text-[10px] font-black uppercase text-amber-400 tracking-wider">CRICHEROES SCORECARD</span>
                    <h3 class="font-black text-xl text-white mt-0.5">Player Personal Runs & Wickets</h3>
                    <p class="text-slate-400 text-xs">Detailed individual batting & bowling cards with strike rates, maidens, and economy.</p>
                </div>

                <!-- Innings Switcher Tabs -->
                <div class="flex items-center gap-2 p-1 bg-black/40 rounded-xl border border-white/10 self-start sm:self-auto">
                    <button type="button" 
                            @click="scorecardInning = 1"
                            :class="scorecardInning === 1 ? 'bg-red-600 text-white font-bold' : 'text-slate-400 hover:text-white'"
                            class="px-4 py-2 rounded-lg text-xs transition flex items-center gap-1.5">
                        <span>1st Innings: {{ $match->team1_name }}</span>
                        <span class="px-1.5 py-0.2 rounded-md bg-black/30 font-mono text-[10px]">{{ $score1 ? "{$score1->runs}/{$score1->wickets}" : '0/0' }}</span>
                    </button>
                    <button type="button" 
                            @click="scorecardInning = 2"
                            :class="scorecardInning === 2 ? 'bg-red-600 text-white font-bold' : 'text-slate-400 hover:text-white'"
                            class="px-4 py-2 rounded-lg text-xs transition flex items-center gap-1.5">
                        <span>2nd Innings: {{ $match->team2_name }}</span>
                        <span class="px-1.5 py-0.2 rounded-md bg-black/30 font-mono text-[10px]">{{ $score2 ? "{$score2->runs}/{$score2->wickets}" : '0/0' }}</span>
                    </button>
                </div>
            </div>

            <!-- ==================== INNINGS 1: TEAM 1 BATTING & TEAM 2 BOWLING ==================== -->
            <div x-show="scorecardInning === 1" class="space-y-6">
                <!-- Batting Table: Team 1 -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h4 class="font-black text-sm text-blue-400 flex items-center gap-2">
                            <span>🏏 {{ $match->team1_name }} Batting Card</span>
                        </h4>
                        <span class="text-xs text-slate-400 font-mono">
                            Overs: {{ $score1->overs ?? '0.0' }} / {{ $match->total_overs }}
                        </span>
                    </div>

                    <div class="overflow-x-auto rounded-2xl border border-white/10 bg-black/20">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-white/5 text-slate-400 font-bold uppercase text-[10px] tracking-wider border-b border-white/10">
                                <tr>
                                    <th class="py-3 px-4">Batter</th>
                                    <th class="py-3 px-3">Dismissal</th>
                                    <th class="py-3 px-3 text-right">R</th>
                                    <th class="py-3 px-3 text-right">B</th>
                                    <th class="py-3 px-3 text-right">4s</th>
                                    <th class="py-3 px-3 text-right">6s</th>
                                    <th class="py-3 px-3 text-right">SR</th>
                                    @if($isOwner)
                                        <th class="py-3 px-4 text-center">Score</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                @php
                                    $t1Batters = $team1BattingSquad ?? $match->team1Players;
                                    $strikerId = $state['striker_id'] ?? ($striker?->id ?? null);
                                    $nonStrikerId = $state['non_striker_id'] ?? ($nonStriker?->id ?? null);
                                @endphp
                                @forelse($t1Batters as $p)
                                    @php
                                        $pHasBatted = $p->balls_faced > 0 || $p->runs_scored > 0 || in_array($p->id, array_filter([$strikerId, $nonStrikerId])) || ($p->dismissal && !in_array(strtolower(trim($p->dismissal)), ['not out', 'dnb', 'yet to bat', '']));
                                    @endphp
                                    <tr class="hover:bg-white/5 transition"
                                        :class="liveStriker && liveStriker.id === {{ $p->id }} ? 'bg-emerald-950/20' : ''">
                                        <td class="py-3 px-4">
                                            <div class="flex items-center gap-2">
                                                <div class="w-6 h-6 rounded-md bg-blue-600/30 text-blue-300 font-bold text-[10px] flex items-center justify-center shrink-0">
                                                    {{ strtoupper(substr($p->player_name, 0, 1)) }}
                                                </div>
                                                <div>
                                                    <div class="font-extrabold text-white flex items-center gap-1.5">
                                                        @if($p->user_id)
                                                            <a href="{{ route('profile.user', $p->user_id) }}" class="hover:underline text-blue-400">
                                                                {{ $p->player_name }}
                                                            </a>
                                                        @else
                                                            <span>{{ $p->player_name }}</span>
                                                        @endif
                                                        <template x-if="liveStriker && liveStriker.id === {{ $p->id }}">
                                                            <span class="px-1 rounded bg-emerald-600 text-[8px] font-black text-white">*</span>
                                                        </template>
                                                        @if($p->is_captain)<span class="px-1 rounded bg-red-600 text-[8px] font-black text-white">C</span>@endif
                                                        @if($p->is_wicketkeeper)<span class="px-1 rounded bg-amber-600 text-[8px] font-black text-white">WK</span>@endif
                                                    </div>
                                                    <div class="text-[9px] text-slate-400">{{ $p->player_role }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-3 px-3 text-slate-300 text-[11px]">
                                            @if(!$pHasBatted)
                                                <span class="text-slate-500 italic">yet to bat</span>
                                            @elseif($p->dismissal === 'not out' || empty($p->dismissal))
                                                <span class="text-emerald-400 font-bold">not out</span>
                                            @elseif($p->dismissal === 'dnb')
                                                <span class="text-slate-500 italic">did not bat</span>
                                            @else
                                                <span class="text-slate-400">{{ $p->dismissal }}</span>
                                            @endif
                                        </td>
                                        <td class="py-3 px-3 text-right font-black font-mono text-sm">
                                            @if($p->runs_scored >= 100)
                                                <span class="px-2 py-0.5 rounded-md bg-purple-600 text-white shadow-xs">{{ $p->runs_scored }}</span>
                                            @elseif($p->runs_scored >= 50)
                                                <span class="px-2 py-0.5 rounded-md bg-amber-600 text-white shadow-xs">{{ $p->runs_scored }}</span>
                                            @else
                                                <span class="text-white">{{ $p->runs_scored }}</span>
                                            @endif
                                        </td>
                                        <td class="py-3 px-3 text-right text-slate-300 font-mono">{{ $p->balls_faced }}</td>
                                        <td class="py-3 px-3 text-right text-slate-300 font-mono">{{ $p->fours }}</td>
                                        <td class="py-3 px-3 text-right text-slate-300 font-mono">{{ $p->sixes }}</td>
                                        <td class="py-3 px-3 text-right text-slate-300 font-mono">{{ $p->strike_rate }}</td>
                                        @if($isOwner)
                                            <td class="py-3 px-4 text-center">
                                                <button type="button" 
                                                        @click="openPlayerModal({{ json_encode($p) }})"
                                                        class="px-2.5 py-1 rounded-lg bg-white/10 hover:bg-white/20 text-white text-[10px] font-bold transition flex items-center gap-1 mx-auto">
                                                    <span>✏️ Score</span>
                                                </button>
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="py-4 text-center text-slate-500 italic">No players in {{ $match->team1_name }} squad.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @php
                        $yetToBat1 = $t1Batters->filter(function($pl) use ($strikerId, $nonStrikerId) {
                            $hasBatted = $pl->balls_faced > 0 || $pl->runs_scored > 0 || in_array($pl->id, array_filter([$strikerId, $nonStrikerId])) || ($pl->dismissal && !in_array(strtolower(trim($pl->dismissal)), ['not out', 'dnb', 'yet to bat', '']));
                            return !$hasBatted;
                        });
                    @endphp
                    @if($yetToBat1->isNotEmpty())
                        <div class="p-3 bg-white/5 rounded-2xl border border-white/5 text-xs flex items-center gap-2 flex-wrap">
                            <span class="text-slate-400 font-bold uppercase text-[10px] tracking-wider shrink-0">Yet to Bat:</span>
                            @foreach($yetToBat1 as $yb)
                                <span class="px-2.5 py-1 rounded-lg bg-slate-800 text-slate-300 text-xs border border-white/5 font-medium">
                                    {{ $yb->player_name }}
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>

                <!-- Bowling Table: Team 2 Bowlers bowling against Team 1 -->
                <div class="space-y-3 pt-2">
                    <div class="flex items-center justify-between">
                        <h4 class="font-black text-sm text-rose-400 flex items-center gap-2">
                            <span>🎯 {{ $match->team2_name }} Bowling Card</span>
                        </h4>
                    </div>

                    <div class="overflow-x-auto rounded-2xl border border-white/10 bg-black/20">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-white/5 text-slate-400 font-bold uppercase text-[10px] tracking-wider border-b border-white/10">
                                <tr>
                                    <th class="py-3 px-4">Bowler</th>
                                    <th class="py-3 px-3 text-right">O</th>
                                    <th class="py-3 px-3 text-right">M</th>
                                    <th class="py-3 px-3 text-right">R</th>
                                    <th class="py-3 px-3 text-right">W</th>
                                    <th class="py-3 px-3 text-right">ECO</th>
                                    @if($isOwner)
                                        <th class="py-3 px-4 text-center">Score</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                @forelse($match->team2Players as $b)
                                    <tr class="hover:bg-white/5 transition"
                                        :class="liveBowler && liveBowler.id === {{ $b->id }} ? 'bg-rose-950/20' : ''">
                                        <td class="py-3 px-4">
                                            <div class="flex items-center gap-2">
                                                <div class="w-6 h-6 rounded-md bg-rose-600/30 text-rose-300 font-bold text-[10px] flex items-center justify-center shrink-0">
                                                    {{ strtoupper(substr($b->player_name, 0, 1)) }}
                                                </div>
                                                <div>
                                                    <div class="font-extrabold text-white flex items-center gap-1.5">
                                                        @if($b->user_id)
                                                            <a href="{{ route('profile.user', $b->user_id) }}" class="hover:underline text-rose-400">
                                                                {{ $b->player_name }}
                                                            </a>
                                                        @else
                                                            <span>{{ $b->player_name }}</span>
                                                        @endif
                                                        <template x-if="liveBowler && liveBowler.id === {{ $b->id }}">
                                                            <span class="px-1 rounded bg-rose-600 text-[8px] font-black text-white">ACTIVE</span>
                                                        </template>
                                                        @if($b->is_captain)<span class="px-1 rounded bg-red-600 text-[8px] font-black text-white">C</span>@endif
                                                    </div>
                                                    <div class="text-[9px] text-slate-400">{{ $b->player_role }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-3 px-3 text-right text-slate-300 font-mono">{{ $b->overs_bowled ?: '0.0' }}</td>
                                        <td class="py-3 px-3 text-right text-slate-300 font-mono">{{ $b->maidens }}</td>
                                        <td class="py-3 px-3 text-right text-slate-300 font-mono">{{ $b->runs_conceded }}</td>
                                        <td class="py-3 px-3 text-right font-black font-mono text-sm">
                                            @if($b->wickets_taken > 0)
                                                <span class="px-2 py-0.5 rounded-md bg-rose-600 text-white shadow-xs">{{ $b->wickets_taken }}</span>
                                            @else
                                                <span class="text-slate-400">0</span>
                                            @endif
                                        </td>
                                        <td class="py-3 px-3 text-right text-slate-300 font-mono">{{ $b->economy_rate }}</td>
                                        @if($isOwner)
                                            <td class="py-3 px-4 text-center">
                                                <button type="button" 
                                                        @click="openPlayerModal({{ json_encode($b) }})"
                                                        class="px-2.5 py-1 rounded-lg bg-white/10 hover:bg-white/20 text-white text-[10px] font-bold transition flex items-center gap-1 mx-auto">
                                                    <span>✏️ Score</span>
                                                </button>
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="py-4 text-center text-slate-500 italic">No bowlers in {{ $match->team2_name }} squad.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ==================== INNINGS 2: TEAM 2 BATTING & TEAM 1 BOWLING ==================== -->
            <div x-show="scorecardInning === 2" class="space-y-6">
                <!-- Batting Table: Team 2 -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h4 class="font-black text-sm text-rose-400 flex items-center gap-2">
                            <span>🏏 {{ $match->team2_name }} Batting Card</span>
                        </h4>
                        <span class="text-xs text-slate-400 font-mono">
                            Overs: {{ $score2->overs ?? '0.0' }} / {{ $match->total_overs }}
                        </span>
                    </div>

                    <div class="overflow-x-auto rounded-2xl border border-white/10 bg-black/20">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-white/5 text-slate-400 font-bold uppercase text-[10px] tracking-wider border-b border-white/10">
                                <tr>
                                    <th class="py-3 px-4">Batter</th>
                                    <th class="py-3 px-3">Dismissal</th>
                                    <th class="py-3 px-3 text-right">R</th>
                                    <th class="py-3 px-3 text-right">B</th>
                                    <th class="py-3 px-3 text-right">4s</th>
                                    <th class="py-3 px-3 text-right">6s</th>
                                    <th class="py-3 px-3 text-right">SR</th>
                                    @if($isOwner)
                                        <th class="py-3 px-4 text-center">Score</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                @php
                                    $t2Batters = $team2BattingSquad ?? $match->team2Players;
                                    $t2StrikerId = ($state['batting_team_number'] ?? 1) == 2 ? ($state['striker_id'] ?? null) : null;
                                    $t2NonStrikerId = ($state['batting_team_number'] ?? 1) == 2 ? ($state['non_striker_id'] ?? null) : null;
                                @endphp
                                @forelse($t2Batters as $p)
                                    @php
                                        $p2HasBatted = $p->balls_faced > 0 || $p->runs_scored > 0 || in_array($p->id, array_filter([$t2StrikerId, $t2NonStrikerId])) || ($p->dismissal && !in_array(strtolower(trim($p->dismissal)), ['not out', 'dnb', 'yet to bat', '']));
                                    @endphp
                                    <tr class="hover:bg-white/5 transition"
                                        :class="liveStriker && liveStriker.id === {{ $p->id }} ? 'bg-rose-950/20' : ''">
                                        <td class="py-3 px-4">
                                            <div class="flex items-center gap-2">
                                                <div class="w-6 h-6 rounded-md bg-rose-600/30 text-rose-300 font-bold text-[10px] flex items-center justify-center shrink-0">
                                                    {{ strtoupper(substr($p->player_name, 0, 1)) }}
                                                </div>
                                                <div>
                                                    <div class="font-extrabold text-white flex items-center gap-1.5">
                                                        @if($p->user_id)
                                                            <a href="{{ route('profile.user', $p->user_id) }}" class="hover:underline text-rose-400">
                                                                {{ $p->player_name }}
                                                            </a>
                                                        @else
                                                            <span>{{ $p->player_name }}</span>
                                                        @endif
                                                        <template x-if="liveStriker && liveStriker.id === {{ $p->id }}">
                                                            <span class="px-1 rounded bg-rose-600 text-[8px] font-black text-white">*</span>
                                                        </template>
                                                        @if($p->is_captain)<span class="px-1 rounded bg-red-600 text-[8px] font-black text-white">C</span>@endif
                                                        @if($p->is_wicketkeeper)<span class="px-1 rounded bg-amber-600 text-[8px] font-black text-white">WK</span>@endif
                                                    </div>
                                                    <div class="text-[9px] text-slate-400">{{ $p->player_role }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-3 px-3 text-slate-300 text-[11px]">
                                            @if(!$p2HasBatted)
                                                <span class="text-slate-500 italic">yet to bat</span>
                                            @elseif($p->dismissal === 'not out' || empty($p->dismissal))
                                                <span class="text-emerald-400 font-bold">not out</span>
                                            @elseif($p->dismissal === 'dnb')
                                                <span class="text-slate-500 italic">did not bat</span>
                                            @else
                                                <span class="text-slate-400">{{ $p->dismissal }}</span>
                                            @endif
                                        </td>
                                        <td class="py-3 px-3 text-right font-black font-mono text-sm">
                                            @if($p->runs_scored >= 100)
                                                <span class="px-2 py-0.5 rounded-md bg-purple-600 text-white shadow-xs">{{ $p->runs_scored }}</span>
                                            @elseif($p->runs_scored >= 50)
                                                <span class="px-2 py-0.5 rounded-md bg-amber-600 text-white shadow-xs">{{ $p->runs_scored }}</span>
                                            @else
                                                <span class="text-white">{{ $p->runs_scored }}</span>
                                            @endif
                                        </td>
                                        <td class="py-3 px-3 text-right text-slate-300 font-mono">{{ $p->balls_faced }}</td>
                                        <td class="py-3 px-3 text-right text-slate-300 font-mono">{{ $p->fours }}</td>
                                        <td class="py-3 px-3 text-right text-slate-300 font-mono">{{ $p->sixes }}</td>
                                        <td class="py-3 px-3 text-right text-slate-300 font-mono">{{ $p->strike_rate }}</td>
                                        @if($isOwner)
                                            <td class="py-3 px-4 text-center">
                                                <button type="button" 
                                                        @click="openPlayerModal({{ json_encode($p) }})"
                                                        class="px-2.5 py-1 rounded-lg bg-white/10 hover:bg-white/20 text-white text-[10px] font-bold transition flex items-center gap-1 mx-auto">
                                                    <span>✏️ Score</span>
                                                </button>
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="py-4 text-center text-slate-500 italic">No players in {{ $match->team2_name }} squad.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @php
                        $yetToBat2 = $t2Batters->filter(function($pl) use ($t2StrikerId, $t2NonStrikerId) {
                            $hasBatted = $pl->balls_faced > 0 || $pl->runs_scored > 0 || in_array($pl->id, array_filter([$t2StrikerId, $t2NonStrikerId])) || ($pl->dismissal && !in_array(strtolower(trim($pl->dismissal)), ['not out', 'dnb', 'yet to bat', '']));
                            return !$hasBatted;
                        });
                    @endphp
                    @if($yetToBat2->isNotEmpty())
                        <div class="p-3 bg-white/5 rounded-2xl border border-white/5 text-xs flex items-center gap-2 flex-wrap">
                            <span class="text-slate-400 font-bold uppercase text-[10px] tracking-wider shrink-0">Yet to Bat:</span>
                            @foreach($yetToBat2 as $yb)
                                <span class="px-2.5 py-1 rounded-lg bg-slate-800 text-slate-300 text-xs border border-white/5 font-medium">
                                    {{ $yb->player_name }}
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>

                <!-- Bowling Table: Team 1 Bowlers bowling against Team 2 -->
                <div class="space-y-3 pt-2">
                    <div class="flex items-center justify-between">
                        <h4 class="font-black text-sm text-blue-400 flex items-center gap-2">
                            <span>🎯 {{ $match->team1_name }} Bowling Card</span>
                        </h4>
                    </div>

                    <div class="overflow-x-auto rounded-2xl border border-white/10 bg-black/20">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-white/5 text-slate-400 font-bold uppercase text-[10px] tracking-wider border-b border-white/10">
                                <tr>
                                    <th class="py-3 px-4">Bowler</th>
                                    <th class="py-3 px-3 text-right">O</th>
                                    <th class="py-3 px-3 text-right">M</th>
                                    <th class="py-3 px-3 text-right">R</th>
                                    <th class="py-3 px-3 text-right">W</th>
                                    <th class="py-3 px-3 text-right">ECO</th>
                                    @if($isOwner)
                                        <th class="py-3 px-4 text-center">Score</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                @forelse($match->team1Players as $b)
                                    <tr class="hover:bg-white/5 transition">
                                        <td class="py-3 px-4">
                                            <div class="flex items-center gap-2">
                                                <div class="w-6 h-6 rounded-md bg-blue-600/30 text-blue-300 font-bold text-[10px] flex items-center justify-center shrink-0">
                                                    {{ strtoupper(substr($b->player_name, 0, 1)) }}
                                                </div>
                                                <div>
                                                    <div class="font-extrabold text-white flex items-center gap-1.5">
                                                        @if($b->user_id)
                                                            <a href="{{ route('profile.user', $b->user_id) }}" class="hover:underline text-blue-400">
                                                                {{ $b->player_name }}
                                                            </a>
                                                        @else
                                                            <span>{{ $b->player_name }}</span>
                                                        @endif
                                                        @if($b->is_captain)<span class="px-1 rounded bg-red-600 text-[8px] font-black text-white">C</span>@endif
                                                    </div>
                                                    <div class="text-[9px] text-slate-400">{{ $b->player_role }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-3 px-3 text-right text-slate-300 font-mono">{{ $b->overs_bowled ?: '0.0' }}</td>
                                        <td class="py-3 px-3 text-right text-slate-300 font-mono">{{ $b->maidens }}</td>
                                        <td class="py-3 px-3 text-right text-slate-300 font-mono">{{ $b->runs_conceded }}</td>
                                        <td class="py-3 px-3 text-right font-black font-mono text-sm">
                                            @if($b->wickets_taken > 0)
                                                <span class="px-2 py-0.5 rounded-md bg-rose-600 text-white shadow-xs">{{ $b->wickets_taken }}</span>
                                            @else
                                                <span class="text-slate-400">0</span>
                                            @endif
                                        </td>
                                        <td class="py-3 px-3 text-right text-slate-300 font-mono">{{ $b->economy_rate }}</td>
                                        @if($isOwner)
                                            <td class="py-3 px-4 text-center">
                                                <button type="button" 
                                                        @click="openPlayerModal({{ json_encode($b) }})"
                                                        class="px-2.5 py-1 rounded-lg bg-white/10 hover:bg-white/20 text-white text-[10px] font-bold transition flex items-center gap-1 mx-auto">
                                                    <span>✏️ Score</span>
                                                </button>
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="py-4 text-center text-slate-500 italic">No bowlers in {{ $match->team1_name }} squad.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        </div> <!-- End of Tab 1: Scorecard -->

        <!-- ============================================================== -->
        <!-- TAB 2: BALL-BY-BALL COMMENTARY FEED (CRICBUZZ / CRICHEROES) -->
        <!-- ============================================================== -->
        <div x-show="activeMainTab === 'commentary'" x-cloak class="space-y-6">
            <div class="bg-[#131b2e] border border-white/10 rounded-3xl p-6 sm:p-8 shadow-2xl space-y-6">
                <!-- Header with Filters -->
                <div class="flex items-center justify-between gap-4 pb-4 border-b border-white/10">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-red-500 animate-pulse"></span>
                        <span class="text-xs font-black uppercase text-red-500 tracking-wider">LIVE COMMENTARY</span>
                    </div>

                    <!-- Commentary Outcome Filter Buttons -->
                    <div class="flex items-center gap-1.5 p-1 bg-black/40 rounded-xl border border-white/10 self-start sm:self-auto text-xs font-bold">
                        <button type="button" @click="commentaryFilter = 'all'"
                                :class="commentaryFilter === 'all' ? 'bg-red-600 text-white' : 'text-slate-400 hover:text-white'"
                                class="px-3 py-1.5 rounded-lg transition">
                            All Balls
                        </button>
                        <button type="button" @click="commentaryFilter = 'boundaries'"
                                :class="commentaryFilter === 'boundaries' ? 'bg-blue-600 text-white' : 'text-slate-400 hover:text-white'"
                                class="px-3 py-1.5 rounded-lg transition">
                            4s & 6s 💥
                        </button>
                        <button type="button" @click="commentaryFilter = 'wickets'"
                                :class="commentaryFilter === 'wickets' ? 'bg-rose-600 text-white' : 'text-slate-400 hover:text-white'"
                                class="px-3 py-1.5 rounded-lg transition">
                            Wickets ☝️
                        </button>
                    </div>
                </div>

                <!-- Commentary Feed List -->
                <div class="space-y-3">
                    <template x-if="filteredCommentary().length === 0">
                        <div class="py-12 text-center text-slate-500 space-y-2">
                            <span class="text-3xl block">🎙️</span>
                            <p class="text-sm font-bold text-slate-400">No commentary balls recorded yet.</p>
                            <p class="text-xs text-slate-500">Every ball bowled by scorers will appear here automatically with shot descriptions!</p>
                        </div>
                    </template>

                    <template x-for="item in filteredCommentary()" :key="item.id || (item.ball + '-' + item.time)">
                        <div class="p-4 sm:p-5 rounded-2xl bg-white/5 border border-white/5 hover:border-white/10 transition flex items-start gap-3 sm:gap-4">
                            <!-- Left: Over Badge & Time -->
                            <div class="shrink-0 text-center w-12 sm:w-14">
                                <span class="px-2 py-1 rounded-lg bg-black/50 border border-white/10 font-mono font-bold text-xs sm:text-sm text-slate-200 block" x-text="item.ball"></span>
                                <span class="text-[9px] text-slate-500 mt-1 block truncate" x-text="item.time"></span>
                            </div>

                            <!-- Outcome Chip Badge -->
                            <div class="shrink-0 w-8 h-8 sm:w-9 sm:h-9 rounded-xl flex items-center justify-center font-mono font-black text-xs sm:text-sm shadow-md"
                                 :class="{
                                     'bg-rose-600 text-white shadow-rose-600/40 animate-pulse': item.badge_type === 'wicket' || item.badge === 'W',
                                     'bg-purple-600 text-white shadow-purple-600/40': item.badge_type === 'six' || item.badge === '6',
                                     'bg-blue-600 text-white shadow-blue-600/40': item.badge_type === 'four' || item.badge === '4',
                                     'bg-amber-600 text-white shadow-amber-600/40': item.badge_type === 'extra',
                                     'bg-slate-800 text-slate-300 border border-white/10': item.badge_type === 'dot' || item.badge === '0',
                                     'bg-emerald-600/30 text-emerald-300 border border-emerald-500/30': item.badge_type === 'run' || (!['wicket','six','four','extra','dot'].includes(item.badge_type))
                                 }">
                                <span x-text="item.badge"></span>
                            </div>

                            <!-- Right: Heading, Tags, and Commentary Prose -->
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-center justify-between gap-1.5">
                                    <h5 class="text-xs sm:text-sm font-black text-white" x-text="item.heading"></h5>
                                    <div class="flex items-center gap-1.5 shrink-0">
                                        <template x-if="item.shot">
                                            <span class="px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 text-[10px] font-bold border border-emerald-500/30" x-text="'🏏 ' + item.shot"></span>
                                        </template>
                                        <span class="text-[10px] font-mono text-slate-400 bg-white/5 px-2 py-0.5 rounded-md" x-text="item.team_score"></span>
                                    </div>
                                </div>
                                <p class="text-xs sm:text-sm text-slate-300 mt-1 leading-relaxed font-sans" x-text="item.text"></p>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div> <!-- End of Tab 2: Commentary -->

        <!-- ============================================================== -->
        <!-- TAB 3: SQUADS & PLAYING XI -->
        <!-- ============================================================== -->
        <div x-show="activeMainTab === 'squads'" x-cloak class="space-y-6">

        <!-- ============================================== -->
        <!-- MANUAL MATCH SITUATION & OVERRIDE CONTROLS -->
        <!-- ============================================== -->
        @if($isOwner)
            <div class="bg-[#131b2e] border border-white/10 rounded-3xl p-6 shadow-xl space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-white/10">
                    <div>
                        <h4 class="font-black text-sm text-white">⚙️ Manual Situation & Result Overrides</h4>
                        <p class="text-slate-400 text-[11px]">Optional: customize result banners or change innings status manually.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 mb-1">Match Status</label>
                        <select x-model="scoreStatus" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-xs text-white">
                            <option value="live">🔴 Live Now</option>
                            <option value="completed">🏆 Completed</option>
                            <option value="scheduled">⏳ Scheduled</option>
                            <option value="abandoned">🚫 Abandoned</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-[10px] font-bold text-slate-400 mb-1">Match Situation / Commentary Note</label>
                        <input type="text" x-model="scoreResult" placeholder="e.g. Royal Strikers need 14 runs in 10 balls to win"
                               class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-xs text-white">
                    </div>
                </div>

                <div class="flex items-center justify-end pt-2">
                    <button type="button" @click="saveManualScore()" 
                            class="px-5 py-2 rounded-xl bg-white/10 hover:bg-white/20 text-white font-bold text-xs transition">
                        Update Situation Banner ⚡
                    </button>
                </div>
            </div>
        @endif

        <!-- ============================================== -->
        <!-- CRICHEROES TEAM SQUADS & PLAYING XI CHIPS -->
        <!-- ============================================== -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Team 1 Squad -->
            <div class="p-6 rounded-3xl bg-[#131b2e] border border-white/10 shadow-xl space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-white/10">
                    <div>
                        <span class="text-[10px] font-black uppercase text-blue-400">TEAM 1 SQUAD</span>
                        <h4 class="font-black text-base text-white mt-0.5">{{ $match->team1_name }}</h4>
                    </div>
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-600/20 text-blue-400 border border-blue-500/30">
                        {{ $match->team1Players->count() }} Players
                    </span>
                </div>

                @if($match->team1Players->isEmpty())
                    <p class="text-xs text-slate-500 italic py-2">No players added to this team squad yet.</p>
                @else
                    <div class="space-y-2">
                        @foreach($match->team1Players as $p)
                            <div class="p-3 rounded-xl bg-white/5 border border-white/5 flex items-center justify-between text-xs hover:border-blue-500/30 transition">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <div class="w-8 h-8 rounded-lg bg-blue-600 text-white font-black text-xs flex items-center justify-center shrink-0">
                                        {{ strtoupper(substr($p->player_name, 0, 1)) }}
                                    </div>
                                    <div class="min-w-0">
                                        <div class="font-bold text-white truncate flex items-center gap-1.5">
                                            @if($p->user_id)
                                                <a href="{{ route('profile.user', $p->user_id) }}" class="hover:underline text-blue-400">
                                                    {{ $p->player_name }}
                                                </a>
                                            @else
                                                <span>{{ $p->player_name }}</span>
                                            @endif
                                            @if($p->is_captain)<span class="px-1 rounded bg-red-600 text-[8px] font-black text-white">C</span>@endif
                                            @if($p->is_wicketkeeper)<span class="px-1 rounded bg-amber-600 text-[8px] font-black text-white">WK</span>@endif
                                        </div>
                                        <div class="text-[10px] text-slate-400">{{ $p->player_role }} @if($p->player_phone)• {{ $p->player_phone }}@endif</div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <div class="text-right font-mono text-[11px]">
                                        <div class="font-black text-amber-300">
                                            {{ $p->runs_scored }} <span class="text-[9px] text-slate-400">({{ $p->balls_faced }}b)</span>
                                        </div>
                                        @if($p->overs_bowled && $p->overs_bowled !== '0.0')
                                            <div class="text-[10px] text-rose-400">
                                                {{ $p->wickets_taken }}/{{ $p->runs_conceded }} <span class="text-[9px] text-slate-500">({{ $p->overs_bowled }}ov)</span>
                                            </div>
                                        @endif
                                    </div>
                                    @if($isOwner)
                                        <button type="button" 
                                                @click="openPlayerModal({{ json_encode($p) }})"
                                                class="p-1.5 rounded-lg bg-white/10 hover:bg-white/20 text-white text-xs transition" title="Edit player score">
                                            ✏️
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Team 2 Squad -->
            <div class="p-6 rounded-3xl bg-[#131b2e] border border-white/10 shadow-xl space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-white/10">
                    <div>
                        <span class="text-[10px] font-black uppercase text-rose-400">TEAM 2 SQUAD</span>
                        <h4 class="font-black text-base text-white mt-0.5">{{ $match->team2_name }}</h4>
                    </div>
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-600/20 text-rose-400 border border-rose-500/30">
                        {{ $match->team2Players->count() }} Players
                    </span>
                </div>

                @if($match->team2Players->isEmpty())
                    <p class="text-xs text-slate-500 italic py-2">No players added to this team squad yet.</p>
                @else
                    <div class="space-y-2">
                        @foreach($match->team2Players as $p)
                            <div class="p-3 rounded-xl bg-white/5 border border-white/5 flex items-center justify-between text-xs hover:border-rose-500/30 transition">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <div class="w-8 h-8 rounded-lg bg-rose-600 text-white font-black text-xs flex items-center justify-center shrink-0">
                                        {{ strtoupper(substr($p->player_name, 0, 1)) }}
                                    </div>
                                    <div class="min-w-0">
                                        <div class="font-bold text-white truncate flex items-center gap-1.5">
                                            @if($p->user_id)
                                                <a href="{{ route('profile.user', $p->user_id) }}" class="hover:underline text-rose-400">
                                                    {{ $p->player_name }}
                                                </a>
                                            @else
                                                <span>{{ $p->player_name }}</span>
                                            @endif
                                            @if($p->is_captain)<span class="px-1 rounded bg-red-600 text-[8px] font-black text-white">C</span>@endif
                                            @if($p->is_wicketkeeper)<span class="px-1 rounded bg-amber-600 text-[8px] font-black text-white">WK</span>@endif
                                        </div>
                                        <div class="text-[10px] text-slate-400">{{ $p->player_role }} @if($p->player_phone)• {{ $p->player_phone }}@endif</div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <div class="text-right font-mono text-[11px]">
                                        <div class="font-black text-amber-300">
                                            {{ $p->runs_scored }} <span class="text-[9px] text-slate-400">({{ $p->balls_faced }}b)</span>
                                        </div>
                                        @if($p->overs_bowled && $p->overs_bowled !== '0.0')
                                            <div class="text-[10px] text-rose-400">
                                                {{ $p->wickets_taken }}/{{ $p->runs_conceded }} <span class="text-[9px] text-slate-500">({{ $p->overs_bowled }}ov)</span>
                                            </div>
                                        @endif
                                    </div>
                                    @if($isOwner)
                                        <button type="button" 
                                                @click="openPlayerModal({{ json_encode($p) }})"
                                                class="p-1.5 rounded-lg bg-white/10 hover:bg-white/20 text-white text-xs transition" title="Edit player score">
                                            ✏️
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
        </div> <!-- End of Tab 3: Squads -->

    </div>

@if($isOwner)
    <!-- ============================================================== -->
    <!-- MODAL 1: WICKET DOWN - SELECT HOW OUT & NEXT BATSMAN -->
    <!-- ============================================================== -->
    <div x-cloak x-show="wicketModalOpen" 
         class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-black/80 backdrop-blur-md"
         @keydown.escape.window="wicketModalOpen = false">
        <div class="relative bg-slate-900 border-2 border-rose-500/50 rounded-3xl max-w-md w-full p-6 shadow-2xl space-y-5"
             @click.away="wicketModalOpen = false">
            <div class="flex items-center justify-between pb-3 border-b border-white/10">
                <div class="flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-lg bg-rose-600 text-white font-black flex items-center justify-center text-sm">☝️</span>
                    <h3 class="font-black text-lg text-white">Fall of Wicket!</h3>
                </div>
                <button type="button" @click="wicketModalOpen = false" class="text-slate-400 hover:text-white font-bold">✕</button>
            </div>

            <div class="space-y-4 text-xs">
                <div>
                    <label class="block text-slate-300 font-bold mb-1 uppercase tracking-wider text-[10px]">How Was Out?</label>
                    <select x-model="wicketHowOut" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2.5 text-xs text-white">
                        <option value="bowled">🔴 Bowled (Clean Bowled)</option>
                        <option value="caught">🔴 Caught</option>
                        <option value="lbw">🔴 LBW</option>
                        <option value="run out">🔴 Run Out</option>
                        <option value="stumped">🔴 Stumped</option>
                        <option value="hit wicket">🔴 Hit Wicket</option>
                    </select>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-slate-300 font-bold uppercase tracking-wider text-[10px]">Next Batsman Coming to Crease</label>
                        @if(!empty($defaultNextBatterId))
                            <span class="text-[10px] text-emerald-400 font-bold flex items-center gap-1">⚡ Auto-Selected</span>
                        @endif
                    </div>
                    <select x-model="wicketNextBatterId" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2.5 text-xs text-white">
                        @if($availableNextBatsmen->isNotEmpty())
                            @foreach($availableNextBatsmen as $index => $pl)
                                <option value="{{ $pl->id }}">
                                    {{ $pl->player_name }} ({{ $pl->player_role }}){{ $index === 0 ? ' — Next In Line (Default)' : '' }}
                                </option>
                            @endforeach
                        @else
                            <option value="">⚠️ No batsmen left (All Out)</option>
                        @endif
                    </select>
                    @if($availableNextBatsmen->isNotEmpty())
                        <p class="text-[11px] text-slate-400 mt-1.5 flex items-center gap-1">
                            <span class="text-emerald-400 font-bold">✓</span> If not selected, <strong class="text-white ml-0.5">{{ $availableNextBatsmen->first()->player_name }}</strong> will be selected automatically.
                        </p>
                    @else
                        <p class="text-[11px] text-rose-400 mt-1.5 font-medium">
                            ⚠️ All batsmen in this team have been dismissed (All Out / Inning Complete).
                        </p>
                    @endif
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-3 border-t border-white/10">
                <button type="button" @click="wicketModalOpen = false" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-400 hover:text-white">Cancel</button>
                <button type="button" @click="submitWicket()" 
                        class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-rose-600 to-red-600 text-white font-black text-xs shadow-lg shadow-rose-600/30">
                    Confirm Wicket ☝️
                </button>
            </div>
        </div>
    </div>

    <!-- ============================================================== -->
    <!-- MODAL 2: OVER COMPLETE - SELECT NEW BOWLER -->
    <!-- ============================================================== -->
    <div x-cloak x-show="bowlerModalOpen" 
         class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-black/80 backdrop-blur-md"
         @keydown.escape.window="bowlerModalOpen = false">
        <div class="relative bg-slate-900 border-2 border-blue-500/50 rounded-3xl max-w-md w-full p-6 shadow-2xl space-y-5"
             @click.away="bowlerModalOpen = false">
            <div class="flex items-center justify-between pb-3 border-b border-white/10">
                <div class="flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-lg bg-blue-600 text-white font-black flex items-center justify-center text-sm">🎯</span>
                    <div>
                        <h3 class="font-black text-lg text-white">Over Completed!</h3>
                        <p class="text-[11px] text-slate-400">Strike has rotated. Choose bowler for the next over.</p>
                    </div>
                </div>
                <button type="button" @click="bowlerModalOpen = false" class="text-slate-400 hover:text-white font-bold">✕</button>
            </div>

            <div class="space-y-4 text-xs">
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-slate-300 font-bold uppercase tracking-wider text-[10px]">Select Bowler from {{ $activeBowlingTeam }}</label>
                        @if(!empty($defaultNewBowlerId))
                            <span class="text-[10px] text-blue-400 font-bold flex items-center gap-1">🎯 Auto-Selected</span>
                        @endif
                    </div>
                    <select x-model="newBowlerId" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2.5 text-xs text-white">
                        @if($availableBowlers->isNotEmpty())
                            @foreach($availableBowlers as $index => $bw)
                                <option value="{{ $bw->id }}">
                                    {{ $bw->player_name }} • {{ $bw->overs_bowled }} ov ({{ $bw->wickets_taken }}/{{ $bw->runs_conceded }}){{ $index === 0 ? ' — Next In Line (Default)' : '' }}
                                </option>
                            @endforeach
                        @else
                            <option value="">-- Choose Bowler --</option>
                            @foreach($bowlingSquad as $bw)
                                <option value="{{ $bw->id }}">
                                    {{ $bw->player_name }} • {{ $bw->overs_bowled }} ov ({{ $bw->wickets_taken }}/{{ $bw->runs_conceded }})
                                </option>
                            @endforeach
                        @endif
                    </select>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-3 border-t border-white/10">
                <button type="button" @click="bowlerModalOpen = false" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-400 hover:text-white">Cancel</button>
                <button type="button" @click="submitNewBowler()" 
                        class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-black text-xs shadow-lg shadow-blue-600/30">
                    Start Next Over 🎯
                </button>
            </div>
        </div>
    </div>

    <!-- ============================================================== -->
    <!-- MODAL 3: OPENERS & OPENING BOWLER SELECTOR -->
    <!-- ============================================================== -->
    <div x-cloak x-show="openersModalOpen" 
         class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-black/80 backdrop-blur-md"
         @keydown.escape.window="openersModalOpen = false">
        <div class="relative bg-slate-900 border border-white/20 rounded-3xl max-w-md w-full p-6 shadow-2xl space-y-5"
             @click.away="openersModalOpen = false">
            <div class="flex items-center justify-between pb-3 border-b border-white/10">
                <div class="flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-lg bg-emerald-600 text-white font-black flex items-center justify-center text-sm">🏏</span>
                    <h3 class="font-black text-lg text-white">Select Opening Pair & Bowler</h3>
                </div>
                <button type="button" @click="openersModalOpen = false" class="text-slate-400 hover:text-white font-bold">✕</button>
            </div>

            <div class="space-y-4 text-xs">
                <div>
                    <label class="block text-slate-300 font-bold mb-1 uppercase tracking-wider text-[10px]">Striker (Batter 1)</label>
                    <select x-model="selectStrikerId" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2.5 text-xs text-white">
                        <option value="">-- Choose Striker --</option>
                        @foreach($battingSquad as $p)
                            <option value="{{ $p->id }}">{{ $p->player_name }} ({{ $p->player_role }})</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-slate-300 font-bold mb-1 uppercase tracking-wider text-[10px]">Non-Striker (Batter 2)</label>
                    <select x-model="selectNonStrikerId" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2.5 text-xs text-white">
                        <option value="">-- Choose Non-Striker --</option>
                        @foreach($battingSquad as $p)
                            <option value="{{ $p->id }}">{{ $p->player_name }} ({{ $p->player_role }})</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-slate-300 font-bold mb-1 uppercase tracking-wider text-[10px]">Opening Bowler (from {{ $activeBowlingTeam }})</label>
                    <select x-model="selectBowlerId" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2.5 text-xs text-white">
                        <option value="">-- Choose Opening Bowler --</option>
                        @foreach($bowlingSquad as $p)
                            <option value="{{ $p->id }}">{{ $p->player_name }} ({{ $p->player_role }})</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-3 border-t border-white/10">
                <button type="button" @click="openersModalOpen = false" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-400 hover:text-white">Cancel</button>
                <button type="button" @click="submitOpeners()" 
                        class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 text-white font-black text-xs shadow-lg shadow-emerald-600/30">
                    Confirm & Start ⚡
                </button>
            </div>
        </div>
    </div>

    <!-- ============================================================== -->
    <!-- MODAL 4: CRICHEROES INDIVIDUAL PLAYER STATS SCORER MODAL -->
    <!-- ============================================================== -->
    <div x-cloak x-show="playerModalOpen" 
         class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-black/80 backdrop-blur-md"
         @keydown.escape.window="playerModalOpen = false">
        <div class="relative bg-slate-900 border border-white/10 rounded-3xl max-w-lg w-full p-6 sm:p-7 shadow-2xl space-y-5"
             @click.away="playerModalOpen = false">

            <!-- Modal Header -->
            <div class="flex items-center justify-between pb-4 border-b border-white/10">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-red-600 to-rose-600 text-white font-black text-base flex items-center justify-center shadow-lg">
                        🏏
                    </div>
                    <div>
                        <span class="text-[10px] font-black uppercase text-red-500 tracking-wider">CRICHEROES PLAYER SCORER</span>
                        <h3 class="font-black text-lg text-white" x-text="selectedPlayer ? selectedPlayer.player_name : 'Player'"></h3>
                        <div class="text-[11px] text-slate-400 flex items-center gap-2">
                            <span x-text="selectedPlayer ? selectedPlayer.team_name : ''"></span>
                            <span x-text="selectedPlayer && selectedPlayer.player_phone ? '• 📱 ' + selectedPlayer.player_phone : ''"></span>
                        </div>
                    </div>
                </div>
                <button type="button" @click="playerModalOpen = false" class="text-slate-400 hover:text-white text-lg font-bold">✕</button>
            </div>

            <!-- Batting Card Inputs -->
            <div class="p-4 rounded-2xl bg-white/5 border border-white/5 space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-black uppercase text-amber-400 flex items-center gap-1.5">
                        <span>🏏 Batting Performance (Runs & Balls)</span>
                    </span>
                    <!-- Quick Increment Buttons -->
                    <div class="flex items-center gap-1 text-[10px] font-bold">
                        <button type="button" @click="editRuns = parseInt(editRuns || 0) + 1; editBalls = parseInt(editBalls || 0) + 1" class="px-2 py-0.5 rounded bg-white/10 hover:bg-white/20 text-white">+1</button>
                        <button type="button" @click="editRuns = parseInt(editRuns || 0) + 4; editBalls = parseInt(editBalls || 0) + 1; editFours = parseInt(editFours || 0) + 1" class="px-2 py-0.5 rounded bg-blue-600 text-white">+4</button>
                        <button type="button" @click="editRuns = parseInt(editRuns || 0) + 6; editBalls = parseInt(editBalls || 0) + 1; editSixes = parseInt(editSixes || 0) + 1" class="px-2 py-0.5 rounded bg-purple-600 text-white">+6</button>
                    </div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 mb-1">Runs Scored</label>
                        <input type="number" x-model="editRuns" min="0" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-white font-mono font-bold text-sm">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 mb-1">Balls Faced</label>
                        <input type="number" x-model="editBalls" min="0" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-white font-mono text-sm">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 mb-1">4s (Fours)</label>
                        <input type="number" x-model="editFours" min="0" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-white font-mono text-sm">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 mb-1">6s (Sixes)</label>
                        <input type="number" x-model="editSixes" min="0" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-white font-mono text-sm">
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-slate-400 mb-1">Dismissal Status</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <select x-model="editDismissal" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-xs text-white">
                            <option value="not out">🟢 not out</option>
                            <option value="bowled">🔴 Bowled (b Bowler)</option>
                            <option value="caught">🔴 Caught (c Fielder b Bowler)</option>
                            <option value="lbw">🔴 LBW</option>
                            <option value="run out">🔴 Run Out</option>
                            <option value="stumped">🔴 Stumped (st Keeper b Bowler)</option>
                            <option value="hit wicket">🔴 Hit Wicket</option>
                            <option value="dnb">⚪ dnb (Did not bat)</option>
                        </select>
                        <input type="text" x-model="editDismissal" placeholder="Custom: c Aman b Rohit" 
                               class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-xs text-white">
                    </div>
                </div>
            </div>

            <!-- Bowling Card Inputs -->
            <div class="p-4 rounded-2xl bg-white/5 border border-white/5 space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-black uppercase text-rose-400 flex items-center gap-1.5">
                        <span>🎯 Bowling Performance (Wickets & Overs)</span>
                    </span>
                    <!-- Quick Increment Buttons -->
                    <div class="flex items-center gap-1 text-[10px] font-bold">
                        <button type="button" @click="editWickets = parseInt(editWickets || 0) + 1" class="px-2 py-0.5 rounded bg-rose-600 text-white">+1 Wicket</button>
                    </div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 mb-1">Overs (e.g. 4.0)</label>
                        <input type="text" x-model="editOvers" placeholder="4.0" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-white font-mono text-sm">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 mb-1">Maidens</label>
                        <input type="number" x-model="editMaidens" min="0" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-white font-mono text-sm">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 mb-1">Runs Conceded</label>
                        <input type="number" x-model="editRunsConceded" min="0" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-white font-mono text-sm">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 mb-1">Wickets Taken</label>
                        <input type="number" x-model="editWickets" min="0" max="10" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-white font-mono font-bold text-sm">
                    </div>
                </div>
            </div>

            <!-- Sync Checkbox -->
            <div class="flex items-center gap-2 px-1">
                <input type="checkbox" id="syncTeam" x-model="editSyncTeam" class="rounded bg-slate-950 border-white/20 text-red-600 focus:ring-0">
                <label for="syncTeam" class="text-xs text-slate-300">
                    ⚡ Auto-update Team Total Runs & Wickets from players
                </label>
            </div>

            <!-- Modal Actions -->
            <div class="flex items-center justify-end gap-3 pt-3 border-t border-white/10">
                <button type="button" @click="playerModalOpen = false" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-400 hover:text-white">
                    Cancel
                </button>
                <button type="button" @click="savePlayerStats()" 
                        class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-500 hover:to-rose-500 text-white font-extrabold text-xs shadow-lg shadow-red-600/30 transition transform active:scale-95 flex items-center gap-2">
                    <span x-show="!isSavingPlayer">Save Player Stats & Sync Passport ⚡</span>
                    <span x-show="isSavingPlayer">Saving...</span>
                </button>
            </div>

        </div>
    </div>
@endif

</div>
@endsection
