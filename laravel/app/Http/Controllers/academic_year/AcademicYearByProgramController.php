<?php

namespace App\Http\Controllers\academic_year;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Semester;
use Illuminate\Http\Request;

class AcademicYearByProgramController extends Controller
{
    /**
     * Get academic years that have semesters for a specific program.
     * 
     * @param int $program_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAcademicYearsByProgram($program_id)
    {
        try {
            // Get unique academic year IDs from semesters that belong to this program
            $academicYearIds = Semester::where('program_id', $program_id)
                ->distinct()
                ->pluck('academic_year_id')
                ->filter() // Remove null values
                ->toArray();

            if (empty($academicYearIds)) {
                return response()->json([
                    'success' => true,
                    'message' => 'No academic years found for this program',
                    'academic_years' => []
                ], 200);
            }

            // Get the academic years with their details
            $academicYears = AcademicYear::whereIn('id', $academicYearIds)
                ->orderBy('year_label', 'asc')
                ->get(['id', 'year_label', 'start_date', 'end_date']);

            return response()->json([
                'success' => true,
                'message' => 'Academic years fetched successfully',
                'academic_years' => $academicYears
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching academic years: ' . $e->getMessage(),
                'academic_years' => []
            ], 500);
        }
    }
}
