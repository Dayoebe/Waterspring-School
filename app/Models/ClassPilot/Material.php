<?php

namespace App\Models\ClassPilot;

use App\Models\AcademicYear;
use App\Models\MyClass;
use App\Models\School;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Material extends Model
{
    protected $table = 'class_pilot_materials';

    protected $guarded = [];

    protected $casts = ['content' => 'array', 'curriculum_sources' => 'array', 'approved_at' => 'datetime'];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function schoolClass()
    {
        return $this->belongsTo(MyClass::class, 'my_class_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    public function sourceMaterial()
    {
        return $this->belongsTo(self::class, 'source_material_id');
    }

    public function revisions()
    {
        return $this->hasMany(self::class, 'source_material_id');
    }
}
