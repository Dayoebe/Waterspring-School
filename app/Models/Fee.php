<?php

namespace App\Models;

use App\Casts\Money;
use Brick\Money\Money as BrickMoney;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Fee extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const WAIVER_NONE = 'none';

    public const WAIVER_FIXED = 'fixed';

    public const WAIVER_PERCENTAGE = 'percentage';

    protected $fillable = [
        'name',
        'description',
        'fee_category_id',
        'default_amount',
        'default_waiver_type',
        'default_waiver_amount',
        'default_waiver_basis_points',
        'default_fine',
        'status',
        'approval_version',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'default_amount' => Money::class,
        'default_waiver_amount' => Money::class,
        'default_waiver_basis_points' => 'integer',
        'default_fine' => Money::class,
        'approval_version' => 'integer',
        'approved_at' => 'datetime',
    ];

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    /** @return array<string, string> */
    public static function waiverTypes(): array
    {
        return [
            self::WAIVER_NONE => 'No waiver',
            self::WAIVER_FIXED => 'Fixed amount',
            self::WAIVER_PERCENTAGE => 'Percentage',
        ];
    }

    public function defaultWaiver(): BrickMoney
    {
        if ($this->default_waiver_type === self::WAIVER_FIXED) {
            return $this->default_waiver_amount;
        }

        if ($this->default_waiver_type === self::WAIVER_PERCENTAGE) {
            $amountMinor = (int) $this->getRawOriginal('default_amount');
            $basisPoints = (int) $this->default_waiver_basis_points;
            $waiverMinor = intdiv(($amountMinor * $basisPoints) + 5000, 10000);

            return BrickMoney::ofMinor($waiverMinor, config('app.currency'));
        }

        return BrickMoney::zero(config('app.currency'));
    }

    public function defaultTotal(): BrickMoney
    {
        return $this->default_amount->plus($this->default_fine)->minus($this->defaultWaiver());
    }

    public function waiverPercentage(): string
    {
        $percentage = number_format($this->default_waiver_basis_points / 100, 2, '.', '');

        return rtrim(rtrim($percentage, '0'), '.');
    }

    /** @return array{id: int, name: string, amount: string, waiver: string, fine: string} */
    public function invoiceDefaults(): array
    {
        return [
            'id' => (int) $this->id,
            'name' => $this->name,
            'amount' => (string) $this->default_amount->getAmount(),
            'waiver' => (string) $this->defaultWaiver()->getAmount(),
            'fine' => (string) $this->default_fine->getAmount(),
        ];
    }

    /**
     * Get the feeCategory that owns the Fee.
     */
    public function feeCategory(): BelongsTo
    {
        return $this->belongsTo(FeeCategory::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function approvalNotificationDeliveries(): HasMany
    {
        return $this->hasMany(FeeApprovalNotificationDelivery::class);
    }

    public function school()
    {
        return $this->feeCategory->school();
    }
}
