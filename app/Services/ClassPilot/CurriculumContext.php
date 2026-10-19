<?php

namespace App\Services\ClassPilot;

use App\Models\SchemeOfWork;
use App\Models\Syllabus;

class CurriculumContext
{
    public function forSelection(int $schoolId, ?int $classId, ?int $subjectId, ?int $academicYearId, ?int $semesterId, ?string $topic = null): array
    {
        if (! $classId || ! $subjectId) {
            return ['text' => '', 'sources' => [], 'grounded' => false];
        }

        $sources = [];
        $parts = [];
        $limit = (int) config('class-pilot.max_context_characters');

        $syllabus = Syllabus::query()->forSchool($schoolId)->forClass($classId)
            ->where('subject_id', $subjectId)->where('is_published', true)
            ->when($academicYearId, fn ($q) => $q->where('academic_year_id', $academicYearId))
            ->when($semesterId, fn ($q) => $q->where('semester_id', $semesterId))->latest('published_at')->first();

        if ($syllabus) {
            $sources[] = ['type' => 'syllabus', 'id' => $syllabus->id, 'title' => $syllabus->name];
            $parts[] = implode("\n", array_filter([$syllabus->name, $syllabus->overview, $syllabus->aims, $syllabus->learning_outcomes, $syllabus->scope_and_sequence]));
        }

        $scheme = SchemeOfWork::query()->where('school_id', $schoolId)->where('my_class_id', $classId)
            ->where('subject_id', $subjectId)->whereNotNull('published_at')
            ->when($academicYearId, fn ($q) => $q->where('academic_year_id', $academicYearId))
            ->when($semesterId, fn ($q) => $q->where('semester_id', $semesterId))
            ->with(['topics' => fn ($q) => $q->when($topic, fn ($topicQuery) => $topicQuery->where('topic', 'like', '%'.addcslashes($topic, '%_').'%'))->limit(8)])
            ->latest('published_at')->first();

        if ($scheme) {
            $sources[] = ['type' => 'scheme_of_work', 'id' => $scheme->id, 'title' => $scheme->title];
            $parts[] = $scheme->title."\n".$scheme->aim."\n".$scheme->topics->map(fn ($item) => implode(' | ', array_filter([$item->topic, $item->objectives, $item->content_outline, $item->assessment])))->implode("\n");
        }

        return ['text' => mb_substr(implode("\n\n", $parts), 0, $limit), 'sources' => $sources, 'grounded' => $sources !== []];
    }
}
