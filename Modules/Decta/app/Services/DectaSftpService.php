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
        $this->diskName = 'decta'; // Use custom decta disk instead of local
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
     * Download a file from SFTP to local storage using custom decta disk
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

            Log::info('Starting file download using custom decta disk', [
                'remote_path' => $remotePath,
                'local_path' => $localPath,
                'disk' => $this->diskName
            ]);

            // Ensure directory exists using Storage facade
            $directory = dirname($localPath);
            if (!Storage::disk($this->diskName)->exists($directory)) {
                Log::info("Creating directory via Storage facade: {$directory}");
                Storage::disk($this->diskName)->makeDirectory($directory);

                if (!Storage::disk($this->diskName)->exists($directory)) {
                    throw new Exception("Failed to create directory via Storage facade: {$directory}");
                }
            }

            // Remove existing file if it exists
            if (Storage::disk($this->diskName)->exists($localPath)) {
                Log::info("Removing existing file via Storage facade: {$localPath}");
                Storage::disk($this->diskName)->delete($localPath);
            }

            // Get the resolved path from Storage facade for SFTP download
            $fullLocalPath = Storage::disk($this->diskName)->path($localPath);

            Log::info('Using Storage facade resolved path', [
                'local_path' => $localPath,
                'resolved_path' => $fullLocalPath,
                'disk' => $this->diskName
            ]);

            // Ensure the directory for the resolved path exists (using native mkdir as fallback)
            $resolvedDirectory = dirname($fullLocalPath);
            if (!is_dir($resolvedDirectory)) {
                Log::info("Creating resolved directory: {$resolvedDirectory}");
                if (!mkdir($resolvedDirectory, 0755, true)) {
                    throw new Exception("Failed to create resolved directory: {$resolvedDirectory}");
                }
            }

            // Create a temporary script file for SFTP
            $tempScript = tempnam(sys_get_temp_dir(), 'sftp_script');
            if ($tempScript === false) {
                throw new Exception("Failed to create temporary script file");
            }

            $scriptContent = "get \"{$remotePath}\" \"{$fullLocalPath}\"\nquit\n";
            if (file_put_contents($tempScript, $scriptContent) === false) {
                throw new Exception("Failed to write to temporary script file");
            }

            // Build and execute the SFTP command
            $command = $this->buildSftpCommand($tempScript);

            Log::info('Executing SFTP command', [
                'command' => $command,
                'target_path' => $fullLocalPath
            ]);

            exec($command, $output, $returnCode);

            // Clean up temp script
            unlink($tempScript);

            Log::info('SFTP command executed', [
                'return_code' => $returnCode,
                'output' => $output
            ]);

            if ($returnCode !== 0) {
                $outputStr = implode("\n", $output);
                throw new Exception("SFTP command failed with code: {$returnCode}. Output: {$outputStr}");
            }

            // Verify file was downloaded using Storage facade
            if (!Storage::disk($this->diskName)->exists($localPath)) {
                throw new Exception("File was not downloaded successfully - not accessible via Storage facade: {$localPath}");
            }

            // Get file size using Storage facade
            $fileSize = Storage::disk($this->diskName)->size($localPath);
            if ($fileSize === false || $fileSize === 0) {
                throw new Exception("Downloaded file is empty or unreadable via Storage facade: {$localPath}");
            }

            Log::info('SFTP file downloaded successfully', [
                'remote_path' => $remotePath,
                'local_path' => $localPath,
                'resolved_path' => $fullLocalPath,
                'file_size' => $fileSize,
                'disk' => $this->diskName,
                'storage_accessible' => true
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to download SFTP file', [
                'remote_path' => $remotePath,
                'local_path' => $localPath ?? 'not set',
                'resolved_path' => isset($fullLocalPath) ? $fullLocalPath : 'not set',
                'disk' => $this->diskName,
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
            usort($matchingFiles, function ($a, $b) {
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
     * Move a local file to the processed directory with smart path handling
     *
     * @param string $localPath Local file path
     * @return bool Whether the move was successful
     */
    public function moveToProcessed(string $localPath): bool
    {
        try {
            $filename = basename($localPath);
            $processedPath = $this->getSmartProcessedPath($localPath);

            // Create processed directory if it doesn't exist
            $processedDir = dirname($processedPath);
            if (!Storage::disk($this->diskName)->exists($processedDir)) {
                Storage::disk($this->diskName)->makeDirectory($processedDir);
            }

            // Only move if not already in the right place
            if ($localPath !== $processedPath) {
                Storage::disk($this->diskName)->move($localPath, $processedPath);

                Log::info('File moved to processed directory', [
                    'from' => $localPath,
                    'to' => $processedPath
                ]);
            } else {
                Log::info('File already in processed directory', [
                    'path' => $processedPath
                ]);
            }

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
     * Move a local file to the failed directory with smart path handling
     *
     * @param string $localPath Local file path
     * @return bool Whether the move was successful
     */
    public function moveToFailed(string $localPath): bool
    {
        try {
            $filename = basename($localPath);
            $failedPath = $this->getSmartFailedPath($localPath);

            // Create failed directory if it doesn't exist
            $failedDir = dirname($failedPath);
            if (!Storage::disk($this->diskName)->exists($failedDir)) {
                Storage::disk($this->diskName)->makeDirectory($failedDir);
            }

            // Only move if not already in the right place
            if ($localPath !== $failedPath) {
                Storage::disk($this->diskName)->move($localPath, $failedPath);

                Log::info('File moved to failed directory', [
                    'from' => $localPath,
                    'to' => $failedPath
                ]);
            } else {
                Log::info('File already in failed directory', [
                    'path' => $failedPath
                ]);
            }

            return true;
        } catch (Exception $e) {
            Log::error('Failed to move file to failed directory', [
                'file' => $localPath,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get smart processed path that prevents nested directories
     *
     * @param string $currentPath Current file path
     * @return string Correct processed path
     */
    private function getSmartProcessedPath(string $currentPath): string
    {
        $filename = basename($currentPath);
        $processedDir = config('decta.files.processed_dir', 'processed');
        $failedDir = config('decta.files.failed_dir', 'failed');

        // Get the base directory structure (e.g., "files/2025/05/26")
        $baseDir = $this->getBaseDirectory($currentPath);

        // Return the processed path in the base directory
        return $baseDir . '/' . $processedDir . '/' . $filename;
    }

    /**
     * Get smart failed path that prevents nested directories
     *
     * @param string $currentPath Current file path
     * @return string Correct failed path
     */
    private function getSmartFailedPath(string $currentPath): string
    {
        $filename = basename($currentPath);
        $failedDir = config('decta.files.failed_dir', 'failed');

        // Get the base directory structure (e.g., "files/2025/05/26")
        $baseDir = $this->getBaseDirectory($currentPath);

        // Return the failed path in the base directory
        return $baseDir . '/' . $failedDir . '/' . $filename;
    }

    /**
     * Extract the base directory from a path, removing any failed/processed subdirectories
     *
     * @param string $path File path
     * @return string Base directory path
     */
    private function getBaseDirectory(string $path): string
    {
        $directory = dirname($path);
        $processedDir = config('decta.files.processed_dir', 'processed');
        $failedDir = config('decta.files.failed_dir', 'failed');

        // Remove trailing /failed or /processed from the directory
        $directory = preg_replace('/\/' . preg_quote($failedDir, '/') . '$/', '', $directory);
        $directory = preg_replace('/\/' . preg_quote($processedDir, '/') . '$/', '', $directory);

        return $directory;
    }

    /**
     * Get the target directory path for processed files
     *
     * @param string $basePath Base file path
     * @return string Processed directory path
     */
    public function getProcessedDirectoryPath(string $basePath): string
    {
        $baseDir = $this->getBaseDirectory($basePath);
        $processedDir = config('decta.files.processed_dir', 'processed');
        return $baseDir . '/' . $processedDir;
    }

    /**
     * Get the target directory path for failed files
     *
     * @param string $basePath Base file path
     * @return string Failed directory path
     */
    public function getFailedDirectoryPath(string $basePath): string
    {
        $baseDir = $this->getBaseDirectory($basePath);
        $failedDir = config('decta.files.failed_dir', 'failed');
        return $baseDir . '/' . $failedDir;
    }
}
