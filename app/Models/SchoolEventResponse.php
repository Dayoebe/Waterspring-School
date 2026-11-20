<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolEventResponse extends Model
{
    protected $fillable = [
        'school_event_id', 'student_id', 'guardian_id', 'participation_status', 'consent_status',
        'consented_by', 'consented_at', 'emergency_contact_name', 'emergency_contact_phone',
        'emergency_contact_relationship', 'guardian_note', 'responded_at', 'attendance_status',
        'attendance_confirmed_by', 'attendance_confirmed_at',
    ];

    protected $casts = ['consented_at' => 'datetime', 'responded_at' => 'datetime', 'attendance_confirmed_at' => 'datetime'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(SchoolEvent::class, 'school_event_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id')->withTrashed();
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guardian_id')->withTrashed();
    }
}
