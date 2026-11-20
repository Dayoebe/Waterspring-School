<?php

namespace App\Models;

use App\Traits\InSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolEvent extends Model
{
    use HasFactory, InSchool;

    protected $fillable = [
        'school_id', 'title', 'description', 'category', 'starts_at', 'ends_at',
        'status', 'all_day', 'location', 'audiences', 'capacity', 'requires_rsvp',
        'requires_consent', 'requires_emergency_contact', 'response_deadline',
        'cancellation_reason', 'published_at', 'published_by', 'cancelled_at',
        'cancelled_by', 'rescheduled_from_id', 'created_by',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'all_day' => 'boolean',
        'audiences' => 'array',
        'capacity' => 'integer',
        'requires_rsvp' => 'boolean',
        'requires_consent' => 'boolean',
        'requires_emergency_contact' => 'boolean',
        'response_deadline' => 'datetime',
        'published_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(MyClass::class, 'school_event_classes');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(SchoolEventResponse::class);
    }

    public function reminderDeliveries(): HasMany
    {
        return $this->hasMany(SchoolEventReminderDelivery::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')->whereNotNull('published_at');
    }
}
