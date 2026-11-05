<x-guest-layout>
    <div class="max-w-md mx-auto">
        <div class="bg-[#111827]/90 backdrop-blur-xl border border-white/10 rounded-3xl p-6 sm:p-8 shadow-2xl space-y-6 relative overflow-hidden">
            
            <!-- Subtle Top Gradient Glow -->
            <div class="absolute -top-20 -right-20 w-44 h-44 bg-indigo-500/20 rounded-full blur-3xl pointer-events-none"></div>

            <div class="space-y-1.5 pb-2 border-b border-white/5">
                <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md bg-indigo-500/10 text-indigo-400 text-[10px] font-black uppercase tracking-wider">
                    <span>🔒 SECURITY</span>
                </div>
                <h2 class="text-2xl font-black text-white">Reset Password</h2>
                <p class="text-xs text-slate-400">Choose a strong new password for your cricket account.</p>
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

            <form method="POST" action="{{ route('password.store') }}" class="space-y-4 text-xs">
                @csrf

                <!-- Password Reset Token -->
                <input type="hidden" name="token" value="{{ $request->route('token') }}">

                <!-- Email Address -->
                <div class="space-y-1.5">
                    <label for="email" class="block font-bold text-slate-300 uppercase tracking-wider text-[11px]">Email Address</label>
                    <input id="email" type="email" name="email" value="{{ old('email', $request->email) }}" required autofocus autocomplete="username"
                           class="w-full bg-slate-950/80 border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
                </div>

                <!-- Password -->
                <div class="space-y-1.5">
                    <label for="password" class="block font-bold text-slate-300 uppercase tracking-wider text-[11px]">New Password</label>
                    <input id="password" type="password" name="password" required autocomplete="new-password"
                           placeholder="••••••••"
                           class="w-full bg-slate-950/80 border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
                </div>

                <!-- Confirm Password -->
                <div class="space-y-1.5">
                    <label for="password_confirmation" class="block font-bold text-slate-300 uppercase tracking-wider text-[11px]">Confirm New Password</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"
                           placeholder="••••••••"
                           class="w-full bg-slate-950/80 border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
                </div>

                <div class="pt-2">
                    <button type="submit"
                            class="w-full py-3.5 px-4 rounded-xl bg-gradient-to-r from-indigo-600 via-blue-600 to-indigo-600 hover:from-indigo-500 hover:via-blue-500 hover:to-indigo-500 text-white font-black text-sm shadow-xl shadow-indigo-600/30 transition transform active:scale-[0.98]">
                        Reset Password & Sign In ⚡
                    </button>
                </div>
            </form>

        </div>
    </div>
</x-guest-layout>
