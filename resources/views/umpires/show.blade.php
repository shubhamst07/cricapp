@extends('layouts.app')

@section('header')
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('umpires.index') }}" class="p-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-white/10 dark:hover:bg-white/15 text-slate-700 dark:text-slate-200 transition" title="Back to Umpires">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>
            <div>
                <span class="text-[11px] font-black uppercase text-indigo-600 dark:text-indigo-400 tracking-wider">CRICHEROES MATCH OFFICIAL</span>
                <h2 class="font-black text-xl sm:text-2xl text-slate-900 dark:text-white leading-tight">
                    {{ $umpire->name }}
                </h2>
            </div>
        </div>

        @if($isSelf)
            <a href="{{ route('umpires.register') }}" class="px-4 py-2 rounded-xl bg-indigo-50 dark:bg-indigo-900/40 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300 text-xs font-bold transition">
                Edit Profile ✏️
            </a>
        @endif
    </div>
@endsection

@section('content')
<div class="py-8 bg-slate-900 min-h-screen text-slate-100 font-sans" x-data="{
    bookingModal: false,
    tournamentName: 'Weekend Cricket League',
    matchDate: '{{ date('Y-m-d') }}',
    matchTime: '07:30 AM',
    venue: '',
    teams: 'Royal Strikers vs Thunder Titans',
    ballType: 'Tennis Ball',
    contactPhone: '',
    notes: ''
}">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        @if(session('status'))
            <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm font-bold flex items-center gap-2">
                <span>✓ {{ session('status') }}</span>
            </div>
        @endif

        @if(session('error'))
            <div class="p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-sm font-bold flex items-center gap-2">
                <span>⚠️ {{ session('error') }}</span>
            </div>
        @endif

        <!-- Profile Header Card -->
        <div class="bg-[#131b2e] border border-white/10 rounded-3xl p-6 sm:p-8 shadow-2xl relative overflow-hidden">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-6">

                <div class="flex flex-col sm:flex-row sm:items-center gap-5">
                    <div class="w-24 h-24 rounded-3xl bg-gradient-to-tr from-indigo-600 to-blue-600 flex items-center justify-center font-black text-3xl text-white shadow-xl overflow-hidden shrink-0">
                        @if($umpire->photo_url)
                            <img src="{{ $umpire->photo_url }}" alt="{{ $umpire->name }}" class="w-full h-full object-cover">
                        @else
                            <img src="https://ui-avatars.com/api/?name={{ urlencode($umpire->name) }}&background=4f46e5&color=ffffff&size=200&bold=true&font-size=0.4" alt="{{ $umpire->name }}" class="w-full h-full object-cover">
                        @endif
                    </div>

                    <div class="space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="text-2xl sm:text-3xl font-black text-white">{{ $umpire->name }}</h1>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30 flex items-center gap-1">
                                <span>⭐</span>
                                <span>{{ number_format($umpire->rating, 1) }} Rating</span>
                            </span>
                        </div>

                        <p class="text-xs text-slate-300 flex flex-wrap items-center gap-3">
                            <span>📍 <strong>{{ $umpire->city }}</strong></span>
                            <span>•</span>
                            <span class="text-indigo-400 font-bold">🎖️ {{ $umpire->certification_level }}</span>
                            <span>•</span>
                            <span>🕒 {{ $umpire->experience_years }} Years Experience</span>
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-4 shrink-0 sm:border-l sm:border-white/10 sm:pl-6">
                    <div class="text-left sm:text-right">
                        <span class="text-[10px] text-slate-400 uppercase font-bold block">Match Fee</span>
                        <span class="text-2xl sm:text-3xl font-black text-emerald-400">₹{{ number_format($umpire->fee_per_match, 0) }}</span>
                        <span class="text-xs text-slate-400 font-normal block">/ match officiated</span>
                    </div>

                    @if(!$isSelf)
                        @auth
                            <button type="button" @click="bookingModal = true"
                                    class="px-6 py-3 rounded-2xl bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-500 hover:to-blue-500 text-white font-extrabold text-sm shadow-xl shadow-indigo-600/30 transition transform active:scale-95">
                                Book Umpire ⚖️
                            </button>
                        @else
                            <a href="{{ route('login') }}" class="px-6 py-3 rounded-2xl bg-indigo-600 text-white font-bold text-sm">
                                Sign In to Book
                            </a>
                        @endauth
                    @endif
                </div>

            </div>
        </div>

        <!-- 2 Column Details: Stats & Bio -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <div class="lg:col-span-2 space-y-6">

                <!-- Performance Statistics Bar -->
                <div class="grid grid-cols-3 gap-4">
                    <div class="p-5 rounded-2xl bg-[#131b2e] border border-white/10 text-center">
                        <span class="text-[10px] uppercase font-bold text-slate-400 block">Matches Umpired</span>
                        <span class="text-3xl font-black text-white mt-1 block">{{ $umpire->matches_umpired }}+</span>
                    </div>

                    <div class="p-5 rounded-2xl bg-[#131b2e] border border-white/10 text-center">
                        <span class="text-[10px] uppercase font-bold text-slate-400 block">Experience</span>
                        <span class="text-3xl font-black text-indigo-400 mt-1 block">{{ $umpire->experience_years }} Yrs</span>
                    </div>

                    <div class="p-5 rounded-2xl bg-[#131b2e] border border-white/10 text-center">
                        <span class="text-[10px] uppercase font-bold text-slate-400 block">Status</span>
                        <span class="text-xs font-black uppercase text-emerald-400 mt-3 inline-block px-2.5 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20">
                            Available 🟢
                        </span>
                    </div>
                </div>

                <!-- Match Types Officiated -->
                <div class="p-6 rounded-3xl bg-[#131b2e] border border-white/10 shadow-xl space-y-3">
                    <h3 class="text-base font-black text-white">Match Types & Ball Specialization</h3>
                    @if(!empty($umpire->match_types) && is_array($umpire->match_types))
                        <div class="flex flex-wrap gap-2 pt-2">
                            @foreach($umpire->match_types as $mt)
                                <div class="px-4 py-2 rounded-xl bg-white/5 border border-white/10 text-xs font-bold text-white flex items-center gap-1.5">
                                    <span>🏏</span>
                                    <span>{{ $mt }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <!-- Bio / Portfolio -->
                <div class="p-6 rounded-3xl bg-[#131b2e] border border-white/10 shadow-xl space-y-3">
                    <h3 class="text-base font-black text-white">About Umpire Official</h3>
                    <p class="text-xs sm:text-sm text-slate-300 leading-relaxed whitespace-pre-line">
                        {{ $umpire->bio ?: 'Certified grassroots cricket umpire with extensive experience officiating competitive matches, tournaments, and corporate leagues under official MCC cricket laws.' }}
                    </p>
                </div>

                <!-- Officiated Matches Record -->
                <div class="p-6 rounded-3xl bg-[#131b2e] border border-white/10 shadow-xl space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-black text-white flex items-center gap-2">
                            <span>⚖️</span>
                            <span>Officiated Matches & History</span>
                        </h3>
                        <span class="text-xs text-indigo-400 font-bold">{{ $umpire->matches_umpired }} Total Matches</span>
                    </div>

                    @if(isset($officiatedMatches) && $officiatedMatches->isNotEmpty())
                        <div class="space-y-2.5">
                            @foreach($officiatedMatches as $m)
                                <a href="{{ route('matches.show', $m) }}"
                                   class="block p-3.5 rounded-2xl bg-white/5 hover:bg-white/10 border border-white/10 transition group">
                                    <div class="flex items-center justify-between text-xs mb-1">
                                        <span class="font-extrabold text-white group-hover:text-indigo-400 transition">{{ $m->team1_name }} vs {{ $m->team2_name }}</span>
                                        <span class="text-[10px] text-slate-400">{{ $m->match_date?->format('M d, Y') }}</span>
                                    </div>
                                    <div class="flex items-center justify-between text-[11px] text-slate-400">
                                        <span>📍 {{ $m->venue ?? 'Ground' }} • {{ $m->tournament_name ?? 'Club Match' }}</span>
                                        <span class="font-bold {{ $m->status === 'completed' ? 'text-emerald-400' : 'text-amber-400' }}">
                                            {{ ucfirst($m->status) }}
                                            @if($m->umpire_rating)
                                                <span class="ml-1 text-amber-300">⭐ {{ $m->umpire_rating }}</span>
                                            @endif
                                        </span>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-6 text-xs text-slate-400">
                            <span>No matches officiated on LiveCricket recorded yet.</span>
                        </div>
                    @endif
                </div>

            </div>

            <!-- Right Column: Direct Contact & Booking Widget -->
            <div class="space-y-6">

                <div class="p-6 rounded-3xl bg-[#131b2e] border border-white/10 shadow-xl space-y-4">
                    <h3 class="text-base font-black text-white">Direct Match Booking</h3>

                    <div class="p-4 rounded-2xl bg-black/40 border border-white/5 space-y-3 text-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Match Fee:</span>
                            <span class="font-bold text-emerald-400">₹{{ number_format($umpire->fee_per_match, 2) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Official Contact:</span>
                            <a href="tel:{{ $umpire->phone }}" class="font-bold text-blue-400 underline">{{ $umpire->phone }}</a>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Qualification:</span>
                            <span class="font-bold text-indigo-300">{{ $umpire->certification_level }}</span>
                        </div>
                    </div>

                    <div class="space-y-2 pt-2">
                        @if(!$isSelf)
                            @auth
                                <button type="button" @click="bookingModal = true"
                                        class="w-full py-3 rounded-xl bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-500 hover:to-blue-500 text-white font-extrabold text-xs shadow-lg shadow-indigo-600/30 transition">
                                    Request Umpire for Match ⚖️
                                </button>
                            @else
                                <a href="{{ route('login') }}" class="block text-center w-full py-3 rounded-xl bg-indigo-600 text-white font-bold text-xs">
                                    Sign In to Book
                                </a>
                            @endauth
                        @endif

                        @if($umpire->whatsapp)
                            <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $umpire->whatsapp) }}?text=Hi%20{{ urlencode($umpire->name) }},%20I%20am%20looking%20for%20an%20umpire%20for%20our%20match" target="_blank"
                               class="w-full py-2.5 rounded-xl bg-emerald-500/10 text-emerald-300 border border-emerald-500/20 hover:bg-emerald-500 hover:text-white font-bold text-xs transition flex items-center justify-center gap-2">
                                <span>💬 Chat on WhatsApp</span>
                            </a>
                        @endif

                        <a href="tel:{{ $umpire->phone }}"
                           class="w-full py-2.5 rounded-xl bg-white/5 hover:bg-white/10 text-slate-300 font-bold text-xs transition flex items-center justify-center gap-2">
                            <span>📞 Call Official</span>
                        </a>
                    </div>
                </div>

            </div>

        </div>

    </div>

    <!-- Booking Modal -->
    <div x-cloak x-show="bookingModal" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-black/80 backdrop-blur-md"
         @keydown.escape.window="bookingModal = false">
        <div class="relative bg-slate-900 border border-white/10 rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-4"
             @click.away="bookingModal = false">

            <div class="flex items-center justify-between pb-3 border-b border-white/10">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-indigo-400">⚖️ BOOK MATCH UMPIRE</span>
                    <h3 class="text-lg font-black text-white">{{ $umpire->name }}</h3>
                    <div class="text-xs text-slate-400">Fee: ₹{{ number_format($umpire->fee_per_match, 2) }} / match</div>
                </div>
                <button type="button" @click="bookingModal = false" class="text-slate-400 hover:text-white text-sm font-bold">✕</button>
            </div>

            <form method="POST" action="{{ route('umpires.book', $umpire) }}" class="space-y-4 text-xs">
                @csrf

                <div>
                    <label class="block font-bold text-slate-300 uppercase tracking-wider mb-1">Teams Playing *</label>
                    <input type="text" name="teams" x-model="teams" required placeholder="e.g. Royal Strikers vs Thunder Titans"
                           class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2.5 text-xs text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-300 uppercase tracking-wider mb-1">Tournament / Match Title</label>
                        <input type="text" name="tournament_name" x-model="tournamentName" placeholder="e.g. Corporate League 2026"
                               class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2.5 text-xs text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-300 uppercase tracking-wider mb-1">Ball Type *</label>
                        <select name="ball_type" x-model="ballType" required
                                class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2.5 text-xs text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                            <option value="Tennis Ball">🎾 Tennis Ball</option>
                            <option value="Leather Ball">🏏 Leather Ball</option>
                            <option value="Tape Ball">⚡ Tape Ball</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-300 uppercase tracking-wider mb-1">Match Date *</label>
                        <input type="date" name="match_date" x-model="matchDate" min="{{ date('Y-m-d') }}" required
                               class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2.5 text-xs text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-300 uppercase tracking-wider mb-1">Match Reporting Time *</label>
                        <input type="text" name="match_time" x-model="matchTime" required placeholder="e.g. 07:30 AM"
                               class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2.5 text-xs text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-slate-300 uppercase tracking-wider mb-1">Ground / Venue Name & Landmark *</label>
                    <input type="text" name="venue" x-model="venue" required placeholder="e.g. Shivaji Park Ground No 3, Mumbai"
                           class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2.5 text-xs text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>

                <div>
                    <label class="block font-bold text-slate-300 uppercase tracking-wider mb-1">Organizer Contact Phone *</label>
                    <input type="text" name="contact_phone" x-model="contactPhone" required placeholder="Enter WhatsApp or Phone number"
                           class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2.5 text-xs text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>

                <div>
                    <label class="block font-bold text-slate-300 uppercase tracking-wider mb-1">Special Match Instructions</label>
                    <textarea name="notes" x-model="notes" rows="2" placeholder="e.g. 20 overs per side, LBW rules applied..."
                              class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2 text-xs text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none"></textarea>
                </div>

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-white/10">
                    <button type="button" @click="bookingModal = false" class="px-4 py-2 text-xs font-bold text-slate-400 hover:text-white">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-500 hover:to-blue-500 text-white font-extrabold text-xs shadow-lg shadow-indigo-600/30 transition">
                        Send Booking Request ⚖️
                    </button>
                </div>
            </form>

        </div>
    </div>

</div>
@endsection
