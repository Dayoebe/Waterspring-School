<?php

namespace App\Models;

use App\Traits\InSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Club extends Model
{
    use HasFactory, InSchool;

    protected $fillable = [
        'school_id', 'name', 'category', 'description', 'meeting_day', 'meeting_time',
        'meeting_location', 'capacity', 'colour', 'is_active', 'created_by',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function school(): BelongsTo { return $this->belongsTo(School::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function activities(): HasMany { return $this->hasMany(ClubActivity::class); }

    public function instructors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'club_instructors', 'club_id', 'teacher_id')
            ->withPivot('is_lead')->withTimestamps();
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'club_memberships', 'club_id', 'student_id')
            ->withPivot(['joined_on', 'status', 'added_by'])->withTimestamps();
    }
}
