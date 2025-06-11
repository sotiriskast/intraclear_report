<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;

class S3HealthCheck extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'storage:s3-check {--clear-cache : Clear AWS credentials cache}';

    /**
     * The console command description.
     */
    protected $description = 'Check S3 storage configuration and connectivity';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('clear-cache')) {
            $this->info('Clearing AWS credentials cache...');
            Cache::forget('aws_temporary_credentials_' . md5(env('AWS_ROLE_ARN')));
        }

        $this->info('Checking S3 configuration...');

        // Check environment variables
        $this->checkEnvironmentVariables();

        // Test S3 connectivity
        $this->testS3Connectivity();

        $this->info('S3 health check completed!');
    }

    private function checkEnvironmentVariables()
    {
        $required = [
            'AWS_ACCESS_KEY_ID' => config('filesystems.disks.s3.key'),
            'AWS_SECRET_ACCESS_KEY' => config('filesystems.disks.s3.secret'),
            'AWS_DEFAULT_REGION' => config('filesystems.disks.s3.region'),
            'AWS_BUCKET' => config('filesystems.disks.s3.bucket'),
            'AWS_ROLE_ARN' => config('filesystems.disks.s3.role_arn')
        ];

        $this->line('Configuration Values:');
        foreach ($required as $name => $value) {
            $status = $value ? '✓' : '✗';
            $display = $value ? (strlen($value) > 20 ? substr($value, 0, 10) . '...' : $value) : 'NOT SET';
            $this->line("  {$status} {$name}: {$display}");
        }

        // Also check if config is cached
        $configCached = app()->configurationIsCached();
        $this->line('');
        $this->line('Configuration Status:');
        $this->line('  ' . ($configCached ? '✓' : '✗') . ' Config cached: ' . ($configCached ? 'YES' : 'NO'));
    }

    private function testS3Connectivity()
    {
        $this->line('');
        $this->info('Testing S3 connectivity...');

        try {
            // Test basic connectivity
            $this->line('Testing S3 assume role disk...');
            $exists = Storage::disk('s3')->exists('health-check-' . time() . '.txt');
            $this->line('✓ S3 assume role disk accessible');

            // Test file operations
            $testFile = 'health-check-' . time() . '.txt';
            $testContent = 'Health check at ' . now();

            $this->line('Testing file upload...');
            Storage::disk('s3')->put($testFile, $testContent);
            $this->line('✓ File upload successful');

            $this->line('Testing file read...');
            $content = Storage::disk('s3')->get($testFile);
            if ($content === $testContent) {
                $this->line('✓ File read successful');
            } else {
                $this->error('✗ File content mismatch');
            }

            $this->line('Testing file deletion...');
            Storage::disk('s3')->delete($testFile);
            $this->line('✓ File deletion successful');

        } catch (\Exception $e) {
            $this->error('✗ S3 connectivity failed: ' . $e->getMessage());

            // Try fallback disk
            try {
                $this->line('Testing fallback S3 direct disk...');
                Storage::disk('s3-direct')->exists('test');
                $this->line('✓ Direct S3 access works - issue is with assume role');
            } catch (\Exception $directE) {
                $this->error('✗ Direct S3 access also failed: ' . $directE->getMessage());
            }
        }
    }
}
