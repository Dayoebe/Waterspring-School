<?php

namespace App\Models;

use App\Traits\InSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransportRoute extends Model
{
    use InSchool;

    protected $fillable = [
        'school_id', 'name', 'code', 'vehicle_label', 'driver_name', 'driver_phone', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function stops(): HasMany
    {
        return $this->hasMany(TransportStop::class)->orderBy('sequence');
    }
}
