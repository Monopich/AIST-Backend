<?php

namespace App\Http\Controllers\subject;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Subject;

use App\Models\User;
use Illuminate\Http\Request;

class SubjectController extends Controller
{
    public function createSubject(Request $request)
    {
        $validated = $request->validate([
            'subject_name' => 'required|string',
            // 'subject_code' => 'required|string',
            'credit' => 'required|numeric',
            'description' => 'nullable|string',
            'program_id' => 'required|exists:programs,id',

            'total_hours' => 'nullable|numeric',
            'practice_hours' => 'nullable|numeric',

            'user_id' => 'nullable|exists:users,id',
        ]);

        // check if user is teacher
        if (!empty($validated['user_id'])) {
            $user = User::find($validated['user_id']);

            if (!$user || !$user->hasRole('Staff')) {
                return response()->json([
                    'message' => 'User is not a teacher.',
                ], 422);
            }
        }
        // $department = Department::find($validated['department_id']);
        $program = Program::find($validated['program_id']);

        // Helper function to create abbreviation from words
        function abbreviate($text)
        {
            if (!$text)
                return 'XX';
            $words = explode(' ', $text);
            $abbr = '';
            foreach ($words as $word) {
                $abbr .= strtoupper(substr($word, 0, 1));
            }
            return $abbr;
        }

        // $departmentCode = abbreviate($department->department_name ?? 'DEP'); // "Information Technology" → "IT"
        $programCode = abbreviate($program->program_name ?? 'PRG');
        $subjectName = abbreviate($validated['subject_name'] ?? 'SUB');

        // Count existing subjects for department+program to generate increment
        $count = Subject::where('program_id', $validated['program_id'])
            ->count() + 1;

        // Final subject code
        $subjectCode = sprintf("%s-%s-%03d", $programCode, $subjectName, $count);


        $subject = Subject::create([
            'subject_name' => $validated['subject_name'],
            'subject_code' => $subjectCode,
            'department_id' => $program->department_id,
            'credit' => $validated['credit'],
            'description' => $validated['description'] ?? 'No description provided',
            'program_id' => $validated['program_id'],
            'total_hours' => $validated['total_hours'],
            'practice_hours' => $validated['practice_hours'],

        ]);


        // Attach teacher if provided
        if (!empty($validated['user_id'])) {
            $subject->teachers()->attach([$validated['user_id']]);
        }


        $subject->teachers;
        $subject->program;
        $subject->department;

        return response()->json([
            'message' => 'Subject created successfully.',
            'subject' => $subject,
        ], 201);
    }


    public function assignTeacherToSubject(Request $request, $subject_id)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $user = User::find($validated['user_id']);

        // Check if user has 'Staff' or 'Teacher' role
        if (!$user->hasRole('Staff') && !$user->hasRole('Teacher')) {
            return response()->json([
                'message' => 'User is not a teacher or staff.',
            ], 422);
        }

        // Check if subject exists
        $subject = Subject::find($subject_id);
        if (!$subject) {
            return response()->json([
                'message' => 'Subject not found.',
            ], 404);
        }


        if ($subject->teachers()->where('user_id', $user->id)->exists()) {
            return response()->json([
                'message' => 'User is already assigned to this subject.',
            ], 409);
        }


        $subject->teachers()->attach($user->id);

