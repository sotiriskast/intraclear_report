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
            max-width: 800px;
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
            font-size: 24px;
        }
        .alert {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        .summary-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #dc3545;
        }
        .summary-card h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #666;
            text-transform: uppercase;
        }
        .summary-card .value {
            font-size: 24px;
            font-weight: bold;
            color: #dc3545;
        }
        .section {
            margin-bottom: 30px;
        }
        .section h2 {
            border-bottom: 2px solid #dc3545;
            padding-bottom: 5px;
            color: #dc3545;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f8f9fa;
            font-weight: bold;
            color: #495057;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        .currency-tag, .merchant-tag {
            display: inline-block;
            background: #e9ecef;
            color: #495057;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            margin: 2px;
        }
        .amount {
            font-weight: bold;
            color: #dc3545;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            font-size: 12px;
            color: #6c757d;
            text-align: center;
        }
        .no-data {
            text-align: center;
            color: #6c757d;
            font-style: italic;
            padding: 20px;
        }
        @media (max-width: 600px) {
            .summary-grid {
                grid-template-columns: 1fr;
            }
            table {
                font-size: 14px;
            }
            th, td {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>🚨 Declined Transactions Alert</h1>
    </div>

    <div class="alert">
        <strong>Alert:</strong> {{ $summaryData['summary']['total_declined'] }} declined transactions detected
        for the period {{ $summaryData['period']['start_date'] }} to {{ $summaryData['period']['end_date'] }}.
    </div>

    <!-- Summary Statistics -->
    <div class="section">
        <h2>📊 Summary</h2>
        <div class="summary-grid">
            <div class="summary-card">
                <h3>Total Declined</h3>
                <div class="value">{{ number_format($summaryData['summary']['total_declined']) }}</div>
            </div>
            <div class="summary-card">
                <h3>Total Amount</h3>
                <div class="value">{{ number_format($summaryData['summary']['total_amount'], 2) }}</div>
            </div>
            <div class="summary-card">
                <h3>Affected Merchants</h3>
                <div class="value">{{ $summaryData['summary']['unique_merchants'] }}</div>
            </div>
            <div class="summary-card">
                <h3>Currencies</h3>
                <div class="value">{{ $summaryData['summary']['unique_currencies'] }}</div>
            </div>
        </div>
    </div>

    <!-- Currency Breakdown -->
    @if(!empty($summaryData['currency_breakdown']))
        <div class="section">
            <h2>💱 Currency Breakdown</h2>
            <table>
                <thead>
                <tr>
                    <th>Currency</th>
                    <th>Count</th>
                    <th>Total Amount</th>
                    <th>% of Total</th>
                </tr>
                </thead>
                <tbody>
                @foreach($summaryData['currency_breakdown'] as $currency)
                    <tr>
                        <td><span class="currency-tag">{{ $currency['currency'] }}</span></td>
                        <td>{{ number_format($currency['count']) }}</td>
                        <td class="amount">{{ number_format($currency['total_amount'], 2) }}</td>
                        <td>{{ number_format(($currency['count'] / $summaryData['summary']['total_declined']) * 100, 1) }}%</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <!-- Merchant Breakdown -->
    @if(!empty($summaryData['merchant_breakdown']))
        <div class="section">
            <h2>🏪 Top Affected Merchants</h2>
            <table>
                <thead>
                <tr>
                    <th>Merchant</th>
                    <th>Count</th>
                    <th>Total Amount</th>
                    <th>Currencies</th>
                </tr>
                </thead>
                <tbody>
                @foreach($summaryData['merchant_breakdown'] as $merchant)
                    <tr>
                        <td>
                            {{ $merchant['merchant_name'] ?: 'Unknown' }}
                            @if($merchant['merchant_id'])
                                <br><small style="color: #6c757d;">ID: {{ $merchant['merchant_id'] }}</small>
                            @endif
                        </td>
                        <td>{{ number_format($merchant['count']) }}</td>
                        <td class="amount">{{ number_format($merchant['total_amount'], 2) }}</td>
                        <td>
                            @foreach($merchant['currencies'] as $currency)
                                <span class="currency-tag">{{ $currency }}</span>
                            @endforeach
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <!-- Decline Reasons -->
    @if(!empty($summaryData['decline_reasons']))
        <div class="section">
            <h2>❌ Common Decline Reasons</h2>
            <table>
                <thead>
                <tr>
                    <th>Reason</th>
                    <th>Count</th>
                    <th>% of Total</th>
                </tr>
                </thead>
                <tbody>
                @foreach($summaryData['decline_reasons'] as $reason)
                    <tr>
                        <td>{{ $reason['reason'] ?: 'No reason provided' }}</td>
                        <td>{{ number_format($reason['count']) }}</td>
                        <td>{{ number_format(($reason['count'] / $summaryData['summary']['total_declined']) * 100, 1) }}%</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <!-- Recent Transactions -->
    @if(!empty($summaryData['recent_transactions']))
        <div class="section">
            <h2>📋 Recent Declined Transactions</h2>
            <p style="color: #6c757d; font-size: 14px;">Showing up to 20 most recent transactions:</p>
            <table>
                <thead>
                <tr>
                    <th>Payment ID</th>
                    <th>Merchant</th>
                    <th>Amount</th>
                    <th>Date</th>
                    <th>Reason</th>
                </tr>
                </thead>
                <tbody>
                @foreach($summaryData['recent_transactions'] as $transaction)
                    <tr>
                        <td style="font-family: monospace; font-size: 12px;">{{ $transaction['payment_id'] }}</td>
                        <td>{{ $transaction['merchant_name'] ?: 'Unknown' }}</td>
                        <td class="amount">{{ number_format($transaction['amount'], 2) }} {{ $transaction['currency'] }}</td>
                        <td>{{ $transaction['transaction_date'] }}</td>
                        <td style="font-size: 12px; max-width: 200px; overflow: hidden; text-overflow: ellipsis;">
                            {{ $transaction['error_message'] ?: 'No reason provided' }}
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <!-- Period Information -->
    <div class="section">
        <h2>📅 Period Information</h2>
        <p><strong>Date Range:</strong> {{ $summaryData['period']['start_date'] }} to {{ $summaryData['period']['end_date'] }}</p>
        <p><strong>Days Checked:</strong> {{ $summaryData['period']['days_checked'] }}</p>
        <p><strong>Report Generated:</strong> {{ $summaryData['generated_at'] }}</p>
    </div>

    <div class="footer">
        <p>This is an automated notification from the Decta transaction monitoring system.</p>
        <p>Please review the declined transactions and take appropriate action if needed.</p>
    </div>
</div>
</body>
</html>
