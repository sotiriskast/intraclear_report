# Decta Module Implementation Summary

## Overview

The Decta module has been enhanced with comprehensive transaction processing and matching capabilities. The system now can:

1. Download yesterday's transaction files automatically
2. Process CSV files and store transaction data in the database
3. Match Decta transactions with payment gateway transactions
4. Provide detailed monitoring and reporting
5. Handle error recovery and system maintenance

## Database Schema

### New Tables Created:
- `decta_transactions` - Stores individual transaction records from CSV files
- `decta_files` - Enhanced to track file processing status and metadata

### Key Features:
- Full transaction data storage with proper indexing
- Transaction matching status tracking
- Relationship between files and their transactions
- Soft delete support for data recovery
- JSON metadata storage for flexible data

## Available Commands

### Core Processing Commands

#### 1. Download Files
```bash
# Download yesterday's files (default)
php artisan decta:download-files

# Download files for specific date
php artisan decta:download-files --date=2025-05-22

# Force download even if already processed
php artisan decta:download-files --force

# Look back more days if yesterday's file not found
php artisan decta:download-files --days-back=14
```

#### 2. Process Files
```bash
# Process all pending files
php artisan decta:process-files

# Process specific file
php artisan decta:process-files --file-id=123

# Skip transaction matching (just process CSV)
php artisan decta:process-files --skip-matching

# Retry failed files
php artisan decta:process-files --retry-failed
```

#### 3. Match Transactions
```bash
# Match unmatched transactions
php artisan decta:match-transactions

# Match transactions for specific file
php artisan decta:match-transactions --file-id=123

# Retry failed matches
php artisan decta:match-transactions --retry-failed

# Force re-match all transactions
php artisan decta:match-transactions --force
```

### Monitoring and Maintenance Commands

#### 4. Status Report
```bash
# Basic status report
php artisan decta:status

# Detailed statistics
php artisan decta:status --detailed

# Status for specific file
php artisan decta:status --file-id=123

# Export report to JSON
php artisan decta:status --export=json

# Analyze last 30 days
php artisan decta:status --days=30
```

#### 5. Cleanup
```bash
# Clean up old records (90+ days)
php artisan decta:cleanup

# Also remove physical files
php artisan decta:cleanup --remove-files --remove-processed

# Clean unmatched transactions
php artisan decta:cleanup --remove-unmatched

# Reset stuck files
php artisan decta:cleanup --reset-stuck

# Dry run (show what would be cleaned)
php artisan decta:cleanup --dry-run
```

### Testing Commands

#### 6. Test Connection
```bash
# Test SFTP connection
php artisan decta:test-connection
```

#### 7. Test Latest File
```bash
# Find and optionally download latest file
php artisan decta:test-latest-file --download
```

## Automated Scheduling

The system automatically schedules:

- **Daily at 2 AM**: Download yesterday's files
- **Daily at 3 AM**: Process downloaded files
- **Every 4 hours**: Retry failed transaction matching
- **Every 6 hours**: Retry failed file processing
- **Weekly on Sunday 1 AM**: Cleanup old records

## Transaction Matching Logic

The system uses multiple strategies to match Decta transactions with payment gateway data:

### Primary Strategy: Approval ID + Amount
- Matches on `tr_approval_id` and exact `tr_amount`
- Most reliable matching method

### Secondary Strategy: Return Reference Number
- Matches on `tr_ret_ref_nr` when available
- Good for unique reference tracking

### Tertiary Strategy: Amount + Date + Currency
- Matches on amount, transaction date, and currency
- Used when other identifiers aren't available

### Fallback Strategy: Fuzzy Matching
- Time-based proximity matching
- Multiple criteria scoring system

## Error Handling and Recovery

### File Processing Errors
- Files marked as failed with error messages
- Automatic retry mechanism
- Stuck file detection and reset
- Physical file management (processed/failed directories)

### Transaction Matching Errors
- Failed match attempts tracked with details
- Maximum retry limits (configurable)
- Manual matching capability via dashboard
- Bulk operations for transaction management

### System Health Monitoring
- Database connection monitoring
- Disk space checking
- Memory usage tracking
- Processing time analysis

## Dashboard and Monitoring

### Available Endpoints
- `/decta/dashboard` - Main dashboard
- `/decta/dashboard/file/{id}` - File details
- `/decta/dashboard/transaction/{id}` - Transaction details
- API endpoints for statistics and health checks

### Key Metrics Tracked
- File processing success rates
- Transaction matching rates
- Processing times
- Error patterns
- Currency breakdowns
- Daily/weekly trends

## Configuration

### SFTP Settings (`.env`)
```env
DECTA_SFTP_HOST=files.decta.com
DECTA_SFTP_PORT=822
DECTA_SFTP_USERNAME=INTCL
DECTA_SFTP_PRIVATE_KEY_PATH=/path/to/decta_rsa
DECTA_SFTP_REMOTE_PATH=in_file/reports
DECTA_SFTP_LOCAL_PATH=decta/files
```

### Database Connections
- Main database: Default Laravel connection
- Payment Gateway: `payment_gateway_mysql` connection

## Best Practices

### Daily Operations
1. Check system status: `php artisan decta:status`
2. Monitor failed files/transactions
3. Review matching rates
4. Check for stuck processes

### Weekly Maintenance
1. Run cleanup: `php artisan decta:cleanup --dry-run` (then without dry-run)
2. Review error patterns
3. Check disk space usage
4. Verify automated schedules are running

### Troubleshooting
1. Test SFTP connection: `php artisan decta:test-connection`
2. Check latest files: `php artisan decta:test-latest-file`
3. Reset stuck files: `php artisan decta:cleanup --reset-stuck`
4. Review logs in `storage/logs/decta-*.log`

## Security Considerations

- SFTP private key file permissions should be 600
- Database credentials should be secured
- Log files may contain sensitive transaction data
- API endpoints should be protected with authentication
- File storage directories should not be web-accessible

## Performance Optimization

- Database indexes on frequently queried fields
- Batch processing for large files
- Connection pooling for database operations
- Efficient memory usage for large CSV files
- Background job processing for heavy operations

## Extensibility

The system is designed to be extensible:
- New matching strategies can be added
- Additional file formats can be supported
- Custom fee calculation logic can be integrated
- New monitoring metrics can be added
- API endpoints can be extended
