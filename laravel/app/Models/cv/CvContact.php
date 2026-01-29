<?php

namespace App\Models\cv;

use Illuminate\Database\Eloquent\Model;

class CvContact extends Model
{
    protected $table = 'cv_contacts';
    protected $fillable = [
        'contact_type',
        'cv_id',
        'contact'
    ];

    public static $contactType = [
        'Email',
        'Phone',
        'Github',
        'Linkedin',
        'Portfolio',
        'Twitter',
        'Facebook',
        'Telegram',
        'Other'
    ];

   
    public function cv(){
        return $this->belongsTo(Cv::class, 'cv_id');
    }
}
