<?php
namespace Modules\Decta\Services;

use Illuminate\Mail\Mailable;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
class DeclinedTransactionsMail extends Mailable
{
    use Queueable, SerializesModels;

    public $summaryData;
    public $emailSubject;

    public function __construct(string $subject, array $summaryData)
    {
        $this->emailSubject = $subject;
        $this->summaryData = $summaryData;
    }

    public function build()
    {
        return $this->subject($this->emailSubject)
            ->view('decta::emails.declined-transactions')
            ->with([
                'summaryData' => $this->summaryData,
                'subject' => $this->emailSubject
            ]);
    }
}
