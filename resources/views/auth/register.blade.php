<x-guest-layout>
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">

        <!-- Left Column: Cricket Platform Hero Showcase -->
        <div class="hidden lg:flex lg:col-span-6 flex-col justify-center space-y-6 pr-4">
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-bold w-fit">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                <span>FREE CRICKETER PASSPORT & SCORER</span>
            </div>

            <div class="space-y-3">
                <h1 class="text-3xl sm:text-4xl xl:text-5xl font-black text-white leading-tight tracking-tight">
                    Join The <br>
                    <span class="bg-gradient-to-r from-emerald-400 via-teal-400 to-indigo-400 bg-clip-text text-transparent">Cricket Movement.</span> ⚡
                </h1>
                <p class="text-sm text-slate-400 leading-relaxed max-w-md">
                    Create your official player profile, score local club & tournament matches with real-time ball tracking, and connect with certified umpires.
                </p>
            </div>

            <!-- 3 Cricket Highlights -->
            <div class="space-y-3 pt-2">
                <div class="p-3.5 rounded-2xl bg-white/[0.03] border border-white/5 flex items-start gap-3.5">
                    <div class="w-9 h-9 rounded-xl bg-emerald-600/20 border border-emerald-500/30 flex items-center justify-center text-base shrink-0">
                        🏏
                    </div>
                    <div>
                        <h4 class="text-xs font-black text-white">Full Match Control & Scoring</h4>
                        <p class="text-[11px] text-slate-400 mt-0.5">Custom overs, toss winner selector, strike rotation & CricHeroes level scoring accuracy.</p>
                    </div>
                </div>

                <div class="p-3.5 rounded-2xl bg-white/[0.03] border border-white/5 flex items-start gap-3.5">
                    <div class="w-9 h-9 rounded-xl bg-indigo-600/20 border border-indigo-500/30 flex items-center justify-center text-base shrink-0">
                        📱
                    </div>
                    <div>
                        <h4 class="text-xs font-black text-white">Auto-Synced Player Career Stats</h4>
                        <p class="text-[11px] text-slate-400 mt-0.5">Whenever your mobile number is selected in any match, your runs, strike rate & wickets sync instantly.</p>
                    </div>
                </div>

                <div class="p-3.5 rounded-2xl bg-white/[0.03] border border-white/5 flex items-start gap-3.5">
                    <div class="w-9 h-9 rounded-xl bg-sky-600/20 border border-sky-500/30 flex items-center justify-center text-base shrink-0">
                        🏟️
                    </div>
                    <div>
                        <h4 class="text-xs font-black text-white">Book Grounds & Certified Officials</h4>
                        <p class="text-[11px] text-slate-400 mt-0.5">Explore floodlit turf grounds and hire match officials with verified phone & WhatsApp numbers.</p>
                    </div>
                </div>
            </div>

            <div class="p-3.5 rounded-2xl bg-gradient-to-r from-emerald-900/30 to-slate-900/40 border border-emerald-500/20 flex items-center gap-3">
                <span class="text-xl">⚡</span>
                <span class="text-xs font-medium text-slate-300">
                    <strong>100% Free Forever</strong> for players, captains, and grassroots cricket organizers.
                </span>
            </div>
        </div>

        <!-- Right Column: Modern Glassmorphic Registration Form -->
        <div class="lg:col-span-6 w-full max-w-md mx-auto">
            <div class="bg-[#111827]/90 backdrop-blur-xl border border-white/10 rounded-3xl p-6 sm:p-8 shadow-2xl space-y-5 relative overflow-hidden">
                
                <!-- Subtle Top Gradient Glow -->
                <div class="absolute -top-20 -right-20 w-44 h-44 bg-emerald-500/20 rounded-full blur-3xl pointer-events-none"></div>

                <!-- Card Header -->
                <div class="space-y-1.5 pb-2 border-b border-white/5">
                    <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md bg-emerald-500/10 text-emerald-400 text-[10px] font-black uppercase tracking-wider">
                        <span>⚡ NEW CRICKETER REGISTRATION</span>
                    </div>
                    <h2 class="text-2xl font-black text-white">Create Your Account</h2>
                    <p class="text-xs text-slate-400">Get started in 30 seconds. No credit card required.</p>
                </div>

                @if ($errors->any())
                    <div class="p-3.5 rounded-2xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-xs font-bold space-y-1">
                        @foreach ($errors->all() as $error)
                            <div class="flex items-center gap-1.5">
                                <span>⚠️</span>
                                <span>{{ $error }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('register') }}" class="space-y-4 text-xs" x-data="{ showPass: false, showConfirm: false }">
                    @csrf

                    <!-- Full Name -->
                    <div class="space-y-1.5">
                        <label for="name" class="block font-bold text-slate-300 uppercase tracking-wider text-[11px]">Player / Full Name *</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                            </span>
                            <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name"
                                   placeholder="e.g. Virat Sharma"
                                   class="w-full bg-slate-950/80 border border-white/10 rounded-xl pl-10 pr-4 py-2.5 text-sm text-white placeholder-slate-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
                        </div>
                    </div>

                    <!-- Email Address -->
                    <div class="space-y-1.5">
                        <label for="email" class="block font-bold text-slate-300 uppercase tracking-wider text-[11px]">Email Address *</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" />
                                </svg>
                            </span>
                            <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username"
                                   placeholder="player@example.com"
                                   class="w-full bg-slate-950/80 border border-white/10 rounded-xl pl-10 pr-4 py-2.5 text-sm text-white placeholder-slate-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
                        </div>
                    </div>

                    <!-- Phone Number (Optional but recommended) -->
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <label for="phone" class="block font-bold text-slate-300 uppercase tracking-wider text-[11px]">Mobile Number (Optional)</label>
                            <span class="text-[10px] text-emerald-400 font-bold">⚡ Syncs Match Stats</span>
                        </div>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                </svg>
                            </span>
                            <input id="phone" type="text" name="phone" value="{{ old('phone') }}" autocomplete="tel"
                                   placeholder="e.g. 9876543210"
                                   class="w-full bg-slate-950/80 border border-white/10 rounded-xl pl-10 pr-4 py-2.5 text-sm text-white placeholder-slate-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
                        </div>
                    </div>

                    <!-- Password Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <label for="password" class="block font-bold text-slate-300 uppercase tracking-wider text-[11px]">Password *</label>
                            <div class="relative">
                                <input id="password" :type="showPass ? 'text' : 'password'" name="password" required autocomplete="new-password"
                                       placeholder="••••••••"
                                       class="w-full bg-slate-950/80 border border-white/10 rounded-xl pl-3.5 pr-9 py-2.5 text-sm text-white placeholder-slate-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
                                <button type="button" @click="showPass = !showPass" class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-slate-400 hover:text-white transition" tabindex="-1">
                                    <span x-show="!showPass" class="text-xs">👁️</span>
                                    <span x-show="showPass" class="text-xs">🙈</span>
                                </button>
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <label for="password_confirmation" class="block font-bold text-slate-300 uppercase tracking-wider text-[11px]">Confirm *</label>
                            <div class="relative">
                                <input id="password_confirmation" :type="showConfirm ? 'text' : 'password'" name="password_confirmation" required autocomplete="new-password"
                                       placeholder="••••••••"
                                       class="w-full bg-slate-950/80 border border-white/10 rounded-xl pl-3.5 pr-9 py-2.5 text-sm text-white placeholder-slate-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
                                <button type="button" @click="showConfirm = !showConfirm" class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-slate-400 hover:text-white transition" tabindex="-1">
                                    <span x-show="!showConfirm" class="text-xs">👁️</span>
                                    <span x-show="showConfirm" class="text-xs">🙈</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-2">
                        <button type="submit"
                                class="w-full py-3.5 px-4 rounded-xl bg-gradient-to-r from-emerald-600 via-teal-600 to-indigo-600 hover:from-emerald-500 hover:via-teal-500 hover:to-indigo-500 text-white font-black text-sm shadow-xl shadow-emerald-600/30 transition transform active:scale-[0.98] flex items-center justify-center gap-2">
                            <span>Create Cricket Account</span>
                            <span>⚡</span>
                        </button>
                    </div>
                </form>

                <!-- Switch to Login Link -->
                <div class="pt-4 border-t border-white/5 text-center">
                    <p class="text-xs text-slate-400">
                        Already have an account?
                        <a href="{{ route('login') }}" class="font-extrabold text-indigo-400 hover:text-indigo-300 ml-1 transition">
                            Sign In Here ➔
                        </a>
                    </p>
                </div>

            </div>
        </div>

    </div>
</x-guest-layout>
