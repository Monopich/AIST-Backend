<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportScore extends Model
{
    protected $fillable = [
        'file',
        'academic_year',
    ];
}
