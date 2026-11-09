<?php

namespace App\Models;

use App\Traits\InSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class TransportEvent extends Model
{
    use InSchool;

    public const UPDATED_AT = null;

    protected $fillable = [
        'school_id', 'student_id', 'student_transport_assignment_id', 'authorized_pickup_guardian_id',
        'transport_route_id', 'transport_stop_id', 'event_type', 'verification_method',
        'recorded_by', 'recorder_name', 'notes', 'occurred_at',
    ];

    protected $casts = ['occurred_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Transport events are immutable.'));
        static::deleting(fn () => throw new LogicException('Transport events cannot be deleted.'));
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(StudentTransportAssignment::class, 'student_transport_assignment_id');
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(AuthorizedPickupGuardian::class, 'authorized_pickup_guardian_id');
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
