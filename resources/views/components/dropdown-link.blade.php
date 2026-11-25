@props(['active' => false])

@php
$classes = ($active ?? false)
    ? 'block w-full px-4 py-2 text-start text-xs font-bold leading-5 text-indigo-600 dark:text-indigo-300 bg-indigo-50/70 dark:bg-indigo-500/15 focus:outline-none transition duration-150 ease-in-out whitespace-nowrap'
    : 'block w-full px-4 py-2 text-start text-xs font-semibold leading-5 text-slate-700 dark:text-slate-200 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-white/[0.08] focus:outline-none transition duration-150 ease-in-out whitespace-nowrap';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
