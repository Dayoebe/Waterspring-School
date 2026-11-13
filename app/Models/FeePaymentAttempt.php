<?php

namespace App\Models;

use App\Casts\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class FeePaymentAttempt extends Model
{
    protected $fillable = [
        'school_id',
        'fee_invoice_id',
        'fee_invoice_record_id',
        'initiated_by',
        'provider',
        'reference',
        'access_code',
        'authorization_url',
        'provider_transaction_id',
        'amount',
        'currency',
        'payer_email',
        'status',
        'gateway_response',
        'failure_reason',
        'provider_payload',
        'fee_payment_transaction_id',
        'initialized_at',
        'verified_at',
        'paid_at',
    ];

    protected $casts = [
        'amount' => Money::class,
        'provider_payload' => 'array',
        'initialized_at' => 'datetime',
        'verified_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('Online payment attempts cannot be deleted.'));
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(FeeInvoice::class, 'fee_invoice_id');
    }

    public function invoiceRecord(): BelongsTo
    {
        return $this->belongsTo(FeeInvoiceRecord::class, 'fee_invoice_record_id');
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(FeePaymentTransaction::class, 'fee_payment_transaction_id');
    }
}
