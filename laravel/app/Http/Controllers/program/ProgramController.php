<?php

namespace App\Http\Controllers\program;

use App\Http\Controllers\Controller;
// use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Group;
use App\Models\Program;
use App\Models\UserProgram;
use App\Models\Semester;
use App\Models\SubDepartment;
use App\Models\Subject;
// use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\AcademicYear;
use Carbon\Carbon;

class ProgramController extends Controller
{

    public function createNewProgram(Request $request)
    {

        $validated = $request->validate([
            'program_name' => 'nullable|string',
            'degree_level' => 'required|string',
            'duration_years' => 'required|numeric|min:1',
            'sub_department_id' => 'nullable|exists:sub_departments,id',
            'department_id' => 'nullable|exists:departments,id',
            'academic_year' => 'nullable|string'
        ]);

        $program = Program::create([
            'program_name' => $validated['program_name'] ?? null,
            'degree_level' => $validated['degree_level'],
            'duration_years' => $validated['duration_years'],
            'sub_department_id' => $validated['sub_department_id'] ?? null,
            'department_id' => $validated['department_id'] ?? null,
            'academic_year' => $validated['academic_year'] ?? null
            
        ]);

        return response()->json([
            'message' => 'Create New Program Successful',
            'program' => $program
        ]);

    }

    public function paginateProgram(Request $request){

        $perPage = $request->input('per_page', 14);

        $programs = Program::with('department')->paginate($perPage);

        if($programs->isEmpty()){
            return response()->json([
                'message'=> 'Program not available yet'
            ]);
        }

        return response()->json([
            'message' => 'Paginated list of programs',
            'programs' => $programs
        ]);

    }
    public function paginateSubjectOfProgram($program_id){

        $subjects = Subject::where('program_id',$program_id)->paginate(14);

        if($subjects->isEmpty()){
            return response()->json([
                'message'=> 'This program not available subject yet'
            ]);
        }

        return response()->json([
            'message' => 'Paginated list of programs',
            'programs' => $subjects
        ]);

    }

    public function searchPaginateProgram(Request $request)
    {
    $perPage = $request->query('per_page', 14);
    $search = $request->query('search');

    $programs = Program::query()
        ->when($search, function ($query, $search) {
            $query->where('program_name', 'like', "%{$search}%")
                  ->orWhere('degree_level', 'like', "%{$search}%");
        })
        ->orderBy('id', 'desc')
        ->paginate($perPage);

    if ($programs->isEmpty()){
        return response()->json([
            'message'=> "No result match with $search"
        ]);
    }

    return response()->json([
        'message' => "List of programs that match with $search",
        'programs' => $programs
    ]);
    }

    public function filterProgram(Request $request){
        $filter = $request->query('filter');

        $programs = Program::query()
        ->when($filter, function ($query, $search) {
            $query->Where('degree_level', 'like', "%{$search}%");
        })
        ->orderBy('id', 'desc')
        ->paginate(14);

        if($programs->isEmpty()){
            return response()->json([
                'message'=> "No result match with $filter"
            ]);

        }

        return response()->json([
        'message' => "List program with $filter ",
        'programs' => $programs
    ]);
    }


    public function updateProgram(Request $request, $program_id)
    {

        $program = Program::find($program_id);
        if (!$program) {
            return response()->json([
                'message' => 'Program not found.',
            ]);
        }

        $validated = $request->validate([
            'program_name' => 'string',
            'degree_level' => 'required|string',
            'department_id' => 'required|exists:departments,id',
            'duration_years' => 'required|numeric|min:1',
            'academic_year' => 'nullable|string'
        ]);

        $program->update([
            'program_name' => $validated['program_name'] ?? null,
            'degree_level' => $validated['degree_level'],
            'department_id' => $validated['department_id'],
            'duration_years' => $validated['duration_years'],
            'academic_year' => $validated['academic_year'] ?? $program->academic_year
        ]);

        return response()->json([
            'message' => 'Updated program Successful',
            'program' => $program
        ]);
    }

    public function removeProgram($program_id)
    {
        $program = Program::find($program_id);
        if (!$program) {
            return response()->json([
                'message' => 'Program not found.',
            ]);
        }

        $program->delete();

        return response()->json([
            'message' => 'Program is deleted successful.'
        ]);
    }

    public function getAllProgram()
    {

        $programs = Program::with('department')->get();

        if ($programs->isEmpty()) {
            return response()->json([
                'message' => 'Not available program.'
            ]);
        }

        return response()->json([
            'message' => 'List all program is succeed.',
            'programs' => $programs
        ]);
    }

