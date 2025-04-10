<?php

namespace App\Http\Controllers;

use App\Mail\MerchantSyncFailed;
use App\Mail\NewMerchantCreated;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class EmailTestController extends Controller
{
    /**
     * Display the email testing dashboard.
     *
     * @return \Illuminate\View\View
     */
    public function dashboard(): View
    {
        $baseUrl = url('/admin/test-emails');

        return view('emails.test-dashboard', [
            'emails' => [
                [
                    'name' => 'Merchant Sync Failed',
                    'description' => 'Email sent when the merchant sync process fails',
                    'url' => $baseUrl . '/merchant-sync-failed',
                    'preview_url' => $baseUrl . '/preview/merchant-sync-failed'
                ],
                [
                    'name' => 'New Merchant Created',
                    'description' => 'Email sent when a new merchant is created during sync',
                    'url' => $baseUrl . '/new-merchant-created',
                    'preview_url' => $baseUrl . '/preview/new-merchant-created'
                ]
            ]
        ]);
    }

    /**
     * Send a test merchant sync failed email.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function testMerchantSyncFailed(): JsonResponse
    {
        $errorMessage = 'This is a test error message for merchant sync failure';
        $stackTrace = "Exception: Test Exception\n at MerchantSyncService.php:123\n at SyncController.php:45";

        // Get admin email with fallback
        $adminEmail = config('app.admin_email');

        // Validate that we have a valid email address
        if (empty($adminEmail) || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            return response()->json([
                'status' => 'error',
                'message' => 'No valid admin email configured. Please set APP_ADMIN_EMAIL in your .env file.'
            ], 400);
        }

        Mail::to($adminEmail)
            ->send(new MerchantSyncFailed($errorMessage, $stackTrace));

        return response()->json([
            'status' => 'success',
            'message' => 'Merchant sync failed test email sent to ' . $adminEmail
        ]);
    }

    /**
     * Send a test new merchant created email.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function testNewMerchantCreated(): JsonResponse
    {
        // Create a sample merchant object similar to what would come from the database
        $merchant = (object) [
            'id' => 'ACC_' . rand(10000, 99999),
            'corp_name' => 'Test Merchant Corp',
            'email' => 'test@testmerchant.com',
            'phone' => '555-123-4567',
            'active' => true
        ];

        $merchantId = rand(1, 1000); // Simulate internal merchant ID

        // Get admin email with fallback
        $adminEmail = config('app.admin_email');

        // Validate that we have a valid email address
        if (empty($adminEmail) || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            return response()->json([
                'status' => 'error',
                'message' => 'No valid admin email configured. Please set APP_ADMIN_EMAIL in your .env file.'
            ], 400);
        }

        Mail::to($adminEmail)
            ->send(new NewMerchantCreated($merchant, $merchantId));

        return response()->json([
            'status' => 'success',
            'message' => 'New merchant created test email sent to ' . $adminEmail,
            'merchant_data' => [
                'merchant_id' => $merchantId,
                'account_id' => $merchant->id,
                'name' => $merchant->corp_name
            ]
        ]);
    }
}
