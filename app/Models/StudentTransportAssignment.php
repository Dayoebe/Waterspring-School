<?php

namespace App\Models;

use App\Traits\InSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentTransportAssignment extends Model
{
    use InSchool;

    protected $fillable = [
        'school_id', 'student_id', 'transport_route_id', 'transport_stop_id', 'direction',
        'status', 'starts_on', 'ends_on', 'assigned_by',
    ];

    protected $casts = ['starts_on' => 'date', 'ends_on' => 'date'];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(TransportRoute::class, 'transport_route_id');
    }

    public function stop(): BelongsTo
    {
        return $this->belongsTo(TransportStop::class, 'transport_stop_id');
    }
}
