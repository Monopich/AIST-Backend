<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AcademicYear extends Model
{
    // use SoftDeletes;
    protected $fillable = [
        'year_label', 'dates'
    ];

    protected $casts = [
        'dates' => 'array',
    ];
    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];

     public function getCreatedAtAttribute($value)
    {
        return date('d-m-Y H:i:s', strtotime($value));
    }
    public function getUpdatedAtAttribute($value)
    {
        return date('d-m-Y H:i:s', strtotime($value));
    }

    public function semesters()
    {
        return $this->hasMany(Semester::class);
    }

    public function UserPrograms(){
        return $this->hasMany(UserProgram::class);
    }

    public function programs()
    {
        return $this->hasMany(Program::class);
    }

}
