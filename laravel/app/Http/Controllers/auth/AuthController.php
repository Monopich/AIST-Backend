<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Generation;
use App\Models\Program;
use App\Models\Role;
use App\Models\User;
use App\Models\UserDetail;
use App\Models\UserProgram;
use App\Models\UserRole;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $validated = $request->validate([
            "email" => "required|email",
            "password" => "required|string"
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user) {
            return response()->json([
                'message' => 'Email does not exist'
            ], 404);
        }

        if (!Hash::check($validated["password"], $user->password)) {
            return response()->json([
                'message' => 'Incorrect email or password'
            ], 401);
        }

        // with Sanctum
        $token = $user->createToken('api-token')->plainTextToken;

        $role = UserRole::with('role')->where('user_id', $user->id)->get()->pluck('role.role_key');

        if ($role->contains('Staff')) {
            $role = $role->filter(function ($item) {
                return $item !== 'Staff';
            })->values();
            $role->push('Teacher');
        }

        $department = Department::where('department_head_id', $user->id)->first();

        if ($role->contains('Head Department')) {
            return response()->json([
                'message' => 'Login successful',
                'token' => $token,
                'roles' => $role,
                'department_id' => $department->id ?? null,
                'users' => $user
            ]);
        }

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'roles' => $role,
            'users' => $user
        ]);
    }


    public function logout(Request $request)
    {
        $token = $request->bearerToken();

        // Case 1: No token at all
        if (!$token) {
            return response()->json(['message' => 'Token is required'], 401);
        }

        // Case 2: Token exists but is invalid
        if (!$request->user()) {
            return response()->json(['message' => 'Invalid token'], 401);
        }

        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successful'
        ]);
    }

    protected function assignRole(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role_id' => 'required|exists:roles,id',
        ]);
        $alreadyAssigned = UserRole::where('user_id', $validated['user_id'])
            ->where('role_id', $validated['role_id'])
            ->exists();
        if ($alreadyAssigned) {
            return response()->json([
                'message' => 'User already assign role'
            ], 409);
        }
        $userRole = UserRole::create($validated);

        return response()->json([
            'message' => "Role assigned successful"
        ]);
    }

    public function registerUser(Request $request)
    {
        $authUser = User::findOrFail($request->user()->id);

        if (!$authUser->hasRole('Admin')) {
            return response()->json([
                'message' => 'You are not authorized to register new users.'
            ], 403);
        }

        DB::beginTransaction();
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                // 'email' => 'required|email|unique:users,email',
                // 'password' => 'required|string|min:8',
                'department_id' => 'nullable|exists:departments,id',
                'sub_department_id' => 'nullable|exists:sub_departments,id',
                'program_id' => 'nullable|exists:programs,id',

                'khmer_first_name' => 'nullable|string',
                'khmer_last_name' => 'nullable|string',
                'latin_name' => 'required|string',
                'khmer_name' => 'required|string',
                'address' => 'nullable|string',
                'date_of_birth' => 'required|date|before:today',
                'origin' => 'nullable|string',
                'profile_picture' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'gender' => 'required|in:Male,Female',
                'phone_number' => 'nullable|string|max:20',
                'bio' => 'nullable|string',

                // optional
                'special' => 'nullable|boolean',
                // 'guardian' => 'nullable|string',
                'high_school' => 'nullable|string',
                'mcs_no' => 'nullable|string',
                'can_id' => 'nullable|string',
                'bac_grade' => 'nullable|string',
                'bac_from' => 'nullable|string',
                'bac_program' => 'nullable|string',
                'degree' => 'nullable|string',
                'option' => 'nullable|string',
                'history' => 'nullable|string',
                'redoubles' => 'nullable|array',
                'scholarships' => 'nullable|string',
                'branch' => 'nullable|string',
                'file' => 'nullable|string',
                'grade' => 'nullable|string',
                'is_radie' => 'nullable|boolean',
                'current_address' => 'nullable|string',
                'father_name' => 'nullable|string',
                'father_phone' => 'nullable|string',
                'mother_name' => 'nullable|string',
                'mother_phone' => 'nullable|string',

                'guardian_phone' => 'nullable|string',
                'guardian_name' => 'nullable|string',
                'place_of_birth' => 'nullable|string',
                'join_at' => 'nullable|date',
                'graduated_from' => 'nullable|string',
                'graduated_at' => 'nullable|integer',
                'experience' => 'nullable|string',

                // prefix for first id_card
                'id_prefix' => 'nullable|string|max:5',

                'role_key' => 'required|string|exists:roles,role_key',

                'start_year' => 'required_if:role_key,Student|integer|min:2000|max:' . (date('Y') + 10),
            ]);



            $year = date('Y');
            $prefix = ($validated['id_prefix'] ?? '') . $year;

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

            $user = User::create([
                'name' => $validated['name'],
                'email' => $email,
                'password' => $idCard,
            ]);



            if ($request->hasFile('profile_picture')) {
                $file = $request->file('profile_picture');
                $extension = $file->getClientOriginalExtension();
                $filename = $idCard . '.' . $extension;

                // Delete old picture if it exists
                Storage::disk('public')->delete('profile_pictures/' . $idCard . '.*');

                $profilePath = Storage::disk('public')->putFileAs(
                    'profile_pictures',
                    $file,
                    $filename
                );
            }
            switch ($validated['role_key']) {
                case 'Student':
                    // Create user detail
                    $UserDetail = UserDetail::create([
                        'user_id' => $user->id,
                        'id_card' => $idCard,
                        'department_id' => $validated['department_id'] ?? null,
                        'sub_department_id' => $validated['sub_department_id'] ?? null,
                        // 'program_id' => $programId,
                        'khmer_first_name' => $validated['khmer_first_name'] ?? null,
                        'khmer_last_name' => $validated['khmer_last_name'] ?? null,
                        'latin_name' => $validated['latin_name'],
                        'khmer_name' => $validated['khmer_name'],
                        'address' => $validated['address'] ?? null,
                        'date_of_birth' => $validated['date_of_birth'],
                        'origin' => $validated['origin'] ?? null,
                        'profile_picture' => $profilePath ?? null,
                        'gender' => $validated['gender'],
                        'bio' => $validated['bio'] ?? null,
                        'phone_number' => $validated['phone_number'] ?? null,
                        'special' => $validated['special'] ?? null,
                        // 'guardian' => $validated['guardian'] ?? null,
                        'high_school' => $validated['high_school'] ?? null,
                        'mcs_no' => $validated['mcs_no'] ?? null,
                        'can_id' => $validated['can_id'] ?? null,
                        'bac_grade' => $validated['bac_grade'] ?? null,
                        'bac_from' => $validated['bac_from'] ?? null,
                        'bac_program' => $validated['bac_program'] ?? null,
                        'degree' => $validated['degree'] ?? null,
                        'option' => $validated['option'] ?? null,
                        'history' => $validated['history'] ?? null,
                        'redoubles' => $validated['redoubles'] ?? null,
                        'scholarships' => $validated['scholarships'] ?? null,
                        'branch' => $validated['branch'] ?? null,
                        'file' => $validated['file'] ?? null,
                        'grade' => $validated['grade'] ?? null,
                        'is_radie' => $validated['is_radie'] ?? null,
                        'current_address' => $validated['current_address'] ?? null,
                        'father_name' => $validated['father_name'] ?? null,
                        'father_phone' => $validated['father_phone'] ?? null,
                        'mother_name' => $validated['mother_name'] ?? null,
                        'mother_phone' => $validated['mother_phone'] ?? null,

                        'guardian_name' => $validated['guardian_name'] ?? null,
                        'guardian_phone' => $validated['guardian_phone'] ?? null,
                        'place_of_birth' => $validated['place_of_birth'] ?? null,
                        'join_at' => $validated['join_at'] ?? null,
                        'graduated_from' => $validated['graduated_from'] ?? null,
                        'graduated_at' => $validated['graduated_at'] ?? null,
                        'experience' => $validated['experience'] ?? null,

                    ]);
                    $role = Role::where('role_key', $validated['role_key'] ?? "Student")->firstOrFail();

                    $user->roles()->syncWithoutDetaching([$role->id]);

                    if ($validated['program_id']) {
                        $program = Program::findOrFail($validated['program_id']);
                        $startYear = $validated['start_year'];

                        $generation = Generation::where('program_id', $program->id)
                            ->orderByDesc('number_gen')
                            ->first();
                        if (!$generation) {
                            $latestGen = Generation::where('program_id', $program->id)
                                ->orderByDesc('number_gen')
                                ->first();

                            $nextNumberGen = $latestGen ? $latestGen->number_gen + 1 : 1;

                            $endYear = $startYear + $program->duration_years;  // use program duration

                            $generation = Generation::create([
                                'program_id' => $program->id,
                                'start_year' => $startYear,
                                'end_year' => $endYear,
                                'number_gen' => $nextNumberGen,
                            ]);
                        }

                        $yearStart = $startYear;
                        $yearEnd = $yearStart + 1;
                        $yearLabel = "{$yearStart}-{$yearEnd}";

                        //  Find or create single academic year
                        $academicYear = AcademicYear::firstOrCreate(
                            ['year_label' => $yearLabel],
                            ['dates' => json_encode(['start_year' => $yearStart, 'end_year' => $yearEnd])]
                        );

                        //  Link student only to this one
                        UserProgram::firstOrCreate(
                            [
                                'program_id' => $program->id,
                                'user_id' => $user->id,
                            ],
                            [
                                'generation_id' => $generation->id,
                                'academic_year_id' => $academicYear->id,
                                'year' => 1
                            ]
                        );

                    }
                    $user->load('userDetail', 'userDetail.department', 'userDetail.subDepartment', 'userPrograms', 'userPrograms.academicYear', 'userPrograms.generation', 'userPrograms.program', 'roles');

                    break;
                case 'Head Department':

                    // Create user detail
                    $UserDetail = UserDetail::create([
                        'user_id' => $user->id,
                        'id_card' => $idCard,
                        'department_id' => null,
                        // 'sub_department_id' => $validated['sub_department_id'] ?? null,
                        // 'program_id' => $programId,
                        'khmer_first_name' => $validated['khmer_first_name'] ?? null,
                        'khmer_last_name' => $validated['khmer_last_name'] ?? null,
                        'latin_name' => $validated['latin_name'],
                        'khmer_name' => $validated['khmer_name'],
                        'address' => $validated['address'] ?? null,
                        'date_of_birth' => $validated['date_of_birth'],
                        'origin' => $validated['origin'] ?? null,
                        'profile_picture' => $profilePath ?? null,
                        'gender' => $validated['gender'],
                        'bio' => $validated['bio'] ?? null,
                        'phone_number' => $validated['phone_number'] ?? null,
                        'special' => $validated['special'] ?? null,
                        // 'guardian' => $validated['guardian'] ?? null,
                        'high_school' => $validated['high_school'] ?? null,
                        'mcs_no' => $validated['mcs_no'] ?? null,
                        'can_id' => $validated['can_id'] ?? null,
                        'bac_grade' => $validated['bac_grade'] ?? null,
                        'bac_from' => $validated['bac_from'] ?? null,
                        'bac_program' => $validated['bac_program'] ?? null,
                        'degree' => $validated['degree'] ?? null,
                        'option' => $validated['option'] ?? null,
                        'history' => $validated['history'] ?? null,
                        'redoubles' => $validated['redoubles'] ?? null,
                        'scholarships' => $validated['scholarships'] ?? null,
                        'branch' => $validated['branch'] ?? null,
                        'file' => $validated['file'] ?? null,
                        'grade' => $validated['grade'] ?? null,
                        'is_radie' => $validated['is_radie'] ?? false,
                        'current_address' => $validated['current_address'] ?? null,

                        'guardian_name' => $validated['guardian_name'] ?? null,
                        'guardian_phone' => $validated['guardian_phone'] ?? null,
                        'place_of_birth' => $validated['place_of_birth'] ?? null,
                        'join_at' => $validated['join_at'] ?? null,
                        'graduated_from' => $validated['graduated_from'] ?? null,
                        'graduated_at' => $validated['graduated_at'] ?? null,
                        'experience' => $validated['experience'] ?? null,

                    ]);
                    $department = Department::find($validated['department_id']);
                    $department?->assignHead($user->id);

                    $role = Role::where('role_key', $validated['role_key'] ?? "Head Department")->firstOrFail();
                    $user->roles()->syncWithoutDetaching([$role->id]);
                    $user->load('userDetail', 'headDepartment', 'roles');
                    break;
                case 'Staff':

                    $UserDetail = UserDetail::create([
                        'user_id' => $user->id,
                        'id_card' => $idCard,
                        'department_id' => $validated['department_id'] ?? null,
                        'sub_department_id' => $validated['sub_department_id'] ?? null,
                        // 'program_id' => $programId,
                        'khmer_first_name' => $validated['khmer_first_name'] ?? null,
                        'khmer_last_name' => $validated['khmer_last_name'] ?? null,
                        'latin_name' => $validated['latin_name'],
                        'khmer_name' => $validated['khmer_name'],
                        'address' => $validated['address'] ?? null,
                        'date_of_birth' => $validated['date_of_birth'],
                        'origin' => $validated['origin'] ?? null,
                        'profile_picture' => $profilePath ?? null,
                        'gender' => $validated['gender'],
                        'bio' => $validated['bio'] ?? null,
                        'phone_number' => $validated['phone_number'] ?? null,
                        'special' => $validated['special'] ?? null,
                        // 'guardian' => $validated['guardian'] ?? null,
                        'high_school' => $validated['high_school'] ?? null,
                        'mcs_no' => $validated['mcs_no'] ?? null,
                        'can_id' => $validated['can_id'] ?? null,
                        'bac_grade' => $validated['bac_grade'] ?? null,
                        'bac_from' => $validated['bac_from'] ?? null,
                        'bac_program' => $validated['bac_program'] ?? null,
                        'degree' => $validated['degree'] ?? null,
                        'option' => $validated['option'] ?? null,
                        'history' => $validated['history'] ?? null,
                        'redoubles' => $validated['redoubles'] ?? null,
                        'scholarships' => $validated['scholarships'] ?? null,
                        'branch' => $validated['branch'] ?? null,
                        'file' => $validated['file'] ?? null,
                        'grade' => $validated['grade'] ?? null,
                        'is_radie' => $validated['is_radie'] ?? null,
                        'current_address' => $validated['current_address'] ?? null,
                        'father_name' => $validated['father_name'] ?? null,
                        'father_phone' => $validated['father_phone'] ?? null,
                        'mother_name' => $validated['mother_name'] ?? null,
                        'mother_phone' => $validated['mother_phone'] ?? null,

                        'guardian_name' => $validated['guardian_name'] ?? null,
                        'guardian_phone' => $validated['guardian_phone'] ?? null,
                        'place_of_birth' => $validated['place_of_birth'] ?? null,
                        'join_at' => $validated['join_at'] ?? null,
                        'graduated_from' => $validated['graduated_from'] ?? null,
                        'graduated_at' => $validated['graduated_at'] ?? null,
                        'experience' => $validated['experience'] ?? null,

                    ]);
                    $role = Role::where('role_key', $validated['role_key'] ?? "Staff")->firstOrFail();
                    $user->roles()->syncWithoutDetaching([$role->id]);
                    $user->load('userDetail.department', 'userDetail.subDepartment', 'roles');

                    break;
                case 'Admin':

                    return response()->json([
                        'message' => 'Admin creation is not allowed via this endpoint.'
                    ], 400);
                    break;
                default:
                    return response()->json([
                        'message' => 'Invalid role key provided.'
                    ], 400);
            }






            // Create mailbox in mail server
            // $password = $idCard;
            // $checkCmd = "docker exec mailserver setup email list | grep -w {$email}";
            // exec($checkCmd, $outputCheck, $returnCheck);

            // if ($returnCheck === 0) {
            //     // Email already exists
            //     $mailboxCreated = false;
            // } else {
            //     // Email does not exist → create it
            //     $cmd = "docker exec mailserver setup email add {$email} {$password}";
            //     exec($cmd, $output, $returnVar);
            //     $mailboxCreated = $returnVar === 0;
            // }


            DB::commit();
            return response()->json([
                'message' => "$role->role_key created user successfully",
                'data' => $user
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'User creation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function updateProfilePicture(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'profile_picture' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $user = User::findOrFail($validated['user_id']);

        DB::beginTransaction();
        try {
            $idCard = $user->userDetail->id_card ?? $user->id;

            if ($request->hasFile('profile_picture')) {
                $file = $request->file('profile_picture');
                $extension = $file->getClientOriginalExtension();
                $filename = $idCard . '.' . $extension;

                // Delete old picture if exists
                if ($user->userDetail && $user->userDetail->profile_picture) {
                    Storage::disk('public')->delete($user->userDetail->profile_picture);
                }

                // Store new picture
                $profilePath = Storage::disk('public')->putFileAs(
                    'profile_pictures',
                    $file,
                    $filename
                );

                // Update or create userDetail record
                $user->userDetail()->updateOrCreate(
                    ['user_id' => $user->id],
                    ['profile_picture' => $profilePath]
                );
            }

            DB::commit();

            return response()->json([
                'message' => 'Profile picture updated successfully',
                'profile_picture' => $user->fresh()->userDetail->profile_picture,
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update profile picture',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function changePassword(Request $request)
    {
        $token = $request->bearerToken(); // Case 1: No token
        if (!$token) {
            return response()->json(['message' => 'Token is required'], 401);
        }

        // Case 2: Token provided but invalid
        if (!$request->user()) {
            return response()->json(['message' => 'Invalid token'], 401);
        }

        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        // Verify old password
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Update in Laravel DB
            $user->password = $request->new_password;
            $user->save();

            // Update in mail server
            // $email = $user->email;
            // $newPassword = $request->new_password;

            // $cmd = "docker exec mailserver setup email update {$email} {$newPassword}";
            // exec($cmd, $output, $returnVar);

            // if ($returnVar !== 0) {
            //     throw new Exception('Mail server password update failed');
            // }

            DB::commit();

            return response()->json([
                'message' => "Password changed successfully for {$user->email}",
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Password change failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function confirmPassword(Request $request)
    {
        $validated = $request->validate([
            'reset_token' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        // Find user with valid reset token
        $user = User::whereNotNull('otp_code')
            ->whereNotNull('otp_expires_at')
            ->get()
            ->first(function ($u) use ($validated) {
                return Hash::check($validated['reset_token'], $u->otp_code);
            });

        if (!$user) {
            return response()->json(['message' => 'Invalid or expired reset token.'], 400);
        }

        // Check if token is expired
        if (Carbon::parse($user->otp_expires_at)->isPast()) {
            return response()->json(['message' => 'Reset token has expired. Please request a new OTP.'], 400);
        }

        DB::beginTransaction();
        try {
            $user->password = $validated['new_password'];
            $user->otp_code = null;
            $user->otp_expires_at = null;
            $user->save();

            DB::commit();

            return response()->json([
                'message' => 'Password has been reset successfully.',
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Password reset failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function createAdmin(Request $request)
    {

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
        ]);


        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        $role = Role::where('role_key', 'Admin')->first();
        if (!$role) {
            return response()->json([
                'message' => 'Admin role not found.'
            ], 404);
        }

        $user->roles()->attach($role->id);

        return response()->json([
            'message' => 'Admin created successfully',
            'user' => $user
        ]);
    }
}
