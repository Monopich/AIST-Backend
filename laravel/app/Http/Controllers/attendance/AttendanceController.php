<?php

namespace App\Http\Controllers\attendance;

use App\Http\Controllers\Controller;
use App\Models\AttendanceTracking;
use App\Models\Department;
use App\Models\LeaveRequest;
use App\Models\Location;
use App\Models\QrCode;
use App\Models\TimeSlot;
use App\Models\UserDetail;
// use App\Models\TrackingAttendance;
use App\Models\User;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Storage;

class AttendanceController extends Controller
{

    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {

        $earthRadius = 6371000; // meters

        $latFrom = deg2rad($lat1);
        $lonFrom = deg2rad($lon1);
        $latTo = deg2rad($lat2);
        $lonTo = deg2rad($lon2);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));

        return $earthRadius * $angle; // distance in meters
    }

    public function testDetectLocation(Request $request, $locationId)
    {

        $validated = $request->validate([
            'latitude' => "required|numeric",
            'longitude' => "required|numeric",
        ]);
        $location = Location::find($locationId);

        $distance = $this->calculateDistance(
            $validated['latitude'],
            $validated['longitude'],
            $location->latitude,
            $location->longitude
        );

        if ($distance > 150) {
            return response()->json([
                'message' => 'You are too far from the location to check in.',
                'distance' => round($distance, 2) . ' meters'
            ], 403);
        }
        return response()->json([
            'message' => "You are successful with $distance meters"
        ]);
    }

    public function scanAttendance(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string',
            'device' => 'nullable|string',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'wifi_ssid' => 'nullable|string',
        ]);

        $user = $request->user();
        $now = now(); // Current time
        $slotDate = $now->toDateString();

        // 1. Verify QR code
        $qr = QrCode::with('location')->where('code', $validated['code'])->first();
        if (!$qr) {
            return response()->json(['message' => 'Invalid QR code.'], 404);
        }

        $location = $qr->location;

        // 2. Distance check
        $distance = $this->calculateDistance(
            $validated['latitude'],
            $validated['longitude'],
            $location->latitude,
            $location->longitude
        );
        if ($distance > 150) {
            return response()->json([
                'message' => 'You are too far from the location to check in.',
                'distance' => round($distance, 2) . ' meters'
            ], 403);
        }

        // 3. Wi-Fi check
        $expectedSsid = $location->wifi_ssid;
        if ($expectedSsid && !empty($validated['wifi_ssid']) && $expectedSsid !== $validated['wifi_ssid']) {
            return response()->json([
                'message' => 'You are outside the allowed network area.',
                'expected_ssid' => $expectedSsid,
                'provided_ssid' => $validated['wifi_ssid'] ?? null
            ], 403);
        }

        // 4. Find current slot
        if ($user->hasRole('Staff')) { // Teacher
            $timeSlots = $user->subjects()
                ->with('timeSlots')
                ->get()
                ->pluck('timeSlots')
                ->flatten()
                ->filter(function ($slot) use ($location, $now, $user) {
                    $slotTime = json_decode($slot->time_slot, true);
                    if (!$slotTime) {
                        $slot->debug_reason = 'No time_slot JSON';
                        return false;
                    }

                    if ($slot->location_id != $location->id) {
                        $slot->debug_reason = 'Location mismatch';
                        return false;
                    }

                    if (strtolower($slot->day_of_week) != strtolower($now->format('l'))) {
                        $slot->debug_reason = 'Day of week mismatch (DB: ' . $slot->day_of_week . ', Now: ' . $now->format('l') . ')';
                        return false;
                    }

                    if ($slot->teacher_id != $user->id) {
                        $slot->debug_reason = 'Teacher mismatch (DB: ' . $slot->teacher_id . ', User: ' . $user->id . ')';
                        return false;
                    }

                    $slotStart = Carbon::parse($now->toDateString() . ' ' . $slotTime['start_time']);
                    $slotEnd   = Carbon::parse($now->toDateString() . ' ' . $slotTime['end_time']);

                    if (!($slotStart->lte($now) && $slotEnd->gte($now))) {
                        $slot->debug_reason = 'Time mismatch (Start: ' . $slotStart . ', End: ' . $slotEnd . ', Now: ' . $now . ')';
                        return false;
                    }

                    $slot->debug_reason = 'Matched';
                    return true;
                })
                ->sortBy(fn($slot) => json_decode($slot->time_slot, true)['start_time']);

            $currentSlot = $timeSlots->first();

            if (!$currentSlot) {
                return response()->json([
                    'message' => 'You have no teaching slot right now.',
                    'time-now' => $now->format('d-m-Y H:i:s'),
                ], 403);
            }
        } else { // Student
            $timeSlots = $user->groups()
                ->with('timeTables.timeSlots')
                ->get()
                ->pluck('timeTables.*.timeSlots.*')
                ->flatten()
                ->filter(function ($slot) use ($location, $now, $slotDate) {
                    $slotTime = json_decode($slot->time_slot, true);
                    return $slot->location_id == $location->id
                        && Carbon::parse($slot->time_slot_date)->toDateString() == $slotDate
                        && $slotTime
                        && Carbon::parse($slotDate . ' ' . $slotTime['start_time'])->lte($now)
                        && Carbon::parse($slotDate . ' ' . $slotTime['end_time'])->gte($now);
                })
                ->sortBy(fn($slot) => json_decode($slot->time_slot, true)['start_time']);
        }

        $currentSlot = $timeSlots->first();

        if (!$currentSlot) {
            return response()->json([
                'message' => 'You are not scheduled for this location at this time.',
                'time-now' => $now->format('d-m-Y H:i:s')
            ], 403);
        }

        // 5. Prevent duplicate attendance first
        $existingAttendance = AttendanceTracking::where('user_id', $user->id)
            ->where('time_slot_id', $currentSlot->id)
            ->whereDate('attendance_date', $slotDate)
            ->first();

        if ($existingAttendance) {
            return response()->json([
                'message' => 'You have already completed attendance for this time slot.',
                'attendance' => $existingAttendance
            ], 200);
        }

        // 6. Check teacher leave (applies to both teachers & students)
        $teacherLeave = LeaveRequest::where('user_id', $currentSlot->teacher_id ?? null)
            ->where('status', 'Approved')
            ->whereDate('start_date', '<=', $slotDate)
            ->whereDate('end_date', '>=', $slotDate)
            ->first();

        if ($teacherLeave) {
            $attendance = AttendanceTracking::create([
                'user_id' => $user->id,
                'qr_code_id' => $qr->id,
                'check_in_time' => $now,
                'check_out_time' => Carbon::parse($slotDate . ' ' . json_decode($currentSlot->time_slot, true)['end_time']),
                'scanned_at' => $now,
                'attendance_date' => $slotDate,
                'device' => $validated['device'] ?? null,
                'status' => 'No class',
                'leave_request_id' => $teacherLeave->id,
                'request_attendance_id' => null,
                'time_slot_id' => $currentSlot->id,
            ]);

            return response()->json([
                'message' => 'Teacher is on leave, class cancelled for this slot.',
                'attendance' => $attendance,
                'current_slot' => $currentSlot
            ], 200);
        }

        // 7. Mark Present/Late
        $slotTime = json_decode($currentSlot->time_slot, true);
        $slotStart = Carbon::parse($slotDate . ' ' . $slotTime['start_time']);
        $graceTime = $slotStart->copy()->addMinutes(15);
        $status = $now->lte($graceTime) ? 'Present' : 'Late';

        $attendance = AttendanceTracking::create([
            'user_id' => $user->id,
            'qr_code_id' => $qr->id,
            'check_in_time' => $now,
            'check_out_time' => Carbon::parse($slotDate . ' ' . $slotTime['end_time']),
            'scanned_at' => $now,
            'attendance_date' => $slotDate,
            'device' => $validated['device'] ?? null,
            'status' => $status,
            'leave_request_id' => null,
            'request_attendance_id' => null,
            'time_slot_id' => $currentSlot->id,
        ]);

        $currentSlotForToday = $currentSlot->replicate();
        $currentSlotForToday->time_slot_date = $slotDate;

        return response()->json([
            'message' => $user->hasRole('Staff') ? 'Teacher check-in successful' : 'Check-in successful',
            'attendance' => $attendance,
            'current_slot' => $currentSlotForToday
        ]);
    }

    public function getAttendance(Request $request)
    {
        $user = $request->user();

        $perPage = $request->query('per_page', 14);

        // Get filters from query params
        $dateFilter = $request->query('date');
        $monthFilter = $request->query('month');
        $yearFilter = $request->query('year');
        $statusFilter = $request->query('status');

        $attendanceQuery = AttendanceTracking::where('user_id', $user->id)
            ->orderBy('scanned_at', 'desc');

        // Filter by exact date
        if ($dateFilter) {
            $attendanceQuery->whereDate('attendance_date', $dateFilter);
        }

        // Filter by month
        if ($monthFilter) {
            $attendanceQuery->whereMonth('attendance_date', $monthFilter);
        }

        // Filter by year
        if ($yearFilter) {
            $attendanceQuery->whereYear('attendance_date', $yearFilter);
        }

        // Apply status filter
        if ($statusFilter) {
            $attendanceQuery->where('status', $statusFilter);
        }


        $attendance = $attendanceQuery->paginate($perPage);

        if ($attendance->isEmpty()) {
            return response()->json([
                'message' => 'No attendance records found for the specified filters.'
            ], 200);
        }

        // Load qrCode and location relationships
        $attendance->load(['timeSlot', 'timeSlot.teacher:id,name', 'timeSlot.location:id,name,building_id', 'timeSlot.subject:id,subject_name']);

        $attendance->getCollection()->transform(function ($attendance) {
            $timeSlot = $attendance->timeSlot;
            return [
                'id' => $attendance->id,
                'check_in_time' => $attendance->check_in_time,
                'check_out_time' => $attendance->check_out_time,
                'scanned_at' => $attendance->scanned_at,
                'request_attendance_id' => $attendance->request_attendance_id,
                'leave_request_id' => $attendance->request_attendance_id,
                'user_id' => $attendance->user_id,
                'qr_code_id' => $attendance->qr_code_id,
                'attendance_date' => $attendance->attendance_date,
                'status' => $attendance->status,
                'teacher_name' => optional(optional($timeSlot)->teacher)->name,
                'subject_name' => optional(optional($timeSlot)->subject)->subject_name,
                'location_name' => optional(optional($timeSlot)->location)->name,
                'time_of_slot' => optional(optional($timeSlot))->time_slot,
                'day_of_week' => optional(optional($timeSlot))->day_of_week,
                'time_slot' => $attendance->timeSlot, // keep if you still want full slot
            ];
        });

        return response()->json([
            'attendance' => $attendance
        ]);
    }

    public function getAllAttendance(Request $request)
    {
        $perPage = $request->input('per_page', 14);

        $query = AttendanceTracking::query()->with(['timeSlot', 'timeSlot.teacher:id,name', 'timeSlot.location:id,name,building_id', 'timeSlot.subject:id,subject_name']);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by attendance_date (exact match)
        if ($request->filled('attendance_date')) {
            $query->whereDate('attendance_date', $request->attendance_date);
        }

        $attendances = $query->orderBy('attendance_date', 'desc')->paginate($perPage);

        if ($attendances->isEmpty()) {
            return response()->json([
                'message' => 'No attendance records found.'
            ], 404);
        }

        $attendances->getCollection()->transform(function ($attendance) {
            $timeSlot = $attendance->timeSlot;
            return [
                'id' => $attendance->id,
                'check_in_time' => $attendance->check_in_time,
                'check_out_time' => $attendance->check_out_time,
                'scanned_at' => $attendance->scanned_at,
                'request_attendance_id' => $attendance->request_attendance_id,
                'leave_request_id' => $attendance->request_attendance_id,
                'user_id' => $attendance->user_id,
                'attendance_date' => $attendance->attendance_date,
                'status' => $attendance->status,
                'qr_code_id' => $attendance->qr_code_id,
                'teacher_name' => optional(optional($timeSlot)->teacher)->name,
                'subject_name' => optional(optional($timeSlot)->subject)->subject_name,
                'location_name' => optional(optional($timeSlot)->location)->name,
                'time_of_slot' => optional(optional($timeSlot))->time_slot,
                'day_of_week' => optional(optional($timeSlot))->day_of_week,
                'time_slot' => $attendance->timeSlot, // keep if you still want full slot
            ];
        });

        return response()->json([
            'message' => 'List all attendances success.',
            'attendances' => $attendances
        ]);
    }

    public function removeAttendance($attendance_id)
    {

        $attendance = AttendanceTracking::find($attendance_id);

        if (!$attendance) {
            return response()->json([
                'message' => 'Attendance not found'
            ], 404);
        }

        $attendance->delete();

        return response()->json([
            'message' => "Attendance id  $attendance_id removed successful. "
        ]);
    }

    public function createLeaveRequest(Request $request)
    {
        try {
            $validated = $request->validate([
                'type' => 'required|string',
                'start_date' => 'required|date|after_or_equal:today',
                'end_date' => 'required|date|after_or_equal:start_date',
                'reason' => 'nullable|string',
                'handover_detail' => 'nullable|string',
                'emergency_contact' => 'nullable|string',
                'document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            ], [
                'start_date.after_or_equal' => 'Start date must be today or later',
                'end_date.after_or_equal' => 'End date must be the same or after start date',
                'document.mimes' => 'Document must be a file of type: pdf, jpg, jpeg, png',
                'document.max' => 'Document size must not exceed 10MB',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        }

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => "User is not exists ."
            ]);
        }
        if ($request->hasFile('document')) {
            $extension = $request->file('document')->getClientOriginalExtension();
            $leaveDate = Carbon::parse($validated['start_date'])->format('Y_m_d');
            $filename = 'leave_request_' . $user->id . '_at_' . $leaveDate . '.' . $extension;

            $filePath = $request->file('document')->storeAs(
                'leave_documents',
                $filename,
                'public'
            );
        }


        //Check for overlapping leave requests
        $startDate = Carbon::parse($validated['start_date'])->startOfDay();
        $endDate = Carbon::parse($validated['end_date'])->endOfDay();

        // Check for overlapping leave requests (only Pending or Approved)
        $hasOverlap = LeaveRequest::where('user_id', $user->id)
            ->whereIn('status', ['Pending', 'Approved'])
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate)
            ->exists();

        if ($hasOverlap) {
            return response()->json([
                'message' => 'You already have a leave request during this period.',
            ], 422);
        }

        $leaveRequest = LeaveRequest::create([
            'user_id' => $user->id,
            'type' => $validated['type'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'reason' => $validated['reason'] ?? null,
            'handover_detail' => $validated['handover_detail'] ?? null,
            'emergency_contact' => $validated['emergency_contact'] ?? null,
            'document' => $filePath ?? null,
            'requested_at' => Carbon::now(),
        ]);

        return response()->json([
            'message' => 'Create list request successful',
            'leave_request' => $leaveRequest,
            'document_path' => $filePath ?? null
        ], 201);
    }

    public function getAllLeaveRequests(Request $request)
    {
        $user = Auth::user();

        $query = LeaveRequest::where('user_id', $user->id);

        // Optional filter by status
        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }

        $leaveRequests = $query->get();

        if ($leaveRequests->isEmpty()) {
            return response()->json([
                'message' => "Request not available."
            ], 404);
        }

        return response()->json([
            'message' => "List all requests successful.",
            'requests' => $leaveRequests
        ]);
    }

    public function approveRequest(Request $request)
    {
        $validated = $request->validate([
            'request_id' => 'required|exists:leave_requests,id',
        ]);

        $leaveRequest = LeaveRequest::find($validated['request_id']);

        if (!$leaveRequest) {
            return response()->json([
                'message' => 'Leave request not found.'
            ], 404);
        }

        if ($leaveRequest->status !== 'Pending') {
            return response()->json([
                'message' => "This request has already been verified.",
                'current_status' => $leaveRequest->status
            ], 400);
        }

        $leaveRequest->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return response()->json([
            'message' => 'Leave request approved successfully.',
            'leave_request' => $leaveRequest,
            'approved_by' => $request->user()->only('name', 'email')
        ]);
    }
    public function rejectRequest(Request $request)
    {
        $validated = $request->validate([
            'request_id' => 'required|exists:leave_requests,id',
            'remark' => 'nullable|string'
        ]);

        $leaveRequest = LeaveRequest::find($validated['request_id']);

        if (!$leaveRequest) {
            return response()->json([
                'message' => 'Leave request not found.'
            ], 404);
        }

        if ($leaveRequest->status !== 'Pending') {
            return response()->json([
                'message' => "This request has already been verified.",
                'current_status' => $leaveRequest->status
            ], 400);
        }

        $leaveRequest->update([
            'status' => 'Rejected',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'remark' => $validated['remark']
        ]);

        return response()->json([
            'message' => 'Leave request rejected successfully.',
            'leave_request' => $leaveRequest,
            'approved_by' => $request->user()->only('name', 'email')
        ]);
    }

    public function getAllLeaveRequestsByAdmin(Request $request)
    {
        $perPage = $request->input('per_page', 14);

        // Start query
        $query = LeaveRequest::orderBy('requested_at', 'asc');


        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }

        // Paginate after filtering
        $leaveRequests = $query->paginate($perPage);

        if ($leaveRequests->isEmpty()) {
            return response()->json([
                'message' => "Request not available."
            ], 404);
        }
        $leaveRequests->getCollection()->transform(function ($leaveRequest) {
            return [
                'id' => $leaveRequest->id,
                'status' => $leaveRequest->status,
                'start_date' => $leaveRequest->start_date,
                'end_date' => $leaveRequest->end_date,
                'type' => $leaveRequest->type,
                'requested_at' => $leaveRequest->requested_at,
                'approved_at' => $leaveRequest->approved_at,
                'approved_by' => $leaveRequest->approved_by,
                'user_id' => $leaveRequest->user_id,
                'id_card' => $leaveRequest->user->userDetail->id_card ?? null,
                'latin_name' => $leaveRequest->user->userDetail->latin_name ?? null,
                'emergency_contact' => $leaveRequest->emergency_contact,
                'reason' => $leaveRequest->reason,
                'remark' => $leaveRequest->remark,
                'approved_by_name' => $leaveRequest->approved_by_name,
                'handover_detail' => $leaveRequest->handover_detail,
                'total_days' => $leaveRequest->total_days,
                'document' => $leaveRequest->document,
                'approved_by_user' => $leaveRequest->approved_by_user,

            ];
        });

        return response()->json([
            'message' => "List all requests successful.",
            'requests' => $leaveRequests
        ]);
    }

    public function removeLeaveRequest($id)
    {
        $leaveRequest = LeaveRequest::findOrFail($id);

        if (!$leaveRequest) {
            return response()->json([
                'message' => "Leave not found"
            ]);
        }

        $leaveRequest->delete();

        return response()->json([
            'message' => "Deleted successful ."
        ]);
    }

    /**
     * Admin/Staff: Get all leave requests for Staff (teachers)
     */
    public function getAllLeaveRequestsByTeacher(Request $request)
    {
        $user = $request->user();

        // Only allow users with role Staff (admins)
        if (!$user->hasRole('Staff')) {
            return response()->json([
                'message' => 'Unauthorized. Only Staff can access this.'
            ], 403);
        }

        $perPage = $request->input('per_page', 14);

        // Get all leave requests of users with role Staff (teachers)
        $query = LeaveRequest::whereHas('user.roles', function ($q) {
            $q->where('role_key', 'Staff');
        });

        // Optional filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('start_date')) {
            $query->whereDate('start_date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('end_date', '<=', $request->end_date);
        }

        $leaveRequests = $query->orderBy('requested_at', 'desc')->paginate($perPage);

        if ($leaveRequests->isEmpty()) {
            return response()->json([
                'message' => "No leave requests found."
            ], 404);
        }

        // Transform response
        $leaveRequests->getCollection()->transform(function ($leaveRequest) {
            return [
                'id' => $leaveRequest->id,
                'status' => $leaveRequest->status,
                'start_date' => $leaveRequest->start_date,
                'end_date' => $leaveRequest->end_date,
                'type' => $leaveRequest->type,
                'requested_at' => $leaveRequest->requested_at,
                'approved_at' => $leaveRequest->approved_at,
                'approved_by' => $leaveRequest->approved_by,
                'user_id' => $leaveRequest->user_id,
                'name' => $leaveRequest->user->name ?? null,
                'id_card' => $leaveRequest->user->userDetail->id_card ?? null,
                'latin_name' => $leaveRequest->user->userDetail->latin_name ?? null,
                'emergency_contact' => $leaveRequest->emergency_contact,
                'remark' => $leaveRequest->remark,
                'handover_detail' => $leaveRequest->handover_detail,
                'total_days' => $leaveRequest->total_days,
                'document' => $leaveRequest->document,
            ];
        });

        return response()->json([
            'message' => "List of Staff leave requests retrieved successfully.",
            'requests' => $leaveRequests
        ]);
    }

    /**
     * Teacher: Get leave requests submitted by the authenticated teacher
     */
    public function getLeaveRequestTeacher(Request $request)
    {
        $authUser = $request->user(); // Logged-in teacher

        // Only allow Staff role
        if (!$authUser->hasRole('Staff')) {
            return response()->json([
                'message' => 'Unauthorized. Only teachers can access this.'
            ], 403);
        }

        $currentYear = now()->year;

        $leaveRequests = LeaveRequest::where('user_id', $authUser->id)
            // ->whereYear('requested_at', $currentYear)
            ->orderBy('requested_at', 'desc')
            ->get()
            ->map(function ($leaveRequest) {
                return [
                    'id' => $leaveRequest->id,
                    'status' => $leaveRequest->status,
                    'start_date' => $leaveRequest->start_date,
                    'end_date' => $leaveRequest->end_date,
                    'type' => $leaveRequest->type,
                    'requested_at' => $leaveRequest->requested_at,
                    'approved_at' => $leaveRequest->approved_at,
                    'approved_by' => $leaveRequest->approved_by,
                    'user_id' => $leaveRequest->user_id,
                    'name' => $leaveRequest->user->name ?? null,
                    'id_card' => $leaveRequest->user->userDetail->id_card ?? null,
                    'latin_name' => $leaveRequest->user->userDetail->latin_name ?? null,
                    'emergency_contact' => $leaveRequest->emergency_contact,
                    'reason' => $leaveRequest->reason,
                    'remark' => $leaveRequest->remark,
                    'handover_detail' => $leaveRequest->handover_detail,
                    'total_days' => $leaveRequest->total_days,
                    'document' => $leaveRequest->document,
                ];
            });

        return response()->json([
            'success' => true,
            'requests' => $leaveRequests
        ]);
    }

    /**
     * Teacher: Create a leave request
     */
    public function createTeacherLeaveRequest(Request $request)
    {
        $user = $request->user();
        // Max leave request limit (18)
        $MAX_LEAVE_REQUESTS = 18;

        // Role check
        if (!$user->hasRole('Staff')) {
            return response()->json([
                'message' => 'Unauthorized. Only teachers can create a leave request.'
            ], 403);
        }

        $currentYear = now()->year;

        // 🔥 FIX 1: Count only PENDING and APPROVED requests for the current year
        $currentYearLeaveCount = LeaveRequest::where('user_id', $user->id)
            ->whereYear('requested_at', $currentYear)
            ->whereIn('status', ['Pending', 'Approved'])
            ->count();

        if ($currentYearLeaveCount >= $MAX_LEAVE_REQUESTS) {
            return response()->json([
                'message' => "You have reached the maximum number of leave requests for {$currentYear} ({$MAX_LEAVE_REQUESTS})."
            ], 403);
        }

        // Validation
        try {
            $validated = $request->validate([
                'type' => 'required|string',
                'start_date' => 'required|date|after_or_equal:today',
                'end_date' => 'required|date|after_or_equal:start_date',
                'reason' => 'nullable|string',
                'handover_detail' => 'nullable|string',
                'emergency_contact' => 'nullable|string',
                'document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            ], [
                'start_date.after_or_equal' => 'Start date must be today or later',
                'end_date.after_or_equal' => 'End date must be the same or after start date',
                'document.mimes' => 'Document must be a file of type: pdf, jpg, jpeg, png',
                'document.max' => 'Document size must not exceed 10MB',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        }

        // 4️⃣ Overlapping leave check
        $startDate = Carbon::parse($validated['start_date'])->startOfDay();
        $endDate   = Carbon::parse($validated['end_date'])->endOfDay();

        $hasOverlap = LeaveRequest::where('user_id', $user->id)
            ->whereIn('status', ['Pending', 'Approved'])
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate)
            ->exists();

        if ($hasOverlap) {
            return response()->json([
                'message' => 'You already have a leave request during this period.'
            ], 422);
        }

        // File upload
        $filePath = null;
        if ($request->hasFile('document')) {
            $extension = $request->file('document')->getClientOriginalExtension();
            $leaveDate = Carbon::parse($validated['start_date'])->format('Y_m_d');
            $filename = 'teacher_leave_' . $user->id . '_at_' . $leaveDate . '.' . $extension;
            $filePath = $request->file('document')->storeAs('leave_documents', $filename, 'public');
        }

        // Create leave request
        $leaveRequest = LeaveRequest::create([
            'user_id' => $user->id,
            'type' => $validated['type'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'reason' => $validated['reason'] ?? null,
            'handover_detail' => $validated['handover_detail'] ?? null,
            'emergency_contact' => $validated['emergency_contact'] ?? null,
            'document' => $filePath,
            'requested_at' => now(),
        ]);

        // Calculate remaining requests for the current year
        $remainingRequests = max(0, $MAX_LEAVE_REQUESTS - ($currentYearLeaveCount + 1));

        // Success response
        return response()->json([
            'message' => 'Teacher leave request created successfully.',
            'leave_request' => $leaveRequest,
            'document_path' => $filePath,
            'remaining_requests' => $remainingRequests
        ], 201);
    }

    /**
 * Student: Create a leave request
 */
    public function createStudentLeaveRequest(Request $request)
    {
        $user = $request->user();
        // Max leave request limit (18)
        $MAX_LEAVE_REQUESTS = 18;

        // Only allow Students
        if (!$user->hasRole('Student')) {
            return response()->json([
                'message' => 'Unauthorized. Only students can create a leave request.'
            ], 403);
        }

        $currentYear = now()->year;

        // 🔥 FIX 1: Count only PENDING and APPROVED requests for the current year
        $currentYearLeaveCount = LeaveRequest::where('user_id', $user->id)
            ->whereYear('requested_at', $currentYear)
            ->whereIn('status', ['Pending', 'Approved'])
            ->count();

        if ($currentYearLeaveCount >= $MAX_LEAVE_REQUESTS) {
            return response()->json([
                'message' => "You have reached the maximum number of leave requests for {$currentYear} ({$MAX_LEAVE_REQUESTS})."
            ], 403);
        }

        try {
            $validated = $request->validate([
                'type' => 'required|string',
                'start_date' => 'required|date|after_or_equal:today',
                'end_date' => 'required|date|after_or_equal:start_date',
                'reason' => 'nullable|string',
                'handover_detail' => 'nullable|string',
                'emergency_contact' => 'nullable|string',
                'document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            ], [
                'start_date.after_or_equal' => 'Start date must be today or later',
                'end_date.after_or_equal' => 'End date must be the same or after start date',
                'document.mimes' => 'Document must be a file of type: pdf, jpg, jpeg, png',
                'document.max' => 'Document size must not exceed 10MB',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        }

        // File upload
        $filePath = null;
        if ($request->hasFile('document')) {
            $extension = $request->file('document')->getClientOriginalExtension();
            $leaveDate = Carbon::parse($validated['start_date'])->format('Y_m_d');
            $filename = 'student_leave_' . $user->id . '_at_' . $leaveDate . '.' . $extension;
            $filePath = $request->file('document')->storeAs('leave_documents', $filename, 'public');
        }

        // Overlapping leave check
        $startDate = Carbon::parse($validated['start_date'])->startOfDay();
        $endDate = Carbon::parse($validated['end_date'])->endOfDay();

        $hasOverlap = LeaveRequest::where('user_id', $user->id)
            ->whereIn('status', ['Pending', 'Approved'])
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate)
            ->exists();

        if ($hasOverlap) {
            return response()->json([
                'message' => 'You already have a leave request during this period.'
            ], 422);
        }

        // Create leave request
        $leaveRequest = LeaveRequest::create([
            'user_id' => $user->id,
            'type' => $validated['type'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'reason' => $validated['reason'] ?? null,
            'handover_detail' => $validated['handover_detail'] ?? null,
            'emergency_contact' => $validated['emergency_contact'] ?? null,
            'document' => $filePath,
            'requested_at' => now(),
        ]);

        // Calculate remaining requests for the current year
        $remainingRequests = max(0, $MAX_LEAVE_REQUESTS - ($currentYearLeaveCount + 1));

        return response()->json([
            'message' => 'Student leave request created successfully.',
            'leave_request' => $leaveRequest,
            'document_path' => $filePath,
            'remaining_requests' => $remainingRequests

        ], 201);
    }

    /**
 * Student: Get leave requests submitted by the authenticated student
 */
    public function getLeaveRequestStudent(Request $request)
    {
        $user = $request->user();

        // Only allow Students
        if (!$user->hasRole('Student')) {
            return response()->json([
                'message' => 'Unauthorized. Only students can access this.'
            ], 403);
        }

        $currentYear = now()->year;

        $leaveRequests = LeaveRequest::where('user_id', $user->id)
            // ->whereYear('requested_at', $currentYear)
            ->orderBy('requested_at', 'desc')
            ->get()
            ->map(function ($leaveRequest) {
                return [
                    'id' => $leaveRequest->id,
                    'status' => $leaveRequest->status,
                    'start_date' => $leaveRequest->start_date,
                    'end_date' => $leaveRequest->end_date,
                    'type' => $leaveRequest->type,
                    'requested_at' => $leaveRequest->requested_at,
                    'approved_at' => $leaveRequest->approved_at,
                    'approved_by' => $leaveRequest->approved_by,
                    'user_id' => $leaveRequest->user_id,
                    'name' => $leaveRequest->user->name ?? null,
                    'id_card' => $leaveRequest->user->userDetail->id_card ?? null,
                    'latin_name' => $leaveRequest->user->userDetail->latin_name ?? null,
                    'emergency_contact' => $leaveRequest->emergency_contact,
                    'reason' => $leaveRequest->reason,
                    'remark' => $leaveRequest->remark,
                    'handover_detail' => $leaveRequest->handover_detail,
                    'total_days' => $leaveRequest->total_days,
                    'document' => $leaveRequest->document,
                ];
            });

        return response()->json([
            'success' => true,
            'requests' => $leaveRequests
        ]);
    }

    /**
     * Head of Department: Create leave request
     */
    public function createHodLeaveRequest(Request $request)
    {
        $user = $request->user();
        // Max leave request limit (18)
        $MAX_LEAVE_REQUESTS = 18;

        if (!$user->hasRole('Head Department')) {
            return response()->json([
                'message' => 'Unauthorized.'
            ], 403);
        }

        $currentYear = now()->year;

        // 🔥 FIX 1: Count only PENDING and APPROVED requests for the current year
        $currentYearLeaveCount = LeaveRequest::where('user_id', $user->id)
            ->whereYear('requested_at', $currentYear)
            ->whereIn('status', ['Pending', 'Approved'])
            ->count();

        if ($currentYearLeaveCount >= $MAX_LEAVE_REQUESTS) {
            return response()->json([
                'message' => "You have reached the maximum number of leave requests for {$currentYear} ({$MAX_LEAVE_REQUESTS})."
            ], 403);
        }

        try {
            $validated = $request->validate([
                'type' => 'required|string',
                'start_date' => 'required|date|after_or_equal:today',
                'end_date' => 'required|date|after_or_equal:start_date',
                'reason' => 'nullable|string',
                'handover_detail' => 'nullable|string',
                'emergency_contact' => 'nullable|string',
                'document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            ], [
                'start_date.after_or_equal' => 'Start date must be today or later',
                'end_date.after_or_equal' => 'End date must be the same or after start date',
                'document.mimes' => 'Document must be a file of type: pdf, jpg, jpeg, png',
                'document.max' => 'Document size must not exceed 10MB',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        }

        // Upload document
        $filePath = null;
        if ($request->hasFile('document')) {
            $extension = $request->file('document')->getClientOriginalExtension();
            $leaveDate = Carbon::parse($validated['start_date'])->format('Y_m_d');
            $filename = 'hod_leave_' . $user->id . '_at_' . $leaveDate . '.' . $extension;

            $filePath = $request->file('document')
                ->storeAs('leave_documents', $filename, 'public');
        }

        // Overlapping leave check
        $startDate = Carbon::parse($validated['start_date'])->startOfDay();
        $endDate = Carbon::parse($validated['end_date'])->endOfDay();

        $hasOverlap = LeaveRequest::where('user_id', $user->id)
            ->whereIn('status', ['Pending', 'Approved'])
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate)
            ->exists();

        if ($hasOverlap) {
            return response()->json([
                'message' => 'You already have a leave request during this period.'
            ], 422);
        }

        $leaveRequest = LeaveRequest::create([
            'user_id' => $user->id,
            'type' => $validated['type'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'reason' => $validated['reason'] ?? null,
            'handover_detail' => $validated['handover_detail'] ?? null,
            'emergency_contact' => $validated['emergency_contact'] ?? null,
            'document' => $filePath,
            'requested_at' => now(),
            'status' => 'Pending',
        ]);

         // Calculate remaining requests for the current year
        $remainingRequests = max(0, $MAX_LEAVE_REQUESTS - ($currentYearLeaveCount + 1));

        return response()->json([
            'message' => 'HOD leave request created successfully.',
            'leave_request' => $leaveRequest,
            'document_path' => $filePath,
            'remaining_requests' => $remainingRequests
        ], 201);
    }


    /**
     * Head of Department: Get own leave requests
     */
    public function getLeaveRequestHod(Request $request)
    {
        $authUser = $request->user();

        if (!$authUser->hasRole('Head Department')) {
            return response()->json([
                'message' => 'Unauthorized.'
            ], 403);
        }

        $currentYear = now()->year;

        $leaveRequests = LeaveRequest::where('user_id', $authUser->id)
            // ->whereYear('requested_at', $currentYear)
            ->orderBy('requested_at', 'desc')
            ->get()
            ->map(function ($leaveRequest) {
                 return [
                    'id' => $leaveRequest->id,
                    'status' => $leaveRequest->status,
                    'start_date' => $leaveRequest->start_date,
                    'end_date' => $leaveRequest->end_date,
                    'type' => $leaveRequest->type,
                    'requested_at' => $leaveRequest->requested_at,
                    'approved_at' => $leaveRequest->approved_at,
                    'approved_by' => $leaveRequest->approved_by,
                    'user_id' => $leaveRequest->user_id,
                    'name' => $leaveRequest->user->name ?? null,
                    'id_card' => $leaveRequest->user->userDetail->id_card ?? null,
                    'latin_name' => $leaveRequest->user->userDetail->latin_name ?? null,
                    'emergency_contact' => $leaveRequest->emergency_contact,
                    'reason' => $leaveRequest->reason,
                    'remark' => $leaveRequest->remark,
                    'handover_detail' => $leaveRequest->handover_detail,
                    'total_days' => $leaveRequest->total_days,
                    'document' => $leaveRequest->document,
                ];
            });

        return response()->json([
            'success' => true,
            'requests' => $leaveRequests
        ]);
    }


    public function getLeaveRequestsByHod(Request $request)
    {
        $hod = $request->user();

        if (!$hod->hasRole('Head Department')) {
            return response()->json([
                'message' => 'Unauthorized. Only Head Department can access this.'
            ], 403);
        }

        // ✅ 1) get from relation first
        $departmentId = Department::where('department_head_id', $hod->id)->value('id');

        // ✅ 2) fallback: query user_details directly (if relation missing)
        if (!$departmentId) {
            $departmentId = UserDetail::where('user_id', $hod->id)->value('department_id');
        }

        // ✅ 3) if still null => real problem in DB
        if (!$departmentId) {
            return response()->json([
                'status' => 'bad_request',
                'code' => 422,
                'message' => 'HOD department not found.',
                'hod_id' => $hod->id
            ], 422);
        }

        $perPage = (int) $request->query('per_page', 14);

        $status = $request->query('status');
        $role   = $request->query('role');

        $allowedRoles  = ['Student', 'Staff'];
        $allowedStatus = ['Pending', 'Approved', 'Rejected'];

        $query = LeaveRequest::query()
            ->with([
                'user:id,name,email',
                'user.roles:id,name,role_key',
                'user.userDetail:user_id,id_card,latin_name,khmer_name,department_id',
            ])
            ->whereHas('user.userDetail', fn ($q) => $q->where('department_id', $departmentId))
            ->whereHas('user.roles', function ($q) use ($role, $allowedRoles) {
                $useRoles = ($role && in_array($role, $allowedRoles)) ? [$role] : $allowedRoles;
                $q->whereIn('role_key', $useRoles);
            })
            ->orderByDesc('requested_at');

        if ($status && in_array($status, $allowedStatus)) {
            $query->where('status', $status);
        }

        if ($request->filled('start_date')) {
            $query->whereDate('start_date', '>=', $request->start_date); // YYYY-MM-DD
        }
        if ($request->filled('end_date')) {
            $query->whereDate('end_date', '<=', $request->end_date); // YYYY-MM-DD
        }

        return response()->json([
            'message' => 'Leave requests retrieved successfully.',
            'department_id' => $departmentId,
            'requests' => $query->paginate($perPage),
        ], 200);
    }

    public function approveLeaveRequestByHod(Request $request, $id)
    {
        $hod = $request->user();

        // ✅ allow both role naming
        if (!$hod || (!$hod->hasRole('Head Department') && !$hod->hasRole('Head_Department'))) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only Head Department can approve.'
            ], 403);
        }

        // ✅ SAME department logic as getLeaveRequestsByHod()
        $departmentId = Department::where('department_head_id', $hod->id)->value('id');
        if (!$departmentId) {
            $departmentId = UserDetail::where('user_id', $hod->id)->value('department_id');
        }
        if (!$departmentId) {
            return response()->json([
                'success' => false,
                'message' => 'HOD department not found.',
                'hod_id' => $hod->id
            ], 422);
        }

        $leaveRequest = LeaveRequest::with(['user.userDetail', 'user.roles'])->find($id);
        if (!$leaveRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Leave request not found.'
            ], 404);
        }

        if ($leaveRequest->status !== 'Pending') {
            return response()->json([
                'success' => false,
                'message' => 'This request has already been processed.',
                'current_status' => $leaveRequest->status
            ], 400);
        }

        // ✅ department match
        $requestUserDept = optional(optional($leaveRequest->user)->userDetail)->department_id;
        if ((int)$requestUserDept !== (int)$departmentId) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden. Different department.',
                'hod_department_id' => $departmentId,
                'request_department_id' => $requestUserDept,
            ], 403);
        }

        // ✅ allow only Student/Staff (keep as you want)
        $isStudentOrStaff = optional($leaveRequest->user)->roles?->contains(
            fn($r) => in_array($r->role_key, ['Student', 'Staff'])
        );

        if (!$isStudentOrStaff) {
            return response()->json([
                'success' => false,
                'message' => 'Only Student/Staff leave requests can be approved here.'
            ], 403);
        }

        $leaveRequest->update([
            'status' => 'Approved',
            'approved_by' => $hod->id,
            'approved_at' => now(),
        ]);

        $leaveRequest->refresh()->load(['user.userDetail', 'user.roles']);

        return response()->json([
            'success' => true,
            'message' => 'Leave request approved successfully.',
            'leave_request' => $leaveRequest
        ], 200);
    }

    public function rejectLeaveRequestByHod(Request $request, $id)
    {
        $hod = $request->user();

        // ✅ allow both role naming
        if (!$hod || (!$hod->hasRole('Head Department') && !$hod->hasRole('Head_Department'))) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only Head Department can reject.'
            ], 403);
        }

        $validated = $request->validate([
            'remark' => 'required|string'
        ]);

        // ✅ SAME department logic as getLeaveRequestsByHod()
        $departmentId = Department::where('department_head_id', $hod->id)->value('id');
        if (!$departmentId) {
            $departmentId = UserDetail::where('user_id', $hod->id)->value('department_id');
        }
        if (!$departmentId) {
            return response()->json([
                'success' => false,
                'message' => 'HOD department not found.',
                'hod_id' => $hod->id
            ], 422);
        }

        $leaveRequest = LeaveRequest::with(['user.userDetail', 'user.roles'])->find($id);
        if (!$leaveRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Leave request not found.'
            ], 404);
        }

        if ($leaveRequest->status !== 'Pending') {
            return response()->json([
                'success' => false,
                'message' => 'This request has already been processed.',
                'current_status' => $leaveRequest->status
            ], 400);
        }

        // ✅ department match
        $requestUserDept = optional(optional($leaveRequest->user)->userDetail)->department_id;
        if ((int)$requestUserDept !== (int)$departmentId) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden. Different department.',
                'hod_department_id' => $departmentId,
                'request_department_id' => $requestUserDept,
            ], 403);
        }

        // ✅ allow only Student/Staff
        $isStudentOrStaff = optional($leaveRequest->user)->roles?->contains(
            fn($r) => in_array($r->role_key, ['Student', 'Staff'])
        );

        if (!$isStudentOrStaff) {
            return response()->json([
                'success' => false,
                'message' => 'Only Student/Staff leave requests can be rejected here.'
            ], 403);
        }

        $leaveRequest->update([
            'status' => 'Rejected',
            'approved_by' => $hod->id,
            'approved_at' => now(),
            'remark' => $validated['remark'],
        ]);

        $leaveRequest->refresh()->load(['user.userDetail', 'user.roles']);

        return response()->json([
            'success' => true,
            'message' => 'Leave request rejected successfully.',
            'leave_request' => $leaveRequest
        ], 200);
    }


}
