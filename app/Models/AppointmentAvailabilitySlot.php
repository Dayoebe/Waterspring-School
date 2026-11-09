<?php

namespace App\Models;

use App\Traits\InSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppointmentAvailabilitySlot extends Model
{
    use InSchool;

    protected $fillable = [
        'school_id', 'teacher_id', 'starts_at', 'ends_at', 'mode', 'location',
        'notes', 'status', 'created_by',
    ];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime'];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(ParentTeacherAppointment::class);
    }
}
