<?php

namespace App\Models;

use App\Traits\InSchool;
use Illuminate\Database\Eloquent\Model;

class HouseBadge extends Model
{
    use InSchool;

    protected $fillable = ['school_id', 'name', 'icon', 'description', 'minimum_points', 'active'];

    protected $casts = ['active' => 'boolean'];
}
