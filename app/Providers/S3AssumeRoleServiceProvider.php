<?php

namespace App\Providers;

use Aws\Exception\AwsException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Filesystem;
use Aws\S3\S3Client;
use Aws\Sts\StsClient;

class S3AssumeRoleServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot()
    {
        Storage::extend('s3-assume-role', function ($app, $config) {
            try {
                // Validate required configuration
                $this->validateConfiguration();

                // Create S3 client with assume role credentials
                $s3Client = $this->createS3Client();

                $adapter = new AwsS3V3Adapter(
                    $s3Client,
                    $config['bucket'] ?? env('AWS_BUCKET'),
                    $config['prefix'] ?? ''
                );

                return new FilesystemAdapter(
                    new Filesystem($adapter),
                    $adapter,
                    $config
                );

            } catch (\Exception $e) {
                Log::error('S3 Assume Role Setup Error', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'config' => array_filter([
                        'region' => env('AWS_DEFAULT_REGION'),
                        'bucket' => env('AWS_BUCKET'),
                        'role_arn' => env('AWS_ROLE_ARN'),
                        'has_access_key' => !empty(env('AWS_ACCESS_KEY_ID')),
                        'has_secret_key' => !empty(env('AWS_SECRET_ACCESS_KEY')),
                    ])
                ]);

                // In production, you might want to fallback to local storage
                if (app()->environment('production')) {
                    Log::warning('Falling back to local storage due to S3 configuration error');
                    // You could return a local adapter here as fallback
                }

                throw $e;
            }
        });
    }

    /**
     * Validate required AWS configuration
     */
    private function validateConfiguration(): void
    {
        $requiredConfigs = [
            'AWS_ACCESS_KEY_ID' => config('filesystems.disks.s3.key'),
            'AWS_SECRET_ACCESS_KEY' => config('filesystems.disks.s3.secret'),
            'AWS_DEFAULT_REGION' => config('filesystems.disks.s3.region'),
            'AWS_BUCKET' => config('filesystems.disks.s3.bucket'),
            'AWS_ROLE_ARN' => config('filesystems.disks.s3.role_arn')
        ];

        $missing = [];
        foreach ($requiredConfigs as $name => $value) {
            if (empty($value)) {
                $missing[] = $name;
            }
        }

        if (!empty($missing)) {
            throw new \InvalidArgumentException(
                'Missing required AWS configuration: ' . implode(', ', $missing)
            );
        }
    }

    /**
     * Create S3 client with assume role credentials
     */
    private function createS3Client(): S3Client
    {
        // Get temporary credentials through assume role
        $credentials = $this->getTemporaryCredentials();

        return new S3Client([
            'region' => config('filesystems.disks.s3.region', 'eu-west-1'),
            'version' => 'latest',
            'credentials' => [
                'key' => $credentials['key'],
                'secret' => $credentials['secret'],
                'token' => $credentials['token']
            ],
            'use_path_style_endpoint' => config('filesystems.disks.s3.use_path_style_endpoint', false),
            'http' => [
                'connect_timeout' => 10,
                'timeout' => 30,
                'verify' => true
            ],
            'retries' => [
                'mode' => 'adaptive',
                'max_attempts' => 3
            ]
        ]);
    }

    /**
     * Get temporary credentials by assuming the specified role
     */
    private function getTemporaryCredentials(): array
    {
        $roleArn = config('filesystems.disks.s3.role_arn');
        $cacheKey = 'aws_temporary_credentials_' . md5($roleArn);

        return Cache::remember($cacheKey, 55 * 60, function () use ($roleArn) {
            try {
                // Create STS client with base credentials
                $stsClient = new StsClient([
                    'region' => config('filesystems.disks.s3.region', 'eu-west-1'),
                    'version' => 'latest',
                    'credentials' => [
                        'key' => config('filesystems.disks.s3.key'),
                        'secret' => config('filesystems.disks.s3.secret')
                    ],
                    'http' => [
                        'connect_timeout' => 10,
                        'timeout' => 30
                    ]
                ]);

                Log::info('Attempting to assume AWS role', [
                    'role_arn' => $roleArn,
                    'region' => config('filesystems.disks.s3.region')
                ]);

                $result = $stsClient->assumeRole([
                    'RoleArn' => $roleArn,
                    'RoleSessionName' => $this->generateSessionName(),
                    'DurationSeconds' => 3600 // 1 hour
                ]);

                Log::info('Successfully assumed AWS role');

                return [
                    'key' => $result['Credentials']['AccessKeyId'],
                    'secret' => $result['Credentials']['SecretAccessKey'],
                    'token' => $result['Credentials']['SessionToken'],
                    'expires' => $result['Credentials']['Expiration']
                ];

            } catch (AwsException $e) {
                Log::error('AWS Assume Role Error', [
                    'message' => $e->getMessage(),
                    'aws_error_code' => $e->getAwsErrorCode(),
                    'aws_error_message' => $e->getAwsErrorMessage(),
                    'aws_request_id' => $e->getAwsRequestId(),
                    'aws_error_type' => $e->getAwsErrorType(),
                    'status_code' => $e->getStatusCode(),
                    'role_arn' => $roleArn,
                    'region' => config('filesystems.disks.s3.region')
                ]);
                throw $e;
            } catch (\Exception $e) {
                Log::error('General STS Error', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }
        });
    }

    /**
     * Generate a unique session name
     */
    private function generateSessionName(): string
    {
        $appName = str_replace(' ', '-', strtolower(config('app.name', 'laravel')));
        $timestamp = time();
        $random = substr(md5(uniqid()), 0, 8);

        return "{$appName}-{$timestamp}-{$random}";
    }
}
