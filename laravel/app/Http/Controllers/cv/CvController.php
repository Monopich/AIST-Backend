<?php

namespace App\Http\Controllers\cv;

use App\Http\Controllers\Controller;
use App\Models\cv\Contact;
use App\Models\cv\Cv;
use App\Models\cv\CvContact;
use App\Models\cv\CvEducation;
use App\Models\cv\CvLanguage;
use App\Models\cv\CvSkill;
use App\Models\cv\CvWork;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Str;

class CvController extends Controller
{


    public function createNewCv(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
            'position' => 'nullable|string|max:255',
            'summary' => 'nullable|string',

            // skills
            'skills' => 'nullable|array',
            'skills.*.skill_name' => 'required|string|max:255',
            'skills.*.proficiency_level' => 'nullable|in:' . implode(',', CvSkill::$proficiencyLevels),

            // educations
            'educations' => 'nullable|array',
            'educations.*.institution_name' => 'required|string|max:255',
            'educations.*.degree' => ['nullable', Rule::in(CvEducation::$degreeLevels)],
            'educations.*.location' => 'nullable|string|max:255',
            'educations.*.field_of_study' => 'nullable|string|max:255',
            'educations.*.start_date' => 'nullable|date',
            'educations.*.end_date' => 'nullable|date|after_or_equal:educations.*.start_date',
            'educations.*.is_current' => 'boolean',
            'educations.*.description' => 'nullable|string',

            // languages
            'languages' => 'nullable|array',
            'languages.*.language_name' => 'required|string|max:255',
            'languages.*.proficiency_level' => 'nullable|in:' . implode(',', CvLanguage::$proficiencyLevels),

            // works
            'works' => 'nullable|array',
            'works.*.company_name' => 'required|string|max:255',
            'works.*.position' => 'required|string|max:255',
            'works.*.location' => 'nullable|string|max:255',
            'works.*.start_date' => 'required|date',
            'works.*.end_date' => 'nullable|date|after_or_equal:works.*.start_date',
            'works.*.experience_level' => ['nullable', Rule::in(CvWork::$experienceLevels)],
            'works.*.is_current' => 'boolean',
            'works.*.description' => 'nullable|string',

            // contacts
            'contacts' => 'nullable|array',
            'contacts.*.contact_type' => 'required|string|in:' . implode(',', CvContact::$contactType),
            'contacts.*.contact' => 'nullable|string|max:255',
        ]);

        DB::beginTransaction();

        try {

            // profile picture
            if ($request->hasFile('profile_picture')) {
                $fileName = Str::slug($validated['name']) . '_' . time() . '.' .
                    $request->file('profile_picture')->getClientOriginalExtension();

                $validated['profile_picture'] = Storage::disk('private')
                    ->putFileAs('cv/profile_pictures', $request->file('profile_picture'), $fileName);
            }

            // create CV
            $cv = Cv::create([
                'name' => $validated['name'],
                'address' => $validated['address'] ?? null,
                'profile_picture' => $validated['profile_picture'] ?? null,
                'position' => $validated['position'] ?? null,
                'summary' => $validated['summary'] ?? null,
            ]);

            // skills
            if (!empty($validated['skills'])) {
                foreach ($validated['skills'] as $skill) {
                    $cv->skills()->create($skill);
                }
            }

            // educations
            if (!empty($validated['educations'])) {
                foreach ($validated['educations'] as $education) {
                    $cv->educations()->create($education);
                }
            }

            // languages
            if (!empty($validated['languages'])) {
                foreach ($validated['languages'] as $language) {
                    $cv->languages()->create($language);
                }
            }

            // works
            if (!empty($validated['works'])) {
                foreach ($validated['works'] as $work) {
                    $cv->works()->create($work);
                }
            }

            // contacts
            if (!empty($validated['contacts'])) {
                foreach ($validated['contacts'] as $contact) {
                    $cv->contacts()->create($contact);
                }
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'code' => 201,
                'message' => 'CV created successfully',
                'cv' => $cv->load(['skills', 'educations', 'languages', 'works', 'contacts']),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'code' => 500,
                'error' => 'CV creation failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getCv($cvId)
    {

        $cv = Cv::with(['skills', 'contacts', 'educations', 'languages', 'works'])
            ->find($cvId);

        if (!$cv) {
            return response()->json([
                'status' => false,
                'message' => 'CV not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'code' => 200,
            'message' => 'The CV retrieved successfully',
            'cv' => $cv,
        ], 200);
    }

    public function removeWork() {}

    public function showProfilePicture(Cv $cv)
    {
        if (!$cv->profile_picture) {
            abort(404);
        }

        if (!Storage::disk('private')->exists($cv->profile_picture)) {
            abort(404);
        }

        return response()->file(
            Storage::disk('private')->path($cv->profile_picture)
        );
    }
    public function deleteCv($cvId)
    {

        $cv = Cv::find($cvId);
        if (!$cv) {
            return response()->json([
                'status' => false,
                'code' => 404,
                'message' => "CV not found !"
            ], 404);
        }
        $cv->delete();
        return response()->json([
            'status' => true,
            'code' => 200,
            'message' => "CV deleted successfully ! "
        ]);
    }
    public function getAllCvs(Request $request)
    {
        $per_page = $request->query('per_page', 14);
        $cvs = Cv::with(['skills', 'contacts', 'educations', 'languages', 'works'])->paginate($per_page);

        return response()->json([
            'status' => true,
            'code' => 200,
            'message' => 'All CVs retrieved successfully',
            'cvs' => $cvs,
        ], 200);
    }
}
