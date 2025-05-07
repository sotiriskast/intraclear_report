# Local Database Migration to PostgreSQL

## Overview
The codebase has been updated to support migration from the local MariaDB database to PostgreSQL, while keeping the external payment gateway database on MariaDB.

## Changes Made

### 1. Use Dynamic Database Connection

All hardcoded references to `DB::connection('mariadb')` have been replaced with `DB::connection('pgsql')` to make the codebase more flexible. This will automatically use whatever database connection is set as default in your config.

### Files Modified

1. **MerchantSyncService.php**
   - Updated transaction handling to use the default database connection
   - Modified all database queries to use the default connection

2. **ExcelExportService.php**
   - Updated merchant lookup query to use the default database connection

3. **GenerateSettlementReports.php**
   - Updated all database queries to use the default database connection
   - Modified storage of reports and archives to work with PostgreSQL

4. **SettlementController.php**
   - Updated all database queries to use the default database connection
   - Modified report and archive lookup to be database-agnostic

5. **SettlementReportsList.php**
   - Updated database connection for report listing

### 2. SQL Compatibility Updates

In addition to these changes, several SQL-specific functions were updated to work with both MariaDB and PostgreSQL:
- Date formatting functions (`DATE_FORMAT` → `TO_CHAR` in PostgreSQL)
- Date extraction functions (`DATE()` → `column::date` in PostgreSQL)
- Data type modifications (`DECIMAL` → `NUMERIC` in PostgreSQL)
- Foreign key handling (`SET FOREIGN_KEY_CHECKS` → `SET session_replication_role` in PostgreSQL)

## Database Connection Configuration

The codebase now checks the database driver type at runtime using:
```php
DB::connection()->getDriverName() === 'pgsql'
```

This allows conditional SQL syntax that works with either database type.

## Next Steps for Migration

1. Install PostgreSQL and the required PHP extensions:
   ```
   sudo apt-get install postgresql postgresql-contrib php-pgsql
   ```

2. Create a new PostgreSQL database:
   ```
   sudo -u postgres psql
   CREATE DATABASE intraclear;
   CREATE USER intraclear_user WITH PASSWORD 'your_secure_password';
   GRANT ALL PRIVILEGES ON DATABASE intraclear TO intraclear_user;
   ```

3. Update your `.env` file:
   ```
   DB_CONNECTION=pgsql
   DB_HOST=127.0.0.1
   DB_PORT=5432
   DB_DATABASE=intraclear
   DB_USERNAME=intraclear_user
   DB_PASSWORD=your_secure_password
   ```

4. Run the migrations to create your schema in PostgreSQL:
   ```
   php artisan migrate:fresh
   ```

5. If you have existing data, use a migration tool or custom script to move data from MariaDB to PostgreSQL.

## Note on External Payment Gateway

The `payment_gateway_mysql` connection remains unchanged and will continue to use MariaDB. All queries that use this connection have been updated to check the connection type at runtime, but the external database will remain on MariaDB as specified.
