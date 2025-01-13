@props(['active'])

@php
    $classes = ($active ?? false)
        ? 'flex items-center px-2 py-2 text-sm font-medium rounded-md text-white bg-indigo-800 group'
        : 'flex items-center px-2 py-2 text-sm font-medium rounded-md text-indigo-100 hover:text-white hover:bg-indigo-600 group';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
