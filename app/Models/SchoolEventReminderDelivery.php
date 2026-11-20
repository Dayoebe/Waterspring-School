<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolEventReminderDelivery extends Model
{
    protected $fillable = [
        'school_event_id', 'school_event_response_id', 'guardian_id', 'reminder_type',
        'status', 'attempts', 'queued_at', 'sent_at', 'failed_at', 'failure_code',
    ];

    protected $casts = ['attempts' => 'integer', 'queued_at' => 'datetime', 'sent_at' => 'datetime', 'failed_at' => 'datetime'];

    public function event()
    {
        return $this->belongsTo(SchoolEvent::class, 'school_event_id');
    }

    public function response()
    {
        return $this->belongsTo(SchoolEventResponse::class, 'school_event_response_id');
    }

    public function guardian()
    {
        return $this->belongsTo(User::class, 'guardian_id')->withTrashed();
    }
}
