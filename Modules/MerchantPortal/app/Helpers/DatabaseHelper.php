<?php
namespace Modules\MerchantPortal\Helpers;

use Illuminate\Support\Facades\DB;

class DatabaseHelper
{
    /**
     * Get the appropriate MONTH function for the current database driver
     */
    public static function monthFunction(string $column): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'mysql' => "MONTH($column)",
            'pgsql' => "EXTRACT(MONTH FROM $column)",
            'sqlite' => "strftime('%m', $column)",
            'sqlsrv' => "MONTH($column)",
            default => "EXTRACT(MONTH FROM $column)",
        };
    }

    /**
     * Get the appropriate YEAR function for the current database driver
     */
    public static function yearFunction(string $column): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'mysql' => "YEAR($column)",
            'pgsql' => "EXTRACT(YEAR FROM $column)",
            'sqlite' => "strftime('%Y', $column)",
            'sqlsrv' => "YEAR($column)",
            default => "EXTRACT(YEAR FROM $column)",
        };
    }

    /**
     * Get the appropriate DATE function for the current database driver
     */
    public static function dateFunction(string $column): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'mysql' => "DATE($column)",
            'pgsql' => "DATE($column)",
            'sqlite' => "DATE($column)",
            'sqlsrv' => "CAST($column AS DATE)",
            default => "DATE($column)",
        };
    }

    /**
     * Get the appropriate HOUR function for the current database driver
     */
    public static function hourFunction(string $column): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'mysql' => "HOUR($column)",
            'pgsql' => "EXTRACT(HOUR FROM $column)",
            'sqlite' => "strftime('%H', $column)",
            'sqlsrv' => "DATEPART(HOUR, $column)",
            default => "EXTRACT(HOUR FROM $column)",
        };
    }
}
