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
                            {--connection=mariadb : Database connection to use}
                            {--force : Force truncation without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Truncate settlement and related tables while preserving user and permission data';

    /**
     * Tables that should never be truncated
     *
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
        'model_has_roles'
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $connection = $this->option('connection');
        $force = $this->option('force');

        // Get all tables using DB facade instead of Schema
        $tables = DB::connection($connection)
            ->select('SHOW TABLES');

        // Extract table names from the result
        $tableKey = 'Tables_in_' . DB::connection($connection)->getDatabaseName();
        $allTables = array_map(function($table) use ($tableKey) {
            return $table->$tableKey;
        }, $tables);

        // Filter out protected tables (case-insensitive comparison)
        $tablesToTruncate = array_filter($allTables, function($table) {
            return !in_array(strtolower($table), array_map('strtolower', $this->protectedTables));
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

        // Disable foreign key checks to avoid constraint errors
        DB::connection($connection)->statement('SET FOREIGN_KEY_CHECKS=0');

        $count = 0;
        $errors = [];

        $this->output->progressStart(count($tablesToTruncate));

        foreach ($tablesToTruncate as $table) {
            try {
                DB::connection($connection)->table($table)->truncate();
                $count++;
            } catch (\Exception $e) {
                $errors[$table] = $e->getMessage();
            }
            $this->output->progressAdvance();
        }

        $this->output->progressFinish();

        // Re-enable foreign key checks
        DB::connection($connection)->statement('SET FOREIGN_KEY_CHECKS=1');

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
