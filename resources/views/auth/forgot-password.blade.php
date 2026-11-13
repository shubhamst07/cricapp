@php
    $isStep2 = session('step') == 2 || old('otp');
    $otpEmail = session('otp_email') ?? old('email');
@endphp

<x-guest-layout>
    <div class="max-w-md mx-auto" x-data="{ showPass: false, showConfirm: false }">
        <div class="bg-[#111827]/90 backdrop-blur-xl border border-white/10 rounded-3xl p-6 sm:p-8 shadow-2xl space-y-6 relative overflow-hidden">
            
            <!-- Subtle Top Glow -->
            <div class="absolute -top-20 -right-20 w-44 h-44 bg-indigo-500/20 rounded-full blur-3xl pointer-events-none"></div>

            <!-- Header -->
            <div class="space-y-1.5 pb-2 border-b border-white/5">
                <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md bg-indigo-500/10 text-indigo-400 text-[10px] font-black uppercase tracking-wider">
                    <span>📱 OTP ACCOUNT RECOVERY</span>
                </div>
                <h2 class="text-2xl font-black text-white">
                    {{ $isStep2 ? 'Verify 6-Digit OTP' : 'Forgot Password?' }}
                </h2>
                <p class="text-xs text-slate-400">
                    {{ $isStep2 ? 'Enter the 6-digit OTP code sent to your registered email and choose a new password.' : 'Enter your registered email address to receive an instant 6-digit OTP verification code.' }}
                </p>
            </div>

            <!-- Step Progress Indicator -->
            <div class="grid grid-cols-2 gap-2 text-center text-[10px] font-bold">
                <div class="py-1.5 rounded-lg border {{ !$isStep2 ? 'bg-indigo-600/20 border-indigo-500/40 text-indigo-300' : 'bg-white/5 border-white/5 text-slate-500' }}">
                    1. Send OTP
                </div>
                <div class="py-1.5 rounded-lg border {{ $isStep2 ? 'bg-indigo-600/20 border-indigo-500/40 text-indigo-300' : 'bg-white/5 border-white/5 text-slate-500' }}">
                    2. Verify & Reset
                </div>
            </div>

            <!-- Status / Success Message -->
            @if (session('status'))
                <div class="p-3.5 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-xs font-bold flex items-start gap-2">
                    <span class="text-base leading-none">✓</span>
                    <span class="flex-1">{{ session('status') }}</span>
                </div>
            @endif



            <!-- Validation Errors -->
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

            @if ($isStep2)
                <!-- ================= STEP 2: VERIFY OTP & RESET PASSWORD ================= -->
                <form method="POST" action="{{ route('password.verify-otp') }}" class="space-y-4 text-xs">
                    @csrf
                    <input type="hidden" name="email" value="{{ $otpEmail }}">

                    <!-- Active Email Badge -->
                    <div class="p-2.5 rounded-xl bg-white/[0.03] border border-white/5 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-slate-400 text-[11px]">Email:</span>
                            <span class="text-white font-bold font-mono text-xs">{{ $otpEmail }}</span>
                        </div>
                        <a href="{{ route('password.request') }}" class="text-[11px] text-indigo-400 hover:text-indigo-300 underline font-semibold">
                            Change
                        </a>
                    </div>

                    <!-- 6-Digit OTP Code -->
                    <div class="space-y-1.5">
                        <label for="otp" class="block font-bold text-slate-300 uppercase tracking-wider text-[11px]">
                            Enter 6-Digit OTP Code
                        </label>
                        <div class="relative">
                            <input id="otp" type="text" name="otp" maxlength="6" pattern="[0-9]{6}" required autofocus
                                   value="{{ old('otp', '') }}"
                                   placeholder="••••••"
                                   style="letter-spacing: 0.35em;"
                                   class="w-full bg-slate-950/80 border border-white/10 rounded-xl px-4 py-3 text-center text-lg font-black font-mono text-amber-300 placeholder-slate-600 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
                        </div>
                        <p class="text-[10px] text-slate-500 text-center">OTP is valid for 10 minutes</p>
                    </div>

                    <!-- New Password -->
                    <div class="space-y-1.5">
                        <label for="password" class="block font-bold text-slate-300 uppercase tracking-wider text-[11px]">
                            New Password
                        </label>
                        <div class="relative">
                            <input id="password" :type="showPass ? 'text' : 'password'" name="password" required
                                   placeholder="At least 6 characters"
                                   class="w-full bg-slate-950/80 border border-white/10 rounded-xl pl-4 pr-11 py-3 text-sm text-white placeholder-slate-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
                            <button type="button" @click="showPass = !showPass" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-white transition focus:outline-none" tabindex="-1">
                                <span x-show="!showPass" title="Show password">👁️</span>
                                <span x-show="showPass" title="Hide password">🙈</span>
                            </button>
                        </div>
                    </div>

                    <!-- Confirm Password -->
                    <div class="space-y-1.5">
                        <label for="password_confirmation" class="block font-bold text-slate-300 uppercase tracking-wider text-[11px]">
                            Confirm New Password
                        </label>
                        <div class="relative">
                            <input id="password_confirmation" :type="showConfirm ? 'text' : 'password'" name="password_confirmation" required
                                   placeholder="Re-enter new password"
                                   class="w-full bg-slate-950/80 border border-white/10 rounded-xl pl-4 pr-11 py-3 text-sm text-white placeholder-slate-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
                            <button type="button" @click="showConfirm = !showConfirm" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-white transition focus:outline-none" tabindex="-1">
                                <span x-show="!showConfirm" title="Show password">👁️</span>
                                <span x-show="showConfirm" title="Hide password">🙈</span>
                            </button>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-2">
                        <button type="submit"
                                class="w-full py-3.5 px-4 rounded-xl bg-gradient-to-r from-emerald-600 via-teal-600 to-emerald-600 hover:from-emerald-500 hover:via-teal-500 hover:to-emerald-500 text-white font-black text-sm shadow-xl shadow-emerald-600/30 transition transform active:scale-[0.98] flex items-center justify-center gap-2">
                            <span>Verify OTP & Set New Password</span>
                            <span>➔</span>
                        </button>
                    </div>
                </form>

                <!-- Resend OTP Form -->
                <div class="pt-3 border-t border-white/5 flex items-center justify-between text-xs">
                    <a href="{{ route('password.request') }}" class="text-slate-400 hover:text-white transition font-medium">
                        ← Change Email
                    </a>
                    <form method="POST" action="{{ route('password.email') }}" class="inline">
                        @csrf
                        <input type="hidden" name="email" value="{{ $otpEmail }}">
                        <button type="submit" class="text-indigo-400 hover:text-indigo-300 font-bold transition">
                            Resend 6-Digit OTP ↺
                        </button>
                    </form>
                </div>

            @else
                <!-- ================= STEP 1: ENTER EMAIL TO SEND OTP ================= -->
                <form method="POST" action="{{ route('password.email') }}" class="space-y-4 text-xs">
                    @csrf

                    <!-- Email Address -->
                    <div class="space-y-1.5">
                        <label for="email" class="block font-bold text-slate-300 uppercase tracking-wider text-[11px]">
                            Registered Email Address
                        </label>
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
                            <span>Send 6-Digit OTP</span>
                            <span>📱</span>
                        </button>
                    </div>
                </form>

                <!-- Admin Quick Access Hint -->
                <div class="p-3 rounded-2xl bg-indigo-500/5 border border-indigo-500/10 text-center text-[11px] text-slate-400">
                    🛡️ <strong>Admin Notice:</strong> Admin accounts can also sign in directly using Admin PIN (<code class="text-indigo-300 font-mono">cricadmin2026</code>).
                </div>
            @endif

            <div class="pt-4 border-t border-white/5 text-center">
                <a href="{{ route('login') }}" class="text-xs font-bold text-slate-400 hover:text-white transition flex items-center justify-center gap-1">
                    <span>←</span>
                    <span>Back to Sign In</span>
                </a>
            </div>

        </div>
    </div>
</x-guest-layout>
