<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TimeTable extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'name', 'description','group_id'
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

    public function timeSlots(){
        return $this->hasMany(TimeSlot::class);
    }
    public function group(){
        return $this->belongsTo(Group::class);
    }



}
