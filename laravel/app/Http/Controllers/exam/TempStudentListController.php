<?php

namespace App\Http\Controllers\Exam;

use App\Http\Controllers\Controller;
use App\Models\TempStudentList;
use Illuminate\Http\Request;

class TempStudentListController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'academic_year' => 'required|integer',
            'import_score_id' => 'nullable|exists:import_scores,id',
            'status' => 'nullable|in:selected,not_selected',
        ]);

        $query = TempStudentList::with('tempStudent')
            ->where('academic_year', $request->academic_year);

        if ($request->filled('import_score_id')) {
            $query->where('import_score_id', $request->import_score_id);
        }

        if ($request->filled('status')) {
            $query->where('enrollment_decision', $request->status);
        }

        return response()->json(
            $query->orderBy('rank')->get()
        );
    }
}

