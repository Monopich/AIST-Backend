<?php

namespace App\Http\Controllers\program;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Generation;
use App\Models\Program;
use App\Models\User;
use App\Models\UserProgram;
use App\Models\Department;
use App\Models\Group;
// use App\Models\Department;
// use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// use Illuminate\Support\Facades\DB;


class UserProgramController extends Controller
{
    /**
     * 🔹 Add / Promote student to program with year
     */
    /**
     * 🔹 Add / Promote student to program with year
     */
    public function addStudentToProgram(Request $request)
    {
        $validated = $request->validate([
            'user_id'       => 'required|exists:users,id',
            'program_id'    => 'required|exists:programs,id',
            'generation_id' => 'nullable|exists:generations,id',
        ]);

        $user = User::find($validated['user_id']);
        if (!$user || !$user->hasRole('Student')) {
            return response()->json(['message' => 'User is not a student.'], 422);
        }

        $program = Program::findOrFail($validated['program_id']);

        /**
         * 🔹 Resolve generation
         */
        $generationId = $validated['generation_id']
            ?? Generation::where('program_id', $program->id)
                ->orderByDesc('number_gen')
                ->value('id');
        if (!$generationId) {
            $generationId = Generation::generateNewForProgram($program->id)->id;
        }

        /**
         * 🔴 BLOCK duplicate academic year promotion
         */
        $alreadyPromotedThisYear = UserProgram::where('user_id', $user->id)
            ->where('program_id', $program->id)
            ->whereHas('program', function ($q) use ($program) {
                $q->where('academic_year', $program->academic_year);
            })
            ->exists();

        if ($alreadyPromotedThisYear) {
            return response()->json([
                'message' => "Student already promoted in academic year {$program->academic_year}"
            ], 409);
        }

        /**
         * ✅ Auto-increment year
         */
        $lastYear = UserProgram::where('user_id', $user->id)->max('year');
        $year = $lastYear ? $lastYear + 1 : 1;

        /**
         * ✅ Create enrollment
         */
        $enrollment = UserProgram::create([
            'user_id'       => $user->id,
            'program_id'    => $program->id,
            'generation_id' => $generationId,
            'year'          => $year,
        ]);

        return response()->json([
            'message' => 'Student enrolled successfully.',
            'data'    => $enrollment,
            'message' => 'Student enrolled successfully.',
            'data'    => $enrollment
        ], 201);
    }

    /**
     * 🔹 Remove student from all programs
     */
    public function removeStudent($userId)
    {
        $deleted = UserProgram::where('user_id', $userId)->delete();

        if (!$deleted) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        return response()->json(['message' => 'Student removed from programs successfully.']);
    }

    /**
     * 🔹 List students by generation, program, year
     */
    public function listAllStudentByGeneration(Request $request)
    {
        $generationId = $request->query('generation');
        $programId    = $request->query('program');
        $year         = $request->query('year');
        $perPage      = $request->query('per_page', 14);


        $students = User::whereHas('roles', fn ($q) =>
                $q->where('role_key', 'Student')
            )
            ->whereHas('userDetail.userPrograms', function ($q) use ($generationId, $programId, $year) {
                if ($generationId) $q->where('generation_id', $generationId);
                if ($programId) $q->where('program_id', $programId);
                if ($year) $q->where('year', $year);
            })
            ->with([
                'userDetail.userPrograms.program',
                'userDetail.userPrograms.generation'
            ])
            ->paginate($perPage);

        if ($students->isEmpty()) {
            return response()->json(['message' => 'Student not available.'], 404);
        }

        $students->getCollection()->transform(fn ($student) => [
            'id'         => $student->id,
            'name'       => $student->name,
            'email'      => $student->email,
            'program'    => optional($student->userDetail->userPrograms->first()?->program)->program_name,
            'generation' => optional($student->userDetail->userPrograms->first()?->generation)->number_gen,
            'year'       => $student->userDetail->userPrograms->first()?->year,
        ]);

        return response()->json([
            'message'  => 'Students retrieved successfully.',
            'students' => $students
        ]);
    }

    /**
     * 🔹 Get all programs of a user (with year)
     */
   
