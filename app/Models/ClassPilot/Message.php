<?php

namespace App\Models\ClassPilot;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $table = 'class_pilot_messages';

    protected $guarded = [];

    protected $casts = ['metadata' => 'array'];

    public function session()
    {
        return $this->belongsTo(LearningSession::class, 'class_pilot_session_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
