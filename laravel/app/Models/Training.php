<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Training extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'start_date',
        'end_date',
        'location',
        'created_by',
        'status'
    ];

    // Creator of the training
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Trainers of this training
    public function trainers()
    {
        return $this->belongsToMany(Trainer::class, 'training_trainer', 'training_id', 'trainer_id');
    }

    // Trainees of this training
    public function trainees()
    {
        return $this->belongsToMany(Trainee::class, 'training_trainee', 'training_id', 'trainee_id')
                    ->withPivot('status');
    }
}
