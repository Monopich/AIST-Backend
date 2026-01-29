<?php

namespace App\Http\Controllers\cv;

use App\Http\Controllers\Controller;
use App\Models\cv\Cv;
use App\Models\cv\CvLanguage;
use Illuminate\Http\Request;

class CvLanguageController extends Controller
{
    public function addNewLanguages(Request $request, $cvId)
    {

        $validated = $request->validate([
            'languages' => 'nullable|array',
            'languages.*.language_name' => 'required|string|max:255',
            'languages.*.proficiency_level' => 'nullable|in:' . implode(',', CvLanguage::$proficiencyLevels),
        ]);

        $cv = Cv::with('languages')->find($cvId);
        if (!$cv) {
            return response()->json(
                [
                    'status' => false,
                    'code' => 404,
                    'message' => 'Cv not found ! '
                ]
            );
        }

        foreach ($validated['languages'] as $language) {
            $cv->languages()->create($language);
        };
        return response()->json([
            'status' => true,
            'code' => 201,
            'message' => "Added new Language(s) to the cv successful !",
            'languages' => $cv
        ], 201);
    }

    public function deleteLanguageFromCv(Request $request, $cvId)
    {
        $cv = Cv::find($cvId);

        if (!$cv) {
            return response()->json([
                'status' => false,
                'message' => 'CV not found',
            ], 404);
        }

        $validated = $request->validate([
            'language_ids' => 'required|array|min:1',
            'language_ids.*' => 'required|integer|exists:cv_languages,id',
        ]);

        // Ensure all languages belong to this CV
        $count = $cv->languages()
            ->whereIn('id', $validated['language_ids'])
            ->count();

        if ($count !== count($validated['language_ids'])) {
            return response()->json([
                'status' => false,
                'code' => 403,
                'message' => 'One or more languages do not belong to this CV',
            ], 403);
        }


        $deleted = $cv->languages()
            ->whereIn('id', $validated['language_ids'])
            ->delete();

        return response()->json([
            'status' => true,
            'code' => 200,
            'message' => "$deleted language(s) deleted successfully!",
            'cv' => $cv->load([
                'languages' => fn($q) => $q->latest()
            ]),
        ]);
    }
}
