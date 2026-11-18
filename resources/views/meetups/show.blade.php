@extends('layouts.app')

@section('title', $meetup->title . ' | LiveCricket Match Meetup')

@section('header')
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="flex items-center gap-3">
            <a href="{{ route('meetups.index') }}"
               class="p-2 rounded-xl bg-slate-100 dark:bg-white/10 text-slate-700 dark:text-slate-300 hover:text-white hover:bg-slate-200 dark:hover:bg-white/20 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <div>
                <div class="flex items-center gap-2">
                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-black uppercase tracking-wider
                        {{ $meetup->status === 'open' ? 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30' : ($meetup->status === 'full' ? 'bg-amber-500/15 text-amber-400 border border-amber-500/30' : 'bg-slate-800 text-slate-400') }}">
                        @if($meetup->status === 'open')
                            🟢 Open ({{ $slotsLeft }} Slots Vacant)
                        @elseif($meetup->status === 'full')
                            🟡 Squad Full
                        @else
                            {{ ucfirst($meetup->status) }}
                        @endif
                    </span>
                    <span class="text-xs text-slate-400">📍 {{ $meetup->city }}</span>
                </div>
                <h2 class="font-black text-xl sm:text-2xl text-slate-900 dark:text-white leading-tight mt-0.5">
                    {{ $meetup->title }}
                </h2>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <!-- Share on WhatsApp -->
            <a href="https://api.whatsapp.com/send?text={{ urlencode('🏏 Cricket Match on ' . $meetup->formatted_date . ' @ ' . $meetup->location_name . ' (' . $meetup->city . ')! ' . $slotsLeft . ' slots left. Join here: ' . route('meetups.show', $meetup)) }}"
               target="_blank"
               class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-md transition">
                <span>📲 Share Invite</span>
            </a>

            @if($isOrganizer)
                <form action="{{ route('meetups.destroy', $meetup) }}" method="POST"
                      onsubmit="return confirm('Are you sure you want to cancel and delete this match meetup?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="px-3.5 py-2 rounded-xl bg-rose-500/15 hover:bg-rose-500/25 border border-rose-500/30 text-rose-400 font-bold text-xs transition">
                        🗑️ Cancel Match
                    </button>
                </form>
            @endif
        </div>
    </div>
@endsection

@section('content')
<div class="py-6 sm:py-8 bg-slate-950 min-h-screen text-slate-100 font-sans" 
     x-data="{ 
         joinModal: false,
         careerModalOpen: false,
         careerLoading: false,
         careerError: '',
         careerTab: 'batting',
         careerData: null,
         async openCareerModal(participantId) {
             if (!participantId) return;
             this.careerModalOpen = true;
             this.careerLoading = true;
             this.careerError = '';
             this.careerTab = 'batting';
             this.careerData = null;
             try {
                 const res = await fetch(`/meetups/{{ $meetup->id }}/participants/${participantId}/career`, {
                     headers: { 'Accept': 'application/json' }
                 });
                 if (!res.ok) throw new Error('Could not load career statistics');
                 this.careerData = await res.json();
             } catch (e) {
                 console.error('Failed to load career data:', e);
                 this.careerError = 'Unable to load player career stats at the moment.';
             } finally {
                 this.careerLoading = false;
             }
         },
         async openUserCareer(userId) {
             if (!userId) return;
             this.careerModalOpen = true;
             this.careerLoading = true;
             this.careerError = '';
             this.careerTab = 'batting';
             this.careerData = null;
             try {
                 const res = await fetch(`/users/${userId}/career`, {
                     headers: { 'Accept': 'application/json' }
                 });
                 if (!res.ok) throw new Error('Could not load user career statistics');
                 this.careerData = await res.json();
             } catch (e) {
                 console.error('Failed to load career data:', e);
                 this.careerError = 'Unable to load player career stats at the moment.';
             } finally {
                 this.careerLoading = false;
             }
         }
     }">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

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
        <!-- TOP HERO CARD: MATCH OVERVIEW & KEY DETAILS -->
        <!-- ============================================================== -->
        <div class="p-5 sm:p-7 rounded-3xl bg-[#131b2e] border border-white/10 shadow-2xl relative overflow-hidden">
            <div class="absolute -top-16 -right-16 w-56 h-56 rounded-full bg-amber-500/10 blur-3xl pointer-events-none"></div>

            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 relative z-10">
                <!-- Left Details -->
                <div class="space-y-4 flex-1">
                    <!-- Badges Row -->
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        <!-- Date Badge -->
                        <span class="px-3 py-1 rounded-xl bg-amber-500/15 border border-amber-500/30 text-amber-300 font-black flex items-center gap-1.5">
                            📅 {{ $meetup->formatted_date }} ({{ $meetup->timing_badge }})
                        </span>

                        <!-- Time Badge -->
                        <span class="px-3 py-1 rounded-xl bg-white/5 border border-white/10 text-white font-bold flex items-center gap-1.5">
                            ⏰ {{ $meetup->match_time }}
                        </span>

                        <!-- Ball Type Badge -->
                        <span class="px-3 py-1 rounded-xl bg-blue-500/15 border border-blue-500/30 text-blue-300 font-bold">
                            @if($meetup->ball_type === 'tennis') 🎾 Tennis Ball
                            @elseif($meetup->ball_type === 'leather') 🏏 Leather Ball
                            @elseif($meetup->ball_type === 'tape') ⚾ Tape Ball
                            @elseif($meetup->ball_type === 'box_turf') 🟡 Box Turf
                            @endif
                        </span>

                        <!-- Format Badge -->
                        <span class="px-3 py-1 rounded-xl bg-purple-500/15 border border-purple-500/30 text-purple-300 font-bold capitalize">
                            {{ str_replace('_', ' ', $meetup->match_format) }}
                        </span>

                        <!-- Skill Level -->
                        <span class="px-3 py-1 rounded-xl bg-emerald-500/15 border border-emerald-500/30 text-emerald-300 font-bold capitalize">
                            🌟 {{ $meetup->skill_level === 'all' ? 'All Skill Levels' : ucfirst($meetup->skill_level) }}
                        </span>
                    </div>

                    <!-- Venue & Location Info -->
                    <div>
                        <h1 class="text-2xl sm:text-3xl font-black text-white leading-tight">
                            {{ $meetup->title }}
                        </h1>
                        <div class="flex flex-wrap items-center gap-y-2 gap-x-3 text-slate-300 text-sm mt-2">
                            <span class="font-bold text-amber-400">🏟️ {{ $meetup->location_name }}</span>
                            <span>•</span>
                            <span class="text-slate-400">📍 {{ $meetup->city }}</span>
                            @if($meetup->location_address)
                                <span>•</span>
                                <span class="text-slate-400 text-xs">{{ $meetup->location_address }}</span>
                            @endif
                            <a href="{{ $meetup->google_maps_link }}" target="_blank"
                               class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-emerald-500/15 hover:bg-emerald-500/25 border border-emerald-500/30 text-emerald-300 text-xs font-bold transition shadow-xs">
                                <span>🗺️ Google Maps Directions</span>
                                <span class="text-[10px]">↗</span>
                            </a>
                        </div>
                    </div>

                    <!-- Cost & Circle -->
                    <div class="flex flex-wrap items-center gap-4 text-xs font-semibold text-slate-400">
                        <div class="flex items-center gap-1.5">
                            <span class="text-slate-500">💰 Match Cost:</span>
                            <span class="text-emerald-400 font-black text-sm">{{ $meetup->cost_per_player ?: 'Free' }}</span>
                        </div>
                        @if($meetup->circle)
                            <div class="flex items-center gap-1.5">
                                <span class="text-slate-500">⭕ Circle:</span>
                                <span class="text-indigo-400 font-bold">{{ $meetup->circle->name }}</span>
                            </div>
                        @endif
                        @if($meetup->ground)
                            <div class="flex items-center gap-1.5">
                                <span class="text-slate-500">Ground Profile:</span>
                                <a href="{{ route('grounds.show', $meetup->ground) }}" class="text-amber-400 hover:underline">
                                    View Ground Info →
                                </a>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Right Action Box: RSVP / WhatsApp -->
                <div class="w-full lg:w-72 bg-slate-900/90 rounded-2xl border border-white/10 p-5 space-y-4 shrink-0 shadow-inner">
                    <!-- Vacancy Meter -->
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-bold text-slate-300">Squad Capacity</span>
                            <span class="font-black {{ $slotsLeft > 0 ? 'text-amber-400' : 'text-slate-400' }}">
                                {{ $meetup->activeParticipants->count() }} / {{ $meetup->total_players }} Players
                            </span>
                        </div>
                        @php
                            $progress = min(100, round(($meetup->activeParticipants->count() / max(1, $meetup->total_players)) * 100));
                        @endphp
                        <div class="w-full h-2.5 rounded-full bg-slate-800 overflow-hidden">
                            <div class="h-full bg-gradient-to-r from-amber-500 to-orange-500 rounded-full transition-all duration-500"
                                 style="width: {{ $progress }}%"></div>
                        </div>
                        <div class="text-[11px] text-slate-400 text-center font-bold">
                            @if($slotsLeft > 0)
                                🔥 <span class="text-amber-300 font-black">{{ $slotsLeft }} slots</span> still vacant!
                            @else
                                🔒 Squad is fully packed
                            @endif
                        </div>
                    </div>

                    <!-- Join / Leave Buttons -->
                    @auth
                        @if($isJoined)
                            <div class="space-y-2">
                                <div class="w-full py-2.5 px-3 rounded-xl bg-emerald-500/20 border border-emerald-500/40 text-emerald-300 text-xs font-black text-center flex items-center justify-center gap-2">
                                    <span>✅ You are in this squad!</span>
                                </div>
                                @if(!$isOrganizer)
                                    <form action="{{ route('meetups.leave', $meetup) }}" method="POST"
                                          onsubmit="return confirm('Are you sure you want to withdraw your slot? Other players will be able to take it.');">
                                        @csrf
                                        <button type="submit"
                                                class="w-full py-2 px-3 rounded-xl bg-white/5 hover:bg-rose-500/20 text-slate-400 hover:text-rose-300 text-xs font-bold transition">
                                            Leave Squad / Withdraw Slot
                                        </button>
                                    </form>
                                @endif
                            </div>
                        @elseif($slotsLeft > 0)
                            <button type="button" @click="joinModal = true"
                                    class="w-full py-3.5 px-4 rounded-xl bg-gradient-to-r from-amber-500 via-orange-500 to-amber-600 hover:from-amber-400 hover:to-orange-500 text-slate-950 font-black text-sm uppercase tracking-wider shadow-lg shadow-amber-500/30 transition transform active:scale-95 cursor-pointer flex items-center justify-center gap-2">
                                <span>🙋‍♂️ Join Squad / I'm In</span>
                            </button>
                        @else
                            <div class="w-full py-3 px-4 rounded-xl bg-slate-800 text-slate-400 text-xs font-bold text-center">
                                ⚠️ All player slots are full
                            </div>
                        @endif
                    @else
                        <a href="{{ route('login') }}"
                           class="w-full py-3.5 px-4 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 text-slate-950 font-black text-sm uppercase tracking-wider shadow-md transition flex items-center justify-center gap-2">
                            <span>Sign In to Join Match</span>
                        </a>
                    @endauth

                    <!-- WhatsApp & Call Connect with Organizer -->
                    <div class="pt-2 border-t border-white/10 space-y-2">
                        @if($meetup->contact_whatsapp)
                            @php
                                $waText = urlencode("Hi {$meetup->user->name}, I saw your cricket meetup '{$meetup->title}' on LiveCricket. I want to play in this match!");
                                $cleanWa = preg_replace('/[^0-9]/', '', $meetup->contact_whatsapp);
                                if (strlen($cleanWa) == 10) $cleanWa = '91' . $cleanWa;
                            @endphp
                            <a href="https://wa.me/{{ $cleanWa }}?text={{ $waText }}" target="_blank"
                               class="w-full py-2.5 px-3 rounded-xl bg-emerald-600/20 hover:bg-emerald-600/30 border border-emerald-500/30 text-emerald-300 text-xs font-black transition flex items-center justify-center gap-2">
                                <span>💬 Chat on WhatsApp</span>
                            </a>
                        @endif

                        @if($meetup->contact_phone)
                            <a href="tel:{{ $meetup->contact_phone }}"
                               class="w-full py-2 px-3 rounded-xl bg-white/5 hover:bg-white/10 text-slate-300 text-xs font-bold transition flex items-center justify-center gap-2">
                                <span>📞 Call Organizer ({{ $meetup->contact_phone }})</span>
                            </a>
                        @endif

                        @if($meetup->whatsapp_group_link)
                            <a href="{{ $meetup->whatsapp_group_link }}" target="_blank"
                               class="w-full py-2 px-3 rounded-xl bg-indigo-500/15 hover:bg-indigo-500/25 border border-indigo-500/30 text-indigo-300 text-xs font-bold transition flex items-center justify-center gap-2">
                                <span>👥 Join Match Group</span>
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- SQUAD LINEUP (PLAYERS REGISTERED) -->
        <!-- ============================================================== -->
        <div class="p-5 sm:p-7 rounded-3xl bg-[#131b2e] border border-white/10 shadow-2xl space-y-5">
            <div class="flex items-center justify-between border-b border-white/10 pb-4">
                <div>
                    <h3 class="font-black text-lg sm:text-xl text-white flex items-center gap-2">
                        <span>🏏 Confirmed Match Squad</span>
                        <span class="px-2.5 py-0.5 rounded-full bg-amber-500/20 text-amber-400 text-xs font-black">
                            {{ $meetup->activeParticipants->count() }} Players
                        </span>
                    </h3>
                    <p class="text-xs text-slate-400 mt-0.5">Players who have confirmed their spot for this match</p>
                </div>

                @if(!$isJoined && $slotsLeft > 0)
                    @auth
                        <button type="button" @click="joinModal = true"
                                class="hidden sm:inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-black text-xs transition">
                            ➕ Join Squad
                        </button>
                    @endauth
                @endif
            </div>

            <!-- Players Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3.5">
                @foreach($meetup->activeParticipants as $index => $participant)
                    @php
                        $isThisOrganizer = $participant->user_id === $meetup->user_id;
                    @endphp
                    <div class="p-3.5 rounded-2xl bg-slate-900 border border-white/10 flex items-center justify-between gap-3 relative overflow-hidden group hover:border-amber-500/40 hover:bg-slate-850 transition shadow-lg">
                        <!-- Clickable Player Info: Opens Career Stats Modal -->
                        <button type="button" 
                                @click="openCareerModal({{ $participant->id }})"
                                class="flex items-center gap-3 min-w-0 flex-1 group/player cursor-pointer text-left focus:outline-none" 
                                title="Click to view {{ $participant->player_name }}'s Career Stats & Profile">
                            <!-- Avatar / Number -->
                            <div class="w-10 h-10 rounded-xl {{ $isThisOrganizer ? 'bg-amber-500 text-slate-950 font-black' : 'bg-slate-800 text-white font-bold' }} flex items-center justify-center text-sm shrink-0 shadow-md group-hover/player:scale-105 group-hover/player:ring-2 group-hover/player:ring-amber-400/50 transition">
                                @if($isThisOrganizer)
                                    👑
                                @else
                                    #{{ $index + 1 }}
                                @endif
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="font-bold text-sm text-white truncate flex items-center gap-1.5 group-hover/player:text-amber-400 transition-colors">
                                    <span class="truncate">{{ $participant->player_name }}</span>
                                    @if($isThisOrganizer)
                                        <span class="px-1.5 py-0.2 rounded text-[10px] font-black bg-amber-500/20 text-amber-300 shrink-0">Host</span>
                                    @endif
                                    <span class="text-xs text-amber-400 opacity-60 group-hover/player:opacity-100 group-hover/player:translate-x-0.5 transition shrink-0">📊</span>
                                </div>
                                <div class="text-xs text-slate-400 truncate flex items-center gap-1 mt-0.5">
                                    <span>{{ $participant->player_role ?: 'All-Rounder' }}</span>
                                    @if($participant->notes)
                                        <span class="text-slate-500 truncate">• {{ $participant->notes }}</span>
                                    @endif
                                </div>
                            </div>
                        </button>

                        <div class="flex items-center gap-1 shrink-0">
                            <!-- Stats Button -->
                            <button type="button"
                                    @click="openCareerModal({{ $participant->id }})"
                                    title="View Career Stats"
                                    class="inline-flex items-center gap-1 text-[11px] font-bold text-amber-400 hover:text-amber-300 py-1.5 px-2.5 rounded-lg bg-amber-500/10 hover:bg-amber-500/20 border border-amber-500/20 transition cursor-pointer">
                                <span>Stats</span>
                                <span class="text-[10px]">📊</span>
                            </button>

                            <!-- Organizer Remove Option -->
                            @if($isOrganizer && !$isThisOrganizer)
                                <form action="{{ route('meetups.participants.remove', [$meetup, $participant]) }}" method="POST"
                                      onsubmit="return confirm('Remove {{ $participant->player_name }} from squad?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            title="Remove player"
                                            class="p-1.5 rounded-lg text-slate-500 hover:text-rose-400 hover:bg-white/5 text-xs transition">
                                        ✕
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach

                <!-- Vacant Slot Placeholders -->
                @for($i = 0; $i < min(6, $slotsLeft); $i++)
                    <div class="p-3.5 rounded-2xl border-2 border-dashed border-white/10 flex items-center justify-between gap-3 text-slate-500 bg-white/[0.01]">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl border border-dashed border-white/20 flex items-center justify-center text-xs font-bold text-slate-600">
                                ?
                            </div>
                            <div>
                                <div class="font-bold text-xs text-slate-400">Vacant Slot</div>
                                <div class="text-[11px] text-slate-600">Looking for player...</div>
                            </div>
                        </div>
                        @auth
                            @if(!$isJoined)
                                <button type="button" @click="joinModal = true"
                                        class="text-xs font-black text-amber-400 hover:text-amber-300 px-2 py-1 rounded bg-amber-500/10">
                                    + Claim
                                </button>
                            @endif
                        @endauth
                    </div>
                @endfor
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- MATCH DETAILS, VENUE & ORGANIZER CARDS -->
        <!-- ============================================================== -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Ground Location & Google Maps Card -->
            <div class="p-5 sm:p-7 rounded-3xl bg-[#131b2e] border border-white/10 shadow-xl space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="font-black text-base text-white flex items-center gap-2">
                        <span>📍 Ground & Location</span>
                    </h3>
                    @if($meetup->ground)
                        <a href="{{ route('grounds.show', $meetup->ground) }}" class="text-[11px] text-amber-400 font-bold hover:underline">
                            Ground Info →
                        </a>
                    @endif
                </div>

                <div class="bg-slate-900/60 p-4 rounded-2xl border border-white/5 space-y-1.5">
                    <div class="font-black text-white text-base">
                        🏟️ {{ $meetup->location_name }}
                    </div>
                    @if($meetup->location_address)
                        <div class="text-xs text-slate-300">
                            {{ $meetup->location_address }}
                        </div>
                    @endif
                    <div class="text-xs text-slate-400 font-bold">
                        📍 City: {{ $meetup->city }}
                    </div>
                </div>

                <a href="{{ $meetup->google_maps_link }}" target="_blank"
                   class="w-full py-3 px-4 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-black text-xs transition flex items-center justify-center gap-2 shadow-lg shadow-emerald-600/25">
                    <span>🗺️ Open in Google Maps</span>
                    <span class="text-xs">↗</span>
                </a>
            </div>

            <!-- Instructions & Rules -->
            <div class="p-5 sm:p-7 rounded-3xl bg-[#131b2e] border border-white/10 shadow-xl space-y-3">
                <h3 class="font-black text-base text-white flex items-center gap-2">
                    <span>📋 Match Notes & Ground Rules</span>
                </h3>
                @if($meetup->description)
                    <p class="text-xs sm:text-sm text-slate-300 leading-relaxed whitespace-pre-line bg-slate-900/60 p-4 rounded-2xl border border-white/5">
                        {{ $meetup->description }}
                    </p>
                @else
                    <p class="text-xs text-slate-500 italic">No specific ground rules added. Reach on time with sports kit.</p>
                @endif

                <div class="space-y-1.5 pt-2 text-xs text-slate-400">
                    <div class="flex items-center gap-2">
                        <span class="text-amber-400">⏱️</span>
                        <span>Please arrive 15 minutes before reporting time ({{ $meetup->match_time }}).</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-emerald-400">🤝</span>
                        <span>Maintain sportsmanship and enjoy competitive cricket!</span>
                    </div>
                </div>
            </div>

            <!-- Organizer Info Card -->
            <div class="p-5 sm:p-7 rounded-3xl bg-[#131b2e] border border-white/10 shadow-xl space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="font-black text-base text-white flex items-center gap-2">
                        <span>👑 Organizer Information</span>
                    </h3>
                    @if($meetup->user_id)
                        <button type="button" 
                                @click="openUserCareer({{ $meetup->user_id }})"
                                class="text-[11px] text-amber-400 font-bold hover:underline flex items-center gap-1 cursor-pointer">
                            <span>Career Stats</span>
                            <span>📊</span>
                        </button>
                    @endif
                </div>

                @if($meetup->user_id)
                    <button type="button"
                            @click="openUserCareer({{ $meetup->user_id }})"
                            title="Click to view {{ $meetup->user->name }}'s Career Stats & Profile"
                            class="w-full text-left flex items-center gap-3.5 bg-slate-900/60 hover:bg-slate-900/90 p-4 rounded-2xl border border-white/5 hover:border-amber-500/30 transition group cursor-pointer">
                        <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-amber-500 to-orange-500 text-slate-950 font-black text-lg flex items-center justify-center shrink-0 shadow-lg group-hover:scale-105 transition-transform">
                            {{ strtoupper(substr($meetup->user->name, 0, 1)) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="font-black text-white text-base truncate group-hover:text-amber-400 transition-colors flex items-center gap-1.5">
                                <span>{{ $meetup->user->name }}</span>
                                <span class="text-xs text-amber-400 opacity-60 group-hover:opacity-100 group-hover:translate-x-0.5 transition">📊</span>
                            </div>
                            <div class="text-xs text-slate-400">Match Organizer • {{ $meetup->city }}</div>
                        </div>
                    </button>
                @else
                    <div class="flex items-center gap-3.5 bg-slate-900/60 p-4 rounded-2xl border border-white/5">
                        <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-amber-500 to-orange-500 text-slate-950 font-black text-lg flex items-center justify-center shrink-0 shadow-lg">
                            {{ strtoupper(substr($meetup->user->name, 0, 1)) }}
                        </div>
                        <div class="min-w-0">
                            <div class="font-black text-white text-base truncate">{{ $meetup->user->name }}</div>
                            <div class="text-xs text-slate-400">Match Organizer • {{ $meetup->city }}</div>
                        </div>
                    </div>
                @endif

                <div class="space-y-2 pt-1">
                    @if($meetup->contact_whatsapp)
                        <a href="https://wa.me/{{ $cleanWa }}?text={{ $waText }}" target="_blank"
                           class="w-full py-2.5 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs transition flex items-center justify-center gap-2 shadow-md">
                            <span>💬 Message Organizer on WhatsApp</span>
                        </a>
                    @endif

                    @if($meetup->contact_phone)
                        <a href="tel:{{ $meetup->contact_phone }}"
                           class="w-full py-2.5 px-4 rounded-xl bg-slate-900 hover:bg-slate-800 border border-white/10 text-white font-bold text-xs transition flex items-center justify-center gap-2">
                            <span>📞 Call {{ $meetup->contact_phone }}</span>
                        </a>
                    @endif
                </div>
            </div>
        </div>

    </div>

    <!-- ============================================================== -->
    <!-- MODAL: JOIN SQUAD FORM (1-TAP RSVP) -->
    <!-- ============================================================== -->
    <div x-show="joinModal"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
         @keydown.escape.window="joinModal = false">
        
        <div class="w-full max-w-md rounded-3xl bg-[#131b2e] border border-white/15 p-6 space-y-5 shadow-2xl relative"
             @click.away="joinModal = false">
            
            <div class="flex items-center justify-between border-b border-white/10 pb-3">
                <div class="flex items-center gap-2">
                    <span class="text-xl">🏏</span>
                    <h3 class="font-black text-lg text-white">Join Match Squad</h3>
                </div>
                <button type="button" @click="joinModal = false" class="text-slate-400 hover:text-white font-bold text-sm">
                    ✕
                </button>
            </div>

            <form action="{{ route('meetups.join', $meetup) }}" method="POST" class="space-y-4">
                @csrf

                <!-- Player Role Select -->
                <div>
                    <label for="modal_player_role" class="block text-xs font-black uppercase tracking-wider text-slate-300 mb-1.5">
                        Your Playing Role <span class="text-rose-400">*</span>
                    </label>
                    <select id="modal_player_role" name="player_role" required
                            class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-white/10 text-white text-sm focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition">
                        <option value="All-Rounder" selected>⚡ All-Rounder (Bat & Bowl)</option>
                        <option value="Batsman">🏏 Top/Middle Order Batsman</option>
                        <option value="Bowler">🎯 Fast / Spin Bowler</option>
                        <option value="Wicket-Keeper">🧤 Wicket Keeper</option>
                    </select>
                </div>

                <!-- Contact Phone -->
                <div>
                    <label for="modal_contact_phone" class="block text-xs font-black uppercase tracking-wider text-slate-300 mb-1.5">
                        WhatsApp / Contact Number
                    </label>
                    <input type="text" id="modal_contact_phone" name="contact_phone"
                           value="{{ Auth::user()->phone ?? '' }}"
                           placeholder="For match coordination on WhatsApp"
                           class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-white/10 text-white placeholder-slate-500 text-sm focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition" />
                    <p class="text-[11px] text-slate-400 mt-1">Organizer will add you to the match WhatsApp group.</p>
                </div>

                <!-- Notes / Bowling Style -->
                <div>
                    <label for="modal_notes" class="block text-xs font-black uppercase tracking-wider text-slate-300 mb-1.5">
                        Note for Captain (Optional)
                    </label>
                    <input type="text" id="modal_notes" name="notes"
                           placeholder="e.g. Right-hand bat, medium pace, have own bat"
                           class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-white/10 text-white placeholder-slate-500 text-sm focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition" />
                </div>

                <!-- Submit Button -->
                <div class="pt-2 flex items-center justify-end gap-3">
                    <button type="button" @click="joinModal = false"
                            class="px-4 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 text-slate-300 font-bold text-xs transition">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 text-slate-950 font-black text-xs uppercase tracking-wider shadow-lg shadow-amber-500/25 transition">
                        Confirm & Reserve Slot ⚡
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ============================================================== -->
    <!-- MODAL: PLAYER CAREER STATS & PASSPORT MODAL -->
    <!-- ============================================================== -->
    <div x-cloak x-show="careerModalOpen" 
         class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-3 sm:p-5 bg-black/85 backdrop-blur-md"
         @keydown.escape.window="careerModalOpen = false">
        <div class="relative bg-slate-900 border border-white/15 rounded-3xl max-w-lg w-full p-4 sm:p-6 shadow-2xl space-y-4 max-h-[92vh] overflow-y-auto"
             @click.away="careerModalOpen = false">
            
            <!-- Close Button -->
            <button type="button" @click="careerModalOpen = false" 
                    class="absolute top-4 right-4 w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-slate-300 hover:text-white flex items-center justify-center font-bold text-sm transition z-20">
                ✕
            </button>

            <!-- Loading Spinner State -->
            <template x-if="careerLoading">
                <div class="py-16 flex flex-col items-center justify-center gap-3">
                    <div class="w-10 h-10 border-4 border-amber-500 border-t-transparent rounded-full animate-spin"></div>
                    <p class="text-xs font-bold text-slate-300">Loading Player Career Stats...</p>
                </div>
            </template>

            <!-- Error State -->
            <template x-if="!careerLoading && careerError">
                <div class="py-10 text-center space-y-3">
                    <div class="text-3xl">⚠️</div>
                    <p class="text-xs text-rose-400 font-bold" x-text="careerError"></p>
                    <button type="button" @click="careerModalOpen = false" class="px-4 py-2 rounded-xl bg-white/10 text-xs font-bold text-white hover:bg-white/20">Close</button>
                </div>
            </template>

            <!-- Loaded Career Profile Content -->
            <template x-if="!careerLoading && careerData">
                <div class="space-y-4">
                    <!-- Player Header Card -->
                    <div class="p-4 sm:p-5 rounded-2xl bg-gradient-to-br from-amber-950/30 via-slate-900 to-slate-950 border border-amber-500/30 flex items-start gap-3.5 relative overflow-hidden">
                        <div class="absolute -right-6 -bottom-6 text-7xl opacity-5 pointer-events-none select-none">🏏</div>
                        <img :src="careerData.player.avatar_url" 
                             :alt="careerData.player.name" 
                             class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl object-cover border-2 border-amber-400/40 shadow-lg shrink-0">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="px-2 py-0.5 rounded bg-amber-500/20 text-amber-300 border border-amber-500/30 text-[9px] font-black uppercase tracking-wider">
                                    CAREER STATS
                                </span>
                                <template x-if="careerData.player.is_captain">
                                    <span class="px-1.5 py-0.5 rounded bg-red-600 text-white text-[9px] font-black">HOST / CAPTAIN</span>
                                </template>
                                <template x-if="careerData.player.is_wicketkeeper">
                                    <span class="px-1.5 py-0.5 rounded bg-emerald-600 text-white text-[9px] font-black">WK</span>
                                </template>
                            </div>
                            <h3 class="text-base sm:text-lg font-black text-white truncate mt-1" x-text="careerData.player.name"></h3>
                            <div class="text-xs text-slate-300 flex items-center gap-1.5 flex-wrap mt-0.5">
                                <span class="text-amber-400 font-bold" x-text="careerData.player.role"></span>
                                <span class="text-slate-500">•</span>
                                <span class="text-slate-300 truncate" x-text="careerData.player.team"></span>
                            </div>
                            <div class="text-[11px] text-slate-400 mt-1 flex items-center gap-2 flex-wrap">
                                <span x-text="'📍 ' + careerData.player.city"></span>
                                <template x-if="careerData.player.profile_url">
                                    <a :href="careerData.player.profile_url" 
                                       class="text-amber-400 hover:text-amber-300 hover:underline font-bold flex items-center gap-1 text-[11px]">
                                        <span>View Profile</span>
                                        <span>➔</span>
                                    </a>
                                </template>
                            </div>
                        </div>
                    </div>

                    <!-- Career Navigation Tabs -->
                    <div class="flex items-center gap-1.5 p-1 rounded-xl bg-white/5 border border-white/5 text-xs font-bold">
                        <button type="button" 
                                @click="careerTab = 'batting'"
                                class="flex-1 py-2 rounded-lg transition text-center flex items-center justify-center gap-1.5"
                                :class="careerTab === 'batting' ? 'bg-gradient-to-r from-amber-500 to-orange-500 text-slate-950 font-black shadow-md' : 'text-slate-400 hover:text-white'">
                            <span>🏏 Batting</span>
                        </button>
                        <button type="button" 
                                @click="careerTab = 'bowling'"
                                class="flex-1 py-2 rounded-lg transition text-center flex items-center justify-center gap-1.5"
                                :class="careerTab === 'bowling' ? 'bg-gradient-to-r from-rose-600 to-red-600 text-white shadow-md' : 'text-slate-400 hover:text-white'">
                            <span>🎯 Bowling</span>
                        </button>
                        <button type="button" 
                                @click="careerTab = 'matches'"
                                class="flex-1 py-2 rounded-lg transition text-center flex items-center justify-center gap-1.5"
                                :class="careerTab === 'matches' ? 'bg-gradient-to-r from-emerald-600 to-teal-600 text-white shadow-md' : 'text-slate-400 hover:text-white'">
                            <span>📋 Form</span>
                        </button>
                    </div>

                    <!-- TAB 1: BATTING CAREER STATS -->
                    <div x-show="careerTab === 'batting'" class="space-y-3">
                        <!-- Key Highlights Strip -->
                        <div class="grid grid-cols-4 gap-2 text-center">
                            <div class="p-2.5 rounded-xl bg-white/5 border border-white/5">
                                <div class="text-[10px] text-slate-400 font-bold uppercase">Runs</div>
                                <div class="text-base sm:text-lg font-black text-amber-300 font-mono" x-text="careerData.batting.runs"></div>
                            </div>
                            <div class="p-2.5 rounded-xl bg-white/5 border border-white/5">
                                <div class="text-[10px] text-slate-400 font-bold uppercase">Highest</div>
                                <div class="text-base sm:text-lg font-black text-white font-mono" x-text="careerData.batting.highest"></div>
                            </div>
                            <div class="p-2.5 rounded-xl bg-white/5 border border-white/5">
                                <div class="text-[10px] text-slate-400 font-bold uppercase">Average</div>
                                <div class="text-base sm:text-lg font-black text-emerald-400 font-mono" x-text="careerData.batting.average"></div>
                            </div>
                            <div class="p-2.5 rounded-xl bg-white/5 border border-white/5">
                                <div class="text-[10px] text-slate-400 font-bold uppercase">SR</div>
                                <div class="text-base sm:text-lg font-black text-blue-400 font-mono" x-text="careerData.batting.strike_rate"></div>
                            </div>
                        </div>

                        <!-- Complete Batting Metrics Grid -->
                        <div class="p-3.5 rounded-2xl bg-white/5 border border-white/5">
                            <div class="grid grid-cols-3 sm:grid-cols-4 gap-2.5 text-xs">
                                <div>
                                    <span class="text-[10px] text-slate-400 block">Matches</span>
                                    <span class="font-black text-white font-mono text-sm" x-text="careerData.batting.matches"></span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-slate-400 block">Innings</span>
                                    <span class="font-black text-white font-mono text-sm" x-text="careerData.batting.innings"></span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-slate-400 block">Balls Faced</span>
                                    <span class="font-black text-white font-mono text-sm" x-text="careerData.batting.balls"></span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-slate-400 block">Not Outs</span>
                                    <span class="font-black text-emerald-400 font-mono text-sm" x-text="careerData.batting.not_outs"></span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-slate-400 block">Fours (4s)</span>
                                    <span class="font-black text-white font-mono text-sm" x-text="careerData.batting.fours"></span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-slate-400 block">Sixes (6s)</span>
                                    <span class="font-black text-white font-mono text-sm" x-text="careerData.batting.sixes"></span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-slate-400 block">Fifties (50s)</span>
                                    <span class="font-black text-amber-400 font-mono text-sm" x-text="careerData.batting.fifties"></span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-slate-400 block">Hundreds (100s)</span>
                                    <span class="font-black text-purple-400 font-mono text-sm" x-text="careerData.batting.hundreds"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 2: BOWLING CAREER STATS -->
                    <div x-show="careerTab === 'bowling'" class="space-y-3">
                        <!-- Key Highlights Strip -->
                        <div class="grid grid-cols-4 gap-2 text-center">
                            <div class="p-2.5 rounded-xl bg-white/5 border border-white/5">
                                <div class="text-[10px] text-slate-400 font-bold uppercase">Wickets</div>
                                <div class="text-base sm:text-lg font-black text-rose-400 font-mono" x-text="careerData.bowling.wickets"></div>
                            </div>
                            <div class="p-2.5 rounded-xl bg-white/5 border border-white/5">
                                <div class="text-[10px] text-slate-400 font-bold uppercase">Best</div>
                                <div class="text-base sm:text-lg font-black text-white font-mono" x-text="careerData.bowling.best"></div>
                            </div>
                            <div class="p-2.5 rounded-xl bg-white/5 border border-white/5">
                                <div class="text-[10px] text-slate-400 font-bold uppercase">Economy</div>
                                <div class="text-base sm:text-lg font-black text-emerald-400 font-mono" x-text="careerData.bowling.economy"></div>
                            </div>
                            <div class="p-2.5 rounded-xl bg-white/5 border border-white/5">
                                <div class="text-[10px] text-slate-400 font-bold uppercase">Average</div>
                                <div class="text-base sm:text-lg font-black text-blue-400 font-mono" x-text="careerData.bowling.average"></div>
                            </div>
                        </div>

                        <!-- Complete Bowling Metrics Grid -->
                        <div class="p-3.5 rounded-2xl bg-white/5 border border-white/5">
                            <div class="grid grid-cols-3 sm:grid-cols-4 gap-2.5 text-xs">
                                <div>
                                    <span class="text-[10px] text-slate-400 block">Innings Bowled</span>
                                    <span class="font-black text-white font-mono text-sm" x-text="careerData.bowling.innings"></span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-slate-400 block">Overs</span>
                                    <span class="font-black text-white font-mono text-sm" x-text="careerData.bowling.overs"></span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-slate-400 block">Maidens</span>
                                    <span class="font-black text-white font-mono text-sm" x-text="careerData.bowling.maidens"></span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-slate-400 block">Runs Conceded</span>
                                    <span class="font-black text-white font-mono text-sm" x-text="careerData.bowling.runs_conceded"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 3: RECENT MATCHES FORM -->
                    <div x-show="careerTab === 'matches'" class="space-y-2">
                        <template x-if="careerData.recent_matches && careerData.recent_matches.length > 0">
                            <div class="space-y-2">
                                <template x-for="rm in careerData.recent_matches" :key="rm.match_id">
                                    <div class="p-3 rounded-xl bg-white/5 border border-white/5 flex items-center justify-between gap-3 text-xs">
                                        <div class="min-w-0">
                                            <div class="font-bold text-white truncate" x-text="rm.title"></div>
                                            <div class="text-[10px] text-slate-400 flex items-center gap-1.5 mt-0.5">
                                                <span x-text="rm.date"></span>
                                                <span>•</span>
                                                <span class="text-slate-300" x-text="rm.team"></span>
                                            </div>
                                        </div>
                                        <div class="text-right shrink-0">
                                            <div class="font-mono font-bold text-amber-300" x-text="rm.runs + ' (' + rm.balls + 'b)'"></div>
                                            <div class="text-[10px] font-mono text-slate-400" x-text="rm.overs + ' ov, ' + rm.wickets + '/' + rm.runs_conceded"></div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>
                        <template x-if="!careerData.recent_matches || careerData.recent_matches.length === 0">
                            <div class="py-8 text-center text-slate-500 italic text-xs">
                                No recent match history recorded for this player yet.
                            </div>
                        </template>
                    </div>

                    <!-- Modal Bottom Actions: "View Profile" link right here! -->
                    <div class="flex items-center justify-between gap-3 pt-3 border-t border-white/10">
                        <template x-if="careerData.player.profile_url">
                            <a :href="careerData.player.profile_url" 
                               class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-400 hover:to-orange-400 text-slate-950 font-black text-xs transition flex items-center gap-2 shadow-lg shadow-amber-500/25">
                                <span>👤 View Profile</span>
                                <span>➔</span>
                            </a>
                        </template>
                        <template x-if="!careerData.player.profile_url">
                            <span class="text-[11px] text-slate-500 italic">Unregistered Player</span>
                        </template>
                        <button type="button" @click="careerModalOpen = false" 
                                class="px-5 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-white font-bold text-xs transition">
                            Close
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>
@endsection