        return response()->json([
            'message' => 'Teacher assigned to subject successfully.',
            'subject' => $subject,
            'assigned_user' => $user,
        ], 200);
    }

    public function UnassignTeacherFromSubject(Request $request)
    {

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'subject_id' => 'required|exists:subjects,id',
        ]);

        $subject = Subject::findOrFail($validated['subject_id']);
        $user = User::findOrFail($validated['user_id']);

        if (!$subject) {
            return response()->json([
                'message' => "Subject not found !"
            ], 404);
        }
        if (!$user) {
            return response()->json([
                'message' => "User not found !"
            ], 404);
        }

        $checkExisting = $subject->teachers()->where('user_id', $validated['user_id'])->exists();

        if (!$checkExisting) {
            return response()->json([
                'message' => "Teacher doesn't teach this subject !"
            ], 422);
        }

        $subject->teachers()->detach($validated['user_id']);

        return response()->json([
            'message' => "Successful unassign subject from teacher",
            'subject' => $subject,
            'user' => $user
        ]);
    }

    public function getAllTeacherOfSubject(Request $request)
    {
        $subject_id = $request->query('subject_id');

        $subject = Subject::with('teachers')->find($subject_id);

        if (!$subject) {
            return response()->json([
                'message' => 'Subject not found.',
            ], 404);
        }



        return response()->json([
            'message' => "List of teachers for the subject '$subject->subject_name' ",
            'teachers' => $subject->teachers,
        ]);
    }

    public function getAllSubjectWithTeachers(Request $request)
    {
        $perPage = $request->query('per_page');

        if (!$perPage) {
            $perPage = 14;
        }

        $subjects = Subject::with([
            'teachers' => function ($query) {
                $query->select('name', 'email');
            }
        ])->paginate($perPage);

        if ($subjects->isEmpty()) {
            return response()->json([
                'message' => 'No available subject.',
            ], 404);
        }

        return response()->json([
            'message' => "List of teachers for the subject",
            'teachers' => $subjects,
        ]);
    }


    public function updateSubject(Request $request, $subject_id)
    {
        $validated = $request->validate([
            'subject_name' => 'required|string',
            // 'subject_code' => 'required|string',
            'credit' => 'required|numeric',
            'description' => 'nullable|string',
            'total_hours' => 'nullable|numeric',
            'practice_hours' => 'nullable|numeric',
            'user_id' => 'nullable|exists:users,id',
            'program_id' => 'nullable|exists:programs,id',
        ]);

        if (!$subject_id) {
            return response()->json([
                'message' => 'Please provide subject Id',
            ], 422);
        }

        // Find the subject
        $subject = Subject::find($subject_id);

        if (!$subject) {
            return response()->json([
                'message' => 'Subject not found.',
            ], 404);
        }

        // Check if user is teacher/staff
        $userId = $validated['user_id'] ?? null;
        if (!empty($userId)) {
            $user = User::find($userId);

            if (!$user || !$user->hasRole('Staff')) {
                return response()->json([
                    'message' => 'User is not a teacher.',
                ], 422);
            }
        }

        // Update department_id based on program_id if provided
        $programId = $validated['program_id'] ?? $subject->program_id;
        $program = Program::find($programId);
        $departmentId = $program ? $program->department_id : $subject->department_id;

        // Update subject fields
        $subject->update([
            'subject_name' => $validated['subject_name'],
            'credit' => $validated['credit'],
            'description' => $validated['description'] ?? 'No description provided',
            'total_hours' => $validated['total_hours'],
            'practice_hours' => $validated['practice_hours'],
            'program_id' => $programId,
            'department_id' => $departmentId,
        ]);

        // Attach teacher if valid and not already attached
        if (!empty($userId) && !$subject->teachers()->where('user_id', $userId)->exists()) {
            $subject->teachers()->attach($userId);
        }
        $subject->load('department');

        return response()->json([
            'message' => 'Subject updated successfully.',
            'subject' => $subject,
        ], 200);
    }

    public function getAllSubjects(Request $request)
    {

        $subjects = Subject::with('teachers')->paginate(14);

        if ($subjects->isEmpty()) {
            return response()->json([
                'message' => 'Not available subject yet.'
            ], 404);
        }

        return response()->json([
            'message' => "List all subject by paginate successful",
            'subjects' => $subjects
        ]);

    }

    public function searchSubject(Request $request)
    {
        $search = $request->query('search');

        $subjects = Subject::query()
            ->when($search, function ($query, $search) {
                $query->where('subject_name', 'like', "%{$search}%")
                    ->orWhere('subject_code', 'like', "%{$search}%")
                    ->orWhere('credit', 'like', "%{$search}%");
            })
            ->orderBy('id', 'desc')
            ->paginate(14);

        if ($subjects->isEmpty()) {
            return response()->json([
                'message' => "No result match with $search"
            ], 404);

        }

        return response()->json([
            'message' => "This result match with $search",
            'subjects' => $subjects
        ]);

    }


    public function removeSubject($subject_id)
    {

        $foundSubject = Subject::find($subject_id);

        if (!$subject_id) {
            return response()->json([
                'message' => 'Please provide subject Id'
            ]);
        }
        if (!$foundSubject) {
            return response()->json([
                'message' => 'Subject is not found'
            ]);
        }
        $foundSubject->delete();

        return response()->json([
            'message' => "Subject ,$foundSubject->subject_name deleted successful."
        ]);


    }

    public function addSubjectToSemester(Request $request)
    {
        $validated = $request->validate([
            'subject_ids' => 'required|array',
            'subject_ids.*' => 'exists:subjects,id',
            'semester_id' => 'required|exists:semesters,id',
        ]);

        $semester = Semester::findOrFail($validated['semester_id']);

        // Get already assigned subjects
        $existingSubjects = $semester->subjects()->pluck('subjects.id')->toArray();

        // Separate new subjects from existing
        $newSubjects = array_diff($validated['subject_ids'], $existingSubjects);

        // Attach only new subjects
        if (!empty($newSubjects)) {
            $semester->subjects()->syncWithoutDetaching($newSubjects);
        }

        return response()->json([
            'message' => 'Subjects processed successfully.',
            'already_exists' => $existingSubjects,
            'added' => $newSubjects,
            'semester' => $semester->load('subjects'), // eager load subjects
        ], 201);
    }


    public function removeSubjectsFromSemester(Request $request)
    {
        $validated = $request->validate([
            'subject_ids' => 'required|array',
            'subject_ids.*' => 'exists:subjects,id',
            'semester_id' => 'required|exists:semesters,id',
        ]);

        $semester = Semester::findOrFail($validated['semester_id']);

        if (!$semester) {
            return response()->json([
                'message' => "Semester not found ."
            ], 404);
        }

        $existingSubjects = $semester->subjects()->pluck('subjects.id')->toArray();

        // Subjects that actually exist in this semester
        $subjectsToRemove = array_intersect($validated['subject_ids'], $existingSubjects);

        if (empty($subjectsToRemove)) {
            return response()->json([
                'message' => 'None of the given subjects are assigned to this semester.',
            ], 404);
        }


        $semester->subjects()->detach($subjectsToRemove);


        return response()->json([
            'message' => 'Subjects removed successfully.',
            'removed' => $validated['subject_ids'],
            'semester' => $semester->load('subjects')
        ], 200);
    }

    public function getAllSubjectOfTeacher(Request $request)
    {
        $user = $request->user();

        // Filters
        $year = $request->input('year');
        $semesterNumber = $request->input('semester_number');

        $selectedSubjects = $user->subjects()
            ->where('user_id', $user->id)
            ->with([ 'semesters.academicYear'])
            ->when($year, function ($query) use ($year) {
                $query->whereHas('semesters.academicYear', function ($q) use ($year) {
                    // Match if year_label contains the year (e.g. "2025-2026")
                    $q->where('year_label', 'like', "%$year%");
                });
            })
            ->when($semesterNumber, function ($query) use ($semesterNumber) {
                $query->whereHas('semesters', function ($q) use ($semesterNumber) {
                    $q->where('semester_number', $semesterNumber);
                });
            })
            ->get();

        if ($selectedSubjects->isEmpty()) {
            return response()->json([
                'message' => "Teacher : $user->name , doesn't teach any subjects for the given filters!",
            ], 404);
        }

        return response()->json([
            'message' => "Get all Subjects of teacher : $user->name",
            'subject' => $selectedSubjects
        ]);
    }

    // Get subjects taught by a specific teacher
    public function getSubjectsByTeacher(Request $request, $teacherId)
    {
        $teacher = User::find($teacherId);

        if (!$teacher) {
            return response()->json([
                'message' => 'Teacher not found.',
            ], 404);
        }

        if (!$teacher->hasRole('Staff')) {
            return response()->json([
                'message' => 'User is not a teacher.',
            ], 422);
        }

        $subjects = $teacher->subjects()->select('subjects.id', 'subjects.subject_name', 'subjects.subject_code', 'subjects.credit')->get();

        return response()->json([
            'message' => "List of subjects taught by teacher: $teacher->name",
            'subjects' => $subjects,
        ], 200);
    }

}
