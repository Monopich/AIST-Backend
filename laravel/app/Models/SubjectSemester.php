<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubjectSemester extends Model
{
    protected $fillable = [
        'subject_id', 'semester_id'
    ];
}
