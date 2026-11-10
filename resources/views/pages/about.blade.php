@extends('layouts.app')

@section('header')
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/20 mb-1">
                🏏 Grassroots to Global Cricket
            </div>
            <h2 class="font-black text-2xl sm:text-3xl text-slate-900 dark:text-white leading-tight">About LiveCricket</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Empowering every cricket player, team, and tournament with digital live scoring</p>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('contact') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-indigo-600 to-blue-600 text-white font-bold text-xs shadow-md shadow-indigo-600/30 hover:from-indigo-500 transition">
                <span>📬 Contact Founder</span>
            </a>
            <a href="https://wa.me/919876543210?text=Hi%20Shubham,%20I%20visited%20LiveCricket" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-md shadow-emerald-600/30 transition">
                <span>💬 WhatsApp</span>
            </a>
        </div>
    </div>
@endsection

@section('content')
<div class="py-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12">
    <!-- Hero Platform Intro -->
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-indigo-900/40 via-slate-900 to-[#0b1120] border border-white/10 p-8 sm:p-12 shadow-2xl text-white">
        <div class="absolute -top-24 -right-24 w-80 h-80 bg-indigo-600/20 rounded-full blur-3xl pointer-events-none"></div>
        <div class="relative z-10 max-w-3xl space-y-4">
            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-extrabold uppercase tracking-wider bg-rose-500/20 text-rose-300 border border-rose-500/30">
                <span class="w-2 h-2 rounded-full bg-rose-400 animate-ping"></span>
                The LiveCricket Mission
            </span>
            <h1 class="text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight leading-tight">
                Bringing International-Grade Live Scoring to Grassroots Cricket.
            </h1>
            <p class="text-slate-300 text-sm sm:text-base leading-relaxed">
                LiveCricket was born from a simple belief: every local match deserves an international feel. Whether it's a gully face-off, a corporate trophy, a weekend turf championship, or a Ranji tournament, our platform provides real-time ball-by-ball updates, live scoreboards, certified umpire booking, and premier venue reservations.
            </p>
        </div>
    </div>

    <!-- Founder Showcase Card -->
    <div class="bg-white dark:bg-[#111827]/90 border border-slate-200 dark:border-white/10 rounded-3xl p-6 sm:p-10 shadow-xl relative overflow-hidden transition-colors duration-200">
        <div class="flex flex-col lg:flex-row items-center lg:items-start gap-8">
            <!-- Profile Avatar & Badge -->
            <div class="shrink-0 text-center lg:text-left">
                <div class="relative inline-block">
                    <div class="w-32 h-32 sm:w-36 sm:h-36 rounded-3xl bg-gradient-to-tr from-indigo-600 via-blue-600 to-teal-400 p-1 shadow-2xl">
                        <div class="w-full h-full rounded-[22px] bg-slate-900 flex items-center justify-center text-4xl sm:text-5xl font-black text-white shadow-inner uppercase">
                            ST
                        </div>
                    </div>
                    <span class="absolute -bottom-2 -right-2 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-500 text-white shadow-lg border-2 border-slate-900">
                        Founder ⚡
                    </span>
                </div>
            </div>

            <!-- Founder Details -->
            <div class="flex-1 space-y-4 text-center lg:text-left">
                <div>
                    <span class="text-xs font-extrabold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">Founder & Lead Developer</span>
                    <h2 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white mt-1">Shubham Thakur</h2>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                        📍 Shimla, Himachal Pradesh & Mumbai, India
                    </p>
                </div>

                <p class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed max-w-2xl">
                    "Cricket is in our DNA. We designed LiveCricket to give grassroots players, umpires, and ground owners a complete home where every boundary, wicket, and maiden over is recorded in real-time, giving local athletes the visibility they truly deserve."
                </p>

                <!-- Founder Contact Info Buttons -->
                <div class="pt-2 flex flex-wrap items-center justify-center lg:justify-start gap-3">
                    <a href="tel:+919876543210" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-white/10 dark:hover:bg-white/15 text-slate-900 dark:text-white font-bold text-xs transition border border-slate-200 dark:border-white/10">
                        <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                        </svg>
                        <span>+91 98765 43210</span>
                    </a>

                    <a href="https://wa.me/919876543210?text=Hi%20Shubham,%20I%20have%20an%20enquiry%20regarding%20LiveCricket" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs transition shadow-md shadow-emerald-600/25">
                        <span>💬 Direct WhatsApp</span>
                    </a>

                    <a href="mailto:shubhamthakurst07@gmail.com" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-white/10 dark:hover:bg-white/15 text-slate-900 dark:text-white font-bold text-xs transition border border-slate-200 dark:border-white/10">
                        <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        <span>shubhamthakurst07@gmail.com</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Core Platform Pillars -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white dark:bg-[#111827]/80 border border-slate-200 dark:border-white/10 rounded-3xl p-6 shadow-sm hover:shadow-md transition">
            <div class="w-12 h-12 rounded-2xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-2xl mb-4">
                🏏
            </div>
            <h3 class="font-black text-lg text-slate-900 dark:text-white mb-2">Live Scores & Broadcast</h3>
            <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                Get real-time Cricbuzz integration for international and domestic matches alongside our CricHeroes live scoring engine for local club games.
            </p>
        </div>

        <div class="bg-white dark:bg-[#111827]/80 border border-slate-200 dark:border-white/10 rounded-3xl p-6 shadow-sm hover:shadow-md transition">
            <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-2xl mb-4">
                🏟️
            </div>
            <h3 class="font-black text-lg text-slate-900 dark:text-white mb-2">Turf & Ground Booking</h3>
            <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                Browse verified cricket grounds, turf pitches, and floodlit arenas. Compare slot pricing, amenities, and reserve instantly online.
            </p>
        </div>

        <div class="bg-white dark:bg-[#111827]/80 border border-slate-200 dark:border-white/10 rounded-3xl p-6 shadow-sm hover:shadow-md transition">
            <div class="w-12 h-12 rounded-2xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center text-2xl mb-4">
                ⚖️
            </div>
            <h3 class="font-black text-lg text-slate-900 dark:text-white mb-2">Certified Umpires Hub</h3>
            <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                Empowering cricket officials. Tournament organizers can book state and district certified umpires with direct phone and profile access.
            </p>
        </div>
    </div>
</div>
@endsection
