<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Score extends Model
{
    protected $fillable = [
        'user_program_id',
        'subject_id',
        'score',
    ];

    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    // Relationships
    public function userProgram()
    {
        return $this->belongsTo(UserProgram::class, 'user_program_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }
}
