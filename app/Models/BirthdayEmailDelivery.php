<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $school_id
 * @property int $celebrant_id
 * @property ?int $sender_id
 * @property ?int $recipient_user_id
 * @property string $kind
 * @property int $birthday_year
 * @property string $recipient_type
 * @property string $status
 * @property ?Carbon $queued_at
 * @property ?Carbon $sent_at
 * @property ?Carbon $failed_at
 * @property-read User $celebrant
 * @property-read ?User $sender
 */
class BirthdayEmailDelivery extends Model
{
    protected $fillable = [
        'school_id', 'celebrant_id', 'sender_id', 'recipient_user_id', 'kind',
        'birthday_year', 'recipient_type', 'status', 'deduplication_key',
        'queued_at', 'sent_at', 'failed_at', 'failure_code',
    ];

    protected $casts = [
        'queued_at' => 'datetime',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function celebrant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'celebrant_id');
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
