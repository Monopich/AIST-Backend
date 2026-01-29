<?php

namespace App\Http\Controllers\student;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\AttendanceTracking;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Exists;

class StudentController extends Controller
{
    public function listAllStudents(Request $request)
    {
        $query = User::whereHas('roles', function ($q) {
            $q->where('role_key', 'Student');
        })
            ->with([
                'userDetail',
                'groups' => function ($q) {
                    $q->select('groups.id', 'name');
                },
                'roles' => function ($q) {
                    $q->select('roles.id', 'name', 'role_key');
                }
            ]);

        // Apply filters dynamically
        if ($request->filled('gender')) {
            $query->whereHas('userDetail', function ($q) use ($request) {
                $q->where('gender', $request->gender);
            });
        }

        if ($request->filled('program_id')) {
            $query->whereHas('userDetail', function ($q) use ($request) {
                $q->where('program_id', $request->program_id);
            });
        }

        if ($request->filled('department_id')) {
            $query->whereHas('userDetail', function ($q) use ($request) {
                $q->where('department_id', $request->department_id);
            });
        }

        if ($request->filled('sub_department_id')) {
            $query->whereHas('userDetail', function ($q) use ($request) {
                $q->where('sub_department_id', $request->sub_department_id);
            });
        }

        $students = $query->get()->map(function ($user) {
            $flattened = $user->toArray();
            $userDetail = $flattened['user_detail'] ?? [];

            // Remove original user_detail
            unset($flattened['user_detail']);

            // Merge user_detail fields into top-level user object
            return array_merge($flattened, $userDetail);
        });

        if ($students->isEmpty()) {
            return response()->json([
                'message' => "Student not found."
            ], 404);
        }

        return response()->json([
            'message' => 'List all students successful.',
            'students' => $students
        ], 200);
    }


    public function paginateStudents(Request $request)
    {
        $perPage = $request->input('per_page', 14);
        $query = User::whereHas('roles', function ($q) {
            $q->where('role_key', 'Student');
        })
            ->with([
                'userDetail',
                'roles' => function ($q) {
                    $q->select('roles.id', 'name', 'role_key');
                }
            ]);

        // Apply filters dynamically
        if ($request->filled('gender')) {
            $query->whereHas('userDetail', function ($q) use ($request) {
                $q->where('gender', $request->gender);
            });
        }

        if ($request->filled('program_id')) {
            $query->whereHas('userDetail', function ($q) use ($request) {
                $q->where('program_id', $request->program_id);
            });
        }

        if ($request->filled('department_id')) {
            $query->whereHas('userDetail', function ($q) use ($request) {
                $q->where('department_id', $request->department_id);
            });
        }

        if ($request->filled('sub_department_id')) {
            $query->whereHas('userDetail', function ($q) use ($request) {
                $q->where('sub_department_id', $request->sub_department_id);
            });
        }

        // $students = $query->get()->map(function ($user) {
        //     $flattened = $user->toArray();
        //     $userDetail = $flattened['user_detail'] ?? [];

        //     // Remove original user_detail
        //     unset($flattened['user_detail']);

        //     // Merge user_detail fields into top-level user object
        //     return array_merge($flattened, $userDetail);
        // });
        $students = $query->paginate($perPage);

        // Flatten userDetail inside paginated collection
        $students->getCollection()->transform(function ($user) {
            $flattened = $user->toArray();
            $userDetail = $flattened['user_detail'] ?? [];
            unset($flattened['user_detail']);
            return array_merge($flattened, $userDetail);
        });

        if ($students->isEmpty()) {
            return response()->json([
                'message' => "Student not found."
            ], 404);
        }

        return response()->json([
            'message' => 'List all students successful.',
            'students' => $students
        ], 200);
    }


