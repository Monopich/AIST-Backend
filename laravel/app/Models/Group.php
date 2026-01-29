<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Group extends Model
{

    protected $fillable = [
        'name', 'department_id', 'sub_department_id','semester_id','description', 'program_id'
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

    public function semester(){
        return $this->belongsTo(Semester::class);
    }
    public function department(){
        return $this->belongsTo(Department::class);
    }
    public function students(){
        return $this->belongsToMany(User::class,'group_users','group_id','user_id');
    }
    public function program(){
        return $this->belongsTo(Program::class);
    }
    public function subDepartment(){
        return $this->belongsTo(SubDepartment::class);
    }
    public function timeTables(){
        return $this->hasMany(TimeTable::class);
    }

    // Group.php (optional but VERY useful)
    public function timeSlots()
    {
        return $this->hasManyThrough(TimeSlot::class, TimeTable::class);
    }

}
