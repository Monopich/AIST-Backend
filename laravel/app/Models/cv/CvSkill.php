<?php

namespace App\Models\cv;

use Illuminate\Database\Eloquent\Model;

class CvSkill extends Model
{
    protected $table = 'cv_skills';

    protected $fillable = [
        'cv_id',
        'skill_name',
        'proficiency_level',
    ];


    public static $proficiencyLevels = [
        'Novice',        // beginner, almost no experience
        'Beginner',      // basic understanding
        'Intermediate',  // moderate experience
        'Advanced',      // strong experience, can work independently
        'Expert',        // very strong, recognized authority
        'Master',        // top level, mentor/lead
        'other',         // for any other proficiency not listed
    ];


    public function cv()
    {
        return $this->belongsTo(Cv::class, 'cv_id');
    }
}
