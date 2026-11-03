@extends('layouts.app')

@section('header')
    <div class="flex items-center gap-3">
        <a href="{{ route('matches.index') }}" class="p-2 rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-700 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
        </a>
        <div>
            <span class="text-[11px] font-black uppercase text-red-600 tracking-wider">CRICHEROES SETUP</span>
            <h2 class="font-black text-xl text-gray-900 leading-tight">Start A New Cricket Match</h2>
        </div>
    </div>
@endsection

@section('content')
<div class="py-8 bg-slate-900 min-h-screen text-slate-100 font-sans" x-data="{
    matchTeam1Name: 'Royal Strikers',
    matchTeam2Name: 'Thunder Titans',
    activeSquadTab: 1,
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
    selectedPlayerOfMatch: '{{ addslashes(old('player_of_match', '')) }}',
    customPlayerOfMatch: false,

    init() {
        this.$nextTick(() => {
            this.syncPlayerOfMatchOptions();
        });
    },

    syncPlayerOfMatchOptions() {
        const select = this.$refs.playerOfMatchSelect;
        if (!select) return;
        const currentVal = (this.selectedPlayerOfMatch || '').trim();
        
        let html = '<option value="">-- Select Player of the Match (Optional) --</option>';
        
        const t1 = this.team1Squad || [];
        if (t1.length > 0) {
            html += `<optgroup label="${this.matchTeam1Name || 'Team 1'} Squad">`;
            t1.forEach(p => {
                const pName = (p.player_name || '').trim();
                if (!pName) return;
                const isSel = (pName.toLowerCase() === currentVal.toLowerCase()) ? 'selected' : '';
                html += `<option value="${pName}" ${isSel}>${pName} (${this.matchTeam1Name || 'Team 1'} • ${p.player_role || 'Player'})</option>`;
            });
            html += `</optgroup>`;
        }
        
        const t2 = this.team2Squad || [];
        if (t2.length > 0) {
            html += `<optgroup label="${this.matchTeam2Name || 'Team 2'} Squad">`;
            t2.forEach(p => {
                const pName = (p.player_name || '').trim();
                if (!pName) return;
                const isSel = (pName.toLowerCase() === currentVal.toLowerCase()) ? 'selected' : '';
                html += `<option value="${pName}" ${isSel}>${pName} (${this.matchTeam2Name || 'Team 2'} • ${p.player_role || 'Player'})</option>`;
            });
            html += `</optgroup>`;
        }

        if (currentVal) {
            const inT1 = t1.some(p => (p.player_name || '').trim().toLowerCase() === currentVal.toLowerCase());
            const inT2 = t2.some(p => (p.player_name || '').trim().toLowerCase() === currentVal.toLowerCase());
            if (!inT1 && !inT2) {
                html += `<optgroup label="Custom"><option value="${currentVal}" selected>${currentVal} (Selected)</option></optgroup>`;
            }
        }

        select.innerHTML = html;
        if (currentVal) {
            select.value = currentVal;
        }
    },

    get allSquadPlayers() {
        const list = [];
        const seen = new Set();
        (this.team1Squad || []).forEach(p => {
            const name = (p.player_name || '').trim();
            if (name && !seen.has(name)) {
                seen.add(name);
                list.push({
                    name: name,
                    role: p.player_role || 'Batter',
                    team: this.matchTeam1Name || 'Team 1',
                    teamNum: 1
                });
            }
        });
        (this.team2Squad || []).forEach(p => {
            const name = (p.player_name || '').trim();
            if (name && !seen.has(name)) {
                seen.add(name);
                list.push({
                    name: name,
                    role: p.player_role || 'Batter',
                    team: this.matchTeam2Name || 'Team 2',
                    teamNum: 2
                });
            }
        });
        return list;
    },

    toastMsg: '',
    showToast(msg) {
        this.toastMsg = msg;
        setTimeout(() => { this.toastMsg = ''; }, 3000);
    },

    openManualAdd(defaultName = '') {
        this.searchedPlayer = null;
        this.searchNotFound = true;
        this.newPlayerName = defaultName || this.playerSearchQuery.trim();
        this.newPlayerPhone = '';
        this.newPlayerRole = 'All-Rounder';
    },

    async searchPlayerWhatsApp() {
        const query = this.playerSearchQuery.trim();
        if (!query) {
            this.showToast('Please enter a WhatsApp number or player name');
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
                this.newPlayerPhone = data.player.phone || '';
            } else {
                this.searchNotFound = true;
                this.newPlayerName = isNaN(query) ? query : '';
                this.newPlayerPhone = !isNaN(query) ? query : '';
            }
        } catch(e) {
            console.error(e);
            this.showToast('Error looking up player');
        } finally {
            this.isSearchingPlayer = false;
        }
    },

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
            this.showToast(name + ' added to ' + (this.matchTeam1Name || 'Team 1') + '! 🏏');
        } else {
            this.team2Squad.push(playerObj);
            this.showToast(name + ' added to ' + (this.matchTeam2Name || 'Team 2') + '! 🏏');
        }

        this.syncPlayerOfMatchOptions();

        this.searchedPlayer = null;
        this.searchNotFound = false;
        this.playerSearchQuery = '';
        this.newPlayerName = '';
        this.newPlayerPhone = '';
        this.isCaptainTag = false;
        this.isWkTag = false;
    },

    removePlayer(teamNum, index) {
        if (teamNum === 1) {
            this.team1Squad.splice(index, 1);
        } else {
            this.team2Squad.splice(index, 1);
        }
        this.syncPlayerOfMatchOptions();
    }
}">
    <!-- Floating Toast -->
    <div x-cloak x-show="toastMsg" x-transition 
         class="fixed bottom-6 right-6 z-50 bg-gradient-to-r from-emerald-600 to-teal-600 text-white px-5 py-3 rounded-2xl shadow-2xl font-bold text-sm flex items-center gap-2 border border-white/20">
        <span class="w-2 h-2 rounded-full bg-white animate-pulse"></span>
        <span x-text="toastMsg"></span>
    </div>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-[#131b2e] border border-white/10 rounded-3xl p-6 sm:p-8 shadow-2xl">
            <form method="POST" action="{{ route('matches.store') }}" class="space-y-5">
                @csrf

                <!-- Hidden inputs for Squad Players -->
                <input type="hidden" name="team1_players" :value="JSON.stringify(team1Squad)">
                <input type="hidden" name="team2_players" :value="JSON.stringify(team2Squad)">

                <!-- Tournament Name -->
                <div>
                    <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1">Tournament / League / Series</label>
                    <input type="text" name="tournament_name" value="{{ old('tournament_name', 'Gully Premier League 2026') }}" required
                           class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2.5 text-sm text-white focus:ring-2 focus:ring-red-500 focus:outline-none">
                </div>

                <!-- Teams Row -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1">Team 1 Name</label>
                        <input type="text" name="team1_name" x-model="matchTeam1Name" value="{{ old('team1_name', 'Royal Strikers') }}" placeholder="e.g. Royal Strikers" required
                               class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2.5 text-sm text-white focus:ring-2 focus:ring-red-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1">Team 2 Name</label>
                        <input type="text" name="team2_name" x-model="matchTeam2Name" value="{{ old('team2_name', 'Thunder Titans') }}" placeholder="e.g. Thunder Titans" required
                               class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2.5 text-sm text-white focus:ring-2 focus:ring-red-500 focus:outline-none">
                    </div>
                </div>

                <!-- Overs, Ball Type, Status -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1">Total Overs</label>
                        <select name="total_overs" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2.5 text-xs text-white">
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
                        <select name="ball_type" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2.5 text-xs text-white">
                            <option value="Tennis Ball" selected>🎾 Tennis Ball</option>
                            <option value="Leather Ball">🏏 Leather Ball</option>
                            <option value="Tape Ball">⚡ Tape Ball</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1">Match State</label>
                        <select name="status" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2.5 text-xs text-white">
                            <option value="live">🔴 Live Now</option>
                            <option value="scheduled" selected>⏳ Scheduled</option>
                            <option value="completed">🏆 Completed</option>
                        </select>
                    </div>
                </div>

                <!-- Date & Venue -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1">Match Date</label>
                        <input type="date" name="match_date" value="{{ date('Y-m-d') }}" required
                               class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2.5 text-sm text-white focus:ring-2 focus:ring-red-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1">Ground / Venue</label>
                        <input type="text" name="venue" placeholder="e.g. Shivaji Park Ground"
                               class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2.5 text-sm text-white focus:ring-2 focus:ring-red-500 focus:outline-none">
                    </div>
                </div>

                <!-- Toss Details -->
                <div>
                    <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1">Toss Details (Optional)</label>
                    <input type="text" name="toss_decision" placeholder="e.g. Royal Strikers won toss & elected to bat"
                           class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2.5 text-sm text-white focus:ring-2 focus:ring-red-500 focus:outline-none">
                </div>

                <!-- Player of the Match Dropdown (Populated Dynamically from Both Teams) -->
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider">
                            🏆 Player of the Match (Optional)
                        </label>
                        <button type="button" 
                                @click="customPlayerOfMatch = !customPlayerOfMatch"
                                class="text-[11px] text-amber-400 hover:text-amber-300 font-semibold underline">
                            <span x-text="customPlayerOfMatch ? '▾ Choose from Squads Dropdown' : '✍️ Type custom name'"></span>
                        </button>
                    </div>

                    <!-- Dropdown Mode -->
                    <div x-show="!customPlayerOfMatch">
                        <div class="relative">
                            <select name="player_of_match" 
                                    x-ref="playerOfMatchSelect"
                                    x-model="selectedPlayerOfMatch"
                                    @change="selectedPlayerOfMatch = $event.target.value"
                                    class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2.5 text-sm text-white focus:ring-2 focus:ring-red-500 focus:outline-none appearance-none pr-10">
                                <option value="">-- Select Player of the Match (Optional) --</option>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3.5 text-slate-400 text-xs">
                                ▼
                            </div>
                        </div>
                        <p class="text-[11px] text-slate-400 mt-1 flex items-center justify-between">
                            <span>Players will appear in the dropdown once added to both team squads.</span>
                            <span class="text-emerald-400 font-mono font-bold" x-text="((team1Squad.length + team2Squad.length)) + ' players in squads'"></span>
                        </p>
                    </div>

                    <!-- Custom Text Input Mode -->
                    <div x-show="customPlayerOfMatch" style="display: none;">
                        <input type="text" 
                               :name="customPlayerOfMatch ? 'player_of_match' : ''"
                               x-model="selectedPlayerOfMatch" 
                               placeholder="Enter custom Player of the Match name"
                               class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2.5 text-sm text-white focus:ring-2 focus:ring-red-500 focus:outline-none">
                    </div>
                </div>

                <!-- Live Stream Link (YouTube / Facebook / Twitch) -->
                <div>
                    <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1 flex items-center justify-between">
                        <span class="flex items-center gap-1.5">
                            <span class="text-red-500">🔴</span>
                            <span>Live Stream Link (YouTube Live / Facebook Live / Twitch)</span>
                        </span>
                        <span class="text-[10px] text-slate-400 lowercase font-normal">optional</span>
                    </label>
                    <div class="relative">
                        <input type="url" name="stream_url" value="{{ old('stream_url') }}"
                               placeholder="e.g. https://www.youtube.com/watch?v=... or https://facebook.com/.../videos/..."
                               class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2.5 text-sm text-white focus:ring-2 focus:ring-red-500 focus:outline-none pl-9">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-red-500 text-sm">
                            🎥
                        </span>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">
                        Paste YouTube Live or Facebook Live stream URL here if broadcasting this match.
                    </p>
                </div>

                <!-- ============================================================== -->
                <!-- CRICHEROES SQUAD BUILDER & WHATSAPP LOOKUP SECTION -->
                <!-- ============================================================== -->
                <div class="pt-5 border-t border-white/10 space-y-4">
                    <div>
                        <span class="text-xs font-black uppercase text-emerald-400 flex items-center gap-1.5">
                            <span>📱 ADD PLAYERS VIA WHATSAPP / PHONE</span>
                        </span>
                        <p class="text-[11px] text-slate-400 mt-0.5">
                            Enter player's WhatsApp number to auto-fetch past cricket career stats & add to playing squad.
                        </p>
                    </div>

                    <!-- Team Selector Tabs -->
                    <div class="flex items-center gap-2 p-1 bg-black/40 rounded-xl border border-white/5">
                        <button type="button" 
                                @click="activeSquadTab = 1"
                                :class="activeSquadTab === 1 ? 'bg-red-600 text-white font-bold' : 'text-slate-400 hover:text-white'"
                                class="flex-1 py-2 rounded-lg text-xs transition flex items-center justify-center gap-1.5">
                            <span x-text="matchTeam1Name || 'Team 1'"></span>
                            <span class="px-1.5 py-0.2 rounded-full bg-black/30 text-[10px]" x-text="team1Squad.length"></span>
                        </button>
                        <button type="button" 
                                @click="activeSquadTab = 2"
                                :class="activeSquadTab === 2 ? 'bg-red-600 text-white font-bold' : 'text-slate-400 hover:text-white'"
                                class="flex-1 py-2 rounded-lg text-xs transition flex items-center justify-center gap-1.5">
                            <span x-text="matchTeam2Name || 'Team 2'"></span>
                            <span class="px-1.5 py-0.2 rounded-full bg-black/30 text-[10px]" x-text="team2Squad.length"></span>
                        </button>
                    </div>

                    <!-- WhatsApp / Phone Search Input -->
                    <div class="flex items-center gap-2">
                        <div class="relative flex-1">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-emerald-400 text-sm">
                                💬
                            </span>
                            <input type="text" x-model="playerSearchQuery" 
                                   @keydown.enter.prevent="searchPlayerWhatsApp()"
                                   placeholder="Enter WhatsApp/Phone (e.g. 9876543210, 9812345678) or Player Name"
                                   class="w-full bg-slate-950 border border-white/10 rounded-xl pl-9 pr-3 py-2 text-xs text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                        </div>
                        <button type="button" @click="searchPlayerWhatsApp()" 
                                class="px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shrink-0 transition flex items-center gap-1.5 shadow-md">
                            <span x-show="!isSearchingPlayer">Fetch Record 🔍</span>
                            <span x-show="isSearchingPlayer" class="animate-spin text-xs">⏳</span>
                        </button>
                        <button type="button" @click="openManualAdd(playerSearchQuery)"
                                title="Directly add a new player without searching directory"
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
                                <div class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-blue-600/20 border border-blue-500/30 text-xs text-white">
                                    <span class="font-bold" x-text="p.player_name"></span>
                                    <template x-if="p.is_captain"><span class="px-1 rounded bg-red-600 text-[9px] font-black">C</span></template>
                                    <template x-if="p.is_wicketkeeper"><span class="px-1 rounded bg-amber-600 text-[9px] font-black">WK</span></template>
                                    <button type="button" @click="removePlayer(1, index)" class="text-slate-400 hover:text-red-400 ml-1 font-bold">✕</button>
                                </div>
                            </template>
                        </div>

                        <!-- Team 2 Squad Chips -->
                        <div x-show="activeSquadTab === 2" class="flex flex-wrap gap-2">
                            <template x-if="team2Squad.length === 0">
                                <div class="text-[11px] text-slate-500 italic py-1">No players added to this team yet. Search WhatsApp above to add.</div>
                            </template>
                            <template x-for="(p, index) in team2Squad" :key="index">
                                <div class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-rose-600/20 border border-rose-500/30 text-xs text-white">
                                    <span class="font-bold" x-text="p.player_name"></span>
                                    <template x-if="p.is_captain"><span class="px-1 rounded bg-red-600 text-[9px] font-black">C</span></template>
                                    <template x-if="p.is_wicketkeeper"><span class="px-1 rounded bg-amber-600 text-[9px] font-black">WK</span></template>
                                    <button type="button" @click="removePlayer(2, index)" class="text-slate-400 hover:text-red-400 ml-1 font-bold">✕</button>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-5 border-t border-white/10">
                    <a href="{{ route('matches.index') }}" class="px-4 py-2 text-xs font-bold text-slate-400 hover:text-white">Cancel</a>
                    <button type="submit" 
                            class="px-8 py-3 rounded-xl bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-500 hover:to-rose-500 text-white font-extrabold text-xs shadow-lg shadow-red-600/30 transition transform active:scale-95">
                        Start Match & Save Squad 🏏
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
