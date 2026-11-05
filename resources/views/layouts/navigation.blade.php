<nav x-data="{ open: false }" class="bg-[#0b1120]/95 backdrop-blur-xl border-b border-white/10 shadow-2xl sticky top-0 z-40">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
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
                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500 animate-ping"></span>
                        </div>
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('live-scores.index')" :active="request()->routeIs('live-scores.*') || request()->routeIs('home')">
                        <span>🏏 {{ __('Live Scores') }}</span>
                    </x-nav-link>

                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        <span class="inline-flex items-center gap-1.5 font-bold">
                            <span class="w-2 h-2 rounded-full bg-rose-500 animate-pulse"></span>
                            <span>{{ __('Cricket Feed & Memes') }}</span>
                        </span>
                    </x-nav-link>

                    @auth
                    <x-nav-link :href="route('matches.index')" :active="request()->routeIs('matches.*')">
                        <span class="inline-flex items-center gap-1.5 font-semibold">
                            <span>⚡ {{ __('My Matches') }}</span>
                        </span>
                    </x-nav-link>
                    @else
                    <x-nav-link :href="route('matches.index')" :active="request()->routeIs('matches.*')">
                        <span class="inline-flex items-center gap-1.5 font-semibold">
                            <span>⚡ {{ __('Matches') }}</span>
                        </span>
                    </x-nav-link>
                    @endauth

                    <x-nav-link :href="route('grounds.index')" :active="request()->routeIs('grounds.*')">
                        <span class="inline-flex items-center gap-1.5 font-semibold">
                            <span>🏟️ {{ __('Grounds') }}</span>
                        </span>
                    </x-nav-link>

                    <x-nav-link :href="route('umpires.index')" :active="request()->routeIs('umpires.*')">
                        <span class="inline-flex items-center gap-1.5 font-semibold">
                            <span>⚖️ {{ __('Umpires') }}</span>
                        </span>
                    </x-nav-link>
                </div>
            </div>

            <!-- Right Side Auth Menu -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                @auth
                    <!-- Settings Dropdown for Authenticated User -->
                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <button class="inline-flex items-center gap-2 px-3.5 py-2 border border-white/10 text-xs font-bold rounded-xl text-white bg-white/5 hover:bg-white/10 focus:outline-none transition">
                                <div class="w-6 h-6 rounded-lg bg-indigo-600 flex items-center justify-center text-[10px] font-black uppercase">
                                    {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                                </div>
                                <div class="truncate max-w-[120px]">{{ Auth::user()->name }}</div>
                                <svg class="fill-current h-3.5 w-3.5 text-slate-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            <x-dropdown-link :href="route('profile.show')">
                                <span>👤 {{ __('My Profile') }}</span>
                            </x-dropdown-link>
                            <x-dropdown-link :href="route('profile.edit')">
                                <span>⚙️ {{ __('Account Settings') }}</span>
                            </x-dropdown-link>

                            <!-- Authentication -->
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-dropdown-link :href="route('logout')"
                                        onclick="event.preventDefault(); this.closest('form').submit();">
                                    <span class="text-rose-400">🚪 {{ __('Log Out') }}</span>
                                </x-dropdown-link>
                            </form>
                        </x-slot>
                    </x-dropdown>
                @else
                    <!-- Guest Links: Login & Register -->
                    <div class="flex items-center gap-2">
                        <a href="{{ route('login') }}" class="text-xs font-bold text-slate-300 hover:text-white px-3.5 py-2 rounded-xl hover:bg-white/5 transition">
                            Log In
                        </a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-500 hover:to-blue-500 text-white text-xs font-black rounded-xl shadow-lg shadow-indigo-600/30 transition transform active:scale-95">
                                Register ⚡
                            </a>
                        @endif
                    </div>
                @endauth
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-xl text-slate-400 hover:text-white hover:bg-white/10 focus:outline-none transition">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden bg-[#0b1120] border-t border-white/10">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('live-scores.index')" :active="request()->routeIs('live-scores.*') || request()->routeIs('home')">
                <span>🏏 {{ __('Live Scores') }}</span>
            </x-responsive-nav-link>

            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                <span class="inline-flex items-center gap-2 font-bold">
                    <span class="w-2 h-2 rounded-full bg-rose-500 animate-pulse"></span>
                    <span>{{ __('Cricket Feed & Memes') }}</span>
                </span>
            </x-responsive-nav-link>

            @auth
            <x-responsive-nav-link :href="route('matches.index')" :active="request()->routeIs('matches.*')">
                <span>⚡ {{ __('My Matches') }}</span>
            </x-responsive-nav-link>
            @else
            <x-responsive-nav-link :href="route('matches.index')" :active="request()->routeIs('matches.*')">
                <span>⚡ {{ __('Matches') }}</span>
            </x-responsive-nav-link>
            @endauth

            <x-responsive-nav-link :href="route('grounds.index')" :active="request()->routeIs('grounds.*')">
                <span>🏟️ {{ __('Grounds') }}</span>
            </x-responsive-nav-link>

            <x-responsive-nav-link :href="route('umpires.index')" :active="request()->routeIs('umpires.*')">
                <span>⚖️ {{ __('Umpires') }}</span>
            </x-responsive-nav-link>
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-3 border-t border-white/10">
            @auth
                <div class="px-4 flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center text-xs font-black uppercase text-white">
                        {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                    </div>
                    <div>
                        <div class="font-bold text-sm text-white">{{ Auth::user()->name }}</div>
                        <div class="text-xs text-slate-400">{{ Auth::user()->email }}</div>
                    </div>
                </div>

                <div class="mt-3 space-y-1">
                    <x-responsive-nav-link :href="route('profile.show')">
                        <span>👤 {{ __('My Profile') }}</span>
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('profile.edit')">
                        <span>⚙️ {{ __('Account Settings') }}</span>
                    </x-responsive-nav-link>

                    <!-- Authentication -->
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <x-responsive-nav-link :href="route('logout')"
                                onclick="event.preventDefault(); this.closest('form').submit();">
                            <span class="text-rose-400">🚪 {{ __('Log Out') }}</span>
                        </x-responsive-nav-link>
                    </form>
                </div>
            @else
                <div class="px-4 py-2 flex items-center gap-3">
                    <a href="{{ route('login') }}" class="flex-1 text-center py-2 text-xs font-bold text-slate-300 hover:text-white bg-white/5 rounded-xl">
                        Log In
                    </a>
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="flex-1 text-center py-2 bg-gradient-to-r from-indigo-600 to-blue-600 text-white text-xs font-bold rounded-xl shadow-md">
                            Register ⚡
                        </a>
                    @endif
                </div>
            @endauth
        </div>
    </div>
</nav>
