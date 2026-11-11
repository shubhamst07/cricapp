<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'LiveCricket') }}</title>

        <!-- PWA & Mobile App Meta Tags -->
        <link rel="manifest" href="/manifest.json">
        <meta name="theme-color" content="#0f172a">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="LiveCricket">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <link rel="icon" type="image/x-icon" href="/favicon.ico">
        <link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192x192.png">
        <link rel="icon" type="image/png" sizes="512x512" href="/icons/icon-512x512.png">

        <!-- Google AdSense Ready Tag -->
        @if(env('ADSENSE_CLIENT_ID'))
            <meta name="adsense-client" content="{{ env('ADSENSE_CLIENT_ID') }}">
            <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client={{ env('ADSENSE_CLIENT_ID') }}" crossorigin="anonymous"></script>
        @endif

        <!-- Theme Initialization to prevent FOUC -->
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

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800,900&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    </head>
    <body class="font-sans antialiased bg-slate-50 text-slate-900 dark:bg-[#080d1a] dark:text-slate-100 min-h-screen selection:bg-indigo-600 selection:text-white relative overflow-x-hidden flex flex-col justify-between transition-colors duration-200">
        <!-- Ambient Stadium Lights Effect -->
        <div class="fixed inset-0 pointer-events-none -z-10 overflow-hidden dark:opacity-100 opacity-20 transition-opacity">
            <div class="absolute -top-32 -left-32 w-96 h-96 bg-indigo-600/15 rounded-full blur-[130px]"></div>
            <div class="absolute top-1/3 -right-32 w-96 h-96 bg-blue-600/10 rounded-full blur-[140px]"></div>
            <div class="absolute -bottom-32 left-1/3 w-96 h-96 bg-emerald-600/10 rounded-full blur-[130px]"></div>
            <div class="absolute inset-0 bg-[radial-gradient(#1e293b_1px,transparent_1px)] [background-size:24px_24px] opacity-25"></div>
        </div>

        <div class="min-h-screen bg-slate-50 text-slate-900 dark:bg-[#080d1a] dark:text-slate-100 flex flex-col justify-between transition-colors duration-200">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @hasSection('header')
                <header class="bg-white/90 dark:bg-[#0b1120]/90 border-b border-slate-200 dark:border-white/10 text-slate-900 dark:text-white backdrop-blur-md shadow-xs">
                    <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8">
                        @yield('header')
                    </div>
                </header>
            @elseif (isset($header))
                <header class="bg-white/90 dark:bg-[#0b1120]/90 border-b border-slate-200 dark:border-white/10 text-slate-900 dark:text-white backdrop-blur-md shadow-xs">
                    <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endif

            <!-- Page Content -->
            <main class="flex-1">
                @yield('content')
                {{ $slot ?? '' }}
            </main>

            <!-- Modern Global Footer -->
            <footer class="bg-white/80 dark:bg-[#0b1120]/95 border-t border-slate-200 dark:border-white/10 mt-16 text-slate-600 dark:text-slate-400 backdrop-blur-md transition-colors duration-200">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                        <!-- Brand & Founder -->
                        <div class="space-y-3 md:col-span-1">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-xl bg-gradient-to-tr from-indigo-600 to-blue-500 flex items-center justify-center text-white shadow-md">
                                    <svg class="w-4 h-4 fill-none stroke-current" stroke-width="2" viewBox="0 0 24 24">
                                        <circle cx="12" cy="12" r="10" />
                                        <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10z" />
                                        <path d="M2 12h20" />
                                    </svg>
                                </div>
                                <span class="font-black text-lg text-slate-900 dark:text-white tracking-tight">Live<span class="text-indigo-600 dark:text-indigo-400">Cricket</span></span>
                            </div>
                            <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                                Real-time cricket scorecards, grassroots tournament management, verified umpire hiring, and venue booking for cricket lovers.
                            </p>
                            <div class="pt-2 text-xs">
                                <span class="font-semibold text-slate-700 dark:text-slate-300">Platform Founder:</span>
                                <div class="font-black text-slate-900 dark:text-white">Shubham Thakur</div>
                                <a href="tel:+919876543210" class="text-indigo-600 dark:text-indigo-400 font-bold hover:underline">📞 +91 98765 43210</a>
                            </div>
                        </div>

                        <!-- Quick Links -->
                        <div>
                            <h4 class="text-xs font-black uppercase tracking-wider text-slate-900 dark:text-slate-200 mb-3">Live Cricket</h4>
                            <ul class="space-y-2 text-xs font-medium">
                                <li><a href="{{ route('live-scores.index') }}" class="hover:text-indigo-600 dark:hover:text-white transition">🏏 Live Match Scores</a></li>
                                <li><a href="{{ route('live-scores.index') }}?tab=upcoming" class="hover:text-indigo-600 dark:hover:text-white transition">📅 Upcoming Fixtures</a></li>
                                <li><a href="{{ route('live-scores.index') }}?tab=recent" class="hover:text-indigo-600 dark:hover:text-white transition">🏆 Recent Match Results</a></li>
                                <li><a href="{{ route('dashboard') }}" class="hover:text-indigo-600 dark:hover:text-white transition">🔥 Cricket Feed & Memes</a></li>
                            </ul>
                        </div>

                        <!-- Community & Venues -->
                        <div>
                            <h4 class="text-xs font-black uppercase tracking-wider text-slate-900 dark:text-slate-200 mb-3">Platform Services</h4>
                            <ul class="space-y-2 text-xs font-medium">
                                <li><a href="{{ route('matches.index') }}" class="hover:text-indigo-600 dark:hover:text-white transition">⚡ Local Matches Arena</a></li>
                                <li><a href="{{ route('grounds.index') }}" class="hover:text-indigo-600 dark:hover:text-white transition">🏟️ Cricket Grounds & Turf</a></li>
                                <li><a href="{{ route('umpires.index') }}" class="hover:text-indigo-600 dark:hover:text-white transition">⚖️ Verified Match Umpires</a></li>
                                @auth
                                    <li><a href="{{ route('profile.show') }}" class="hover:text-indigo-600 dark:hover:text-white transition">👤 My Player Passport</a></li>
                                @endauth
                            </ul>
                        </div>

                        <!-- Company & Contact -->
                        <div>
                            <h4 class="text-xs font-black uppercase tracking-wider text-slate-900 dark:text-slate-200 mb-3">Company</h4>
                            <ul class="space-y-2 text-xs font-medium">
                                <li><a href="{{ route('about') }}" class="hover:text-indigo-600 dark:hover:text-white transition font-bold text-indigo-600 dark:text-indigo-400">📖 About Us</a></li>
                                <li><a href="{{ route('contact') }}" class="hover:text-indigo-600 dark:hover:text-white transition font-bold text-indigo-600 dark:text-indigo-400">📬 Contact Us</a></li>
                                <li><a href="{{ route('privacy') }}" class="hover:text-indigo-600 dark:hover:text-white transition">🔒 Privacy Policy</a></li>
                                <li><a href="{{ route('terms') }}" class="hover:text-indigo-600 dark:hover:text-white transition">⚖️ Terms of Service</a></li>
                                <li><a href="https://wa.me/919876543210?text=Hi%20Shubham,%20I%20have%20an%20enquiry%20regarding%20LiveCricket" target="_blank" rel="noopener noreferrer" class="hover:text-emerald-500 transition text-emerald-600 dark:text-emerald-400 font-bold">💬 WhatsApp Founder</a></li>
                            </ul>
                            <div class="mt-4 pt-3 border-t border-slate-200 dark:border-white/10 flex items-center gap-2">
                                <button type="button" onclick="toggleTheme()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold bg-slate-100 hover:bg-slate-200 dark:bg-white/5 dark:hover:bg-white/10 text-slate-700 dark:text-slate-300 transition">
                                    <span>🌓 Switch Mode</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-slate-200 dark:border-white/10 mt-8 pt-6 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-slate-500">
                        <p>© {{ date('Y') }} LiveCricket. Created by <strong class="text-slate-800 dark:text-slate-200">Shubham Thakur</strong> (+91 98765 43210). All rights reserved.</p>
                        <div class="flex items-center gap-4 flex-wrap">
                            <a href="{{ route('about') }}" class="hover:underline">About</a>
                            <a href="{{ route('contact') }}" class="hover:underline">Contact</a>
                            <a href="{{ route('privacy') }}" class="hover:underline">Privacy Policy</a>
                            <a href="{{ route('terms') }}" class="hover:underline">Terms of Service</a>
                            <a href="tel:+919876543210" class="hover:underline">+91 98765 43210</a>
                        </div>
                    </div>
                </div>
            </footer>
        </div>

        <!-- PWA Mobile Install Banner -->
        <div id="pwa-install-banner" 
             style="display: none;" 
             class="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-6 sm:bottom-6 sm:max-w-md z-50 bg-[#131b2e]/95 backdrop-blur-md border-2 border-indigo-500/40 rounded-2xl p-4 shadow-2xl text-white">
            <div class="flex items-center gap-3">
                <img src="/icons/icon-192x192.png" alt="LiveCricket" class="w-12 h-12 rounded-xl shadow-md border border-white/10 shrink-0">
                <div class="flex-1 min-w-0">
                    <h4 class="font-black text-sm text-white truncate">Install LiveCricket App</h4>
                    <p class="text-[11px] text-slate-300 line-clamp-1">Faster live scores, offline support & full screen</p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <button id="pwa-install-btn" type="button" 
                            class="px-3.5 py-2 rounded-xl bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-500 text-white font-black text-xs shadow-md shadow-red-600/30 active:scale-95 transition">
                        Install 📲
                    </button>
                    <button id="pwa-close-btn" type="button" class="p-1.5 text-slate-400 hover:text-white rounded-lg">
                        ✕
                    </button>
                </div>
            </div>
        </div>

        <!-- PWA Service Worker & Install Script -->
        <script>
            // Register Service Worker
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', () => {
                    navigator.serviceWorker.register('/sw.js')
                        .then(reg => console.log('LiveCricket PWA SW Registered:', reg.scope))
                        .catch(err => console.warn('LiveCricket PWA SW error:', err));
                });
            }

            // PWA Install Prompt Handler
            let deferredPrompt = null;
            const installBanner = document.getElementById('pwa-install-banner');
            const installBtn = document.getElementById('pwa-install-btn');
            const closeBtn = document.getElementById('pwa-close-btn');

            window.addEventListener('beforeinstallprompt', (e) => {
                e.preventDefault();
                deferredPrompt = e;
                if (!localStorage.getItem('pwa_install_dismissed')) {
                    if (installBanner) installBanner.style.display = 'block';
                }
            });

            if (installBtn) {
                installBtn.addEventListener('click', async () => {
                    if (!deferredPrompt) return;
                    deferredPrompt.prompt();
                    const { outcome } = await deferredPrompt.userChoice;
                    console.log('User PWA install response:', outcome);
                    deferredPrompt = null;
                    if (installBanner) installBanner.style.display = 'none';
                });
            }

            if (closeBtn) {
                closeBtn.addEventListener('click', () => {
                    if (installBanner) installBanner.style.display = 'none';
                    localStorage.setItem('pwa_install_dismissed', '1');
                });
            }

            window.addEventListener('appinstalled', () => {
                if (installBanner) installBanner.style.display = 'none';
                deferredPrompt = null;
                console.log('LiveCricket PWA installed successfully!');
            });
        </script>
    </body>
</html>
