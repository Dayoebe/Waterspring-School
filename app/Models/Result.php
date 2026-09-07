<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Result extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_record_id',
        'subject_id',
        'academic_year_id',
        'semester_id',
        'ca1_score',     
        'ca2_score',     
        'ca3_score',     
        'ca4_score',     
        'exam_score',
        'total_score',
        'subject_position',
        'teacher_comment',
        'approved',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($result) {
            self::validateSubjectEnrollment($result);
        });
        
        static::updating(function ($result) {
            if ($result->isDirty(['student_record_id', 'subject_id'])) {
                self::validateSubjectEnrollment($result);
            }
        });
    }

    protected static function validateSubjectEnrollment($result)
    {
        $student = StudentRecord::find($result->student_record_id);
        
        if (!$student) {
            throw new \Exception("Student record not found");
        }
        
        // Check if student is enrolled in this subject
        $isEnrolled = $student->studentSubjects()
            ->where('subject_id', $result->subject_id)
            ->exists();
        
        if (!$isEnrolled) {
            $subject = Subject::find($result->subject_id);
            $subjectName = $subject ? $subject->name : 'Unknown Subject';
            throw new \Exception("Student not enrolled in {$subjectName}");
        }
    }

    public static function rules(): array
    {
        return [
            'student_record_id' => 'required|exists:student_records,id',
            'subject_id' => 'required|exists:subjects,id',
            'academic_year_id' => 'required|exists:academic_years,id',
            'semester_id' => 'required|exists:semesters,id',
        ];
    }

    public static function validateSubjectAssignment(StudentRecord $student, Subject $subject)
    {
        // Check if subject exists in student's class
        $classSubjects = Subject::query()
            ->where('is_legacy', false)
            ->whereHas('classes', fn ($query) => $query->where('my_classes.id', $student->my_class_id))
            ->pluck('id');
        
        if (!$classSubjects->contains($subject->id)) {
            throw new \Exception("Subject {$subject->name} not assigned to class {$student->myClass->name}");
        }

        // Check if student is enrolled in this subject
        if (!$student->studentSubjects->contains($subject->id)) {
            throw new \Exception("Student not enrolled in subject {$subject->name}");
        }
        
        return true;
    }

    public function studentRecord()
    {
        return $this->belongsTo(StudentRecord::class);
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

    public function validate()
    {
        if (!$this->studentRecord || !$this->academicYearId || !$this->semesterId) {
            session()->flash('error', 'Invalid student record, academic year, or semester.');
            return false;
        }
        return true;
    }

    public function student()
    {
        return $this->belongsTo(StudentRecord::class, 'student_record_id')
            ->withoutGlobalScope('notGraduated');
    }

}
