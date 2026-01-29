<?php

namespace App\Http\Controllers\subject;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\UserProgram;
use App\Models\Subject;
use App\Models\Score;

class ScoreController extends Controller
{
    public function importMoodleScores(Request $request)
    {
        $request->validate([
            'subject_id' => 'required|exists:subjects,id',
            'file' => 'required|file|mimes:csv,txt',
        ]);

        $subjectId = $request->subject_id;
        $file = $request->file('file');

        $csvData = array_map('str_getcsv', file($file->getRealPath()));
        $header = $csvData[0];
        $rows = array_slice($csvData, 1);

        $finalIndex = array_search('Final total', $header);

        if ($finalIndex === false) {
            return response()->json(['error' => 'Final total column not found'], 400);
        }

        $imported = 0;

        foreach ($rows as $row) {
            $studentId = $row[array_search('ID number', $header)]; // e.g., e20250001
            $finalScore = $row[$finalIndex];

            // Find user_program_id by matching email local-part
            $userProgram = UserProgram::whereHas('user', function ($q) use ($studentId) {
                $q->where('email', 'like', $studentId . '%'); 
                // matches e20250001@rtc-bb.camai.kh
            })->first();

            if ($userProgram && is_numeric($finalScore)) {
                Score::updateOrCreate(
                    [
                        'user_program_id' => $userProgram->id,
                        'subject_id' => $subjectId
                    ],
                    ['score' => $finalScore]
                );
                $imported++;
            }
        }

        return response()->json([
            'message' => "Scores imported successfully",
            'imported' => $imported
        ]);
    }

    public function getScoresBySubject($subjectId)
    {
        $subject = Subject::with(['program'])->findOrFail($subjectId);

        $scores = Score::with(['userProgram.user', 'userProgram.userDetail'])
            ->where('subject_id', $subjectId)
            ->get()
            ->map(function ($score) {
                return [
                    'user_program_id' => $score->user_program_id,
                    'name' => $score->userProgram->userDetail->latin_name ?? $score->userProgram->user->name,
                    'email' => $score->userProgram->user->email,
                    'score' => $score->score
                ];
            });

        return response()->json([
            'subject' => $subject->subject_name,
            'scores' => $scores
        ]);
    }

    public function getScoresByUserProgram($userProgramId)
    {
        $userProgram = UserProgram::with(['user', 'userDetail', 'program'])->findOrFail($userProgramId);

        $scores = Score::with('subject')
            ->where('user_program_id', $userProgramId)
            ->get()
            ->map(function ($score) {
                return [
                    'subject' => $score->subject->subject_name,
                    'score' => $score->score
                ];
            });

        return response()->json([
            'user_program_id' => $userProgram->id,
            'student_name' => $userProgram->userDetail->latin_name ?? $userProgram->user->name,
            'program' => $userProgram->program->program_name,
            'scores' => $scores
        ]);
    }

    public function getAllSubjectScoresByUserProgram($userProgramId)
    {
        $userProgram = UserProgram::with([
            'user',
            'userDetail',
            'program.subjects'
        ])->findOrFail($userProgramId);

        // Get all scores for this student
        $scores = Score::where('user_program_id', $userProgramId)
            ->get()
            ->keyBy('subject_id');

        // Map all subjects with score (or null)
        $subjects = $userProgram->program->subjects->map(function ($subject) use ($scores) {
            return [
                'subject_id' => $subject->id,
                'subject_name' => $subject->subject_name,
                'score' => $scores[$subject->id]->score ?? null,
            ];
        });

        return response()->json([
            'user_program_id' => $userProgram->id,
            'student_name' => $userProgram->userDetail->latin_name ?? $userProgram->user->name,
            'email' => $userProgram->user->email,
            'program' => $userProgram->program->program_name,
            'subjects' => $subjects,
        ]);
    }

