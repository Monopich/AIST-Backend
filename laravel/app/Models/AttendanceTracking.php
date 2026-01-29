<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class AttendanceTracking extends Model
{
    protected $fillable = [
        "user_id","qr_code_id","date","status","check_in_time","check_out_time","device","scanned_at","request_attendance_id",
        "attendance_date",'leave_request_id','time_slot_id'
    ];

      protected $hidden = [
        'created_at',
        'updated_at',
    ];


     public function getCreatedAtAttribute($value)
    {
        return date('d-m-Y H:i:s', strtotime($value));
    }
    public function getUpdatedAtAttribute($value)
    {
        return date('d-m-Y H:i:s', strtotime($value));
    }

    public function getAttendanceDateAttribute($value)
    {
        return Carbon::parse($value)->format('d-m-Y') ?? null;
    }
    public function getScannedAtAttribute($value)
    {
        return Carbon::parse($value)->format('d-m-Y H:i:s') ?? null;
    }

    public function user(){
        return $this->belongsTo(User::class);
    }
    public function leaveRequests(){
        return $this->hasMany(LeaveRequest::class);
    }

    public function qrCode(){
        return $this->belongsTo(QrCode::class);
    }
    public function timeSlot(){
        return $this->belongsTo(TimeSlot::class);
    }

    public function requestAttendance(){
        return $this->belongsTo(RequestAttendance::class, 'request_id');
    }
}
