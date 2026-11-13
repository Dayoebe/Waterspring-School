<?php

namespace App\Models;

use App\Casts\Money;
use App\Services\AuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

class FeeInvoice extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = ['name', 'note', 'issue_date', 'due_date', 'user_id'];

    protected $casts = [
        'issue_date' => 'datetime:Y-m-d',
        'due_date' => 'datetime:Y-m-d',
        'amount' => Money::class,
        'fine' => Money::class,
        'paid' => Money::class,
        'waiver' => Money::class,
    ];

    protected static function booted(): void
    {
        static::updated(function (self $invoice): void {
            $fields = array_intersect(array_keys($invoice->getChanges()), ['name', 'note', 'issue_date', 'due_date', 'user_id']);
            if ($fields === []) {
                return;
            }

            $invoice->loadMissing('user');
            app(AuditTrail::class)->record(
                $invoice->user?->school_id,
                'finance',
                'invoice.updated',
                'Invoice details were changed.',
                $invoice,
                collect($fields)->mapWithKeys(fn (string $field) => [$field => $invoice->getRawOriginal($field)])->all(),
                collect($fields)->mapWithKeys(fn (string $field) => [$field => $invoice->getAttribute($field)])->all(),
                subjectLabel: $invoice->name,
            );
        });
        static::deleting(function (self $invoice): void {
            if ($invoice->feeInvoiceRecords()->whereHas('paymentTransactions')->exists()) {
                throw new LogicException('Invoices with payment history cannot be deleted.');
            }
            if ($invoice->feeInvoiceRecords()->whereHas('paymentAttempts')->exists()) {
                throw new LogicException('Invoices with online payment attempts cannot be deleted.');
            }
        });
    }

    /**
     * Get the user that owns the FeeInvoice.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all of the feeInvoiceRecords for the FeeInvoice.
     */
    public function feeInvoiceRecords(): HasMany
    {
        return $this->hasMany(FeeInvoiceRecord::class);
    }

    public function scopeisDue(Builder $query): void
    {
        $query->whereHas('FeeInvoiceRecords', function ($query) {
            return $query->isDue();
        });
    }

    public function scopeisPaid(Builder $query): void
    {
        $query->whereDoesntHave('FeeInvoiceRecords', function ($query) {
            return $query->isDue();
        });
    }

    private function getSumOfFieldFromRecords($attribute)
    {
        $total = $this->loadMissing('feeInvoiceRecords')->feeInvoiceRecords->map(function ($model) {
            return $model->getAttributes();
        })->sum($attribute);

        return $this->castAttribute($attribute, $total);
    }

    public function getAmountAttribute()
    {
        return $this->getSumOfFieldFromRecords('amount');
    }

    public function getPaidAttribute()
    {
        return $this->getSumOfFieldFromRecords('paid');
    }

    public function getWaiverAttribute()
    {
        return $this->getSumOfFieldFromRecords('waiver');
    }

    public function getFineAttribute()
    {
        return $this->getSumOfFieldFromRecords('fine');
    }

    public function getBalanceAttribute()
    {
        return $this->amount->plus($this->fine)
            ->minus($this->paid)
            ->minus($this->waiver);
    }
}