    public function getUserPrograms($userId)
    {
        $userPrograms = UserProgram::with([
                'program:id,program_name,degree_level',
                'generation:id,number_gen'
            ])
            ->where('user_id', $userId)
            ->orderBy('year')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $userPrograms
        ]);
    }

    /**
     * 🔹 Get all enrolled students
     */
    public function getAllEnrolledStudents()
    {
        // ✅ First, ensure all null years are set to 1 in DB
        UserProgram::whereNull('year')->update(['year' => 1]);

        // Load enrollments with related models
        $enrollments = UserProgram::with([
            'userDetail:id,user_id,latin_name,khmer_name',
            'user.groups:id,name,semester_id,program_id,sub_department_id',
            'user.groups.semester:id,semester_number',
            'user.groups.subDepartment:id,name',
            'program:id,program_name,academic_year,degree_level,department_id,sub_department_id',
            'program.department:id,department_name', // load department inside program
            'generation:id,number_gen'
        ])
        ->join('programs', 'user_programs.program_id', '=', 'programs.id') // join programs
        ->orderBy('programs.academic_year') // order by academic_year
        ->select('user_programs.*') // avoid selecting all program columns twice
        ->get();

        // Attach all user groups to each UserProgram row
        $enrollments->each(function ($enrollment) {
            // Now year is always non-null
            $enrollment->group = $enrollment->user ? $enrollment->user->groups : [];
        });

        return response()->json([
            'message' => 'All enrolled students retrieved successfully',
            'data'    => $enrollments
        ]);
    }




    /**
     * 🔹 Promote multiple students at once
     */
    public function promoteMultipleStudents(Request $request)
    {
        $validated = $request->validate([
            'user_ids'      => 'required|array|min:1',
            'user_ids.*'    => 'exists:users,id',
            'program_id'    => 'required|exists:programs,id',
            'generation_id' => 'nullable|exists:generations,id',
        ]);

        $program = Program::findOrFail($validated['program_id']);
        $academicYear = $program->academic_year;
        $programDuration = $program->duration_years ;
        
        $generationId = $validated['generation_id']
            ?? Generation::where('program_id', $program->id)
                ->orderByDesc('number_gen')
                ->value('id');

        if (!$generationId) {
            $generation = Generation::generateNewForProgram($program->id);
            $generationId = $generation->id;
        }

        $created = [];
        $skipped = [];

        DB::beginTransaction();

        try {
            foreach ($validated['user_ids'] as $userId) {
                $user = User::find($userId);

                if (!$user || !$user->hasRole('Student')) {
                    $skipped[] = ['user_id' => $userId, 'reason' => 'Not a student'];
                    continue;
                }

                // 🔹 Get last year for this student in this program
                $lastYear = UserProgram::where('user_id', $user->id)->max('year');
                $nextYear = $lastYear ? $lastYear + 1 : 1;

                    if ($nextYear > $programDuration) {
                    $skipped[] = [
                        'user_id' => $userId,
                        'reason'  => "Cannot promote beyond program duration ({$programDuration} year(s))"
                    ];
                    continue;
                }
                
                // 🔹 Prevent duplicate program in the same academic year
                $exists = UserProgram::where('user_id', $userId)
                    ->where('program_id', $program->id)
                    ->whereHas('program', function ($q) use ($academicYear) {
                        $q->where('academic_year', $academicYear);
                    })
                    ->exists();


                if ($exists) {
                    $skipped[] = [
                        'user_id' => $userId,
                        'reason'  => "Already promoted to year {$nextYear}"
                    ];
                    continue;
                }

                // 🔹 Create new enrollment
                $created[] = UserProgram::create([
                    'user_id'       => $userId,
                    'program_id'    => $program->id,
                    'generation_id' => $generationId,
                    'year'          => $nextYear,
                ]);
            }

        DB::commit();

            return response()->json([
                'message'       => 'Promotion completed',
                'created_count' => count($created),
                'skipped_count' => count($skipped),
                'created'       => $created,
                'skipped'       => $skipped,
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Promotion failed',
                'error'   => $e->getMessage()
            ], 500);
        }
    }


    public function getAllPrograms()
    {

        $programs = Program::all(['id', 'program_name', 'degree_level', 'academic_year']);
        return response()->json(['data' => $programs]);

    }

    /**
     * 🔹 Get all departments
     */
    public function getAllDepartments()
    {
        $departments = Department::all(['id', 'department_name']);
        return response()->json(['data' => $departments]);
    }

    /**
     * 🔹 Get all groups
     */
    public function getAllGroups()
    {
        $groups = Group::all(['id', 'name', 'program_id']);
        return response()->json(['data' => $groups]);
    }

    /**
     * 🔹 Get all degree levels
     */
    public function getAllDegrees()
    {
        $degrees = Program::select('degree_level')->distinct()->get()->pluck('degree_level');
        return response()->json(['data' => $degrees]);
    }

}
