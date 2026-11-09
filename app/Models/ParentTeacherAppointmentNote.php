<?php

namespace App\Models;

use App\Traits\InSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ParentTeacherAppointmentNote extends Model
{
    use InSchool;

    public const UPDATED_AT = null;

    protected $fillable = [
        'parent_teacher_appointment_id', 'school_id', 'author_id', 'author_name', 'type', 'note',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Appointment notes are append-only.'));
        static::deleting(fn () => throw new LogicException('Appointment notes cannot be deleted.'));
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(ParentTeacherAppointment::class, 'parent_teacher_appointment_id');
    }
}