    public function getProgramByDepartment(Request $request)
    {
        $departmentId = $request->query('department_id');

        $programs = Program::where('department_id', $departmentId)
            ->with([
                'department',
                'semesters.academicYear'
            ])
            ->get();

        if ($programs->isEmpty()) {
            return response()->json([
                'message' => 'Not available program.'
            ]);
        }
        $department = Department::find($departmentId);

        return response()->json([
            'message' => "List the program of $department->name .",
            'programs' => $programs
        ]);

    }

    public function getGroupsByProgram(Request $request, $program_id)
    {
        // $perPage = $request->query('per_page', 14);

        $user = $request->user();
        if (!$user) {
            return response()->json([
                'message' => 'Unauthorized.'
            ], 401);
        }
        if (!$user->hasRole('Head Department')) {
            return response()->json([
                'message' => 'You are not authorized to access this resource.'
            ], 403);
        }

        $department = Department::where('department_head_id', $user->id)->first();
        if (!$department) {
            return response()->json([
                'message' => 'You are not assigned as head of any department.'
            ], 403);
        }

        $program = Program::find($program_id);
        if (!$program) {
            return response()->json([
                'message' => 'Program not found.'
            ], 404);
        }

        $programDepartmentId = $program->department_id;
        if ($programDepartmentId && (int) $programDepartmentId !== (int) $department->id) {
            return response()->json([
                'message' => 'You are not authorized to access this program.'
            ], 403);
        }
        if (!$programDepartmentId && $program->sub_department_id) {
            $subDepartment = SubDepartment::find($program->sub_department_id);
            if (!$subDepartment || (int) $subDepartment->department_id !== (int) $department->id) {
                return response()->json([
                    'message' => 'You are not authorized to access this program.'
                ], 403);
            }
        }
        if (!$programDepartmentId && !$program->sub_department_id) {
            return response()->json([
                'message' => 'You are not authorized to access this program.'
            ], 403);
        }

        $groups = Group::with(['semester:id,semester_number'])
            ->where('program_id', $program_id)->get();
            // ->paginate($perPage);

        if ($groups->isEmpty()) {
            return response()->json([
                'message' => 'No groups found for this program.'
            ], 404);
        }

        return response()->json([
            'message' => 'Groups retrieved successfully.',
            'program' => $program,
            'groups' => $groups
        ], 200);
    }

