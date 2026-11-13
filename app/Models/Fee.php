<?php

namespace App\Models;

use App\Casts\Money;
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

    protected $fillable = [
        'name',
        'description',
        'fee_category_id',
        'default_amount',
        'status',
        'approval_version',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'default_amount' => Money::class,
        'approval_version' => 'integer',
        'approved_at' => 'datetime',
    ];

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
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
