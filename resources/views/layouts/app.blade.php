<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
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

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    </head>
    <body class="font-sans antialiased bg-[#080d1a] text-slate-100 min-h-screen selection:bg-indigo-600 selection:text-white relative overflow-x-hidden flex flex-col justify-between">
        <!-- Ambient Stadium Lights Effect -->
        <div class="fixed inset-0 pointer-events-none -z-10 overflow-hidden">
            <div class="absolute -top-32 -left-32 w-96 h-96 bg-indigo-600/15 rounded-full blur-[130px]"></div>
            <div class="absolute top-1/3 -right-32 w-96 h-96 bg-blue-600/10 rounded-full blur-[140px]"></div>
            <div class="absolute -bottom-32 left-1/3 w-96 h-96 bg-emerald-600/10 rounded-full blur-[130px]"></div>
            <div class="absolute inset-0 bg-[radial-gradient(#1e293b_1px,transparent_1px)] [background-size:24px_24px] opacity-25"></div>
        </div>

        <div class="min-h-screen bg-[#080d1a] text-slate-100 flex flex-col justify-between">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @if (isset($header))
                <header class="bg-[#0b1120]/90 border-b border-white/5 text-white backdrop-blur-md">
                    <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8 text-white">
                        {{ $header }}
                    </div>
                </header>
            @else
                @yield('header')
            @endif

            <!-- Page Content -->
            <main class="flex-1">
                @yield('content')
                {{ $slot ?? '' }}
            </main>
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
