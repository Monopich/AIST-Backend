<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Role extends Model
{

    use SoftDeletes;
    protected $fillable = [
        "name",
        'description',
        'role_key',
    ];
    protected $hidden = ['pivot','created_at',
        'updated_at','deleted_at'];


     public function getCreatedAtAttribute($value)
    {
        return date('d-m-Y H:i:s', strtotime($value));
    }
    public function getUpdatedAtAttribute($value)
    {
        return date('d-m-Y H:i:s', strtotime($value));
    }
    public function userRoles() {
        return $this->hasMany(UserRole::class);
    }
}
