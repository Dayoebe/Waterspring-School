<?php

namespace App\Models;

use App\Traits\InSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

class ContentReview extends Model
{
    use InSchool;

    protected $fillable = [
        'school_id',
        'subject_type',
        'subject_id',
        'workflow',
        'status',
        'submission_version',
        'submitted_by',
        'submitted_at',
        'decided_by',
        'decided_at',
        'decision_note',
        'snapshot',
    ];

    protected $casts = [
        'submission_version' => 'integer',
        'submitted_at' => 'datetime',
        'decided_at' => 'datetime',
        'snapshot' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(function (ContentReview $review): void {
            if ($review->getOriginal('status') !== 'pending') {
                throw new LogicException('A decided content review is immutable. Submit a new version instead.');
            }
        });

        static::deleting(fn () => throw new LogicException('Content review history cannot be deleted.'));
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by')->withTrashed();
    }

    public function decisionMaker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by')->withTrashed();
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }
}
