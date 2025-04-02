@component('mail::message')
    # ALERT: Missing Scheme Rates

    <div style="background-color: #fff3cd; border-left: 5px solid #ffc107; padding: 15px; margin-bottom: 20px;">
        <strong>Warning:</strong> There are missing scheme rates for the settlement period
        <strong>{{ \Carbon\Carbon::parse($dateRange['start'])->format('Y-m-d') }}</strong> to
        <strong>{{ \Carbon\Carbon::parse($dateRange['end'])->format('Y-m-d') }}</strong>.
    </div>

    @foreach($missingRates as $currency => $brandData)
        <div style="margin-bottom: 25px;">
            <h2 style="color: #3490dc; font-size: 18px; margin-bottom: 10px; border-bottom: 1px solid #e2e8f0; padding-bottom: 5px;">
                {{ $currency }} Currency
            </h2>

            @foreach($brandData as $brand => $dates)
                <h3 style="color: #2d3748; font-size: 16px; margin-top: 15px; margin-bottom: 10px;">
                    {{ $brand }}
                </h3>

                <p>The following dates are missing exchange rates:</p>

                <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 10px; margin-bottom: 15px;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                        <tr>
                            <th style="text-align: left; padding: 8px; border-bottom: 2px solid #e2e8f0; color: #4a5568;">Date</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($dates as $date)
                            <tr>
                                <td style="text-align: left; padding: 8px; border-bottom: 1px solid #e2e8f0;">{{ $date }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
        </div>
    @endforeach

    <div style="background-color: #e8f4fd; border: 1px solid #bee3f8; border-radius: 4px; padding: 15px; margin-top: 20px;">
        <strong>Action Required:</strong> Please add the missing rates to ensure accurate settlement calculations.
    </div>

    @component('mail::button', ['url' => config('app.url'), 'color' => 'primary'])
        Go to Application
    @endcomponent

    Thank you,<br>
    {{ config('app.name') }}
@endcomponent
