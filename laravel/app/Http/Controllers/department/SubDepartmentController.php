<?php

namespace App\Http\Controllers\department;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\SubDepartment;
use App\Models\User;
use Illuminate\Http\Request;

class SubDepartmentController extends Controller
{

    public function createNewSubDepartment(Request $request)
    {

        $validated = $request->validate([
            'name' => 'required|string',
            'department_id' => 'required|exists:departments,id',
            'description' => 'nullable|required|string'
        ]);

        $exists = SubDepartment::where('name', $validated['name'])
            ->where('department_id', $validated['department_id'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Sub-department with this name already exists in the department.'
            ], 409); // 409 Conflict
        }


        $subDepartment = SubDepartment::create([
            'name' => $validated['name'],
            'department_id' => $validated['department_id'],
            'description' => $validated['description'] ?? null,
        ]);


        return response()->json([
            'message' => 'Sub Department created successful',
            'sub_department' => $subDepartment
        ]);


    }

    public function updateSubDepartment(Request $request, $subDepartment_id)
    {

        $subDepartment = SubDepartment::find($subDepartment_id);

        if (!$subDepartment) {
            return response()->json([
                'message' => 'Sub-Department not found .'
            ]);

        }

        $validated = $request->validate([
            'name' => 'required|string',
            'description' => 'nullable|required|string',
            'department_id' => 'required|exists:departments,id'
        ]);

        $existDepartment = Department::find($validated['department_id']);

        if (!$existDepartment) {
            return response()->json([
                'message' => 'Department not found.'
            ], 404);
        }


        $subDepartment->update([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'department_id' => $validated['department_id']
        ]);


        return response()->json([
            'message' => 'Sub Department updated successful',
            'sub_department' => $subDepartment
        ]);
    }

    public function deleteSubDepartment($subDepartment_id)
    {

        $subDepartment = SubDepartment::find($subDepartment_id);

        if (!$subDepartment) {
            return response()->json(
                [
                    'message' => 'Sub-Department not found .'
                ],
                404
            );
        }

        $subDepartment->delete();

        return response()->json([
            'message' => 'Sub-Department deleted successful .'
        ]);

    }

    public function getAllStudentOfSubDepartment(Request $request)
    {
        $subDepartment_id = $request->query('sub_department_id');

        $students = User::whereHas('roles', function ($query) {
            $query->where('role_key', 'Student');
        })->whereHas('userDetail', function ($query) use ($subDepartment_id) {
            $query->where('sub_department_id', $subDepartment_id);
        })
            ->with(['userDetail'])
            ->paginate(14);

        if ($students->isEmpty()) {
            return response()->json(
                [
                    'message' => 'Not available students in this Sub-Department.'
                ]
            );
        }
        return response([
            'message' => 'List all students in sub-department.',
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

 public function getAllSubDepartment(Request $request)
{
    $departmentId = $request->query('department_id');

    $query = SubDepartment::with('department:id,department_name');

    // Apply filter if department_id is given
    if (!empty($departmentId)) {
        $query->where('department_id', $departmentId);
    }

    $subDepartments = $query->get();

    if ($subDepartments->isEmpty()) {
        return response()->json([
            'message' => 'No sub-departments found.',
        ]);
    }

    return response()->json([
        'message' => 'List all sub departments successful.',
        'sub_department' => $subDepartments,
    ]);
}




}
