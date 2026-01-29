<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubDepartment extends Model
{
    use SoftDeletes;
    protected $fillable = [
        "name","department_id","description",
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
    public function department(){
        return $this->belongsTo(Department::class);
    }
    public function userDetails(){
        return $this->hasMany(UserDetail::class);
    }

    // public function programs(){
    //     return $this->hasMany(Program::class);
    // }


}
