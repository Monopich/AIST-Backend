<?php

namespace App\Http\Controllers\group;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\GroupHistory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class GroupController extends Controller
{

    public function createNewGroup(Request $request)
    {

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'semester_id' => 'required|exists:semesters,id',
            'program_id' => 'required|exists:programs,id',
            'department_id' => 'required|exists:departments,id',
            'sub_department_id' => 'nullable|exists:sub_departments,id',
            'description' => 'nullable|string|max:500',
        ]);

        // Check if group already exists
        $exists = Group::where('name', $validated['name'])
            ->where('semester_id', $validated['semester_id'])
            ->where('program_id', $validated['program_id'])
            ->exists();
        
        if ($exists) {
            return response()->json([
                'message' => 'This group already exists for the selected semester.'
            ], 409);
        }
        
        // Create new group with default description if empty
        $group = Group::create([
            'name' => $validated['name'],
            'semester_id' => $validated['semester_id'],
            'program_id' => $validated['program_id'],
            'department_id' => $validated['department_id'],
            'sub_department_id' => $validated['sub_department_id'] ?? null,
            'description' => !empty($validated['description']) ? $validated['description'] : 'No description',
        ]);

        return response()->json([
            'message' => 'New group created successfully.',
            'group' => $group
        ], 201);
    }

    public function updateGroup(Request $request, $groupId)
    {

        $group = Group::findOrFail($groupId);
        // Convert empty string to null
        $request->merge([
            'sub_department_id' => $request->sub_department_id ?: null,
        ]);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'semester_id' => 'required|exists:semesters,id',
            'program_id' => 'required|exists:programs,id',
            'department_id' => 'required|exists:departments,id',
            'sub_department_id' => 'nullable|exists:sub_departments,id',
            'description' => 'nullable|string|max:500',
        ]);

        // Update group
        $group->update($validated);

        return response()->json([
            'message' => 'Group updated successfully.',
            'group' => $group
        ], 200);
    }

    public function deleteGroup($groupId)
    {

        $group = Group::findOrFail($groupId);

        $group->delete();

        return response()->json([
            'message' => 'Group deleted successfully.'
        ], 200);
    }

    public function getAllGroups()
    {
        $perPage = request()->input('per_page', 14);
        $groups = Group::with(['semester:id,semester_number', 'subDepartment:id,name', 'program:id,program_name,degree_level'])
            ->paginate($perPage);

        return response()->json([
            'message' => 'Groups retrieved successfully.',
            'groups' => $groups
        ], 200);
    }

    public function assignToUser(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'group_id' => 'required|exists:groups,id',
        ]);

        $group = Group::findOrFail($validated['group_id']);
        $user = User::findOrFail($validated['user_id']);

        // check user is student
        if (!$user->hasRole('Student')) {
            return response()->json([
                'message' => 'User is not a student.'
            ], 400);
        }

        $existGroup = $user->groups()
            ->where('semester_id', $group->semester_id)
            ->where('program_id', $group->program_id)
            ->first();

        if ($existGroup) {
            return response()->json([
                'message' => "Student is already in another group in the semester of program.",
            ], 409);
        }

        // Check if user already exists in the group
        if ($group->students()->where('user_id', $user->id)->exists()) {
            return response()->json([
                'message' => 'User already exists in this group.'
            ], 409);
        }

        // Attach user to group
        $group->students()->attach($user->id);


        return response()->json([
            'message' => 'User assigned to group successfully.',
            'group' => $group,
            'user' => $user
        ], 200);
    }

    public function assignMultipleToGroup(Request $request)
    {
        $validated = $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'group_id' => 'required|exists:groups,id',
        ]);

        $group = Group::findOrFail($validated['group_id']);
        $userIds = $validated['user_ids'];

        // Load all users at once
        $users = User::whereIn('id', $userIds)->get();

        $nonStudentIds = [];
        $alreadyInGroupIds = [];
        $conflictIds = [];

        foreach ($users as $user) {
            // 1. Check role
            if (!$user->hasRole('Student')) {
                $nonStudentIds[] = $user->id;
                continue;
            }

            // 2. Check if already in THIS group
            if ($group->students()->where('users.id', $user->id)->exists()) {
                $alreadyInGroupIds[] = $user->id;
                continue;
            }

            // 3. Check if already in ANOTHER group in the same semester & program
            $existingGroup = $user->groups()
                ->where('semester_id', $group->semester_id)
                ->where('program_id', $group->program_id)
                ->first();

            if ($existingGroup) {
                $conflictIds[] = [
                    'user_id' => $user->id,
                    'existing_group' => $existingGroup->name,
                ];
            }
        }

        // Handle errors
        $errors = [];
        if (!empty($nonStudentIds)) {
            $errors[] = 'The following users are not students: ' . implode(', ', $nonStudentIds);
        }
        if (!empty($alreadyInGroupIds)) {
            $errors[] = 'The following users are already in this group: ' . implode(', ', $alreadyInGroupIds);
        }
        if (!empty($conflictIds)) {
            foreach ($conflictIds as $conflict) {
                $errors[] = "User {$conflict['user_id']} is already in another group ({$conflict['existing_group']}) within the same semester and program.";
            }
        }

        if (!empty($errors)) {
            return response()->json([
                'message' => implode(' ', $errors),
            ], 422);
        }

        // Attach users
        $group->students()->attach($userIds);

        return response()->json([
            'message' => 'Multiple users assigned to group successfully.',
            'group' => $group,
            'user_ids' => $userIds
        ], 200);
    }


    public function removeMultipleFromGroup(Request $request)
    {
        $validated = $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'group_id' => 'required|exists:groups,id',
        ]);

        $group = Group::findOrFail($validated['group_id']);
        $userIds = $validated['user_ids'];

        // Get only IDs that are actually in the group
        $existingInGroupIds = $group->students()
            ->whereIn('user_id', $userIds)
            ->pluck('user_id')
            ->toArray();

        // Remove only the ones that exist in the group
        if (!empty($existingInGroupIds)) {
            $group->students()->detach($existingInGroupIds);
        }
        if ($existingInGroupIds === []) {
            return response()->json([
                'message' => 'No users found in this group to remove.',
            ], 404);
        }

        return response()->json([
            'message' => 'Users removed from group successfully.',
            'removed_user_ids' => $existingInGroupIds
        ], 200);
    }

    public function removeStudentFromGroup(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'group_id' => 'required|exists:groups,id',
        ]);

        $group = Group::findOrFail($validated['group_id']);
        $user = User::findOrFail($validated['user_id']);

        // Check if user exists in the group
        if (!$group->students()->where('user_id', $user->id)->exists()) {
            return response()->json([
                'message' => 'User not found in this group.'
            ], 404);
        }

        // Detach user from group
        $group->students()->detach($user->id);

        return response()->json([
            'message' => 'User removed from group successfully.',
            'group' => $group,
            'user' => $user
        ], 200);
    }

    public function getGroupById($groupId)
    {
        $group = Group::with([
            'semester:id,semester_number',
            'students' => function ($query) {
                $query->whereHas('roles', function ($query) {
                    $query->where('role_key', 'Student');
                })->with('userDetail'); // eager load userDetail
            },
            'subDepartment:id,name',
            'program:id,program_name,degree_level',
        ])->find($groupId);
        if (!$group) {
            return response()->json([
                'message' => 'Group not found'
            ], 404);
        }

        // Map student details
        $groupData = $group->toArray();

        $groupData['students'] = collect($group->students)->map(function ($student) {
            return [
                'user_id' => $student->id,
                'name' => $student->name,
                'email' => $student->email,
                'id_card' => $student->userDetail->id_card ?? null,
                'gender' => $student->userDetail->gender ?? null,
                'date_of_birth' => $student->userDetail->date_of_birth ?? null,
                'latin_name' => $student->userDetail->latin_name ?? null,
                'khmer_name' => $student->userDetail->khmer_name ?? null,
            ];
        });

        return response()->json([
            'group' => $groupData
        ], 200);

    }

    public function filterGroupsByProgram(Request $request, $programId)
    {
        $perPage = $request->query('per_page', 14);

        $groups = Group::with(['semester:id,semester_number', 'subDepartment:id,name', 'program:id,program_name,degree_level'])
            ->where('program_id', $programId)
            ->paginate($perPage);

        if ($groups->isEmpty()) {
            return response()->json([
                'message' => 'No groups found for this program.'
            ], 404);
        }
        return response()->json([
            'message' => 'Groups retrieved successfully.',
            'groups' => $groups
        ], 200);

    }

    public function searchGroup(Request $request)
    {
        $searchTerm = $request->query('search', '');
        $perPage = $request->query('per_page', 14);

        $groups = Group::with(['semester:id,semester_number', 'subDepartment:id,name', 'program:id,program_name,degree_level'])
            ->where('name', 'like', '%' . $searchTerm . '%')
            ->orWhereHas('students', function ($query) use ($searchTerm) {
                $query->where('name', 'like', '%' . $searchTerm . '%');
            })
            ->paginate($perPage);

        if ($groups->isEmpty()) {
            return response()->json([
                'message' => 'No groups found matching the search term.'
            ], 404);
        }

        return response()->json([
            'message' => 'Groups retrieved successfully.',
            'groups' => $groups
        ], 200);
    }

    public function filterGroupsByDepartment(Request $request, $departmentId)
    {
        $perPage = $request->query('per_page', 14);

        $groups = Group::with(['semester:id,semester_number', 'subDepartment:id,name', 'program:id,program_name,degree_level'])
            ->where('department_id', $departmentId)
            ->paginate($perPage);

        if ($groups->isEmpty()) {
            return response()->json([
                'message' => 'No groups found for this department.'
            ], 404);
        }
        return response()->json([
            'message' => 'Groups retrieved successfully.',
            'groups' => $groups
        ], 200);
    }

    public function filterGroupsBySubDepartment(Request $request, $subDepartmentId)
    {
        $perPage = $request->query('per_page', 14);

        $groups = Group::with(['semester:id,semester_number', 'subDepartment:id,name', 'program:id,program_name,degree_level'])
            ->where('sub_department_id', $subDepartmentId)
            ->paginate($perPage);

        if ($groups->isEmpty()) {
            return response()->json([
                'message' => 'No groups found for this sub-department.'
            ], 404);
        }
        return response()->json([
            'message' => 'Groups retrieved successfully.',
            'groups' => $groups
        ], 200);
    }

    public function cloneGroup(Request $request, $groupId)
    {
        $group = Group::findOrFail($groupId);

        // Create a new group to new table with the same attributes
        $newGroup = GroupHistory::create([
            'name' => $group->name,
            'group_id' => $group->id, // Link to the original group
            'semester_id' => $group->semester_id,
            'program_id' => $group->program_id,
            'department_id' => $group->department_id,
            'sub_department_id' => $group->sub_department_id,
            'description' => $group->description,
        ]);

        // Clone users from the old group to the new group
        foreach ($group->students as $student) {
            $newGroup->students()->attach($student->id);
        }

        return response()->json([
            'message' => 'Group cloned successfully.',
            'new_group' => $newGroup
        ], 201);

    }

    public function getStudentByGeneration()
    {

    }



    public function addStudentToNewGroup(Request $request)
    {
        $validated = $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',

            'group_name' => 'required|string|max:255',
            'semester_id' => 'required|exists:semesters,id',
            'program_id' => 'required|exists:programs,id',
            'department_id' => 'required|exists:departments,id',
            'sub_department_id' => 'required|exists:sub_departments,id',
            'description' => 'nullable|string|max:500',
        ]);

        // Check if group already exists for the semester + program
        $exists = Group::where('name', $validated['group_name'])
            ->where('semester_id', $validated['semester_id'])
            ->where('program_id', $validated['program_id'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'This group already exists for the selected semester of the program.'
            ], 409);
        }

        $userIds = $validated['user_ids'];

        // Load all users at once
        $users = User::whereIn('id', $userIds)->get();
        $nonStudentIds = [];

        // Check roles
        foreach ($users as $user) {
            if (!$user->hasRole('student')) {
                $nonStudentIds[] = $user->id;
            }
        }

        if (!empty($nonStudentIds)) {
            return response()->json([
                'message' => 'The following users are not students: ' . implode(', ', $nonStudentIds),
            ], 422);
        }

        // Use transaction to ensure atomicity
        DB::beginTransaction();
        try {
            // Create the group
            $group = Group::create([
                'name' => $validated['group_name'],
                'semester_id' => $validated['semester_id'],
                'program_id' => $validated['program_id'],
                'department_id' => $validated['department_id'],
                'sub_department_id' => $validated['sub_department_id'],
                'description' => $validated['description'] ?? null,
            ]);

            // Attach students to the group
            $group->students()->attach($userIds);

            $users = User::whereIn('id', $userIds)->get();

            $userNames = $users->pluck('name'); // collection of names

            DB::commit();

            return response()->json([
                'message' => 'New group created and students added successfully.',
                'group' => $group,
                'user_ids' => $userIds,
                'users' => $users
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create group: ' . $e->getMessage()
            ], 500);
        }
    }
}
