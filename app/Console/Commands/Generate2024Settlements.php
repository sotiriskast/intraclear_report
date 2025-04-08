<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class Generate2024Settlements extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'intraclear:generate-2024-settlements
                            {--dry-run : Show commands without executing them}
                            {--merchant= : Optional merchant ID to filter by}
                            {--delay=2 : Delay in seconds between executions}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate settlement reports for each completed week of 2024 up to today';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $year = 2024;

        $this->info("Generating weekly settlement reports for $year up to the current date...");

        // Get optional merchant ID
        $merchantOption = $this->option('merchant') ? ['--merchant-id' => $this->option('merchant')] : [];

        // Find the first Monday of 2024 (January 1st, 2024 is a Monday)
        $date = Carbon::createFromDate(2024, 1, 1);

        // Make sure we're starting on a Monday
        if ($date->dayOfWeek !== CarbonInterface::MONDAY) {
            $date = $date->next(CarbonInterface::MONDAY);
        }

        // Get the last completed Sunday (today if today is Sunday, otherwise the previous Sunday)
        $now = Carbon::now();
        $lastSunday = $now->dayOfWeek === CarbonInterface::SUNDAY
            ? $now->copy()
            : $now->copy()->previous(CarbonInterface::SUNDAY);

        $this->info("First Monday of $year: " . $date->format('Y-m-d'));
        $this->info("Last completed Sunday: " . $lastSunday->format('Y-m-d'));
        $this->newLine();

        // Define all the Monday to Sunday periods in 2024 up to the last completed Sunday
        $periods = [];
        while ($date->year === $year && $date->lte($lastSunday)) {
            $monday = $date->copy();
            $sunday = $monday->copy()->addDays(6);

            // If this Sunday is in the future, we don't include it
            if ($sunday->gt($lastSunday)) {
                break;
            }

            $periods[] = [
                'start' => $monday->format('Y-m-d'),
                'end' => $sunday->format('Y-m-d'),
                'week' => $monday->weekOfYear
            ];

            $date->addWeek();
        }

        $totalWeeks = count($periods);
        $this->info("Total completed weeks in $year so far: $totalWeeks");

        if ($totalWeeks === 0) {
            $this->warn("No completed weeks found to process.");
            return 0;
        }

        // Create a progress bar
        $bar = $this->output->createProgressBar($totalWeeks);
        $bar->start();

        $successCount = 0;
        $failCount = 0;

        foreach ($periods as $index => $period) {
            $weekNumber = $period['week'];
            $startDate = $period['start'];
            $endDate = $period['end'];

            // Command options
            $options = array_merge([
                '--start-date' => $startDate,
                '--end-date' => $endDate,
            ], $merchantOption);

            // Only show detailed output if in dry run mode
            if ($this->option('dry-run')) {
                $this->info("Week $weekNumber: $startDate to $endDate");
                $this->line("Command: php artisan intraclear:settlement-generate --start-date=$startDate --end-date=$endDate" .
                    (isset($merchantOption['--merchant-id']) ? " --merchant-id=" . $merchantOption['--merchant-id'] : ""));

                // Simulate progress
                $bar->advance();
                continue;
            }

            // Execute the command
            try {
                $this->comment("\nProcessing Week $weekNumber: $startDate to $endDate");

                // Execute the settlement generation command
                $exitCode = Artisan::call('intraclear:settlement-generate', $options);

                // Display the output from the command
                $output = Artisan::output();

                if ($exitCode === 0) {
                    $this->line("✓ Successfully generated report for Week $weekNumber");
                    $successCount++;
                } else {
                    $this->error("✗ Failed to generate report for Week $weekNumber (Exit code: $exitCode)");
                    $this->line("Output: " . $output);
                    $failCount++;

                    // Log the error
                    Log::error("Failed to generate settlement report", [
                        'week' => $weekNumber,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'exit_code' => $exitCode,
                        'output' => $output
                    ]);
                }

                // Advance the progress bar
                $bar->advance();

                // Add a delay between executions to avoid overloading the system
                if ($index < $totalWeeks - 1) {
                    $delay = $this->option('delay');
                    $this->comment("Waiting $delay seconds before next execution...");
                    sleep($delay);
                }
            } catch (\Exception $e) {
                $bar->clear();
                $this->error("Exception processing week $weekNumber ($startDate to $endDate): " . $e->getMessage());
                $bar->display();
                $failCount++;

                // Log the exception
                Log::error("Exception during settlement report generation", [
                    'week' => $weekNumber,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        $bar->finish();
        $this->newLine(2);

        if ($this->option('dry-run')) {
            $this->info("Dry run completed. $totalWeeks weeks would be processed.");
        } else {
            $this->info("Completed generating settlement reports for $year up to " . $lastSunday->format('Y-m-d') . ".");
            $this->line("Successful: $successCount | Failed: $failCount | Total: $totalWeeks");
        }

        return 0;
    }
}
