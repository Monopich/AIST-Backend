<?php

namespace App\Http\Controllers\cv;

use App\Http\Controllers\Controller;
use App\Models\cv\Cv;
use App\Models\cv\CvEducation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CvEducationController extends Controller
{
    public function addEducationToCv(Request $request, $cvId)
    {

        $cv = Cv::with('educations')->find($cvId);

        if (!$cv) {
            return response()->json([
                'status' => false,
                'message' => 'CV not found',
            ], 404);
        }

        $validatedData = $request->validate([
            'educations' => 'nullable|array',
            'educations.*.institution_name' => 'required|string|max:255',
            'educations.*.degree' => ['nullable', Rule::in(CvEducation::$degreeLevels)],
            'educations.*.location' => 'nullable|string|max:255',
            'educations.*.field_of_study' => 'nullable|string|max:255',
            'educations.*.start_date' => 'nullable|date',
            'educations.*.end_date' => 'nullable|date|after_or_equal:educations.*.start_date',
            'educations.*.is_current' => 'boolean',
            'educations.*.description' => 'nullable|string',
        ]);

        foreach ($validatedData['educations'] as $data) {
            $cv->educations()->create($data);
        }

        return response()->json([
            'status' => true,
            'code' => 201,
            'message' => 'Added educations successfully !',
            'educations' => $cv
        ], 201);
    }
    public function deleteEducations(Request $request, $cvId)
    {
        $cv = Cv::find($cvId);

        if (!$cv) {
            return response()->json([
                'status' => false,
                'message' => 'CV not found',
            ], 404);
        }

        $validated = $request->validate([
            'education_ids' => 'required|array|min:1',
            'education_ids.*' => 'required|integer|exists:cv_educations,id',
        ]);

        //  Check ownership: all educations must belong to this CV
        $count = $cv->educations()
            ->whereIn('id', $validated['education_ids'])
            ->count();

        if ($count !== count($validated['education_ids'])) {
            return response()->json([
                'status' => false,
                'code' => 403,
                'message' => 'One or more educations do not belong to this CV',
            ], 403);
        }

        //Safe bulk delete
        $deleted = $cv->educations()
            ->whereIn('id', $validated['education_ids'])
            ->delete();

        return response()->json([
            'status' => true,
            'code' => 200,
            'message' => "$deleted education(s) deleted successfully!",
            'cv' => $cv->load([
                'educations' => fn($q) => $q->latest()
            ]),
        ]);
    }
}
