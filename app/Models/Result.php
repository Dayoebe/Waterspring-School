<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class Result extends Model
{
    use HasFactory;

    public const SCORE_MAXIMUMS = [
        'ca1_score' => 20,
        'ca2_score' => 30,
        'exam_score' => 50,
    ];

    protected $fillable = [
        'student_record_id',
        'subject_id',
        'academic_year_id',
        'semester_id',
        'ca1_score',
        'ca2_score',
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
            self::validateAndCalculateScores($result);
        });

        static::updating(function ($result) {
            if ($result->isDirty(['student_record_id', 'subject_id'])) {
                self::validateSubjectEnrollment($result);
            }

            self::validateAndCalculateScores($result);
        });
    }

    public static function calculateTotal(mixed $ca1, mixed $ca2, mixed $exam): float
    {
        return round((float) ($ca1 ?? 0) + (float) ($ca2 ?? 0) + (float) ($exam ?? 0), 2);
    }

    protected static function validateAndCalculateScores(Result $result): void
    {
        $labels = [
            'ca1_score' => 'CA 1',
            'ca2_score' => 'CA 2',
            'exam_score' => 'Exam',
        ];

        foreach (self::SCORE_MAXIMUMS as $column => $maximum) {
            $score = $result->{$column};

            if ($score === null || $score === '') {
                continue;
            }

            if (! is_numeric($score) || (float) $score < 0 || (float) $score > $maximum) {
                throw ValidationException::withMessages([
                    $column => "{$labels[$column]} score must be between 0 and {$maximum}.",
                ]);
            }
        }

        $result->total_score = self::calculateTotal(
            $result->ca1_score,
            $result->ca2_score,
            $result->exam_score
        );
    }

    protected static function validateSubjectEnrollment($result)
    {
        $student = StudentRecord::find($result->student_record_id);

        if (! $student) {
            throw new \Exception('Student record not found');
        }

        // Check if student is enrolled in this subject
        $isEnrolled = $student->studentSubjects()
            ->where('subject_id', $result->subject_id)
            ->exists();

        if (! $isEnrolled) {
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

        if (! $classSubjects->contains($subject->id)) {
            throw new \Exception("Subject {$subject->name} not assigned to class {$student->myClass->name}");
        }

        // Check if student is enrolled in this subject
        if (! $student->studentSubjects->contains($subject->id)) {
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
        if (! $this->studentRecord || ! $this->academicYearId || ! $this->semesterId) {
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
