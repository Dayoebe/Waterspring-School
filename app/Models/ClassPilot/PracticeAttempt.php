<?php

namespace App\Models\ClassPilot;

use App\Models\StudentRecord;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Model;

class PracticeAttempt extends Model
{
    protected $table = 'class_pilot_practice_attempts';

    protected $guarded = [];

    protected $casts = ['answers' => 'array', 'feedback' => 'array', 'completed_at' => 'datetime'];

    public function studentRecord()
    {
        return $this->belongsTo(StudentRecord::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function material()
    {
        return $this->belongsTo(Material::class, 'class_pilot_material_id');
    }
}
