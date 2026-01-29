<?php

namespace App\Http\Controllers\time_table;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Group;
use App\Models\Program;
use App\Models\Semester;
use App\Models\SubDepartment;
use App\Models\TimeSlot;
use App\Models\TimeTable;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TimeTableController extends Controller
{
    public function createTimeTable(Request $request)
    {

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'group_id' => 'required|exists:groups,id',
        ]);

        $group = Group::findOrFail($validated['group_id']);


        // Check if this semester already has a timetable
        // $exists = TimeTable::whereHas('group', function ($query) use ($group) {
        //     $query->where('semester_id', $group->semester_id);

        // })->exists();
        $semester = $group->semester;

        if (!$semester) {
            return response()->json([
                'message' => 'This group is not linked to any semester.',
            ], 422);
        }
        // $exists = TimeTable::whereHas('group', function ($q) use ($semester) {
        //     $q->where('semester_id', $semester->id);
        // })->exists();
        $existingTimeTable = $semester->timeTables()
            ->with(['group', 'timeSlots'])
            ->where('group_id', $group->id)
            ->first();


        if ($existingTimeTable) {
            return response()->json([
                'message' => 'A timetable already exists for this semester.',
                'time_table' => $existingTimeTable,
                'group' => $group
            ], 409);
        }

        $timeTable = TimeTable::create([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'group_id' => $validated['group_id'],
        ]);

        return response()->json([
            'message' => 'Time table created successfully',
            'time_table' => $timeTable,
            'group' => $group
        ]);
    }

    public function getAllTimeTables(Request $request)
    {
        $perPage = $request->input('per_page', 14);
        $semester_id = $request->input('semester_id');
        $group_id = $request->input('group_id');

        $timeTables = TimeTable::with(['group:id,name,program_id'])
            ->whereHas('group', function ($query) use ($semester_id, $group_id) {

                if ($semester_id) {
                    $query->where('semester_id', $semester_id);
                }

                if ($group_id) {
                    $query->where('id', $group_id);
                }
            })
            ->paginate($perPage);

        if ($timeTables->isEmpty()) {
            return response()->json([
                'message' => 'No time tables found for the given criteria.',
                'time_tables' => $timeTables
            ], 404);
        }

        return response()->json([
            'message' => 'Retrieving time tables.',
            'time_tables' => $timeTables
        ], 200);
    }

    public function deleteTimeTable($id)
    {
        $timeTable = TimeTable::find($id);

        if (!$timeTable) {
            return response()->json([
                'message' => 'Time table not found.'
            ], 404);
        }

        $timeTable->delete();

        return response()->json([
            'message' => 'Time table deleted successfully.'
        ], 200);
    }

    public function getTimeTableById($id)
    {
        $timeTable = TimeTable::with([
            'group',
            'group.semester:id,semester_number,semester_key,academic_year_id',
            'group.program:id,program_name,degree_level',
            'group.program:id.generation',
            'group.semester.academicYear',
            'group.subDepartment:id,name',
            'group.students:id,name,email',
            'timeSlots',
        ])->find($id);



        if (!$timeTable) {
            return response()->json([
                'message' => 'Time table not found.'
            ], 404);
        }

        $response = [
            'message' => 'Time table retrieved successfully.',
            'time_table' => [
                'id' => $timeTable->id,
                'name' => $timeTable->name,
                'description' => $timeTable->description,
                'group_id' => $timeTable->group_id,
                'group' => [
                    'id' => optional($timeTable->group)->id,
                    'name' => optional($timeTable->group)->name,
                    'semester_id' => optional($timeTable->group)->semester_id,
                    'program_id' => optional($timeTable->group)->program_id,
                    'department_id' => optional($timeTable->group)->department_id,
                    'sub_department_id' => optional($timeTable->group)->sub_department_id,
                    'description' => optional($timeTable->group)->description,
                    'semester' => [
                        'id' => optional(optional($timeTable->group)->semester)->id,
                        'semester_number' => optional(optional($timeTable->group)->semester)->semester_number,
                        'semester_key' => optional(optional($timeTable->group)->semester)->semester_key,
                    ],
                    'academic_year' => [
                        'id' => optional(optional(optional($timeTable->group)->semester)->academicYear)->id,
                        'year_label' => optional(optional(optional($timeTable->group)->semester)->academicYear)->year_label,
                        'dates' => optional(optional(optional($timeTable->group)->semester)->academicYear)->dates,
                    ],
                    'sub_department' => [
                        'id' => optional(optional($timeTable->group)->subDepartment)->id,
                        'name' => optional(optional($timeTable->group)->subDepartment)->name,
                    ],
                    'program' => [
                        'id' => optional(optional($timeTable->group)->program)->id,
                        'program_name' => optional(optional($timeTable->group)->program)->program_name,
                        'degree_level' => optional(optional($timeTable->group)->program)->degree_level,
                    ],
                    'students' => optional($timeTable->group->students)->map(function ($student) {
                        return [
                            'id' => $student->id,
                            'name' => $student->name,
                            'email' => $student->email,
                        ];
                    })->toArray(),
                ],
                'time_slots' => $timeTable->timeSlots,
            ],

        ];

        return response()->json($response, 200);
        // return response()->json([
        //     'message' => 'Time table retrieved successfully.',
        //     'time_table' => $timeTable
        // ], 200);
    }

    public function updateTimeTable(Request $request, $id)
    {
        $timeTable = TimeTable::find($id);

        if (!$timeTable) {
            return response()->json([
                'message' => 'Time table not found.'
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $timeTable->update($validated);

        return response()->json([
            'message' => 'Time table updated successfully.',
            'time_table' => $timeTable
        ], 200);
    }
    // check timetbale id -> location-> time ->  // create time slots can create only one
    // have to validate if this group has class 8:00-10:00 on 2024-09-01 so cannot create another 8:00-10:00 on 2024-09-01 
    //for the same group how ever the separate room and check check teacher . 
    //    Is he involved in another class at the same time, so mean he has class at the same time and validate
    public function createTimeSlots(Request $request, $timeTableId)
    {
        $timeTable = TimeTable::with('group.semester')->find($timeTableId);

        if (!$timeTable) {
            return response()->json([
                'message' => 'Time table not found.'
            ], 404);
        }

        // Get semester information
        $semester = $timeTable->group->semester ?? null;

        if (!$semester) {
            return response()->json([
                'message' => 'Semester not found for this time table.'
            ], 404);
        }

        $validated = $request->validate([
            'slots' => 'required|array',
            'slots.*.time_slot.start_time' => 'required|date_format:H:i:s',
            'slots.*.time_slot.end_time' => 'required|date_format:H:i:s',
            'slots.*.teacher_id' => 'nullable|exists:users,id',
            'slots.*.subject_id' => 'nullable|exists:subjects,id',
            'slots.*.location_id' => 'nullable|exists:locations,id',
            'slots.*.remark' => 'nullable|string',
            'slots.*.time_slot_date' => 'required|date'
        ]);

        // Parse semester start and end dates
        $semesterStart = Carbon::createFromFormat('d-m-Y', $semester->start_date)->startOfDay();
        $semesterEnd = Carbon::createFromFormat('d-m-Y', $semester->end_date)->endOfDay();

        foreach ($validated['slots'] as $index => $slot) {
            // Custom error for missing teacher_id
            if (empty($slot['teacher_id'])) {
                return response()->json([
                    'message' => "Teacher is not chosen for slot #"
                ], 422);
            }
            // Custom error for missing subject_id
            if (empty($slot['subject_id'])) {
                return response()->json([
                    'message' => "Subject is not chosen for slot #"
                ], 422);
            }
            // Custom error for missing location_id
            if (empty($slot['location_id'])) {
                return response()->json([
                    'message' => "Location is not chosen for slot #"
                ], 422);
            }
            // Validate time slot date is within semester range
            $timeSlotDate = Carbon::parse($slot['time_slot_date']);

            if ($timeSlotDate->lt($semesterStart) || $timeSlotDate->gt($semesterEnd)) {
                return response()->json([
                    'message' => "Time slot date for slot #" . ($index + 1) . " ({$slot['time_slot_date']}) is out of semester range. Semester runs from {$semester->start_date} to {$semester->end_date}."
                ], 422);
            }
            $startTime = $slot['time_slot']['start_time'];
            $endTime = $slot['time_slot']['end_time'];

            // Validate end time is after start time
            if ($endTime <= $startTime) {
                return response()->json([
                    'message' => "End time must be greater than start time for slot #" . ($index + 1)
                ], 422);
            }

            // Auto-calculate day of week from time_slot_date
            $dayOfWeek = Carbon::parse($slot['time_slot_date'])->format('l');

            // Check teacher validation if teacher_id is provided
            if (!empty($slot['teacher_id'])) {
                $teacher = User::find($slot['teacher_id']);

                // Check role
                if (!$teacher->hasRole('Staff')) {
                    return response()->json([
                        'message' => "User with ID {$slot['teacher_id']} is not a teacher for slot #" . ($index + 1)
                    ], 422);
                }

                // Check teacher teaches subject
                if (!empty($slot['subject_id'])) {
                    $teachesSubject = $teacher->subjects()->where('subjects.id', $slot['subject_id'])->exists();

                    if (!$teachesSubject) {
                        // Get subject name for better error message
                        $subjectName = optional($teacher->subjects()->where('subjects.id', $slot['subject_id'])->first())->subject_name;
                        if (!$subjectName) {
                            // fallback: try to get subject name directly
                            $subjectModel = \App\Models\Subject::find($slot['subject_id']);
                            $subjectName = $subjectModel ? $subjectModel->subject_name : $slot['subject_id'];
                        }
                        return response()->json([
                            'message' => "Teacher '{$teacher->name}' does not teach subject '{$subjectName}' for slot #" . ($index + 1)
                        ], 422);
                    }
                }
            }
            // Check for teacher time conflict across all time tables
            $teacherConflict = TimeSlot::where('teacher_id', $slot['teacher_id'])
                ->where('time_slot_date', $slot['time_slot_date'])
                ->get();

            foreach ($teacherConflict as $conflictSlot) {
                $conflictTime = is_array($conflictSlot->time_slot) ? $conflictSlot->time_slot : json_decode($conflictSlot->time_slot, true);
                $conflictStart = $conflictTime['start_time'];
                $conflictEnd = $conflictTime['end_time'];
                // Check for time overlap
                if ($startTime < $conflictEnd && $endTime > $conflictStart) {
                    return response()->json([
                        'message' => "Teacher time conflict detected for slot #" .
                            ". Teacher '{$teacher->name}' is already assigned to another class on {$slot['time_slot_date']} from " . $conflictStart . " to " . $conflictEnd . "."
                    ], 422);
                }
            }

            // Check for group time conflict - a group cannot have two classes at the same time
            $groupConflict = TimeSlot::whereHas('timeTable', function($query) use ($timeTable) {
                    $query->where('group_id', $timeTable->group_id);
                })
                ->where('time_slot_date', $slot['time_slot_date'])
                ->get();

            foreach ($groupConflict as $conflictSlot) {
                $conflictTime = is_array($conflictSlot->time_slot) ? $conflictSlot->time_slot : json_decode($conflictSlot->time_slot, true);
                $conflictStart = $conflictTime['start_time'];
                $conflictEnd = $conflictTime['end_time'];
                // Check for time overlap
                if ($startTime < $conflictEnd && $endTime > $conflictStart) {
                    $conflictTeacher = optional($conflictSlot->teacher)->name ?? 'Unknown';
                    $conflictSubject = optional($conflictSlot->subject)->subject_name ?? 'Unknown';
                    return response()->json([
                        'message' => "Group time conflict detected for slot #" . ($index + 1) .
                            ". This group already has a class on {$slot['time_slot_date']} from " . $conflictStart . " to " . $conflictEnd . 
                            " with teacher '{$conflictTeacher}' teaching '{$conflictSubject}'. A group cannot attend two classes simultaneously."
                    ], 422);
                }
            }

            // Check for exact duplicate time slot within the SAME time table
            $duplicateExists = TimeSlot::where('time_table_id', $timeTableId)
                ->where('time_slot_date', $slot['time_slot_date'])
                ->where('time_slot', json_encode([
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                ]))
                ->where('location_id', $slot['location_id'] ?? null)
                ->where('teacher_id', $slot['teacher_id'] ?? null)
                ->where('subject_id', $slot['subject_id'] ?? null)
                ->exists();

            if ($duplicateExists) {
                return response()->json([
                    'message' => "Duplicate time slot detected for slot #" . ($index + 1) .
                        ". A slot with the same date ({$slot['time_slot_date']}), time ({$startTime} - {$endTime}), location, teacher, and subject already exists in this time table."
                ], 422);
            }

            // Check for time conflict at this location across ALL time tables (prevent double-booking)
            if (!empty($slot['location_id'])) {
                $existingSlots = TimeSlot::where('location_id', $slot['location_id'])
                    ->where('time_slot_date', $slot['time_slot_date'])
                    ->get();

                foreach ($existingSlots as $existing) {
                    $existingSlot = is_array($existing->time_slot) ? $existing->time_slot : json_decode($existing->time_slot, true);
                    $existingStart = $existingSlot['start_time'];
                    $existingEnd = $existingSlot['end_time'];

                    // Check for time overlap
                    if ($startTime < $existingEnd && $endTime > $existingStart) {
                        return response()->json([
                            'message' => "Location conflict detected for slot #" . ($index + 1) .
                                ". Location '" . optional($existing->location)->name .
                                "' is already booked on {$slot['time_slot_date']} from " . $existingStart . " to " . $existingEnd . " by another time table."
                        ], 422);
                    }
                }
            }

            // Create the slot
            $timeTable->timeSlots()->create([
                'teacher_id' => $slot['teacher_id'] ?? null,
                'subject_id' => $slot['subject_id'] ?? null,
                'location_id' => $slot['location_id'] ?? null,
                'remark' => $slot['remark'] ?? null,
                'day_of_week' => $dayOfWeek,
                'time_slot_date' => $slot['time_slot_date'],
                'time_slot' => json_encode([
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                ]),
            ]);
        }

        return response()->json([
            'message' => 'Time slots created successfully.',
            // 'time_table' => $timeTable,
            'time_slots' => $timeTable->timeSlots
        ], 201);
    }

    public function getTimeSlotsOfGroup(Request $request, $groupId)
    {
        $day = $request->input('day');

        $timeSlots = TimeSlot::with(['teacher:id,name,email', 'subject:id,subject_name', 'location:id,name,floor,building_id'])
            ->whereHas('timeTable.group', function ($query) use ($groupId) {
                $query->where('id', $groupId);
            })
            ->when($day, function ($query, $day) {
                $query->where('day_of_week', $day);
            })
            ->get();

        if ($timeSlots->isEmpty()) {
            return response()->json([
                'message' => 'No time slots found for this group.',
                'time_slots' => $timeSlots
            ], 404);
        }

        return response()->json([
            'message' => "Retrieving time slots for the group with ID: $groupId.",
            'table_table' => $timeSlots->first()->timeTable,
            'time_slots' => $timeSlots,
        ], 200);
    }

    public function getAllTimeSlots(Request $request) // https://api.example.com/time-slots?year_label=2025
    {
        $perPage = $request->input('per_page', 14);
        $day = $request->input('day');
        $academicYearLabel = $request->input(key: 'year_label');
        $programId = $request->input('program_id');
        $semesterId = $request->input('semester_id');
        $groupId = $request->input('group_id');
        $year = $request->input('year'); // Filter by year (e.g., 2025)
        $month = $request->input('month'); // Filter by month (e.g., 09 or 9)
        $dayOfMonth = $request->input('day_of_month'); // Filter by day of month (e.g., 20)

        $timeSlots = TimeSlot::with([
            // 'timeTable.group.semester.academicYear',
            // 'timeTable.group.department',
            // 'timeTable.group',
            // 'timeTable.group.subDepartment',
            // 'teacher:id,name,email',
            // 'subject:id,subject_name',
            // 'location:id,name,floor,building_id'
        ])
            ->when($day, fn($query) => $query->where('day_of_week', $day))
            ->when(
                $academicYearLabel,
                fn($query) =>
                $query->whereHas(
                    'timeTable.group.semester.academicYear',
                    fn($q) =>
                    $q->where('year_label', 'like', "%{$academicYearLabel}%")
                )
            )
            ->when(
                $programId,
                fn($query) =>
                $query->whereHas(
                    'timeTable.group.program',
                    fn($q) =>
                    $q->where('id', $programId)
                )
            )
            ->when(
                $semesterId,
                fn($query) =>
                $query->whereHas(
                    'timeTable.group.semester',
                    fn($q) =>
                    $q->where('id', $semesterId)
                )
            )
            ->when(
                $groupId,
                fn($query) =>
                $query->whereHas(
                    'timeTable.group',
                    fn($q) =>
                    $q->where('id', $groupId)
                )
            )
            ->when($year, function ($query) use ($year) {
                $query->whereYear('time_slot_date', $year);
            })
            ->when($month, function ($query) use ($month) {
                $query->whereMonth('time_slot_date', $month);
            })
            ->when($dayOfMonth, function ($query) use ($dayOfMonth) {
                $query->whereDay('time_slot_date', $dayOfMonth);
            })
            ->paginate($perPage);

        if ($timeSlots->count() === 0) {
            return response()->json([
                'message' => 'No time slots available with the filter.',
                'time_slots' => $timeSlots
            ], 200);
        }

        return response()->json([
            'message' => "Retrieving time slots",
            // 'time_table' => optional($timeSlots->first())->timeTable,
            'time_slots' => $timeSlots,
        ], 200);
    }



    public function removeMultipleTimeSlots(Request $request)
    {
        $validated = $request->validate([
            'time_table_id' => 'required|exists:time_tables,id',
            'slot_ids' => 'required|array',
            'slot_ids.*' => 'exists:time_slots,id',
        ]);

        $timeTable = TimeTable::find($validated['time_table_id']);
        $slotIds = $validated['slot_ids'];

        // Keep only slots that belong to this timetable
        $existingSlotIds = $timeTable->timeSlots()
            ->whereIn('id', $slotIds)
            ->pluck('id')
            ->toArray();

        // Optional: check for slots that are not in this timetable
        $notInTimeTable = array_diff($slotIds, $existingSlotIds);

        if (!empty($notInTimeTable)) {
            return response()->json([
                'message' => 'The following slots are not part of this time table: ' . implode(', ', $notInTimeTable)
            ], 422);
        }

        // Delete the slots
        TimeSlot::whereIn('id', $existingSlotIds)->delete();

        return response()->json([
            'message' => 'Time slots removed successfully.',
            'removed_slot_ids' => $existingSlotIds
        ], 200);
    }

    public function getTimeSlotByUser(Request $request)
    {
        $user = $request->user();

        $perPage = $request->query('per_page', 14);
        // $day = $request->query('day');
        // $orderBy = $request->query('order_by', 'day_of_week');

        $timeSlots = TimeSlot::with('timeTable.group', 'subject:id,subject_name', 'teacher:id,name', 'location:id,name,floor,building_id')
            ->whereHas('timeTable.group', function ($query) use ($user) {
                $query->whereHas('students', function ($q) use ($user) {
                    $q->where('users.id', $user->id);
                });
            })->paginate($perPage);

        return response()->json([
            'message' => 'Retrieving time slots for the user.',
            'time_slots' => $timeSlots,
        ], 200);
    }

    public function getTimeSlotForTeacher(Request $request)
    {
        $user = $request->user();

        $perPage = $request->query('per_page', 14);
        $day = $request->query('day');
        $orderBy = $request->query('order_by', 'day_of_week');

        $timeSlots = TimeSlot::with('timeTable.group', 'subject:id,subject_name', 'teacher:id,name', 'location:id,name,floor,building_id')
            //  ->whereHas('subject.teachers', function ($q) use ($user) {
            // $q->where('users.id', $user->id);
            ->where('teacher_id', $user->id)
            ->when($day, function ($query, $day) {
                $query->where('day_of_week', $day);
            })
            ->orderBy($orderBy)
            ->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'code' => 200,
            'message' => 'Retrieving time slots for the teacher.',
            'time_slots' => $timeSlots,
        ], 200);
    }


    public function cloneWeek(Request $request, $timeTableId)
    {
        $validated = $request->validate([
            'from_start' => 'required|date_format:Y-m-d',
            'from_end' => 'required|date_format:Y-m-d',
            'to_start' => 'required|date_format:Y-m-d',
        ]);

        $timeTable = TimeTable::with('group.semester')->find($timeTableId);
        if (!$timeTable) {
            return response()->json([
                'message' => 'Time table not found.'
            ], 404);
        }

        // Get semester information
        $semester = $timeTable->group->semester ?? null;

        if (!$semester) {
            return response()->json([
                'message' => 'Semester not found for this time table.'
            ], 404);
        }

        $fromStart = Carbon::parse($validated['from_start']);
        $fromEnd = Carbon::parse($validated['from_end']);
        $toStart = Carbon::parse($validated['to_start']);

        if ($toStart->lt(today())) {
            return response()->json([
                'message' => 'Cannot clone to a past date.'
            ], 422);
        }

        // Parse semester start and end dates
        $semesterStart = Carbon::createFromFormat('d-m-Y', $semester->start_date)->startOfDay();
        $semesterEnd = Carbon::createFromFormat('d-m-Y', $semester->end_date)->endOfDay();

        // Correct diff in days: toStart - fromStart
        $diffDays = $toStart->diffInDays($fromStart);

        $slots = TimeSlot::where('time_table_id', $timeTableId)
            ->whereBetween('time_slot_date', [$fromStart, $fromEnd])
            ->get();

        $cloned = [];
        foreach ($slots as $slot) {
            $originalDate = Carbon::parse($slot->time_slot_date);

            // Calculate the difference in weekdays (0=Monday ... 6=Sunday)
            $fromStartIndex = $fromStart->dayOfWeek; // 0=Sunday
            $slotIndex = $originalDate->dayOfWeek;

            $diff = ($slotIndex - $fromStartIndex + 7) % 7; // ensure positive

            // Add to the new start
            $newDate = $toStart->copy()->addDays($diff);

            // Validate cloned date is within semester range
            if ($newDate->lt($semesterStart) || $newDate->gt($semesterEnd)) {
                return response()->json([
                    'message' => "Cannot clone time slot. The cloned date ({$newDate->format('Y-m-d')}) would be out of semester range. Semester runs from {$semester->start_date} to {$semester->end_date}."
                ], 422);
            }

            $exists = TimeSlot::where('teacher_id', $slot->teacher_id)
                ->where('time_slot_date', $newDate->format('Y-m-d'))
                ->where('time_slot', $slot->time_slot)
                ->exists();

            if (!$exists) {
                $cloned[] = $timeTable->timeSlots()->create([
                    'teacher_id' => $slot->teacher_id,
                    'subject_id' => $slot->subject_id,
                    'location_id' => $slot->location_id,
                    'remark' => $slot->remark,
                    'day_of_week' => $slot->day_of_week,
                    'time_slot_date' => $newDate->format('Y-m-d'),
                    'time_slot' => $slot->time_slot,
                ]);
            }
        }
        return response()->json([
            'message' => 'Week cloned successfully.',
            'from_week' => $fromStart->format('Y-m-d') . ' to ' . $fromEnd->format('Y-m-d'),
            'to_week' => $toStart->format('Y-m-d') . ' to ' . $toStart->copy()->addDays($fromEnd->diffInDays($fromStart))->format('Y-m-d'),
            'cloned_slots' => $cloned
        ], 201);
    }

    public function getAWeekEvents(Request $request, $timeTableId)
    {
        // Find the timetable with semester information
        $timeTable = TimeTable::with(['group.semester', 'timeSlots'])->find($timeTableId);

        if (!$timeTable) {
            return response()->json([
                'message' => 'Time table not found.'
            ], 404);
        }

        $semester = $timeTable->group->semester ?? null;

        if (!$semester) {
            return response()->json([
                'message' => 'Semester not found for this time table.'
            ], 404);
        }

        // Parse semester start and end dates
        $semesterStart = Carbon::createFromFormat('d-m-Y', $semester->start_date)->startOfDay();
        $semesterEnd = Carbon::createFromFormat('d-m-Y', $semester->end_date)->endOfDay();

        // Start from the beginning of the week that contains semester start
        $weekStart = $semesterStart->copy()->startOfWeek(Carbon::MONDAY);

        // Calculate all weeks in the semester
        $weeks = [];
        $weekNumber = 1;

        while ($weekStart->lte($semesterEnd)) {
            $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);

            // Adjust if week end goes beyond semester end
            if ($weekEnd->gt($semesterEnd)) {
                $weekEnd = $semesterEnd->copy();
            }

            // Get time slots for this week
            $weekTimeSlots = $timeTable->timeSlots()
                ->whereBetween('time_slot_date', [
                    $weekStart->format('Y-m-d'),
                    $weekEnd->format('Y-m-d')
                ])
                ->with(['teacher:id,name,email', 'subject:id,subject_name', 'location:id,name,floor,building_id'])
                ->orderBy('time_slot_date')
                ->orderByRaw("JSON_UNQUOTE(JSON_EXTRACT(time_slot, '$.start_time'))")
                ->get();

            // Group time slots by date
            $dailySlots = [];
            $currentDate = $weekStart->copy();

            while ($currentDate->lte($weekEnd)) {
                $dateStr = $currentDate->format('Y-m-d');
                $daySlots = $weekTimeSlots->filter(function ($slot) use ($dateStr) {
                    return $slot->time_slot_date === $dateStr;
                });

                $dailySlots[] = [
                    'date' => $currentDate->format('Y-m-d'),
                    'day_name' => $currentDate->format('l'),
                    'day_short' => $currentDate->format('D'),
                    'is_in_semester' => $currentDate->gte($semesterStart) && $currentDate->lte($semesterEnd),
                    'time_slots' => $daySlots->values(),
                    'total_slots' => $daySlots->count()
                ];

                $currentDate->addDay();
            }

            $weeks[] = [
                'week_number' => $weekNumber,
                'week_start' => $weekStart->format('Y-m-d'),
                'week_end' => $weekEnd->format('Y-m-d'),
                'week_start_formatted' => $weekStart->format('d M Y'),
                'week_end_formatted' => $weekEnd->format('d M Y'),
                'days' => $dailySlots,
                'total_time_slots' => $weekTimeSlots->count()
            ];

            // Move to next week
            $weekStart->addWeek();
            $weekNumber++;
        }

        return response()->json([
            'message' => 'Week-by-week events retrieved successfully.',
            'time_table' => [
                'id' => $timeTable->id,
                'name' => $timeTable->name,
                'description' => $timeTable->description
            ],
            'semester' => [
                'id' => $semester->id,
                'semester_number' => $semester->semester_number,
                'semester_key' => $semester->semester_key,
                'start_date' => $semester->start_date,
                'end_date' => $semester->end_date,
                'duration_days' => $semesterStart->diffInDays($semesterEnd) + 1
            ],
            'total_weeks' => count($weeks),
            'weeks' => $weeks
        ], 200);
    }

    public function getSpecificWeekEvents(Request $request, $timeTableId, $weekNumber)
    {
        // Find the timetable with semester information
        $timeTable = TimeTable::with(['group.semester'])->find($timeTableId);

        if (!$timeTable) {
            return response()->json([
                'message' => 'Time table not found.'
            ], 404);
        }

        $semester = $timeTable->group->semester ?? null;

        if (!$semester) {
            return response()->json([
                'message' => 'Semester not found for this time table.'
            ], 404);
        }

        // Parse semester start and end dates
        $semesterStart = Carbon::createFromFormat('d-m-Y', $semester->start_date)->startOfDay();
        $semesterEnd = Carbon::createFromFormat('d-m-Y', $semester->end_date)->endOfDay();

        // Calculate total weeks
        $weekStart = $semesterStart->copy()->startOfWeek(Carbon::MONDAY);
        $totalWeeks = 0;
        $tempDate = $weekStart->copy();
        while ($tempDate->lte($semesterEnd)) {
            $totalWeeks++;
            $tempDate->addWeek();
        }

        // Validate week number
        if ($weekNumber < 1 || $weekNumber > $totalWeeks) {
            return response()->json([
                'message' => "Invalid week number. Must be between 1 and {$totalWeeks}."
            ], 422);
        }

        // Calculate the specific week's start and end
        $weekStart = $semesterStart->copy()->startOfWeek(Carbon::MONDAY)->addWeeks($weekNumber - 1);
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);

        // Adjust if week end goes beyond semester end
        if ($weekEnd->gt($semesterEnd)) {
            $weekEnd = $semesterEnd->copy();
        }

        // Get time slots for this week
        $weekTimeSlots = $timeTable->timeSlots()
            ->whereBetween('time_slot_date', [
                $weekStart->format('Y-m-d'),
                $weekEnd->format('Y-m-d')
            ])
            ->with(['teacher:id,name,email', 'subject:id,subject_name', 'location:id,name,floor,building_id'])
            ->orderBy('time_slot_date')
            ->orderByRaw("JSON_UNQUOTE(JSON_EXTRACT(time_slot, '$.start_time'))")
            ->get();

        // Group time slots by date
        $dailySlots = [];
        $currentDate = $weekStart->copy();

        while ($currentDate->lte($weekEnd)) {
            $dateStr = $currentDate->format('Y-m-d');
            $daySlots = $weekTimeSlots->filter(function ($slot) use ($dateStr) {
                return $slot->time_slot_date === $dateStr;
            });

            $dailySlots[] = [
                'date' => $currentDate->format('Y-m-d'),
                'day_name' => $currentDate->format('l'),
                'day_short' => $currentDate->format('D'),
                'is_in_semester' => $currentDate->gte($semesterStart) && $currentDate->lte($semesterEnd),
                'time_slots' => $daySlots->values(),
                'total_slots' => $daySlots->count()
            ];

            $currentDate->addDay();
        }

        return response()->json([
            'message' => "Week {$weekNumber} events retrieved successfully.",
            'time_table' => [
                'id' => $timeTable->id,
                'name' => $timeTable->name,
                'description' => $timeTable->description
            ],
            'semester' => [
                'id' => $semester->id,
                'semester_number' => $semester->semester_number,
                'semester_key' => $semester->semester_key,
                'start_date' => $semester->start_date,
                'end_date' => $semester->end_date
            ],
            'week_info' => [
                'current_week' => $weekNumber,
                'total_weeks' => $totalWeeks,
                'week_start' => $weekStart->format('Y-m-d'),
                'week_end' => $weekEnd->format('Y-m-d'),
                'week_start_formatted' => $weekStart->format('d M Y'),
                'week_end_formatted' => $weekEnd->format('d M Y')
            ],
            'days' => $dailySlots,
            'total_time_slots' => $weekTimeSlots->count()
        ], 200);
    }

    public function getTimeServer()
    {
        $timeServer = Carbon::now()->toTimeString();
        $dateServer = Carbon::now()->toDateTime();

        return response()->json([
            'message' => "List time of server.",
            'time_server' => $timeServer,
            'date_server' => $dateServer
        ]);
    }

    public function removeTimeSlot($id)
    {
        $timeSlot = TimeSlot::find($id);

        if (!$timeSlot) {
            return response()->json([
                'message' => 'Time slot not found.'
            ], 404);
        }

        $timeSlot->delete();

        return response()->json([
            'message' => 'Time slot deleted successfully.'
        ], 200);
    }

    public function updateTimeSlot(Request $request, $id)
    {
        $timeSlot = TimeSlot::with('timeTable.group.semester')->find($id);

        if (!$timeSlot) {
            return response()->json([
                'message' => 'Time slot not found.'
            ], 404);
        }

        // Get semester information
        $semester = $timeSlot->timeTable->group->semester ?? null;

        if (!$semester) {
            return response()->json([
                'message' => 'Semester not found for this time slot.'
            ], 404);
        }

        $validated = $request->validate([
            'teacher_id' => 'nullable|exists:users,id',
            'subject_id' => 'nullable|exists:subjects,id',
            'location_id' => 'nullable|exists:locations,id',
            'remark' => 'nullable|string',
            'time_slot_date' => 'required|date',
            'time_slot.start_time' => 'required|date_format:H:i:s',
            'time_slot.end_time' => 'required|date_format:H:i:s|after:time_slot.start_time',
        ]);

        // Parse semester start and end dates
        $semesterStart = Carbon::createFromFormat('d-m-Y', $semester->start_date)->startOfDay();
        $semesterEnd = Carbon::createFromFormat('d-m-Y', $semester->end_date)->endOfDay();

        // Validate time slot date is within semester range
        $timeSlotDate = Carbon::parse($validated['time_slot_date']);

        if ($timeSlotDate->lt($semesterStart) || $timeSlotDate->gt($semesterEnd)) {
            return response()->json([
                'message' => "Time slot date ({$validated['time_slot_date']}) is out of semester range. Semester runs from {$semester->start_date} to {$semester->end_date}."
            ], 422);
        }
        $startTime = $validated['time_slot']['start_time'];
        $endTime = $validated['time_slot']['end_time'];

        // Auto-calculate day of week from time_slot_date
        $dayOfWeek = Carbon::parse($validated['time_slot_date'])->format('l');

        // Check for teacher time conflict across all time tables (excluding current slot)
        if (!empty($validated['teacher_id'])) {
            $teacherConflict = TimeSlot::where('teacher_id', $validated['teacher_id'])
                ->where('time_slot_date', $validated['time_slot_date'])
                ->where('id', '!=', $id)
                ->get();

            foreach ($teacherConflict as $conflictSlot) {
                $conflictTime = is_array($conflictSlot->time_slot) ? $conflictSlot->time_slot : json_decode($conflictSlot->time_slot, true);
                $conflictStart = $conflictTime['start_time'];
                $conflictEnd = $conflictTime['end_time'];
                // Check for time overlap
                if ($startTime < $conflictEnd && $endTime > $conflictStart) {
                    return response()->json([
                        'message' => "Teacher time conflict detected. Teacher is already assigned to another class on {$validated['time_slot_date']} from $conflictStart to $conflictEnd."
                    ], 422);
                }
            }
        }

        // Check for group time conflict - a group cannot have two classes at the same time (excluding current slot)
        $groupConflict = TimeSlot::whereHas('timeTable', function($query) use ($timeSlot) {
                $query->where('group_id', $timeSlot->timeTable->group_id);
            })
            ->where('time_slot_date', $validated['time_slot_date'])
            ->where('id', '!=', $id)
            ->get();

        foreach ($groupConflict as $conflictSlot) {
            $conflictTime = is_array($conflictSlot->time_slot) ? $conflictSlot->time_slot : json_decode($conflictSlot->time_slot, true);
            $conflictStart = $conflictTime['start_time'];
            $conflictEnd = $conflictTime['end_time'];
            // Check for time overlap
            if ($startTime < $conflictEnd && $endTime > $conflictStart) {
                $conflictTeacher = optional($conflictSlot->teacher)->name ?? 'Unknown';
                $conflictSubject = optional($conflictSlot->subject)->subject_name ?? 'Unknown';
                return response()->json([
                    'message' => "Group time conflict detected. This group already has a class on {$validated['time_slot_date']} from " . $conflictStart . " to " . $conflictEnd . 
                        " with teacher '{$conflictTeacher}' teaching '{$conflictSubject}'. A group cannot attend two classes simultaneously."
                ], 422);
            }
        }

        // Check for exact duplicate time slot within the SAME time table (excluding current slot)
        $duplicateExists = TimeSlot::where('time_table_id', $timeSlot->time_table_id)
            ->where('time_slot_date', $validated['time_slot_date'])
            ->where('time_slot', json_encode([
                'start_time' => $startTime,
                'end_time' => $endTime,
            ]))
            ->where('location_id', $validated['location_id'] ?? null)
            ->where('teacher_id', $validated['teacher_id'] ?? null)
            ->where('subject_id', $validated['subject_id'] ?? null)
            ->where('id', '!=', $id)
            ->exists();

        if ($duplicateExists) {
            return response()->json([
                'message' => "Duplicate time slot detected. A slot with the same date ({$validated['time_slot_date']}), time ({$startTime} - {$endTime}), location, teacher, and subject already exists in this time table."
            ], 422);
        }

        // Check for location conflict across ALL time tables (excluding current slot)
        if (!empty($validated['location_id'])) {
            $existingSlots = TimeSlot::where('location_id', $validated['location_id'])
                ->where('time_slot_date', $validated['time_slot_date'])
                ->where('id', '!=', $id)
                ->get();

            foreach ($existingSlots as $existing) {
                $existingSlot = is_array($existing->time_slot) ? $existing->time_slot : json_decode($existing->time_slot, true);
                $existingStart = $existingSlot['start_time'];
                $existingEnd = $existingSlot['end_time'];

                // Check for time overlap
                if ($startTime < $existingEnd && $endTime > $existingStart) {
                    return response()->json([
                        'message' => "Location conflict detected. Location is already booked from " . $existingStart . " to " . $existingEnd . " by another time table."
                    ], 422);
                }
            }
        }

        // Update the slot
        $timeSlot->update([
            'teacher_id' => $validated['teacher_id'] ?? null,
            'subject_id' => $validated['subject_id'] ?? null,
            'location_id' => $validated['location_id'] ?? null,
            'remark' => $validated['remark'] ?? null,
            'day_of_week' => $dayOfWeek,
            'time_slot_date' => $validated['time_slot_date'],
            'time_slot' => json_encode([
                'start_time' => $startTime,
                'end_time' => $endTime,
            ]),
        ]);
        return response()->json([
            'message' => 'Time slot updated successfully.',
            'time_slot' => $timeSlot
        ], 200);
    }


    public function getTimeSlotsByDepartmentAndGroup(Request $request)
    {
        $user = $request->user();

        $departmentId = $request->query('department_id');
        $subDepartmentId = $request->query('sub_department_id');
        $programId = $request->query('program_id');
        $semesterId = $request->query('semester_id');
        $groupId = $request->query('group_id');

        //  department_id is required
        if (!$departmentId) {
            return response()->json([
                'status' => 'error',
                'code' => 400,
                'message' => 'department_id query parameter is required.',
            ], 400);
        }
        $foundUser = User::find($user->id);
        if (!$foundUser) {
            return response()->json([
                'status' => 'error',
                'code' => 404,
                'message' => 'User not found.',
            ], 404);
        }

        // Invalid department
        $department = Department::find($departmentId);
        if (!$department) {
            return response()->json([
                'status' => 'error',
                'code' => 404,
                'message' => 'Department not found.',
            ], 404);
        }

        // Authorization (Head can access ONLY their department)
        if (
            $department->department_head_id !== $foundUser->id
        ) {
            return response()->json([
                'status' => 'error',
                'code' => 403,
                'message' => 'You are not authorized to access this department.',
            ], 403);
        }

        if ($subDepartmentId) {
            $subDepartment = SubDepartment::find($subDepartmentId);
            if (!$subDepartment) {
                return response()->json([
                    'status' => 'error',
                    'code' => 404,
                    'message' => 'Sub-department not found.',
                ], 404);
            }
            if ($subDepartment->department_id !== (int) $departmentId) {
                return response()->json([
                    'status' => 'error',
                    'code' => 400,
                    'message' => 'Sub-department does not belong to the department.',
                ], 400);
            }
        }

        if ($programId) {
            $program = Program::find($programId);
            if (!$program) {
                return response()->json([
                    'status' => 'error',
                    'code' => 404,
                    'message' => 'Program not found.',
                ], 404);
            }
            if ($program->department_id && (int) $program->department_id !== (int) $departmentId) {
                return response()->json([
                    'status' => 'error',
                    'code' => 403,
                    'message' => 'You are not authorized to access this program.',
                ], 403);
            }
            if (!$program->department_id && $program->sub_department_id) {
                $programSubDepartment = SubDepartment::find($program->sub_department_id);
                if (!$programSubDepartment || (int) $programSubDepartment->department_id !== (int) $departmentId) {
                    return response()->json([
                        'status' => 'error',
                        'code' => 403,
                        'message' => 'You are not authorized to access this program.',
                    ], 403);
                }
            }
            if (!$program->department_id && !$program->sub_department_id) {
                return response()->json([
                    'status' => 'error',
                    'code' => 403,
                    'message' => 'You are not authorized to access this program.',
                ], 403);
            }
        }

        if ($semesterId) {
            $semester = Semester::find($semesterId);
            if (!$semester) {
                return response()->json([
                    'status' => 'error',
                    'code' => 404,
                    'message' => 'Semester not found.',
                ], 404);
            }
            if ($programId && (int) $semester->program_id !== (int) $programId) {
                return response()->json([
                    'status' => 'error',
                    'code' => 400,
                    'message' => 'Semester does not belong to the program.',
                ], 400);
            }
            if (!$programId) {
                $semesterProgram = Program::find($semester->program_id);
                if (!$semesterProgram) {
                    return response()->json([
                        'status' => 'error',
                        'code' => 403,
                        'message' => 'You are not authorized to access this semester.',
                    ], 403);
                }
                if ($semesterProgram->department_id && (int) $semesterProgram->department_id !== (int) $departmentId) {
                    return response()->json([
                        'status' => 'error',
                        'code' => 403,
                        'message' => 'You are not authorized to access this semester.',
                    ], 403);
                }
                if (!$semesterProgram->department_id && $semesterProgram->sub_department_id) {
                    $semesterSubDepartment = SubDepartment::find($semesterProgram->sub_department_id);
                    if (
                        !$semesterSubDepartment ||
                        (int) $semesterSubDepartment->department_id !== (int) $departmentId
                    ) {
                        return response()->json([
                            'status' => 'error',
                            'code' => 403,
                            'message' => 'You are not authorized to access this semester.',
                        ], 403);
                    }
                }
                if (!$semesterProgram->department_id && !$semesterProgram->sub_department_id) {
                    return response()->json([
                        'status' => 'error',
                        'code' => 403,
                        'message' => 'You are not authorized to access this semester.',
                    ], 403);
                }
            }
        }

        // Fetch data using QUERY filters
        $groups = Group::with([
            'department',
            'subDepartment',
            'timeTables.timeSlots.subject'
        ])
            ->where('department_id', $departmentId)
            ->when($subDepartmentId, fn($q) => $q->where('sub_department_id', $subDepartmentId))
            ->when($programId, fn($q) => $q->where('program_id', $programId))
            ->when($semesterId, fn($q) => $q->where('semester_id', $semesterId))
            ->when($groupId, fn($q) => $q->where('id', $groupId))
            ->get();

        // Response (Department → Groups → TimeSlots)
        $groupedByDepartment = $groups->groupBy(
            fn($g) => $g->department?->department_name ?? 'Unknown Department'
        );

        $data = $groupedByDepartment->map(function ($groups, $departmentName) {
            return [
                'department' => $departmentName,
                'groups' => $groups->map(function ($group) {
                    return [
                        'group' => [
                            'id' => $group->id,
                            'name' => $group->name,
                        ],
                        'time_slots' => $group->timeTables
                            ->flatMap->timeSlots
                            ->map(function ($slot) {
                                return [
                                    'id' => $slot->id,
                                    'day' => $slot->day_of_week,
                                    'slot' => $slot->time_slot,
                                    'time_slot_date' => $slot->time_slot_date,
                                    'remark' => $slot->remark,
                                    'subject' => $slot->subject,
                                    'location' => $slot->location,
                                    'teacher' => $slot->teacher
                                ];
                            })
                            ->values(),
                    ];
                })->values(),
            ];
        })->values();

        return response()->json([
            'status' => 'success',
            'code' => 200,
            'message' => 'Time slots retrieved successfully.',
            'data' => $data,
        ], 200);
    }

    public function createTimeSlotByHeadDepartment(Request $request, $group_id)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'code' => 401,
                'message' => 'Unauthorized'
            ], 401);
        }

        if (!$user->hasRole('Head Department')) {
            return response()->json([
                'status' => 'error',
                'code' => 403,
                'message' => 'You are not authorized to create time slots.'
            ], 403);
        }

        $group = Group::with(['department', 'semester'])->find($group_id);
        if (!$group) {
            return response()->json([
                'status' => 'error',
                'code' => 404,
                'message' => 'Group not found.'
            ], 404);
        }

        $department = $group->department;
        if (!$department) {
            return response()->json([
                'status' => 'error',
                'code' => 404,
                'message' => 'Department not found for this group.'
            ], 404);
        }

        if ($department->department_head_id !== $user->id) {
            return response()->json([
                'status' => 'error',
                'code' => 403,
                'message' => 'You are not authorized to access this group.'
            ], 403);
        }

        // $timeTable = TimeTable::with('group.semester')
        //     ->where('group_id', $group_id)
        //     ->latest('id')
        //     ->first();

        // if (!$timeTable) {
        //     return response()->json([
        //         'message' => 'Time table not found for this group.'
        //     ], 404);
        // }
        $timeTable = TimeTable::firstOrCreate(
            ['group_id' => $group_id],
            [
                'name' => $group->name . ' Time Table',
                'description' => 'Auto-created time table for group ' . $group->name,
                'created_by' => $user->id, // optional
            ]
        );


        $semester = $group->semester ?? null;
        if (!$semester) {
            return response()->json([
                'status' => 'error',
                'code' => 404,
                'message' => 'Semester not found for this group.'
            ], 404);
        }

        $validated = $request->validate([
            'slots' => 'required|array',
            'slots.*.time_slot.start_time' => 'required|date_format:H:i:s',
            'slots.*.time_slot.end_time' => 'required|date_format:H:i:s',
            'slots.*.teacher_id' => 'nullable|exists:users,id',
            'slots.*.subject_id' => 'nullable|exists:subjects,id',
            'slots.*.location_id' => 'nullable|exists:locations,id',
            'slots.*.remark' => 'nullable|string',
            'slots.*.time_slot_date' => 'required|date'
        ]);

        $semesterStart = Carbon::createFromFormat('d-m-Y', $semester->start_date)->startOfDay();
        $semesterEnd = Carbon::createFromFormat('d-m-Y', $semester->end_date)->endOfDay();

        foreach ($validated['slots'] as $index => $slot) {
            if (empty($slot['teacher_id'])) {
                return response()->json([
                    'status' => 'error',
                    'code' => 422,
                    'message' => "Teacher is not chosen for slot #"
                ], 422);
            }
            if (empty($slot['subject_id'])) {
                return response()->json([
                    'status' => 'error',
                    'code' => 422,
                    'message' => "Subject is not chosen for slot #"
                ], 422);
            }
            if (empty($slot['location_id'])) {
                return response()->json([
                    'status' => 'error',
                    'code' => 422,
                    'message' => "Location is not chosen for slot #"
                ], 422);
            }

            $timeSlotDate = Carbon::parse($slot['time_slot_date']);
            if ($timeSlotDate->lt($semesterStart) || $timeSlotDate->gt($semesterEnd)) {
                return response()->json([
                    'status' => 'error',
                    'code' => 422,
                    'message' => "Time slot date for slot #" . ($index + 1) . " ({$slot['time_slot_date']}) is out of semester range. Semester runs from {$semester->start_date} to {$semester->end_date}."
                ], 422);
            }

            $startTime = $slot['time_slot']['start_time'];
            $endTime = $slot['time_slot']['end_time'];
            if ($endTime <= $startTime) {
                return response()->json([
                    'status' => 'error',
                    'code' => 422,
                    'message' => "End time must be greater than start time for slot #" . ($index + 1)
                ], 422);
            }

            $dayOfWeek = Carbon::parse($slot['time_slot_date'])->format('l');

            if (!empty($slot['teacher_id'])) {
                $teacher = User::find($slot['teacher_id']);
                if (!$teacher->hasRole('Staff')) {
                    return response()->json([
                        'status' => 'error',
                        'code' => 422,
                        'message' => "User with ID {$slot['teacher_id']} is not a teacher for slot #" . ($index + 1)
                    ], 422);
                }

                if (!empty($slot['subject_id'])) {
                    $teachesSubject = $teacher->subjects()->where('subjects.id', $slot['subject_id'])->exists();
                    if (!$teachesSubject) {
                        $subjectName = optional($teacher->subjects()->where('subjects.id', $slot['subject_id'])->first())->subject_name;
                        if (!$subjectName) {
                            $subjectModel = \App\Models\Subject::find($slot['subject_id']);
                            $subjectName = $subjectModel ? $subjectModel->subject_name : $slot['subject_id'];
                        }
                        return response()->json([
                            'status' => 'error',
                            'code' => 422,
                            'message' => "Teacher '{$teacher->name}' does not teach subject '{$subjectName}' for slot #" . ($index + 1)
                        ], 422);
                    }
                }
            }

            $teacherConflict = TimeSlot::where('teacher_id', $slot['teacher_id'])
                ->where('time_slot_date', $slot['time_slot_date'])
                ->get();

            foreach ($teacherConflict as $conflictSlot) {
                $conflictTime = is_array($conflictSlot->time_slot) ? $conflictSlot->time_slot : json_decode($conflictSlot->time_slot, true);
                $conflictStart = $conflictTime['start_time'];
                $conflictEnd = $conflictTime['end_time'];
                if ($startTime < $conflictEnd && $endTime > $conflictStart) {
                    return response()->json([
                        'status' => 'error',
                        'code' => 422,
                        'message' => "Teacher time conflict detected for slot #" .
                            ". Teacher '{$teacher->name}' is already assigned to another class on {$slot['time_slot_date']} from " . $conflictStart . " to " . $conflictEnd . "."
                    ], 422);
                }
            }

            $duplicateExists = TimeSlot::where('time_table_id', $timeTable->id)
                ->where('time_slot_date', $slot['time_slot_date'])
                ->where('time_slot', json_encode([
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                ]))
                ->where('location_id', $slot['location_id'] ?? null)
                ->where('teacher_id', $slot['teacher_id'] ?? null)
                ->where('subject_id', $slot['subject_id'] ?? null)
                ->exists();

            if ($duplicateExists) {
                return response()->json([
                    'status' => 'error',
                    'code' => 422,
                    'message' => "Duplicate time slot detected for slot #" . ($index + 1) .
                        ". A slot with the same date ({$slot['time_slot_date']}), time ({$startTime} - {$endTime}), location, teacher, and subject already exists in this time table."
                ], 422);
            }

            if (!empty($slot['location_id'])) {
                $existingSlots = TimeSlot::where('location_id', $slot['location_id'])
                    ->where('time_slot_date', $slot['time_slot_date'])
                    ->get();

                foreach ($existingSlots as $existing) {
                    $existingSlot = is_array($existing->time_slot) ? $existing->time_slot : json_decode($existing->time_slot, true);
                    $existingStart = $existingSlot['start_time'];
                    $existingEnd = $existingSlot['end_time'];

                    if ($startTime < $existingEnd && $endTime > $existingStart) {
                        return response()->json([
                            'status' => 'error',
                            'code' => 422,
                            'message' => "Location conflict detected for slot #" . ($index + 1) .
                                ". Location '" . optional($existing->location)->name .
                                "' is already booked on {$slot['time_slot_date']} from " . $existingStart . " to " . $existingEnd . " by another time table."
                        ], 422);
                    }
                }
            }

            $timeTable->timeSlots()->create([
                'teacher_id' => $slot['teacher_id'] ?? null,
                'subject_id' => $slot['subject_id'] ?? null,
                'location_id' => $slot['location_id'] ?? null,
                'remark' => $slot['remark'] ?? null,
                'day_of_week' => $dayOfWeek,
                'time_slot_date' => $slot['time_slot_date'],
                'time_slot' => json_encode([
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                ]),
            ]);
        }

        return response()->json([
            'status' => 'success',
            'code' => 201,
            'message' => 'Time slots created successfully.',
            'time_slots' => $timeTable->timeSlots
        ], 201);
    }
}
