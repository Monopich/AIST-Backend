<?php

namespace App\Http\Controllers\department;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Program;
use App\Models\SubDepartment;
use App\Models\User;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    public function assignHeadDepartment(Request $request, $department_id)
    {
        $department = Department::find($department_id);

        if (!$department) {
            return response()->json([
                'message' => 'Department not found.'
            ], 404);
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $user = User::with('roles')->find($validated['user_id']);

        if ($department->department_head_id !== null) {
            return response()->json([
                'message' => 'This department already has a head assigned.'
            ], 400);
        }
        // Check if user has Head_of_Department role
        if (!$user->hasRole('Head Department')) {
            return response()->json([
                'message' => 'User is not a Head of Department.'
            ], 403);
        }

        // Ensure the user is not already assigned as head of another department
        $existingDepartment = Department::where('department_head_id', $user->id)
            ->where('id', '!=', $department->id)
            ->first();

        if ($existingDepartment) {
            return response()->json([
            'message' => 'This user is already assigned as head of another department.'
            ], 409);
        }

        // Assign head to department
        $department->department_head_id = $user->id;
        $department->save();

        return response()->json([
            'message' => 'Department head assigned successfully.',
            'department' => $department
        ]);

    }

    public function createNewDepartment(Request $request)
    {

        $validated = $request->validate([
            'department_name' => [
            'required',
            'string',
            'max:255',
            Rule::unique('departments')->whereNull('deleted_at')

            ],
            "description" => "nullable|string",
            "department_head_id" => "nullable|exists:users,id"
        ]);


        $department = Department::create([
            "department_name" => $validated['department_name'],
            "description" => $validated['description'],
            "department_head_id" => $validated['department_head_id'] ?? null
        ]);

        return response()->json([
            'message' => 'Department created successfully ',
            'Department' => $department,
        ]);
    }

    public function updateDepartment(Request $request, $department_id)
    {
        // $authUser = User::findOrFail($request->user()->id);

        // if (!$authUser->hasRole('Admin')) {
        //     return response()->json([
        //         'message' => 'You are not authorized to update department.'
        //     ], 403);
        // }

        $foundDepartment = Department::find($department_id);

        $keepHead = $foundDepartment->department_head_id;

        if (!$foundDepartment) {
            return response()->json([
                'message' => 'Depart not found .'
            ]);
        }
        if (!$department_id) {
            return response()->json([
                'message' => 'Id of department is required .'
            ]);
        }


        $validated = $request->validate([
            "department_name" => "required|string|max:255|unique:departments,department_name",
            "description" => "nullable|string",
            "department_head_id" => "nullable|exists:users,id"
        ]);


        $foundDepartment->update([
            "department_name" => $validated['department_name'],
            "description" => $validated['description'] ?? $foundDepartment->description,
            "department_head_id" => $validated['department_head_id'] ?? $keepHead
        ]);

        return response()->json([
            'message' => 'Department updated successfully ',
            'Department' => $foundDepartment,
        ]);
    }



    public function listAllDepartment()
    {
        $departments = Department::with('headOfDepartment')->get();

        if ($departments->isEmpty()) {
            return response()->json([
                'message' => 'No available Department'
            ]);
        }
        return response()->json([
            'message' => 'List successfully',
            'all_department' => $departments
        ]);

    }
    public function findDetailDepartment( $department_id)
    {
        if(!$department_id){
             return response()->json([
                'message' => 'Department not found '
            ]);
        }
        $department = Department::find($department_id);

        if (!$department) {
            return response()->json([
                'message' => 'Department not found '
            ]);
        }
        $department->load(['subDepartments', 'headOfDepartment']);
        return response()->json([
            'message' => "Find department successful",
            'department' => $department
        ]);

    }

    public function paginateStudentByDepartment(Request $request, $department_id)
    {
        $students = User::whereHas('roles', function ($query) {
            $query->where('role_key', 'Student');
        })->whereHas('userDetail', function ($query) use ($department_id) {
            $query->where('department_id', $department_id);
        })
            ->with(['userDetail'])
            ->paginate(14);


        return response()->json([
            'students' => $students->items(),
            'meta' => [
                'current_page' => $students->currentPage(),
                'last_page' => $students->lastPage(),
                'per_page' => $students->perPage(),
                'total' => $students->total(),
            ],
            'links' => [
                'first' => $students->url(1),
                'last' => $students->url($students->lastPage()),
                'prev' => $students->previousPageUrl(),
                'next' => $students->nextPageUrl(),
            ],
        ]);
    }

    public function getDepartmentByHead(Request $request)
    {
        if (!$request->user()) {
            return response()->json([
                'message' => 'Unauthorized'
            ]);
        }
        $userId = $request->user()->id;
        $departments = Department::where('department_head_id', $userId)->get();
        if ($departments->isEmpty()) {
            return response()->json([
                'message' => 'You are not owner of any department.'
            ], 404);
        }
        return response()->json([
            'message' => "List department successful",
            'department' => $departments
        ]);

    }

    public function listAllStaffOfDepartment(Request $request)
    {

        $departmentId = $request->get('department_id');
        $allStaff = User::whereHas('userDetail', function ($query) use ($departmentId) {
            $query->where('department_id', $departmentId);
        })->whereHas('roles', function ($query) {
            $query->where('role_key', '!=', 'Student'); // exclude students
        })->with(['userDetail', 'roles'])->get();

        if ($allStaff->isEmpty()) {
            return response()->json([
                'message' => 'No staff found for this department.',
            ], 404);
        }

        return response()->json([
            'message' => 'List of staff retrieved successfully.',
            'allStaff' => $allStaff,
        ]);
    }

    public function addStaffToDepartment(Request $request)
    {

        $validated = $request->validate([
            ''
        ]);

    }

    public function deleteDepartment(Request $request, $department_id)
    {
        $foundDepartment = Department::find($department_id);

        if (!$foundDepartment) {
            return response()->json([
                'message' => 'department not found.'
            ], 404);
        }

        $foundDepartment->delete();

        return response()->json([
            'message' => 'Department is deleted successful'
        ]);
    }

    public function changeHeadDepartment(Request $request, $department_id)
    {

        $foundDepartment = Department::find($department_id);
        if (!$foundDepartment) {
            return response()->json([
                'message' => 'department not found.'
            ], 404);
        }

        $validated = $request->validate([
            'department_head_id' => 'required|exists:users,id'
        ]);

        $foundDepartment->update([
            'department_head_id' => $validated['department_head_id']
        ]);
        return response()->json(
            [
                'message' => 'Head of department changed successful.'
            ]
        );

    }

    public function listAllSubDepartmentOfDepartment(Request $request)
    {
        $department_id = $request->input('department_id');

        $listSubDepartment = SubDepartment::where('department_id', $department_id)->get();

        if ($listSubDepartment->isEmpty()) {
            return response()->json([
                'message' => 'this department not available sub-department.'
            ],404);
        }

        $department = Department::find($department_id);

        return response()->json([
            "message" => "List All Sub-Department from $department->department_name",
            "all_sub_departments" => $listSubDepartment
        ]);


    }


    public function searchDepartment(Request $request){

        $search = $request->query('search');
        $perPage = $request->query('per_page', 14);

        $departments = Department::where('department_name', 'like',"$search%")
        ->orWhere('department_name', 'like',"$search%")->with('subDepartments')->paginate($perPage);
        ;

        if($departments->isEmpty()){
            return response()->json([
            'message' => "No result match with $search"
        ]);
        }

        return response()->json([
            'message' => "Result of $search",
            'departments' => $departments
        ]);


    }

    // public function addSubDepartmentToProgram(Request $request, $subDepartment_id){

    //     $validated = $request->validate([
    //         'department_id' => 'required|exists:departments,id',
    //     ]);

    // }

    // public function getUserByHeadDepartment(Request $request, $department_id)
    // {
    //     // 1. Check department
    //     $department = Department::find($department_id);
    //     if (!$department) {
    //         return response()->json([
    //             'message' => 'Department not found.'
    //         ], 404);
    //     }

    //     // 2. Allowed roles
    //     $allowedRoles = ['staff', 'teacher', 'student'];

    //     // 3. Get roles from query
    //     $roles = $request->query('role');

    //     // Default roles if not provided
    //     $requestedRoles = $roles
    //         ? array_map('trim', explode(',', $roles))
    //         : ['staff', 'student'];

    //     // 4. Validate roles
    //     foreach ($requestedRoles as $role) {
    //         if (!in_array($role, $allowedRoles)) {
    //             return response()->json([
    //                 'message' => 'Invalid role provided.',
    //                 'allowed_roles' => $allowedRoles
    //             ], 400);
    //         }
    //     }

    //     // 5. Normalize roles (teacher → staff)
    //     $roleMap = [
    //         'teacher' => 'staff',
    //     ];

    //     $roleKeys = collect($requestedRoles)
    //         ->map(fn ($role) => $roleMap[$role] ?? $role)
    //         ->unique()
    //         ->values()
    //         ->toArray();

    //     // 6. Query users
    //     $users = User::whereHas('roles', function ($query) use ($roleKeys) {
    //             $query->whereIn('role_key', $roleKeys);
    //         })
    //         ->whereHas('userDetail', function ($query) use ($department_id) {
    //             $query->where('department_id', $department_id);
    //         })
    //         ->with(['roles', 'userDetail'])
    //         ->get();

    //     return response()->json([
    //         'status' => 'success',
    //         'code' => 200,
    //         'message' => 'Users retrieved successfully.',
    //         'department' => $department->name,
    //         // 'roles_requested' => $requestedRoles,
    //         'roles_used' => $roleKeys,
    //         'users' => $users
    //     ]);
    // }

    public function getUserByHeadDepartment(Request $request, $department_id)
    {
        $department = Department::find($department_id);
        if (!$department) {
            return response()->json(['message' => 'Department not found.'], 404);
        }

        $allowedRoles = ['staff', 'teacher', 'student'];

        $roles = $request->query('role');
        $requestedRoles = $roles
            ? array_map('trim', explode(',', $roles))
            : ['staff', 'student'];

        foreach ($requestedRoles as $role) {
            if (!in_array($role, $allowedRoles)) {
                return response()->json([
                    'message' => 'Invalid role provided.',
                    'allowed_roles' => $allowedRoles
                ], 400);
            }
        }

        // Map API roles to DB role_key
        $roleMap = [
            'teacher' => 'Staff',
            'staff'   => 'Staff',
            'student' => 'Student',
        ];

        $roleKeys = collect($requestedRoles)
            ->map(fn ($r) => $roleMap[$r] ?? $r)
            ->unique()
            ->values()
            ->toArray();

        $users = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('role_key', $roleKeys))
            ->whereHas('userDetail', fn ($q) => $q->where('department_id', $department_id))
            ->with([
                'roles:id,name,description,role_key,level',
                'userDetail.department:id,department_name',
                'userDetail.subDepartment:id,name,department_id,description',

                'userPrograms' => function ($q) {
                    $q->orderByDesc('academic_year_id')
                    ->orderByDesc('generation_id')
                    ->orderByDesc('id')
                    ->with('program:id,program_name,degree_level,duration_years');
                },
            ])
            ->get();

        $users = $users->map(function ($u) {

            // ✅ current program = first item after ordering
            $currentUP = $u->userPrograms->first();

            // ✅ optional: collect all program names
            $programNames = $u->userPrograms
                ->pluck('program.program_name')
                ->filter()
                ->values()
                ->toArray();

            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'created_at' => $u->created_at,
                'updated_at' => $u->updated_at,

                'roles' => $u->roles,
                'user_detail' => $u->userDetail,
                'section' => $u->userDetail?->subDepartment,

                // ✅ programs list (rows)
                'programs' => $u->userPrograms,

                // ✅ current program info
                'current_program' => $currentUP,
                'program' => $currentUP?->program,
                'program_name' => $currentUP?->program?->program_name,

                // ✅ optional
                'program_names' => $programNames,
            ];
        });

        return response()->json([
            'status' => 'success',
            'code' => 200,
            'message' => 'Users retrieved successfully.',
            'department' => $department->department_name ?? $department->name,
            'roles_used' => $roleKeys,
            'users' => $users,
        ]);
    }

    public function getProgramByDepartment(Request $request, $id)
    {
        $programs = Program::where('department_id', $id)->get();
        $department = Department::find($id);

        if (!$department) {
            return response()->json([
                'status' => 'error',
                'code' => 404,
                'message' => 'Department not found.'
            ], 404);

        $user = $request->user();

        if($user->id !== $department->department_head_id){
            return response()->json([
                'status' => 'error',
                'code' => 403,
                'message' => 'You are not authorized to access this department programs.'
            ], 403);

        }
        }

        return response()->json([
            'status' => 'success',
            'code' => 200,
            'message' => 'Programs retrieved successfully.',
            'programs' => $programs
        ],200);
    }
    public function getSubDepartmentByDepartment(Request $request, $id)
    {
        $sub_department = SubDepartment::where('department_id', $id)->get();
        $department = Department::find($id);

        if (!$department) {
            return response()->json([
                'status' => 'error',
                'code' => 404,
                'message' => 'Department not found.'
            ], 404);

        $user = $request->user();

        if($user->id !== $department->department_head_id){
            return response()->json([
                'status' => 'error',
                'code' => 403,
                'message' => 'You are not authorized to access this department programs.'
            ], 403);

        }
        }

        return response()->json([
            'status' => 'success',
            'code' => 200,
            'message' => 'SubDepartments retrieved successfully.',
            'sub_departments' => $sub_department
        ],200);
    }




}
