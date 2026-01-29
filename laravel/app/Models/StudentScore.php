<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentScore extends Model
{
    protected $fillable = ['scores', 'student_id', 'subject_id','semester_id','attendance_score','exam_score'];

    //   protected $hidden = [
    //     'created_at',
    //     'updated_at',
    // ];
     public function getCreatedAtAttribute($value)
    {
        return date('d-m-Y H:i:s', strtotime($value));
    }
    public function getUpdatedAtAttribute($value)
    {
        return date('d-m-Y H:i:s', strtotime($value));
    }

    public function student(){
        return $this->belongsTo(User::class, 'student_id');
    }
    public function subject(){
        return $this->belongsTo(Subject::class);
    }
    public function semester(){
        return $this->belongsTo(Semester::class);
    }
}
