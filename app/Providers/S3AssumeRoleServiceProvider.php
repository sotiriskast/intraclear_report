<?php

namespace App\Providers;

use Aws\Exception\AwsException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Storage;
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
                // First, create STS client with default credentials
                $stsClient = new StsClient([
                    'region' => env('AWS_DEFAULT_REGION'),
                    'version' => 'latest',
                    'credentials' => [
                        'key' => env('AWS_ACCESS_KEY_ID'),
                        'secret' => env('AWS_SECRET_ACCESS_KEY')
                    ],
                ]);

                // Try to assume role directly first to test
                try {
                    $result = $stsClient->assumeRole([
                        'RoleArn' => env('AWS_ROLE_ARN'),
                        'RoleSessionName' => 'test-session'
                    ]);
                    // Create S3 client with temporary credentials
                    $s3Client = new S3Client([
                        'region' => env('AWS_DEFAULT_REGION'),
                        'version' => 'latest',
                        'credentials' => [
                            'key' => $result['Credentials']['AccessKeyId'],
                            'secret' => $result['Credentials']['SecretAccessKey'],
                            'token' => $result['Credentials']['SessionToken']
                        ],
                        'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false)
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
}
