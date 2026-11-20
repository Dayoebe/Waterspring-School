<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentHouseBadge extends Model
{
    protected $fillable = ['house_badge_id', 'student_id', 'academic_year_id', 'awarded_by', 'reason', 'awarded_at'];

    protected $casts = ['awarded_at' => 'datetime'];

    public function badge()
    {
        return $this->belongsTo(HouseBadge::class, 'house_badge_id');
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id')->withTrashed();
    }
}
