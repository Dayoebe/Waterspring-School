<?php

namespace App\Models;

use App\Traits\InSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParentTeacherAppointment extends Model
{
    use InSchool;

    protected $fillable = [
        'school_id', 'appointment_availability_slot_id', 'parent_id', 'teacher_id',
        'student_id', 'reason', 'status', 'requested_at', 'responded_at',
        'responded_by', 'cancelled_at', 'completed_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'responded_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function slot(): BelongsTo
    {
        return $this->belongsTo(AppointmentAvailabilitySlot::class, 'appointment_availability_slot_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ParentTeacherAppointmentNote::class)->latest('id');
    }
}
