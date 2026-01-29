<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GroupHistory extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'group_id',
        'name',
        'semester_id',
        'department_id',
        'program_id',
        'sub_department_id',
        'description',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function subDepartment()
    {
        return $this->belongsTo(SubDepartment::class);
    }

    public function students()
    {
        return $this->belongsToMany(User::class, 'group_users', 'group_id', 'user_id');
    }
}
