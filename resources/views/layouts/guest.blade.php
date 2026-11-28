@php
    $decodeSeo = static fn (string $val): string => html_entity_decode(trim($val), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $guestTitle = $decodeSeo($__env->yieldContent('title', 'Sign In | CricApp.in'));
    $guestDesc = $decodeSeo($__env->yieldContent('meta_description', 'Sign in or create your CricApp.in account to manage live cricket matches, player stats, turf ground bookings, and verified umpires.'));
    $guestRobots = $decodeSeo($__env->yieldContent('robots', 'noindex, follow'));
    $googleSiteVerification = config('services.google.site_verification');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $guestTitle }}</title>
        <meta name="description" content="{{ $guestDesc }}">
        <meta name="robots" content="{{ $guestRobots }}">
        <link rel="canonical" href="{{ url()->current() }}">
        @if($googleSiteVerification)
            <meta name="google-site-verification" content="{{ $googleSiteVerification }}">
        @endif
        <meta name="p:domain_verify" content="d27f1dab4f1097be198ea72c58f63db3"/>

        <!-- PWA & Favicon -->
        <link rel="icon" type="image/x-icon" href="/favicon.ico">
        <meta name="theme-color" content="#080d1a">

        <x-analytics-head />

        <!-- Non-blocking Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800,900&display=swap" rel="stylesheet" media="print" onload="this.media='all'" />
        <noscript><link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800,900&display=swap" rel="stylesheet" /></noscript>

        <!-- Theme Initialization -->
        <script>
            if (localStorage.theme === 'light') {
                document.documentElement.classList.remove('dark');
            } else {
                document.documentElement.classList.add('dark');
            }
            function toggleTheme() {
                if (document.documentElement.classList.contains('dark')) {
                    document.documentElement.classList.remove('dark');
                    localStorage.theme = 'light';
                } else {
                    document.documentElement.classList.add('dark');
                    localStorage.theme = 'dark';
                }
                window.dispatchEvent(new CustomEvent('theme-changed', { detail: { theme: localStorage.theme } }));
            }
        </script>

        <!-- Scripts & Styles -->
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    </head>
    <body class="font-sans antialiased bg-slate-50 text-slate-900 dark:bg-[#080d1a] dark:text-slate-100 min-h-screen selection:bg-indigo-600 selection:text-white relative overflow-x-hidden flex flex-col justify-between transition-colors duration-200">
        <!-- Global Animated Cricket Bat & Ball Loader -->
        <x-cricket-loader label="Loading pitch..." />

        <!-- Lightweight Ambient Stadium Glow -->
        <div class="fixed inset-0 pointer-events-none -z-10 overflow-hidden dark:opacity-100 opacity-20 transition-opacity"
             style="background-image: radial-gradient(circle at 12% 8%, rgba(79,70,229,0.16), transparent 36%), radial-gradient(circle at 88% 35%, rgba(37,99,235,0.12), transparent 38%), radial-gradient(circle at 45% 92%, rgba(16,185,129,0.10), transparent 36%);">
            <div class="absolute inset-0 bg-[radial-gradient(#1e293b_1px,transparent_1px)] [background-size:24px_24px] opacity-25"></div>
        </div>

        <!-- Top Header Navigation (Consistent Stadium Command Bar) -->
        <header x-data="{ isDark: document.documentElement.classList.contains('dark') }"
                @theme-changed.window="isDark = document.documentElement.classList.contains('dark')"
                class="w-full border-b border-slate-200/80 dark:border-white/10 bg-white/90 dark:bg-[#080e1c]/90 backdrop-blur-xl sticky top-0 z-30 transition-colors duration-200">
            <div class="h-[2.5px] w-full bg-gradient-to-r from-indigo-600 via-emerald-400 to-amber-500"></div>
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
                <a href="{{ route('live-scores.index') }}" class="flex items-center gap-2.5 group whitespace-nowrap shrink-0">
                    <div class="relative w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-600 via-blue-600 to-emerald-500 p-[1px] shadow-md shadow-indigo-600/25 group-hover:scale-105 transition transform">
                        <div class="w-full h-full rounded-[11px] bg-slate-950/90 flex items-center justify-center">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M8 20V6M12 20V6M16 20V6" stroke="#e2e8f0" stroke-width="1.8" stroke-linecap="round" />
                                <path d="M7.2 5.5H11.5M12.5 5.5H16.8" stroke="#f59e0b" stroke-width="1.8" stroke-linecap="round" />
                                <circle cx="17.5" cy="14.5" r="3.5" fill="#e11d48" stroke="#fff1f2" stroke-width="0.9" />
                            </svg>
                        </div>
                    </div>
                    <div class="flex flex-col leading-none">
                        <div class="flex items-baseline">
                            <span class="font-black text-lg sm:text-xl text-slate-900 dark:text-white tracking-tight">Cric</span>
                            <span class="font-black text-lg sm:text-xl bg-gradient-to-r from-amber-500 to-orange-500 bg-clip-text text-transparent tracking-tight">App</span>
                            <span class="text-[11px] font-extrabold text-indigo-600 dark:text-indigo-400 ml-0.5">.in</span>
                        </div>
                        <span class="text-[8.5px] font-extrabold uppercase tracking-[0.22em] text-emerald-600 dark:text-emerald-400 mt-0.5">
                            Cricket Arena
                        </span>
                    </div>
                </a>

                <div class="hidden sm:inline-flex items-center gap-0.5 p-1 rounded-2xl bg-slate-100/90 dark:bg-[#0f172a]/90 border border-slate-200/90 dark:border-white/[0.09] shadow-inner">
                    <a href="{{ route('live-scores.index') }}" class="px-3 py-2 rounded-xl text-[13px] font-semibold leading-none whitespace-nowrap text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-white/75 dark:hover:bg-white/[0.06] transition">
                        Live Scores
                    </a>
                    <a href="{{ route('articles.index') }}" class="px-3 py-2 rounded-xl text-[13px] font-semibold leading-none whitespace-nowrap text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-white/75 dark:hover:bg-white/[0.06] transition">
                        News &amp; Articles
                    </a>
                    <a href="{{ route('grounds.index') }}" class="px-3 py-2 rounded-xl text-[13px] font-semibold leading-none whitespace-nowrap text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-white/75 dark:hover:bg-white/[0.06] transition">
                        Grounds
                    </a>
                    <a href="{{ route('about') }}" class="px-3 py-2 rounded-xl text-[13px] font-semibold leading-none whitespace-nowrap text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-white/75 dark:hover:bg-white/[0.06] transition">
                        About
                    </a>
                    <a href="{{ route('contact') }}" class="px-3 py-2 rounded-xl text-[13px] font-semibold leading-none whitespace-nowrap text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-white/75 dark:hover:bg-white/[0.06] transition">
                        Contact
                    </a>
                </div>

                <button type="button"
                        onclick="toggleTheme()"
                        class="w-9 h-9 inline-flex items-center justify-center rounded-xl border border-slate-200 dark:border-white/10 bg-slate-100/80 dark:bg-white/[0.05] text-slate-700 dark:text-amber-300 hover:bg-slate-200/80 dark:hover:bg-white/10 transition cursor-pointer shrink-0"
                        title="Toggle Light / Dark mode"
                        aria-label="Toggle Light / Dark mode">
                    <template x-if="isDark">
                        <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </template>
                    <template x-if="!isDark">
                        <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                        </svg>
                    </template>
                </button>
            </div>
        </header>

        <!-- Main Slot -->
        <main class="flex-1 flex items-center justify-center p-4 sm:p-6 lg:p-8">
            <div class="w-full max-w-6xl">
                {{ $slot }}
            </div>
        </main>

        <!-- Minimal Footer -->
        <footer class="w-full border-t border-white/5 py-4 text-center text-xs text-slate-500">
            <div class="max-w-7xl mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-2">
                <span>© {{ date('Y') }} CricApp.in • The Grassroots Cricket Ecosystem</span>
                <div class="flex items-center gap-4 text-slate-400 text-[11px]">
                    <a href="{{ route('live-scores.index') }}" class="hover:text-white transition">Scores</a>
                    <span>•</span>
                    <a href="{{ route('grounds.index') }}" class="hover:text-white transition">Grounds</a>
                    <span>•</span>
                    <a href="{{ route('about') }}" class="hover:text-white transition">About</a>
                    <span>•</span>
                    <a href="{{ route('contact') }}" class="hover:text-white transition">Contact</a>
                </div>
            </div>
        </footer>

    </body>
</html>
