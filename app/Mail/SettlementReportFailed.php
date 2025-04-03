<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SettlementReportFailed extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The job details.
     *
     * @var array
     */
    public $jobDetails;

    /**
     * Create a new message instance.
     *
     * @param  array  $jobDetails
     * @return void
     */
    public function __construct(array $jobDetails)
    {
        $this->jobDetails = $jobDetails;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $attempt = $this->jobDetails['attempts'];
        $maxTries = 3; // Match the $tries property in the job

        return $this->subject('Settlement Report Generation Failed - Attempt ' . $attempt . ' of ' . $maxTries)
            ->markdown('emails.settlement.settlements-report-failed')
            ->with([
                'jobDetails' => $this->jobDetails,
                'nextRetry' => $attempt < $maxTries ? 'Job will retry in approximately 24 hours.' : 'No more retries scheduled.',
            ]);
    }
}
