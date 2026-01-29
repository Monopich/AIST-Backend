<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subject extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [
        "subject_name",
        "department_id",
        "program_id",
        "subject_code",
        "description",
        "credit",
        'total_hours',
        'practice_hours'
    ];

    protected $hidden = [
        // 'created_at',
        // 'updated_at',
        'deleted_at',
        'pivot'

    ];


    public function getCreatedAtAttribute($value)
    {
        return date('d-m-Y H:i:s', strtotime($value));
    }
    public function getUpdatedAtAttribute($value)
    {
        return date('d-m-Y H:i:s', strtotime($value));
    }


    public function program()
    {
        return $this->belongsTo(Program::class);
    }
    public function teachers()
    {
        return $this->belongsToMany(User::class, 'subject_teachers', 'subject_id', 'user_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function semesters()
    {
        return $this->belongsToMany(Semester::class, 'subject_semesters', 'subject_id', 'semester_id');
    }

    public function timeSlots()
    {
        return $this->hasMany(TimeSlot::class);
    }

}
