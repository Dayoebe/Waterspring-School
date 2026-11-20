<?php

namespace App\Models;

use App\Traits\InSchool;
use Illuminate\Database\Eloquent\Model;

class HouseChallenge extends Model
{
    use InSchool;

    protected $fillable = ['school_id', 'academic_year_id', 'semester_id', 'title', 'description', 'starts_at', 'deadline_at', 'status', 'completion_points', 'created_by'];

    protected $casts = ['starts_at' => 'datetime', 'deadline_at' => 'datetime'];

    public function participations()
    {
        return $this->hasMany(HouseChallengeParticipation::class);
    }
}
