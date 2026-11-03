@extends('layouts.app')

@section('header')
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="px-2.5 py-0.5 rounded-md text-[11px] font-black uppercase tracking-wider bg-emerald-600 text-white shadow-xs">
                    CRICHEROES VENUE HUB
                </span>
                <span class="text-xs text-gray-400 font-bold">• Book Grounds & Turfs</span>
            </div>
            <h2 class="font-black text-2xl text-gray-900 leading-tight mt-1">Cricket Grounds & Venues</h2>
        </div>

        @auth
            <a href="{{ route('grounds.create') }}"
               class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-extrabold text-sm shadow-md shadow-emerald-600/25 transition transform active:scale-95 cursor-pointer">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                </svg>
                <span>List Your Ground 🏟️</span>
            </a>
        @else
            <a href="{{ route('login') }}"
               class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-gray-900 hover:bg-gray-800 text-white font-bold text-sm shadow-md transition">
                <span>Sign In to List Ground</span>
            </a>
        @endauth
    </div>
@endsection

@section('content')
<div class="py-6 sm:py-8 bg-slate-900 min-h-screen text-slate-100 font-sans" x-data="{
    bookingModal: false,
    selectedGround: null,
    bookingDate: '{{ date('Y-m-d') }}',
    timeSlot: '06:00 AM - 09:00 AM',
    teamName: '',
    contactPhone: '',
    notes: '',
    openBooking(ground) {
        this.selectedGround = ground;
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
            <form method="GET" action="{{ route('grounds.index') }}" class="space-y-4">
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
                               placeholder="Search grounds by name, area, address, or landmark..."
                               class="w-full bg-slate-950 border border-white/10 rounded-xl pl-10 pr-10 py-2.5 text-xs sm:text-sm text-white placeholder-slate-500 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                        @if(!empty($search))
                            <a href="{{ route('grounds.index', ['tab' => $tab]) }}"
                               class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-white text-xs font-bold"
                               title="Clear search">
                                ✕
                            </a>
                        @endif
                    </div>

                    <button type="submit"
                            class="w-full sm:w-auto px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-extrabold text-xs shadow-md shadow-emerald-600/30 transition">
                        Find Grounds
                    </button>
                    @if(!empty($search) || !empty($city) || !empty($pitchType) || !empty($amenity) || !empty($maxPrice))
                        <a href="{{ route('grounds.index', ['tab' => $tab]) }}"
                           class="w-full sm:w-auto text-center px-4 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-slate-300 text-xs font-bold transition">
                            Reset Filters
                        </a>
                    @endif
                </div>

                <!-- Secondary Filters Row -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-3 border-t border-white/5 text-xs">
                    <!-- City Dropdown -->
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">City / Location</label>
                        <select name="city" onchange="this.form.submit()" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-xs text-white focus:ring-2 focus:ring-emerald-500">
                            <option value="all">All Cities</option>
                            @foreach($cities as $c)
                                <option value="{{ $c }}" {{ $city === $c ? 'selected' : '' }}>{{ $c }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Pitch Type Dropdown -->
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Pitch Type</label>
                        <select name="pitch_type" onchange="this.form.submit()" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-xs text-white focus:ring-2 focus:ring-emerald-500">
                            <option value="all">All Pitch Types</option>
                            <option value="Turf Pitch" {{ $pitchType === 'Turf Pitch' ? 'selected' : '' }}>🌱 Turf Pitch</option>
                            <option value="Natural Grass" {{ $pitchType === 'Natural Grass' ? 'selected' : '' }}>🌿 Natural Grass</option>
                            <option value="Cement / Matting" {{ $pitchType === 'Cement / Matting' ? 'selected' : '' }}>🏏 Cement / Matting</option>
                            <option value="Box Cricket / Astro Turf" {{ $pitchType === 'Box Cricket / Astro Turf' ? 'selected' : '' }}>⚡ Box Cricket / Astro Turf</option>
                        </select>
                    </div>

                    <!-- Amenities Filter -->
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Must-Have Amenity</label>
                        <select name="amenity" onchange="this.form.submit()" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-xs text-white focus:ring-2 focus:ring-emerald-500">
                            <option value="all">Any Amenities</option>
                            <option value="Floodlights" {{ $amenity === 'Floodlights' ? 'selected' : '' }}>💡 Floodlights (Day/Night)</option>
                            <option value="Pavilion" {{ $amenity === 'Pavilion' ? 'selected' : '' }}>🏛️ Pavilion / Stand</option>
                            <option value="Net Practice" {{ $amenity === 'Net Practice' ? 'selected' : '' }}>🎯 Net Practice Area</option>
                            <option value="Drinking Water" {{ $amenity === 'Drinking Water' ? 'selected' : '' }}>💧 RO Drinking Water</option>
                            <option value="Parking" {{ $amenity === 'Parking' ? 'selected' : '' }}>🚗 Car & Bike Parking</option>
                        </select>
                    </div>

                    <!-- Max Price Dropdown -->
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Budget / Slot</label>
                        <select name="max_price" onchange="this.form.submit()" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-xs text-white focus:ring-2 focus:ring-emerald-500">
                            <option value="">Any Price</option>
                            <option value="1000" {{ $maxPrice == '1000' ? 'selected' : '' }}>Under ₹1,000</option>
                            <option value="2000" {{ $maxPrice == '2000' ? 'selected' : '' }}>Under ₹2,000</option>
                            <option value="3000" {{ $maxPrice == '3000' ? 'selected' : '' }}>Under ₹3,000</option>
                            <option value="5000" {{ $maxPrice == '5000' ? 'selected' : '' }}>Under ₹5,000</option>
                        </select>
                    </div>
                </div>
            </form>
        </div>

        <!-- ============================================== -->
        <!-- NAVIGATION TABS -->
        <!-- ============================================== -->
        <div class="flex items-center gap-2 overflow-x-auto pb-2 scrollbar-none border-b border-white/10">
            <a href="{{ route('grounds.index', ['tab' => 'all']) }}"
               class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shrink-0 {{ $tab === 'all' ? 'bg-emerald-600 text-white shadow-md' : 'bg-white/5 hover:bg-white/10 text-slate-300' }}">
                <span>🏟️ Available Grounds</span>
                <span class="px-1.5 py-0.2 rounded-md bg-black/20 text-[10px]">{{ $grounds->total() }}</span>
            </a>

            @auth
                <a href="{{ route('grounds.index', ['tab' => 'my_bookings']) }}"
                   class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shrink-0 {{ $tab === 'my_bookings' ? 'bg-emerald-600 text-white shadow-md' : 'bg-white/5 hover:bg-white/10 text-slate-300' }}">
                    <span>📅 My Bookings</span>
                    <span class="px-1.5 py-0.2 rounded-md bg-black/20 text-[10px]">{{ $myBookings->count() }}</span>
                </a>

                @if($incomingRequests->count() > 0)
                    <a href="{{ route('grounds.index', ['tab' => 'incoming_requests']) }}"
                       class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shrink-0 {{ $tab === 'incoming_requests' ? 'bg-emerald-600 text-white shadow-md' : 'bg-white/5 hover:bg-white/10 text-slate-300' }}">
                        <span class="w-2 h-2 rounded-full bg-amber-400 animate-ping"></span>
                        <span>🔔 Incoming Ground Requests</span>
                        <span class="px-1.5 py-0.2 rounded-md bg-amber-500 text-slate-950 text-[10px] font-black">{{ $incomingRequests->where('status', 'pending')->count() }}</span>
                    </a>
                @endif

                <a href="{{ route('grounds.index', ['tab' => 'my_grounds']) }}"
                   class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shrink-0 {{ $tab === 'my_grounds' ? 'bg-emerald-600 text-white shadow-md' : 'bg-white/5 hover:bg-white/10 text-slate-300' }}">
                    <span>👑 My Listed Grounds</span>
                </a>
            @endauth
        </div>

        <!-- ============================================== -->
        <!-- TAB CONTENT: MY BOOKINGS -->
        <!-- ============================================== -->
        @if($tab === 'my_bookings')
            <div class="space-y-4">
                <h3 class="text-lg font-black text-white flex items-center gap-2">
                    <span>📅 My Ground Slot Bookings</span>
                </h3>

                @if($myBookings->count() > 0)
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach($myBookings as $b)
                            <div class="p-5 rounded-2xl bg-[#131b2e] border border-white/10 shadow-xl space-y-3">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="font-extrabold text-white text-base">{{ $b->ground->name }}</h4>
                                        <div class="text-xs text-slate-400 mt-0.5">📍 {{ $b->ground->city }} • {{ $b->ground->address }}</div>
                                    </div>
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase
                                        {{ $b->status === 'confirmed' ? 'bg-emerald-600/20 text-emerald-300 border border-emerald-500/30' : ($b->status === 'pending' ? 'bg-amber-600/20 text-amber-300 border border-amber-500/30 animate-pulse' : 'bg-slate-700 text-slate-400') }}">
                                        {{ $b->status }}
                                    </span>
                                </div>

                                <div class="p-3 rounded-xl bg-black/40 border border-white/5 grid grid-cols-2 gap-2 text-xs">
                                    <div>
                                        <span class="text-slate-400 block text-[10px]">Booking Date</span>
                                        <span class="font-bold text-white">{{ $b->booking_date->format('d M Y') }}</span>
                                    </div>
                                    <div>
                                        <span class="text-slate-400 block text-[10px]">Time Slot</span>
                                        <span class="font-bold text-emerald-400">{{ $b->time_slot }}</span>
                                    </div>
                                    <div>
                                        <span class="text-slate-400 block text-[10px]">Team Name</span>
                                        <span class="font-bold text-white">{{ $b->team_name }}</span>
                                    </div>
                                    <div>
                                        <span class="text-slate-400 block text-[10px]">Total Fee</span>
                                        <span class="font-bold text-white">₹{{ number_format($b->total_price, 2) }}</span>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between pt-2 border-t border-white/5 text-xs">
                                    <div class="flex items-center gap-2">
                                        @if($b->ground->whatsapp)
                                            <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $b->ground->whatsapp) }}?text=Hi,%20I%20have%20booked%20slot%20at%20{{ urlencode($b->ground->name) }}%20on%20{{ $b->booking_date->format('d-m-Y') }}" target="_blank"
                                               class="px-3 py-1.5 rounded-lg bg-emerald-600/20 text-emerald-300 border border-emerald-500/30 font-bold hover:bg-emerald-600 hover:text-white transition flex items-center gap-1">
                                                <span>💬 WhatsApp Ground Host</span>
                                            </a>
                                        @endif
                                    </div>

                                    @if($b->status === 'pending')
                                        <form method="POST" action="{{ route('grounds.bookings.status', $b) }}" onsubmit="return confirm('Cancel this booking request?');">
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
                        <div class="text-4xl mb-2">🏟️</div>
                        <h4 class="font-bold text-white">No Bookings Yet</h4>
                        <p class="text-slate-400 text-xs mt-1">Browse grounds and book your upcoming match slot.</p>
                        <a href="{{ route('grounds.index') }}" class="mt-4 inline-block px-4 py-2 rounded-xl bg-emerald-600 text-white text-xs font-bold">Browse Grounds</a>
                    </div>
                @endif
            </div>

        <!-- ============================================== -->
        <!-- TAB CONTENT: INCOMING REQUESTS (FOR GROUND HOSTS) -->
        <!-- ============================================== -->
        @elseif($tab === 'incoming_requests')
            <div class="space-y-4">
                <h3 class="text-lg font-black text-white flex items-center gap-2">
                    <span>🔔 Incoming Slot Booking Requests for Your Grounds</span>
                </h3>

                @if($incomingRequests->count() > 0)
                    <div class="space-y-3">
                        @foreach($incomingRequests as $req)
                            <div class="p-5 rounded-2xl bg-[#131b2e] border border-white/10 shadow-xl flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span class="font-black text-white text-base">{{ $req->team_name }}</span>
                                        <span class="text-xs text-slate-400">• for ground: <strong class="text-emerald-400">{{ $req->ground->name }}</strong></span>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase
                                            {{ $req->status === 'confirmed' ? 'bg-emerald-600/20 text-emerald-300' : ($req->status === 'pending' ? 'bg-amber-600/20 text-amber-300 animate-pulse' : 'bg-slate-700 text-slate-400') }}">
                                            {{ $req->status }}
                                        </span>
                                    </div>
                                    <div class="text-xs text-slate-300 flex flex-wrap items-center gap-3">
                                        <span>📅 <strong>{{ $req->booking_date->format('d M Y') }}</strong></span>
                                        <span>⏰ <strong>{{ $req->time_slot }}</strong></span>
                                        <span>💰 <strong>₹{{ number_format($req->total_price, 2) }}</strong></span>
                                        <span>📞 Booker Phone: <a href="tel:{{ $req->contact_phone }}" class="text-blue-400 font-bold underline">{{ $req->contact_phone }}</a></span>
                                    </div>
                                    @if($req->notes)
                                        <div class="text-xs text-slate-400 italic">"{{ $req->notes }}"</div>
                                    @endif
                                </div>

                                <div class="flex items-center gap-2 shrink-0">
                                    @if($req->status === 'pending')
                                        <form method="POST" action="{{ route('grounds.bookings.status', $req) }}" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="confirmed">
                                            <button type="submit" class="px-3.5 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-md transition">
                                                Confirm Slot ✅
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('grounds.bookings.status', $req) }}" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="cancelled">
                                            <button type="submit" class="px-3 py-1.5 rounded-xl bg-red-600/20 hover:bg-red-600/30 text-red-300 font-bold text-xs transition">
                                                Decline ✕
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-xs text-slate-400 font-bold">Handled ({{ ucfirst($req->status) }})</span>
                                    @endif

                                    @if($req->contact_phone)
                                        <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $req->contact_phone) }}?text=Hi%20{{ urlencode($req->team_name) }},%20regarding%20your%20booking%20at%20{{ urlencode($req->ground->name) }}" target="_blank"
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
                        <p class="text-slate-400 text-xs">No pending booking requests right now.</p>
                    </div>
                @endif
            </div>

        <!-- ============================================== -->
        <!-- TAB CONTENT: ALL GROUNDS CARDS -->
        <!-- ============================================== -->
        @else
            @if($grounds->count() > 0)
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($grounds as $ground)
                        <div class="rounded-2xl bg-[#131b2e] border border-white/10 overflow-hidden shadow-2xl flex flex-col justify-between hover:border-emerald-500/40 transition group">

                            <!-- Ground Media Header -->
                            <div class="relative h-48 bg-gradient-to-tr from-slate-950 to-slate-800 overflow-hidden">
                                @if($ground->image_url)
                                    <img src="{{ $ground->image_url }}" alt="{{ $ground->name }}" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                                @else
                                    <div class="w-full h-full flex flex-col items-center justify-center text-slate-600">
                                        <span class="text-5xl">🏟️</span>
                                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 mt-2">Cricket Ground</span>
                                    </div>
                                @endif

                                <!-- Badges Floating -->
                                <div class="absolute top-3 left-3 flex flex-wrap gap-1.5">
                                    <span class="px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider bg-black/70 backdrop-blur-md text-emerald-400 border border-emerald-500/30">
                                        {{ $ground->pitch_type }}
                                    </span>
                                </div>

                                <div class="absolute top-3 right-3">
                                    <span class="px-2.5 py-1 rounded-lg text-xs font-black bg-emerald-600 text-white shadow-lg">
                                        ₹{{ number_format($ground->price_per_slot, 0) }} <span class="text-[10px] font-normal">/ {{ $ground->slot_duration }}</span>
                                    </span>
                                </div>

                                <div class="absolute bottom-2 left-3 right-3 flex items-center justify-between text-xs text-white drop-shadow">
                                    <div class="flex items-center gap-1 font-bold">
                                        <span>📍</span>
                                        <span>{{ $ground->city }}</span>
                                    </div>
                                    @if(auth()->check() && auth()->id() === $ground->user_id)
                                        <span class="px-2 py-0.5 rounded bg-blue-600 text-[10px] font-black uppercase">Your Listing</span>
                                    @endif
                                </div>
                            </div>

                            <!-- Ground Details Body -->
                            <div class="p-5 space-y-4 flex-1 flex flex-col justify-between">
                                <div>
                                    <a href="{{ route('grounds.show', $ground) }}" class="font-extrabold text-white text-lg hover:text-emerald-400 transition leading-snug">
                                        {{ $ground->name }}
                                    </a>
                                    <p class="text-xs text-slate-400 mt-1 line-clamp-2">{{ $ground->address }}</p>

                                    <!-- Amenities Chips -->
                                    @if(!empty($ground->amenities) && is_array($ground->amenities))
                                        <div class="flex flex-wrap gap-1.5 mt-3">
                                            @foreach(array_slice($ground->amenities, 0, 4) as $am)
                                                <span class="px-2 py-0.5 rounded-md bg-white/5 border border-white/10 text-[10px] font-semibold text-slate-300">
                                                    ✓ {{ $am }}
                                                </span>
                                            @endforeach
                                            @if(count($ground->amenities) > 4)
                                                <span class="px-1.5 py-0.5 rounded-md bg-white/5 text-[10px] text-slate-400">+{{ count($ground->amenities) - 4 }} more</span>
                                            @endif
                                        </div>
                                    @endif
                                </div>

                                <!-- Actions Strip -->
                                <div class="pt-3 border-t border-white/5 flex items-center justify-between gap-2">
                                    @auth
                                        <button type="button"
                                                @click="openBooking({{ json_encode($ground) }})"
                                                class="flex-1 py-2 px-3 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-extrabold text-xs shadow-md shadow-emerald-600/20 transition flex items-center justify-center gap-1.5">
                                            <span>Book Slot 📅</span>
                                        </button>
                                    @else
                                        <a href="{{ route('login') }}"
                                           class="flex-1 py-2 px-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-extrabold text-xs text-center transition">
                                            Sign in to Book 📅
                                        </a>
                                    @endauth

                                    <a href="{{ route('grounds.show', $ground) }}"
                                       class="px-3 py-2 rounded-xl bg-white/10 hover:bg-white/20 text-slate-200 text-xs font-bold transition">
                                        Details
                                    </a>

                                    @if($ground->whatsapp)
                                        <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $ground->whatsapp) }}?text=Hi,%20I%20am%20interested%20in%20booking%20slots%20at%20{{ urlencode($ground->name) }}" target="_blank"
                                           class="p-2 rounded-xl bg-emerald-500/10 text-emerald-400 hover:bg-emerald-500 hover:text-white transition" title="Chat on WhatsApp">
                                            💬
                                        </a>
                                    @endif
                                </div>
                            </div>

                        </div>
                    @endforeach
                </div>

                <div class="mt-6">
                    {{ $grounds->links() }}
                </div>
            @else
                <div class="bg-[#131b2e] border border-white/5 rounded-3xl p-10 sm:p-16 text-center max-w-lg mx-auto">
                    <div class="w-20 h-20 rounded-full bg-emerald-600/10 border-2 border-dashed border-emerald-500/30 flex items-center justify-center text-4xl mx-auto mb-4">
                        🏟️
                    </div>
                    <h3 class="text-xl font-black text-white">No Grounds Found</h3>
                    <p class="text-slate-400 text-xs sm:text-sm mt-1.5 leading-relaxed">
                        Try resetting your search or city filters, or be the first to list a cricket turf ground in your city!
                    </p>
                    <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                        <a href="{{ route('grounds.index') }}" class="px-5 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-white text-xs font-bold transition">
                            Clear Filters ✕
                        </a>
                        @auth
                            <a href="{{ route('grounds.create') }}" class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition">
                                + List Your Ground 🏟️
                            </a>
                        @endauth
                    </div>
                </div>
            @endif
        @endif

    </div>

    <!-- ============================================== -->
    <!-- QUICK GROUND BOOKING MODAL -->
    <!-- ============================================== -->
    <div x-cloak x-show="bookingModal" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-black/80 backdrop-blur-md"
         @keydown.escape.window="bookingModal = false">
        <div class="relative bg-slate-900 border border-white/10 rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-4"
             @click.away="bookingModal = false">

            <div class="flex items-center justify-between pb-3 border-b border-white/10">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-emerald-400">📅 BOOK MATCH SLOT</span>
                    <h3 class="text-lg font-black text-white" x-text="selectedGround ? selectedGround.name : 'Book Ground'"></h3>
                    <div class="text-xs text-slate-400" x-text="selectedGround ? ('📍 ' + selectedGround.city + ' • Fee: ₹' + selectedGround.price_per_slot + ' / ' + selectedGround.slot_duration) : ''"></div>
                </div>
                <button type="button" @click="bookingModal = false" class="text-slate-400 hover:text-white text-sm font-bold">✕</button>
            </div>

            <form method="POST" :action="selectedGround ? ('/grounds/' + selectedGround.id + '/book') : '#'" class="space-y-4 text-xs">
                @csrf

                <div>
                    <label class="block font-bold text-slate-300 uppercase tracking-wider mb-1">Your Team / Club Name *</label>
                    <input type="text" name="team_name" x-model="teamName" required placeholder="e.g. Royal Strikers CC"
                           class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2.5 text-xs text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-300 uppercase tracking-wider mb-1">Booking Date *</label>
                        <input type="date" name="booking_date" x-model="bookingDate" min="{{ date('Y-m-d') }}" required
                               class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2.5 text-xs text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-300 uppercase tracking-wider mb-1">Time Slot *</label>
                        <select name="time_slot" x-model="timeSlot" required
                                class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2.5 text-xs text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                            <option value="06:00 AM - 09:00 AM">🌅 Morning (06:00 AM - 09:00 AM)</option>
                            <option value="09:00 AM - 12:00 PM">☀️ Midday (09:00 AM - 12:00 PM)</option>
                            <option value="01:00 PM - 04:00 PM">🌤️ Afternoon (01:00 PM - 04:00 PM)</option>
                            <option value="04:00 PM - 07:00 PM">🌇 Evening (04:00 PM - 07:00 PM)</option>
                            <option value="07:00 PM - 10:00 PM (Floodlights)">💡 Night Slot (07:00 PM - 10:00 PM)</option>
                            <option value="Full Day Tournament">🏆 Full Day (Tournament)</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-slate-300 uppercase tracking-wider mb-1">Captain / Organizer Contact Phone *</label>
                    <input type="text" name="contact_phone" x-model="contactPhone" required placeholder="Enter WhatsApp or Phone number"
                           class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2.5 text-xs text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>

                <div>
                    <label class="block font-bold text-slate-300 uppercase tracking-wider mb-1">Match Notes (Optional)</label>
                    <textarea name="notes" x-model="notes" rows="2" placeholder="e.g. 20 Overs Leather ball match, need extra cricket balls or drinks..."
                              class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2 text-xs text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none"></textarea>
                </div>

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-white/10">
                    <button type="button" @click="bookingModal = false" class="px-4 py-2 text-xs font-bold text-slate-400 hover:text-white">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-extrabold text-xs shadow-lg shadow-emerald-600/30 transition">
                        Confirm & Request Slot 🏏
                    </button>
                </div>
            </form>

        </div>
    </div>

</div>
@endsection
