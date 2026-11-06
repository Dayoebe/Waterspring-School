<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class FeePaymentReconciliation extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'fee_payment_transaction_id',
        'status',
        'statement_reference',
        'notes',
        'reconciled_by',
        'reconciler_name',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Fee reconciliation history is immutable.'));
        static::deleting(fn () => throw new LogicException('Fee reconciliation history cannot be deleted.'));
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(FeePaymentTransaction::class, 'fee_payment_transaction_id');
    }

    public function reconciler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }
}