    public function getSemestersByProgramForHeadDepartment(Request $request, $program_id)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'message' => 'Unauthorized.'
            ], 401);
        }
        if (!$user->hasRole('Head Department')) {
            return response()->json([
                'message' => 'You are not authorized to access this resource.'
            ], 403);
        }

        $department = Department::where('department_head_id', $user->id)->first();
        if (!$department) {
            return response()->json([
                'message' => 'You are not assigned as head of any department.'
            ], 403);
        }

        $program = Program::find($program_id);
        if (!$program) {
            return response()->json([
                'message' => 'Program not found.'
            ], 404);
        }

        $programDepartmentId = $program->department_id;
        if ($programDepartmentId && (int) $programDepartmentId !== (int) $department->id) {
            return response()->json([
                'message' => 'You are not authorized to access this program.'
            ], 403);
        }
        if (!$programDepartmentId && $program->sub_department_id) {
            $subDepartment = SubDepartment::find($program->sub_department_id);
            if (!$subDepartment || (int) $subDepartment->department_id !== (int) $department->id) {
                return response()->json([
                    'message' => 'You are not authorized to access this program.'
                ], 403);
            }
        }
        if (!$programDepartmentId && !$program->sub_department_id) {
            return response()->json([
                'message' => 'You are not authorized to access this program.'
            ], 403);
        }

        $semesters = Semester::with(['academicYear'])
            ->where('program_id', $program_id)
            ->orderBy('semester_number')
            ->get();

        if ($semesters->isEmpty()) {
            return response()->json([
                'message' => 'No semesters found for this program.'
            ], 404);
        }

        return response()->json([
            'message' => 'Semesters retrieved successfully.',
            'program' => $program,
            'semesters' => $semesters
        ], 200);
    }

    // public function createSemesterForProgram(Request $request)
    // {
    //     $validated = $request->validate([
    //         'program_id' => 'required|exists:programs,id',
    //         'semesters' => 'required|array|min:1',
    //         'semesters.*.academic_year_id' => 'required|exists:academic_years,id',
    //         'semesters.*.semester_number' => 'required|integer|in:1,2',
    //         'semesters.*.start_date' => 'required|date',
    //         'semesters.*.end_date' => 'required|date|after:semesters.*.start_date',
    //     ]);

    //     $program = Program::find($validated['program_id']);
    //     if (!$program) {
    //         return response()->json([
    //             'message' => 'Program not found.',
    //         ], 404);
    //     }

    //     $createdSemesters = [];
    //     $groupedByAcademicYear = collect($validated['semesters'])->groupBy('academic_year_id');

    //     // Validate each academic year has only semester 1 and 2
    //     foreach ($groupedByAcademicYear as $academicYearId => $semestersInYear) {
    //         $semesterNumbers = $semestersInYear->pluck('semester_number')->toArray();
            
    //         // Check for duplicate semester numbers in same academic year
    //         if (count($semesterNumbers) !== count(array_unique($semesterNumbers))) {
    //             return response()->json([
    //                 'message' => "Duplicate semester numbers found in academic year ID {$academicYearId}. Each academic year can only have one Semester 1 and one Semester 2.",
    //             ], 422);
    //         }

    //         // Check if semesters already exist for this program and academic year
    //         $existingSemesters = Semester::where('program_id', $program->id)
    //             ->where('academic_year_id', $academicYearId)
    //             ->count();

    //         if ($existingSemesters > 0) {
    //             $academicYear = AcademicYear::find($academicYearId);
    //             return response()->json([
    //                 'message' => "Semesters already exist for this program in academic year {$academicYear->year_label}.",
    //             ], 409);
    //         }
    //     }

    //     // Process each semester
    //     foreach ($validated['semesters'] as $index => $semesterData) {
    //         $academicYear = AcademicYear::find($semesterData['academic_year_id']);
            
    //         if (!$academicYear) {
    //             return response()->json([
    //                 'message' => "Academic year with ID {$semesterData['academic_year_id']} not found.",
    //             ], 404);
    //         }

    //         $semesterStart = Carbon::parse($semesterData['start_date']);
    //         $semesterEnd = Carbon::parse($semesterData['end_date']);

    //         // Validate dates are within academic year bounds
    //         $academicYearStart = $academicYear->dates['start_year'] ?? null;
    //         $academicYearEnd = $academicYear->dates['end_year'] ?? null;

    //         if ($academicYearStart && $academicYearEnd) {
    //             if ($semesterStart->year < $academicYearStart || $semesterEnd->year > $academicYearEnd) {
    //                 return response()->json([
    //                     'message' => "Semester {$semesterData['semester_number']} dates fall outside the academic year {$academicYear->year_label}.",
    //                 ], 422);
    //             }
    //         }

    //         // Check for overlapping with other semesters in the same academic year
    //         $otherSemestersInYear = array_filter($validated['semesters'], function($s) use ($semesterData, $index, $validated) {
    //             return $s['academic_year_id'] === $semesterData['academic_year_id'] 
    //                 && array_search($s, $validated['semesters']) !== $index;
    //         });

    //         foreach ($otherSemestersInYear as $otherSemester) {
    //             $otherStart = Carbon::parse($otherSemester['start_date']);
    //             $otherEnd = Carbon::parse($otherSemester['end_date']);

    //             if ($semesterStart->lessThanOrEqualTo($otherEnd) && $semesterEnd->greaterThanOrEqualTo($otherStart)) {
    //                 return response()->json([
    //                     'message' => "Semester {$semesterData['semester_number']} overlaps with Semester {$otherSemester['semester_number']} in academic year {$academicYear->year_label}.",
    //                 ], 422);
    //             }
    //         }

    //         // Create the semester
    //         $semester = $program->semesters()->create([
    //             'semester_number' => $semesterData['semester_number'],
    //             'semester_key' => "Semester {$semesterData['semester_number']}",
    //             'start_date' => $semesterStart->format('Y-m-d'),
    //             'end_date' => $semesterEnd->format('Y-m-d'),
    //             'academic_year_id' => $academicYear->id,
    //         ]);

    //         $createdSemesters[] = $semester;
    //     }

    //     return response()->json([
    //         'message' => "Successfully created " . count($createdSemesters) . " semester(s) for the program",
    //         'semesters' => $createdSemesters,
    //     ]);
    // }
       public function createSemesterForProgram(Request $request)
    {
        $validated = $request->validate([
            'program_id' => 'required|exists:programs,id',
            'semester_number' => 'required|numeric',
            'semester_key' => 'required|string',
            'start_date' => [
                'required',
                'date',
                function ($attr, $value, $fail) {
                    if (!Carbon::parse($value)->isMonday()) {
                        $fail('Start date must be a Monday.');
                    }
                },
            ],
            'end_date' => [
                'required',
                'date',
                'after_or_equal:start_date',
                function ($attr, $value, $fail) {
                    if (!Carbon::parse($value)->isSunday()) {
                        $fail('End date must be a Sunday.');
                    }
                },
            ],
        ]);

        $program = Program::find($validated['program_id']);
        if (!$program) {
            return response()->json([
                'message' => 'Program not found.',
            ], 404);
        }

        // Calculate academic year number based on semester_number
        // Assuming 2 semesters per year
        $year = ceil($validated['semester_number'] / 2);

        $semester = $program->semesters()->create([
            'semester_number' => $validated['semester_number'],
            'semester_key' => $validated['semester_key'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'year' => $year, // year 1, 2, 3...
        ]);

        return response()->json([
            'message' => 'Semester created successfully',
            'semester' => $semester,
        ]);
    }
    public function updateSemester(Request $request, $semester_id)
    {

        $semester = Semester::find($semester_id);

        if (!$semester) {
            return response()->json([
                'message' => 'Semester not found.'
            ]);
        }

        $validated = $request->validate([
            'semester_number' => 'required|numeric',
            'semester_key' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $semester->update([
            'semester_number' => $validated['semester_number'],
            'semester_key' => $validated['semester_key'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
        ]);

        return response()->json([
            'message' => 'Semester is updated successful.',
            'semester' => $semester
        ]);

    }

    public function getSubjectInProgram($program_id)
    {

        $subjects = Subject::where('program_id', $program_id)->get();
        $program = Program::find($program_id);

        if (!$program) {
            return response()->json([
                'message' => "This Program is not found",
            ]);
        }

        if ($subjects->isEmpty()) {
            return response()->json([
                'message' => "Not available subject in program",
            ]);
        }
        ;


        return response()->json([
            'message' => "List all Subject in program ($program->program_name)",
            'subjects' => $subjects
        ]);
    }

    public function removeSubjectFromProgram(Request $request, $program_id)
    {
        $validated = $request->validate([
            'subject_id' => 'required|exists:subjects,id',
        ]);

        $subject = Subject::where('id', $validated['subject_id'])
            ->where('program_id', $program_id)
            ->first();

        if (!$subject) {
            return response()->json([
                'message' => 'Subject not found for this program.',
            ], 404);
        }

        // Remove association by setting program_id to null
        $subject->program_id = null;
        $subject->save();

        return response()->json([
            'message' => 'Subject removed from program successfully.',
        ], 200);
    }


    public function addProgramToDepartment(Request $request, $department_id)
    {
        $validated = $request->validate([
            'program_id' => 'required|exists:programs,id',
        ]);

        $department = Department::find($department_id);

        $foundProgram = Program::find($validated['program_id']);

        if($foundProgram->department_id !== null){
            return response()->json([
                'message' => "This program is already exists in another department ."
            ],409);
        }

        if (!$department) {
            return response()->json([
                'message' => 'Department not found.',
            ], 404);
        }

        $program = Program::find($validated['program_id']);
        if (!$program) {
            return response()->json([
                'message' => 'Program not found.',
            ], 404);
        }

        $program->update([
            'department_id' => $department->id,
        ]);

        return response()->json([
            'message' => 'Program added to Department successfully.',
            'department' => $department,
            'program' => $program,
        ]);
    }

    public function removeProgramFromDepartment(Request $request, $department_id)
    {
        $department = Department::find($department_id);
        $program_id = $request->input('program_id');
        if (!$department) {
            return response()->json([
                'message' => 'Department not found.',
            ], 404);
        }

        $program = Program::find($program_id);
        if (!$program) {
            return response()->json([
                'message' => 'Program not found.',
            ], 404);
        }

        if ($program->department_id !== $department->id) {
            return response()->json([
                'message' => 'This program does not belong to this department.',
            ], 409);
        }

        // Remove program from department
        $program->update([
            'department_id' => null,
        ]);

        return response()->json([
            'message' => 'Program removed from department successfully.',
            'program' => $program,
        ], 200);
    }


    public function addSubjectToProgram(Request $request, $program_id)
    {
        $validated = $request->validate([
            'subject_id' => 'required|exists:subjects,id',
        ]);

        $program = Program::find($program_id);
        if (!$program) {
            return response()->json([
                'message' => 'Program not found.',
            ], 404);
        }

        // check if exist
        $existingSubject = Subject::where('id', $validated['subject_id'])
            ->where('program_id', $program_id)
            ->first();

        if ($existingSubject) {
            return response()->json([
                'message' => 'Subject is already associated with this program.',
            ], 400);
        }

        $subject = Subject::find($validated['subject_id']);
        if (!$subject) {
            return response()->json([
                'message' => 'Subject not found.',
            ], 404);
        }

        // Associate the subject with the program
        $subject->program_id = $program->id;
        $subject->save();

        return response()->json([
            'message' => 'Subject added to program successfully.',
            'program' => $program,
            'subject' => $subject,
        ]);
    }

    public function cloneSemester(Request $request)
    {
        $validated = $request->validate([
            'program_id' => 'required|exists:programs,id',
        ]);

        $program = Program::find($validated['program_id']);

        if (!$program) {
            return response()->json([
                'message' => 'Program not found.',
            ], 404);
        }

        // Get existing semesters
        $semesters = $program->semesters()->get();

        if ($semesters->isEmpty()) {
            return response()->json([
                'message' => 'This program has no semesters to clone.'
            ], 404);
        }

        // Auto-increase academic year (example: "2024-2025" -> "2025-2026")
        $oldYear = $program->academic_year;

        if ($oldYear && preg_match('/(\d{4})-(\d{4})/', $oldYear, $match)) {
            $start = intval($match[1]) + 1;
            $end = intval($match[2]) + 1;
            $newAcademicYear = "$start-$end";
        } else {
            // Fallback
            $newAcademicYear = now()->year . "-" . (now()->year + 1);
        }

        // Update program with new academic year
        $program->update([
            'academic_year' => $newAcademicYear
        ]);

        // Clone each semester
        foreach ($semesters as $sem) {
            $newSem = $sem->replicate();
            $newSem->program_id = $program->id;
            $newSem->start_date = Carbon::parse($sem->start_date)->addYear();
            $newSem->end_date = Carbon::parse($sem->end_date)->addYear();
            $newSem->save();
        }

        return response()->json([
            'message' => 'Semesters cloned successfully!',
            'new_academic_year' => $newAcademicYear
        ]);
    }

    public function cloneProgram(Request $request)
    {
        $validated = $request->validate([
            'program_id' => 'required|exists:programs,id',
            'academic_year' => 'nullable|string', // 👈 optional input
        ]);

        $program = Program::with(['semesters', 'subjects'])->find($validated['program_id']);

        if (!$program) {
            return response()->json([
                'message' => 'Program not found.',
            ], 404);
        }

        /**
         * 1️⃣ Determine Academic Year
         * Priority:
         * - request academic_year
         * - auto increase
         */
        if (!empty($validated['academic_year'])) {
            $newAcademicYear = $validated['academic_year'];
        } else {
            $newAcademicYear = $this->getNextAcademicYear($program->academic_year)
                ?? now()->year . '-' . (now()->year + 1);
        }

        /**
         * 2️⃣ Clone Program
         */
        $newProgram = $program->replicate();
        $newProgram->academic_year = $newAcademicYear;
        $newProgram->original_program_id = $program->id; // ⭐ reference
        $newProgram->created_at = now();
        $newProgram->updated_at = now();
        $newProgram->save();

        /**
         * 3️⃣ Clone Semesters
         */
        foreach ($program->semesters as $semester) {
            $newSemester = $semester->replicate();
            $newSemester->program_id = $newProgram->id;

            // auto shift dates
            $newSemester->start_date = Carbon::parse($semester->start_date)->addYear();
            $newSemester->end_date = Carbon::parse($semester->end_date)->addYear();

            $newSemester->save();
        }

        /**
         * 4️⃣ Clone Subjects
         */
        foreach ($program->subjects as $subject) {
            $newSubject = $subject->replicate();
            $newSubject->program_id = $newProgram->id;
            $newSubject->save();
        }

        return response()->json([
            'message' => 'Program cloned successfully',
            'original_program_id' => $program->id,
            'new_program_id' => $newProgram->id,
            'academic_year' => $newAcademicYear,
        ], 201);
    }



    private function getNextAcademicYear($year)
    {
        if (!$year || !str_contains($year, '-')) {
            return null; 
        }

        [$start, $end] = explode('-', $year);
        return ((int)$start + 1) . '-' . ((int)$end + 1);
    }


}
