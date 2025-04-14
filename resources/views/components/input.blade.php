@props(['disabled' => false])

<input {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => 'border-zinc-300 ring-1 ring-inset ring-zinc-300 focus-visible:border-indigo-500 focus-visible:ring-indigo-500 rounded-md shadow-sm']) !!}>
