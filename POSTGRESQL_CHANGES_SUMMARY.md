# PostgreSQL Migration Changes Summary

## Overview
The following changes have been implemented to make the codebase compatible with PostgreSQL while keeping the external payment_gateway database on MariaDB.

## 1. Command Line Tools

### TruncateMariaDBTables Command
- Updated the default connection from `mariadb` to `pgsql`
- Added database-specific table listing:
  - MariaDB: `SHOW TABLES`
  - PostgreSQL: `SELECT table_name FROM information_schema.tables`
- Added database-specific foreign key handling:
  - MariaDB: `SET FOREIGN_KEY_CHECKS=0/1`
  - PostgreSQL: `SET session_replication_role = 'replica'/'origin'`
- Added special handling for payment_gateway_mysql to recognize it as a MariaDB connection

## 2. SQL Function Compatibility

### Date Formatting
- Replaced `DATE_FORMAT(date, "%Y-%m")` with database-specific syntax:
  - PostgreSQL: `TO_CHAR(date, 'YYYY-MM')`
  - MariaDB kept as is
- Updated all DB::raw calls in MerchantAnalytics.php to handle both database types

### Date Extraction
- Replaced `DATE(column)` with database-specific syntax:
  - PostgreSQL: `column::date`
  - MariaDB kept as is
- Added conditional logic to determine database type

### Numeric Types
- Updated `CAST` syntax for numeric types:
  - MariaDB: `CAST(x AS DECIMAL(12,2))`
  - PostgreSQL: `CAST(x AS NUMERIC(12,2))`

## 3. Special Handling for External Database

- All queries to `payment_gateway_mysql` connection were left untouched regarding query structure
- Added conditional logic to ensure SQL syntax is compatible with the connection's database type
- Added comments to clarify that payment_gateway_mysql will always be MariaDB

## 4. Files Modified

1. `/app/Console/Commands/TruncateMariaDBTables.php`
   - Updated to handle both PostgreSQL and MariaDB table listing and foreign key constraints

2. `/app/Livewire/MerchantAnalytics.php`
   - Updated date formatting functions to be database-agnostic
   - Modified three different SQL queries to handle PostgreSQL syntax

3. `/app/Repositories/TransactionRepository.php`
   - Modified transaction date extraction and CAST operations
   - Updated exchange rate queries to handle PostgreSQL syntax

4. `/app/Services/Settlement/SchemeRateValidationService.php`
   - Added comment to clarify that no changes are needed since it always uses MariaDB

## Next Steps for Full Migration

1. Create a PostgreSQL database with appropriate credentials
2. Update `.env` file to use PostgreSQL as the default connection
3. Run migrations to create the schema in PostgreSQL
4. Import data from MariaDB to PostgreSQL
5. Test the application thoroughly

## Note on payment_gateway_mysql

The payment_gateway_mysql connection remains unchanged and continues to use MariaDB. All SQL queries that use this connection have been updated to check the connection type, but since this will always be MariaDB, the SQL syntax will remain compatible.