    public function getStudentById(Request $request, $id)
    {

        $student = User::whereHas('roles', function ($query) {
            $query->where('role_key', 'Student');
        })
            ->with([
                'userDetail.department:id,department_name',
                'userDetail.subDepartment:id,name',
                'roles' => function ($query) {
                    $query->select('roles.id', 'name', 'role_key');
                }
                // 'userDetail.subDepartments:id,name'
            ])->find($id);
        if (!$student) {
            return response()->json([
                'message' => 'Student not found !',

            ], 404);
        }

        $data = [
            'id' => $student->id,
            'name' => $student->name,
            'email' => $student->email,
            'deleted_at' => $student->deleted_at,
        ];

        if ($student->userDetail) {
            $data = array_merge($data, $student->userDetail->toArray());
        }
        $data['roles'] = $student->roles->pluck('role_key')->toArray();
        if (!$student) {
            return response()->json([
                'message' => 'Student not found'
            ], 404);
        }

        return response()->json([
            'message' => 'Student found successful.',
            'student' => $data
        ], 200);
    }

    public function searchUser(Request $request)
    {
        $searchTerm = $request->input('search_term');
        $departmentId = $request->input('department_id');
        $subDepartmentId = $request->input('sub_department_id');
        $perPage = $request->input('per_page', 14);

        $students = User::with(['userDetail', 'roles'])
            ->whereHas('roles', fn($q) => $q->where('role_key', 'Student'))
            ->whereHas('userDetail', function ($q) use ($departmentId, $subDepartmentId) {
                if ($departmentId) {
                    $q->where('department_id', $departmentId);
                }
                if ($subDepartmentId) {
                    $q->where('sub_department_id', $subDepartmentId);
                }
            })
            ->where(function ($query) use ($searchTerm, $departmentId, $subDepartmentId) {
                $query->where('name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('email', 'LIKE', "%{$searchTerm}%")
                    ->orWhereHas('userDetail', function ($q) use ($searchTerm, $departmentId, $subDepartmentId) {
                        $q->where('id_card', 'LIKE', "%{$searchTerm}%")
                            ->orWhere('khmer_name', 'LIKE', "%{$searchTerm}%")
                            ->orWhere('address', 'LIKE', "%{$searchTerm}%")
                            ->orWhere('origin', 'LIKE', "%{$searchTerm}%")
                            ->orWhere('latin_name', 'LIKE', "%{$searchTerm}%");
                    });
            })->paginate($perPage);

            // ->where(function ($query) use ($searchTerm) {
            //     $query->whereAll(['name','email'], 'LIKE', "%{$searchTerm}%")
            //         ->orWhereHas('userDetail', function ($query) use ($searchTerm) {
            //             $query->whereAll(['id_card','khmer_name', 'address','origin', 'latin_name'], 'LIKE', "%{$searchTerm}%");
            //         });

            // })
            // ->paginate(14)

        ;

        if ($students->isEmpty()) {
            return response()->json([
                'message' => 'No results found'
            ], 404);
        }

        return response()->json([
            'students' => $students
        ], 200);
    }

    public function filterByDepartment(Request $request, $departmentId)
    {
        $students = User::with([
            'userDetail',
            'roles' => function ($query) {
                $query->select('roles.id', 'name', 'role_key')->where('role_key', 'Student');
            }
        ])
            ->whereHas('userDetail', function ($query) use ($departmentId) {
                $query->where('department_id', $departmentId);
            })
            ->get();

        if (!$departmentId) {
            return response()->json([
                'message' => 'Department ID is required'
            ], 400);
        }

        $existDepartment = Department::where('id', $departmentId)->first();

        if (!$existDepartment) {
            return response()->json([
                'message' => 'Department is not found !'
            ]);
        } elseif ($students->isEmpty()) {
            return response()->json([
                'message' => 'No students found for department ID: ' . $departmentId
            ], 404);
        }

        return response()->json([
            'students' => $students
        ], 200);
    }


  public function getAcademicInformation(Request $request)
{
    $user = $request->user();
    $yearLabel = $request->query('year_label');
    $yearNumber = $request->query('year_number');

    // Eager load everything needed
    $user->load([
        'userPrograms.program.semesters.academicYear',
        'userPrograms.program.semesters.subjects',
        'userPrograms.program.semesters.studentScores.subject'
    ]);

    // Get all programs for the user
    $programs = $user->userPrograms->pluck('program')->filter();

    // Get all semesters for these programs
    $allSemesters = $programs->flatMap(fn($program) => $program->semesters);

    // Get academic years related to these semesters, unique and sorted
    $academicYears = $allSemesters->pluck('academicYear')
        ->unique('id')
        ->sortBy(fn($ay) => json_decode($ay->dates)->start_year)
        ->values();

    // Add year_number like Year 1, Year 2, ...
    $academicYearsWithNumber = $academicYears->map(fn($ay, $index) => tap($ay, fn($ay) => $ay->year_number = $index + 1));

    // Determine current academic year
    $today = now();
    $currentAcademicYear = $academicYearsWithNumber->first(fn($ay) => $today->year >= json_decode($ay->dates)->start_year && $today->year <= json_decode($ay->dates)->end_year);
    $currentYearNumber = $currentAcademicYear ? $currentAcademicYear->year_number : null;

    // Filter semesters based on query parameters
    $semesters = $allSemesters->filter(function ($semester) use ($yearLabel, $yearNumber) {
        $academicYear = $semester->academicYear;
        if (!$academicYear) return false;
        if ($yearLabel && $academicYear->year_label !== $yearLabel) return false;
        if ($yearNumber && ($academicYear->year_number ?? null) != $yearNumber) return false;
        return true;
    })->values();

    // Map semesters with subjects, scores, and attendances
    $semesters = $semesters->map(function ($semester) use ($user) {
        // Get all subjects in the semester
        $subjects = $semester->subjects;

        // Get student scores for this semester
        $scores = $semester->studentScores->keyBy('subject_id');

        // Group attendances by subject_id
        $attendances = AttendanceTracking::with(['timeSlot.subject', 'timeSlot.timeTable.group'])
            ->where('user_id', $user->id)
            ->whereHas('timeSlot.timeTable.group', fn($q) => $q->where('semester_id', $semester->id))
            ->get()
            ->groupBy('timeSlot.subject_id');

        // Merge subjects with scores and attendance
        $semesterSubjects = $subjects->map(function ($subject) use ($scores, $attendances) {
            $score = $scores->get($subject->id);

            $subjectAttendances = $attendances->get($subject->id, collect());

            $attendanceCounts = [
                'present' => $subjectAttendances->where('status', 'Present')->count(),
                'late' => $subjectAttendances->where('status', 'Late')->count(),
                'absent' => $subjectAttendances->where('status', 'Absent')->count(),
                'no_class' => $subjectAttendances->where('status',"No Class")->count(),
                'on_leave' => $subjectAttendances->where('status', 'On Leave')->count(),
                'total' => $subjectAttendances->count()
            ];

            return [
                'id' => $subject->id,
                'subject_name' => $subject->subject_name,
                'subject_code' => $subject->subject_code,
                'description' => $subject->description,
                'credit' => $subject->credit,
                'total_hours' => $subject->total_hours,
                'practice_hours' => $subject->practice_hours,
                'scores' => $score->scores ?? null,
                'attendance_score' => $score->attendance_score ?? null,
                'exam_score' => $score->exam_score ?? null,
                'student_id' => $score->student_id ?? null,
                'attendances' => $subjectAttendances->map(fn($att) => [
                    'id' => $att->id,
                    'attendance_date' => $att->attendance_date,
                    'status' => $att->status,
                    'subject_id' => $att->timeSlot->subject_id,
                ])->values(),
                'attendance_count' => $attendanceCounts
            ];
        });

        return [
            'id' => $semester->id,
            'academic_year_id' => $semester->academic_year_id,
            'semester_number' => $semester->semester_number,
            'semester_key' => $semester->semester_key,
            'start_date' => $semester->start_date,
            'end_date' => $semester->end_date,
            'program_id' => $semester->program_id,
            'subjects' => $semesterSubjects,
            'academic_year' => $semester->academicYear,
        ];
    });

    return response()->json([
        'message' => 'Student academic information retrieved successfully.',
        'current_year_number' => $currentYearNumber,
        'semesters' => $semesters,
    ], 200);
}


}
