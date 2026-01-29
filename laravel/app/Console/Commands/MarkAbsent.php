<?php

namespace App\Console\Commands;

use App\Models\AttendanceTracking;
use App\Models\QrCode;
use App\Models\LeaveRequest; // New model for leave requests
use App\Models\TimeSlot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MarkAbsent extends Command
{

    protected $signature = 'attendance:mark-absent';
    protected $description = 'Mark users as absent if they did not scan within their time slots, unless on leave';

    public function handle(): void
    {
        $now = Carbon::now();
        Log::info('Starting MarkAbsent command', ['time' => $now->format('Y-m-d H:i:s')]);

        // Load all users with their groups, timetables, and slots
        $users = User::with('groups.timeTables.timeSlots')->get();

        // foreach ($users as $user) {
        //     Log::info('Processing user', ['user_id' => $user->id, 'name' => $user->name]);

        //     // Flatten all time slots for the user
        //     $slots = $user->groups
        //         ->flatMap(fn($g) => $g->timeTables)
        //         ->flatMap(fn($tt) => $tt->timeSlots);

        //     foreach ($slots as $slot) {
        //         $slotTime = json_decode($slot->time_slot, true);

        //         if (!$slotTime || !isset($slotTime['start_time'], $slotTime['end_time'])) {
        //             continue; // skip invalid slots
        //         }

        //         $slotDate = Carbon::createFromFormat('d-m-Y', $slot->time_slot_date)->toDateString();
        //         $slotStart = Carbon::parse($slotDate . ' ' . $slotTime['start_time']);
        //         $slotEnd   = Carbon::parse($slotDate . ' ' . $slotTime['end_time']);

        //         // Only process slots that have ended
        //         if ($slotEnd->gt($now)) {
        //             continue;
        //         }

        //         // Skip if attendance already exists
        //         $attendanceExists = AttendanceTracking::where([
        //             'user_id' => $user->id,
        //             'time_slot_id' => $slot->id,
        //             'attendance_date' => $slotDate,
        //         ])->exists();

        //         if ($attendanceExists) {
        //             continue;
        //         }

        //         // Check if user was on leave for this slot
        //         $leaveRequest = LeaveRequest::where('user_id', $user->id)
        //             ->where('status', 'Approved')
        //             ->whereDate('start_date', '<=', $slotDate)
        //             ->whereDate('end_date', '>=', $slotDate)
        //             ->first();

        //         if ($leaveRequest) {
        //             AttendanceTracking::create([
        //                 'user_id' => $user->id,
        //                 'time_slot_id' => $slot->id,
        //                 'attendance_date' => $slotDate,
        //                 'status' => 'On Leave',
        //                 'qr_code_id' => null,
        //                 'scanned_at' => null,
        //                 'device' => null,
        //                 'leave_request_id' => $leaveRequest->id,
        //             ]);
        //         } else {
        //             AttendanceTracking::create([
        //                 'user_id' => $user->id,
        //                 'time_slot_id' => $slot->id,
        //                 'attendance_date' => $slotDate,
        //                 'status' => 'Absent',
        //                 'qr_code_id' => QrCode::where('location_id', $slot->location_id)->first()->id ?? null,
        //                 'scanned_at' => null,
        //                 'device' => null,
        //             ]);
        //         }

        //         Log::info('Attendance recorded', [
        //             'user_id' => $user->id,
        //             'slot_id' => $slot->id,
        //             'slot' => $slot,
        //             'date' => $slotDate,
        //             'status' => $leaveRequest ? 'On Leave' : 'Absent'
        //         ]);
        //     }
        // }
        foreach ($users as $user) {
            Log::info('Processing user', ['user_id' => $user->id, 'name' => $user->name]);

            // 1. Get slots for this user
            if ($user->hasRole('Student')) {
                $slots = $user->groups
                    ->flatMap(fn($g) => $g->timeTables)
                    ->flatMap(fn($tt) => $tt->timeSlots);
            } elseif ($user->hasRole('Staff')) {
                $slots = TimeSlot::where('teacher_id', $user->id)->get();
            } else {
                continue; // skip if not student/teacher
            }

            // 2. Process each slot
            foreach ($slots as $slot) {
                $slotTime = json_decode($slot->time_slot, true);
                if (!$slotTime || !isset($slotTime['start_time'], $slotTime['end_time'])) {
                    continue;
                }

                $slotDate = Carbon::createFromFormat('d-m-Y', $slot->time_slot_date)->toDateString();
                $slotStart = Carbon::parse($slotDate . ' ' . $slotTime['start_time']);
                $slotEnd = Carbon::parse($slotDate . ' ' . $slotTime['end_time']);

                if ($slotEnd->gt($now)) {
                    continue; // skip ongoing slots
                }

                // Skip if attendance already exists
                $attendanceExists = AttendanceTracking::where([
                    'user_id' => $user->id,
                    'time_slot_id' => $slot->id,
                    'attendance_date' => $slotDate,
                ])->exists();

                if ($attendanceExists) {
                    continue;
                }

                if ($user->hasRole('Student')) {
                    // Check if teacher is on leave
                    $teacherLeave = LeaveRequest::where('user_id', $slot->teacher_id)
                        ->where('status', 'Approved')
                        ->whereDate('start_date', '<=', $slotDate)
                        ->whereDate('end_date', '>=', $slotDate)
                        ->first();

                    if ($teacherLeave) {
                        // Mark student attendance as "No Class"
                        AttendanceTracking::create([
                            'user_id' => $user->id,
                            'time_slot_id' => $slot->id,
                            'attendance_date' => $slotDate,
                            'status' => 'No class',
                            'qr_code_id' => null,
                            'scanned_at' => null,
                            'device' => null,
                            'leave_request_id' => $teacherLeave->id ?? null,
                        ]);

                        Log::info('Attendance recorded as No Class due to teacher leave', [
                            'user_id' => $user->id,
                            'slot_id' => $slot->id,
                            'slot_date' => $slotDate,
                            'teacher_id' => $slot->teacher_id
                        ]);
                        continue; // skip further checks for student
                    }

                    // Check leave request for the student
                    $leaveRequest = LeaveRequest::where('user_id', $user->id)
                        ->where('status', 'Approved')
                        ->whereDate('start_date', '<=', $slotDate)
                        ->whereDate('end_date', '>=', $slotDate)
                        ->first();

                    AttendanceTracking::create([
                        'user_id' => $user->id,
                        'time_slot_id' => $slot->id,
                        'attendance_date' => $slotDate,
                        'status' => $leaveRequest ? 'On Leave' : 'Absent',
                        'qr_code_id' => $user->role === 'student'
                            ? QrCode::where('location_id', $slot->location_id)->first()->id ?? null
                            : null, // teachers may not scan QR
                        'scanned_at' => null,
                        'device' => null,
                        'leave_request_id' => $leaveRequest->id ?? null,
                    ]);

                    Log::info('Attendance recorded', [
                        'user_id' => $user->id,
                        'slot_id' => $slot->id,
                        'date' => $slotDate,
                        'status' => $leaveRequest ? 'On Leave' : 'Absent'
                    ]);
                } elseif ($user->hasRole('Staff')) {
                    // Teacher own leave
                    $teacherLeave = LeaveRequest::where('user_id', $user->id)
                        ->where('status', 'Approved')
                        ->whereDate('start_date', '<=', $slotDate)
                        ->whereDate('end_date', '>=', $slotDate)
                        ->first();

                    AttendanceTracking::create([
                        'user_id' => $user->id,
                        'time_slot_id' => $slot->id,
                        'attendance_date' => $slotDate,
                        'status' => $teacherLeave ? 'On Leave' : 'Absent',
                        'qr_code_id' => null,
                        'scanned_at' => null,
                        'device' => null,
                        'leave_request_id' => $teacherLeave->id ?? null,
                    ]);
                }
            }
        }

        Log::info('MarkAbsent command finished');
        $this->info('MarkAbsent completed successfully.');
    }

}
