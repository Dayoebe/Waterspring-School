<?php

namespace App\Services\OpenLearning;

use App\Models\MyClass;
use App\Models\SchemeOfWork;
use App\Models\SchemeTopic;
use App\Models\School;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\Syllabus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CurriculumCatalog
{
    public function school(): ?School
    {
        $configuredId = (int) config('open-learning.school_id');

        if ($configuredId > 0) {
            return School::query()->find($configuredId);
        }

        return School::query()
            ->whereNotNull('academic_year_id')
            ->whereNotNull('semester_id')
            ->where('name', 'like', 'Watersprings%')
            ->orderBy('id')
            ->first();
    }

    public function classes(?School $school = null): Collection
    {
        $school ??= $this->school();
        if (! $school?->academic_year_id) {
            return collect();
        }

        $classIds = $this->publishedSchemes($school)->whereHas('topics')->pluck('my_class_id');

        return MyClass::query()->whereIn('id', $classIds)->instructional()->orderBy('name')->get(['id', 'name']);
    }

    public function subjects(School $school, int $classId): Collection
    {
        $subjectIds = $this->publishedSchemes($school)
            ->where('my_class_id', $classId)
            ->whereHas('topics')
            ->whereNotNull('subject_id')
            ->pluck('subject_id');

        return Subject::query()->allSchools()->where('school_id', $school->id)->active()
            ->whereIn('id', $subjectIds)->orderBy('name')->get(['id', 'name', 'short_name']);
    }

    public function terms(School $school, int $classId, int $subjectId): Collection
    {
        $termIds = $this->publishedSchemes($school)
            ->where('my_class_id', $classId)
            ->where('subject_id', $subjectId)
            ->whereHas('topics')
            ->pluck('semester_id');

        return Semester::query()->allSchools()->where('school_id', $school->id)
            ->where('academic_year_id', $school->academic_year_id)
            ->whereIn('id', $termIds)->orderBy('id')->get(['id', 'name']);
    }

    public function topics(School $school, int $classId, int $subjectId, int $semesterId): Collection
    {
        return SchemeTopic::query()
            ->whereHas('scheme', fn (Builder $query) => $this->scopePublishedSelection(
                $query,
                $school,
                $classId,
                $subjectId,
                $semesterId,
            ))
            ->orderBy('week_number')
            ->get(['id', 'scheme_of_work_id', 'week_number', 'topic', 'aim', 'objectives']);
    }

    public function selection(School $school, int $classId, int $subjectId, int $semesterId, int $topicId): ?SchemeTopic
    {
        return SchemeTopic::query()
            ->with([
                'scheme' => fn ($query) => $query->allSchools(),
                'scheme.school',
                'scheme.schoolClass:id,name',
                'scheme.subject' => fn ($query) => $query->allSchools()->select(['subjects.id', 'subjects.name']),
                'scheme.academicYear' => fn ($query) => $query->allSchools()->select(['academic_years.id', 'academic_years.start_year', 'academic_years.stop_year']),
                'scheme.semester' => fn ($query) => $query->allSchools()->select(['semesters.id', 'semesters.name']),
            ])
            ->whereHas('scheme', fn (Builder $query) => $this->scopePublishedSelection(
                $query,
                $school,
                $classId,
                $subjectId,
                $semesterId,
            ))
            ->find($topicId);
    }

    public function context(School $school, SchemeTopic $topic): array
    {
        $scheme = $topic->scheme;
        $syllabus = Syllabus::query()->forSchool($school->id)->forClass($scheme->my_class_id)
            ->where('subject_id', $scheme->subject_id)
            ->where('academic_year_id', $scheme->academic_year_id)
            ->where('semester_id', $scheme->semester_id)
            ->where('is_published', true)
            ->latest('published_at')
            ->first();

        $parts = array_filter([
            'Scheme title: '.$scheme->title,
            'Scheme aim: '.$scheme->aim,
            'Scheme objectives: '.$scheme->objectives,
            'Topic: '.$topic->topic,
            'Topic aim: '.$topic->aim,
            'Learning objectives: '.$topic->objectives,
            'Content outline: '.$topic->content_outline,
            'Learner activities: '.$topic->learner_activities,
            'Resources: '.$topic->resources,
            'Assessment guidance: '.$topic->assessment,
            $syllabus ? 'Published syllabus: '.implode("\n", array_filter([
                $syllabus->name, $syllabus->overview, $syllabus->aims,
                $syllabus->learning_outcomes, $syllabus->scope_and_sequence,
                $syllabus->learning_resources, $syllabus->cross_curricular_links,
            ])) : null,
        ]);

        return [
            'text' => mb_substr(implode("\n\n", $parts), 0, (int) config('class-pilot.max_context_characters')),
            'grounded' => true,
            'sources' => array_values(array_filter([
                ['type' => 'scheme_of_work', 'id' => $scheme->id, 'title' => $scheme->title],
                $syllabus ? ['type' => 'syllabus', 'id' => $syllabus->id, 'title' => $syllabus->name] : null,
            ])),
        ];
    }

    private function publishedSchemes(School $school): Builder
    {
        return SchemeOfWork::query()->allSchools()
            ->where('school_id', $school->id)
            ->where('academic_year_id', $school->academic_year_id)
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('class_subject')
                    ->whereColumn('class_subject.school_id', 'schemes_of_work.school_id')
                    ->whereColumn('class_subject.my_class_id', 'schemes_of_work.my_class_id')
                    ->whereColumn('class_subject.subject_id', 'schemes_of_work.subject_id');
            })
            ->whereNotNull('published_at');
    }

    private function scopePublishedSelection(Builder $query, School $school, int $classId, int $subjectId, int $semesterId): Builder
    {
        return $query->allSchools()->where('school_id', $school->id)
            ->where('academic_year_id', $school->academic_year_id)
            ->where('my_class_id', $classId)
            ->where('subject_id', $subjectId)
            ->whereHas('subject', fn (Builder $subjectQuery) => $subjectQuery->allSchools()
                ->where('subjects.school_id', $school->id)
                ->whereHas('classes', fn (Builder $classQuery) => $classQuery->where('my_classes.id', $classId)))
            ->where('semester_id', $semesterId)
            ->whereNotNull('published_at');
    }
}
