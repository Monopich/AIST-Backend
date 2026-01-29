<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class Mission extends Model
{
    protected $table = 'missions';
    protected $fillable = [
        'mission_title',
        'mission_type',
        'description',
        'assigned_date',
        'due_date',
        'budget',
        'location',
        'status',
    ];

    public static array $mission_types = ['Conference', 'Training', 'Meeting', 'Field Trip', 'Research','Workshop','Other'];
    public static array $statuses = ['pending', 'in_progress','completed','cancelled' ,'overdue'];


    // relation 
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_missions');
    }
}
