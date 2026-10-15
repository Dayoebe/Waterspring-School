<?php

namespace App\Models;

use App\Traits\InSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SchemeOfWork extends Model
{
    use HasFactory, InSchool;

    protected $table = 'schemes_of_work';

    protected $fillable = [
        'school_id', 'academic_year_id', 'semester_id', 'my_class_id', 'subject_id', 'teacher_id',
        'type', 'scope_key', 'title', 'aim', 'objectives', 'teaching_approach', 'assessment_strategy',
        'resources', 'learner_support', 'published_at', 'updated_by',
    ];

    protected $casts = ['published_at' => 'datetime'];

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    public function schoolClass()
    {
        return $this->belongsTo(MyClass::class, 'my_class_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function topics()
    {
        return $this->hasMany(SchemeTopic::class);
    }
}
