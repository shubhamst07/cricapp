@props(['active'])

@php
$classes = ($active ?? false)
            ? 'flex items-center justify-between w-full px-3.5 py-2.5 rounded-xl text-sm font-bold text-indigo-600 dark:text-white bg-indigo-50 dark:bg-indigo-600/20 border border-indigo-200/70 dark:border-indigo-500/30 transition duration-150 focus:outline-none'
            : 'flex items-center justify-between w-full px-3.5 py-2.5 rounded-xl text-sm font-semibold text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-white/[0.06] border border-transparent transition duration-150 focus:outline-none';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
