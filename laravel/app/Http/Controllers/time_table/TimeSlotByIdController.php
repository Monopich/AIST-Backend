<?php

namespace App\Http\Controllers\time_table;

use App\Http\Controllers\Controller;
use App\Models\TimeSlot;
use App\Models\TimeTable;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
class TimeSlotByIdController extends Controller
{
       public function  getTimeSlotByTimeTableId(Request $request, $timetableId)
    {
        // Verify timetable exists
        $timeTable = TimeTable::find($timetableId);
        if (!$timeTable) {
            return response()->json(['message' => 'Time table not found'], 404);
        }

        // Get all time slots for this timetable
        $timeSlots = TimeSlot::with(['teacher:id,name,email', 'subject:id,subject_name', 'location:id,name,floor,building_id'])
            ->where('time_table_id', $timetableId)
            ->orderBy('time_slot_date')
            ->orderBy('day_of_week')
            ->get();

        if ($timeSlots->isEmpty()) {
            return response()->json([
                'message' => 'No time slots found for this time table.',
                'time_slots' => []
            ], 200);
        }

        return response()->json([
            'message' => 'Time slots retrieved successfully.',
            'time_table' => $timeTable,
            'time_slots' => $timeSlots
        ], 200);
    }
    public function getTimeSlotByGroupId(Request $request, $groupId)
    {
        // Get timetable by group_id
        $timeTable = TimeTable::where('group_id', $groupId)->first();
        
        if (!$timeTable) {
            return response()->json([
                'message' => 'No time table found for this group.'
            ], 404);
        }

        // Get all time slots for this timetable
        $timeSlots = TimeSlot::with([
                'teacher:id,name,email',
                'subject:id,subject_name',
                'location:id,name,floor,building_id'
            ])
            ->where('time_table_id', $timeTable->id)
            ->orderBy('time_slot_date')
            ->orderBy('day_of_week')
            ->get();

        if ($timeSlots->isEmpty()) {
            return response()->json([
                'message' => 'No time slots found for this group.',
                'time_table' => $timeTable,
                'time_slots' => []
            ], 200);
        }

        return response()->json([
            'message' => 'Time slots retrieved successfully.',
            'time_table' => $timeTable,
            'time_slots' => $timeSlots
        ], 200);
    }
}   