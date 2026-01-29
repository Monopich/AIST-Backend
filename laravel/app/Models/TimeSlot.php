<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class TimeSlot extends Model
{
    protected $fillable = [
        'day_of_week', 'time_slot','time_table_id','subject_id','teacher_id', 'location_id', 'remark','time_slot_date'
    ];

    protected $casts = [
        'time_slot' => 'array',
        'time_slot_date' => 'date',
    ];

    //   protected $hidden = [
    //     'created_at',
    //     'updated_at',

    //];

    public function getTimeSlotDateAttribute($value)
    {
        return Carbon::parse($value)->format('Y-m-d') ?? null;
    }
     public function getCreatedAtAttribute($value)
    {
        return date('d-m-Y H:i:s', strtotime($value));
    }
    public function getUpdatedAtAttribute($value)
    {
        return date('d-m-Y H:i:s', strtotime($value));
    }


    public function teacher(){
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function group_user(){
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function location(){
        return $this->belongsTo(Location::class);
    }
    public function timeTable(){
        return $this->belongsTo(TimeTable::class);
    }
     public function subject(){
        return $this->belongsTo(Subject::class);
    }
}
