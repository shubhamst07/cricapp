<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'LiveCricket') }}</title>

        <!-- PWA & Favicon -->
        <link rel="icon" type="image/x-icon" href="/favicon.ico">
        <meta name="theme-color" content="#080d1a">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800,900&display=swap" rel="stylesheet" />

        <!-- Scripts & Styles -->
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    </head>
    <body class="font-sans antialiased bg-[#080d1a] text-slate-100 min-h-screen selection:bg-indigo-600 selection:text-white relative overflow-x-hidden flex flex-col justify-between">

        <!-- Ambient Stadium Lights Effect -->
        <div class="fixed inset-0 pointer-events-none -z-10 overflow-hidden">
            <div class="absolute -top-32 -left-32 w-96 h-96 bg-indigo-600/20 rounded-full blur-[130px]"></div>
            <div class="absolute top-1/3 -right-32 w-96 h-96 bg-blue-600/15 rounded-full blur-[140px]"></div>
            <div class="absolute -bottom-32 left-1/3 w-96 h-96 bg-emerald-600/10 rounded-full blur-[130px]"></div>
            <div class="absolute inset-0 bg-[radial-gradient(#1e293b_1px,transparent_1px)] [background-size:24px_24px] opacity-25"></div>
        </div>

        <!-- Top Header Navigation -->
        <header class="w-full border-b border-white/5 bg-[#0b1120]/80 backdrop-blur-md sticky top-0 z-30">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                <a href="{{ route('live-scores.index') }}" class="flex items-center gap-2.5 group">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-indigo-600 to-blue-500 flex items-center justify-center text-white shadow-lg shadow-indigo-600/30 group-hover:scale-105 transition transform">
                        <svg class="w-5 h-5 fill-none stroke-current" stroke-width="2" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10" />
                            <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" />
                            <path d="M2 12h20" />
                        </svg>
                    </div>
                    <div class="flex items-baseline gap-1">
                        <span class="font-black text-xl text-white tracking-tight">Live<span class="text-indigo-400">Cricket</span></span>
                        <span class="w-2 h-2 rounded-full bg-rose-500 animate-pulse"></span>
                    </div>
                </a>

                <div class="flex items-center gap-3">
                    <a href="{{ route('live-scores.index') }}" class="text-xs font-bold text-slate-300 hover:text-white px-3 py-1.5 rounded-xl hover:bg-white/5 transition flex items-center gap-1.5">
                        <span>🏏 Live Scores</span>
                    </a>
                    <a href="{{ route('grounds.index') }}" class="text-xs font-bold text-slate-400 hover:text-white px-3 py-1.5 rounded-xl hover:bg-white/5 transition hidden sm:flex items-center gap-1.5">
                        <span>🏟️ Grounds</span>
                    </a>
                    <a href="{{ route('umpires.index') }}" class="text-xs font-bold text-slate-400 hover:text-white px-3 py-1.5 rounded-xl hover:bg-white/5 transition hidden sm:flex items-center gap-1.5">
                        <span>⚖️ Umpires</span>
                    </a>
                </div>
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
                <span>© {{ date('Y') }} LiveCricket • The Grassroots Cricket Ecosystem</span>
                <div class="flex items-center gap-4 text-slate-400 text-[11px]">
                    <a href="{{ route('live-scores.index') }}" class="hover:text-white transition">Scores</a>
                    <span>•</span>
                    <a href="{{ route('grounds.index') }}" class="hover:text-white transition">Grounds</a>
                    <span>•</span>
                    <a href="{{ route('umpires.index') }}" class="hover:text-white transition">Umpires</a>
                </div>
            </div>
        </footer>

    </body>
</html>
