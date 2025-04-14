@props(['active'])

@php
    $classes = ($active ?? false)
                ? 'block w-full ps-3 pe-4 py-2 border-l-4 border-indigo-400 text-start text-base font-medium text-indigo-700 bg-indigo-50 focus-visible:outline-none focus-visible:text-indigo-800 focus-visible:bg-indigo-100 focus-visible:border-indigo-700 transition duration-300 ease-in-out'
                : 'block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-zinc-600 hover:text-zinc-800 hover:bg-zinc-50 hover:border-zinc-300 focus-visible:outline-none focus-visible:text-zinc-800 focus-visible:bg-zinc-50 focus-visible:border-zinc-300 transition duration-300 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
