<?php

namespace App\Models\ClassPilot;

use Illuminate\Database\Eloquent\Model;

class Usage extends Model
{
    protected $table = 'class_pilot_usage';

    protected $guarded = [];

    protected $casts = ['metadata' => 'array'];
}
