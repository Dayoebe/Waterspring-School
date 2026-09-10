<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssignmentNotificationDelivery extends Model
{
    protected $fillable = [
        'assignment_id',
        'recipient_id',
        'event',
        'deduplication_key',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];
}
