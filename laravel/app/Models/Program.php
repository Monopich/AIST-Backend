<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Program extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'program_name', 'degree_level', 'duration_years'
        ,'department_id', 'sub_department_id','academic_year'
    ];

    protected $hidden = [
        'deleted_at',
        // 'updated_at',
        // 'created_at'
    ];

     public function getCreatedAtAttribute($value)
    {
        return date('d-m-Y H:i:s', strtotime($value));
    }
    public function getUpdatedAtAttribute($value)
    {
        return date('d-m-Y H:i:s', strtotime($value));
    }

    public function semesters(){
        return $this->hasMany(Semester::class, 'program_id');
    }

    public function department(){
        return $this->belongsTo(Department::class);
    }

    public function subDepartment(){
        return $this->belongsTo(SubDepartment::class);
    }
    public function subjects(){
        return $this->hasMany(Subject::class);
    }

    public function generation(){
        return $this->hasMany(Generation::class);
    }

    public function users(){
        return $this->hasMany(UserDetail::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function usersProgram() {
        return $this->hasMany(UserProgram::class, 'program_id');
    }

    public function originalProgram()
    {
        return $this->belongsTo(Program::class, 'original_program_id');
    }

    public function clonedPrograms()
    {
        return $this->hasMany(Program::class, 'original_program_id');
    }


}
