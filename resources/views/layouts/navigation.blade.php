<nav x-data="{ open: false }" class="bg-white border-b border-gray-100 shadow-xs">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('live-scores.index') }}" class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-indigo-600 to-indigo-500 flex items-center justify-center text-white shadow-sm">
                            <svg class="w-5 h-5 fill-none stroke-current" stroke-width="2" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="10" />
                                <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" />
                                <path d="M2 12h20" />
                            </svg>
                        </div>
                        <span class="font-black text-xl text-gray-900 tracking-tight">LiveCricket</span>
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('live-scores.index')" :active="request()->routeIs('live-scores.*') || request()->routeIs('home')">
                        {{ __('Live Scores') }}
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
                            <span>🏏 {{ __('My Matches') }}</span>
                        </span>
                    </x-nav-link>
                    @else
                    <x-nav-link :href="route('matches.index')" :active="request()->routeIs('matches.*')">
                        <span class="inline-flex items-center gap-1.5 font-semibold">
                            <span>🏏 {{ __('Matches') }}</span>
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
                            <button class="inline-flex items-center px-3 py-2 border border-gray-200 text-sm leading-4 font-semibold rounded-xl text-gray-700 bg-white hover:bg-gray-50 focus:outline-none transition ease-in-out duration-150">
                                <div>{{ Auth::user()->name }}</div>

                                <div class="ms-1">
                                    <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            <x-dropdown-link :href="route('profile.show')">
                                {{ __('My Profile') }}
                            </x-dropdown-link>
                            <x-dropdown-link :href="route('profile.edit')">
                                {{ __('Account Settings') }}
                            </x-dropdown-link>

                            <!-- Authentication -->
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf

                                <x-dropdown-link :href="route('logout')"
                                        onclick="event.preventDefault();
                                                    this.closest('form').submit();">
                                    {{ __('Log Out') }}
                                </x-dropdown-link>
                            </form>
                        </x-slot>
                    </x-dropdown>
                @else
                    <!-- Guest Links: Login & Register -->
                    <div class="flex items-center gap-2">
                        <a href="{{ route('login') }}" class="text-xs font-bold text-gray-700 hover:text-indigo-600 px-3 py-2 rounded-lg hover:bg-gray-50 transition">
                            Log In
                        </a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="inline-flex items-center px-3.5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-xs transition">
                                Register
                            </a>
                        @endif
                    </div>
                @endauth
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('live-scores.index')" :active="request()->routeIs('live-scores.*') || request()->routeIs('home')">
                {{ __('Live Scores') }}
            </x-responsive-nav-link>

            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                <span class="inline-flex items-center gap-2 font-bold">
                    <span class="w-2 h-2 rounded-full bg-rose-500 animate-pulse"></span>
                    <span>{{ __('Cricket Feed & Memes') }}</span>
                </span>
            </x-responsive-nav-link>

            @auth
            <x-responsive-nav-link :href="route('matches.index')" :active="request()->routeIs('matches.*')">
                {{ __('🏏 My Matches') }}
            </x-responsive-nav-link>
            @else
            <x-responsive-nav-link :href="route('matches.index')" :active="request()->routeIs('matches.*')">
                {{ __('🏏 Matches') }}
            </x-responsive-nav-link>
            @endauth

            <x-responsive-nav-link :href="route('grounds.index')" :active="request()->routeIs('grounds.*')">
                {{ __('🏟️ Grounds') }}
            </x-responsive-nav-link>

            <x-responsive-nav-link :href="route('umpires.index')" :active="request()->routeIs('umpires.*')">
                {{ __('⚖️ Umpires') }}
            </x-responsive-nav-link>
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-3 border-t border-gray-200">
            @auth
                <div class="px-4">
                    <div class="font-medium text-base text-gray-800">{{ Auth::user()->name }}</div>
                    <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
                </div>

                <div class="mt-3 space-y-1">
                    <x-responsive-nav-link :href="route('profile.show')">
                        {{ __('My Profile') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('profile.edit')">
                        {{ __('Account Settings') }}
                    </x-responsive-nav-link>

                    <!-- Authentication -->
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf

                        <x-responsive-nav-link :href="route('logout')"
                                onclick="event.preventDefault();
                                            this.closest('form').submit();">
                            {{ __('Log Out') }}
                        </x-responsive-nav-link>
                    </form>
                </div>
            @else
                <div class="px-4 flex items-center gap-3">
                    <a href="{{ route('login') }}" class="text-sm font-semibold text-gray-700 hover:text-indigo-600 py-2">
                        Log In
                    </a>
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="px-4 py-2 bg-indigo-600 text-white text-xs font-bold rounded-lg shadow-xs">
                            Register
                        </a>
                    @endif
                </div>
            @endauth
        </div>
    </div>
</nav>
