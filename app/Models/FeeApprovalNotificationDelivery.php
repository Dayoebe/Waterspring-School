<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeApprovalNotificationDelivery extends Model
{
    protected $fillable = [
        'fee_id',
        'recipient_id',
        'recipient_email',
        'deduplication_key',
        'status',
        'queued_at',
        'error',
    ];

    protected $casts = ['queued_at' => 'datetime'];

    public function fee(): BelongsTo
    {
        return $this->belongsTo(Fee::class);
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }
}
