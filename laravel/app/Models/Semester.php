<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Semester extends Model
{
    use SoftDeletes;
    protected $fillable = [
        "semester_key",'program_id', 'semester_number' ,'start_date','end_date', 'year',
    ];


    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date'
    ];

    protected $hidden = [
        // 'created_at',
        // 'updated_at',
        'deleted_at',
        'pivot'
    ];

    public function getStartDateAttribute($value)
    {
        return Carbon::parse($value)->format('d-m-Y') ?? null;
    }
    public function getEndDateAttribute($value)
    {
        return Carbon::parse($value)->format('d-m-Y') ?? null;
    }
      public function getCreatedAtAttribute($value)
    {
        return date('d-m-Y H:i:s', strtotime($value));
    }
    public function getUpdatedAtAttribute($value)
    {
        return date('d-m-Y H:i:s', strtotime($value));
    }


    public function groups(){
        return $this->hasMany(Group::class);
    }
    public function program(){
        return $this->belongsTo(Program::class);
    }
    public function academicYear(){
        return $this->belongsTo(AcademicYear::class);
    }
    // public function timeTables(){
    //     return $this->hasMany(TimeTable::class);
    // }
    public function timeTables()
        {
            return $this->hasManyThrough(TimeTable::class, Group::class, 'semester_id', 'group_id');
        }

    public function subjects(){
        return $this->belongsToMany(Subject::class, 'subject_semesters','semester_id','subject_id');
    }

    public function studentScores(){
        return $this->hasMany(StudentScore::class);
    }


}
