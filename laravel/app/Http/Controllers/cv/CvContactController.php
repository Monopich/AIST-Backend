<?php

namespace App\Http\Controllers\cv;

use App\Http\Controllers\Controller;
use App\Models\cv\Cv;
use App\Models\cv\CvContact;
use Illuminate\Http\Request;

class CvContactController extends Controller
{
    public function addNewContactsToCv(Request $request, $cvId)
    {

        $cv = Cv::find($cvId);
        if (!$cv) {
            return response()->json([
                'status' => false,
                'code' => 404,
                'message' => 'Cv not found !'
            ], 404);
        }
        $validated = $request->validate([
            // contacts
            'contacts' => 'nullable|array',
            'contacts.*.contact_type' => 'required|string|in:' . implode(',', CvContact::$contactType),
            'contacts.*.contact' => 'nullable|string|max:255',
        ]);

        foreach ($validated['contacts'] as $contact) {
            $cv->contacts()->create($contact);
        }
        return response()->json([
            'status' => true,
            'code' => 200,
            'message' => "New contact(s) added successfully!",
            'cv' => $cv->load('contacts'),
        ]);
    }

    public function deleteContacts(Request $request, $cvId)
    {
        $cv = Cv::find($cvId);

        if (!$cv) {
            return response()->json([
                'status' => false,
                'message' => 'CV not found',
            ], 404);
        }

        $validated = $request->validate([
            'contact_ids' => 'required|array|min:1',
            'contact_ids.*' => 'required|integer|exists:cv_contacts,id',
        ]);

        // Check ownership: all educations must belong to this CV
        $count = $cv->contacts()
            ->whereIn('id', $validated['contact_ids'])
            ->count();

        if ($count !== count($validated['contact_ids'])) {
            return response()->json([
                'status' => false,
                'code' => 403,
                'message' => 'One or more contacts do not belong to this CV',
            ], 403);
        }

        // Safe bulk delete
        $deleted = $cv->contacts()
            ->whereIn('id', $validated['contact_ids'])
            ->delete();

        return response()->json([
            'status' => true,
            'code' => 200,
            'message' => "$deleted contacts(s) deleted successfully!",
            'cv' => $cv->load([
                'contacts' => fn($q) => $q->latest()
            ]),
        ]);
    }

   
}
