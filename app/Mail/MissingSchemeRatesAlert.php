<?php

namespace App\Mail;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MissingSchemeRatesAlert extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The missing rates data.
     *
     * @var array
     */
    public $missingRates;

    /**
     * The date range being processed.
     *
     * @var array
     */
    public $dateRange;

    /**
     * Create a new message instance.
     *
     * @param array $missingRates
     * @param array $dateRange
     * @return void
     */
    public function __construct(array $missingRates, array $dateRange)
    {
        $this->missingRates = $missingRates;
        $this->dateRange = $dateRange;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $currencies = implode(', ', array_keys($this->missingRates));
        $dateRange = Carbon::parse($this->dateRange['start'])->format('M d') .
            ' - ' .
            Carbon::parse($this->dateRange['end'])->format('M d, Y');

        return $this->subject('🚨 ALERT: Missing Scheme Rates for ' . $currencies . ' (' . $dateRange . ')')
            ->view('emails.settlements.missing-scheme-rates-alert')
            ->with([
                'missingRates' => $this->missingRates,
                'dateRange' => $this->dateRange
            ]);
    }
}
