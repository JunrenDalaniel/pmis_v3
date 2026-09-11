<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RodStatus extends Model
{
    use HasFactory;

    protected $fillable = [
        'step_number',
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'step_number' => 'integer',
        'is_active' => 'boolean',
    ];
}
