<?php

namespace App\Http\Controllers\score;

use App\Http\Controllers\Controller;
use App\Models\Semester;
use App\Models\StudentScore;
use Illuminate\Http\Request;

class ScoreController extends Controller
{

    public function scoreStudent(Request $request)
    {

        $validated = $request->validate([
            // 'scores' => 'required|numeric|min:0|max:100',
            'student_id' => 'required|exists:users,id',
            'subject_id' => 'required|exists:subjects,id',
            'semester_id' => 'required|exists:semesters,id',
            'attendance_score' => 'nullable|numeric|min:0|max:100',
            'exam_score' => 'nullable|numeric|min:0|max:100',

            // 'attendance_score_percentage' => 'nullable|numeric|min:0|max:100',
            // 'exam_score_percentage' => 'nullable|numeric|min:0|max:100',
        ]);

        // find attendance for the student in the same subject and semester
        $semester = Semester::find($validated['semester_id']);
        if (!$semester) {
            return response()->json([
                'message' => 'Semester not found',
            ], 404);
        }

        // calculate attendance score if attendance_score_percentage is provided
        // if (isset($validated['attendance_score']) && isset($validated['attendance_score_percentage'])) {
        //     $validated['attendance_score'] = ($validated['attendance_score'] / 100) * $validated['attendance_score_percentage'];
        // }

        // $attendance = $semester->timeTables->timeSlots;

        // check sore for the student in the same subject and semester
        $existingScore = StudentScore::where('student_id', $validated['student_id'])
            ->where('subject_id', $validated['subject_id'])
            ->where('semester_id', $validated['semester_id'])
            ->first();
        if ($existingScore) {
            return response()->json([
                'message' => 'Score for this student in this subject and semester already exists',
            ], 409);
        }

        $totalScore = ($validated['attendance_score'] ?? 0) + ($validated['exam_score'] ?? 0);
        $score = StudentScore::create([
            'scores' => $totalScore,
            'student_id' => $validated['student_id'],
            'subject_id' => $validated['subject_id'],
            'semester_id' => $validated['semester_id'],
            'attendance_score' => $validated['attendance_score'] ?? null,
            'exam_score' => $validated['exam_score'] ?? null,
        ]);
        return response()->json([
            'message' => 'Score recorded successfully',
            'data' => $score
        ], 201);

    }

    public function getScoresOfStudent(Request $request)
    {
        $user = $request->user();
        $scores = StudentScore::where('student_id',$user->id)->with([ 'subject', 'semester'])->get();
        return response()->json([
            'message' => 'Scores retrieved successfully',
            'data' => $scores
        ], 200);

    }

}
