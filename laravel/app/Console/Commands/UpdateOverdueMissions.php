<?php

namespace App\Console\Commands;

use App\Models\Mission;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateOverdueMissions extends Command
{
   protected $signature = 'missions:update-overdue';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically update missions to in_progress or overdue based on dates';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking mission statuses by date...');

        // Use app timezone (set in config/app.php) and compare as DATE
        $today = Carbon::now()->toDateString();

        DB::beginTransaction();

        try {
            /**
             * Rule 1: pending OR in_progress -> overdue (if due_date < today)
             * (This ensures in_progress missions become overdue automatically when late.)
             */
            $overdueCount = Mission::query()
                ->whereIn('status', ['pending', 'in_progress'])
                ->whereDate('due_date', '<', $today)
                ->update([
                    'status' => 'overdue',
                ]);

            /**
             * Rule 2: pending -> in_progress
             * when assigned_date <= today AND due_date >= today
             */
            $inProgressCount = Mission::query()
                ->where('status', 'pending')
                ->whereDate('assigned_date', '<=', $today)
                ->whereDate('due_date', '>=', $today)
                ->update([
                    'status' => 'in_progress',
                    // Optional: 'status_updated_at' => now(),
                ]);

            DB::commit();

            if ($overdueCount === 0 && $inProgressCount === 0) {
                $this->info('No missions needed updates.');
            } else {
                $this->info("Updated {$overdueCount} mission(s) to overdue and {$inProgressCount} mission(s) to in_progress.");
            }

            Log::info('Mission status auto-update completed', [
                'run_at' => now()->toDateTimeString(),
                'today' => $today,
                'overdue' => $overdueCount,
                'in_progress' => $inProgressCount,
            ]);

            return Command::SUCCESS;

        } catch (\Throwable $e) {
            DB::rollBack();

            $this->error('Failed to update mission statuses: ' . $e->getMessage());

            Log::error('Mission status auto-update failed', [
                'run_at' => now()->toDateTimeString(),
                'today' => $today,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }
}
