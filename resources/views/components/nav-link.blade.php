@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center justify-center whitespace-nowrap px-3 py-2 rounded-lg text-[13px] font-bold leading-none bg-white dark:bg-indigo-600 text-indigo-600 dark:text-white shadow-sm transition-all duration-150 focus:outline-none'
            : 'inline-flex items-center justify-center whitespace-nowrap px-3 py-2 rounded-lg text-[13px] font-semibold leading-none text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-white/70 dark:hover:bg-white/[0.06] transition-all duration-150 focus:outline-none';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
