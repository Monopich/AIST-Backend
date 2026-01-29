<?php

namespace App\Http\Controllers\cv;

use App\Http\Controllers\Controller;
use App\Models\cv\Cv;
use App\Models\cv\CvWork;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CvWorkController extends Controller
{
    public function addNewWorks(Request $request, $cvId)
    {

        $cv = Cv::with(['works'])
            ->find($cvId);

        if (!$cv) {
            return response()->json([
                'status' => false,
                'message' => 'CV not found',
            ], 404);
        }
        $validated = $request->validate([
            'works' => 'nullable|array',
            'works.*.company_name' => 'required|string|max:255',
            'works.*.position' => 'required|string|max:255',
            'works.*.location' => 'nullable|string|max:255',
            'works.*.start_date' => 'required|date',
            'works.*.end_date' => 'nullable|date|after_or_equal:works.*.start_date',
            'works.*.experience_level' => ['nullable', Rule::in(CvWork::$experienceLevels)],
            'works.*.is_current' => 'boolean',
            'works.*.description' => 'nullable|string',
        ]);

        // $newWorks = 
        foreach ($validated['works'] as $work) {
            $cv->works()->create($work);
        }

        return response()->json([
            'status' => true,
            'code' => 201,
            'message' => 'Created new work or experience successfully !',
            'cv' => $cv
        ], 201);
    }
    public function deleteWorks(Request $request, $cvId)
    {
        $cv = Cv::with(['works'])->find($cvId);

        if (!$cv) {
            return response()->json([
                'status' => false,
                'message' => 'CV not found',
            ], 404);
        }

        // Validate the request: an array of work IDs to delete
        $validated = $request->validate([
            'work_ids' => 'required|array',
            'work_ids.*' => 'required|integer|exists:cv_works,id',
        ]);

        // $deletedCount = 0;

         $count = $cv->works()
            ->whereIn('id', $validated['work_ids'])
            ->count();

        if ($count !== count($validated['work_ids'])) {
            return response()->json([
                'status' => false,
                'code' => 403,
                'message' => 'One or more languages do not belong to this CV',
            ], 403);
        }


        $deleted = $cv->works()
            ->whereIn('id', $validated['work_ids'])
            ->delete();

        return response()->json([
            'status' => true,
            'code' => 200,
            'message' => "$deleted work(s) deleted successfully!",
            'cv' => $cv->load([
                'works' => fn($q) => $q->latest()
            ]),
        ]);
    
    }

   
}
