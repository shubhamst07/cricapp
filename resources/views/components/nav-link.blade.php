@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center px-1 pt-1 border-b-2 border-indigo-600 dark:border-indigo-400 text-sm font-extrabold leading-5 text-indigo-600 dark:text-white shadow-xs focus:outline-none transition duration-150 ease-in-out'
            : 'inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-bold leading-5 text-slate-600 hover:text-indigo-600 dark:text-slate-400 dark:hover:text-white hover:border-slate-300 dark:hover:border-slate-600 focus:outline-none transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
