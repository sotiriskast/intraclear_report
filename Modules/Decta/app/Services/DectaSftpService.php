<?php

namespace Modules\Decta\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Exception;

class DectaSftpService
{
    /**
     * @var array
     */
    protected $config;

    /**
     * @var string
     */
    protected $diskName;

    /**
     * Initialize the SFTP service
     */
    public function __construct()
    {
        $this->config = config('decta.sftp');
        $this->diskName = 'local'; // Use the local disk for storing downloaded files
    }

    /**
     * List all files in the remote directory using SFTP
     *
     * @param string $directory Remote directory path
     * @param bool $recursive Whether to list files recursively
     * @return array List of files
     */
    public function listFiles(string $directory = '', bool $recursive = false): array
    {
        try {
            $tempScript = tempnam(sys_get_temp_dir(), 'sftp_script');
            $listCommand = $directory ? "ls -la {$directory}" : "ls -la";
            file_put_contents($tempScript, "{$listCommand}\nquit\n");

            $command = $this->buildSftpCommand($tempScript);

            exec($command, $output, $returnCode);
            unlink($tempScript);

            if ($returnCode !== 0) {
                throw new Exception("SFTP command failed with code: $returnCode. Output: " . implode("\n", $output));
            }

            // Parse the directory listing output
            $files = [];
            foreach ($output as $line) {
                // Skip empty lines and SFTP prompts
                if (empty(trim($line)) || strpos($line, 'sftp>') === 0 || strpos($line, 'Connected to') === 0) {
                    continue;
                }

                // Parse the line (format example: "-rw-r--r--   1 user  group  12345 May 23 14:24 filename.txt")
                $parts = preg_split('/\s+/', trim($line), 9);
                if (count($parts) >= 9) {
                    $permissions = $parts[0];
                    $size = (int)$parts[4];
                    $name = $parts[8];

                    // Skip directories (first char in permissions is 'd')
                    if (substr($permissions, 0, 1) !== 'd' && $name !== '.' && $name !== '..') {
                        $extension = pathinfo($name, PATHINFO_EXTENSION);

                        // Filter by configured file extensions if they exist
                        if (empty(config('decta.files.extensions')) ||
                            in_array('.' . $extension, config('decta.files.extensions'))) {

                            $filePath = trim($directory . '/' . $name, '/');
                            $files[] = [
                                'type' => 'file',
                                'path' => $filePath,
                                'visibility' => 'public',
                                'lastModified' => time(), // Approximate
                                'fileSize' => $size
                            ];
                        }
                    }
                }
            }

            Log::info('SFTP files listed', [
                'directory' => $directory,
                'count' => count($files)
            ]);

            return $files;

        } catch (Exception $e) {
            Log::error('Failed to list SFTP files', [
                'directory' => $directory,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Download a file from SFTP to local storage
     *
     * @param string $remotePath Remote file path
     * @param string|null $localPath Local path to save the file
     * @return bool Whether the download was successful
     */
    public function downloadFile(string $remotePath, ?string $localPath = null): bool
    {
        try {
            // If no local path is provided, use the remote filename in the configured local path
            if ($localPath === null) {
                $filename = basename($remotePath);
                $localPath = $this->config['local_path'] . '/' . $filename;
            }

            // Get the full local path
            $fullLocalPath = storage_path('app/' . $localPath);

            // Create directory if it doesn't exist
            $directory = dirname($fullLocalPath);
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            // Create a temporary script file for SFTP
            $tempScript = tempnam(sys_get_temp_dir(), 'sftp_script');
            file_put_contents($tempScript, "get \"{$remotePath}\" \"{$fullLocalPath}\"\nquit\n");

            // Build and execute the SFTP command
            $command = $this->buildSftpCommand($tempScript);

            exec($command, $output, $returnCode);
            unlink($tempScript);

            if ($returnCode !== 0) {
                throw new Exception("SFTP command failed with code: $returnCode. Output: " . implode("\n", $output));
            }

            // Check if file was actually downloaded
            if (!file_exists($fullLocalPath)) {
                throw new Exception("File was not downloaded: {$remotePath}");
            }

            Log::info('SFTP file downloaded', [
                'remote_path' => $remotePath,
                'local_path' => $localPath,
                'size' => filesize($fullLocalPath)
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to download SFTP file', [
                'remote_path' => $remotePath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Build the SFTP command with proper authentication options
     *
     * @param string $batchFile Path to the batch file
     * @return string The complete SFTP command
     */
    protected function buildSftpCommand(string $batchFile): string
    {
        $options = [
            '-b ' . escapeshellarg($batchFile), // Batch file
            '-P ' . escapeshellarg($this->config['port']), // Port
            '-i ' . escapeshellarg($this->config['private_key_path']), // Identity file
        ];

        // Add IdentitiesOnly option if configured (equivalent to what worked before)
        if ($this->config['identities_only'] ?? true) {
            $options[] = '-o IdentitiesOnly=yes';
        }

        // Add other useful options
        $options[] = '-o StrictHostKeyChecking=no'; // Don't prompt for unknown hosts
        $options[] = '-o UserKnownHostsFile=/dev/null'; // Don't update known_hosts
        $options[] = '-o LogLevel=ERROR'; // Reduce output noise

        return sprintf(
            'sftp %s %s@%s',
            implode(' ', $options),
            escapeshellarg($this->config['username']),
            escapeshellarg($this->config['host'])
        );
    }

    /**
     * Find the latest file matching the expected pattern
     *
     * @param int $daysBack Number of days to look back
     * @return array|null Latest file info or null if not found
     */
    public function findLatestFile(int $daysBack = 7): ?array
    {
        try {
            $reportsDir = 'in_file/reports';

            // List all files in the reports directory
            $files = $this->listFiles($reportsDir);

            if (empty($files)) {
                Log::info('No files found in reports directory');
                return null;
            }

            // Expected file patterns with dates
            $patterns = [
                '/^INTCL_transact2_(\d{8})\.csv$/',
                '/^INTCL_transact_(\d{8})\.csv$/',
                '/^transact2_(\d{8})\.csv$/',
                '/^transact_(\d{8})\.csv$/',
            ];

            $matchingFiles = [];

            foreach ($files as $file) {
                $filename = basename($file['path']);

                // Check if file matches any of our patterns
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $filename, $matches)) {
                        $fileDate = $matches[1]; // YYYYMMDD from filename
                        $timestamp = strtotime($fileDate);

                        // Only consider files within our date range
                        $cutoffDate = strtotime("-{$daysBack} days");

                        if ($timestamp >= $cutoffDate) {
                            $matchingFiles[] = array_merge($file, [
                                'file_date' => $fileDate,
                                'timestamp' => $timestamp,
                                'pattern_matched' => $pattern,
                                'formatted_date' => date('Y-m-d', $timestamp),
                            ]);
                        }
                        break;
                    }
                }
            }

            if (empty($matchingFiles)) {
                Log::info('No matching files found within date range', ['days_back' => $daysBack]);
                return null;
            }

            // Sort by timestamp (latest first)
            usort($matchingFiles, function($a, $b) {
                return $b['timestamp'] - $a['timestamp'];
            });

            $latestFile = $matchingFiles[0];

            Log::info('Latest file found', [
                'filename' => basename($latestFile['path']),
                'date' => $latestFile['file_date'],
                'size' => $latestFile['fileSize']
            ]);

            return $latestFile;

        } catch (Exception $e) {
            Log::error('Error finding latest file', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Test the SFTP connection
     *
     * @return bool Whether the connection is successful
     */
    public function testConnection(): bool
    {
        try {
            $tempScript = tempnam(sys_get_temp_dir(), 'sftp_script');
            file_put_contents($tempScript, "pwd\nquit\n");

            $command = $this->buildSftpCommand($tempScript);

            exec($command, $output, $returnCode);
            unlink($tempScript);

            return $returnCode === 0;

        } catch (Exception $e) {
            Log::error('SFTP connection test failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Move a local file to the processed directory
     *
     * @param string $localPath Local file path
     * @return bool Whether the move was successful
     */
    public function moveToProcessed(string $localPath): bool
    {
        try {
            $filename = basename($localPath);
            $directory = dirname($localPath);
            $processedDir = $directory . '/' . config('decta.files.processed_dir');

            // Create processed directory if it doesn't exist
            if (!Storage::disk($this->diskName)->exists($processedDir)) {
                Storage::disk($this->diskName)->makeDirectory($processedDir);
            }

            $processedPath = $processedDir . '/' . $filename;

            Storage::disk($this->diskName)->move($localPath, $processedPath);

            Log::info('File moved to processed directory', [
                'from' => $localPath,
                'to' => $processedPath
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to move file to processed directory', [
                'file' => $localPath,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Move a local file to the failed directory
     *
     * @param string $localPath Local file path
     * @return bool Whether the move was successful
     */
    public function moveToFailed(string $localPath): bool
    {
        try {
            $filename = basename($localPath);
            $directory = dirname($localPath);
            $failedDir = $directory . '/' . config('decta.files.failed_dir');

            // Create failed directory if it doesn't exist
            if (!Storage::disk($this->diskName)->exists($failedDir)) {
                Storage::disk($this->diskName)->makeDirectory($failedDir);
            }

            $failedPath = $failedDir . '/' . $filename;

            Storage::disk($this->diskName)->move($localPath, $failedPath);

            Log::info('File moved to failed directory', [
                'from' => $localPath,
                'to' => $failedPath
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to move file to failed directory', [
                'file' => $localPath,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
