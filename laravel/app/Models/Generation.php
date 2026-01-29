<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Generation extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'number_gen','program_id', 'start_year','end_year'
    ];

    protected $hidden = [
        // 'created_at',
        // 'updated_at',
        'deleted_at'
    ];

     public function getCreatedAtAttribute($value)
    {
        return date('d-m-Y H:i:s', strtotime($value));
    }
    public function getUpdatedAtAttribute($value)
    {
        return date('d-m-Y H:i:s', strtotime($value));
    }

    public function program(){
        return $this->belongsTo(Program::class);
    }
    public function userPrograms(){
        return $this->hasMany(UserProgram::class);
    }



public static function generateNewForProgram($programId)
{
    $program = Program::findOrFail($programId);

    // Find the latest generation for this program
    $lastGeneration = self::where('program_id', $programId)
                          ->orderByDesc('start_year')
                          ->first();

    // Determine the start and end year
    $startYear = $lastGeneration ? $lastGeneration->start_year + 1 : now()->year;
    $endYear = $startYear + $program->duration_years;

    // Determine generation number
    $numberGen = $lastGeneration ? $lastGeneration->number_gen + 1 : 1;

    // Create new generation
    return self::create([
        'program_id' => $programId,
        'number_gen' => $numberGen,
        'start_year' => $startYear,
        'end_year' => $endYear
    ]);
}


}
