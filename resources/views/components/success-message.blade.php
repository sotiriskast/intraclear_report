@props(['message'])

<div x-data="{ show: true }"
     x-show="show"
     x-init="setTimeout(() => show = false, 3000)"
    {{ $attributes->merge(['class' => 'fixed bottom-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg']) }}>
    {{ $message }}
</div>
