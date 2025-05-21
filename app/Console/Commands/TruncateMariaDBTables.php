<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TruncateMariaDBTables extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'intraclear:truncate-tables
                            {--connection=pgsql : Database connection to use}
                            {--force : Force truncation without confirmation}';

    /**
     * The console command description.
     * @var string
     */
    protected $description = 'Truncate settlement and related tables while preserving user and permission data';

    /**
     * Tables that should never be truncated
     * @var array
     */
    protected $protectedTables = [
        'users',
        'roles',
        'permissions',
        'migrations',
        'fee_types',
        'role_has_permissions',
        'model_has_permissions',
        'model_has_roles',
//        'merchant_settings',
//        'merchant_fees',
//        'merchants'
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $connection = $this->option('connection');
        $force = $this->option('force');

        // Get all tables based on database type
        $allTables = [];

        if ($connection === 'pgsql') {
            // PostgreSQL approach
            $tables = DB::connection($connection)->select("
                SELECT table_name
                FROM information_schema.tables
                WHERE table_schema = 'public' AND table_type = 'BASE TABLE'
            ");
            $allTables = array_map(function($table) {
                return $table->table_name;
            }, $tables);
        } else {
            // MariaDB/MySQL approach
            $tables = DB::connection($connection)->select('SHOW TABLES');
            $databaseName = DB::connection($connection)->getDatabaseName();
            $tableKey = 'Tables_in_' . $databaseName;
            $allTables = array_map(function($table) use ($tableKey) {
                return $table->$tableKey;
            }, $tables);
        }

        // Filter out protected tables
        $tablesToTruncate = array_filter($allTables, function($table) use ($connection) {
            if ($connection === 'pgsql') {
                // PostgreSQL is case-sensitive
                return !in_array($table, $this->protectedTables);
            } else {
                // MariaDB/MySQL is case-insensitive
                return !in_array(strtolower($table), array_map('strtolower', $this->protectedTables));
            }
        });

        if (empty($tablesToTruncate)) {
            $this->error('No tables to truncate!');
            return 1;
        }

        // Display tables that will be truncated
        $this->info('The following tables will be truncated:');
        $this->line(implode(PHP_EOL, $tablesToTruncate));

        // Display tables that are protected
        $this->info('The following tables are protected and will NOT be truncated:');
        $this->line(implode(PHP_EOL, $this->protectedTables));

        // Ask for confirmation unless --force is provided
        if (!$force && !$this->confirm('Do you really want to truncate the listed tables?')) {
            $this->info('Operation cancelled.');
            return 1;
        }

        // Disable foreign key checks based on database type
        if ($connection === 'pgsql') {
            // PostgreSQL: Disable triggers (which include foreign key constraints)
//            DB::connection($connection)->statement('SET session_replication_role = \'replica\'');
        } else {
            // MySQL/MariaDB
            DB::connection($connection)->statement('SET FOREIGN_KEY_CHECKS=0');
        }

        $count = 0;
        $errors = [];

        $this->output->progressStart(count($tablesToTruncate));

        foreach ($tablesToTruncate as $table) {
            try {
                if ($connection === 'pgsql') {
                    // PostgreSQL: Use RESTART IDENTITY to reset auto-increment sequences
                    DB::connection($connection)->statement("TRUNCATE TABLE \"{$table}\" RESTART IDENTITY CASCADE");
                } else {
                    // MySQL/MariaDB
                    DB::connection($connection)->table($table)->truncate();
                }
                $count++;
            } catch (\Exception $e) {
                $errors[$table] = $e->getMessage();
            }
            $this->output->progressAdvance();
        }

        $this->output->progressFinish();

        // Re-enable foreign key checks based on database type
        if ($connection === 'pgsql') {
            // PostgreSQL: Re-enable triggers
//            DB::connection($connection)->statement('SET session_replication_role = \'origin\'');
        } else {
            // MySQL/MariaDB
            DB::connection($connection)->statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->info("Successfully truncated {$count} out of " . count($tablesToTruncate) . " tables.");

        if (!empty($errors)) {
            $this->error("Errors occurred while truncating the following tables:");
            foreach ($errors as $table => $error) {
                $this->line("{$table}: {$error}");
            }
        }

        return 0;
    }
}
