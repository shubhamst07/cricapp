@php
    $decodeSeo = static fn (string $val): string => html_entity_decode(trim($val), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $seoTitle = $decodeSeo($__env->yieldContent('title', 'CricApp.in - Live Cricket Scores, News & Match Center'));
    $seoDesc = $decodeSeo($__env->yieldContent('meta_description', 'Watch live international and local cricket scores, ball-by-ball commentary, detailed scorecards, cricket news stories, turf ground bookings, and verified umpires on CricApp.in.'));
    $seoCanonical = $decodeSeo($__env->yieldContent('canonical', url()->current()));
    $seoRobots = $decodeSeo($__env->yieldContent('robots', 'index, follow, max-image-preview:large'));
    $seoOgType = $decodeSeo($__env->yieldContent('og_type', 'website'));
    $seoImage = $decodeSeo($__env->yieldContent('og_image', url('/icons/icon-512x512.png')));
    $adsenseClientId = config('services.adsense.client_id');
    $googleSiteVerification = config('services.google.site_verification');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $seoTitle }}</title>
        <meta name="description" content="{{ $seoDesc }}">
        <meta name="robots" content="{{ $seoRobots }}">
        <link rel="canonical" href="{{ $seoCanonical }}">
        @if($googleSiteVerification)
            <meta name="google-site-verification" content="{{ $googleSiteVerification }}">
        @endif
        <meta name="p:domain_verify" content="d27f1dab4f1097be198ea72c58f63db3"/>

        <!-- Open Graph & Social SEO -->
        <meta property="og:site_name" content="CricApp.in">
        <meta property="og:type" content="{{ $seoOgType }}">
        <meta property="og:title" content="{{ $seoTitle }}">
        <meta property="og:description" content="{{ $seoDesc }}">
        <meta property="og:url" content="{{ $seoCanonical }}">
        <meta property="og:image" content="{{ $seoImage }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $seoTitle }}">
        <meta name="twitter:description" content="{{ $seoDesc }}">
        <meta name="twitter:image" content="{{ $seoImage }}">
        @stack('meta')

        <!-- Structured Data (JSON-LD) -->
        @hasSection('schema')
            @yield('schema')
        @else
            <script type="application/ld+json">{!! json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => 'CricApp.in',
                'url' => rtrim(config('app.url', 'https://www.cricapp.in'), '/'),
                'description' => $seoDesc,
                'publisher' => [
                    '@type' => 'Organization',
                    'name' => 'CricApp.in',
                    'url' => rtrim(config('app.url', 'https://www.cricapp.in'), '/'),
                    'logo' => [
                        '@type' => 'ImageObject',
                        'url' => url('/icons/icon-512x512.png'),
                    ],
                ],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        @endif

        <!-- PWA & Mobile App Meta Tags -->
        <link rel="alternate" type="application/rss+xml" title="CricApp.in - Cricket News &amp; Match Reports" href="{{ url('/rss.xml') }}">
        <link rel="manifest" href="/manifest.json">
        <meta name="theme-color" content="#0f172a">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="CricApp">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <link rel="icon" type="image/x-icon" href="/favicon.ico">
        <link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192x192.png">
        <link rel="icon" type="image/png" sizes="512x512" href="/icons/icon-512x512.png">

        <!-- Google AdSense Tag (cached safely via config/services.php) -->
        @if($adsenseClientId)
            <meta name="adsense-client" content="{{ $adsenseClientId }}">
            <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client={{ $adsenseClientId }}" crossorigin="anonymous"></script>
        @endif

        <x-analytics-head />

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

        <!-- Non-blocking Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800,900&display=swap" rel="stylesheet" media="print" onload="this.media='all'" />
        <noscript><link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800,900&display=swap" rel="stylesheet" /></noscript>

        <!-- Scripts -->
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    </head>
    <body class="font-sans antialiased bg-slate-50 text-slate-900 dark:bg-[#080d1a] dark:text-slate-100 min-h-screen selection:bg-indigo-600 selection:text-white relative overflow-x-hidden flex flex-col justify-between transition-colors duration-200">
        <!-- Global Animated Cricket Bat & Ball Loader -->
        <x-cricket-loader label="Loading pitch..." />

        <!-- Lightweight Ambient Stadium Glow (zero heavy blur compositing) -->
        <div class="fixed inset-0 pointer-events-none -z-10 overflow-hidden dark:opacity-100 opacity-20 transition-opacity"
             style="background-image: radial-gradient(circle at 12% 8%, rgba(79,70,229,0.14), transparent 36%), radial-gradient(circle at 88% 35%, rgba(37,99,235,0.11), transparent 38%), radial-gradient(circle at 45% 92%, rgba(16,185,129,0.10), transparent 36%);">
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
                                <span class="font-black text-lg text-slate-900 dark:text-white tracking-tight">CricApp<span class="text-indigo-600 dark:text-indigo-400">.in</span></span>
                            </div>
                            <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                                Real-time cricket scorecards, grassroots tournament management, verified umpire hiring, and venue booking for cricket lovers.
                            </p>
                            <div class="pt-2 text-xs">
                                <span class="font-semibold text-slate-700 dark:text-slate-300">Platform Founder:</span>
                                <div class="font-black text-slate-900 dark:text-white">Shubham Thakur</div>
                                <a href="tel:+918219795479" class="text-indigo-600 dark:text-indigo-400 font-bold hover:underline">📞 +91 82197 95479</a>
                            </div>
                        </div>

                        <!-- Quick Links -->
                        <div>
                            <h4 class="text-xs font-black uppercase tracking-wider text-slate-900 dark:text-slate-200 mb-3">Live Cricket</h4>
                            <ul class="space-y-2 text-xs font-medium">
                                <li><a href="{{ route('live-scores.index') }}" class="hover:text-indigo-600 dark:hover:text-white transition">🏏 Live Match Scores</a></li>
                                <li><a href="{{ route('live-scores.index') }}?tab=upcoming" class="hover:text-indigo-600 dark:hover:text-white transition">📅 Upcoming Fixtures</a></li>
                                <li><a href="{{ route('live-scores.index') }}?tab=recent" class="hover:text-indigo-600 dark:hover:text-white transition">🏆 Recent Match Results</a></li>
                                <li><a href="{{ route('feed') }}" class="hover:text-indigo-600 dark:hover:text-white transition">🔥 Cricket Feed & Memes</a></li>
                            </ul>
                        </div>

                        <!-- Community & Venues -->
                        <div>
                            <h4 class="text-xs font-black uppercase tracking-wider text-slate-900 dark:text-slate-200 mb-3">Platform Services</h4>
                            <ul class="space-y-2 text-xs font-medium">
                                <li><a href="{{ route('matches.index') }}" class="hover:text-indigo-600 dark:hover:text-white transition">⚡ Local Matches Arena</a></li>
                                <li><a href="{{ route('articles.index') }}" class="hover:text-amber-400 dark:hover:text-amber-400 text-amber-500 font-bold transition">📰 News & Articles</a></li>
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
                                <li><a href="https://wa.me/918219795479?text=Hi%20Shubham,%20I%20have%20an%20enquiry%20regarding%20CricApp" target="_blank" rel="noopener noreferrer" class="hover:text-emerald-500 transition text-emerald-600 dark:text-emerald-400 font-bold">💬 WhatsApp Founder</a></li>
                            </ul>
                            <div class="mt-4 pt-3 border-t border-slate-200 dark:border-white/10 flex items-center gap-2">
                                <button type="button" onclick="toggleTheme()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold bg-slate-100 hover:bg-slate-200 dark:bg-white/5 dark:hover:bg-white/10 text-slate-700 dark:text-slate-300 transition">
                                    <span>🌓 Switch Mode</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-slate-200 dark:border-white/10 mt-8 pt-6 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-slate-500">
                        <p>© {{ date('Y') }} CricApp.in. Created by <strong class="text-slate-800 dark:text-slate-200">Shubham Thakur</strong> (+91 82197 95479). All rights reserved.</p>
                        <div class="flex items-center gap-4 flex-wrap">
                            <a href="{{ route('about') }}" class="hover:underline">About</a>
                            <a href="{{ route('contact') }}" class="hover:underline">Contact</a>
                            <a href="{{ route('privacy') }}" class="hover:underline">Privacy Policy</a>
                            <a href="{{ route('terms') }}" class="hover:underline">Terms of Service</a>
                            <a href="tel:+918219795479" class="hover:underline">+91 82197 95479</a>
                        </div>
                    </div>
                </div>
            </footer>
        </div>

        <!-- PWA Mobile Install Banner (Floating Bottom Card) -->
        <div id="pwa-install-banner" 
             style="display: none;" 
             class="fixed bottom-4 left-3 right-3 sm:left-auto sm:right-6 sm:bottom-6 sm:max-w-md z-50 bg-[#131b2e]/95 backdrop-blur-xl border border-red-500/30 rounded-2xl p-3.5 sm:p-4 shadow-2xl text-white">
            <div class="flex items-center gap-3">
                <img src="/icons/icon-192x192.png" alt="CricApp" class="w-11 h-11 sm:w-12 sm:h-12 rounded-xl shadow-md border border-white/10 shrink-0">
                <div class="flex-1 min-w-0">
                    <h4 class="font-black text-xs sm:text-sm text-white truncate flex items-center gap-1.5">
                        <span>CricApp.in</span>
                        <span class="px-1.5 py-0.2 rounded text-[9px] font-black bg-red-600/30 text-red-300">Fast ⚡</span>
                    </h4>
                    <p class="text-[11px] text-slate-300 line-clamp-1">Fast ball-by-ball scores & full screen experience</p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <button type="button" onclick="triggerPwaInstall()" 
                            class="px-3.5 py-2 rounded-xl bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-500 text-white font-black text-xs shadow-md shadow-red-600/30 active:scale-95 transition cursor-pointer">
                        Install 📲
                    </button>
                    <button id="pwa-close-btn" type="button" 
                            class="p-1.5 text-slate-400 hover:text-white rounded-lg transition" 
                            title="Dismiss">
                        ✕
                    </button>
                </div>
            </div>
        </div>

        <!-- PWA Manual Install Instructions Modal (For iOS Safari & Supported Browsers) -->
        <div id="pwa-guide-modal" 
             style="display: none;" 
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-md">
            <div class="relative w-full max-w-sm rounded-3xl bg-[#131b2e] border border-white/15 p-6 shadow-2xl space-y-5 text-white">
                <div class="flex items-center justify-between border-b border-white/10 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">📲</span>
                        <h3 class="font-black text-base text-white">Install CricApp</h3>
                    </div>
                    <button type="button" onclick="closePwaGuideModal()" class="text-slate-400 hover:text-white font-bold text-sm">
                        ✕
                    </button>
                </div>

                <!-- Instructions Content (Dynamically shown based on OS) -->
                <div id="pwa-ios-instructions" class="space-y-3.5 text-xs text-slate-200">
                    <p class="text-slate-300">Follow these 2 simple steps to install on your <strong>iPhone / iPad</strong>:</p>
                    <div class="p-3 rounded-xl bg-slate-900 border border-white/5 space-y-2">
                        <div class="flex items-center gap-2.5 font-bold text-white">
                            <span class="w-6 h-6 rounded-lg bg-indigo-600 flex items-center justify-center text-xs">1</span>
                            <span>Tap Safari Share button</span>
                            <span class="text-base">⎙</span>
                        </div>
                        <p class="text-[11px] text-slate-400 pl-8">Located at the bottom of your Safari screen.</p>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-900 border border-white/5 space-y-2">
                        <div class="flex items-center gap-2.5 font-bold text-white">
                            <span class="w-6 h-6 rounded-lg bg-indigo-600 flex items-center justify-center text-xs">2</span>
                            <span>Scroll & tap "Add to Home Screen"</span>
                            <span class="text-base">➕</span>
                        </div>
                        <p class="text-[11px] text-slate-400 pl-8">CricApp will appear on your home screen!</p>
                    </div>
                </div>

                <div id="pwa-android-instructions" class="space-y-3.5 text-xs text-slate-200" style="display: none;">
                    <p class="text-slate-300">To install on <strong>Android / Chrome / Edge</strong>:</p>
                    <div class="p-3 rounded-xl bg-slate-900 border border-white/5 space-y-2">
                        <div class="flex items-center gap-2.5 font-bold text-white">
                            <span class="w-6 h-6 rounded-lg bg-red-600 flex items-center justify-center text-xs">1</span>
                            <span>Tap Chrome Menu</span>
                            <span class="text-base">⋮</span>
                        </div>
                        <p class="text-[11px] text-slate-400 pl-8">At the top-right corner of your browser.</p>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-900 border border-white/5 space-y-2">
                        <div class="flex items-center gap-2.5 font-bold text-white">
                            <span class="w-6 h-6 rounded-lg bg-red-600 flex items-center justify-center text-xs">2</span>
                            <span>Select "Install app" or "Add to Home screen"</span>
                        </div>
                    </div>
                </div>

                <button type="button" onclick="closePwaGuideModal()" 
                        class="w-full py-2.5 rounded-xl bg-gradient-to-r from-red-600 to-rose-600 text-white font-black text-xs uppercase tracking-wider shadow-md">
                    Got It 👍
                </button>
            </div>
        </div>

        <!-- PWA Service Worker & Universal Install Engine -->
        <script>
            // Register Service Worker
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', () => {
                    navigator.serviceWorker.register('/sw.js')
                        .then(reg => console.log('LiveCricket PWA SW Registered:', reg.scope))
                        .catch(err => console.warn('LiveCricket PWA SW error:', err));
                });
            }

            // Universal PWA Install Trigger Engine
            window.deferredPWAInstallPrompt = null;
            const isRunningStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

            window.addEventListener('beforeinstallprompt', (e) => {
                e.preventDefault();
                window.deferredPWAInstallPrompt = e;

                // Show install floating banner if not running in standalone & not snoozed
                if (!isRunningStandalone) {
                    const dismissedUntil = localStorage.getItem('pwa_dismissed_until');
                    const now = Date.now();
                    if (!dismissedUntil || now > parseInt(dismissedUntil)) {
                        const banner = document.getElementById('pwa-install-banner');
                        if (banner) banner.style.display = 'block';
                    }
                }
            });

            // Master trigger called by any Install button across the app
            window.triggerPwaInstall = async function() {
                if (isRunningStandalone) {
                    alert('🏏 CricApp is already installed on your device!');
                    return;
                }

                if (window.deferredPWAInstallPrompt) {
                    window.deferredPWAInstallPrompt.prompt();
                    const { outcome } = await window.deferredPWAInstallPrompt.userChoice;
                    console.log('PWA prompt user choice:', outcome);
                    if (outcome === 'accepted') {
                        window.deferredPWAInstallPrompt = null;
                        const banner = document.getElementById('pwa-install-banner');
                        if (banner) banner.style.display = 'none';
                    }
                } else {
                    // Browser didn't provide automatic prompt (e.g. iOS Safari, or Chrome already prompted)
                    openPwaGuideModal();
                }
            };

            function openPwaGuideModal() {
                const modal = document.getElementById('pwa-guide-modal');
                const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
                const iosBox = document.getElementById('pwa-ios-instructions');
                const androidBox = document.getElementById('pwa-android-instructions');

                if (isIOS) {
                    if (iosBox) iosBox.style.display = 'block';
                    if (androidBox) androidBox.style.display = 'none';
                } else {
                    if (iosBox) iosBox.style.display = 'none';
                    if (androidBox) androidBox.style.display = 'block';
                }

                if (modal) modal.style.display = 'flex';
            }

            function closePwaGuideModal() {
                const modal = document.getElementById('pwa-guide-modal');
                if (modal) modal.style.display = 'none';
            }

            // Close button snoozes the banner for 24 hours
            const closeBtn = document.getElementById('pwa-close-btn');
            if (closeBtn) {
                closeBtn.addEventListener('click', () => {
                    const banner = document.getElementById('pwa-install-banner');
                    if (banner) banner.style.display = 'none';
                    // Snooze for 24 hours
                    localStorage.setItem('pwa_dismissed_until', (Date.now() + 86400000).toString());
                });
            }

            window.addEventListener('appinstalled', () => {
                const banner = document.getElementById('pwa-install-banner');
                if (banner) banner.style.display = 'none';
                window.deferredPWAInstallPrompt = null;
                console.log('LiveCricket PWA installed successfully!');
            });
        </script>
    </body>
</html>
