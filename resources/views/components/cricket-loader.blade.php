@props([
    'inline' => false,
    'label' => 'Loading match center...',
    'id' => 'cricapp-global-loader',
])

@if($inline)
    <div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center py-10 px-4 text-center']) }}>
        <div class="relative flex flex-col items-center gap-3 p-6 rounded-3xl bg-white/90 dark:bg-[#111827]/90 border border-slate-200 dark:border-white/10 shadow-xl backdrop-blur-md">
            <svg class="w-24 h-20 overflow-visible" viewBox="0 0 120 90" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <!-- 22-Yard Pitch Crease -->
                <rect x="18" y="79" width="84" height="3" rx="1.5" class="fill-slate-300 dark:fill-slate-700" />
                <ellipse cx="45" cy="78" rx="16" ry="3.5" class="fill-indigo-500/35 dark:fill-emerald-400/35 cric-pitch-shadow" />

                <!-- Subtle Stumps Silhouette -->
                <g opacity="0.28" class="stroke-slate-500 dark:stroke-slate-400" stroke-width="2" stroke-linecap="round">
                    <line x1="22" y1="36" x2="22" y2="78" />
                    <line x1="27" y1="36" x2="27" y2="78" />
                    <line x1="32" y1="36" x2="32" y2="78" />
                    <line x1="21" y1="35" x2="33" y2="35" />
                </g>

                <!-- Spinning Crimson Leather Cricket Ball -->
                <g class="cric-ball-body">
                    <defs>
                        <radialGradient id="cricBallGradInline" cx="35%" cy="30%" r="70%">
                            <stop offset="0%" stop-color="#fb7185" />
                            <stop offset="55%" stop-color="#e11d48" />
                            <stop offset="100%" stop-color="#881337" />
                        </radialGradient>
                    </defs>
                    <circle cx="45" cy="55" r="11" fill="url(#cricBallGradInline)" />
                    <g class="cric-ball-seam">
                        <path d="M36 51 Q45 55 54 59" stroke="#fff1f2" stroke-width="1.6" stroke-dasharray="2.5 2" stroke-linecap="round" />
                        <path d="M37 49 Q45 53 53 57" stroke="#fecdd3" stroke-width="0.9" stroke-dasharray="2 2.5" stroke-linecap="round" opacity="0.7" />
                    </g>
                </g>

                <!-- Swinging Willow Cricket Bat -->
                <g class="cric-bat-arm">
                    <defs>
                        <linearGradient id="cricWillowGradInline" x1="0" y1="0" x2="1" y2="1">
                            <stop offset="0%" stop-color="#fef08a" />
                            <stop offset="55%" stop-color="#f59e0b" />
                            <stop offset="100%" stop-color="#b45309" />
                        </linearGradient>
                    </defs>
                    <!-- Handle -->
                    <rect x="75.5" y="10" width="5" height="22" rx="2.5" class="fill-indigo-600 dark:fill-indigo-400" />
                    <line x1="75.5" y1="15" x2="80.5" y2="15" stroke="#ffffff" stroke-width="1.2" opacity="0.6" />
                    <line x1="75.5" y1="20" x2="80.5" y2="20" stroke="#ffffff" stroke-width="1.2" opacity="0.6" />
                    <line x1="75.5" y1="25" x2="80.5" y2="25" stroke="#ffffff" stroke-width="1.2" opacity="0.6" />
                    <!-- Willow Blade -->
                    <path d="M71 32 L85 32 L87 69 Q87 74 78 75 Q69 74 69 69 Z" fill="url(#cricWillowGradInline)" stroke="#b45309" stroke-width="1" />
                    <line x1="78" y1="33" x2="78" y2="69" stroke="#fef9c3" stroke-width="1.3" opacity="0.7" stroke-linecap="round" />
                </g>
            </svg>

            <div class="space-y-1">
                <div class="text-xs font-black tracking-tight text-slate-900 dark:text-white">
                    Cric<span class="text-indigo-600 dark:text-amber-400">App</span><span class="text-[10px] text-indigo-500">.in</span>
                </div>
                <p class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">{{ $label }}</p>
            </div>
        </div>
    </div>
