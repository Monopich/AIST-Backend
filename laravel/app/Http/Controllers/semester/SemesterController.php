<?php

namespace App\Http\Controllers\semester;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Group;
use App\Models\Semester;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SemesterController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | CREATE NEW SEMESTER
    |--------------------------------------------------------------------------
    */

    public function createNewSemesterProgram(Request $request)
    {
        $validated = $request->validate([
            'semester_key' => 'nullable|string',
            'semester_number' => 'required|numeric',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'program_id' => 'required|exists:programs,id',
        ]);

        // ---------------------- DATE VALIDATION ----------------------
        if (isset($validated['start_date'])) {
            $startDate = Carbon::parse($validated['start_date']);

            if ($startDate->dayOfWeek !== Carbon::MONDAY) {
                return response()->json([
                    'message' => 'The semester start_date must be a Monday.'
                ], 422);
            }
        }

        if (isset($validated['end_date'])) {
            $endDate = Carbon::parse($validated['end_date']);

            if ($endDate->dayOfWeek !== Carbon::SUNDAY) {
                return response()->json([
                    'message' => 'The semester end_date must be a Sunday.'
                ], 422);
            }
        }

        // ---------------------- ACADEMIC YEAR LOGIC ----------------------
        $semesterStart = Carbon::parse($validated['start_date']);
        $semesterEnd = isset($validated['end_date']) ? Carbon::parse($validated['end_date']) : null;

        // Example: 2025 → "2025-2026"
        $yearStart = $semesterStart->year;
        $yearEnd = $yearStart + 1;
        $yearLabel = "{$yearStart}-{$yearEnd}";

        // Find or create academic year
        $academicYear = AcademicYear::firstOrCreate(
            ['year_label' => $yearLabel],
            ['dates' => ['start_year' => $yearStart, 'end_year' => $yearEnd]]
        );

        // Validate start + end year range
        if ($semesterStart->year < $yearStart || $semesterStart->year > $yearEnd) {
            return response()->json([
                'message' => 'Semester start_date is outside the academic year range.'
            ], 422);
        }

        if ($semesterEnd && ($semesterEnd->year < $yearStart || $semesterEnd->year > $yearEnd)) {
            return response()->json([
                'message' => 'Semester end_date is outside the academic year range.'
            ], 422);
        }

        // ---------------------- OVERLAP / DUPLICATE CHECK ----------------------
        $exists = Semester::where('program_id', $validated['program_id'])
            ->where('academic_year_id', $academicYear->id)
            ->where(function ($query) use ($validated, $semesterStart, $semesterEnd) {
                $query->where('semester_number', $validated['semester_number'])
                    ->orWhere(function ($q) use ($semesterStart, $semesterEnd) {
                        if ($semesterStart && $semesterEnd) {
                            $q->where(function ($sub) use ($semesterStart, $semesterEnd) {
                                $sub->where('start_date', '<=', $semesterEnd)
                                    ->where('end_date', '>=', $semesterStart);
                            });
                        }
                    });
                })
                ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'This semester overlaps with another semester or duplicate semester number.'
            ], 409);
        }

        $autoYear = intval(ceil($validated['semester_number'] / 2));

        // ---------------------- CREATE SEMESTER ----------------------
        $semester = Semester::create([
            'semester_key' => $validated['semester_key'],
            'semester_number' => $validated['semester_number'],
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            'program_id' => $validated['program_id'],
            'academic_year_id' => $academicYear->id,
            'year'              => $autoYear,
        ]);

        return response()->json([
            'message' => 'New semester program created successfully.',
            'semester' => $semester,
            'academic_year' => $academicYear,
        ], 201);
    }



    /*
    |--------------------------------------------------------------------------
    | UPDATE SEMESTER
    |--------------------------------------------------------------------------
    */

    public function updateSemesterProgram(Request $request, $semesterId)
    {
        $semester = Semester::findOrFail($semesterId);

        $validated = $request->validate([
            'semester_key' => 'nullable|string',
            'semester_number' => 'required|numeric',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'program_id' => 'required|exists:programs,id',
            'year' => 'nullable|string',
        ]);

        // ---------------------- DATE VALIDATION (MONDAY/SUNDAY) ----------------------
        if (isset($validated['start_date'])) {
            $start = Carbon::parse($validated['start_date']);
            if ($start->dayOfWeek !== Carbon::MONDAY) {
                return response()->json([
                    'message' => 'The semester start_date must be a Monday.'
                ], 422);
            }
        }

        if (isset($validated['end_date'])) {
            $end = Carbon::parse($validated['end_date']);
            if ($end->dayOfWeek !== Carbon::SUNDAY) {
                return response()->json([
                    'message' => 'The semester end_date must be a Sunday.'
                ], 422);
            }
        }

        $autoYear = intval(ceil($validated['semester_number'] / 2));

        // ---------------------- UPDATE SEMESTER ----------------------
        $semester->update([
            'semester_key' => $validated['semester_key'],
            'semester_number' => $validated['semester_number'],
            'start_date' => $validated['start_date'] ?? $semester->start_date,
            'end_date' => $validated['end_date'] ?? $semester->end_date,
            'program_id' => $validated['program_id'],
            'year'=> $autoYear,
        ]);

        return response()->json([
            'message' => 'Semester update successful.',
            'semester' => $semester
        ], 200);
    }



    /*
    |--------------------------------------------------------------------------
    | DELETE SEMESTER
    |--------------------------------------------------------------------------
    */

    public function deleteSemesterProgram($semesterId)
    {
        $semester = Semester::findOrFail($semesterId);
        $semester->delete();

        return response()->json([
            'message' => 'Semester deleted successfully.'
        ], 200);
    }



    /*
    |--------------------------------------------------------------------------
    | GET SEMESTERS BY PROGRAM
    |--------------------------------------------------------------------------
    */

    public function getAllSemestersByProgram($programId)
    {
        $semesters = Semester::where('program_id', $programId)->get();

        if ($semesters->isEmpty()) {
            return response()->json([
                'message' => 'No semesters found for this program.'
            ], 404);
        }

        return response()->json([
            'semesters' => $semesters
        ], 200);
    }



    /*
    |--------------------------------------------------------------------------
    | GET ALL SEMESTERS
    |--------------------------------------------------------------------------
    */

    public function getAllSemesters(Request $request)
    {
        $perPage = $request->query('per_page') ?? 14;

        $semesters = Semester::with([
            'program:id,program_name,degree_level',
            'academicYear'
        ])
            ->orderBy('program_id')
            ->orderBy('semester_number')
            ->paginate($perPage);

        if ($semesters->isEmpty()) {
            return response()->json([
                'message' => 'No semesters found.'
            ], 404);
        }

        return response()->json([
            'semesters' => $semesters
        ], 200);
    }



    /*
    |--------------------------------------------------------------------------
    | GET SEMESTERS BY ACADEMIC YEAR
    |--------------------------------------------------------------------------
    */

    public function getAllSemesterByAcademicYear(Request $request)
    {
        $academicYearId = $request->input('academic_year_id');

        if (!$academicYearId) {
            return response()->json([
                'message' => 'academic_year_id is required.'
            ], 422);
        }

        $semesters = Semester::with(['program', 'academicYear', 'subjects'])
            ->where('academic_year_id', $academicYearId)
            ->get();

        if ($semesters->isEmpty()) {
            return response()->json([
                'message' => 'No semesters found for this academic year.'
            ], 404);
        }

        return response()->json([
            'message' => 'Semesters retrieved successfully.',
            'semesters' => $semesters
        ], 200);
    }



    /*
    |--------------------------------------------------------------------------
    | GET GROUPS BY SEMESTER
    |--------------------------------------------------------------------------
    */

    public function getAllGroupBySemester(Request $request, $semesterId)
    {
        $groups = Group::with(['semester', 'semester.academicYear:id,year_label', 'students'])
            ->where('semester_id', $semesterId)
            ->get();

        if ($groups->isEmpty()) {
            return response()->json([
                'message' => 'No groups found for this semester.'
            ], 404);
        }

        return response()->json([
            'message' => 'Groups retrieved successfully.',
            'groups' => $groups
        ], 200);
    }



    /*
    |--------------------------------------------------------------------------
    | GET SUBJECTS BY SEMESTER
    |--------------------------------------------------------------------------
    */

    public function getSubjectsBySemester($semesterId)
    {
        $semester = Semester::with('subjects')->findOrFail($semesterId);

        if ($semester->subjects->isEmpty()) {
            return response()->json([
                'message' => 'No subjects found for this semester.'
            ], 404);
        }

        return response()->json([
            'message' => 'Subjects retrieved successfully.',
            'subjects' => $semester->subjects
        ], 200);
    }
}
