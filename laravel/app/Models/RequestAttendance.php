<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequestAttendance extends Model
{
    protected $fillable = [
        "user_id","start_date","end_date","status",
        "reason","approved_by", 'approved_at',
    ];


    //   protected $hidden = [
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

    public function user(){
        return $this->belongsTo(User::class);
    }
}
