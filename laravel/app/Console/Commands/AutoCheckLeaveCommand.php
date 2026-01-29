<?php

namespace App\Console\Commands;

use App\Models\LeaveRequest;
use Illuminate\Console\Command;
use Carbon\Carbon;

class AutoCheckLeaveCommand extends Command
{
    protected $signature = 'leave:auto-check';
    protected $description = 'Automatically reject pending leaves when end_date has passed';

    public function handle()
    {
        $today = now()->format('d-m-Y');
        $todayCarbon = Carbon::createFromFormat('d-m-Y', $today);

        // Pending → Rejected when END DATE has passed
        $pending = LeaveRequest::where('status', 'Pending')->get();

        foreach ($pending as $leave) {
            try {
                $start = Carbon::createFromFormat('d-m-Y', $leave->start_date);
            } catch (\Exception $e) {
                $this->error("Invalid date format for ID {$leave->id}: {$leave->start_date}");
                continue;
            }

            if ($todayCarbon->gt($start->copy())) {
            $leave->update([
                'status' => 'Rejected',
                'approved_at' => null,
                'approved_by' => null,
            ]);

        }
        }

        $this->info("Auto-check completed. Total auto rejected: " . $pending->count());
    }
}
