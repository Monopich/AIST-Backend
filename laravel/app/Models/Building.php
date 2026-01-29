<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Building extends Model
{
    protected $fillable = [
        "name"
    ];

    //  protected $hidden = [
    //     'created_at',
    //     'updated_at',

    // ];


     public function getCreatedAtAttribute($value)
    {
        return date('d-m-Y H:i:s', strtotime($value));
    }
    public function getUpdatedAtAttribute($value)
    {
        return date('d-m-Y H:i:s', strtotime($value));
    }
    public function locations(){
        return $this->hasMany(Location::class);
    }
}