@else
    <div id="{{ $id }}"
         role="status"
         aria-live="polite"
         class="fixed inset-0 z-[100] flex flex-col items-center justify-center bg-slate-950/35 dark:bg-[#050914]/70 backdrop-blur-sm transition-opacity duration-300 opacity-0 pointer-events-none">
        <!-- Top Animated Pitch Progress Bar -->
        <div class="fixed top-0 left-0 right-0 h-[3px] overflow-hidden bg-slate-200/30 dark:bg-white/10">
            <div class="h-full w-1/3 bg-gradient-to-r from-indigo-500 via-emerald-400 to-amber-500 cric-progress-sweep"></div>
        </div>

        <!-- Center Stage Cricket Bat & Ball Card -->
        <div class="relative flex flex-col items-center gap-3 px-8 py-6 rounded-3xl bg-white/95 dark:bg-[#0f172a]/95 border border-slate-200/90 dark:border-white/15 shadow-2xl shadow-indigo-950/20 dark:shadow-black/70">
            <svg class="w-28 h-24 overflow-visible" viewBox="0 0 120 90" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <!-- 22-Yard Pitch Crease & Shadow -->
                <rect x="16" y="79" width="88" height="3" rx="1.5" class="fill-slate-300 dark:fill-slate-700" />
                <ellipse cx="45" cy="78" rx="16" ry="3.5" class="fill-indigo-500/35 dark:fill-emerald-400/35 cric-pitch-shadow" />

                <!-- Subtle Stumps -->
                <g opacity="0.28" class="stroke-slate-500 dark:stroke-slate-400" stroke-width="2" stroke-linecap="round">
                    <line x1="21" y1="36" x2="21" y2="78" />
                    <line x1="26" y1="36" x2="26" y2="78" />
                    <line x1="31" y1="36" x2="31" y2="78" />
                    <line x1="20" y1="35" x2="32" y2="35" />
                </g>

                <!-- Bouncing Spinning Seam Ball -->
                <g class="cric-ball-body">
                    <defs>
                        <radialGradient id="cricBallGradGlobal" cx="35%" cy="30%" r="70%">
                            <stop offset="0%" stop-color="#fb7185" />
                            <stop offset="55%" stop-color="#e11d48" />
                            <stop offset="100%" stop-color="#881337" />
                        </radialGradient>
                    </defs>
                    <circle cx="45" cy="55" r="11.5" fill="url(#cricBallGradGlobal)" />
                    <g class="cric-ball-seam">
                        <path d="M35.5 51 Q45 55 54.5 59" stroke="#fff1f2" stroke-width="1.7" stroke-dasharray="2.5 2" stroke-linecap="round" />
                        <path d="M36.5 48.5 Q45 52.5 53.5 56.5" stroke="#fecdd3" stroke-width="1" stroke-dasharray="2 2.5" stroke-linecap="round" opacity="0.7" />
                    </g>
                </g>

                <!-- Swinging Cricket Bat -->
                <g class="cric-bat-arm">
                    <defs>
                        <linearGradient id="cricWillowGradGlobal" x1="0" y1="0" x2="1" y2="1">
                            <stop offset="0%" stop-color="#fef08a" />
                            <stop offset="55%" stop-color="#f59e0b" />
                            <stop offset="100%" stop-color="#b45309" />
                        </linearGradient>
                    </defs>
                    <rect x="75.5" y="10" width="5" height="22" rx="2.5" class="fill-indigo-600 dark:fill-indigo-400" />
                    <line x1="75.5" y1="15" x2="80.5" y2="15" stroke="#ffffff" stroke-width="1.2" opacity="0.6" />
                    <line x1="75.5" y1="20" x2="80.5" y2="20" stroke="#ffffff" stroke-width="1.2" opacity="0.6" />
                    <line x1="75.5" y1="25" x2="80.5" y2="25" stroke="#ffffff" stroke-width="1.2" opacity="0.6" />
                    <path d="M71 32 L85 32 L87 69 Q87 74 78 75 Q69 74 69 69 Z" fill="url(#cricWillowGradGlobal)" stroke="#b45309" stroke-width="1" />
                    <line x1="78" y1="33" x2="78" y2="69" stroke="#fef9c3" stroke-width="1.3" opacity="0.7" stroke-linecap="round" />
                </g>
            </svg>

            <div class="text-center space-y-1">
                <div class="text-sm font-black tracking-tight text-slate-900 dark:text-white">
                    Cric<span class="text-indigo-600 dark:text-amber-400">App</span><span class="text-xs text-indigo-500 font-bold">.in</span>
                </div>
                <p id="cricapp-loader-text" class="text-[11px] font-bold text-slate-500 dark:text-slate-400 tracking-wide">
                    {{ $label }}
                </p>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const loader = document.getElementById(@json($id));
            const textEl = document.getElementById('cricapp-loader-text');
            if (!loader) return;

            let safetyTimer = null;

            function showLoader(msg) {
                if (textEl && msg) textEl.textContent = msg;
                else if (textEl) textEl.textContent = @json($label);

                loader.classList.remove('opacity-0', 'pointer-events-none');
                loader.classList.add('opacity-100');

                clearTimeout(safetyTimer);
                safetyTimer = setTimeout(hideLoader, 8000);
            }

            function hideLoader() {
                clearTimeout(safetyTimer);
                loader.classList.add('opacity-0', 'pointer-events-none');
                loader.classList.remove('opacity-100');
            }

            window.CricLoader = { show: showLoader, hide: hideLoader };

            // Hide when page renders or restores from browser Back/Forward cache
            if (document.readyState === 'loading') {
                showLoader();
                document.addEventListener('DOMContentLoaded', () => setTimeout(hideLoader, 160), { once: true });
            } else {
                hideLoader();
            }

            window.addEventListener('pageshow', hideLoader);
            window.addEventListener('load', hideLoader);
            window.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') hideLoader();
            });

            // Trigger automatically on internal navigation link clicks
            document.addEventListener('click', (e) => {
                if (e.defaultPrevented || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) {
                    return;
                }
                const link = e.target.closest('a[href]');
                if (!link) return;

                const href = link.getAttribute('href') || '';
                const target = (link.getAttribute('target') || '').toLowerCase();
                if (
                    !href ||
                    href.startsWith('#') ||
                    href.startsWith('javascript:') ||
                    href.startsWith('mailto:') ||
                    href.startsWith('tel:') ||
                    target === '_blank' ||
                    link.hasAttribute('download')
                ) {
                    return;
                }

                try {
                    const url = new URL(link.href, window.location.origin);
                    if (url.origin !== window.location.origin) return;
                    if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash) return;
                } catch (_) {
                    return;
                }

                showLoader('Loading pitch...');
            }, false);

            // Trigger automatically on standard non-AJAX form submissions
            document.addEventListener('submit', (e) => {
                if (e.defaultPrevented) return;
                const form = e.target;
                if (!form || form.dataset.noLoader === 'true') return;
                showLoader('Updating scoreboard...');
            }, false);
        })();
    </script>
@endif
