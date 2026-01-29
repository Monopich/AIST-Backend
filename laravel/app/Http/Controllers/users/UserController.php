<?php

namespace App\Http\Controllers\users;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Generation;
use App\Models\Program;
use App\Models\SubDepartment;
use App\Models\Role;
use App\Models\Subject;
use App\Models\User;
use App\Models\UserDetail;
use App\Models\UserProgram;
use App\Models\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    public function getUserDetails(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }
        $user = User::with([
            'userDetail.department',
            'userDetail.subDepartment',
            'userPrograms.program',
            'userPrograms.generation',
            'userPrograms.academicYear',
            'roles',
            'groups.semester.academicYear',
        ])->find($request->user()->id);


        // Get roles
        $roleKeys = $user->roles()->pluck('role_key');

        // Get groups with latest semester
        $group = $user->groups
            ? $user->groups
            ->filter(fn($group) => $group->pivot->is_active)
            ->map(fn($group) => [
                'id' => $group->id,
                'name' => $group->name,
                'semester' => $group->semester->name ?? null,
                'academic_year' => $group->semester->academicYear->name ?? null,
            ])
            : collect(); // return empty collection if null
        $groups = $group->sortByDesc('academic_year')->values();

        // Get current year
        $userPrograms = $user->userPrograms
            ->filter(fn($up) => $up)
            ->map(function ($up) {
                return [
                    'id' => $up->id,
                    'program_id' => $up->program_id,
                    'program_name' => $up->program?->program_name,
                    'degree_level' => $up->program?->degree_level,
                    'year' => $up->year,
                    'duration_years' => $up->program?->duration_years,
                    'number_generation_program' => $up->generation?->number_gen,
                ];
            });


        // Map academic years
        // $academicYears = $user->userPrograms
        //     ->pluck('academicYear')
        //     ->filter(function ($ay) use ($currentYear) {
        //         $dates = is_string($ay?->dates) ? json_decode($ay->dates, true) : $ay?->dates;
        //         return $dates && ($dates['start_year'] ?? 0) <= $currentYear;
        //     })
        //     ->unique('id')
        //     ->values()
        //     ->map(function ($ay) {
        //         $dates = is_string($ay->dates) ? json_decode($ay->dates, true) : $ay->dates;
        //         return [
        //             'id' => $ay->id,
        //             'year_label' => $ay->year_label,
        //             'dates' => $dates,
        //         ];
        //     });

        // Use relationship directly
        $detailUser = $user->userDetail()->with('department', 'subDepartment')->first();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'user_detail' => $detailUser,
                'head_department' => $user->headDepartment ?? null,
                // 'academic_year' => $academicYears,
                'user_programs' => $userPrograms,
                'profile_picture' => $detailUser?->profile_picture
                    ? asset('storage/' . $detailUser->profile_picture)
                    : null,
                'profile_picture_online' => $detailUser?->profile_picture
                    ? rtrim(env('APP_URL'), '/') . '/storage/' . $detailUser->profile_picture
                    : null,
            ],
            'roles' => $roleKeys,
            'groups' => $groups,
        ], 200);
    }


    public function updateUser(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'program_id' => 'nullable|exists:programs,id',
                'department_id' => 'nullable|exists:departments,id',
                'sub_department_id' => 'nullable|exists:sub_departments,id',
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

                // optional extra fields
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

                'start_year' => 'required_if:role_key,Student|integer|min:2000|max:' . (date('Y') + 10),

                'role_key' => 'required|string|exists:roles,role_key',
            ]);

            // find the user
            $user = User::findOrFail($id);

            $profilePath = $user->userDetail->profile_picture ?? null;

            if ($request->hasFile('profile_picture')) {
                $file = $request->file('profile_picture');
                $extension = strtolower($file->getClientOriginalExtension());

                // Use id_card if exists, fallback to user ID
                $baseName = $user->userDetail->id_card ?? $user->id;

                // Delete any existing profile pictures for this user regardless of extension
                $oldFiles = Storage::disk('public')->files('profile_pictures');
                foreach ($oldFiles as $oldFile) {
                    if (basename($oldFile, pathinfo($oldFile, PATHINFO_EXTENSION)) === $baseName) {
                        Storage::disk('public')->delete($oldFile);
                    }
                }

                // Store the new file
                $filename = $baseName . '.' . $extension;
                $profilePath = $file->storeAs('profile_pictures', $filename, 'public');

                // Update userDetail
                $user->userDetail->profile_picture = $profilePath;
                $user->userDetail->save();
            }
            // update user base table
            $user->update([
                'name' => $validated['name'],
            ]);

            if ($validated['role_key'] === 'Head Department') {

                $user->userDetail->update([
                    'department_id' => null,
                    // 'sub_department_id' => $validated['sub_department_id'] ?? $user->userDetail->sub_department_id,
                    // 'program_id' => $validated['program_id'] ?? $user->userDetail->program_id,
                    'khmer_first_name' => $validated['khmer_first_name'] ?? $user->userDetail->khmer_first_name,
                    'khmer_last_name' => $validated['khmer_last_name'] ?? $user->userDetail->khmer_last_name,
                    'latin_name' => $validated['latin_name'] ?? $user->userDetail->latin_name,
                    'khmer_name' => $validated['khmer_name'] ?? $user->userDetail->khmer_name,
                    'address' => $validated['address'] ?? $user->userDetail->address,
                    'date_of_birth' => $validated['date_of_birth'],
                    'origin' => $validated['origin'] ?? $user->userDetail->origin,
                    'profile_picture' => $profilePath ?? $user->userDetail->profile_picture,
                    'gender' => $validated['gender'],
                    'bio' => $validated['bio'] ?? $user->userDetail->bio,
                    'phone_number' => $validated['phone_number'] ?? $user->userDetail->phone_number,
                    'special' => $validated['special'] ?? $user->userDetail->special,
                    // 'guardian' => $validated['guardian'] ?? $user->userDetail->guardian,
                    'high_school' => $validated['high_school'] ?? $user->userDetail->high_school,
                    'mcs_no' => $validated['mcs_no'] ?? $user->userDetail->mcs_no,
                    'can_id' => $validated['can_id'] ?? $user->userDetail->can_id,
                    'bac_grade' => $validated['bac_grade'] ?? $user->userDetail->bac_grade,
                    'bac_from' => $validated['bac_from'] ?? $user->userDetail->bac_from,
                    'bac_program' => $validated['bac_program'] ?? $user->userDetail->bac_program,
                    'degree' => $validated['degree'] ?? $user->userDetail->degree,
                    'option' => $validated['option'] ?? $user->userDetail->option,
                    'history' => $validated['history'] ?? $user->userDetail->history,
                    'redoubles' => $validated['redoubles'] ?? $user->userDetail->redoubles,
                    'scholarships' => $validated['scholarships'] ?? $user->userDetail->scholarships,
                    'branch' => $validated['branch'] ?? $user->userDetail->branch,
                    'file' => $validated['file'] ?? $user->userDetail->file,
                    'grade' => $validated['grade'] ?? $user->userDetail->grade,
                    'is_radie' => $validated['is_radie'] ?? $user->userDetail->is_radie,
                    'current_address' => $validated['current_address'] ?? $user->userDetail->current_address,
                    'father_name' => $validated['father_name'] ?? $user->userDetail->father_name,
                    'father_phone' => $validated['father_phone'] ?? $user->userDetail->father_phone,
                    'mother_name' => $validated['mother_name'] ?? $user->userDetail->mother_name,
                    'mother_phone' => $validated['mother_phone'] ?? $user->userDetail->mother_phone,

                    'guardian_name' => $validated['guardian_name'] ?? $user->userDetail->guardian_name,
                    'guardian_phone' => $validated['guardian_phone'] ?? $user->userDetail->guardian_phone,
                    'place_of_birth' => $validated['place_of_birth'] ?? $user->userDetail->place_of_birth,

                    'join_at' => $validated['join_at'] ?? null,
                    'graduated_from' => $validated['graduated_from'] ?? null,
                    'graduated_at' => $validated['graduated_at'] ?? null,
                    'experience' => $validated['experience'] ?? null,

                ]);
                $role = Role::where('role_key', $validated['role_key'] ?? 'Head Department')->firstOrFail();
                $user->roles()->syncWithoutDetaching([$role->id]);

                $department = Department::findOrFail($validated['department_id']);

                $department->assignHead($user->id);

                $user->load('userDetail', 'headDepartment', 'roles');
            } elseif ($validated['role_key'] === 'Student') {

                if ($validated['program_id']) {
                    $program = Program::findOrFail($validated['program_id']);
                    $startYear = $validated['start_year'];

                    $generation = Generation::where('program_id', $program->id)
                        ->orderByDesc('number_gen')
                        ->firstOrFail();

                    $yearStart = $startYear;
                    $yearEnd = $yearStart + 1;
                    $yearLabel = "{$yearStart}-{$yearEnd}";

                    //  Find or create single academic year
                    $academicYear = AcademicYear::firstOrCreate(
                        ['year_label' => $yearLabel],
                        ['dates' => json_encode(['start_year' => $yearStart, 'end_year' => $yearEnd])]
                    );

                    //  Link student only to this one
                    UserProgram::create([
                        'program_id' => $program->id,
                        'user_id' => $user->id,
                        'generation_id' => $generation->id,
                        'academic_year_id' => $academicYear->id,
                    ]);
                }
                // update user details
                $user->userDetail->update([
                    'department_id' => $validated['department_id'] ?? $user->userDetail->department_id,
                    'sub_department_id' => $validated['sub_department_id'] ?? $user->userDetail->sub_department_id,
                    // 'program_id' => $validated['program_id'] ?? $user->userDetail->program_id,
                    'khmer_first_name' => $validated['khmer_first_name'] ?? $user->userDetail->khmer_first_name,
                    'khmer_last_name' => $validated['khmer_last_name'] ?? $user->userDetail->khmer_last_name,
                    'latin_name' => $validated['latin_name'] ?? $user->userDetail->latin_name,
                    'khmer_name' => $validated['khmer_name'] ?? $user->userDetail->khmer_name,
                    'address' => $validated['address'] ?? $user->userDetail->address,
                    'date_of_birth' => $validated['date_of_birth'],
                    'origin' => $validated['origin'] ?? $user->userDetail->origin,
                    'profile_picture' => $profilePath ?? $user->userDetail->profile_picture,
                    'gender' => $validated['gender'],
                    'bio' => $validated['bio'] ?? $user->userDetail->bio,
                    'phone_number' => $validated['phone_number'] ?? $user->userDetail->phone_number,
                    'special' => $validated['special'] ?? $user->userDetail->special,
                    // 'guardian' => $validated['guardian'] ?? $user->userDetail->guardian,
                    'high_school' => $validated['high_school'] ?? $user->userDetail->high_school,
                    'mcs_no' => $validated['mcs_no'] ?? $user->userDetail->mcs_no,
                    'can_id' => $validated['can_id'] ?? $user->userDetail->can_id,
                    'bac_grade' => $validated['bac_grade'] ?? $user->userDetail->bac_grade,
                    'bac_from' => $validated['bac_from'] ?? $user->userDetail->bac_from,
                    'bac_program' => $validated['bac_program'] ?? $user->userDetail->bac_program,
                    'degree' => $validated['degree'] ?? $user->userDetail->degree,
                    'option' => $validated['option'] ?? $user->userDetail->option,
                    'history' => $validated['history'] ?? $user->userDetail->history,
                    'redoubles' => $validated['redoubles'] ?? $user->userDetail->redoubles,
                    'scholarships' => $validated['scholarships'] ?? $user->userDetail->scholarships,
                    'branch' => $validated['branch'] ?? $user->userDetail->branch,
                    'file' => $validated['file'] ?? $user->userDetail->file,
                    'grade' => $validated['grade'] ?? $user->userDetail->grade,
                    'is_radie' => $validated['is_radie'] ?? $user->userDetail->is_radie,
                    'current_address' => $validated['current_address'] ?? $user->userDetail->current_address,
                    'father_name' => $validated['father_name'] ?? $user->userDetail->father_name,
                    'father_phone' => $validated['father_phone'] ?? $user->userDetail->father_phone,
                    'mother_name' => $validated['mother_name'] ?? $user->userDetail->mother_name,
                    'mother_phone' => $validated['mother_phone'] ?? $user->userDetail->mother_phone,

                    'guardian_name' => $validated['guardian_name'] ?? $user->userDetail->guardian_name,
                    'guardian_phone' => $validated['guardian_phone'] ?? $user->userDetail->guardian_phone,
                    'place_of_birth' => $validated['place_of_birth'] ?? $user->userDetail->place_of_birth,

                    'join_at' => $validated['join_at'] ?? null,
                    'graduated_from' => $validated['graduated_from'] ?? null,
                    'graduated_at' => $validated['graduated_at'] ?? null,
                    'experience' => $validated['experience'] ?? null,

                ]);
                $role = Role::where('role_key', $validated['role_key'] ?? 'Student')->firstOrFail();
                $user->roles()->syncWithoutDetaching([$role->id]);
                $user->load('userDetail', 'userPrograms', 'userPrograms.program', 'roles');
            } elseif ($validated['role_key'] === 'Staff') {

                $user->userDetail->update([
                    'department_id' => $validated['department_id'] ?? $user->userDetail->department_id,
                    'sub_department_id' => $validated['sub_department_id'] ?? $user->userDetail->sub_department_id,
                    // 'program_id' => $validated['program_id'] ?? $user->userDetail->program_id,
                    'khmer_first_name' => $validated['khmer_first_name'] ?? $user->userDetail->khmer_first_name,
                    'khmer_last_name' => $validated['khmer_last_name'] ?? $user->userDetail->khmer_last_name,
                    'latin_name' => $validated['latin_name'] ?? $user->userDetail->latin_name,
                    'khmer_name' => $validated['khmer_name'] ?? $user->userDetail->khmer_name,
                    'address' => $validated['address'] ?? $user->userDetail->address,
                    'date_of_birth' => $validated['date_of_birth'],
                    'origin' => $validated['origin'] ?? $user->userDetail->origin,
                    'profile_picture' => $profilePath ?? $user->userDetail->profile_picture,
                    'gender' => $validated['gender'],
                    'bio' => $validated['bio'] ?? $user->userDetail->bio,
                    'phone_number' => $validated['phone_number'] ?? $user->userDetail->phone_number,
                    'special' => $validated['special'] ?? $user->userDetail->special,
                    // 'guardian' => $validated['guardian'] ?? $user->userDetail->guardian,
                    'high_school' => $validated['high_school'] ?? $user->userDetail->high_school,
                    'mcs_no' => $validated['mcs_no'] ?? $user->userDetail->mcs_no,
                    'can_id' => $validated['can_id'] ?? $user->userDetail->can_id,
                    'bac_grade' => $validated['bac_grade'] ?? $user->userDetail->bac_grade,
                    'bac_from' => $validated['bac_from'] ?? $user->userDetail->bac_from,
                    'bac_program' => $validated['bac_program'] ?? $user->userDetail->bac_program,
                    'degree' => $validated['degree'] ?? $user->userDetail->degree,
                    'option' => $validated['option'] ?? $user->userDetail->option,
                    'history' => $validated['history'] ?? $user->userDetail->history,
                    'redoubles' => $validated['redoubles'] ?? $user->userDetail->redoubles,
                    'scholarships' => $validated['scholarships'] ?? $user->userDetail->scholarships,
                    'branch' => $validated['branch'] ?? $user->userDetail->branch,
                    'file' => $validated['file'] ?? $user->userDetail->file,
                    'grade' => $validated['grade'] ?? $user->userDetail->grade,
                    'is_radie' => $validated['is_radie'] ?? $user->userDetail->is_radie,
                    'current_address' => $validated['current_address'] ?? $user->userDetail->current_address,
                    'father_name' => $validated['father_name'] ?? $user->userDetail->father_name,
                    'father_phone' => $validated['father_phone'] ?? $user->userDetail->father_phone,
                    'mother_name' => $validated['mother_name'] ?? $user->userDetail->mother_name,
                    'mother_phone' => $validated['mother_phone'] ?? $user->userDetail->mother_phone,

                    'guardian_name' => $validated['guardian_name'] ?? $user->userDetail->guardian_name,
                    'guardian_phone' => $validated['guardian_phone'] ?? $user->userDetail->guardian_phone,
                    'place_of_birth' => $validated['place_of_birth'] ?? $user->userDetail->place_of_birth,

                    'join_at' => $validated['join_at'] ?? null,
                    'graduated_from' => $validated['graduated_from'] ?? null,
                    'graduated_at' => $validated['graduated_at'] ?? null,
                    'experience' => $validated['experience'] ?? null,
                ]);
                $role = Role::where('role_key', $validated['role_key'] ?? 'Staff')->firstOrFail();
                $user->roles()->syncWithoutDetaching([$role->id]);

                $user->load('userDetail', 'headDepartment', 'roles');
            }
            // update user details
            else {
                // update user details

            }



            // sync role

            DB::commit();


            $user->load('userDetail', 'headDepartment', 'roles');
            return response()->json([
                'message' => "$role->role_key updated user successfully",
                'data' => $user->load('userDetail', 'roles')
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'User update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function registerNewStaff(Request $request)
    {

        DB::beginTransaction();
        try {

            $validated = $request->validate([

                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|min:8',

                'id_card' => 'required|string|unique:user_details,id_card',
                'department_id' => 'required|exists:departments,id',
                'sub_department_id' => 'required|exists:sub_departments,id',
                // 'semester_id' => 'required|exists:semesters,id',
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

                // 'role_id' => 'required|exists:roles,id',
                'role_key' => 'required|string|exists:roles,role_key',
            ]);
            $role = Role::where('role_key', $validated['role_key'])->first();

            if (!$role) {
                return response()->json([
                    'message' => 'Invalid role key provided.'
                ], 400);
            }
            $profilePath = null;
            if ($request->hasFile('profile_picture')) {
                $profilePath = $request->file('profile_picture')->store('profile_pictures', 'public');
            }



            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
            ]);

            UserDetail::create([
                'user_id' => $user->id,
                'id_card' => $validated['id_card'],
                'department_id' => $validated['department_id'],
                'sub_department_id' => $validated['sub_department_id'],
                // 'semester_id' => $validated['semester_id'],
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
            ]);

            $alreadyAssigned = UserRole::where('user_id', $user->id)
                ->where('role_id', $role->id)
                ->exists();

            if (!$alreadyAssigned) {
                UserRole::create([
                    'user_id' => $user->id,
                    'role_id' => $role->id,
                ]);
            }

            DB::commit();
            return response()->json([
                'message' => "$role->role_key created user successful",
                'data' => $user
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'User creation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function getAllStaff(Request $request)
    {
        $staffs = User::whereHas('roles', function ($query) {
            $query->where('role_key', 'Staff')
                ->orWhere('role_key', 'Head Department');
        })
            ->with([
                'userDetail',
                'roles' => function ($query) {
                    $query->select('name', 'role_key');
                }
            ])
            ->get()
            ->map(function ($user) {
                $flattened = $user->toArray();
                $userDetail = $flattened['user_detail'] ?? [];

                // Remove original user_detail
                unset($flattened['user_detail']);

                // Merge user_detail fields into top-level user object
                return array_merge($flattened, $userDetail);
            });;

        return response()->json([
            'message' => 'List all staffs$staffs successful.',
            'staffs' => $staffs
        ]);
    }

    public function paginateAllStaff(Request $request)
    {
        $perPage = $request->query('per_page');

        if (!$perPage) {
            $perPage = 14;
        }
        $staffs = User::whereHas('roles', function ($query) {
            $query->where('role_key', 'Staff')
                ->orWhere('role_key', 'Head Department');
        })
            ->with([
                'userDetail',
                'roles' => function ($query) {
                    $query->select('name', 'role_key');
                }
            ])
            ->orderBy('name', 'asc')
            ->paginate($perPage); // paginate with 10 per page

        // Transform the paginated items
        $staffs->getCollection()->transform(function ($user) {
            $flattened = $user->toArray();
            $userDetail = $flattened['user_detail'] ?? [];

            unset($flattened['user_detail']);

            return array_merge($flattened, $userDetail);
        });

        return response()->json([
            'message' => 'List all staffs successful.',
            'staffs' => $staffs
        ]);
    }

    public function changePictureProfile(Request $request)
    {

        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $validated = $request->validate([
            'profile_picture' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $profilePath = $request->file('profile_picture')->store('profile_pictures', 'public');

        // Update user detail with new profile picture
        $user->userDetail()->update(['profile_picture' => $profilePath]);

        return response()->json([
            'message' => 'Profile picture updated successfully',
            'profile_picture' => $profilePath
        ]);
    }

    public function removeUser($userId)
    {

        $user = User::find($userId);

        if (!$user) {
            return response()->json([
                'message' => "User not found."
            ], 404);
        }

        $user->delete();

        $user->userDetail()->delete();

        return response()->json([
            'message' => "User $user->email deleted successful.",
        ]);
    }

    public function getAllHead()
    {

        $users = User::whereHas('roles', function ($query) {
            $query->where('role_key', 'Head Department');
        })->with('headDepartment')->get();

        if ($users->isEmpty()) {
            return response()->json([
                'message' => "User not available .",
            ], 404);
        }
        return response()->json([
            'message' => "List all head of department successful .",
            'users' => $users
        ]);
    }

    public function getAllUsers(Request $request)
    {
        $perPage = $request->input('per_page', 14);
        $origin = $request->input('origin');
        $academicYear = $request->input('academic_year');


        $roleKey = $request->filled('role') ? trim(preg_replace('/\s+/', '', $request->input('role'))) : null;
        $gender = $request->filled('gender') ? trim($request->input('gender')) : null;

        $query = User::with('userDetail', 'groups', 'roles', 'headDepartment', 'userPrograms.program:id,program_name,degree_level,duration_years');

        if ($roleKey) {
            $query->whereHas('roles', function ($q) use ($roleKey) {
                $q->whereRaw("REPLACE(role_key, ' ', '') = ?", [$roleKey]);
            });
        }

        if ($gender) {
            $query->whereHas('userDetail', function ($q) use ($gender) {
                $q->where('gender', $gender);
            });
        }
        if ($origin) {
            $query->whereHas('userDetail', function ($q) use ($origin) {
                $q->whereRaw("REPLACE(origin, ' ', '') = ?", [$origin]);
            });
        }


        if ($academicYear) {
            $query->whereHas('userPrograms.academicYear', function ($q) use ($academicYear) {
                $q->where('year_label', $academicYear);
            });
        }

        $users = $query->paginate($perPage);

        $users->getCollection()->transform(function ($user) {
            $userArray = $user->toArray();
            $userDetail = $userArray['user_detail'] ?? [];

            // 🔹 Find the current program
            $currentProgram = null;
            if (!empty($userDetail['user_programs'])) {
                // Example: take the latest program by start_date as current
                $programs = collect($userDetail['user_programs']);
                $currentProgram = $programs->sortByDesc('start_date')->first();
            }

            unset($userArray['user_detail']);
            $userArray = array_merge($userArray, $userDetail);

            // Add current program
            $userArray['current_program'] = $currentProgram;

            return $userArray;
        });

        if ($users->isEmpty()) {
            return response()->json([
                'message' => "User not available."
            ], 404);
        }




        return response()->json([
            'message' => "List users successful",
            'users' => $users
        ]);
    }



    public function getAllHeadDepartment(Request $request)
    {

        $users = User::whereHas('roles', function ($q) {
            $q->where('role_key', 'Head Department');
        })->with('headDepartment')->get();

        if ($users->isEmpty()) {
            return response()->json([
                'message' => "Head department in not found ."
            ]);
        }

        $users->load('userDetail', 'roles:id,role_key');
        return response()->json([
            'message' => 'Get all head of department success.',
            'users' => $users
        ]);
    }

    public function getUserById($userId)
    {

        $user = User::with(['userDetail', 'roles:id,name,role_key', 'userDetail.userPrograms', 'userDetail.userPrograms.generation:id,number_gen'])->findOrFail($userId);

        if (!$userId) {
            return response()->json([
                'message' => "User not found."
            ]);
        }
        return response()->json([
            'message' => "Get user successful.",
            'user' => $user
        ], 200);
    }


    public function getStudentLearnWithTeacher(Request $request)
    {
        $teacher = $request->user();

        /**
         * 0) Resolve teacher department id (prefer userDetail)
         */
        $teacherDepartmentId = optional($teacher->userDetail)->department_id ?? $teacher->department_id;

        \Log::info('Teacher Debug', [
            'teacher_id' => $teacher->id,
            'teacher_department_id_users_table' => $teacher->department_id ?? null,
            'teacher_department_id_userDetail' => optional($teacher->userDetail)->department_id ?? null,
            'resolved_department_id' => $teacherDepartmentId,
        ]);

        /**
         * 1) Subjects taught by teacher
         */
        $subjects = Subject::whereHas('teachers', function ($q) use ($teacher) {
            $q->where('users.id', $teacher->id);
        })->get();

        if ($subjects->isEmpty()) {
            return response()->json([
                'message' => 'This teacher is not assigned to any subject.',
                'total'   => 0,
                'data'    => [],
            ], 200);
        }

        /**
         * 2) Program IDs from those subjects
         */
        $programIds = $subjects->pluck('program_id')->filter()->unique()->values();

        \Log::info('Program IDs from subjects', ['programIds' => $programIds]);

        if ($programIds->isEmpty()) {
            return response()->json([
                'message' => 'Teacher subjects have no program_id assigned.',
                'total'   => 0,
                'data'    => [],
            ], 200);
        }

        /**
         * 3) Query students in those programs
         *    ✅ section = sub_department (via user.userDetail.subDepartment or program.subDepartment)
         */
        $query = UserProgram::query()
            ->with([
                // user + student detail + section(sub_department)
                'user.userDetail.subDepartment',

                // program + department + section(sub_department)
                'program.department',
                'program.subDepartment',

                // generation
                'generation',
            ])
            ->whereIn('program_id', $programIds)
            ->whereHas('user.userRoles.role', function ($q) {
                $q->where('role_key', 'Student');
            });

        // Optional department filter
        if ($teacherDepartmentId) {
            $query->whereHas('user.userDetail', function ($q) use ($teacherDepartmentId) {
                $q->where('department_id', $teacherDepartmentId);
            });
        } else {
            \Log::warning('Teacher has NO department_id (users table and userDetail). Skipping department filter.');
        }

        $userPrograms = $query->get();

        /**
         * 4) Remove duplicates by user_id (keep latest)
         */
        $userPrograms = $userPrograms
            ->sortByDesc('id')
            ->unique('user_id')
            ->values();

        /**
         * 5) Shape response:
         *    - include: user, user_detail, program, generation
         *    - ✅ section = sub_department
         *    - ✅ section_name field for easy UI
         */
        $data = $userPrograms->map(function ($up) {
            $user = $up->user;
            $userDetail = $user?->userDetail;

            // ✅ section = sub_department (priority: user_detail, fallback: program)
            $subDept = $userDetail?->subDepartment ?? $up->program?->subDepartment;

            // handle both possible column names: sub_department_name or name
            $sectionName = $subDept?->sub_department_name ?? $subDept?->name ?? null;

            return [
                'id'               => $up->id,
                'program_id'       => $up->program_id,
                'year'             => $up->year,
                'user_id'          => $up->user_id,
                'generation_id'    => $up->generation_id,
                'academic_year_id' => $up->academic_year_id,

                // ✅ section object used by your frontend mapping
                'section' => $subDept ? [
                    'id'   => $subDept->id,
                    'name' => $sectionName ?? '',
                ] : null,

                // ✅ direct string for UI convenience
                'section_name' => $sectionName ?? '',

                'user'        => $user,
                'user_detail' => $userDetail,
                'program'     => $up->program ?? null,
                'generation'  => $up->generation ?? null,
            ];
        })->values();

        return response()->json([
            'message' => $data->isEmpty()
                ? 'No students found for this teacher.'
                : 'Students learning with this teacher retrieved successfully.',
            'total' => $data->count(),
            'data'  => $data,
        ], 200);
    }


    public function getAllTeachers()
    {
        $teachers = User::whereHas('roles', function ($query) {
            $query->where('role_key', 'Teacher');
        })
            ->with('userDetail')
            ->get()
            ->map(function ($user) {
                $flattened = $user->toArray();
                $userDetail = $flattened['user_detail'] ?? [];

                // Remove original user_detail
                unset($flattened['user_detail']);

                // Merge user_detail fields into top-level user object
                return array_merge($flattened, $userDetail);
            });

        return response()->json([
            'message' => 'List all teachers successful.',
            'teachers' => $teachers
        ]);
    }

    /**
     * Get available teachers (staff) for a specific date and time range
     * Excludes teachers who are already teaching at that time
     */
    public function getAvailableTeachers(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i:s',
            'end_time' => 'required|date_format:H:i:s',
            'exclude_slot_id' => 'nullable|exists:time_slots,id', // For edit mode
        ]);

        $date = $validated['date'];
        $startTime = $validated['start_time'];
        $endTime = $validated['end_time'];
        $excludeSlotId = $validated['exclude_slot_id'] ?? null;

        // Get all busy teacher IDs for this date and time
        $busyTeacherIds = \App\Models\TimeSlot::where('time_slot_date', $date)
            ->whereNotNull('teacher_id')
            ->when($excludeSlotId, function ($query) use ($excludeSlotId) {
                // Exclude the current slot being edited
                $query->where('id', '!=', $excludeSlotId);
            })
            ->get()
            ->filter(function ($slot) use ($startTime, $endTime) {
                $slotTime = is_array($slot->time_slot) ? $slot->time_slot : json_decode($slot->time_slot, true);
                $slotStart = $slotTime['start_time'];
                $slotEnd = $slotTime['end_time'];
                
                // Check for time overlap
                return $startTime < $slotEnd && $endTime > $slotStart;
            })
            ->pluck('teacher_id')
            ->unique()
            ->filter(); // Remove null values

        // Get all staff/teachers except the busy ones
        $availableTeachers = User::whereHas('roles', function ($query) {
            $query->where('role_key', 'Staff')
                ->orWhere('role_key', 'Head Department');
        })
            ->whereNotIn('id', $busyTeacherIds)
            ->with([
                'userDetail',
                'roles' => function ($query) {
                    $query->select('name', 'role_key');
                }
            ])
            ->orderBy('name')
            ->get()
            ->map(function ($user) {
                $flattened = $user->toArray();
                $userDetail = $flattened['user_detail'] ?? [];
                unset($flattened['user_detail']);
                return array_merge($flattened, $userDetail);
            });

        return response()->json([
            'message' => 'Available teachers retrieved successfully.',
            'teachers' => $availableTeachers,
            'busy_count' => $busyTeacherIds->count(),
        ]);
    }

    public function getAllUsersWithoutPagination(Request $request, $department_id)
    {
        $roleInput = $request->query('role');
        $role = $roleInput ? strtolower($roleInput) : null;
        $allowRoles = ['student', 'teacher', 'staff'];
        if ($role && !in_array($role, $allowRoles)) {
            return response()->json([
                'status' => 'error',
                'code' => 400,
                'message' => "Invalid role parameter. Allowed values are: Student, Teacher, Staff"
            ], 400);
        }
        $roleMap = [
            'student' => 'Student',
            'staff' => 'Staff',
            'teacher' => 'Staff',
        ];
        $users = User::with('roles')
            ->whereHas('userDetail', function ($q) use ($department_id) {
                $q->where('department_id', $department_id);
            })
            ->when($role, function ($q) use ($role, $roleMap) {
                $roleKey = $roleMap[$role];
                $q->whereHas('roles', function ($qr) use ($roleKey) {
                    $qr->where('role_key', $roleKey);
                });
            })
            ->get();
        if ($users->isEmpty()) {
            return response()->json([
                'message' => "User not available."
            ], 404);
        }

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => "List users successful",
            'users' => $users
        ]);
    }
}
