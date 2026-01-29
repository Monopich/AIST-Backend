<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QrCode extends Model
{
    protected $fillable = [
        'code', 'location_id','image_path',
    ];


    // protected $hidden = [
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

    public function location(){
        return $this->belongsTo(Location::class);
    }

    public function trackingAttendances(){
        return $this->hasMany(AttendanceTracking::class);
    }
}
