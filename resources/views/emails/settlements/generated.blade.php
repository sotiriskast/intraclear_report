{{--
/**
 * Settlement Report Generation Email Template
 *
 * This template is used for:
 * - Notifying users of successful report generation
 * - Providing settlement period details
 * - Showing file count information
 * - Delivering ZIP file attachments
 *
 * Variables:
 * @var array $dateRange ['start' => 'Y-m-d', 'end' => 'Y-m-d']
 * @var int $fileCount Number of files in the ZIP
 * @var string $zipPath Path to the ZIP file
 */
--}}
@extends('layouts.email')

@section('title')
    Settlement Reports Generated
@endsection

@section('content')
    <p>Dear User,</p>

    <p>Your settlement reports have been generated successfully.</p>

    <div class="info-box">
        <h3 style="margin-top: 0;">Report Details</h3>
        <p><strong>Date Range:</strong><br>
            {{ $dateRange['start'] }} to {{ $dateRange['end'] }}</p>
        <p><strong>Total Files:</strong> {{ $fileCount }}</p>
    </div>

    @if(!Storage::exists($zipPath))
        <div style="background-color: #FEF3F2; padding: 1rem; margin: 1rem 0; border-radius: 0.375rem;">
            <p style="color: #991B1B; margin: 0;">
                Note: The ZIP file could not be attached to this email. Please contact support for assistance.
            </p>
        </div>
    @else
        <p>The reports have been attached to this email as a ZIP file.</p>
    @endif

    <p>If you have any questions or need assistance, please don't hesitate to contact our support team.</p>

    <p>Best regards,<br>
        {{ config('app.name') }} Team</p>
@endsection

@section('footer')
    <p style="font-size: 0.75rem; color: #718096;">
        This is an automated message. Please do not reply to this email.
    </p>
@endsection
