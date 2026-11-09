<?php

namespace App\Models;

use App\Traits\InSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthorizedPickupGuardian extends Model
{
    use InSchool;

    protected $fillable = [
        'school_id', 'student_id', 'user_id', 'name', 'relationship', 'phone',
        'pickup_code_hash', 'is_active', 'authorized_by', 'failed_verification_attempts',
        'verification_locked_until', 'last_verified_at',
    ];

    protected $hidden = ['pickup_code_hash'];

    protected $casts = [
        'is_active' => 'boolean',
        'verification_locked_until' => 'datetime',
        'last_verified_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