    public function getUserAcademicHistory($userId)
    {
        $userPrograms = UserProgram::with([
            'user',
            'userDetail',
            'program.department',
            'program.subjects',
            'scores.subject',
            'generation'
        ])
        ->where('user_id', $userId)
        ->orderBy('year', 'asc')
        ->get();

        if ($userPrograms->isEmpty()) {
            return response()->json([
                'message' => 'No academic history found'
            ], 404);
        }

        $user = $userPrograms->first()->user;
        $userDetail = $userPrograms->first()->userDetail;

        $academicHistory = $userPrograms->map(function ($userProgram) {

            $scoreMap = $userProgram->scores->keyBy('subject_id');

            $totalCredits = 0;
            $totalWeightedPoint = 0;

            $subjects = $userProgram->program->subjects->map(function ($subject) use (
                $scoreMap,
                &$totalCredits,
                &$totalWeightedPoint
            ) {
                $rawScore = $scoreMap[$subject->id]->score ?? null;
                $score = is_numeric($rawScore) ? (float) $rawScore : 0;

                $gradeInfo = $this->getGradeFromScore($score);

                // GPA calculation based on grade point
                if ($gradeInfo['point'] !== null) {
                    $totalCredits += $subject->credit;
                    $totalWeightedPoint += $gradeInfo['point'] * $subject->credit;
                }

                return [
                    'subject_id'   => $subject->id,
                    'subject_name' => $subject->subject_name,
                    'subject_code' => $subject->subject_code,
                    'credit'       => $subject->credit,
                    'score'        => $score,
                    'grade'        => $gradeInfo['grade'],
                    'grade_point'  => $gradeInfo['point'],
                    'remark'       => $gradeInfo['remark'],
                ];
            });

            $gpa = $totalCredits > 0
                ? round($totalWeightedPoint / $totalCredits, 2)
                : null;

            return [
                'user_program_id' => $userProgram->id,

                // Academic structure
                'year'          => $userProgram->year,
                'generation'    => $userProgram->generation?->number_gen,
                'academic_year' => $userProgram->program->academic_year,

                // Program info
                'program' => [
                    'id'           => $userProgram->program->id,
                    'name'         => $userProgram->program->program_name,
                    'degree_level' => $userProgram->program->degree_level,
                    'department'   => $userProgram->program->department->department_name ?? null,
                ],

                // Certificate-ready data
                'total_credits' => $totalCredits,
                'gpa'           => $gpa,

                // Subjects
                'subjects'      => $subjects,

                'created_at'    => $userProgram->created_at,
            ];
        });

        return response()->json([
            'user_id'      => $user->id,
            'student_name' => $userDetail->latin_name ?? $user->name,
            'khmer_name'   => $userDetail->khmer_name ?? null,
            'email'        => $user->email,
            'academic_history' => $academicHistory,
        ]);
    }

    private function getGradeFromScore($score)
    {
        if ($score === null) {
            return [
                'grade' => null,
                'point' => null,
                'remark' => 'Not Graded'
            ];
        }

        if ($score >= 85) return ['grade' => 'A',  'point' => 4.0, 'remark' => 'Excellent'];
        if ($score >= 80) return ['grade' => 'B+', 'point' => 3.5, 'remark' => 'Very Good'];
        if ($score >= 70) return ['grade' => 'B',  'point' => 3.0, 'remark' => 'Good'];
        if ($score >= 65) return ['grade' => 'C+', 'point' => 2.5, 'remark' => 'Fairly Good'];
        if ($score >= 50) return ['grade' => 'C',  'point' => 2.0, 'remark' => 'Fair'];
        if ($score >= 45) return ['grade' => 'D',  'point' => 1.5, 'remark' => 'Poor'];
        if ($score >= 40) return ['grade' => 'E',  'point' => 1.0, 'remark' => 'Very Poor'];

        return ['grade' => 'F', 'point' => 0.0, 'remark' => 'Failure'];
    }

