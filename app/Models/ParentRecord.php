<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParentRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'student_id', 'relationship_type', 'is_primary_contact',
        'receives_digest', 'can_view_academics', 'can_view_attendance',
        'can_view_portfolio', 'can_consent_events', 'active_from', 'active_until',
        'verified_at', 'verified_by',
    ];

    protected $casts = [
        'is_primary_contact' => 'boolean',
        'receives_digest' => 'boolean',
        'can_view_academics' => 'boolean',
        'can_view_attendance' => 'boolean',
        'can_view_portfolio' => 'boolean',
        'can_consent_events' => 'boolean',
        'active_from' => 'date',
        'active_until' => 'date',
        'verified_at' => 'datetime',
    ];

    /**
     * Get the user that owns the StudentRecord.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where(fn (Builder $query) => $query->whereNull('active_from')->orWhereDate('active_from', '<=', today()))
            ->where(fn (Builder $query) => $query->whereNull('active_until')->orWhereDate('active_until', '>=', today()));
    }
}
