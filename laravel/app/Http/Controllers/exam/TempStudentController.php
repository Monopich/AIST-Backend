<?php

namespace App\Http\Controllers\exam;

use App\Http\Controllers\Controller;
use App\Models\TempStudent;
use App\Models\TempStudentList;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TempStudentController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'academic_year' => 'required|string',
            'khmer_name' => 'required|string|max:255',
            'latin_name' => 'required|string|max:255',
            'profile_picture' => 'nullable|image|max:2048',
            'gender' => 'required|in:Male,Female',
            'date_of_birth' => 'required|date',
            'phone_number' => 'required|string|max:20|unique:temp_students,phone_number',
            'origin' => 'required|string|max:255',
            'department_id' => 'required|integer',
            'program_id' => 'required|integer',
        ]);

        if ($request->hasFile('profile_picture')) {
            $data['profile_picture'] =
                $request->file('profile_picture')
                    ->store('temp_students/profile_pictures', 'local');
        }

        $student = TempStudent::create($data);

        return response()->json([
            'message' => 'Temp student created successfully',
            'data' => $student,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $student = TempStudent::findOrFail($id);

        $data = $request->validate([
            'khmer_name' => 'nullable|string|max:255',
            'latin_name' => 'nullable|string|max:255',
            'profile_picture' => 'nullable|image|max:20480',
            'gender' => 'nullable|in:Male,Female',
            'date_of_birth' => 'nullable|date',
            'phone_number' => 'nullable|string|max:20',
            'origin' => 'nullable|string|max:255',
            'department_id' => 'nullable|integer',
            'program_id' => 'nullable|exists:programs,id',
        ]);

        // ✅ Handle profile picture upload (replace old one)
        if ($request->hasFile('profile_picture')) {

            // delete old picture if exists
            if ($student->profile_picture && Storage::exists($student->profile_picture)) {
                Storage::delete($student->profile_picture);
            }

            // store new picture
            $data['profile_picture'] = $request
                ->file('profile_picture')
                ->store('temp_students/profile_pictures');
        }

        $student->update($data);
        $student->save();



        return response()->json([
            'message' => 'Temp student updated successfully',
            'data' => $student
        ]);
    }

    public function profilePicture($id, Request $request)
    {
        // Validate token from query parameter
        $token = $request->query('token');

        if (!$token) {
            abort(401, 'Unauthorized');
        }

        // Verify the token using Sanctum
        $tokenModel = \Laravel\Sanctum\PersonalAccessToken::findToken($token);

        if (!$tokenModel) {
            abort(401, 'Invalid token');
        }

        // Check if user has Admin role
        $user = $tokenModel->tokenable;
        if (!$user || !$user->hasRole('Admin')) {
            abort(403, 'Forbidden');
        }

        $student = TempStudent::findOrFail($id);

        if (!$student->profile_picture) {
            abort(404, 'No profile picture set for this student');
        }

        if (!Storage::disk('local')->exists($student->profile_picture)) {
            // Debug: show what path we're looking for
            abort(404, 'File not found: ' . $student->profile_picture);
        }

        return response()->file(
            Storage::disk('local')->path($student->profile_picture)
        );
    }

    public function destroy($id)
    {
        $student = TempStudent::findOrFail($id);

        DB::transaction(function () use ($student) {

            // 1️⃣ Delete profile picture file (if exists)
            if (
                $student->profile_picture &&
                Storage::disk('local')->exists($student->profile_picture)
            ) {
                Storage::disk('local')->delete($student->profile_picture);
            }

            // 2️⃣ Delete exam list records (history)
            TempStudentList::where('temp_student_id', $student->id)->delete();

            // 3️⃣ Delete temp student
            $student->delete();
        });

        return response()->json([
            'message' => 'Temp student deleted successfully'
        ]);
    }


    public function index()
    {
        return response()->json(
            TempStudent::orderBy('created_at', 'asc')->paginate(10)
        );
    }

    public function show($id)
    {
        $student = TempStudent::findOrFail($id);

        // Generate full URL with app URL from config
        $imageUrl = $student->profile_picture
            ? url('/external_exam/temp-student/' . $student->id . '/profile-picture?token=' . request()->query('token'))
            : null;

        return response()->json([
            'data' => $student,
            'profile_picture_url' => $imageUrl,
        ]);
    }


}