    public function generateCertificateByYear($userId, $year)
    {
        // Get the user's program for the given year
        $userProgram = UserProgram::with([
            'user',
            'userDetail',
            'program.department',
            'program.subjects',
            'scores.subject',
            'generation'
        ])
        ->where('user_id', $userId)
        ->where('year', $year)
        ->first();

        if (!$userProgram) {
            return response()->json([
                'message' => 'No academic record found for this year'
            ], 404);
        }

        $scoreMap = $userProgram->scores->keyBy('subject_id');

        $totalCredits = 0;
        $totalWeightedPoint = 0;

        $subjects = $userProgram->program->subjects->map(function ($subject) use ($scoreMap, &$totalCredits, &$totalWeightedPoint) {
            $rawScore = $scoreMap[$subject->id]->score ?? null;
            $score = is_numeric($rawScore) ? (float) $rawScore : 0;

            $gradeInfo = $this->getGradeFromScore($score);

            // Calculate GPA using grade point
            if ($gradeInfo['point'] !== null) {
                $totalCredits += $subject->credit;
                $totalWeightedPoint += $gradeInfo['point'] * $subject->credit;
            }

            return [
                'subject_id'   => $subject->id,
                'subject_name' => $subject->subject_name,
                'subject_code' => $subject->subject_code,
                'credit'       => $subject->credit,
                'score'        => $score,
                'grade'        => $gradeInfo['grade'],
                'grade_point'  => $gradeInfo['point'],
                'remark'       => $gradeInfo['remark'],
            ];
        });

        $gpa = $totalCredits > 0 ? round($totalWeightedPoint / $totalCredits, 2) : null;

        // Return certificate-ready structure
        return response()->json([
            'user_id'      => $userProgram->user->id,
            'student_name' => $userProgram->userDetail->latin_name ?? $userProgram->user->name,
            'khmer_name'   => $userProgram->userDetail->khmer_name ?? null,
            'email'        => $userProgram->user->email,
            'program'      => [
                'id'           => $userProgram->program->id,
                'name'         => $userProgram->program->program_name,
                'degree_level' => $userProgram->program->degree_level,
                'department'   => $userProgram->program->department->department_name ?? null,
                'academic_year'=> $userProgram->program->academic_year,
                'generation'   => $userProgram->generation?->number_gen,
            ],
            'year'          => $userProgram->year,
            'total_credits' => $totalCredits,
            'gpa'           => $gpa,
            'subjects'      => $subjects,
            'issued_at'     => now()->format('d-m-Y'),
        ]);
    }

    public function getStudentAcademicHistory(Request $request)
    {
        $userPrograms = UserProgram::with([
            'user',
            'userDetail',
            'program.department',
            'program.subjects',
            'scores.subject',
            'generation'
        ])
        ->where('user_id', $request
        ->user()->id)
        ->orderBy('year', 'asc')
        ->get();

        if ($userPrograms->isEmpty()) {
            return response()->json([
                'message' => 'No academic history found'
            ], 404);
        }

        $user = $userPrograms->first()->user;
        $userDetail = $userPrograms->first()->userDetail;

        $academicHistory = $userPrograms->map(function ($userProgram) {

            $scoreMap = $userProgram->scores->keyBy('subject_id');

            $totalCredits = 0;
            $totalWeightedPoint = 0;

            $subjects = $userProgram->program->subjects->map(function ($subject) use (
                $scoreMap,
                &$totalCredits,
                &$totalWeightedPoint
            ) {
                $rawScore = $scoreMap[$subject->id]->score ?? null;
                $score = is_numeric($rawScore) ? (float) $rawScore : 0;

                $gradeInfo = $this->getGradeFromScore($score);

                // GPA calculation based on grade point
                if ($gradeInfo['point'] !== null) {
                    $totalCredits += $subject->credit;
                    $totalWeightedPoint += $gradeInfo['point'] * $subject->credit;
                }

                return [
                    'subject_id'   => $subject->id,
                    'subject_name' => $subject->subject_name,
                    'subject_code' => $subject->subject_code,
                    'credit'       => $subject->credit,
                    'score'        => $score,
                    'grade'        => $gradeInfo['grade'],
                    'grade_point'  => $gradeInfo['point'],
                    'remark'       => $gradeInfo['remark'],
                ];
            });

            $gpa = $totalCredits > 0
                ? round($totalWeightedPoint / $totalCredits, 2)
                : null;

            return [
                'user_program_id' => $userProgram->id,

                // Academic structure
                'year'          => $userProgram->year,
                'generation'    => $userProgram->generation?->number_gen,
                'academic_year' => $userProgram->program->academic_year,

                // Program info
                'program' => [
                    'id'           => $userProgram->program->id,
                    'name'         => $userProgram->program->program_name,
                    'degree_level' => $userProgram->program->degree_level,
                    'department'   => $userProgram->program->department->department_name ?? null,
                ],

                // Certificate-ready data
                'total_credits' => $totalCredits,
                'gpa'           => $gpa,

                // Subjects
                'subjects'      => $subjects,

                'created_at'    => $userProgram->created_at,
            ];
        });

        return response()->json([
            'user_id'      => $user->id,
            'student_name' => $userDetail->latin_name ?? $user->name,
            'khmer_name'   => $userDetail->khmer_name ?? null,
            'email'        => $user->email,
            'academic_history' => $academicHistory,
        ]);
    }

}
