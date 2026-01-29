<?php

namespace App\Http\Controllers\cv;

use App\Http\Controllers\Controller;
use App\Models\cv\Cv;
use App\Models\cv\CvSkill;
use Illuminate\Http\Request;

class CvSkillController extends Controller
{
    public function addNewSkillToCv(Request $request, $cvId)
    {

        $cv = Cv::find($cvId);

        if (!$cv) {
            return response()->json([
                'status' => false,
                'message' => 'CV not found',
            ], 404);
        }

        $validated = $request->validate([
            // skills
            'skills' => 'nullable|array',
            'skills.*.skill_name' => 'required|string|max:255',
            'skills.*.proficiency_level' => 'nullable|in:' . implode(',', CvSkill::$proficiencyLevels),
        ]);

        foreach ($validated['skills'] as $skill) {
            $cv->skills()->create($skill);
        }
        $cv->load(['skills' => function ($query) {
            $query->latest();
        }]);
        return response()->json([
            'status' => true,
            'code' => 201,
            'message' => 'Created new skill to Cv successfully !',
            'cv' => $cv
        ], 201);
    }

    public function deleteSkills(Request $request, $cvId)
    {
        $cv = Cv::find($cvId);

        if (!$cv) {
            return response()->json([
                'status' => false,
                'message' => 'CV not found',
            ], 404);
        }

        $validated = $request->validate([
            'skill_ids' => 'required|array|min:1',
            'skill_ids.*' => 'required|integer|exists:cv_skills,id',
        ]);

        // Check ownership: skills must belong to this CV
        $skillsCount = CvSkill::where('cv_id', $cvId)
            ->whereIn('id', $validated['skill_ids'])
            ->count();

        if ($skillsCount !== count($validated['skill_ids'])) {
            return response()->json([
                'status' => false,
                'code' => 403,
                'message' => 'One or more skills do not belong to this CV',
            ], 403);
        }

        // Safe delete
        $deleted = CvSkill::where('cv_id', $cvId)
            ->whereIn('id', $validated['skill_ids'])
            ->delete();

        return response()->json([
            'status' => true,
            'code' => 200,
            'message' => "$deleted skill(s) deleted successfully!",
            'cv' => $cv->load([
                'skills' => fn($q) => $q->latest()
            ]),
        ]);
    }
}
