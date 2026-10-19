<?php

namespace App\Models\ClassPilot;

use App\Models\MyClass;
use App\Models\StudentRecord;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class LearningSession extends Model
{
    protected $table = 'class_pilot_sessions';

    protected $guarded = [];

    protected $casts = ['last_activity_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function studentRecord()
    {
        return $this->belongsTo(StudentRecord::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class, 'class_pilot_session_id')->oldest();
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function schoolClass()
    {
        return $this->belongsTo(MyClass::class, 'my_class_id');
    }
}
