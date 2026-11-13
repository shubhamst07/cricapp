<x-guest-layout>
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">

        <!-- Left Column: Cricket Platform Hero Showcase -->
        <div class="hidden lg:flex lg:col-span-6 flex-col justify-center space-y-6 pr-4">
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 text-xs font-bold w-fit">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                <span>CRICHEROES & LIVE CRICKET PLATFORM</span>
            </div>

            <div class="space-y-3">
                <h1 class="text-3xl sm:text-4xl xl:text-5xl font-black text-white leading-tight tracking-tight">
                    Step Onto <br>
                    <span class="bg-gradient-to-r from-indigo-400 via-sky-400 to-emerald-400 bg-clip-text text-transparent">The Cricket Pitch.</span> 🏏
                </h1>
                <p class="text-sm text-slate-400 leading-relaxed max-w-md">
                    Access real-time ball-by-ball scoring, track your player career passport, manage club squads, and book verified match umpires.
                </p>
            </div>

            <!-- 3 Cricket Highlights -->
            <div class="space-y-3 pt-2">
                <div class="p-3.5 rounded-2xl bg-white/[0.03] border border-white/5 flex items-start gap-3.5">
                    <div class="w-9 h-9 rounded-xl bg-indigo-600/20 border border-indigo-500/30 flex items-center justify-center text-base shrink-0">
                        ⚡
                    </div>
                    <div>
                        <h4 class="text-xs font-black text-white">Ball-By-Ball Match Scoring</h4>
                        <p class="text-[11px] text-slate-400 mt-0.5">Automated overs rotation, fall of wickets selector & instant live scorecard updates.</p>
                    </div>
                </div>

                <div class="p-3.5 rounded-2xl bg-white/[0.03] border border-white/5 flex items-start gap-3.5">
                    <div class="w-9 h-9 rounded-xl bg-sky-600/20 border border-sky-500/30 flex items-center justify-center text-base shrink-0">
                        📊
                    </div>
                    <div>
                        <h4 class="text-xs font-black text-white">Player Career Passports</h4>
                        <p class="text-[11px] text-slate-400 mt-0.5">Track cumulative runs, strike rates, 4s, 6s, 5-wicket hauls & player of the match awards.</p>
                    </div>
                </div>

                <div class="p-3.5 rounded-2xl bg-white/[0.03] border border-white/5 flex items-start gap-3.5">
                    <div class="w-9 h-9 rounded-xl bg-emerald-600/20 border border-emerald-500/30 flex items-center justify-center text-base shrink-0">
                        ⚖️
                    </div>
                    <div>
                        <h4 class="text-xs font-black text-white">Certified Umpires & Turf Grounds</h4>
                        <p class="text-[11px] text-slate-400 mt-0.5">Direct phone and WhatsApp booking with verified state and club officials.</p>
                    </div>
                </div>
            </div>

            <div class="p-3.5 rounded-2xl bg-gradient-to-r from-indigo-900/40 to-slate-900/40 border border-indigo-500/20 flex items-center gap-3">
                <span class="text-xl">🏆</span>
                <span class="text-xs font-medium text-slate-300">
                    Trusted by over <strong>1,500+ local cricket teams</strong> and organizers across India.
                </span>
            </div>
        </div>

        <!-- Right Column: Modern Glassmorphic Login Form -->
        <div class="lg:col-span-6 w-full max-w-md mx-auto">
            <div class="bg-[#111827]/90 backdrop-blur-xl border border-white/10 rounded-3xl p-6 sm:p-8 shadow-2xl space-y-6 relative overflow-hidden">
                
                <!-- Subtle Top Gradient Glow -->
                <div class="absolute -top-20 -right-20 w-44 h-44 bg-indigo-500/20 rounded-full blur-3xl pointer-events-none"></div>

                <!-- Card Header -->
                <div class="space-y-1.5 pb-2 border-b border-white/5">
                    <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md bg-indigo-500/10 text-indigo-400 text-[10px] font-black uppercase tracking-wider">
                        <span>🏏 WELCOME BACK</span>
                    </div>
                    <h2 class="text-2xl font-black text-white">Sign In to LiveCricket</h2>
                    <p class="text-xs text-slate-400">Enter your credentials to manage your matches & team.</p>
                </div>

                <!-- Session Status / Flash Alerts -->
                @if (session('status'))
                    <div class="p-3.5 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-xs font-bold flex items-center gap-2">
                        <span>✓</span>
                        <span>{{ session('status') }}</span>
                    </div>
                @endif

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

                <form method="POST" action="{{ route('login') }}" class="space-y-4 text-xs" x-data="{ showPass: false }">
                    @csrf

                    <!-- Email Address -->
                    <div class="space-y-1.5">
                        <label for="email" class="block font-bold text-slate-300 uppercase tracking-wider text-[11px]">Email Address</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" />
                                </svg>
                            </span>
                            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                                   placeholder="name@example.com"
                                   class="w-full bg-slate-950/80 border border-white/10 rounded-xl pl-10 pr-4 py-3 text-sm text-white placeholder-slate-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
                        </div>
                    </div>

                    <!-- Password -->
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <label for="password" class="block font-bold text-slate-300 uppercase tracking-wider text-[11px]">Password</label>
                            @if (Route::has('password.request'))
                                <a href="{{ route('password.request') }}" class="text-[11px] font-bold text-indigo-400 hover:text-indigo-300 transition">
                                    Forgot password?
                                </a>
                            @endif
                        </div>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </span>
                            <input id="password" :type="showPass ? 'text' : 'password'" name="password" required autocomplete="current-password"
                                   placeholder="••••••••"
                                   class="w-full bg-slate-950/80 border border-white/10 rounded-xl pl-10 pr-11 py-3 text-sm text-white placeholder-slate-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
                            <button type="button" @click="showPass = !showPass" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-white transition focus:outline-none" tabindex="-1">
                                <span x-show="!showPass" title="Show password">👁️</span>
                                <span x-show="showPass" title="Hide password">🙈</span>
                            </button>
                        </div>
                    </div>

                    <!-- Remember Me -->
                    <div class="flex items-center justify-between pt-1">
                        <label for="remember_me" class="inline-flex items-center gap-2 cursor-pointer select-none">
                            <input id="remember_me" type="checkbox" name="remember" class="w-4 h-4 rounded bg-slate-950 border-white/20 text-indigo-600 focus:ring-indigo-500 focus:ring-offset-slate-900">
                            <span class="text-xs text-slate-300 font-medium">Keep me signed in</span>
                        </label>
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-2">
                        <button type="submit"
                                class="w-full py-3.5 px-4 rounded-xl bg-gradient-to-r from-indigo-600 via-blue-600 to-indigo-600 hover:from-indigo-500 hover:via-blue-500 hover:to-indigo-500 text-white font-black text-sm shadow-xl shadow-indigo-600/30 transition transform active:scale-[0.98] flex items-center justify-center gap-2">
                            <span>Sign In to Pavilion</span>
                            <span>🏏</span>
                        </button>
                    </div>
                </form>

                <!-- Switch to Register Link -->
                <div class="pt-4 border-t border-white/5 text-center space-y-2.5">
                    <p class="text-xs text-slate-400">
                        Don't have a cricket account?
                        <a href="{{ route('register') }}" class="font-extrabold text-indigo-400 hover:text-indigo-300 ml-1 transition">
                            Create Free Account ➔
                        </a>
                    </p>
                    <div class="p-2.5 rounded-xl bg-indigo-500/5 border border-indigo-500/10 text-center">
                        <p class="text-[11px] text-slate-400">
                            🛡️ <strong>Admin Access:</strong> Sign in with admin email & PIN (<code class="text-indigo-300 font-mono">cricadmin2026</code>)
                        </p>
                    </div>
                </div>

            </div>
        </div>

    </div>
</x-guest-layout>
