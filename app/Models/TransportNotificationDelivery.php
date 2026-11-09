<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransportNotificationDelivery extends Model
{
    protected $fillable = ['transport_event_id', 'recipient_id', 'deduplication_key', 'status', 'error'];
}
