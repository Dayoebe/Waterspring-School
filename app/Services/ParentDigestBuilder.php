<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\AttendanceRecord;
use App\Models\Notice;
use App\Models\ParentRecord;
use App\Models\Result;
use App\Models\SchoolEvent;
use App\Models\StudentActivityRecord;
use App\Models\User;
use App\Support\ResultPublicationStatus;
use Carbon\CarbonInterface;

class ParentDigestBuilder
{
    public function build(User $guardian, CarbonInterface $start, CarbonInterface $end, int $upcomingDays = 7): array
    {
        $links = ParentRecord::query()
            ->active()
            ->where('user_id', $guardian->id)
            ->where('receives_digest', true)
            ->whereNotNull('verified_at')
            ->with(['student.studentRecord'])
            ->get()
            ->filter(fn (ParentRecord $link): bool => (int) $link->student?->school_id === (int) $guardian->school_id);

        $children = $links->map(fn (ParentRecord $link): array => $this->childSummary($link, $start, $end))->values()->all();
        $upcomingEnd = $end->copy()->addDays(max(1, min($upcomingDays, 30)))->endOfDay();

        return [
            'guardian' => ['id' => $guardian->id, 'name' => $guardian->name],
            'school' => ['id' => $guardian->school_id, 'name' => $guardian->school?->name ?: config('app.name'), 'logo_url' => $guardian->school?->logo_url],
            'period' => ['start' => $start->toDateString(), 'end' => $end->toDateString()],
            'children' => $children,
            'announcements' => $this->announcements((int) $guardian->school_id, $start, $upcomingEnd),
            'events' => $this->events((int) $guardian->school_id, $end, $upcomingEnd),
        ];
    }

    private function childSummary(ParentRecord $link, CarbonInterface $start, CarbonInterface $end): array
    {
        $student = $link->student;
        $record = $student?->studentRecord;

        return [
            'id' => $student?->id,
            'name' => $student?->name,
            'relationship' => $link->relationship_type,
            'attendance' => $link->can_view_attendance && $record ? $this->attendance($record->id, $start, $end) : null,
            'assignments' => $link->can_view_academics && $record ? $this->assignments($record->id, $start, $end) : null,
            'results' => $link->can_view_academics && $record ? $this->results($student, $record->id) : null,
            'achievements' => $link->can_view_growth_timeline ? $this->achievements((int) $student?->id, $start, $end) : [],
        ];
    }

    private function attendance(int $studentRecordId, CarbonInterface $start, CarbonInterface $end): array
    {
        $statuses = AttendanceRecord::query()
            ->where('student_record_id', $studentRecordId)
            ->whereHas('attendanceSession', fn ($query) => $query->whereBetween('attendance_date', [$start->toDateString(), $end->toDateString()]))
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($count) => (int) $count);

        return [
            'recorded_days' => $statuses->sum(),
            'present' => (int) $statuses->get('present', 0),
            'late' => (int) $statuses->get('late', 0),
            'absent' => (int) $statuses->get('absent', 0),
            'excused' => (int) $statuses->get('excused', 0),
        ];
    }

    private function assignments(int $studentRecordId, CarbonInterface $start, CarbonInterface $end): array
    {
        return Assignment::query()
            ->whereNotNull('published_at')
            ->whereHas('recipients', fn ($query) => $query->where('student_records.id', $studentRecordId))
            ->whereBetween('due_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->with(['subject:id,name', 'submissions' => fn ($query) => $query->where('student_record_id', $studentRecordId)])
            ->orderBy('due_at')
            ->get()
            ->map(fn (Assignment $assignment): array => [
                'title' => $assignment->title,
                'subject' => $assignment->subject?->name,
                'due_at' => $assignment->due_at?->toIso8601String(),
                'status' => $assignment->submissions->first()?->status ?: 'not_submitted',
            ])->all();
    }

    private function results(User $student, int $studentRecordId): ?array
    {
        $school = $student->school;
        if (! $school?->academic_year_id || ! $school?->semester_id || ! ResultPublicationStatus::termIsPublished((int) $school->id, (int) $school->academic_year_id, (int) $school->semester_id)) {
            return null;
        }

        $results = Result::query()
            ->where('student_record_id', $studentRecordId)
            ->where('academic_year_id', $school->academic_year_id)
            ->where('semester_id', $school->semester_id)
            ->where('approved', true)
            ->with('subject:id,name')
            ->get();

        return [
            'subjects' => $results->map(fn (Result $result): array => ['subject' => $result->subject?->name, 'total' => (float) $result->total_score])->all(),
            'average' => $results->isEmpty() ? null : round((float) $results->avg('total_score'), 1),
        ];
    }

    private function achievements(int $studentId, CarbonInterface $start, CarbonInterface $end): array
    {
        return StudentActivityRecord::query()->approved()->where('student_id', $studentId)->where('eligible_digest', true)
            ->whereBetween('occurred_on', [$start->toDateString(), $end->toDateString()])
            ->with('recognition')->oldest('occurred_on')->get()
            ->map(fn (StudentActivityRecord $record): array => ['category' => $record->display_title, 'message' => $record->display_description])
            ->all();
    }

    private function announcements(int $schoolId, CarbonInterface $start, CarbonInterface $end): array
    {
        return Notice::query()->where('school_id', $schoolId)->where('active', true)
            ->whereDate('start_date', '<=', $end)->whereDate('stop_date', '>=', $start)
            ->orderBy('start_date')->get(['title', 'content', 'start_date', 'stop_date'])
            ->map(fn (Notice $notice): array => ['title' => $notice->title, 'content' => str($notice->content)->stripTags()->limit(240)->toString()])
            ->all();
    }

    private function events(int $schoolId, CarbonInterface $start, CarbonInterface $end): array
    {
        return SchoolEvent::query()->where('school_id', $schoolId)->published()
            ->whereBetween('starts_at', [$start->copy()->endOfDay(), $end])
            ->orderBy('starts_at')->get()
            ->filter(fn (SchoolEvent $event): bool => in_array('all', $event->audiences ?: ['all'], true) || in_array('parent', $event->audiences ?: [], true))
            ->map(fn (SchoolEvent $event): array => ['title' => $event->title, 'starts_at' => $event->starts_at->toIso8601String(), 'location' => $event->location])
            ->values()->all();
    }
}
