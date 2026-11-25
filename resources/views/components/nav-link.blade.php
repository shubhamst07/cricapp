@props(['active'])

@php
$isActive = (bool) ($active ?? false);
$classes = $isActive
    ? 'relative inline-flex items-center justify-center whitespace-nowrap px-3 xl:px-3.5 py-2 rounded-xl text-[13px] font-bold leading-none bg-white dark:bg-gradient-to-b dark:from-indigo-500 dark:to-indigo-600 text-indigo-600 dark:text-white shadow-sm border border-slate-200/80 dark:border-indigo-400/40 transition-all duration-150 focus:outline-none'
    : 'relative inline-flex items-center justify-center whitespace-nowrap px-3 xl:px-3.5 py-2 rounded-xl text-[13px] font-semibold leading-none text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-white/75 dark:hover:bg-white/[0.06] border border-transparent transition-all duration-150 focus:outline-none';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    <span>{{ $slot }}</span>
    @if($isActive)
        <span class="absolute -bottom-[5px] left-1/2 -translate-x-1/2 w-5 h-[3px] rounded-full bg-gradient-to-r from-emerald-400 via-teal-400 to-amber-400 shadow-sm"></span>
    @endif
</a>
