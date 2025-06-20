<?php

namespace Modules\Decta\Console;

use Illuminate\Console\Command;
use Modules\Decta\Services\VisaSmsService;
use Modules\Decta\Models\DectaFile;
use Carbon\Carbon;
use Exception;

class VisaSmsStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'visa:status
                            {--days=30 : Number of days to analyze}
                            {--detailed : Show detailed file information}
                            {--recent : Show only recent files (last 7 days)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display Visa SMS processing status and statistics';

    /**
     * Visa SMS Service
     */
    protected VisaSmsService $visaSmsService;

    /**
     * Create a new command instance.
     */
    public function __construct(VisaSmsService $visaSmsService)
    {
        parent::__construct();
        $this->visaSmsService = $visaSmsService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $days = $this->option('recent') ? 7 : (int) $this->option('days');
            $detailed = $this->option('detailed');

            $this->info('📊 Visa SMS Processing Status Report');
            $this->line('===================================');
            $this->line('Generated at: ' . Carbon::now()->format('Y-m-d H:i:s'));
            $this->line('Period: Last ' . $days . ' days');
            $this->line('');

            // Get processing statistics
            $stats = $this->visaSmsService->getProcessingStats(['days' => $days]);
            $this->displayStatistics($stats);

            // Get recent files
            $recentFiles = $this->getRecentFiles($days);
            $this->displayRecentFiles($recentFiles, $detailed);

            // Show configuration
            if ($detailed) {
                $this->displayConfiguration();
            }

            // Show recommendations
            $this->displayRecommendations($stats, $recentFiles);

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
     * Display recent files
     */
    protected function displayRecentFiles(array $files, bool $detailed): void
    {
        $this->info('📁 Recent Files');
        $this->line('===============');

        if (empty($files)) {
            $this->line('No Visa SMS files found in the specified period.');
            $this->line('');
            return;
        }

        $tableHeaders = ['Filename', 'Status', 'Processed At'];
        if ($detailed) {
            $tableHeaders = array_merge($tableHeaders, ['Updated', 'Not Found', 'Errors', 'File Size']);
        }

        $tableData = [];
        foreach ($files as $file) {
            $status = $this->getStatusIcon($file->status);
            $processedAt = $file->processed_at ? $file->processed_at->format('M j, H:i') : '-';

            $row = [
                $file->filename,
                $status,
                $processedAt
            ];

            if ($detailed) {
                $metadata = $file->metadata['visa_sms_processing'] ?? [];
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

        $config = $this->visaSmsService->getConfig();

        $configData = [
            ['Remote Path', $config['sftp']['remote_path'] ?? 'N/A'],
            ['Local Path', $config['sftp']['local_path'] ?? 'N/A'],
            ['File Prefix', $config['sftp']['file_prefix'] ?? 'N/A'],
            ['File Extension', $config['sftp']['file_extension'] ?? 'N/A'],
            ['Auto Download', $config['scheduling']['auto_download'] ? 'Enabled' : 'Disabled'],
            ['Auto Process', $config['scheduling']['auto_process'] ? 'Enabled' : 'Disabled'],
            ['Download Schedule', $config['scheduling']['download_schedule'] ?? 'N/A'],
            ['Target Field', $config['processing']['matching']['target_field'] ?? 'N/A'],
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
            $recommendations[] = "⚠️  You have {$stats['failed_files']} failed files. Run 'visa:process-sms-reports --status=failed' to retry.";
        }

        // Check for pending files
        if ($stats['pending_files'] > 0) {
            $recommendations[] = "📋 You have {$stats['pending_files']} pending files. Run 'visa:process-sms-reports' to process them.";
        }

        // Check match rate
        $totalTransactions = $stats['total_transactions_updated'] + $stats['total_transactions_not_found'];
        if ($totalTransactions > 0) {
            $matchRate = ($stats['total_transactions_updated'] / $totalTransactions) * 100;
            if ($matchRate < 80) {
                $recommendations[] = "📉 Low transaction match rate ({$matchRate}%). Check if payment IDs in CSV match your transaction database.";
            }
        }

        // Check for missing recent files
        $expectedFiles = $this->getExpectedRecentFiles();
        $actualFiles = array_map(function ($file) {
            return $file->filename;
        }, $files);

        $missingFiles = array_diff($expectedFiles, $actualFiles);
        if (!empty($missingFiles)) {
            $count = count($missingFiles);
            $recommendations[] = "📅 {$count} expected files are missing from recent downloads. Consider running manual download.";
        }

        // Check processing errors
        if ($stats['total_errors'] > 0) {
            $recommendations[] = "🔍 Processing errors detected. Check application logs for details.";
        }

        if (empty($recommendations)) {
            $this->line('✅ Everything looks good! No recommendations at this time.');
        } else {
            foreach ($recommendations as $recommendation) {
                $this->line($recommendation);
            }
        }

        $this->line('');
    }

    /**
     * Get recent files
     */
    protected function getRecentFiles(int $days): array
    {
        $since = Carbon::now()->subDays($days);

        return DectaFile::where(function ($query) {
            $query->where('file_type', 'visa_sms_csv')
                ->orWhere('filename', 'LIKE', 'INTCL_visa_sms_tr_det_%');
        })
            ->where('created_at', '>=', $since)
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Get expected recent files (for missing file detection)
     */
    protected function getExpectedRecentFiles(): array
    {
        $expectedFiles = [];
        $config = $this->visaSmsService->getConfig();
        $prefix = $config['sftp']['file_prefix'] ?? 'INTCL_visa_sms_tr_det_';
        $extension = $config['sftp']['file_extension'] ?? '.csv';

        // Check for files from the last 7 days (excluding today since files are 1 day behind)
        for ($i = 1; $i <= 7; $i++) {
            $date = Carbon::now()->subDays($i);
            $expectedFiles[] = $prefix . $date->format('Ymd') . $extension;
        }

        return $expectedFiles;
    }

    /**
     * Get status icon for file status
     */
    protected function getStatusIcon(string $status): string
    {
        return match ($status) {
            VisaSmsService::STATUS_VISA_PENDING => '⏳ Pending',
            VisaSmsService::STATUS_VISA_PROCESSING => '🔄 Processing',
            VisaSmsService::STATUS_VISA_PROCESSED => '✅ Processed',
            VisaSmsService::STATUS_VISA_FAILED => '❌ Failed',
            // Fallback for old statuses that might still exist
            DectaFile::STATUS_PENDING => '⏳ Pending (Legacy)',
            DectaFile::STATUS_PROCESSING => '🔄 Processing (Legacy)',
            DectaFile::STATUS_PROCESSED => '✅ Processed (Legacy)',
            DectaFile::STATUS_FAILED => '❌ Failed (Legacy)',
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
