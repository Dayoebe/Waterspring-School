<?php

namespace App\Models;

use App\Casts\Money;
use App\Services\AuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class FeeInvoiceRecord extends Model
{
    use HasFactory;

    protected $fillable = ['amount', 'waiver', 'fine', 'fee_id', 'fee_invoice_id'];

    protected $casts = [
        'amount' => Money::class,
        'fine' => Money::class,
        'paid' => Money::class,
        'waiver' => Money::class,
    ];

    protected static function booted(): void
    {
        static::updating(function (self $record): void {
            if ($record->isDirty('paid')) {
                throw new LogicException('The paid total is maintained by the immutable payment ledger.');
            }
        });
        static::updated(function (self $record): void {
            $fields = array_intersect(array_keys($record->getChanges()), ['amount', 'waiver', 'fine']);
            if ($fields === []) {
                return;
            }

            $record->loadMissing('feeInvoice.user');
            app(AuditTrail::class)->record(
                $record->feeInvoice?->user?->school_id,
                'finance',
                'invoice.item_updated',
                'An invoice line amount, waiver, or fine was changed.',
                $record,
                collect($fields)->mapWithKeys(fn (string $field) => [$field => $record->getRawOriginal($field)])->all(),
                collect($fields)->mapWithKeys(fn (string $field) => [$field => $record->getAttribute($field)?->getAmount()->__toString()])->all(),
                ['fee_invoice_id' => $record->fee_invoice_id],
                subjectLabel: $record->feeInvoice?->name,
            );
        });
        static::deleting(function (self $record): void {
            if ($record->paymentTransactions()->exists()) {
                throw new LogicException('Fee items with payment history cannot be deleted.');
            }
            if ($record->paymentAttempts()->exists()) {
                throw new LogicException('Fee items with online payment attempts cannot be deleted.');
            }
        });
    }

    public function scopeIsDue(Builder $query)
    {
        $query->whereRaw('(amount + fine) > ( paid + waiver) ');
    }

    public function scopeIsPaid(Builder $query)
    {
        $query->whereRaw('(amount + fine) <= ( paid + waiver) ');
    }

    /**
     * Get the fee that owns the FeeInvoice.
     */
    public function fee(): BelongsTo
    {
        return $this->belongsTo(Fee::class);
    }

    /**
     * Get the feeInvoice that owns the FeeInvoiceRecord.
     */
    public function feeInvoice(): BelongsTo
    {
        return $this->belongsTo(FeeInvoice::class);
    }

    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(FeePaymentTransaction::class)->orderByDesc('occurred_at')->orderByDesc('id');
    }

    public function paymentAttempts(): HasMany
    {
        return $this->hasMany(FeePaymentAttempt::class);
    }
}
