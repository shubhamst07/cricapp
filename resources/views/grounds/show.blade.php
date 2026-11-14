@extends('layouts.app')

@section('header')
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('grounds.index') }}" class="p-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-white/10 dark:hover:bg-white/15 text-slate-700 dark:text-slate-200 transition" title="Back to Grounds">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>
            <div>
                <span class="text-[11px] font-black uppercase text-emerald-600 dark:text-emerald-400 tracking-wider">CRICHEROES GROUND ARENA</span>
                <h2 class="font-black text-xl sm:text-2xl text-slate-900 dark:text-white leading-tight">
                    {{ $ground->name }}
                </h2>
            </div>
        </div>

        @if($isOwner)
            <span class="px-3 py-1 rounded-full text-xs font-black bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-700/40">
                👑 You Own This Venue
            </span>
        @endif
    </div>
@endsection

@section('content')
<div class="py-8 bg-slate-900 min-h-screen text-slate-100 font-sans" x-data="{
    bookingModal: false,
    bookingDate: '{{ date('Y-m-d') }}',
    timeSlot: '06:00 AM - 09:00 AM',
    teamName: '',
    contactPhone: '',
    notes: '',
    photos: {{ json_encode($ground->images_list) }},
    activePhotoIdx: 0,
    prevPhoto() {
        if (this.photos.length <= 1) return;
        this.activePhotoIdx = (this.activePhotoIdx - 1 + this.photos.length) % this.photos.length;
    },
    nextPhoto() {
        if (this.photos.length <= 1) return;
        this.activePhotoIdx = (this.activePhotoIdx + 1) % this.photos.length;
    }
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

        <!-- Ground Hero & Multi-Photo Gallery -->
        <div class="relative rounded-3xl overflow-hidden bg-slate-950 border border-white/10 shadow-2xl">
            <div class="relative h-72 sm:h-96 w-full overflow-hidden bg-gradient-to-tr from-slate-950 via-slate-900 to-slate-800 group">
                <template x-if="photos.length > 0">
                    <img :src="photos[activePhotoIdx]" :alt="'{{ $ground->name }} photo ' + (activePhotoIdx + 1)"
                         class="w-full h-full object-cover transition-all duration-300">
                </template>
                <template x-if="photos.length === 0">
                    <div class="w-full h-full flex flex-col items-center justify-center text-slate-700">
                        <span class="text-7xl">🏟️</span>
                        <span class="text-sm font-bold uppercase tracking-wider text-slate-500 mt-2">Cricket Ground & Turf</span>
                    </div>
                </template>

                <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/40 to-transparent pointer-events-none"></div>

                <!-- Floating Badges -->
                <div class="absolute top-4 left-4 flex flex-wrap gap-2 pointer-events-none">
                    <span class="px-3 py-1.5 rounded-xl text-xs font-black uppercase tracking-wider bg-black/60 backdrop-blur-md text-emerald-400 border border-emerald-500/40">
                        {{ $ground->pitch_type }}
                    </span>
                    <span class="px-3 py-1.5 rounded-xl text-xs font-black uppercase tracking-wider bg-black/60 backdrop-blur-md text-amber-300 border border-amber-500/40">
                        📍 {{ $ground->city }}
                    </span>
                </div>

                <!-- Photo Counter Indicator -->
                <div x-show="photos.length > 1" class="absolute top-4 right-4 z-10">
                    <span class="px-3 py-1.5 rounded-xl text-xs font-extrabold bg-black/75 backdrop-blur-md text-white border border-white/20 shadow-lg flex items-center gap-1.5">
                        <span>📸</span>
                        <span x-text="(activePhotoIdx + 1) + ' / ' + photos.length"></span>
                    </span>
                </div>

                <!-- Prev & Next Controls -->
                <div x-show="photos.length > 1" class="absolute inset-y-0 left-3 flex items-center z-10">
                    <button type="button" @click="prevPhoto()"
                            class="w-10 h-10 rounded-full bg-black/60 hover:bg-black/90 backdrop-blur text-white flex items-center justify-center border border-white/20 shadow-xl transition transform active:scale-95"
                            title="Previous Photo">
                        ‹
                    </button>
                </div>
                <div x-show="photos.length > 1" class="absolute inset-y-0 right-3 flex items-center z-10">
                    <button type="button" @click="nextPhoto()"
                            class="w-10 h-10 rounded-full bg-black/60 hover:bg-black/90 backdrop-blur text-white flex items-center justify-center border border-white/20 shadow-xl transition transform active:scale-95"
                            title="Next Photo">
                        ›
                    </button>
                </div>

                <div class="absolute bottom-6 left-6 right-6 flex flex-col sm:flex-row sm:items-end justify-between gap-4 z-10 pointer-events-auto">
                    <div>
                        <h1 class="text-2xl sm:text-4xl font-black text-white leading-tight drop-shadow-md">
                            {{ $ground->name }}
                        </h1>
                        <p class="text-xs sm:text-sm text-slate-300 mt-1 flex items-center gap-1.5 drop-shadow">
                            <span>📍</span>
                            <span>{{ $ground->address }}</span>
                        </p>
                    </div>

                    <div class="shrink-0 flex items-center gap-3">
                        <div class="text-right">
                            <span class="text-[10px] text-slate-400 uppercase font-bold block">Slot Fee</span>
                            <span class="text-2xl sm:text-3xl font-black text-emerald-400">₹{{ number_format($ground->price_per_slot, 0) }}</span>
                            <span class="text-xs text-slate-400 font-normal">/ {{ $ground->slot_duration }}</span>
                        </div>

                        @auth
                            <button type="button" @click="bookingModal = true"
                                    class="px-6 py-3 rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-black text-sm shadow-xl shadow-emerald-600/30 transition transform active:scale-95">
                                Book Slot 📅
                            </button>
                        @else
                            <a href="{{ route('login') }}"
                               class="px-6 py-3 rounded-2xl bg-emerald-600 hover:bg-emerald-500 text-white font-black text-sm transition">
                                Sign In to Book 📅
                            </a>
                        @endauth
                    </div>
                </div>
            </div>

            <!-- Thumbnail Selector Strip (if > 1 photo) -->
            <div x-show="photos.length > 1" class="p-3 bg-[#0d1322] border-t border-white/10 flex items-center gap-2.5 overflow-x-auto scrollbar-thin">
                <span class="text-[11px] font-black uppercase tracking-wider text-slate-400 pl-2 shrink-0">Gallery:</span>
                <template x-for="(photoUrl, pIndex) in photos" :key="pIndex">
                    <button type="button"
                            @click="activePhotoIdx = pIndex"
                            class="relative shrink-0 w-16 h-12 rounded-xl overflow-hidden border-2 transition transform active:scale-95"
                            :class="activePhotoIdx === pIndex ? 'border-emerald-500 ring-2 ring-emerald-500/50 scale-105' : 'border-white/10 opacity-60 hover:opacity-100'">
                        <img :src="photoUrl" class="w-full h-full object-cover">
                    </button>
                </template>
            </div>
        </div>

        <!-- 2 Column Details: Left (Overview & Amenities), Right (Contact & Host) -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Left 2 Cols: Details -->
            <div class="lg:col-span-2 space-y-6">

                <!-- Ground Key Stats Bar -->
                <div class="grid grid-cols-3 gap-4">
                    <div class="p-5 rounded-2xl bg-[#131b2e] border border-white/10 text-center">
                        <span class="text-[10px] uppercase font-bold text-slate-400 block">Matches Hosted</span>
                        <span class="text-3xl font-black text-emerald-400 mt-1 block">{{ $ground->matches_hosted ?? 0 }}+</span>
                    </div>

                    <div class="p-5 rounded-2xl bg-[#131b2e] border border-white/10 text-center">
                        <span class="text-[10px] uppercase font-bold text-slate-400 block">Pitch Type</span>
                        <span class="text-sm font-black text-white mt-2 block">{{ $ground->pitch_type }}</span>
                    </div>

                    <div class="p-5 rounded-2xl bg-[#131b2e] border border-white/10 text-center">
                        <span class="text-[10px] uppercase font-bold text-slate-400 block">Ground Status</span>
                        <span class="text-xs font-black uppercase text-emerald-400 mt-3 inline-block px-2.5 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20">
                            {{ ucfirst($ground->status) }} 🟢
                        </span>
                    </div>
                </div>

                <!-- Amenities Box -->
                <div class="p-6 rounded-3xl bg-[#131b2e] border border-white/10 shadow-xl space-y-3">
                    <h3 class="text-base font-black text-white flex items-center gap-2">
                        <span>✨ Ground Amenities & Features</span>
                    </h3>

                    @if(!empty($ground->amenities) && is_array($ground->amenities))
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 pt-2">
                            @foreach($ground->amenities as $am)
                                <div class="p-3 rounded-xl bg-white/5 border border-white/5 flex items-center gap-2 text-xs font-bold text-slate-200">
                                    <span class="text-emerald-400">✓</span>
                                    <span>{{ $am }}</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-xs text-slate-400">Standard local pitch facilities available.</p>
                    @endif
                </div>

                <!-- Description & Rules -->
                <div class="p-6 rounded-3xl bg-[#131b2e] border border-white/10 shadow-xl space-y-3">
                    <h3 class="text-base font-black text-white flex items-center gap-2">
                        <span>📝 Ground Overview & Match Guidelines</span>
                    </h3>
                    <div class="text-xs sm:text-sm text-slate-300 leading-relaxed space-y-2 whitespace-pre-line">
                        {{ $ground->description ?: 'High quality cricket pitch maintained for competitive local cricket tournaments, corporate matches, and friendly weekend games.' }}
                    </div>
                </div>

                <!-- Recent Matches Hosted at this Arena -->
                <div class="p-6 rounded-3xl bg-[#131b2e] border border-white/10 shadow-xl space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-black text-white flex items-center gap-2">
                            <span>🏟️</span>
                            <span>Matches Hosted at this Arena</span>
                        </h3>
                        <span class="text-xs text-emerald-400 font-bold">{{ $ground->matches_hosted ?? 0 }} Total Matches</span>
                    </div>

                    @if(isset($hostedMatches) && $hostedMatches->isNotEmpty())
                        <div class="space-y-2.5">
                            @foreach($hostedMatches as $m)
                                <a href="{{ route('matches.show', $m) }}"
                                   class="block p-3.5 rounded-2xl bg-white/5 hover:bg-white/10 border border-white/10 transition group">
                                    <div class="flex items-center justify-between text-xs mb-1">
                                        <span class="font-extrabold text-white group-hover:text-emerald-400 transition">{{ $m->team1_name }} vs {{ $m->team2_name }}</span>
                                        <span class="text-[10px] text-slate-400">{{ $m->match_date?->format('M d, Y') }}</span>
                                    </div>
                                    <div class="flex items-center justify-between text-[11px] text-slate-400">
                                        <span>🏆 {{ $m->tournament_name ?? 'Club Match' }} • {{ $m->match_type ?? 'T20' }}</span>
                                        <span class="font-bold {{ $m->status === 'completed' ? 'text-emerald-400' : 'text-amber-400' }}">
                                            {{ ucfirst($m->status) }}
                                        </span>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-6 text-xs text-slate-400">
                            <span>No matches hosted on LiveCricket recorded yet.</span>
                        </div>
                    @endif
                </div>

            </div>

            <!-- Right Col: Host & Direct Contact -->
            <div class="space-y-6">

                <div class="p-6 rounded-3xl bg-[#131b2e] border border-white/10 shadow-xl space-y-4">
                    <h3 class="text-base font-black text-white">Contact & Quick Booking</h3>

                    <div class="p-4 rounded-2xl bg-black/40 border border-white/5 space-y-3 text-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Ground Host:</span>
                            <span class="font-bold text-white">{{ $ground->user->name }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">City:</span>
                            <span class="font-bold text-emerald-400">{{ $ground->city }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Pitch Type:</span>
                            <span class="font-bold text-white">{{ $ground->pitch_type }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Phone:</span>
                            <a href="tel:{{ $ground->phone }}" class="font-bold text-blue-400 underline">{{ $ground->phone }}</a>
                        </div>
                    </div>

                    <div class="space-y-2 pt-2">
                        @auth
                            <button type="button" @click="bookingModal = true"
                                    class="w-full py-3 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-extrabold text-xs shadow-lg shadow-emerald-600/30 transition">
                                Request Slot Booking 📅
                            </button>
                        @else
                            <a href="{{ route('login') }}" class="block text-center w-full py-3 rounded-xl bg-emerald-600 text-white font-bold text-xs">
                                Sign In to Book Slot
                            </a>
                        @endauth

                        @if($ground->whatsapp)
                            <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $ground->whatsapp) }}?text=Hi,%20I%20am%20interested%20in%20booking%20slots%20at%20{{ urlencode($ground->name) }}" target="_blank"
                               class="w-full py-2.5 rounded-xl bg-emerald-500/10 text-emerald-300 border border-emerald-500/20 hover:bg-emerald-500 hover:text-white font-bold text-xs transition flex items-center justify-center gap-2">
                                <span>💬 Chat on WhatsApp</span>
                            </a>
                        @endif

                        <a href="tel:{{ $ground->phone }}"
                           class="w-full py-2.5 rounded-xl bg-white/5 hover:bg-white/10 text-slate-300 font-bold text-xs transition flex items-center justify-center gap-2">
                            <span>📞 Call Ground Manager</span>
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
                    <span class="text-[10px] font-black uppercase tracking-wider text-emerald-400">📅 BOOK MATCH SLOT</span>
                    <h3 class="text-lg font-black text-white">{{ $ground->name }}</h3>
                    <div class="text-xs text-slate-400">Fee: ₹{{ number_format($ground->price_per_slot, 2) }} / {{ $ground->slot_duration }}</div>
                </div>
                <button type="button" @click="bookingModal = false" class="text-slate-400 hover:text-white text-sm font-bold">✕</button>
            </div>

            <form method="POST" action="{{ route('grounds.book', $ground) }}" class="space-y-4 text-xs">
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
                    <textarea name="notes" x-model="notes" rows="2" placeholder="e.g. 20 Overs Leather ball match..."
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
