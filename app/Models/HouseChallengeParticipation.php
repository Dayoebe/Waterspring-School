<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HouseChallengeParticipation extends Model
{
    protected $fillable = ['house_challenge_id', 'student_id', 'school_house_id', 'status', 'score', 'position', 'result_note', 'verified_by', 'verified_at'];

    protected $casts = ['score' => 'decimal:2', 'verified_at' => 'datetime'];

    public function challenge()
    {
        return $this->belongsTo(HouseChallenge::class, 'house_challenge_id');
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id')->withTrashed();
    }

    public function house()
    {
        return $this->belongsTo(SchoolHouse::class, 'school_house_id');
    }
}
