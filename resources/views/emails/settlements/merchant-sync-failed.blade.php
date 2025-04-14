<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Merchant Sync Failed</title>
    <style>
        @media only screen and (max-width: 600px) {
            .inner-body {
                width: 100% !important;
            }

            .footer {
                width: 100% !important;
            }
        }

        @media only screen and (max-width: 500px) {
            .button {
                width: 100% !important;
            }
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            color: #2d3748;
            line-height: 1.5;
            margin: 0;
            padding: 0;
        }

        .wrapper {
            background-color: #f8fafc;
            margin: 0;
            padding: 0;
            width: 100%;
        }

        .body {
            border: 1px solid #e8e5ef;
            box-sizing: border-box;
            margin: 0 auto;
            max-width: 600px;
            padding: 0;
            width: 100%;
        }

        .inner-body {
            background-color: #ffffff;
            border-color: #e8e5ef;
            border-radius: 2px;
            border-width: 1px;
            box-shadow: 0 2px 0 rgba(0, 0, 150, 0.025), 2px 4px 0 rgba(0, 0, 150, 0.015);
            margin: 0 auto;
            padding: 0;
            width: 570px;
        }

        .content {
            padding: 20px;
        }

        .header {
            padding: 20px;
            text-align: center;
            font-size: 22px;
            font-weight: bold;
            color: #3d4852;
            background-color: #3490dc;
            color: white;
            border-bottom: 1px solid #e8e5ef;
        }

        .footer {
            margin: 0 auto;
            padding: 0;
            text-align: center;
            width: 570px;
            background-color: #f8fafc;
            color: #b0adc5;
            padding: 35px;
            font-size: 12px;
            text-align: center;
            border-top: 1px solid #e8e5ef;
        }

        h1 {
            color: #e53e3e;
            font-size: 22px;
            font-weight: bold;
            margin-top: 0;
            text-align: left;
        }

        .error-box {
            background-color: #fff5f5;
            border-left: 5px solid #e53e3e;
            padding: 15px;
            margin-bottom: 20px;
        }

        .details-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 15px;
            margin-top: 15px;
            margin-bottom: 20px;
        }

        .stack-trace-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 15px;
            margin-top: 15px;
            margin-bottom: 20px;
            font-family: monospace;
            font-size: 13px;
            overflow-x: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        .panel {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        .panel th {
            text-align: left;
            padding: 8px;
            color: #4a5568;
            font-weight: 600;
            width: 180px;
        }

        .panel td {
            text-align: left;
            padding: 8px;
        }

        .button {
            display: inline-block;
            background-color: #3490dc;
            border-radius: 4px;
            color: #ffffff;
            font-size: 16px;
            font-weight: bold;
            text-decoration: none;
            padding: 12px 25px;
            margin-top: 15px;
        }

        .button:hover {
            background-color: #2779bd;
        }

        .action {
            text-align: center;
            margin-top: 25px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="body">
        <div class="header">
            Intraclear Report
        </div>

        <div class="inner-body">
            <div class="content">
                <h1>Merchant Sync Failed</h1>

                <div class="error-box">
                    <strong>The merchant synchronization process has encountered an error and failed to complete.</strong> Please review the details below and take appropriate action.
                </div>

                <div class="details-box">
                    <table class="panel">
                        <tr>
                            <th>Time:</th>
                            <td>{{ now()->format('Y-m-d H:i:s') }}</td>
                        </tr>
                        <tr>
                            <th>Environment:</th>
                            <td>{{ app()->environment() }}</td>
                        </tr>
                        <tr>
                            <th>Error Message:</th>
                            <td>{{ $errorMessage }}</td>
                        </tr>
                    </table>
                </div>

                <h2>Stack Trace</h2>
                <div class="stack-trace-box">{{ $stackTrace }}</div>

                <div class="action">
                    <a href="{{ config('app.url') }}/admin/merchants" class="button">View Sync Dashboard</a>
                </div>

                <p style="text-align: center;">Please contact the development team if this issue persists.</p>
            </div>
        </div>

        <div class="footer">
            <p>Thanks,<br>
                Intraclear Report Team</p>
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
        </div>
    </div>
</div>
</body>
</html>
