<?php

namespace App\Console\Commands;

use App\Models\CleaningActivity;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FixActivityDates extends Command
{
    protected $signature = 'fix:activity-dates {--dry-run : Show what would be changed without making changes}';
    protected $description = 'Fix activity dates that were stored with wrong timezone (UTC instead of Asia/Makassar)';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $tz = config('app.timezone', 'Asia/Makassar');

        $this->info("Timezone: {$tz}");
        $this->info($dryRun ? '🔍 DRY RUN - no changes will be made' : '🔧 LIVE RUN - fixing dates...');
        $this->newLine();

        // Find activities where the date doesn't match submitted_at in the correct timezone
        $activities = CleaningActivity::whereNotNull('submitted_at')->get();

        $fixed = 0;
        $skipped = 0;

        foreach ($activities as $activity) {
            $submittedAt = Carbon::parse($activity->submitted_at)->timezone($tz);
            $correctDate = $submittedAt->toDateString();
            $storedDate = Carbon::parse($activity->date)->toDateString();

            if ($storedDate !== $correctDate) {
                $this->warn(
                    "ID {$activity->id} | Area: {$activity->area_id} | " .
                    "Stored: {$storedDate} → Correct: {$correctDate} | " .
                    "Submitted at: {$activity->submitted_at}"
                );

                if (!$dryRun) {
                    $activity->update(['date' => $correctDate]);
                }

                $fixed++;
            } else {
                $skipped++;
            }
        }

        $this->newLine();
        $this->info("✅ Fixed: {$fixed} | Skipped (already correct): {$skipped}");

        if ($dryRun && $fixed > 0) {
            $this->warn("Run without --dry-run to apply changes.");
        }

        return Command::SUCCESS;
    }
}
