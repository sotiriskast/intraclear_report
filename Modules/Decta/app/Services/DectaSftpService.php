<?php

namespace Modules\Decta\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;
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
     * (Existing method - keeping as is)
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
     * (Existing method - keeping as is)
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
     * Test the SFTP connection
     * (Existing method - keeping as is)
     */
    public function testConnection(): bool
    {
        try {
            // Validate configuration first
            $validation = $this->validateConfig();
            if (!$validation['valid']) {
                Log::error('SFTP configuration validation failed', $validation);
                return false;
            }

            $tempScript = tempnam(sys_get_temp_dir(), 'sftp_script');
            if ($tempScript === false) {
                Log::error('Failed to create temporary script file');
                return false;
            }

            // Make sure temp script is readable/writable
            chmod($tempScript, 0600);

            $scriptContent = "pwd\nquit\n";
            if (file_put_contents($tempScript, $scriptContent) === false) {
                Log::error('Failed to write to temporary script file', ['temp_script' => $tempScript]);
                unlink($tempScript);
                return false;
            }

            $command = $this->buildSftpCommand($tempScript);

            Log::info('Testing SFTP connection', [
                'command' => $command,
                'temp_script' => $tempScript,
                'script_content' => $scriptContent,
                'config' => [
                    'host' => $this->config['host'],
                    'port' => $this->config['port'],
                    'username' => $this->config['username'],
                    'private_key_path' => $this->config['private_key_path'],
                    'private_key_exists' => file_exists($this->config['private_key_path']),
                    'private_key_readable' => is_readable($this->config['private_key_path']),
                    'private_key_permissions' => file_exists($this->config['private_key_path']) ?
                        substr(sprintf('%o', fileperms($this->config['private_key_path'])), -4) : 'N/A'
                ]
            ]);

            // Execute with more detailed output capture
            $output = [];
            $returnCode = null;
            exec($command . ' 2>&1', $output, $returnCode);

            Log::info('SFTP command executed', [
                'return_code' => $returnCode,
                'output' => $output,
                'output_count' => count($output)
            ]);

            // Clean up temp script
            unlink($tempScript);

            $success = $returnCode === 0;

            if (!$success) {
                Log::error('SFTP connection test failed', [
                    'return_code' => $returnCode,
                    'output' => $output,
                    'command' => $command
                ]);
            }

            return $success;

        } catch (Exception $e) {
            Log::error('SFTP connection test exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Find the latest file matching the expected pattern
     * (Existing method - keeping as is)
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
     * Build the SFTP command with proper authentication options
     * (Existing method - keeping as is)
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
        $options[] = '-o ConnectTimeout=10'; // 10 second timeout
        $options[] = '-o PreferredAuthentications=publickey'; // Only use key auth

        return sprintf(
            'sftp %s %s@%s',
            implode(' ', $options),
            escapeshellarg($this->config['username']),
            escapeshellarg($this->config['host'])
        );
    }
    /**
     * Move a local file to the processed directory with smart path handling
     * (Existing method - keeping as is)
     */
    public function moveToProcessed(string $localPath): bool
    {
        try {
            $filename = basename($localPath);

            // Check if file is already in processed directory
            if ($this->isFileInProcessedDirectory($localPath)) {
                Log::info('File already in processed directory, not moving', [
                    'current_path' => $localPath,
                    'filename' => $filename
                ]);
                return true; // Consider this "successful" since file is where it should be
            }

            $processedPath = $this->getSmartProcessedPath($localPath);

            // Create processed directory if it doesn't exist
            $processedDir = dirname($processedPath);
            if (!Storage::disk($this->diskName)->exists($processedDir)) {
                Storage::disk($this->diskName)->makeDirectory($processedDir, 0755, true);
            }

            // Only move if not already in the right place
            if ($localPath !== $processedPath) {
                Storage::disk($this->diskName)->move($localPath, $processedPath);

                Log::info('File moved to processed directory', [
                    'from' => $localPath,
                    'to' => $processedPath
                ]);
            } else {
                Log::info('File already in correct processed location', [
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
     * (Existing method - keeping as is)
     */
    public function moveToFailed(string $localPath): bool
    {
        try {
            $filename = basename($localPath);

            // Check if file is already in failed directory
            if ($this->isFileInFailedDirectory($localPath)) {
                Log::info('File already in failed directory, not moving', [
                    'current_path' => $localPath,
                    'filename' => $filename
                ]);
                return true; // Consider this "successful" since file is where it should be
            }

            $failedPath = $this->getSmartFailedPath($localPath);

            // Create failed directory if it doesn't exist
            $failedDir = dirname($failedPath);
            if (!Storage::disk($this->diskName)->exists($failedDir)) {
                Storage::disk($this->diskName)->makeDirectory($failedDir, 0755, true);
            }

            // Only move if not already in the right place
            if ($localPath !== $failedPath) {
                Storage::disk($this->diskName)->move($localPath, $failedPath);

                Log::info('File moved to failed directory', [
                    'from' => $localPath,
                    'to' => $failedPath
                ]);
            } else {
                Log::info('File already in correct failed location', [
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

    // ==================== NEW METHODS FOR WEB INTERFACE ====================

    /**
     * Enhanced connection test with detailed response for web interface
     */
    public function testConnectionDetailed(): array
    {
        try {
            $startTime = microtime(true);

            // First validate configuration
            $validation = $this->validateConfig();
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => 'Configuration validation failed: ' . implode(', ', $validation['errors']),
                    'details' => array_merge($validation, [
                        'host' => $this->config['host'],
                        'port' => $this->config['port'],
                        'username' => $this->config['username'],
                    ])
                ];
            }

            $success = $this->testConnection();

            $endTime = microtime(true);
            $connectionTime = round(($endTime - $startTime) * 1000, 2);

            if ($success) {
                // Try to get file count for additional info
                try {
                    $files = $this->listRemoteFiles();
                    $fileCount = count($files);
                } catch (Exception $e) {
                    $fileCount = 'unknown';
                    Log::warning('Could not list files after successful connection', [
                        'error' => $e->getMessage()
                    ]);
                }

                return [
                    'success' => true,
                    'message' => "Connection successful in {$connectionTime}ms",
                    'details' => [
                        'host' => $this->config['host'],
                        'port' => $this->config['port'],
                        'username' => $this->config['username'],
                        'remote_path' => $this->config['remote_path'],
                        'files_found' => $fileCount,
                        'connection_time_ms' => $connectionTime,
                        'validation' => $validation
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Connection failed - check logs for detailed error information',
                    'details' => [
                        'host' => $this->config['host'],
                        'port' => $this->config['port'],
                        'username' => $this->config['username'],
                        'connection_time_ms' => $connectionTime,
                        'validation' => $validation,
                        'suggestion' => $this->getSuggestion($validation)
                    ]
                ];
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
                'details' => [
                    'host' => $this->config['host'],
                    'port' => $this->config['port'],
                    'username' => $this->config['username'],
                    'error' => $e->getMessage(),
                    'suggestion' => 'Check server logs for more details'
                ]
            ];
        }
    }
    private function validateConfig(): array
    {
        $errors = [];
        $warnings = [];

        // Check required config values
        if (empty($this->config['host'])) {
            $errors[] = 'SFTP host is not configured';
        }

        if (empty($this->config['username'])) {
            $errors[] = 'SFTP username is not configured';
        }

        if (empty($this->config['private_key_path'])) {
            $errors[] = 'SFTP private key path is not configured';
        }

        // Check private key file
        if (!empty($this->config['private_key_path'])) {
            $keyPath = $this->config['private_key_path'];

            if (!file_exists($keyPath)) {
                $errors[] = "Private key file does not exist: {$keyPath}";
            } else {
                if (!is_readable($keyPath)) {
                    $errors[] = "Private key file is not readable: {$keyPath}";
                }

                // Check permissions
                $perms = fileperms($keyPath);
                $octal = substr(sprintf('%o', $perms), -3);

                if ($octal !== '600' && $octal !== '400') {
                    $warnings[] = "Private key file permissions are {$octal}, should be 600 or 400";
                }

                // Check if file is actually a private key
                $content = file_get_contents($keyPath);
                if ($content !== false) {
                    if (!str_contains($content, '-----BEGIN') || !str_contains($content, 'PRIVATE KEY-----')) {
                        $warnings[] = "Private key file does not appear to contain a valid private key";
                    }
                } else {
                    $errors[] = "Could not read private key file content";
                }
            }
        }

        // Check port
        $port = $this->config['port'] ?? 22;
        if (!is_numeric($port) || $port < 1 || $port > 65535) {
            $errors[] = "Invalid port number: {$port}";
        }

        // Check if SFTP command is available
        $sftpPath = trim(shell_exec('which sftp'));
        if (empty($sftpPath)) {
            $errors[] = 'SFTP command not found in system PATH';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'sftp_path' => $sftpPath ?? 'not found',
            'php_user' => get_current_user(),
            'working_directory' => getcwd()
        ];
    }

    /**
     * Get suggestion based on validation results
     */
    private function getSuggestion(array $validation): string
    {
        if (!$validation['valid']) {
            $errors = $validation['errors'];

            if (in_array('SFTP command not found in system PATH', $errors)) {
                return 'Install OpenSSH client: sudo apt-get install openssh-client';
            }

            foreach ($errors as $error) {
                if (str_contains($error, 'does not exist')) {
                    return 'Check the private key path in your .env file: DECTA_SFTP_PRIVATE_KEY_PATH';
                }
                if (str_contains($error, 'not readable')) {
                    return 'Fix private key permissions: chmod 600 /path/to/private/key';
                }
            }
        }

        return 'Check the Laravel logs for more detailed error information';
    }

    /**
     * List remote files with enhanced formatting for web interface
     */
    public function listRemoteFiles(string $path = '', bool $showAll = false): array
    {
        $remotePath = $path ?: ($this->config['remote_path'] ?? '');

        // Use existing listFiles method but transform for web interface
        $rawFiles = $this->listFiles($remotePath);

        $files = [];
        $allowedExtensions = $this->config['files']['extensions'] ?? ['.csv', '.xml', '.txt'];

        foreach ($rawFiles as $file) {
            $filename = basename($file['path']);
            $size = $file['fileSize'] ?? 0;
            $modified = $file['lastModified'] ?? time();

            // Filter by file extensions unless showing all
            if (!$showAll) {
                $hasAllowedExtension = false;
                foreach ($allowedExtensions as $ext) {
                    if (Str::endsWith(strtolower($filename), strtolower($ext))) {
                        $hasAllowedExtension = true;
                        break;
                    }
                }
                if (!$hasAllowedExtension) {
                    continue;
                }
            }

            $files[] = [
                'name' => $filename,
                'size' => $size,
                'size_human' => $this->formatBytes($size),
                'modified' => $modified,
                'modified_human' => Carbon::createFromTimestamp($modified)->format('M j, Y g:i A'),
                'path' => $file['path'],
                'full_path' => $file['path']
            ];
        }

        // Sort by modified date (newest first)
        usort($files, function ($a, $b) {
            return $b['modified'] <=> $a['modified'];
        });

        Log::info('Listed remote SFTP files for web interface', [
            'path' => $remotePath,
            'file_count' => count($files),
            'show_all' => $showAll
        ]);

        return $files;
    }

    /**
     * Enhanced download with detailed response for web interface
     */
    public function downloadFileDetailed(string $filename, string $targetDate = null): array
    {
        try {
            $remotePath = rtrim($this->config['remote_path'] ?? '', '/') . '/' . $filename;

            // Generate local path with date structure if needed
            $localBasePath = $this->config['local_path'] ?? 'files';
            if ($targetDate) {
                $dateStr = Carbon::parse($targetDate)->format('Y/m');
                $localPath = "{$localBasePath}/{$dateStr}/{$filename}";
            } else {
                $localPath = "{$localBasePath}/{$filename}";
            }

            $success = $this->downloadFile($remotePath, $localPath);

            if ($success) {
                $fileSize = Storage::disk($this->diskName)->size($localPath);

                return [
                    'success' => true,
                    'filename' => $filename,
                    'message' => "File downloaded successfully: {$filename}",
                    'remote_path' => $remotePath,
                    'local_path' => $localPath,
                    'file_size' => $fileSize,
                    'file_size_human' => $this->formatBytes($fileSize)
                ];
            } else {
                return [
                    'success' => false,
                    'filename' => $filename,
                    'message' => "Failed to download file: {$filename}"
                ];
            }

        } catch (Exception $e) {
            Log::error('Enhanced file download failed', [
                'filename' => $filename,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'filename' => $filename,
                'message' => $e->getMessage()
            ];
        }
    }
    /**
     * Check if a filename matches a specific date
     */
    public function fileMatchesDate(string $filename, string $date): bool
    {
        $targetDate = Carbon::parse($date);

        // Common date patterns in filenames
        $patterns = [
            // YYYYMMDD
            '/(\d{4})(\d{2})(\d{2})/',
            // YYYY-MM-DD
            '/(\d{4})-(\d{2})-(\d{2})/',
            // YYYY_MM_DD
            '/(\d{4})_(\d{2})_(\d{2})/',
            // DD-MM-YYYY
            '/(\d{2})-(\d{2})-(\d{4})/',
            // DD_MM_YYYY
            '/(\d{2})_(\d{2})_(\d{4})/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $filename, $matches)) {
                try {
                    if (strlen($matches[1]) === 4) {
                        // YYYY-MM-DD format
                        $fileDate = Carbon::createFromFormat('Y-m-d', "{$matches[1]}-{$matches[2]}-{$matches[3]}");
                    } else {
                        // DD-MM-YYYY format
                        $fileDate = Carbon::createFromFormat('d-m-Y', "{$matches[1]}-{$matches[2]}-{$matches[3]}");
                    }

                    return $fileDate->isSameDay($targetDate);
                } catch (Exception $e) {
                    // Invalid date, continue to next pattern
                    continue;
                }
            }
        }

        return false;
    }

    /**
     * Get SFTP server information for web interface
     */
    public function getServerInfo(): array
    {
        try {
            $connectionResult = $this->testConnectionDetailed();

            return [
                'host' => $this->config['host'],
                'port' => $this->config['port'],
                'username' => $this->config['username'],
                'remote_path' => $this->config['remote_path'],
                'connected' => $connectionResult['success'],
                'connection_details' => $connectionResult['details'] ?? [],
                'connection_time' => Carbon::now()->toISOString()
            ];

        } catch (Exception $e) {
            return [
                'error' => $e->getMessage(),
                'connected' => false
            ];
        }
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    // ==================== EXISTING HELPER METHODS ====================

    /**
     * Check if file is already in processed directory
     */
    private function isFileInProcessedDirectory(string $filePath): bool
    {
        $processedDir = config('decta.files.processed_dir', 'processed');
        $pathParts = explode('/', $filePath);

        // Check if 'processed' is in the path (but not as the filename)
        $directoryParts = array_slice($pathParts, 0, -1); // Remove filename
        return in_array($processedDir, $directoryParts);
    }

    /**
     * Check if file is already in failed directory
     */
    private function isFileInFailedDirectory(string $filePath): bool
    {
        $failedDir = config('decta.files.failed_dir', 'failed');
        $pathParts = explode('/', $filePath);

        // Check if 'failed' is in the path (but not as the filename)
        $directoryParts = array_slice($pathParts, 0, -1); // Remove filename
        return in_array($failedDir, $directoryParts);
    }

    /**
     * Get smart processed path that prevents nested directories
     */
    private function getSmartProcessedPath(string $currentPath): string
    {
        $filename = basename($currentPath);
        $processedDir = config('decta.files.processed_dir', 'processed');

        // If file is already in processed directory, return current path
        if ($this->isFileInProcessedDirectory($currentPath)) {
            return $currentPath;
        }

        // Get the base directory structure (e.g., "files/2025/05/26")
        $baseDir = $this->getBaseDirectory($currentPath);

        // Return the processed path in the base directory
        return $baseDir . '/' . $processedDir . '/' . $filename;
    }

    /**
     * Get smart failed path that prevents nested directories
     */
    private function getSmartFailedPath(string $currentPath): string
    {
        $filename = basename($currentPath);
        $failedDir = config('decta.files.failed_dir', 'failed');

        // If file is already in failed directory, return current path
        if ($this->isFileInFailedDirectory($currentPath)) {
            return $currentPath;
        }

        // Get the base directory structure (e.g., "files/2025/05/26")
        $baseDir = $this->getBaseDirectory($currentPath);

        // Return the failed path in the base directory
        return $baseDir . '/' . $failedDir . '/' . $filename;
    }

    /**
     * Extract the base directory from a path, removing any failed/processed subdirectories
     */
    private function getBaseDirectory(string $path): string
    {
        $directory = dirname($path);
        $processedDir = config('decta.files.processed_dir', 'processed');
        $failedDir = config('decta.files.failed_dir', 'failed');

        // Remove trailing /failed or /processed from the directory
        // Use word boundaries to prevent partial matches
        $directory = preg_replace('/\/\b' . preg_quote($failedDir, '/') . '\b$/', '', $directory);
        $directory = preg_replace('/\/\b' . preg_quote($processedDir, '/') . '\b$/', '', $directory);

        return $directory;
    }
}
