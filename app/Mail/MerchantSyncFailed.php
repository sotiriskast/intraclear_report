<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MerchantSyncFailed extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The error message.
     *
     * @var string
     */
    public $errorMessage;

    /**
     * The error stack trace.
     *
     * @var string
     */
    public $stackTrace;

    /**
     * Create a new message instance.
     *
     * @param string $errorMessage
     * @param string $stackTrace
     * @return void
     */
    public function __construct(string $errorMessage, string $stackTrace)
    {
        $this->errorMessage = $errorMessage;
        $this->stackTrace = $stackTrace;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('🚨 ALERT: Merchant Sync Failed')
            ->markdown('emails.settlements.merchant-sync-failed')
            ->with([
                'errorMessage' => $this->errorMessage,
                'stackTrace' => $this->stackTrace,
                'timestamp' => now()->format('Y-m-d H:i:s'),
                'environment' => app()->environment(),
            ]);
    }
}
