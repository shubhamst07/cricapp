@extends('layouts.app')

@section('title', 'Need Players? Post Cricket Match Meetup | LiveCricket')

@section('header')
    <div class="flex items-center justify-between">
        <div>
            <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20 mb-1">
                🏏 खिलाड़ी चाहिए • Match Squad Builder
            </div>
            <h2 class="font-black text-2xl sm:text-3xl text-slate-900 dark:text-white leading-tight">
                Organise Match / Find Players
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                Sunday ya weekend match ke liye players kam pad rahe hain? Post details and nearby cricket lovers will join your squad!
            </p>
        </div>
        <a href="{{ route('meetups.index') }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-white/10 text-slate-700 dark:text-slate-300 hover:text-white hover:bg-slate-200 dark:hover:bg-white/20 text-xs font-bold transition">
            ← Back to Matches
        </a>
    </div>
@endsection

@section('content')
<div class="py-6 sm:py-10 bg-slate-50 dark:bg-transparent min-h-screen text-slate-900 dark:text-slate-100 font-sans transition-colors duration-200" x-data="{
    groundsData: {{ Js::from($grounds->keyBy('id')) }},
    dateQuickSelect(val) {
        document.getElementById('match_date').value = val;
    },
    onGroundSelect(val) {
        if (!val || !this.groundsData[val]) return;
        const g = this.groundsData[val];
        if (g.name) document.getElementById('location_name').value = g.name;
        if (g.city) document.getElementById('city').value = g.city;
        if (g.address) document.getElementById('location_address').value = g.address;
        const mapsInput = document.getElementById('google_maps_url');
        if (mapsInput && !mapsInput.value) {
            mapsInput.value = 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent((g.name || '') + ', ' + (g.address || '') + ', ' + (g.city || ''));
        }
    }
}">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

        @if(isset($errors) && $errors->any())
            <div class="mb-6 p-4 rounded-2xl bg-rose-500/15 border border-rose-500/30 text-rose-300 text-xs sm:text-sm font-semibold shadow-lg">
                <div class="font-bold flex items-center gap-2 mb-1">
                    <span>⚠️ Please fix the following errors:</span>
                </div>
                <ul class="list-disc list-inside space-y-0.5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('meetups.store') }}" method="POST" class="space-y-6">
            @csrf

            <!-- ========================================== -->
            <!-- SECTION 1: MATCH BASICS & LOCATION -->
            <!-- ========================================== -->
            <div class="p-5 sm:p-7 rounded-3xl bg-[#131b2e] border border-white/10 shadow-2xl space-y-5">
                <div class="flex items-center gap-3 border-b border-white/10 pb-3">
                    <span class="w-8 h-8 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center font-black text-sm">
                        1
                    </span>
                    <div>
                        <h3 class="font-black text-base sm:text-lg text-white">Match Overview & Location</h3>
                        <p class="text-xs text-slate-400">Where and what kind of cricket are you playing?</p>
                    </div>
                </div>

                <!-- Match Title -->
                <div>
                    <label for="title" class="block text-xs font-black uppercase tracking-wider text-slate-300 mb-1.5">
                        Match Title / Headline <span class="text-rose-400">*</span>
                    </label>
                    <input type="text" id="title" name="title" value="{{ old('title', 'Sunday Morning Tennis Match @ ' . (Auth::user()->city ?? 'Shimla')) }}" required
                           placeholder="e.g. Sunday Friendly Match - Need 4 Batsmen & 1 Bowler"
                           class="w-full px-4 py-3 rounded-2xl bg-slate-900 border border-white/10 text-white placeholder-slate-500 text-sm focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition" />
                    <p class="text-[11px] text-slate-400 mt-1">Make it clear and attractive for nearby players.</p>
                </div>

                <!-- City & Ground / Location Name -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="city" class="block text-xs font-black uppercase tracking-wider text-slate-300 mb-1.5">
                            City / Town <span class="text-rose-400">*</span>
                        </label>
                        <input type="text" id="city" name="city" value="{{ old('city', Auth::user()->city ?? 'Shimla') }}" required
                               placeholder="e.g. Shimla, Theog, Solan, Chandigarh"
                               class="w-full px-4 py-3 rounded-2xl bg-slate-900 border border-white/10 text-white placeholder-slate-500 text-sm focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition" />
                    </div>

                    <div>
                        <label for="location_name" class="block text-xs font-black uppercase tracking-wider text-slate-300 mb-1.5">
                            Ground / Venue Name <span class="text-rose-400">*</span>
                        </label>
                        <input type="text" id="location_name" name="location_name" value="{{ old('location_name') }}" required
                               placeholder="e.g. Ridge Maidan / Police Ground / Box Turf"
                               class="w-full px-4 py-3 rounded-2xl bg-slate-900 border border-white/10 text-white placeholder-slate-500 text-sm focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition" />
                    </div>
                </div>

                <!-- Ground Landmark / Address & Linked Ground -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="location_address" class="block text-xs font-black uppercase tracking-wider text-slate-300 mb-1.5">
                            Landmark / Address (Optional)
                        </label>
                        <input type="text" id="location_address" name="location_address" value="{{ old('location_address') }}"
                               placeholder="e.g. Near Mall Road, Opp. Christ Church"
                               class="w-full px-4 py-3 rounded-2xl bg-slate-900 border border-white/10 text-white placeholder-slate-500 text-sm focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition" />
                    </div>

                    <div>
                        <label for="ground_id" class="block text-xs font-black uppercase tracking-wider text-slate-300 mb-1.5 flex items-center justify-between">
                            <span>Select Registered Ground (Optional)</span>
                            <span class="text-[10px] text-amber-400 font-bold lowercase">auto-fills venue details</span>
                        </label>
                        <select id="ground_id" name="ground_id"
                                @change="onGroundSelect($event.target.value)"
                                class="w-full px-4 py-3 rounded-2xl bg-slate-900 border border-white/10 text-white text-sm focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition">
                            <option value="">-- Custom Venue / Not Listed --</option>
                            @foreach($grounds as $ground)
                                <option value="{{ $ground->id }}" {{ old('ground_id') == $ground->id ? 'selected' : '' }}>
                                    🏟️ {{ $ground->name }} ({{ $ground->city }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Google Maps Location Link -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="google_maps_url" class="block text-xs font-black uppercase tracking-wider text-emerald-400">
                            📍 Google Maps Location Link (Optional)
                        </label>
                        <span class="text-[11px] text-slate-400">Doosre players ko direct ground map pin milega</span>
                    </div>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-emerald-400 font-bold text-sm">
                            🗺️
                        </span>
                        <input type="url" id="google_maps_url" name="google_maps_url" value="{{ old('google_maps_url') }}"
                               placeholder="e.g. https://maps.app.goo.gl/... or paste Google Map location link"
                               class="w-full pl-11 pr-4 py-3 rounded-2xl bg-slate-900 border border-emerald-500/30 text-white placeholder-slate-500 text-sm focus:border-emerald-400 focus:ring-1 focus:ring-emerald-400 transition" />
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">
                        Google Maps me ground ka link copy karke yahan paste karein, taaki match dekhne wale ya squad join karne wale players 1-tap me ground pahunch sakein.
                    </p>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- SECTION 2: DATE, TIME & FORMAT -->
            <!-- ========================================== -->
            <div class="p-5 sm:p-7 rounded-3xl bg-[#131b2e] border border-white/10 shadow-2xl space-y-5">
                <div class="flex items-center gap-3 border-b border-white/10 pb-3">
                    <span class="w-8 h-8 rounded-xl bg-indigo-500/20 text-indigo-400 flex items-center justify-center font-black text-sm">
                        2
                    </span>
                    <div>
                        <h3 class="font-black text-base sm:text-lg text-white">Date, Time & Cricket Format</h3>
                        <p class="text-xs text-slate-400">Schedule your match timings and ball type</p>
                    </div>
                </div>

                <!-- Date & Quick Shortcuts -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="match_date" class="block text-xs font-black uppercase tracking-wider text-slate-300">
                            Match Date <span class="text-rose-400">*</span>
                        </label>
                        <div class="flex items-center gap-1.5">
                            <button type="button" @click="dateQuickSelect('{{ \Carbon\Carbon::today()->format('Y-m-d') }}')"
                                    class="px-2 py-0.5 rounded-lg bg-white/5 hover:bg-white/15 text-[11px] font-bold text-slate-300 hover:text-white transition">
                                Today
                            </button>
                            <button type="button" @click="dateQuickSelect('{{ \Carbon\Carbon::tomorrow()->format('Y-m-d') }}')"
                                    class="px-2 py-0.5 rounded-lg bg-white/5 hover:bg-white/15 text-[11px] font-bold text-slate-300 hover:text-white transition">
                                Tomorrow
                            </button>
                            <button type="button" @click="dateQuickSelect('{{ (\Carbon\Carbon::now()->isSunday() ? \Carbon\Carbon::today() : \Carbon\Carbon::now()->next(\Carbon\Carbon::SUNDAY))->format('Y-m-d') }}')"
                                    class="px-2 py-0.5 rounded-lg bg-amber-500/20 hover:bg-amber-500/30 text-[11px] font-bold text-amber-300 border border-amber-500/30 transition">
                                ⚡ This Sunday
                            </button>
                        </div>
                    </div>
                    <input type="date" id="match_date" name="match_date" value="{{ old('match_date', $defaultDate->format('Y-m-d')) }}" required
                           min="{{ \Carbon\Carbon::today()->format('Y-m-d') }}"
                           class="w-full px-4 py-3 rounded-2xl bg-slate-900 border border-white/10 text-white text-sm focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition" />
                </div>

                <!-- Match Time & Format -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="match_time" class="block text-xs font-black uppercase tracking-wider text-slate-300 mb-1.5">
                            Reporting / Match Time <span class="text-rose-400">*</span>
                        </label>
                        <input type="text" id="match_time" name="match_time" value="{{ old('match_time', '07:30 AM') }}" required
                               placeholder="e.g. 07:00 AM, 04:30 PM"
                               class="w-full px-4 py-3 rounded-2xl bg-slate-900 border border-white/10 text-white placeholder-slate-500 text-sm focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition" />
                    </div>

                    <div>
                        <label for="match_format" class="block text-xs font-black uppercase tracking-wider text-slate-300 mb-1.5">
                            Match Type <span class="text-rose-400">*</span>
                        </label>
                        <select id="match_format" name="match_format" required
                                class="w-full px-4 py-3 rounded-2xl bg-slate-900 border border-white/10 text-white text-sm focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition">
                            <option value="friendly" {{ old('match_format') == 'friendly' ? 'selected' : '' }}>🤝 Friendly Match (10-20 overs)</option>
                            <option value="box_cricket" {{ old('match_format') == 'box_cricket' ? 'selected' : '' }}>🥅 Box Cricket / Turf Match</option>
                            <option value="tournament_practice" {{ old('match_format') == 'tournament_practice' ? 'selected' : '' }}>🏆 Tournament Practice Match</option>
                            <option value="open_net" {{ old('match_format') == 'open_net' ? 'selected' : '' }}>🎯 Open Net Practice Session</option>
                        </select>
                    </div>
                </div>

                <!-- Ball Type & Skill Level -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="ball_type" class="block text-xs font-black uppercase tracking-wider text-slate-300 mb-1.5">
                            Ball Type <span class="text-rose-400">*</span>
                        </label>
                        <select id="ball_type" name="ball_type" required
                                class="w-full px-4 py-3 rounded-2xl bg-slate-900 border border-white/10 text-white text-sm focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition">
                            <option value="tennis" {{ old('ball_type') == 'tennis' ? 'selected' : '' }}>🎾 Tennis Ball (Most Popular)</option>
                            <option value="leather" {{ old('ball_type') == 'leather' ? 'selected' : '' }}>🏏 Season Leather Ball</option>
                            <option value="tape" {{ old('ball_type') == 'tape' ? 'selected' : '' }}>⚾ Tape Ball</option>
                            <option value="box_turf" {{ old('ball_type') == 'box_turf' ? 'selected' : '' }}>🟡 Box Turf Ball</option>
                        </select>
                    </div>

                    <div>
                        <label for="skill_level" class="block text-xs font-black uppercase tracking-wider text-slate-300 mb-1.5">
                            Skill Level / Criteria <span class="text-rose-400">*</span>
                        </label>
                        <select id="skill_level" name="skill_level" required
                                class="w-full px-4 py-3 rounded-2xl bg-slate-900 border border-white/10 text-white text-sm focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition">
                            <option value="all" {{ old('skill_level') == 'all' ? 'selected' : '' }}>🌟 All Welcome (Sabhi khel sakte hain)</option>
                            <option value="beginner" {{ old('skill_level') == 'beginner' ? 'selected' : '' }}>🌱 Beginners / Just for Fun</option>
                            <option value="intermediate" {{ old('skill_level') == 'intermediate' ? 'selected' : '' }}>⚡ Regular / Intermediate Players</option>
                            <option value="advanced" {{ old('skill_level') == 'advanced' ? 'selected' : '' }}>🔥 Advanced / Competitive Club</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- SECTION 3: SQUAD SLOTS & COST -->
            <!-- ========================================== -->
            <div class="p-5 sm:p-7 rounded-3xl bg-[#131b2e] border border-white/10 shadow-2xl space-y-5">
                <div class="flex items-center gap-3 border-b border-white/10 pb-3">
                    <span class="w-8 h-8 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center font-black text-sm">
                        3
                    </span>
                    <div>
                        <h3 class="font-black text-base sm:text-lg text-white">Squad Slots & Cost</h3>
                        <p class="text-xs text-slate-400">Kitne players ki requirement hai?</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label for="slots_needed" class="block text-xs font-black uppercase tracking-wider text-amber-400 mb-1.5">
                            🔥 Vacant Slots Needed <span class="text-rose-400">*</span>
                        </label>
                        <input type="number" id="slots_needed" name="slots_needed" value="{{ old('slots_needed', 4) }}" min="1" max="25" required
                               class="w-full px-4 py-3 rounded-2xl bg-slate-900 border border-amber-500/40 text-amber-300 font-black text-lg focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition" />
                        <p class="text-[11px] text-slate-400 mt-1">Kitne players chahiye?</p>
                    </div>

                    <div>
                        <label for="total_players" class="block text-xs font-black uppercase tracking-wider text-slate-300 mb-1.5">
                            Total Playing Squad Size <span class="text-rose-400">*</span>
                        </label>
                        <input type="number" id="total_players" name="total_players" value="{{ old('total_players', 14) }}" min="2" max="30" required
                               class="w-full px-4 py-3 rounded-2xl bg-slate-900 border border-white/10 text-white font-bold text-base focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition" />
                        <p class="text-[11px] text-slate-400 mt-1">e.g. 11 for single team or 22 for both</p>
                    </div>

                    <div>
                        <label for="cost_per_player" class="block text-xs font-black uppercase tracking-wider text-slate-300 mb-1.5">
                            Cost / Share per Player
                        </label>
                        <input type="text" id="cost_per_player" name="cost_per_player" value="{{ old('cost_per_player', 'Free') }}"
                               placeholder="e.g. Free, ₹50 (Ball), ₹120 (Turf)"
                               class="w-full px-4 py-3 rounded-2xl bg-slate-900 border border-white/10 text-white text-sm focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition" />
                        <p class="text-[11px] text-slate-400 mt-1">Leave as "Free" if no fee</p>
                    </div>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- SECTION 4: CONTACT & WHATSAPP CONNECT -->
            <!-- ========================================== -->
            <div class="p-5 sm:p-7 rounded-3xl bg-[#131b2e] border border-white/10 shadow-2xl space-y-5">
                <div class="flex items-center gap-3 border-b border-white/10 pb-3">
                    <span class="w-8 h-8 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center font-black text-sm">
                        💬
                    </span>
                    <div>
                        <h3 class="font-black text-base sm:text-lg text-white">Organizer Contact & WhatsApp Connect</h3>
                        <p class="text-xs text-slate-400">Players can connect with you instantly on WhatsApp</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="contact_whatsapp" class="block text-xs font-black uppercase tracking-wider text-emerald-400 mb-1.5">
                            WhatsApp Number (10 digits)
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-emerald-400 font-bold text-sm">
                                💬 +91
                            </span>
                            <input type="text" id="contact_whatsapp" name="contact_whatsapp" value="{{ old('contact_whatsapp', Auth::user()->phone ?? '') }}"
                                   placeholder="9876543210"
                                   class="w-full pl-16 pr-4 py-3 rounded-2xl bg-slate-900 border border-white/10 text-white placeholder-slate-500 text-sm focus:border-emerald-400 focus:ring-1 focus:ring-emerald-400 transition" />
                        </div>
                        <p class="text-[11px] text-slate-400 mt-1">Creates a 1-tap WhatsApp chat button for interested players.</p>
                    </div>

                    <div>
                        <label for="contact_phone" class="block text-xs font-black uppercase tracking-wider text-slate-300 mb-1.5">
                            Call Phone Number (Optional)
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 text-sm">
                                📞
                            </span>
                            <input type="text" id="contact_phone" name="contact_phone" value="{{ old('contact_phone', Auth::user()->phone ?? '') }}"
                                   placeholder="Phone number for direct calling"
                                   class="w-full pl-10 pr-4 py-3 rounded-2xl bg-slate-900 border border-white/10 text-white placeholder-slate-500 text-sm focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition" />
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="whatsapp_group_link" class="block text-xs font-black uppercase tracking-wider text-slate-300 mb-1.5">
                            WhatsApp Match Group Invite Link (Optional)
                        </label>
                        <input type="url" id="whatsapp_group_link" name="whatsapp_group_link" value="{{ old('whatsapp_group_link') }}"
                               placeholder="https://chat.whatsapp.com/..."
                               class="w-full px-4 py-3 rounded-2xl bg-slate-900 border border-white/10 text-white placeholder-slate-500 text-sm focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition" />
                    </div>

                    <div>
                        <label for="cricket_circle_id" class="block text-xs font-black uppercase tracking-wider text-slate-300 mb-1.5">
                            Attach to Local Cricket Circle (Optional)
                        </label>
                        <select id="cricket_circle_id" name="cricket_circle_id"
                                class="w-full px-4 py-3 rounded-2xl bg-slate-900 border border-white/10 text-white text-sm focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition">
                            <option value="">-- None (Open to all city players) --</option>
                            @foreach($circles as $circle)
                                <option value="{{ $circle->id }}" {{ old('cricket_circle_id') == $circle->id ? 'selected' : '' }}>
                                    ⭕ {{ $circle->name }} ({{ $circle->city }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label for="description" class="block text-xs font-black uppercase tracking-wider text-slate-300 mb-1.5">
                        Match Details / Instructions / Ground Rules (Optional)
                    </label>
                    <textarea id="description" name="description" rows="3"
                              placeholder="e.g. Please bring your own kit/bat if available. Match will be 14 overs per side. Post match tea will be arranged. Reach by 7:15 AM for toss."
                              class="w-full px-4 py-3 rounded-2xl bg-slate-900 border border-white/10 text-white placeholder-slate-500 text-sm focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition">{{ old('description') }}</textarea>
                </div>
            </div>

            <!-- Submit Button Bar -->
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-2">
                <a href="{{ route('meetups.index') }}"
                   class="w-full sm:w-auto px-6 py-3 rounded-2xl bg-white/5 hover:bg-white/10 text-slate-300 hover:text-white font-bold text-sm text-center transition">
                    Cancel
                </a>

                <button type="submit"
                        class="w-full sm:w-auto px-8 py-4 rounded-2xl bg-gradient-to-r from-amber-500 via-orange-500 to-amber-600 hover:from-amber-400 hover:to-orange-500 text-slate-950 font-black text-base shadow-xl shadow-amber-500/25 transition transform active:scale-95 cursor-pointer">
                    🏏 Publish Match & Open Squad
                </button>
            </div>
        </form>

    </div>
</div>
@endsection
