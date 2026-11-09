<?php

namespace App\Models;

use App\Traits\InSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransportStop extends Model
{
    use InSchool;

    protected $fillable = [
        'school_id', 'transport_route_id', 'name', 'sequence', 'morning_time',
        'afternoon_time', 'landmark', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function route(): BelongsTo
    {
        return $this->belongsTo(TransportRoute::class, 'transport_route_id');
    }
}
