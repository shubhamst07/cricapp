<x-guest-layout>
    <div class="max-w-md mx-auto">
        <div class="bg-[#111827]/90 backdrop-blur-xl border border-white/10 rounded-3xl p-6 sm:p-8 shadow-2xl space-y-6 relative overflow-hidden">
            
            <!-- Subtle Top Gradient Glow -->
            <div class="absolute -top-20 -right-20 w-44 h-44 bg-indigo-500/20 rounded-full blur-3xl pointer-events-none"></div>

            <div class="space-y-1.5 pb-2 border-b border-white/5">
                <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md bg-indigo-500/10 text-indigo-400 text-[10px] font-black uppercase tracking-wider">
                    <span>🔑 ACCOUNT RECOVERY</span>
                </div>
                <h2 class="text-2xl font-black text-white">Forgot Password?</h2>
                <p class="text-xs text-slate-400">
                    No problem. Enter your registered email address and we'll send you a password reset link to get back onto the pitch.
                </p>
            </div>

            <!-- Session Status -->
            @if (session('status'))
                <div class="p-3.5 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-xs font-bold space-y-2.5">
                    <div class="flex items-center gap-2">
                        <span>✓</span>
                        <span>{{ session('status') }}</span>
                    </div>
                    @if (session('direct_reset_url'))
                        <div class="pt-1">
                            <a href="{{ session('direct_reset_url') }}"
                               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-black text-xs shadow-lg shadow-emerald-900/40 transition transform active:scale-95">
                                <span>🔑 Click Here to Reset Password Now</span>
                                <span>→</span>
                            </a>
                        </div>
                    @endif
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

            <form method="POST" action="{{ route('password.email') }}" class="space-y-4 text-xs">
                @csrf

                <!-- Email Address -->
                <div class="space-y-1.5">
                    <label for="email" class="block font-bold text-slate-300 uppercase tracking-wider text-[11px]">Registered Email</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" />
                            </svg>
                        </span>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                               placeholder="name@example.com"
                               class="w-full bg-slate-950/80 border border-white/10 rounded-xl pl-10 pr-4 py-3 text-sm text-white placeholder-slate-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
                    </div>
                </div>

                <div class="pt-2">
                    <button type="submit"
                            class="w-full py-3.5 px-4 rounded-xl bg-gradient-to-r from-indigo-600 via-blue-600 to-indigo-600 hover:from-indigo-500 hover:via-blue-500 hover:to-indigo-500 text-white font-black text-sm shadow-xl shadow-indigo-600/30 transition transform active:scale-[0.98] flex items-center justify-center gap-2">
                        <span>Email Password Reset Link</span>
                        <span>✉️</span>
                    </button>
                </div>
            </form>

            <div class="pt-4 border-t border-white/5 text-center">
                <a href="{{ route('login') }}" class="text-xs font-bold text-slate-400 hover:text-white transition flex items-center justify-center gap-1">
                    <span>←</span>
                    <span>Back to Sign In</span>
                </a>
            </div>

        </div>
    </div>
</x-guest-layout>
