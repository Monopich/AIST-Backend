<?php

namespace App\Models\cv;

use Illuminate\Database\Eloquent\Model;

class CvEducation extends Model
{
    protected $table = 'cv_educations';

    protected $fillable = [
        'cv_id',
        'institution_name',
        'degree',
        'location',
        'field_of_study',
        'start_date',
        'end_date',
        'is_current',
        'description',
    ];

    public static $degreeLevels = [
        'High School',
        'Associate',
        'Bachelor',
        'Master',
        'Doctorate (PhD)',
        'Professional Certificate',
        'Diploma',
        'Other',
    ];

    public function cv()
    {
        return $this->belongsTo(Cv::class, 'cv_id');
    }
}
