<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailables\{Attachment,Content,Envelope};
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
/**
 * Mailable class for sending settlement report generation notifications
 *
 * This class handles:
 * - Sending email notifications when settlement reports are generated
 * - Attaching ZIP files of generated reports
 * - Providing report generation details to recipients
 *
 * @property string $zipPath Path to the ZIP file containing reports
 * @property array $dateRange Date range for the settlement period
 * @property int $fileCount Number of files included in the ZIP
 */
class SettlementReportGenerated extends Mailable
{
    use Queueable, SerializesModels;


    /**
     * Create a new message instance
     *
     * @param string $zipPath Path to the generated ZIP file
     * @param array $dateRange Date range ['start' => 'Y-m-d', 'end' => 'Y-m-d']
     * @param int $fileCount Number of files in the ZIP
     */
    public function __construct(public $zipPath,
                                public $dateRange,
                                public $fileCount)
    {
        //
    }

    /**
     * Get the message envelope
     *
     * @return Envelope
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Settlement Report Generated',
        );
    }

    /**
     * Get the message content definition
     *
     * @return Content
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.settlements.generated',
        );
    }

    /**
     * Get the attachments for the message
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        try {
            if (!Storage::exists($this->zipPath)) {
                throw new \Exception("ZIP file not found in storage: {$this->zipPath}");
            }

            // Get the file contents from storage
            $contents = Storage::get($this->zipPath);
            $filename = basename($this->zipPath);

            return [
                Attachment::fromData(
                    fn () => $contents,
                    $filename
                )->withMime('application/zip')
            ];
        } catch (\Exception $e) {
            \Log::error('Failed to attach settlement report ZIP', [
                'error' => $e->getMessage(),
                'path' => $this->zipPath
            ]);

            // Return empty array to send email without attachment
            return [];
        }
    }
}
