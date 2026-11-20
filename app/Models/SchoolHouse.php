<?php

namespace App\Models;

use App\Traits\InSchool;
use Illuminate\Database\Eloquent\Model;

class SchoolHouse extends Model
{
    use InSchool;

    protected $fillable = ['school_id', 'name', 'colour', 'motto', 'active', 'created_by'];

    protected $casts = ['active' => 'boolean'];

    public function memberships()
    {
        return $this->hasMany(SchoolHouseMembership::class);
    }

    public function transactions()
    {
        return $this->hasMany(HousePointTransaction::class);
    }
}
