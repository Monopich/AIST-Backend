<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    protected $table = 'leave_requests';
    protected $fillable = [
        'user_id',
        'type',
        'start_date',
        'end_date',
        'status',
        'requested_at',
        'reason',
        'approved_at',
        'approved_by',
        'handover_detail',
        'emergency_contact',
        'document',
        'remark'
    ];
    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    protected $hidden = [
        // 'created_at',
        // 'updated_at'
    ];

    protected $appends = [
        'total_days',
        'approved_by_name'
    ];
     public function getCreatedAtAttribute($value)
    {
        return date('d-m-Y H:i:s', strtotime($value));
    }
    public function getUpdatedAtAttribute($value)
    {
        return date('d-m-Y H:i:s', strtotime($value));
    }

    public function getTotalDaysAttribute()
    {
        if (!isset($this->attributes['start_date'], $this->attributes['end_date'])) {
            return null;
        }

        $start = Carbon::parse($this->attributes['start_date']);
        $end = Carbon::parse($this->attributes['end_date']);

        return $start->diffInDays($end) + 1;
    }
    public function getApprovedByNameAttribute()
    {
        return $this->approvedByUser
            ? ['name' => $this->approvedByUser->name]
            : null;
    }

    public function getStartDateAttribute($value)
    {
        return $value ? Carbon::parse($value)->format('d-m-Y') : null;
    }

    public function getEndDateAttribute($value)
    {
        return $value ? Carbon::parse($value)->format('d-m-Y') : null;
    }

    public function getRequestedAtAttribute($value)
    {
        return $value ? Carbon::parse($value)->format('d-m-Y H:i') : null;
    }

    public function getApprovedAtAttribute($value)
    {
        return $value ? Carbon::parse($value)->format('H:i d-m-Y') : null;
    }



    public function user()
    {
        return $this->belongsTo(User::class);
    }

     public function approvedByUser()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

}
