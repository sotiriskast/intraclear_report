<?php

namespace App\Providers;

use Aws\Exception\AwsException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
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
                // Create STS client once
                $stsClient = new StsClient([
                    'region' => env('AWS_DEFAULT_REGION','eu-west-1'),
                    'version' => 'latest',
                    'credentials' => [
                        'key' => env('AWS_ACCESS_KEY_ID'),
                        'secret' => env('AWS_SECRET_ACCESS_KEY')
                    ],
                ]);

                try {
                    $credentials = $this->getTemporaryCredentials($stsClient);

                    $s3Client = new S3Client([
                        'region' => env('AWS_DEFAULT_REGION','eu-west-1'),
                        'version' => 'latest',
                        'credentials' => [
                            'key' => $credentials['key'],
                            'secret' => $credentials['secret'],
                            'token' => $credentials['token']
                        ],
                        'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
                        'http' => [
                            'connect_timeout' => 5,
                            'timeout' => 10
                        ],
                        'retries' => [
                            'mode' => 'adaptive',
                            'max_attempts' => 3
                        ]
                    ]);

                } catch (AwsException $e) {
                    \Log::error('AWS Assume Role Error', [
                        'message' => $e->getMessage(),
                        'aws_message' => $e->getAwsErrorMessage(),
                        'request_id' => $e->getAwsRequestId(),
                        'error_type' => $e->getAwsErrorType(),
                        'role_arn' => env('AWS_ROLE_ARN'),
                        'region' => env('AWS_DEFAULT_REGION')
                    ]);
                    throw $e;
                }

                $adapter = new AwsS3V3Adapter(
                    $s3Client,
                    env('AWS_BUCKET'),
                    ''
                );

                return new FilesystemAdapter(
                    new Filesystem($adapter),
                    $adapter,
                );

            } catch (\Exception $e) {
                \Log::error('General S3 Setup Error: ' . $e->getMessage());
                throw $e;
            }
        });
    }
    private function getTemporaryCredentials(StsClient $stsClient)
    {
        return Cache::remember('aws_temporary_credentials', 55 * 60, function () use ($stsClient) {
            $result = $stsClient->assumeRole([
                'RoleArn' => env('AWS_ROLE_ARN'),
                'RoleSessionName' => 'session-' . time(),
                'DurationSeconds' => 3600 // 1 hour
            ]);

            return [
                'key' => $result['Credentials']['AccessKeyId'],
                'secret' => $result['Credentials']['SecretAccessKey'],
                'token' => $result['Credentials']['SessionToken'],
                'expires' => $result['Credentials']['Expiration']
            ];
        });
    }
}
