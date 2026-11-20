<?php

namespace App\Models;

use App\Traits\InSchool;
use Illuminate\Database\Eloquent\Model;

class HousePointRule extends Model
{
    use InSchool;

    protected $fillable = ['school_id', 'name', 'description', 'default_points', 'duplicate_window_days', 'max_awards_per_student_per_term', 'requires_approval', 'active', 'created_by'];

    protected $casts = ['requires_approval' => 'boolean', 'active' => 'boolean'];
}
