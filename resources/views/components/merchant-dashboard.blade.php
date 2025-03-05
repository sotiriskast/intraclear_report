@props(['merchantId' => null])

<div id="merchant-dashboard-root" data-merchant-id="{{ $merchantId }}">
    <!-- React component will be mounted here -->
    <div class="flex justify-center items-center py-12">
        <svg class="animate-spin h-12 w-12 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <p class="ml-3 text-lg font-medium">Loading dashboard...</p>
    </div>
</div>

@pushonce('scripts')
    @vite(['resources/js/merchant-dashboard.jsx'])
@endpushonce
