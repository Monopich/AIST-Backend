<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Trainer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',         // optional internal user
        'name',            // external name
        'email',           // external email
        'organization_name', 
        'major'
    ];

    // Optional internal user
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Trainings assigned to this trainer
    public function trainings()
    {
        return $this->belongsToMany(Training::class, 'training_trainer', 'trainer_id', 'training_id');
    }
}
