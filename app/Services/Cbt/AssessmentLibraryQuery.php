<?php

namespace App\Services\Cbt;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class AssessmentLibraryQuery
{
    public function accessible(
        Builder $query,
        ?User $viewer,
        bool $restrictedTeacher,
        Collection $managedClassIds,
        Collection $classTeacherClassIds
    ): Builder {
        if (! $viewer?->school_id) {
            return $query->whereRaw('1 = 0');
        }

        $query->forSchool($viewer->school_id)
            ->forCurrentSchoolAcademicPeriod($viewer);

        if (! $restrictedTeacher) {
            return $query;
        }

        $managedClassIds = $managedClassIds->filter()->unique()->values();
        $classTeacherClassIds = $classTeacherClassIds->filter()->unique()->values();

        if ($managedClassIds->isEmpty() && $classTeacherClassIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $assessmentQuery) use ($viewer, $managedClassIds, $classTeacherClassIds) {
            if ($managedClassIds->isNotEmpty()) {
                $assessmentQuery->where(function (Builder $managedAssessmentQuery) use ($viewer, $managedClassIds) {
                    $managedAssessmentQuery->whereIn('assessments.course_id', $managedClassIds)
                        ->whereNotNull('assessments.lesson_id')
                        ->whereExists(function ($subQuery) use ($viewer) {
                            $subQuery->select(DB::raw(1))
                                ->from('subject_teacher as st')
                                ->whereColumn('st.subject_id', 'assessments.lesson_id')
                                ->where('st.user_id', $viewer->id)
                                ->where('st.school_id', $viewer->school_id)
                                ->where(function ($assignmentQuery) {
                                    $assignmentQuery->where('st.is_general', true)
                                        ->orWhereColumn('st.my_class_id', 'assessments.course_id');
                                });
                        });
                });
            }

            $classOnlyIds = $classTeacherClassIds->diff($managedClassIds)->values();

            if ($classOnlyIds->isNotEmpty()) {
                $method = $managedClassIds->isNotEmpty() ? 'orWhereIn' : 'whereIn';
                $assessmentQuery->{$method}('assessments.course_id', $classOnlyIds);
            }
        });
    }

    public function classes(
        Builder $query,
        ?User $viewer,
        bool $restrictedTeacher,
        Collection $classIds
    ): Builder {
        if (! $viewer?->school_id) {
            return $query->whereRaw('1 = 0');
        }

        $query->whereHas('classGroup', fn (Builder $classGroupQuery) => $classGroupQuery
            ->where('school_id', $viewer->school_id));

        if (! $restrictedTeacher) {
            return $query;
        }

        $classIds = $classIds->filter()->unique()->values();

        return $classIds->isEmpty()
            ? $query->whereRaw('1 = 0')
            : $query->whereIn('my_classes.id', $classIds);
    }

    public function subjects(Builder $query, ?int $schoolId, int $classId): Builder
    {
        if (! $schoolId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('subjects.school_id', $schoolId)
            ->where(function (Builder $classScope) use ($classId) {
                $classScope->where('subjects.my_class_id', $classId)
                    ->orWhereHas('classes', fn (Builder $classQuery) => $classQuery
                        ->where('my_classes.id', $classId))
                    ->orWhereIn('subjects.id', function ($subQuery) use ($classId) {
                        $subQuery->from('student_subject')
                            ->where('my_class_id', $classId)
                            ->select('subject_id');
                    });
            })
            ->orderBy('subjects.name')
            ->distinct();
    }

    public function apply(Builder $query, array $filters): Builder
    {
        $query->leftJoin('my_classes', 'my_classes.id', '=', 'assessments.course_id')
            ->leftJoin('subjects', 'subjects.id', '=', 'assessments.lesson_id')
            ->select('assessments.*')
            ->withCount('questions');

        if (($filters['course_id'] ?? '') !== '') {
            $query->where('assessments.course_id', (int) $filters['course_id']);
        }

        if (($filters['lesson_id'] ?? '') !== '') {
            $query->where('assessments.lesson_id', (int) $filters['lesson_id']);
        }

        $searchTerm = trim((string) ($filters['search'] ?? ''));

        if ($searchTerm !== '') {
            $searchTerm = '%'.$searchTerm.'%';

            $query->where(function (Builder $searchQuery) use ($searchTerm) {
                $searchQuery->where('assessments.title', 'like', $searchTerm)
                    ->orWhere('assessments.description', 'like', $searchTerm)
                    ->orWhere('my_classes.name', 'like', $searchTerm)
                    ->orWhere('subjects.name', 'like', $searchTerm)
                    ->orWhere('subjects.short_name', 'like', $searchTerm);
            });
        }

        match ($filters['status'] ?? '') {
            'draft' => $query->where('assessments.is_locked', false),
            'locked' => $query->where('assessments.is_locked', true),
            'student_visible' => $query->whereNotNull('assessments.exam_published_at'),
            'student_hidden' => $query->whereNull('assessments.exam_published_at'),
            'results_visible' => $query->whereNotNull('assessments.results_published_at'),
            'results_hidden' => $query->whereNull('assessments.results_published_at'),
            default => null,
        };

        $direction = strtolower((string) ($filters['direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        match ($filters['sort'] ?? 'created_at') {
            'title' => $query->orderBy('assessments.title', $direction),
            'class' => $query->orderBy('my_classes.name', $direction)->orderBy('assessments.title'),
            'subject' => $query->orderBy('subjects.name', $direction)->orderBy('assessments.title'),
            'duration' => $query->orderBy('assessments.estimated_duration_minutes', $direction),
            'pass_percentage' => $query->orderBy('assessments.pass_percentage', $direction),
            'questions_count' => $query->orderBy('questions_count', $direction),
            'updated_at' => $query->orderBy('assessments.updated_at', $direction),
            default => $query->orderBy('assessments.created_at', $direction),
        };

        return $query->orderByDesc('assessments.id');
    }
}
