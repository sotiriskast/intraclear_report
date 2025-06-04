<?php

namespace Modules\Decta\Services;

use Illuminate\Mail\Mailable;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;

class ErrorNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $errorMessage;
    public $errorDetails;
    public $emailSubject;

    public function __construct(string $subject, string $message, array $details = [])
    {
        $this->emailSubject = $subject;
        $this->errorMessage = $message;
        $this->errorDetails = $details;
    }

    public function build()
    {
        return $this->subject($this->emailSubject)
            ->view('decta::emails.error-notification')
            ->with([
                'errorMessage' => $this->errorMessage,
                'errorDetails' => $this->errorDetails,
                'subject' => $this->emailSubject
            ]);
    }
}
