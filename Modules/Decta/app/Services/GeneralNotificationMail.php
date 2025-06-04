<?php
namespace Modules\Decta\Services;

use Illuminate\Mail\Mailable;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
class GeneralNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $message;
    public $data;
    public $emailSubject;

    public function __construct(string $subject, string $message, array $data = [])
    {
        $this->emailSubject = $subject;
        $this->message = $message;
        $this->data = $data;
    }

    public function build()
    {
        return $this->subject($this->emailSubject)
            ->view('decta::emails.general-notification')
            ->with([
                'message' => $this->message,
                'data' => $this->data,
                'subject' => $this->emailSubject
            ]);
    }
}
