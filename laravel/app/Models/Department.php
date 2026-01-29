<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Model
{
    // use SoftDeletes;
    protected $fillable = [
        "department_name","description","department_head_id"
    ];

      protected $hidden = [
        // 'created_at',
        // 'updated_at',
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



    public function users(){
        return $this->hasMany(User::class, 'department_id');
    }
    public function groups(){
        return $this->hasMany(Group::class);
    }

    public function headOfDepartment(){
        return $this->belongsTo(User::class, 'department_head_id');
    }
    public function subjects(){
        return $this->hasMany(Subject::class,'department_id');
    }

    public function subDepartments(){
        return $this->hasMany(SubDepartment::class);
    }


      // function to assign head of department
    public function assignHead($userId){
        $this->department_head_id = $userId;
        $this->save();
    }


}
