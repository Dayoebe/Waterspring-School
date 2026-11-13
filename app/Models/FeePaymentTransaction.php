<?php

namespace App\Models;

use App\Casts\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class FeePaymentTransaction extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'school_id',
        'fee_invoice_id',
        'fee_invoice_record_id',
        'type',
        'amount',
        'currency',
        'receipt_number',
        'payment_method',
        'external_reference',
        'provider',
        'provider_reference',
        'cashier_id',
        'cashier_name',
        'payer_name',
        'reverses_transaction_id',
        'notes',
        'metadata',
        'recorded_ip',
        'user_agent',
        'occurred_at',
    ];

    protected $casts = [
        'amount' => Money::class,
        'metadata' => 'array',
        'occurred_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Fee payment transactions are immutable.'));
        static::deleting(fn () => throw new LogicException('Fee payment transactions cannot be deleted.'));
    }

    public function scopeForSchool(Builder $query, int $schoolId): Builder
    {
        return $query->where('school_id', $schoolId);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(FeeInvoice::class, 'fee_invoice_id');
    }

    public function invoiceRecord(): BelongsTo
    {
        return $this->belongsTo(FeeInvoiceRecord::class, 'fee_invoice_record_id');
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function originalTransaction(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_transaction_id');
    }

    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reverses_transaction_id');
    }

    public function reconciliations(): HasMany
    {
        return $this->hasMany(FeePaymentReconciliation::class)->latest('id');
    }

    public function paymentAttempt(): HasOne
    {
        return $this->hasOne(FeePaymentAttempt::class);
    }

    public function isReversal(): bool
    {
        return $this->type === 'reversal';
    }
}
