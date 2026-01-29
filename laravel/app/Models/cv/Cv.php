<?php

namespace App\Models\cv;

use Illuminate\Database\Eloquent\Model;

class Cv extends Model
{
    protected $table = 'cvs';

    protected $fillable = [
        'name',
        'address',
        'profile_picture',
        'position',
        'summary',
    ];

    protected $hidden = [
        'profile_picture'
    ];

    // This will automatically appear in JSON output
    protected $appends = ['profile_picture_url'];

    public function getProfilePictureUrlAttribute()
    {
        if (!$this->profile_picture) {
            return null;
        }

        return url("/cvs/profile_picture/{$this->id}");
    }

    public function skills()
    {
        return $this->hasMany(CvSkill::class, 'cv_id');
    }
    public function languages()
    {
        return $this->hasMany(CvLanguage::class, 'cv_id');
    }
    public function educations(){
        return $this->hasMany(CvEducation::class, 'cv_id');
    }
    public function works(){
        return $this->hasMany(CvWork::class, 'cv_id');
    }
    public function contacts(){
        return $this->hasMany(CvContact::class, 'cv_id');
    }
}
