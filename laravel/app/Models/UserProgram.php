<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserProgram extends Model
{
    protected $fillable = [
        'generation_id', 
        'user_id', 
        'program_id',
        'year', 
        'academic_year_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    public function generation(){
        return $this->belongsTo(Generation::class);
    }
    public function program()
    {
        return $this->belongsTo(Program::class, 'program_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function academicYear(){
        return $this->belongsTo(AcademicYear::class);
    }

    public function semester() {
        return $this->belongsTo(Semester::class, 'semester_id');
    }

    public function userDetail()
    {
        return $this->hasOne(UserDetail::class, 'user_id', 'user_id');
    }

    public function groups() {
        return $this->belongsToMany(Group::class, 'group_users', 'user_id', 'group_id')
                    ->with(['semester', 'subDepartment']);
    }

    public function scores()
    {
        return $this->hasMany(Score::class, 'user_program_id');
    }

}
