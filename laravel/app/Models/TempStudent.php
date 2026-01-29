<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TempStudent extends Model
{
    protected $fillable = [
        'academic_year',
        'khmer_name',
        'latin_name',
        'profile_picture',
        'gender',
        'date_of_birth',
        'phone_number',
        'origin',
        'department_id',
        'program_id',
    ];

    public function examResults()
    {
        return $this->hasMany(TempStudentList::class);
    }

    public function user()
    {
        return $this->hasOne(User::class, 'temp_student_id');
    }
}
