@extends('layouts.app')

@section('header')
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="px-2.5 py-0.5 rounded-md text-[11px] font-black uppercase tracking-wider bg-indigo-600 text-white shadow-xs">
                    CRICHEROES OFFICIALS HUB
                </span>
                <span class="text-xs text-gray-400 font-bold">• Certified Match Umpires</span>
            </div>
            <h2 class="font-black text-2xl text-gray-900 leading-tight mt-1">Book Match Umpires ⚖️</h2>
        </div>

        @auth
            <a href="{{ route('umpires.register') }}"
               class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-500 hover:to-blue-500 text-white font-extrabold text-sm shadow-md shadow-indigo-600/25 transition transform active:scale-95 cursor-pointer">
                <span>{{ $myUmpireProfile ? 'Edit Umpire Profile ⚖️' : 'Register as Umpire ⚖️' }}</span>
            </a>
        @else
            <a href="{{ route('login') }}"
               class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-gray-900 hover:bg-gray-800 text-white font-bold text-sm shadow-md transition">
                <span>Sign In to Register as Umpire</span>
            </a>
        @endauth
    </div>
@endsection

@section('content')
<div class="py-6 sm:py-8 bg-slate-900 min-h-screen text-slate-100 font-sans" x-data="{
    bookingModal: false,
    selectedUmpire: null,
    tournamentName: 'Weekend Cricket Cup',
    matchDate: '{{ date('Y-m-d') }}',
    matchTime: '07:30 AM',
    venue: '',
    teams: 'Royal Strikers vs Thunder Titans',
    ballType: 'Tennis Ball',
    contactPhone: '',
    notes: '',
    openBooking(umpire) {
        this.selectedUmpire = umpire;
        this.bookingModal = true;
    }
}">

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

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

        <!-- ============================================== -->
        <!-- SEARCH & ADVANCED FILTER CARD -->
        <!-- ============================================== -->
        <div class="bg-[#131b2e] border border-white/10 rounded-2xl p-5 shadow-2xl space-y-4">
            <form method="GET" action="{{ route('umpires.index') }}" class="space-y-4">
                <input type="hidden" name="tab" value="{{ $tab }}">

                <div class="flex flex-col sm:flex-row items-center gap-3">
                    <div class="relative flex-1 w-full">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </span>
                        <input type="text"
                               name="search"
                               value="{{ $search }}"
                               placeholder="Search umpires by name, city, certification level, or experience..."
                               class="w-full bg-slate-950 border border-white/10 rounded-xl pl-10 pr-10 py-2.5 text-xs sm:text-sm text-white placeholder-slate-500 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        @if(!empty($search))
                            <a href="{{ route('umpires.index', ['tab' => $tab]) }}"
                               class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-white text-xs font-bold"
                               title="Clear search">
                                ✕
                            </a>
                        @endif
                    </div>

                    <button type="submit"
                            class="w-full sm:w-auto px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-extrabold text-xs shadow-md shadow-indigo-600/30 transition">
                        Find Umpires
                    </button>
                    @if(!empty($search) || !empty($city) || !empty($level) || !empty($ballType) || !empty($maxFee))
                        <a href="{{ route('umpires.index', ['tab' => $tab]) }}"
                           class="w-full sm:w-auto text-center px-4 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-slate-300 text-xs font-bold transition">
                            Reset Filters
                        </a>
                    @endif
                </div>

                <!-- Secondary Filters Row -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-3 border-t border-white/5 text-xs">
                    <!-- City Dropdown -->
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">City / Region</label>
                        <select name="city" onchange="this.form.submit()" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-xs text-white focus:ring-2 focus:ring-indigo-500">
                            <option value="all">All Cities</option>
                            @foreach($cities as $c)
                                <option value="{{ $c }}" {{ $city === $c ? 'selected' : '' }}>{{ $c }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Certification Level -->
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Certification Level</label>
                        <select name="level" onchange="this.form.submit()" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-xs text-white focus:ring-2 focus:ring-indigo-500">
                            <option value="all">All Qualifications</option>
                            <option value="BCCI Certified Level 1" {{ $level === 'BCCI Certified Level 1' ? 'selected' : '' }}>🎖️ BCCI Level 1</option>
                            <option value="State Association Certified" {{ $level === 'State Association Certified' ? 'selected' : '' }}>🏛️ State Association</option>
                            <option value="Club / Local League Umpire" {{ $level === 'Club / Local League Umpire' ? 'selected' : '' }}>🏏 Club / League Official</option>
                            <option value="Veteran Match Official" {{ $level === 'Veteran Match Official' ? 'selected' : '' }}>⭐ Veteran Official</option>
                        </select>
                    </div>

                    <!-- Ball Type -->
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Ball Type</label>
                        <select name="ball_type" onchange="this.form.submit()" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-xs text-white focus:ring-2 focus:ring-indigo-500">
                            <option value="all">Any Match Type</option>
                            <option value="Leather Ball" {{ $ballType === 'Leather Ball' ? 'selected' : '' }}>🏏 Leather Ball</option>
                            <option value="Tennis Ball" {{ $ballType === 'Tennis Ball' ? 'selected' : '' }}>🎾 Tennis Ball</option>
                            <option value="T20 Matches" {{ $ballType === 'T20 Matches' ? 'selected' : '' }}>⚡ T20 Tournaments</option>
                        </select>
                    </div>

                    <!-- Max Fee -->
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Match Fee</label>
                        <select name="max_fee" onchange="this.form.submit()" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-xs text-white focus:ring-2 focus:ring-indigo-500">
                            <option value="">Any Fee</option>
                            <option value="500" {{ $maxFee == '500' ? 'selected' : '' }}>Under ₹500 / match</option>
                            <option value="1000" {{ $maxFee == '1000' ? 'selected' : '' }}>Under ₹1,000 / match</option>
                            <option value="1500" {{ $maxFee == '1500' ? 'selected' : '' }}>Under ₹1,500 / match</option>
                        </select>
                    </div>
                </div>
            </form>
        </div>

        <!-- ============================================== -->
        <!-- NAVIGATION TABS -->
        <!-- ============================================== -->
        <div class="flex items-center gap-2 overflow-x-auto pb-2 scrollbar-none border-b border-white/10">
            <a href="{{ route('umpires.index', ['tab' => 'all']) }}"
               class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shrink-0 {{ $tab === 'all' ? 'bg-indigo-600 text-white shadow-md' : 'bg-white/5 hover:bg-white/10 text-slate-300' }}">
                <span>⚖️ Verified Umpires</span>
                <span class="px-1.5 py-0.2 rounded-md bg-black/20 text-[10px]">{{ $umpires->total() }}</span>
            </a>

            @auth
                <a href="{{ route('umpires.index', ['tab' => 'my_bookings']) }}"
                   class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shrink-0 {{ $tab === 'my_bookings' ? 'bg-indigo-600 text-white shadow-md' : 'bg-white/5 hover:bg-white/10 text-slate-300' }}">
                    <span>📅 My Umpire Bookings</span>
                    <span class="px-1.5 py-0.2 rounded-md bg-black/20 text-[10px]">{{ $myBookings->count() }}</span>
                </a>

                @if($myUmpireProfile)
                    <a href="{{ route('umpires.index', ['tab' => 'incoming_requests']) }}"
                       class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shrink-0 {{ $tab === 'incoming_requests' ? 'bg-indigo-600 text-white shadow-md' : 'bg-white/5 hover:bg-white/10 text-slate-300' }}">
                        <span class="w-2 h-2 rounded-full bg-amber-400 animate-ping"></span>
                        <span>🔔 Match Officiating Requests</span>
                        <span class="px-1.5 py-0.2 rounded-md bg-amber-500 text-slate-950 text-[10px] font-black">{{ $incomingRequests->where('status', 'pending')->count() }}</span>
                    </a>
                @endif
            @endauth
        </div>

        <!-- ============================================== -->
        <!-- TAB CONTENT: MY BOOKINGS (FOR MATCH ORGANIZERS) -->
        <!-- ============================================== -->
        @if($tab === 'my_bookings')
            <div class="space-y-4">
                <h3 class="text-lg font-black text-white flex items-center gap-2">
                    <span>📅 Your Umpire Bookings for Matches</span>
                </h3>

                @if($myBookings->count() > 0)
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach($myBookings as $b)
                            <div class="p-5 rounded-2xl bg-[#131b2e] border border-white/10 shadow-xl space-y-3">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="font-extrabold text-white text-base">Umpire: {{ $b->umpireProfile->name }}</h4>
                                        <div class="text-xs text-slate-400 mt-0.5">🏆 {{ $b->tournament_name ?? 'Friendly Match' }} • {{ $b->teams }}</div>
                                    </div>
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase
                                        {{ $b->status === 'confirmed' ? 'bg-emerald-600/20 text-emerald-300 border border-emerald-500/30' : ($b->status === 'pending' ? 'bg-amber-600/20 text-amber-300 border border-amber-500/30 animate-pulse' : 'bg-slate-700 text-slate-400') }}">
                                        {{ $b->status }}
                                    </span>
                                </div>

                                <div class="p-3 rounded-xl bg-black/40 border border-white/5 grid grid-cols-2 gap-2 text-xs">
                                    <div>
                                        <span class="text-slate-400 block text-[10px]">Match Date</span>
                                        <span class="font-bold text-white">{{ $b->match_date->format('d M Y') }}</span>
                                    </div>
                                    <div>
                                        <span class="text-slate-400 block text-[10px]">Match Time</span>
                                        <span class="font-bold text-indigo-400">{{ $b->match_time }}</span>
                                    </div>
                                    <div>
                                        <span class="text-slate-400 block text-[10px]">Venue / Ground</span>
                                        <span class="font-bold text-white truncate block">{{ $b->venue }}</span>
                                    </div>
                                    <div>
                                        <span class="text-slate-400 block text-[10px]">Umpire Fee</span>
                                        <span class="font-bold text-emerald-400">₹{{ number_format($b->total_fee, 2) }}</span>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between pt-2 border-t border-white/5 text-xs">
                                    <div class="flex items-center gap-2">
                                        @if($b->umpireProfile->whatsapp)
                                            <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $b->umpireProfile->whatsapp) }}?text=Hi%20{{ urlencode($b->umpireProfile->name) }},%20regarding%20match%20umpiring%20on%20{{ $b->match_date->format('d-m-Y') }}" target="_blank"
                                               class="px-3 py-1.5 rounded-lg bg-emerald-600/20 text-emerald-300 border border-emerald-500/30 font-bold hover:bg-emerald-600 hover:text-white transition flex items-center gap-1">
                                                <span>💬 WhatsApp Umpire</span>
                                            </a>
                                        @endif
                                    </div>

                                    @if($b->status === 'pending')
                                        <form method="POST" action="{{ route('umpires.bookings.status', $b) }}" onsubmit="return confirm('Cancel this umpire booking request?');">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="cancelled">
                                            <button type="submit" class="text-red-400 hover:underline text-xs font-bold">Cancel Request</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="p-10 rounded-2xl bg-[#131b2e] border border-white/10 text-center max-w-md mx-auto">
                        <div class="text-4xl mb-2">⚖️</div>
                        <h4 class="font-bold text-white">No Umpire Bookings Yet</h4>
                        <p class="text-slate-400 text-xs mt-1">Book certified umpires for your upcoming matches to ensure fair play.</p>
                        <a href="{{ route('umpires.index') }}" class="mt-4 inline-block px-4 py-2 rounded-xl bg-indigo-600 text-white text-xs font-bold">Browse Umpires</a>
                    </div>
                @endif
            </div>

        <!-- ============================================== -->
        <!-- TAB CONTENT: INCOMING MATCH REQUESTS (FOR UMPIRES) -->
        <!-- ============================================== -->
        @elseif($tab === 'incoming_requests' && $myUmpireProfile)
            <div class="space-y-4">
                <h3 class="text-lg font-black text-white flex items-center gap-2">
                    <span>🔔 Match Officiating Requests for You</span>
                </h3>

                @if($incomingRequests->count() > 0)
                    <div class="space-y-3">
                        @foreach($incomingRequests as $req)
                            <div class="p-5 rounded-2xl bg-[#131b2e] border border-white/10 shadow-xl flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span class="font-black text-white text-base">{{ $req->teams }}</span>
                                        <span class="text-xs text-slate-400">• Tournament: <strong class="text-indigo-400">{{ $req->tournament_name }}</strong></span>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase
                                            {{ $req->status === 'confirmed' ? 'bg-emerald-600/20 text-emerald-300' : ($req->status === 'pending' ? 'bg-amber-600/20 text-amber-300 animate-pulse' : 'bg-slate-700 text-slate-400') }}">
                                            {{ $req->status }}
                                        </span>
                                    </div>
                                    <div class="text-xs text-slate-300 flex flex-wrap items-center gap-3">
                                        <span>📅 <strong>{{ $req->match_date->format('d M Y') }}</strong></span>
                                        <span>⏰ <strong>{{ $req->match_time }}</strong></span>
                                        <span>📍 <strong>{{ $req->venue }}</strong></span>
                                        <span>🏏 <strong>{{ $req->ball_type }}</strong></span>
                                        <span>💰 Fee: <strong>₹{{ number_format($req->total_fee, 2) }}</strong></span>
                                        <span>📞 Organizer: <a href="tel:{{ $req->contact_phone }}" class="text-blue-400 font-bold underline">{{ $req->contact_phone }}</a></span>
                                    </div>
                                    @if($req->notes)
                                        <div class="text-xs text-slate-400 italic">"{{ $req->notes }}"</div>
                                    @endif
                                </div>

                                <div class="flex items-center gap-2 shrink-0">
                                    @if($req->status === 'pending')
                                        <form method="POST" action="{{ route('umpires.bookings.status', $req) }}" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="confirmed">
                                            <button type="submit" class="px-3.5 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-md transition">
                                                Accept Match ✅
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('umpires.bookings.status', $req) }}" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="cancelled">
                                            <button type="submit" class="px-3 py-1.5 rounded-xl bg-red-600/20 hover:bg-red-600/30 text-red-300 font-bold text-xs transition">
                                                Decline ✕
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-xs text-slate-400 font-bold">Status: {{ ucfirst($req->status) }}</span>
                                    @endif

                                    @if($req->contact_phone)
                                        <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $req->contact_phone) }}?text=Hi,%20regarding%20your%20umpire%20booking%20for%20{{ urlencode($req->teams) }}%20on%20{{ $req->match_date->format('d-m-Y') }}" target="_blank"
                                           class="p-2 rounded-xl bg-emerald-500/20 text-emerald-300 hover:bg-emerald-500 hover:text-white transition" title="Chat on WhatsApp">
                                            💬
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="p-8 rounded-2xl bg-[#131b2e] border border-white/10 text-center max-w-md mx-auto">
                        <p class="text-slate-400 text-xs">No pending match officiating requests right now.</p>
                    </div>
                @endif
            </div>

        <!-- ============================================== -->
        <!-- TAB CONTENT: ALL UMPIRES LIST -->
        <!-- ============================================== -->
        @else
            @if($umpires->count() > 0)
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($umpires as $umpire)
                        @php
                            $isSelf = auth()->check() && (auth()->id() === $umpire->user_id);
                        @endphp
                        <div class="rounded-2xl bg-[#131b2e] border border-white/10 overflow-hidden shadow-2xl flex flex-col justify-between hover:border-indigo-500/40 transition group p-5 space-y-4">

                            <!-- Header Bar with Avatar, Name, Rating -->
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-indigo-600 to-blue-600 flex items-center justify-center font-black text-lg text-white shadow-md shrink-0 overflow-hidden">
                                        @if($umpire->photo_url)
                                            <img src="{{ $umpire->photo_url }}" alt="{{ $umpire->name }}" class="w-full h-full object-cover">
                                        @else
                                            <span>{{ strtoupper(substr($umpire->name, 0, 2)) }}</span>
                                        @endif
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <a href="{{ route('umpires.show', $umpire) }}" class="font-extrabold text-white text-base hover:text-indigo-400 transition truncate">
                                                {{ $umpire->name }}
                                            </a>
                                            @if($isSelf)
                                                <span class="px-1.5 py-0.2 rounded bg-indigo-600 text-[9px] font-black uppercase text-white shrink-0">You</span>
                                            @endif
                                        </div>
                                        <div class="text-xs text-slate-400 flex items-center gap-1 mt-0.5">
                                            <span>📍 {{ $umpire->city }}</span>
                                            <span>•</span>
                                            <span>{{ $umpire->experience_years }} Yrs Exp</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Rating & Fee Badge -->
                                <div class="text-right shrink-0">
                                    <div class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-amber-500/20 text-amber-300 border border-amber-500/30 text-xs font-black">
                                        <span>⭐</span>
                                        <span>{{ number_format($umpire->rating, 1) }}</span>
                                    </div>
                                    <div class="text-xs font-black text-emerald-400 mt-1">
                                        ₹{{ number_format($umpire->fee_per_match, 0) }} <span class="text-[10px] text-slate-400 font-normal">/ match</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Badges: Certification & Match Types -->
                            <div class="space-y-2">
                                <div class="p-2.5 rounded-xl bg-black/40 border border-white/5 flex items-center justify-between text-xs">
                                    <span class="text-slate-400 text-[11px]">Qualification:</span>
                                    <span class="font-bold text-indigo-300 text-[11px] truncate">{{ $umpire->certification_level }}</span>
                                </div>

                                <div class="flex items-center justify-between text-xs text-slate-300 px-1">
                                    <span>Matches Umpired:</span>
                                    <span class="font-mono font-black text-white">{{ $umpire->matches_umpired }}+ Matches</span>
                                </div>

                                @if(!empty($umpire->match_types) && is_array($umpire->match_types))
                                    <div class="flex flex-wrap gap-1 pt-1">
                                        @foreach($umpire->match_types as $mt)
                                            <span class="px-2 py-0.5 rounded-md bg-white/5 border border-white/10 text-[10px] font-semibold text-slate-300">
                                                🏏 {{ $mt }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            <!-- Bio snippet -->
                            @if($umpire->bio)
                                <p class="text-xs text-slate-400 line-clamp-2 italic">
                                    "{{ $umpire->bio }}"
                                </p>
                            @endif

                            <!-- Action Buttons -->
                            <div class="pt-3 border-t border-white/5 flex items-center justify-between gap-2">
                                @if(!$isSelf)
                                    @auth
                                        <button type="button"
                                                @click="openBooking({{ json_encode($umpire) }})"
                                                class="flex-1 py-2 px-3 rounded-xl bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-500 hover:to-blue-500 text-white font-extrabold text-xs shadow-md shadow-indigo-600/20 transition flex items-center justify-center gap-1.5">
                                            <span>Book Umpire ⚖️</span>
                                        </button>
                                    @else
                                        <a href="{{ route('login') }}"
                                           class="flex-1 py-2 px-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-extrabold text-xs text-center transition">
                                            Sign in to Book ⚖️
                                        </a>
                                    @endauth
                                @else
                                    <a href="{{ route('umpires.register') }}"
                                       class="flex-1 py-2 px-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs text-center transition">
                                        Edit Your Profile
                                    </a>
                                @endif

                                <a href="{{ route('umpires.show', $umpire) }}"
                                   class="px-3 py-2 rounded-xl bg-white/10 hover:bg-white/20 text-slate-200 text-xs font-bold transition">
                                    Profile
                                </a>

                                @if($umpire->whatsapp)
                                    <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $umpire->whatsapp) }}?text=Hi%20{{ urlencode($umpire->name) }},%20I%20need%20an%20umpire%20for%20our%20cricket%20match" target="_blank"
                                       class="p-2 rounded-xl bg-emerald-500/10 text-emerald-400 hover:bg-emerald-500 hover:text-white transition" title="Chat on WhatsApp">
                                        💬
                                    </a>
                                @endif
                            </div>

                        </div>
                    @endforeach
                </div>

                <div class="mt-6">
                    {{ $umpires->links() }}
                </div>
            @else
                <div class="bg-[#131b2e] border border-white/5 rounded-3xl p-10 sm:p-16 text-center max-w-lg mx-auto">
                    <div class="w-20 h-20 rounded-full bg-indigo-600/10 border-2 border-dashed border-indigo-500/30 flex items-center justify-center text-4xl mx-auto mb-4">
                        ⚖️
                    </div>
                    <h3 class="text-xl font-black text-white">No Umpires Found</h3>
                    <p class="text-slate-400 text-xs sm:text-sm mt-1.5 leading-relaxed">
                        Try resetting your search or city filters, or register your profile as an official match umpire.
                    </p>
                    <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                        <a href="{{ route('umpires.index') }}" class="px-5 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-white text-xs font-bold transition">
                            Clear Filters ✕
                        </a>
                        @auth
                            <a href="{{ route('umpires.register') }}" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold transition">
                                Register as Umpire ⚖️
                            </a>
                        @endauth
                    </div>
                </div>
            @endif
        @endif

    </div>

    <!-- ============================================== -->
    <!-- QUICK UMPIRE BOOKING MODAL -->
    <!-- ============================================== -->
    <div x-cloak x-show="bookingModal" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-black/80 backdrop-blur-md"
         @keydown.escape.window="bookingModal = false">
        <div class="relative bg-slate-900 border border-white/10 rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-4"
             @click.away="bookingModal = false">

            <div class="flex items-center justify-between pb-3 border-b border-white/10">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-indigo-400">⚖️ BOOK MATCH UMPIRE</span>
                    <h3 class="text-lg font-black text-white" x-text="selectedUmpire ? selectedUmpire.name : 'Book Umpire'"></h3>
                    <div class="text-xs text-slate-400" x-text="selectedUmpire ? ('Official Fee: ₹' + selectedUmpire.fee_per_match + ' / match • ' + selectedUmpire.city) : ''"></div>
                </div>
                <button type="button" @click="bookingModal = false" class="text-slate-400 hover:text-white text-sm font-bold">✕</button>
            </div>

            <form method="POST" :action="selectedUmpire ? ('/umpires/' + selectedUmpire.id + '/book') : '#'" class="space-y-4 text-xs">
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
                    <textarea name="notes" x-model="notes" rows="2" placeholder="e.g. 20 overs per side, LBW rules applied, bowler limit 4 overs..."
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
