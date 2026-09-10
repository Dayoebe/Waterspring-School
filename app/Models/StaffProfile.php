<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffProfile extends Model
{
    use HasFactory;

    protected $fillable = ['school_id', 'user_id', 'staff_department_id', 'job_title', 'bio', 'qualifications', 'responsibilities', 'joined_on', 'is_public', 'display_order'];

    protected $casts = ['joined_on' => 'date', 'is_public' => 'boolean'];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(StaffDepartment::class, 'staff_department_id');
    }
}
