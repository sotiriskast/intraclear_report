<?php

namespace Modules\Decta\Console;

use Illuminate\Console\Command;
use Modules\Decta\Services\VisaIssuesService;
use Modules\Decta\Models\DectaFile;
use Carbon\Carbon;
use Exception;

class VisaIssuesStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'visa:issues-status
                            {--days=90 : Number of days to analyze}
                            {--detailed : Show detailed file information}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display Visa Issues processing status and statistics';

    /**
     * Visa Issues Service
     */
    protected VisaIssuesService $visaIssuesService;

    /**
     * Create a new command instance.
     */
    public function __construct(VisaIssuesService $visaIssuesService)
    {
        parent::__construct();
        $this->visaIssuesService = $visaIssuesService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $days = (int) $this->option('days');
            $detailed = $this->option('detailed');

            $this->info('📊 Visa Issues Processing Status Report');
            $this->line('=====================================');
            $this->line('Generated at: ' . Carbon::now()->format('Y-m-d H:i:s'));
            $this->line('Period: Last ' . $days . ' days');
            $this->line('');

            // Get processing statistics
            $stats = $this->visaIssuesService->getProcessingStats(['days' => $days]);
            $this->displayStatistics($stats);

            // Get files
            $files = $this->getFiles($days);
            $this->displayFiles($files, $detailed);

            // Show configuration
            if ($detailed) {
                $this->displayConfiguration();
            }

            // Show recommendations
            $this->displayRecommendations($stats, $files);

            return Command::SUCCESS;

        } catch (Exception $e) {
            $this->error("💥 Error generating status report: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Display processing statistics
     */
    protected function displayStatistics(array $stats): void
    {
        $this->info('📈 Processing Statistics');
        $this->line('========================');

        $tableData = [
            ['Total Files', $stats['total_files']],
            ['Pending Files', $stats['pending_files']],
            ['Processed Files', $stats['processed_files']],
            ['Failed Files', $stats['failed_files']],
            ['Transactions Updated', number_format($stats['total_transactions_updated'])],
            ['Transactions Not Found', number_format($stats['total_transactions_not_found'])],
            ['Processing Errors', number_format($stats['total_errors'])],
        ];

        // Calculate success rate
        if ($stats['total_files'] > 0) {
            $successRate = round(($stats['processed_files'] / $stats['total_files']) * 100, 2);
            $tableData[] = ['Success Rate', $successRate . '%'];
        }

        // Calculate match rate
        $totalTransactions = $stats['total_transactions_updated'] + $stats['total_transactions_not_found'];
        if ($totalTransactions > 0) {
            $matchRate = round(($stats['total_transactions_updated'] / $totalTransactions) * 100, 2);
            $tableData[] = ['Transaction Match Rate', $matchRate . '%'];
        }

        $this->table(['Metric', 'Value'], $tableData);
        $this->line('');
    }

    /**
     * Display files
     */
    protected function displayFiles(array $files, bool $detailed): void
    {
        $this->info('📁 Processed Files');
        $this->line('==================');

        if (empty($files)) {
            $this->line('No Visa Issues files found in the specified period.');
            $this->line('');
            $this->info('💡 Get started:');
            $this->line('• List available files: php artisan visa:download-issues-reports --list');
            $this->line('• Download file: php artisan visa:download-issues-reports FILENAME');
            return;
        }

        $tableHeaders = ['Period', 'Status', 'Processed At'];
        if ($detailed) {
            $tableHeaders = array_merge($tableHeaders, ['Updated', 'Not Found', 'Errors', 'File Size']);
        }

        $tableData = [];
        foreach ($files as $file) {
            $status = $this->getStatusIcon($file->status);
            $processedAt = $file->processed_at ? $file->processed_at->format('M j, H:i') : '-';

            // Extract period from filename or metadata
            $period = 'Unknown';
            if (isset($file->metadata['date_range']['period'])) {
                $period = $file->metadata['date_range']['period'];
            } elseif (preg_match('/(\d{8})-(\d{8})/', $file->filename, $matches)) {
                try {
                    $start = Carbon::createFromFormat('Ymd', $matches[1]);
                    $end = Carbon::createFromFormat('Ymd', $matches[2]);
                    $period = $start->format('M j') . ' - ' . $end->format('M j, Y');
                } catch (Exception $e) {
                    $period = $matches[1] . '-' . $matches[2];
                }
            }

            $row = [
                $period,
                $status,
                $processedAt
            ];

            if ($detailed) {
                $metadata = $file->metadata['visa_issues_processing'] ?? [];
                $row = array_merge($row, [
                    number_format($metadata['transactions_updated'] ?? 0),
                    number_format($metadata['transactions_not_found'] ?? 0),
                    number_format($metadata['errors'] ?? 0),
                    $this->formatBytes($file->file_size ?? 0)
                ]);
            }

            $tableData[] = $row;
        }

        $this->table($tableHeaders, $tableData);
        $this->line('');
    }

    /**
     * Display configuration
     */
    protected function displayConfiguration(): void
    {
        $this->info('⚙️ Configuration');
        $this->line('================');

        $config = $this->visaIssuesService->getConfig();

        $configData = [
            ['Remote Path', $config['sftp']['remote_path'] ?? 'N/A'],
            ['File Pattern', $config['sftp']['file_pattern'] ?? 'N/A'],
            ['Target Field', $config['processing']['matching']['target_field'] ?? 'N/A'],
            ['CSV Delimiter', $config['processing']['csv_delimiter'] ?? 'N/A'],
        ];

        $this->table(['Setting', 'Value'], $configData);
        $this->line('');
    }

    /**
     * Display recommendations
     */
    protected function displayRecommendations(array $stats, array $files): void
    {
        $this->info('💡 Recommendations');
        $this->line('==================');

        $recommendations = [];

        // Check for failed files
        if ($stats['failed_files'] > 0) {
            $recommendations[] = "⚠️  You have {$stats['failed_files']} failed files. Run 'visa:process-issues-reports --status=failed' to retry.";
        }

        // Check for pending files
        if ($stats['pending_files'] > 0) {
            $recommendations[] = "📋 You have {$stats['pending_files']} pending files. Run 'visa:process-issues-reports' to process them.";
        }

        // Check match rate
        $totalTransactions = $stats['total_transactions_updated'] + $stats['total_transactions_not_found'];
        if ($totalTransactions > 0) {
            $matchRate = ($stats['total_transactions_updated'] / $totalTransactions) * 100;
            if ($matchRate < 80) {
                $recommendations[] = "📉 Low transaction match rate ({$matchRate}%). Check if payment IDs in CSV match your transaction database.";
            }
        }

        // Check processing errors
        if ($stats['total_errors'] > 0) {
            $recommendations[] = "🔍 Processing errors detected. Check application logs for details.";
        }

        // Check if no files processed recently
        if (empty($files)) {
            $recommendations[] = "📂 No files found. Check available files with 'visa:download-issues-reports --list'.";
        }

        if (empty($recommendations)) {
            $this->line('✅ Everything looks good! No recommendations at this time.');
        } else {
            foreach ($recommendations as $recommendation) {
                $this->line($recommendation);
            }
        }

        $this->line('');
        $this->info('🔧 Useful Commands:');
        $this->line('• List available files: php artisan visa:download-issues-reports --list');
        $this->line('• Download file: php artisan visa:download-issues-reports FILENAME');
        $this->line('• Process files: php artisan visa:process-issues-reports');
        $this->line('• Download and process: php artisan visa:download-issues-reports FILENAME --process-immediately');
    }

    /**
     * Get files
     */
    protected function getFiles(int $days): array
    {
        $since = Carbon::now()->subDays($days);

        return DectaFile::where(function ($query) {
            $query->where('file_type', 'visa_issues_csv')
                ->orWhere('filename', 'LIKE', 'INTCL_visa_sms_tr_det_%-%');
        })
            ->where('created_at', '>=', $since)
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Get status icon for file status
     */
    protected function getStatusIcon(string $status): string
    {
        return match ($status) {
            VisaIssuesService::STATUS_ISSUES_PENDING => '⏳ Pending',
            VisaIssuesService::STATUS_ISSUES_PROCESSING => '🔄 Processing',
            VisaIssuesService::STATUS_ISSUES_PROCESSED => '✅ Processed',
            VisaIssuesService::STATUS_ISSUES_FAILED => '❌ Failed',
            default => '❓ Unknown'
        };
    }

    /**
     * Format bytes to human readable format
     */
    protected function formatBytes(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $pow = floor(log($bytes) / log(1024));
        $pow = min($pow, count($units) - 1);

        return round($bytes / pow(1024, $pow), 2) . ' ' . $units[$pow];
    }
}
