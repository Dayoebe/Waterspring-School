<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentNotificationDelivery extends Model
{
    protected $fillable = [
        'parent_teacher_appointment_id', 'recipient_id', 'event', 'deduplication_key',
        'status', 'sent_at', 'error',
    ];

    protected $casts = ['sent_at' => 'datetime'];
}
