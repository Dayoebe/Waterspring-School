<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeePaymentNotificationDelivery extends Model
{
    protected $fillable = [
        'fee_payment_transaction_id',
        'recipient_id',
        'recipient_email',
        'event',
        'deduplication_key',
        'status',
        'queued_at',
        'error',
    ];

    protected $casts = ['queued_at' => 'datetime'];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(FeePaymentTransaction::class, 'fee_payment_transaction_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }
}
