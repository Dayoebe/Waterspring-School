<?php

namespace App\Models;

use App\Traits\InSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParentDigestDelivery extends Model
{
    use InSchool;

    protected $fillable = [
        'school_id', 'guardian_id', 'week_start_date', 'week_end_date', 'status',
        'attempts', 'child_count', 'payload_hash', 'queued_at', 'sent_at',
        'failed_at', 'failure_code',
    ];

    protected $casts = [
        'week_start_date' => 'date', 'week_end_date' => 'date', 'attempts' => 'integer',
        'child_count' => 'integer', 'queued_at' => 'datetime', 'sent_at' => 'datetime', 'failed_at' => 'datetime',
    ];

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guardian_id')->withTrashed();
    }
}
