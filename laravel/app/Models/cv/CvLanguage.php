<?php

namespace App\Models\cv;

use Illuminate\Database\Eloquent\Model;

class CvLanguage extends Model
{
    protected $table = 'cv_languages';

    protected $fillable = [
        'cv_id',
        'language_name',
        'proficiency_level',
    ];

    public static $proficiencyLevels = [
        'Beginner',
        'Intermediate',
        'Advanced',
        'Fluent',
        'Expert',
        'Native',
    ];

    public function cv()
    {
        return $this->belongsTo(Cv::class, 'cv_id');
    }
}
