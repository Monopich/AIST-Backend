<?php

namespace App\Http\Controllers\Exam;

use App\Http\Controllers\Controller;
use App\Imports\EntranceScoreImport;
use App\Models\ImportScore;
use App\Models\TempStudent;
use App\Models\TempStudentList;
use App\Models\User;
use App\Models\UserDetail;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class ImportScoreController extends Controller
{
    public function preview(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,csv'
        ]);

        $import = new EntranceScoreImport();
        Excel::import($import, $request->file('file'));

        // sort by score DESC (assume score is column index 2)
        $sorted = $import->rows->sortByDesc(fn($row) => $row[2])->values();

        return response()->json([
            'total' => $sorted->count(),
            'data' => $sorted,
        ]);
    }

    public function storeFile(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,csv',
            'academic_year' => 'required|string',
        ]);

        // Use provided academic year (format: 2025-2026)
        $academicYear = $request->academic_year;

        // Find existing record for the year
        $existing = ImportScore::where('academic_year', $academicYear)->first();

        // Store new file
        $path = $request->file('file')->store('exam_scores');

        if ($existing) {
            // Delete old file
            if ($existing->file && Storage::exists($existing->file)) {
                Storage::delete($existing->file);
            }

            // UPDATE (NOT INSERT)
            $existing->update([
                'file' => $path,
            ]);

            return response()->json([
                'message' => 'Score file replaced successfully',
                'data' => $existing,
            ]);
        }

        // Create ONLY if not exists
        $score = ImportScore::create([
            'file' => $path,
            'academic_year' => $academicYear,
        ]);

        return response()->json([
            'message' => 'Score file uploaded successfully',
            'data' => $score,
        ]);
    }

    public function download($id)
    {
        $score = ImportScore::findOrFail($id);

        if (!Storage::exists($score->file)) {
            return response()->json([
                'message' => 'File not found',
                'error' => 'The requested file does not exist on the server.'
            ], 404);
        }

        return Storage::download(
            $score->file,
            'exam_scores_' . $score->academic_year . '.xlsx'
        );
    }

    // public function finalize(Request $request)
    // {
    //     $data = $request->validate([
    //         'academic_year' => 'required|integer',
    //         'import_score_id' => 'required|exists:import_scores,id',
    //         'from' => 'required|integer|min:1',
    //         'to' => 'required|integer|min:1',
    //         'students' => 'required|array|min:1',
    //         'students.*.temp_student_id' => 'required|exists:temp_students,id',
    //         'students.*.score' => 'required|numeric',
    //     ]);

    //     DB::transaction(function () use ($data) {

    //         // 1️⃣ Clear old results for this year
    //         TempStudentList::where('academic_year', $data['academic_year'])->delete();

    //         // 2️⃣ Insert new results
    //         foreach ($data['students'] as $index => $student) {
    //             $rank = $index + 1;

    //             TempStudentList::create([
    //                 'temp_student_id' => $student['temp_student_id'],
    //                 'import_score_id' => $data['import_score_id'],
    //                 'academic_year' => $data['academic_year'],
    //                 'rank' => $rank,
    //                 'score' => $student['score'],
    //                 'enrollment_decision' =>
    //                     ($rank >= $data['from'] && $rank <= $data['to'])
    //                     ? 'enrolled'
    //                     : 'unenrolled',
    //             ]);
    //         }
    //     });

    //     return response()->json([
    //         'message' => 'Student list finalized successfully',
    //         'total' => count($data['students']),
    //         'enrolled' => max(0, $data['to'] - $data['from'] + 1),
    //     ]);
    // }

    public function finalize(Request $request)
    {
        // ✅ 1. Validate input
        $data = $request->validate([
            'academic_year' => 'required|integer',
            'import_score_id' => 'required|exists:import_scores,id',
            'from' => 'required|integer|min:1',
            'to' => 'required|integer|min:1',
            'students' => 'required|array|min:1',
            'students.*.temp_student_id' => 'required|exists:temp_students,id',
            'students.*.score' => 'required|numeric',
        ]);

        // ✅ 2. Validate range logic
        if ($data['from'] > $data['to']) {
            return response()->json([
                'message' => 'Invalid range: "from" must be less than or equal to "to".'
            ], 422);
        }

        DB::transaction(function () use ($data) {

            // ✅ 3. Clear old results for this academic year
            TempStudentList::where('academic_year', $data['academic_year'])->delete();

            // ✅ 4. Insert new finalized results
            foreach ($data['students'] as $index => $student) {
                $rank = $index + 1;

                TempStudentList::create([
                    'temp_student_id' => $student['temp_student_id'],
                    'import_score_id' => $data['import_score_id'],
                    'academic_year' => $data['academic_year'],
                    'rank' => $rank,
                    'score' => $student['score'],
                    'enrollment_decision' =>
                        ($rank >= $data['from'] && $rank <= $data['to'])
                        ? 'selected'
                        : 'not_selected',
                ]);
            }
        });

        // ✅ 5. Response
        return response()->json([
            'message' => 'Student list finalized successfully',
            'total_students' => count($data['students']),
            'selected_students' => max(0, $data['to'] - $data['from'] + 1),
        ]);
    }

    public function enroll(Request $request)
    {
        $data = $request->validate([
            'academic_year' => 'required|integer',
        ]);

        $enrolledCount = 0;
        $skippedCount = 0;

        DB::transaction(function () use ($data, &$enrolledCount, &$skippedCount) {

            // 1️⃣ Get ONLY selected students
            $selectedStudents = TempStudentList::where('academic_year', $data['academic_year'])
                ->where('enrollment_decision', 'selected')
                ->with('tempStudent')
                ->get();

            foreach ($selectedStudents as $item) {

                // 2️⃣ Check if user already exists
                $user = User::where('temp_student_id', $item->temp_student_id)->first();

                if ($user) {
                    // User already exists, skip
                    $skippedCount++;
                    continue;
                }

                // 3️⃣ Create new user
                $user = User::create([
                    'temp_student_id' => $item->temp_student_id,
                    'name' => $item->tempStudent->latin_name,
                    'email' => Str::uuid() . '@student.pending',
                    'password' => Hash::make(Str::random(12)),
                ]);

                // 4️⃣ Auto-assign student role
                try {
                    $user->assignRole('student');
                    $enrolledCount++;
                } catch (\Exception $e) {
                    // Role assignment failed, but user is created
                    $enrolledCount++;
                }
            }
        });

        return response()->json([
            'message' => 'Student enrollment completed',
            'enrolled' => $enrolledCount,
            'skipped' => $skippedCount,
            'total_selected' => $enrolledCount + $skippedCount,
        ]);
    }

    public function enrollOne($tempStudentId)
    {
        $tempStudent = TempStudent::findOrFail($tempStudentId);

        // 1️⃣ Check exam result (must be selected)
        $result = TempStudentList::where('temp_student_id', $tempStudentId)
            ->where('enrollment_decision', 'selected')
            ->latest()
            ->first();

        if (!$result) {
            return response()->json([
                'message' => 'This student is not selected for enrollment.'
            ], 422);
        }

        DB::beginTransaction();
        try {
            // 2️⃣ Prevent duplicate enrollment
            if ($tempStudent->user) {
                return response()->json([
                    'message' => 'Student already enrolled.'
                ], 409);
            }

            // 3️⃣ Generate ID card with 'e' prefix for external exam students
            $year = date('Y');
            $prefix = 'e' . $year;

            // Lock rows properly inside transaction
            $lastIdCard = UserDetail::withTrashed()
                ->where('id_card', 'like', $prefix . '%')
                ->orderBy('id_card', 'desc')
                ->lockForUpdate()
                ->value('id_card');

            $lastNumber = $lastIdCard ? (int) substr($lastIdCard, -4) : 0;

            do {
                $lastNumber++;
                $newNumber = str_pad($lastNumber, 4, '0', STR_PAD_LEFT);

                $idCard = $prefix . $newNumber;
                $email = $idCard . '@rtc-bb.camai.kh';
            } while (
                UserDetail::withTrashed()->where('id_card', $idCard)->exists() ||
                User::withTrashed()->where('email', $email)->exists()
            );

            // 4️⃣ Create official user
            $user = User::create([
                'temp_student_id' => $tempStudent->id,
                'name' => $tempStudent->latin_name,
                'email' => $email,
                'password' => $idCard, // Use ID card as password
            ]);

            // 5️⃣ Handle profile picture if exists
            $profilePath = null;
            if ($tempStudent->profile_picture && Storage::disk('public')->exists($tempStudent->profile_picture)) {
                // Copy from temp storage to permanent storage
                $extension = pathinfo($tempStudent->profile_picture, PATHINFO_EXTENSION);
                $filename = $idCard . '.' . $extension;

                Storage::disk('public')->copy(
                    $tempStudent->profile_picture,
                    'profile_pictures/' . $filename
                );

                $profilePath = 'profile_pictures/' . $filename;
            }

            // 6️⃣ Create comprehensive user detail
            UserDetail::create([
                'user_id' => $user->id,
                'id_card' => $idCard,
                'department_id' => $tempStudent->department_id,
                'sub_department_id' => $tempStudent->sub_department_id ?? null,
                'khmer_first_name' => $tempStudent->khmer_first_name ?? null,
                'khmer_last_name' => $tempStudent->khmer_last_name ?? null,
                'latin_name' => $tempStudent->latin_name,
                'khmer_name' => $tempStudent->khmer_name,
                'address' => $tempStudent->address ?? null,
                'date_of_birth' => $tempStudent->date_of_birth,
                'origin' => $tempStudent->origin ?? null,
                'profile_picture' => $profilePath,
                'gender' => $tempStudent->gender,
                'bio' => $tempStudent->bio ?? null,
                'phone_number' => $tempStudent->phone_number ?? null,
                'special' => $tempStudent->special ?? false,
                'high_school' => $tempStudent->high_school ?? null,
                'mcs_no' => $tempStudent->mcs_no ?? null,
                'can_id' => $tempStudent->can_id ?? null,
                'bac_grade' => $tempStudent->bac_grade ?? null,
                'bac_from' => $tempStudent->bac_from ?? null,
                'bac_program' => $tempStudent->bac_program ?? null,
                'degree' => $tempStudent->degree ?? null,
                'option' => $tempStudent->option ?? null,
                'history' => $tempStudent->history ?? null,
                'redoubles' => $tempStudent->redoubles ?? null,
                'scholarships' => $tempStudent->scholarships ?? null,
                'branch' => $tempStudent->branch ?? null,
                'file' => $tempStudent->file ?? null,
                'grade' => $tempStudent->grade ?? null,
                'is_radie' => $tempStudent->is_radie ?? false,
                'current_address' => $tempStudent->current_address ?? null,
                'father_name' => $tempStudent->father_name ?? null,
                'father_phone' => $tempStudent->father_phone ?? null,
                'mother_name' => $tempStudent->mother_name ?? null,
                'mother_phone' => $tempStudent->mother_phone ?? null,
                'guardian_name' => $tempStudent->guardian_name ?? null,
                'guardian_phone' => $tempStudent->guardian_phone ?? null,
                'place_of_birth' => $tempStudent->place_of_birth ?? null,
                'join_at' => $tempStudent->join_at ?? now(),
                'graduated_from' => $tempStudent->graduated_from ?? null,
                'graduated_at' => $tempStudent->graduated_at ?? null,
                'experience' => $tempStudent->experience ?? null,
            ]);

            // 7️⃣ Assign student role
            $role = \App\Models\Role::where('role_key', 'Student')->firstOrFail();
            $user->roles()->syncWithoutDetaching([$role->id]);

            // 8️⃣ Enroll in program if exists
            if ($tempStudent->program_id) {
                $program = \App\Models\Program::findOrFail($tempStudent->program_id);
                $startYear = $tempStudent->start_year ?? date('Y');

                $generation = \App\Models\Generation::where('program_id', $program->id)
                    ->orderByDesc('number_gen')
                    ->first();

                if (!$generation) {
                    $latestGen = \App\Models\Generation::where('program_id', $program->id)
                        ->orderByDesc('number_gen')
                        ->first();

                    $nextNumberGen = $latestGen ? $latestGen->number_gen + 1 : 1;
                    $endYear = $startYear + $program->duration_years;

                    $generation = \App\Models\Generation::create([
                        'program_id' => $program->id,
                        'start_year' => $startYear,
                        'end_year' => $endYear,
                        'number_gen' => $nextNumberGen,
                    ]);
                }

                $yearStart = $startYear;
                $yearEnd = $yearStart + 1;
                $yearLabel = "{$yearStart}-{$yearEnd}";

                // Find or create academic year
                $academicYear = \App\Models\AcademicYear::firstOrCreate(
                    ['year_label' => $yearLabel],
                    ['dates' => json_encode(['start_year' => $yearStart, 'end_year' => $yearEnd])]
                );

                // Link student to program
                \App\Models\UserProgram::createOrFirst([
                    'program_id' => $program->id,
                    'user_id' => $user->id,
                    'generation_id' => $generation->id,
                    'academic_year_id' => $academicYear->id,
                ]);
            }

            // 9️⃣ Update enrollment decision to 'enrolled'
            $result->update(['enrollment_decision' => 'enrolled']);

            // 🔟 Load relationships
            $user->load('userDetail', 'userDetail.department', 'userDetail.subDepartment', 'userPrograms', 'userPrograms.academicYear', 'userPrograms.generation', 'userPrograms.program', 'roles');

            DB::commit();

            return response()->json([
                'message' => 'Student enrolled successfully',
                'data' => $user
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Student enrollment failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function index()
    {
        return response()->json(
            ImportScore::orderBy('created_at', 'asc')->get()
        );
    }

    public function show($id)
    {
        $score = ImportScore::findOrFail($id);
        return response()->json($score);
    }

}
