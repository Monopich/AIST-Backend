<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use Illuminate\Http\Request;

class AcadamicYearConttroller extends Controller
{
     
    public function createNewAcademicYear(Request $request)
    {
        $validated = $request->validate([
            'year' => 'required|array|size:2',
            'year.start_year' => 'required|integer|min:2000',
            'year.end_year' => 'required|integer|gt:year.start_year',
        ]);

        $startYear = $validated['year']['start_year'];
        $endYear = $validated['year']['end_year'];
        $yearLabel = "{$startYear}-{$endYear}";

        // Check if the year already exists
        $exists = AcademicYear::where('year_label', $yearLabel)->first();
        if ($exists) {
            return response()->json([
                'message' => 'Academic year already exists.',
                'academic_year' => $exists
            ], 409);
        }

        // Create academic year
        $academicYear = AcademicYear::create([
            'year_label' => $yearLabel,
            'start_year' => $startYear,
            'end_year' => $endYear,
            'dates' => $validated['year'],
        ]);

        return response()->json([
            'message' => 'Academic year created successfully.',
            'academic_year' => $academicYear
        ], 201);

    }
}
