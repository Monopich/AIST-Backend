<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubjectTeacher extends Model
{
    protected $fillable = [
        "user_id", "subject_id"
    ];

  protected $hidden = [
        'created_at',
        'updated_at',

    ];

    public function subject(){
        return $this->belongsTo(Subject::class);

    }
    public function teacher(){
        return $this->belongsTo(User::class);
    }
}
