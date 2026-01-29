<?php

namespace App\Models;

use App\Models\TempStudent;
use Illuminate\Database\Eloquent\Model;

class TempStudentList extends Model
{
    protected $table = 'temp_student_list';

    protected $fillable = [
        'temp_student_id',
        'import_score_id',
        'academic_year',
        'rank',
        'score',
        'enrollment_decision',
    ];

    // ✅ ADD THIS METHOD
    public function tempStudent()
    {
        return $this->belongsTo(TempStudent::class, 'temp_student_id');
    }

}
