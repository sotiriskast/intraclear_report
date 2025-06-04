<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subject }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            background: #dc3545;
            color: white;
            padding: 20px;
            margin: -30px -30px 30px -30px;
            border-radius: 8px 8px 0 0;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 22px;
        }
        .alert {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .error-details {
            background: #f8f9fa;
            border-left: 4px solid #dc3545;
            padding: 15px;
            margin: 20px 0;
            font-family: monospace;
            font-size: 14px;
            overflow-x: auto;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            font-size: 12px;
            color: #6c757d;
            text-align: center;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>⚠️ System Error Alert</h1>
    </div>

    <div class="alert">
        <strong>Error:</strong> {{ $errorMessage }}
    </div>

    @if(!empty($errorDetails))
        <h3>Error Details:</h3>
        <div class="error-details">
            @foreach($errorDetails as $key => $value)
                <strong>{{ ucfirst(str_replace('_', ' ', $key)) }}:</strong><br>
                {{ is_array($value) ? json_encode($value, JSON_PRETTY_PRINT) : $value }}<br><br>
            @endforeach
        </div>
    @endif

    <p><strong>Time:</strong> {{ now()->format('Y-m-d H:i:s') }}</p>
    <p><strong>Environment:</strong> {{ app()->environment() }}</p>

    <div class="footer">
        <p>This is an automated error notification from the Decta system.</p>
        <p>Please investigate and resolve the issue as soon as possible.</p>
    </div>
</div>
</body>
</html>
