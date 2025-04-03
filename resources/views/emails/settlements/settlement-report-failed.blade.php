@component('mail::message')
    # Settlement Report Generation Failed

    The settlement report generation job has failed.

    ## Job Details:
    **Job ID:** {{ $jobDetails['job_id'] }}
    **Attempt:** {{ $jobDetails['attempts'] }}
    **Date Range:** {{ $jobDetails['start_date'] }} to {{ $jobDetails['end_date'] }}
    @if($jobDetails['merchant_id'])
        **Merchant ID:** {{ $jobDetails['merchant_id'] }}
    @endif

    ## Error Information:
    {{ $jobDetails['error'] }}
    {{ $nextRetry }}

    @component('mail::button', ['url' => config('app.url')])
        Go to Application
    @endcomponent

    Thanks,<br>
    {{ config('app.name') }}
@endcomponent
