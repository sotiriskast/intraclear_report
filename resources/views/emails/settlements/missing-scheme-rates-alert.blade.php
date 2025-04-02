<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Missing Scheme Rates Alert</title>
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
            background-color: #f8fafc;
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
            color: #3d4852;
            font-size: 20px;
            font-weight: bold;
            margin-top: 0;
            text-align: left;
        }

        .warning-box {
            background-color: #fff3cd;
            border-left: 5px solid #ffc107;
            padding: 15px;
            margin-bottom: 20px;
        }

        .action-box {
            background-color: #e8f4fd;
            border: 1px solid #bee3f8;
            border-radius: 4px;
            padding: 15px;
            margin-top: 20px;
        }

        .currency-box {
            margin-bottom: 25px;
        }

        .currency-header {
            color: #3490dc;
            font-size: 18px;
            margin-bottom: 10px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 5px;
        }

        .brand-header {
            color: #2d3748;
            font-size: 16px;
            margin-top: 15px;
            margin-bottom: 10px;
        }

        .dates-table {
            width: 100%;
            border-collapse: collapse;
        }

        .dates-table th {
            text-align: left;
            padding: 8px;
            border-bottom: 2px solid #e2e8f0;
            color: #4a5568;
        }

        .dates-table td {
            text-align: left;
            padding: 8px;
            border-bottom: 1px solid #e2e8f0;
        }

        .table-wrapper {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 10px;
            margin-bottom: 15px;
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
                <h1>ALERT: Missing Scheme Rates</h1>

                <div class="warning-box">
                    <strong>Warning:</strong> There are missing scheme rates for the settlement period
                    <strong>{{ \Carbon\Carbon::parse($dateRange['start'])->format('Y-m-d') }}</strong> to
                    <strong>{{ \Carbon\Carbon::parse($dateRange['end'])->format('Y-m-d') }}</strong>.
                </div>

                @foreach($missingRates as $currency => $brandData)
                    <div class="currency-box">
                        <h2 class="currency-header">{{ $currency }} Currency</h2>

                        @foreach($brandData as $brand => $dates)
                            <h3 class="brand-header">{{ $brand }}</h3>

                            <p>The following dates are missing exchange rates:</p>

                            <div class="table-wrapper">
                                <table class="dates-table">
                                    <thead>
                                    <tr>
                                        <th>Date</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($dates as $date)
                                        <tr>
                                            <td>{{ $date }}</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endforeach
                    </div>
                @endforeach

                <div class="action-box">
                    <strong>Action Required:</strong> Please add the missing rates to ensure accurate settlement calculations.
                </div>

                <div style="text-align: center; margin-top: 30px;">
                    <a href="{{ config('app.url') }}" class="button">Go to Application</a>
                </div>
            </div>
        </div>

        <div class="footer">
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
        </div>
    </div>
</div>
</body>
</html>
