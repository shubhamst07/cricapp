@php
    $isInternationalActive = (request()->routeIs('live-scores.*') || request()->routeIs('home')) && request()->get('section', 'international') === 'international';
    $isLocalActive = request()->routeIs('live-scores.*') && request()->get('section') === 'local';
    $isFeedActive = request()->routeIs('feed') || request()->routeIs('dashboard');
    $isMatchesActive = request()->routeIs('matches.*');
    $isArticlesActive = request()->routeIs('articles.*') || request()->routeIs('news.*');
    $isMoreActive = request()->routeIs('grounds.*') || request()->routeIs('meetups.*') || request()->routeIs('umpires.*') || request()->routeIs('about') || request()->routeIs('contact');
@endphp

<nav x-data="{ open: false, isDark: document.documentElement.classList.contains('dark') }"
     @theme-changed.window="isDark = document.documentElement.classList.contains('dark')"
     class="bg-white/90 dark:bg-[#0b1120]/90 backdrop-blur-xl border-b border-slate-200/80 dark:border-white/10 sticky top-0 z-40 transition-colors duration-200">
    <!-- Primary Navigation Bar -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16 gap-4">

            <!-- Brand Logo -->
            <div class="shrink-0 flex items-center">
                <a href="{{ route('live-scores.index') }}" class="flex items-center gap-2.5 group whitespace-nowrap">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-indigo-600 to-blue-500 flex items-center justify-center text-white shadow-sm shadow-indigo-600/25 group-hover:scale-105 transition transform">
                        <svg class="w-5 h-5 fill-none stroke-current" stroke-width="2" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10" />
                            <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" />
                            <path d="M2 12h20" />
                        </svg>
                    </div>
                    <span class="font-black text-xl text-slate-900 dark:text-white tracking-tight">Cric<span class="text-indigo-600 dark:text-amber-400">App</span><span class="text-xs text-indigo-500 dark:text-indigo-400 font-bold ml-0.5">.in</span></span>
                </a>
            </div>

            <!-- Center Desktop Navigation Track (Single-Row, Icon-Free Pill Bar) -->
            <div class="hidden lg:flex items-center justify-center">
                <div class="inline-flex items-center gap-1 p-1 rounded-xl bg-slate-100/90 dark:bg-white/[0.04] border border-slate-200/80 dark:border-white/[0.08]">
                    <x-nav-link :href="route('live-scores.index', ['section' => 'international'])" :active="$isInternationalActive">
                        {{ __('International') }}
                    </x-nav-link>

                    <x-nav-link :href="route('live-scores.index', ['section' => 'local'])" :active="$isLocalActive">
                        {{ __('Local Cricket') }}
                    </x-nav-link>

                    <x-nav-link :href="route('feed')" :active="$isFeedActive">
                        {{ __('Cricket Feed') }}
                    </x-nav-link>

                    <x-nav-link :href="route('matches.index')" :active="$isMatchesActive">
                        {{ Auth::check() ? __('My Matches') : __('Matches') }}
                    </x-nav-link>

                    <x-nav-link :href="route('articles.index')" :active="$isArticlesActive">
                        {{ __('News & Articles') }}
                    </x-nav-link>

                    <!-- Clean Explore / More Dropdown -->
                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <button type="button"
                                    class="inline-flex items-center justify-center gap-1 whitespace-nowrap px-3 py-2 rounded-lg text-[13px] leading-none transition-all duration-150 cursor-pointer focus:outline-none {{ $isMoreActive ? 'bg-white dark:bg-indigo-600 text-indigo-600 dark:text-white font-bold shadow-sm' : 'text-slate-600 dark:text-slate-300 font-semibold hover:text-slate-900 dark:hover:text-white hover:bg-white/70 dark:hover:bg-white/[0.06]' }}">
                                <span>{{ __('More') }}</span>
                                <svg class="w-3.5 h-3.5 opacity-75" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            <x-dropdown-link :href="route('grounds.index')" :active="request()->routeIs('grounds.*')">
                                {{ __('Grounds') }}
                            </x-dropdown-link>
                            <x-dropdown-link :href="route('meetups.index')" :active="request()->routeIs('meetups.*')">
                                {{ __('Find Players') }}
                            </x-dropdown-link>
                            <x-dropdown-link :href="route('umpires.index')" :active="request()->routeIs('umpires.*')">
                                {{ __('Umpires') }}
                            </x-dropdown-link>

                            <div class="my-1 border-t border-slate-200 dark:border-white/10"></div>

                            <x-dropdown-link :href="route('about')" :active="request()->routeIs('about')">
                                {{ __('About Us') }}
                            </x-dropdown-link>
                            <x-dropdown-link :href="route('contact')" :active="request()->routeIs('contact')">
                                {{ __('Contact Us') }}
                            </x-dropdown-link>
                        </x-slot>
                    </x-dropdown>
                </div>
            </div>

            <!-- Right Desktop Action Cluster -->
            <div class="hidden lg:flex items-center gap-2 shrink-0">
                <button type="button"
                        onclick="triggerPwaInstall()"
                        class="pwa-install-nav-btn h-9 px-3.5 inline-flex items-center justify-center rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs whitespace-nowrap shadow-sm transition active:scale-95 cursor-pointer"
                        title="Install CricApp on your device">
                    {{ __('Install App') }}
                </button>

                <button type="button"
                        onclick="toggleTheme()"
                        class="w-9 h-9 inline-flex items-center justify-center rounded-xl border border-slate-200 dark:border-white/10 bg-slate-100/80 dark:bg-white/[0.05] text-slate-700 dark:text-amber-300 hover:bg-slate-200/80 dark:hover:bg-white/10 transition cursor-pointer shrink-0"
                        title="Toggle Light / Dark Mode"
                        aria-label="Toggle Light / Dark Mode">
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

                @auth
                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <button type="button" class="h-9 inline-flex items-center gap-2 pl-2 pr-3 border border-slate-200 dark:border-white/10 text-xs font-bold rounded-xl text-slate-800 dark:text-white bg-slate-100/80 dark:bg-white/[0.05] hover:bg-slate-200/80 dark:hover:bg-white/10 focus:outline-none transition whitespace-nowrap cursor-pointer">
                                <div class="w-6 h-6 rounded-lg bg-indigo-600 flex items-center justify-center text-[11px] font-black text-white uppercase shrink-0">
                                    {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                                </div>
                                <span class="truncate max-w-[110px]">{{ Auth::user()->name }}</span>
                                <svg class="fill-current h-3.5 w-3.5 text-slate-400 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            @if(Auth::user()->is_admin)
                                <x-dropdown-link :href="route('articles.create')">
                                    <span class="text-amber-600 dark:text-amber-400 font-bold">{{ __('Write Article (Admin)') }}</span>
                                </x-dropdown-link>
                            @endif
                            <x-dropdown-link :href="route('profile.show')" :active="request()->routeIs('profile.show')">
                                {{ __('My Profile') }}
                            </x-dropdown-link>
                            <x-dropdown-link :href="route('inquiries.index')" :active="request()->routeIs('inquiries.*')">
                                {{ __('Contact Inquiries') }}
                            </x-dropdown-link>
                            <x-dropdown-link :href="route('profile.edit')" :active="request()->routeIs('profile.edit')">
                                {{ __('Account Settings') }}
                            </x-dropdown-link>

                            <div class="my-1 border-t border-slate-200 dark:border-white/10"></div>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-dropdown-link :href="route('logout')"
                                        onclick="event.preventDefault(); this.closest('form').submit();"
                                        class="hover:bg-rose-50 dark:hover:bg-rose-950/30">
                                    <span class="text-rose-600 dark:text-rose-400 font-bold">{{ __('Log Out') }}</span>
                                </x-dropdown-link>
                            </form>
                        </x-slot>
                    </x-dropdown>
                @else
                    <div class="flex items-center gap-1.5">
                        <a href="{{ route('login') }}" class="h-9 px-3.5 inline-flex items-center text-xs font-bold text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white rounded-xl hover:bg-slate-100 dark:hover:bg-white/[0.06] whitespace-nowrap transition">
                            {{ __('Log In') }}
                        </a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="h-9 px-4 inline-flex items-center bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl shadow-sm whitespace-nowrap transition active:scale-95">
                                {{ __('Register') }}
                            </a>
                        @endif
                    </div>
                @endauth
            </div>

            <!-- Mobile / Tablet Compact Controls -->
            <div class="flex items-center lg:hidden gap-2">
                <button type="button"
                        onclick="triggerPwaInstall()"
                        class="pwa-install-mobile-btn h-9 px-3 inline-flex items-center justify-center rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs whitespace-nowrap active:scale-95 transition"
                        title="Install CricApp">
                    {{ __('Install App') }}
                </button>

                <button type="button"
                        onclick="toggleTheme()"
                        class="w-9 h-9 inline-flex items-center justify-center rounded-xl text-slate-700 dark:text-amber-300 bg-slate-100 dark:bg-white/[0.05] border border-slate-200 dark:border-white/10"
                        aria-label="Toggle Light / Dark Mode">
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

                <button @click="open = ! open"
                        type="button"
                        class="w-9 h-9 inline-flex items-center justify-center rounded-xl text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-white/[0.05] border border-slate-200 dark:border-white/10 hover:text-slate-900 dark:hover:text-white focus:outline-none transition"
                        aria-label="Toggle Menu">
                    <svg class="h-5 w-5" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Drawer (Clean Categorized Layout, Zero Emojis) -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden lg:hidden bg-white dark:bg-[#0b1120] border-t border-slate-200 dark:border-white/10 shadow-xl">
        <div class="px-4 py-4 space-y-5 max-h-[82vh] overflow-y-auto">
            <!-- Section 1: Cricket -->
            <div class="space-y-1">
                <div class="px-3 text-[10px] font-extrabold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1.5">
                    Cricket
                </div>
                <x-responsive-nav-link :href="route('live-scores.index', ['section' => 'international'])" :active="$isInternationalActive">
                    {{ __('International') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('live-scores.index', ['section' => 'local'])" :active="$isLocalActive">
                    {{ __('Local Cricket') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('feed')" :active="$isFeedActive">
                    {{ __('Cricket Feed') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('matches.index')" :active="$isMatchesActive">
                    {{ Auth::check() ? __('My Matches') : __('Matches') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('articles.index')" :active="$isArticlesActive">
                    {{ __('News & Articles') }}
                </x-responsive-nav-link>
            </div>

            <!-- Section 2: Community & Venues -->
            <div class="space-y-1 pt-3 border-t border-slate-200 dark:border-white/10">
                <div class="px-3 text-[10px] font-extrabold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1.5">
                    Explore
                </div>
                <x-responsive-nav-link :href="route('grounds.index')" :active="request()->routeIs('grounds.*')">
                    {{ __('Grounds') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('meetups.index')" :active="request()->routeIs('meetups.*')">
                    {{ __('Find Players') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('umpires.index')" :active="request()->routeIs('umpires.*')">
                    {{ __('Umpires') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('about')" :active="request()->routeIs('about')">
                    {{ __('About Us') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('contact')" :active="request()->routeIs('contact')">
                    {{ __('Contact Us') }}
                </x-responsive-nav-link>
            </div>

            <!-- Section 3: Account -->
            <div class="pt-4 border-t border-slate-200 dark:border-white/10">
                @auth
                    <div class="px-3 pb-3 flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-indigo-600 flex items-center justify-center text-xs font-black uppercase text-white shrink-0">
                            {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                        </div>
                        <div class="min-w-0">
                            <div class="font-bold text-sm text-slate-900 dark:text-white truncate">{{ Auth::user()->name }}</div>
                            <div class="text-xs text-slate-500 dark:text-slate-400 truncate">{{ Auth::user()->email }}</div>
                        </div>
                    </div>

                    <div class="space-y-1">
                        @if(Auth::user()->is_admin)
                            <x-responsive-nav-link :href="route('articles.create')" :active="request()->routeIs('articles.create')">
                                <span class="text-amber-600 dark:text-amber-400 font-bold">{{ __('Write Article (Admin)') }}</span>
                            </x-responsive-nav-link>
                        @endif
                        <x-responsive-nav-link :href="route('profile.show')" :active="request()->routeIs('profile.show')">
                            {{ __('My Profile') }}
                        </x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('inquiries.index')" :active="request()->routeIs('inquiries.*')">
                            {{ __('Contact Inquiries') }}
                        </x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('profile.edit')" :active="request()->routeIs('profile.edit')">
                            {{ __('Account Settings') }}
                        </x-responsive-nav-link>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-responsive-nav-link :href="route('logout')"
                                    onclick="event.preventDefault(); this.closest('form').submit();">
                                <span class="text-rose-600 dark:text-rose-400 font-bold">{{ __('Log Out') }}</span>
                            </x-responsive-nav-link>
                        </form>
                    </div>
                @else
                    <div class="flex items-center gap-2.5">
                        <a href="{{ route('login') }}" class="flex-1 text-center py-2.5 text-xs font-bold text-slate-700 dark:text-slate-200 bg-slate-100 dark:bg-white/[0.06] rounded-xl border border-slate-200 dark:border-white/10">
                            {{ __('Log In') }}
                        </a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="flex-1 text-center py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl shadow-sm">
                                {{ __('Register') }}
                            </a>
                        @endif
                    </div>
                @endauth
            </div>
        </div>
    </div>
</nav>
