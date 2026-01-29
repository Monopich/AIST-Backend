<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserDetail extends Model
{

    use SoftDeletes;
    protected $fillable = [
        'user_id',
        'id_card',
        'is_active',
        'department_id',
        'sub_department_id',
        // 'program_id',
        'khmer_first_name',
        'khmer_last_name',
        'latin_name',
        'khmer_name',
        'address',
        'date_of_birth',
        'origin',
        'profile_picture',
        'gender',
        'phone_number',
        'special',
        'bio',
        'place_of_birth',
        'high_school',
        'mcs_no',
        'can_id',
        'bac_grade',
        'bac_from',
        'bac_program',
        'degree',
        'option',
        'history',
        'redoubles',
        'scholarships',
        'branch',
        'file',
        'grade',
        'is_radie',
        'current_address',
        'father_name',
        'father_phone',
        'mother_name',
        'mother_phone',
        'guardian_phone',
        'guardian_name',
        'id_prefix',
        'join_at',
        'graduated_from',
        'graduated_at',
        'experience'
    ];

    // date_of_birth to format Y-m-d
    protected $casts = [
        // 'date_of_birth' => 'date',
        'redoubles' => 'array',
        'join_at' => 'date',
    ];
    protected $hidden = [
        // 'created_at',
        // 'updated_at',
        'deleted_at',
        'id'
    ];

    // public function getIdCardAttribute($value){
    //     return strtoupper($value) ?? null;
    // }
    public function getCreatedAtAttribute($value)
    {
        return date('d-m-Y H:i:s', strtotime($value));
    }
    public function getUpdatedAtAttribute($value)
    {
        return date('d-m-Y H:i:s', strtotime($value));
    }


    public function getDateOfBirthAttribute($value)
    {
        return Carbon::parse($value)->format('d-m-Y') ?? null;
    }
    public function setDateOfBirthAttribute($value)
    {
        if (!$value) {
            $this->attributes['date_of_birth'] = null;
            return;
        }
        $formats = ['d-m-Y', 'd/m/Y','Y-m-d','Y/m/d']; // allowed formats

        foreach ($formats as $format) {
            try {
                $this->attributes['date_of_birth'] = Carbon::createFromFormat($format, $value)->format('Y-m-d');
                return;
            } catch (\Exception $e) {

            }
        }

        try {
            $this->attributes['date_of_birth'] = Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            $this->attributes['date_of_birth'] = null;
        }
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }
    public function subDepartment()
    {
        return $this->belongsTo(SubDepartment::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(\App\Models\AcademicYear::class, 'academic_year_id');
    }

    public function studentScore()
    {
        return $this->hasMany(StudentScore::class);
    }

    // public function program()
    // {
    //     return $this->belongsTo(Program::class);
    // }

    public function userPrograms()
    {
        return $this->hasMany(UserProgram::class, 'user_id', 'id');
    }

}


