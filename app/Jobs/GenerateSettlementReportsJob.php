<?php

namespace App\Jobs;

use App\Mail\SettlementReportFailed;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;

class GenerateSettlementReportsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $startDate;
    protected $endDate;
    protected $merchantId;
    protected $currency;
    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 86400; // 24 hours = 86400 seconds

    public function __construct($startDate, $endDate, $merchantId = null, $currency = null)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->merchantId = $merchantId;
        $this->currency = $currency;
    }

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 3600;

    public function handle(): void
    {
        $command = 'intraclear:settlement-generate';
        $parameters = [];
        if ($this->startDate) {
            $parameters['--start-date'] = Carbon::parse($this->startDate)->format('Y-m-d');
        }

        if ($this->endDate) {
            $parameters['--end-date'] = Carbon::parse($this->endDate)->format('Y-m-d');
        }

        if ($this->merchantId) {
            $parameters['--merchant-id'] = $this->merchantId;
        }
        Artisan::call($command, $parameters);

    }
    /**
     * Handle a job failure.
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function failed(\Throwable $exception): void
    {
        // Get recipients from config
        $recipients = collect(config('settlement.report_recipients', []))
            ->filter()
            ->values();

        if ($recipients->isNotEmpty()) {
            // Send email notification about the failure
            $jobDetails = [
                'job_id' => $this->job->getJobId(),
                'attempts' => $this->attempts(),
                'start_date' => $this->startDate,
                'end_date' => $this->endDate,
                'merchant_id' => $this->merchantId,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ];

            foreach ($recipients as $recipient) {
                Mail::to($recipient)->send(new SettlementReportFailed($jobDetails));
            }
        }
    }
}
