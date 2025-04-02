@component('mail::message')
    # Missing Scheme Rates Alert

    There are missing scheme rates for the following currencies during the settlement period
    **{{ \Carbon\Carbon::parse($dateRange['start'])->format('Y-m-d') }}** to
    **{{ \Carbon\Carbon::parse($dateRange['end'])->format('Y-m-d') }}**.

    @foreach($missingRates as $currency => $brandData)
        ## {{ $currency }}

        @foreach($brandData as $brand => $dates)
            ### {{ $brand }}

            The following dates are missing exchange rates:

            @component('mail::table')
                | Date |
                |------|
                @foreach($dates as $date)
                    | {{ $date }} |
                @endforeach
            @endcomponent

        @endforeach
    @endforeach

    Please add the missing rates to ensure accurate settlement calculations.

    @component('mail::button', ['url' => config('app.url')])
        Go to Application
    @endcomponent

    Thanks,<br>
    {{ config('app.name') }}
@endcomponent
