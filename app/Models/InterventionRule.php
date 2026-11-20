<?php

namespace App\Models;

use App\Traits\InSchool;
use Illuminate\Database\Eloquent\Model;

class InterventionRule extends Model
{
    use InSchool;

    protected $fillable = ['school_id', 'name', 'indicator', 'operator', 'threshold', 'lookback_days', 'minimum_expected_records', 'severity', 'explanation', 'active', 'created_by'];

    protected $casts = ['threshold' => 'decimal:2', 'active' => 'boolean'];
}
