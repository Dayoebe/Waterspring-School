<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OpenLearningRecord extends Model
{
    protected $guarded = [];

    protected $casts = [
        'lesson_state' => 'array',
        'conversation_state' => 'array',
        'assessment_state' => 'array',
        'last_activity_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function topic()
    {
        return $this->belongsTo(SchemeTopic::class, 'scheme_topic_id');
    }

    public function schoolClass()
    {
        return $this->belongsTo(MyClass::class, 'my_class_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }
}
