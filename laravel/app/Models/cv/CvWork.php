<?php

namespace App\Models\cv;

use Illuminate\Database\Eloquent\Model;

class CvWork extends Model
{
    protected $table = 'cv_works';

    protected $fillable = [
        'cv_id',
        'company_name',
        'position',
        'location',
        'start_date',
        'end_date',
        'experience_level',
        'is_current',
        'description',
    ];

    public static $experienceLevels = [
        'Internship',
        'Entry Level',
        'Junior Level',
        'Mid Level',
        'Senior Level',
        'Leadership',
        'Management',
        'Executive',
    ];

    public function cv()
    {
        return $this->belongsTo(Cv::class, 'cv_id');
    }
}
