<?php

namespace App\Http\Controllers\mission;

use App\Http\Controllers\Controller;
use App\Models\Mission;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MissionController extends Controller
{
    public function makeMission(Request $request)
    {
        $validated = $request->validate([
            'mission_title' => 'required|string|max:25',
            'mission_type'  => 'required|string|in:' . implode(',', Mission::$mission_types),
            'status'        => 'nullable|string|in:' . implode(',', Mission::$statuses),
            'description' => 'nullable|string',
            'assigned_date' => 'required|date_format:Y-m-d',
            'due_date' => 'required|date_format:Y-m-d|after_or_equal:assigned_date',
            'budget' => 'nullable|numeric|min:0',
            'location' => 'nullable|string|max:120',
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id',
        ]);

        $allowedRoles = ['Staff', 'Head Department'];

        DB::beginTransaction();
        try {
            $users = User::whereIn('id', $validated['user_ids'])->get();
            $conflictUsers = collect();
            $invalidRoleUsers = collect();

            foreach ($users as $user) {
                // Check role first
                if (!$user->hasAnyRole($allowedRoles)) {
                    $invalidRoleUsers->push($user->id);
                    continue; // Skip conflict check if role is invalid
                }

                // Check overlapping missions (excluding cancelled missions)
                $conflictingMission = $user->missions()
                    ->where('status', '!=', 'cancelled') // Exclude cancelled missions
                    ->where(function ($query) use ($validated) {
                        $query->whereBetween('assigned_date', [$validated['assigned_date'], $validated['due_date']])
                            ->orWhereBetween('due_date', [$validated['assigned_date'], $validated['due_date']])
                            ->orWhere(function ($q) use ($validated) {
                                $q->where('assigned_date', '<=', $validated['assigned_date'])
                                    ->where('due_date', '>=', $validated['due_date']);
                            });
                    })
                    ->first();

                if ($conflictingMission) {
                    $conflictUsers->push([
                        'status' => 'conflict',
                        'code' => 422,
                        'user_id' => $user->id,
                        'user_name' => $user->name ?? null,
                        'conflicting_mission' => [
                            'mission_id' => $conflictingMission->id,
                            'mission_title' => $conflictingMission->mission_title,
                            'status' => $conflictingMission->status,
                            'assigned_date' => $conflictingMission->assigned_date,
                            'due_date' => $conflictingMission->due_date,
                        ]
                    ]);
                }
            }

            // Check if there are any invalid users
            if ($invalidRoleUsers->isNotEmpty() || $conflictUsers->isNotEmpty()) {
                $errorData = [
                    'status' => 'error',
                    'message' => 'Unable to assign mission to some users',
                ];

                if ($invalidRoleUsers->isNotEmpty()) {
                    $errorData['invalid_role_users'] = $invalidRoleUsers->values();
                    $errorData['allowed_roles'] = $allowedRoles;
                }

                if ($conflictUsers->isNotEmpty()) {
                    $errorData['conflict_users'] = $conflictUsers->values();
                    $errorData['conflict_message'] = 'These users have overlapping mission dates';
                }

                return response()->json($errorData, 422);
            }

            // Create mission
            $mission = Mission::create([
                'mission_title' => $validated['mission_title'],
                'mission_type' => $validated['mission_type'],
                'status'        => $request->status ?? 'pending',
                'description' => $validated['description'] ?? null,
                'assigned_date' => $validated['assigned_date'],
                'due_date' => $validated['due_date'],
                'budget' => $validated['budget'] ?? 0,
                'location' => $validated['location'] ?? null,
            ]);

            // Attach users via pivot
            $mission->users()->sync($validated['user_ids']);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'code' => 201,
                'message' => 'Mission created successfully',
                'mission' => $mission->load('users')
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'code' => 500,
                'message' => 'Failed to create mission',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // public function getAllMissions(Request $request)
    // {
    //     $date = $request->query('date');
    //     $status = $request->query('status');
    //     $search = $request->query('search'); // by mission_title, type, location, user name
    //     $perPage = $request->query('per_page', 10); // default 10




    //     try {
    //         $query = Mission::with('users:id,name,email'); // Eager load users with only id and name

    //         // Filter by date (missions that include this date)
    //         if ($date) {
    //             $query->where(function ($q) use ($date) {
    //                 $q->where('assigned_date', '<=', $date)
    //                     ->where('due_date', '>=', $date);
    //             });
    //         }

    //         // Filter by status
    //         if ($status) {
    //             $query->where('status', $status);
    //         }

    //         // Search by mission_title, type, location, or user name
    //         if ($search) {
    //             $query->where(function ($q) use ($search) {
    //                 $q->where('mission_title', 'like', '%' . $search . '%')
    //                     ->orWhere('mission_type', 'like', '%' . $search . '%')
    //                     ->orWhere('location', 'like', '%' . $search . '%')
    //                     ->orWhereHas('users', function ($userQuery) use ($search) {
    //                         $userQuery->where('name', 'like', '%' . $search . '%');
    //                     });
    //             });
    //         }

    //         // Get missions ordered by assigned_date (newest first)
    //         // Get paginated missions ordered by assigned_date (newest first)

    //         $missions = $query->orderBy('assigned_date', 'desc')->paginate($perPage);
    //         $totalMissions = Mission::count();

    //         $pendingCount = Mission::where('status', 'pending')->count();
    //         $inProgressCount = Mission::where('status', 'in_progress')->count();
    //         $completedCount = Mission::where('status', 'completed')->count();
    //         $cancelledCount = Mission::where('status', 'cancelled')->count();
    //         $overDue = Mission::where('status', 'overdue')->count();

    //         return response()->json([
    //             'status' => 'success',
    //             'code' => 200,
    //             'message' => 'Missions retrieved successfully',
    //             'total' => $totalMissions,
    //             'total_per_status' => [
    //                 'pending' => $pendingCount,
    //                 'in_progress' => $inProgressCount,
    //                 'overdue' => $overDue,
    //                 'completed' => $completedCount,
    //                 'cancelled' => $cancelledCount,
    //             ],
    //             // 'missions' => $missions,
    //             'pagination' => [
    //                 'current_page' => $missions->currentPage(),
    //                 'per_page' => $missions->perPage(),
    //                 'last_page' => $missions->lastPage(),
    //             ],

    //             'missions' => $missions->items(),

    //         ], 200);
    //     } catch (\Throwable $e) {
    //         return response()->json([
    //             'status' => 'error',
    //             'code' => 500,
    //             'message' => 'Failed to retrieve missions',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    public function getAllMissions(Request $request)
    {
        $date   = $request->query('date');
        $status = $request->query('status');
        $search = $request->query('search');
        $perPage = $request->query('per_page', 10);

        try {
            $query = Mission::with('users:id,name,email');

            // Filter by date (missions that include this date)
            if ($date) {
                $query->where(function ($q) use ($date) {
                    $q->where('assigned_date', '<=', $date)
                    ->where('due_date', '>=', $date);
                });
            }

            // Filter by status
            if ($status) {
                $query->where('status', $status);
            }

            // Search
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('mission_title', 'like', "%{$search}%")
                    ->orWhere('mission_type', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhereHas('users', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%");
                    });
                });
            }

            // ✅ paginate
            $missions = (clone $query)->orderBy('assigned_date', 'desc')->paginate($perPage);

            // ✅ status counts from SAME filtered query
            $base = (clone $query);

            $pendingCount    = (clone $base)->where('status', 'pending')->count();
            $inProgressCount = (clone $base)->where('status', 'in_progress')->count();
            $completedCount  = (clone $base)->where('status', 'completed')->count();
            $cancelledCount  = (clone $base)->where('status', 'cancelled')->count();

            // ✅ overdue dynamic (same as frontend logic)
            $today = now()->toDateString();
            $overdueCount = (clone $base)
                ->whereIn('status', ['pending', 'in_progress'])
                ->where('due_date', '<', $today)
                ->count();

            return response()->json([
                'status' => 'success',
                'code' => 200,
                'message' => 'Missions retrieved successfully',

                // ✅ total AFTER filters (matches pagination total)
                'total' => $missions->total(),

                // ✅ global counts AFTER filters (matches table scope)
                'total_per_status' => [
                    'pending' => $pendingCount,
                    'in_progress' => $inProgressCount,
                    'overdue' => $overdueCount,
                    'completed' => $completedCount,
                    'cancelled' => $cancelledCount,
                ],

                // ✅ pagination includes total
                'pagination' => [
                    'current_page' => $missions->currentPage(),
                    'per_page' => $missions->perPage(),
                    'last_page' => $missions->lastPage(),
                    'total' => $missions->total(),
                ],

                'missions' => $missions->items(),
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'code' => 500,
                'message' => 'Failed to retrieve missions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function cancelMission(Request $request, $missionId)
    {

        $mission = Mission::find($missionId);

        if (!$mission) {
            return response()->json([
                'status' => 'error',
                'code' => 404,
                'message' => 'Mission not found',
            ], 404);
        }

        if ($mission->status === 'pending') {
            $mission->status = 'cancelled';
            $mission->save();
        } else {
            return response()->json([
                'status' => 'error',
                'code' => 422,
                'message' => 'Only missions with status pending or in_progress can be cancelled',
                'current_status' => $mission->status
            ], 422);
        }
        return response()->json([
            'status' => 'success',
            'code' => 200,
            'message' => 'Mission cancelled successfully',
            'mission' => $mission
        ], 200);
    }

    public function updateMission(Request $request, $missionId)
    {
        $validated = $request->validate([
            'mission_title' => 'required|string|max:25',
            'mission_type'  => 'required|string|in:' . implode(',', Mission::$mission_types),
            'status'        => 'nullable|string|in:' . implode(',', Mission::$statuses),
            'description' => 'nullable|string',
            'assigned_date' => 'required|date_format:Y-m-d',
            'due_date' => 'required|date_format:Y-m-d|after_or_equal:assigned_date',
            'budget' => 'nullable|numeric|min:0',
            'location' => 'nullable|string|max:120',
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id',
        ]);

        $allowedRoles = ['Staff', 'Head Department'];

        DB::beginTransaction();
        try {
            $mission = Mission::findOrFail($missionId);

            // Check if mission is cancelled or completed
            if (in_array($mission->status, ['cancelled', 'completed'])) {
                return response()->json([
                    'status' => 'error',
                    'code' => 422,
                    'message' => "Cannot update mission with status '{$mission->status}'",
                    'current_status' => $mission->status
                ], 422);
            }

            $users = User::whereIn('id', $validated['user_ids'])->get();
            $conflictUsers = collect();
            $invalidRoleUsers = collect();

            foreach ($users as $user) {
                // Check role first
                if (!$user->hasAnyRole($allowedRoles)) {
                    $invalidRoleUsers->push($user->id);
                    continue; // Skip conflict check if role is invalid
                }

                // Check overlapping missions (excluding cancelled missions and current mission)
                $conflictingMission = $user->missions()
                    ->where('missions.id', '!=', $missionId) // Specify table name for id
                    ->where('missions.status', '!=', 'cancelled') // Specify table name for status
                    ->where(function ($query) use ($validated) {
                        $query->whereBetween('missions.assigned_date', [$validated['assigned_date'], $validated['due_date']])
                            ->orWhereBetween('missions.due_date', [$validated['assigned_date'], $validated['due_date']])
                            ->orWhere(function ($q) use ($validated) {
                                $q->where('missions.assigned_date', '<=', $validated['assigned_date'])
                                    ->where('missions.due_date', '>=', $validated['due_date']);
                            });
                    })
                    ->first();

                if ($conflictingMission) {
                    $conflictUsers->push([
                        'user_id' => $user->id,
                        'user_name' => $user->name ?? null,
                        'conflicting_mission' => [
                            'mission_id' => $conflictingMission->id,
                            'mission_title' => $conflictingMission->mission_title,
                            'status' => $conflictingMission->status,
                            'assigned_date' => $conflictingMission->assigned_date,
                            'due_date' => $conflictingMission->due_date,
                        ]
                    ]);
                }
            }

            // Check if there are any invalid users
            if ($invalidRoleUsers->isNotEmpty() || $conflictUsers->isNotEmpty()) {
                $errorData = [
                    'status' => 'error',
                    'message' => 'Unable to assign mission to some users',
                ];

                if ($invalidRoleUsers->isNotEmpty()) {
                    $errorData['invalid_role_users'] = $invalidRoleUsers->values();
                    $errorData['allowed_roles'] = $allowedRoles;
                }

                if ($conflictUsers->isNotEmpty()) {
                    $errorData['conflict_users'] = $conflictUsers->values();
                    $errorData['conflict_message'] = 'These users have overlapping mission dates';
                }

                return response()->json($errorData, 422);
            }

            // Update mission
            $mission->update([
                'mission_title' => $validated['mission_title'],
                'mission_type' => $validated['mission_type'],
                'status'        => $validated['status'] ?? $mission->status,
                'description' => $validated['description'] ?? null,
                'assigned_date' => $validated['assigned_date'],
                'due_date' => $validated['due_date'],
                'budget' => $validated['budget'] ?? 0,
                'location' => $validated['location'] ?? null,
            ]);

            // Sync users via pivot
            $mission->users()->sync($validated['user_ids']);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'code' => 200,
                'message' => 'Mission updated successfully',
                'mission' => $mission->load('users')
            ], 200);
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'code' => 404,
                'message' => 'Mission not found'
            ], 404);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'code' => 500,
                'message' => 'Failed to update mission',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function markAsComplete($missionId)
    {

        $mission = Mission::find($missionId);

        if (!$mission) {
            return response()->json([
                'status' => 'error',
                'code' => 404,
                'message' => 'Mission not found',
            ], 404);
        }

        if ($mission->status === 'in_progress' || $mission->status === 'overdue') {
            $mission->status = 'completed';
            $mission->save();
        } elseif ($mission->status === 'completed') {
            return response()->json([
                'status' => 'conflict',
                'code' => 422,
                'message' => 'Mission is already completed',
            ], 422);
        } else {
            return response()->json([
                'status' => 'error',
                'code' => 422,
                'message' => 'Only missions with status overdue or in_progress can be completed',
                'current_status' => $mission->status
            ], 422);
        }
        return response()->json([
            'status' => 'success',
            'code' => 200,
            'message' => 'Mission marked as completed successfully',
            'mission' => $mission
        ], 200);
    }

    public function getMissionDetails($missionId)
    {
        try {
            $mission = Mission::with(['users.userDetail.department'])->findOrFail($missionId);

            return response()->json([
                'status' => 'success',
                'code' => 200,
                'message' => 'Mission details retrieved successfully',
                'mission' => [
                    'id' => $mission->id,
                    'mission_title' => $mission->mission_title,
                    'mission_type' => $mission->mission_type,
                    'status' => $mission->status,
                    'description' => $mission->description,
                    'assigned_date' => $mission->assigned_date,
                    'due_date' => $mission->due_date,
                    'budget' => $mission->budget,
                    'location' => $mission->location,
                    'created_at' => $mission->created_at,
                    'updated_at' => $mission->updated_at,
                    'users' => $mission->users->map(function ($user) {
                        return [
                            'id' => $user->id,
                            'name' => $user->name,
                            'email' => $user->email,
                            'department_name' => $user->userDetail && $user->userDetail->department
                                ? $user->userDetail->department->department_name
                                : null,
                            'department_id' => $user->userDetail && $user->userDetail->department
                                ? $user->userDetail->department->id
                                : null,
                        ];
                    })
                ]
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'code' => 404,
                'message' => 'Mission not found'
            ], 404);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'code' => 500,
                'message' => 'Failed to retrieve mission details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function GetPersonalMissions(Request $request)
    {
        $user = $request->user();

        $date   = $request->query('date');
        $status = $request->query('status');
        $perPage = $request->query('per_page', 14);

        try {

            // Base query
            $query = $user->missions()
                ->with('users:id,name,email');

            // Filter by date
            if ($date) {
                $query->whereDate('assigned_date', '<=', $date)
                    ->whereDate('due_date', '>=', $date);
            }

            // Filter by status
            if ($status) {
                $query->where('status', $status);
            }

            // Pagination
            $missions = $query
                ->orderByDesc('assigned_date')
                ->paginate($perPage);

            // Total missions (all)
            $totalMissions = $user->missions()->count();

            // Status summary (all missions)
            $totalBaseStatus = [
                'pending'     => $user->missions()->where('status', 'pending')->count(),
                'in_progress' => $user->missions()->where('status', 'in_progress')->count(),
                'completed'   => $user->missions()->where('status', 'completed')->count(),
                'cancelled'   => $user->missions()->where('status', 'cancelled')->count(),
                'overdue'     => $user->missions()->where('status', 'overdue')->count(),
            ];

            return response()->json([
                'status' => 'success',
                'code' => 200,
                'message' => 'Personal missions retrieved successfully',

                // pagination data
                'total_missions' => $totalMissions,
                'total_base_status' => $totalBaseStatus,

                'pagination' => [
                    'current_page' => $missions->currentPage(),
                    'per_page' => $missions->perPage(),
                    'last_page' => $missions->lastPage(),
                ],

                'missions' => $missions->items(),
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'code' => 500,
                'message' => 'Failed to retrieve personal missions',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

     public function selectUsersForMission(Request $request)
    {
        $search = $request->query('search');
        $roles  = ['Staff', 'Head Department'];

        $users = User::query()
            ->select('id', 'name', 'email')
            ->with('roles:id,role_key','userDetail')
            ->whereHas('roles', function ($q) {
                $q->whereIn('role_key', ['Staff', 'Head Department']);
            })
            ->when($search, function ($query) use ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
            })
            ->orderBy('name')
            ->get()
            ->map(function ($user) {
                $roleName = $user->roles->pluck('role_key')->join(', ');
                return [
                    'id' => $user->id,
                    'label' => "{$user->name} ({$roleName})",
                    'name' => $user->name,
                    "email" => $user->email,
                    'from_department' => $user->userDetail->department ? $user->userDetail->department->department_name : null,
                    // 'roles' => $user->roles->pluck('role_key'),
                ];
            });


        return response()->json([
            'status' => 'success',
            'message' => 'Users retrieved successfully for mission selection.',
            'users' => $users
        ], 200);
    }
}
