<?php

namespace App\Http\Controllers\role;

use App\Http\Controllers\Controller;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function assignToUser(Request $request){

        // Validate input
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role_key' => 'required|string'
        ]);

        try {
            $user = User::findOrFail($validated['user_id']);
            $user->assignRole($validated['role_key']);

            return response()->json([
                'message' => "Role '{$validated['role_key']}' assigned to user successfully.",
                'user' => $user->only(['id', 'email']),
                'roles' => $user->roles->pluck('role_key')
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function removeRole(Request $request)
    {
    $validated = $request->validate([
        'user_id' => 'required|exists:users,id',
        'role_key' => 'required|string'
    ]);

    try {
        $user = User::findOrFail($validated['user_id']);
        $user->removeRole($validated['role_key']);

        return response()->json([
            'message' => "Role '{$validated['role_key']}' removed from user successfully.",
            'user' => $user->only(['id', 'email']),
            'roles' => $user->roles->pluck('role_key')
        ]);
    } catch (Exception $e) {
        return response()->json([
            'error' => $e->getMessage()
        ], 400);
    }
}

}
