<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class FeedBack extends Model
{
    protected $table = 'feedbacks';
    protected $fillable = [
        'email', 'remark'
    ];

    protected $hidden = [
        'updated_at'
    ];

    

    public function getCreatedAtAttribute($value)
    {
        return Carbon::parse($value)
                     ->setTimezone('Asia/Phnom_Penh') // set to Cambodia timezone
                     ->format('d-m-Y H:i');
    }


    public function getUpdatedAtAttribute($value)
    {
        return Carbon::parse($value)
                     ->setTimezone('Asia/Phnom_Penh')
                     ->format('d-m-Y H:i');
    }


}
