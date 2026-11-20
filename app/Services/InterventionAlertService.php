<?php

namespace App\Services;

use App\Models\InterventionRule;
use App\Models\School;
use App\Models\StudentInterventionAlert;
use App\Models\StudentRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class InterventionAlertService
{
    public function generateForRule(InterventionRule $rule): array
    {
        $created = 0;
        $incomplete = 0;
        $checked = 0;
        $academicYearId = School::query()->whereKey($rule->school_id)->value('academic_year_id');
        $studentIds = StudentRecord::activeStudentRecordIdsForSchoolAcademicYear((int) $rule->school_id, $academicYearId)
            ->pipe(fn ($ids) => StudentRecord::query()->whereIn('id', $ids)->pluck('user_id'));

        foreach (User::query()->whereIn('id', $studentIds)->cursor() as $student) {
            $checked++;
            $signal = $this->signal($rule, $student);
            if (! $signal['data_complete']) {
                $incomplete++;

                continue;
            }
            if (! $this->matches((float) $signal['value'], $rule->operator, (float) $rule->threshold)) {
                continue;
            }
            $period = now()->startOfWeek()->toDateString();
            $fingerprint = hash('sha256', implode('|', [$rule->id, $student->id, $period]));
            $alert = StudentInterventionAlert::firstOrCreate(['fingerprint' => $fingerprint], [
                'school_id' => $rule->school_id, 'student_id' => $student->id, 'intervention_rule_id' => $rule->id,
                'severity' => $rule->severity, 'observed_value' => $signal['value'], 'data_complete' => true,
                'evidence' => $signal['evidence'], 'missing_data' => [], 'status' => 'new', 'detected_at' => now(),
            ]);
            if ($alert->wasRecentlyCreated) {
                $alert->updates()->create(['author_id' => $rule->created_by, 'event' => 'detected', 'note' => 'Rule threshold met. Human review is required; no disciplinary decision was made.']);
                $created++;
            }
        }

        return compact('created', 'incomplete', 'checked');
    }

    public function generateForSchool(int $schoolId): array
    {
        $totals = ['created' => 0, 'incomplete' => 0, 'checked' => 0];
        foreach (InterventionRule::query()->where('school_id', $schoolId)->where('active', true)->get() as $rule) {
            $result = $this->generateForRule($rule);
            foreach ($totals as $key => $_) {
                $totals[$key] += $result[$key];
            }
        }

        return $totals;
    }

    private function signal(InterventionRule $rule, User $student): array
    {
        $recordId = $student->studentRecord?->id;
        $since = now()->subDays($rule->lookback_days);
        if (! $recordId) {
            return ['value' => 0, 'data_complete' => false, 'evidence' => ['reason' => 'No student record']];
        }

        if (in_array($rule->indicator, ['attendance_absences', 'attendance_lateness'], true)) {
            $sessions = DB::table('attendance_sessions as s')->join('attendance_records as r', 'r.attendance_session_id', '=', 's.id')
                ->where('r.student_record_id', $recordId)->whereDate('s.attendance_date', '>=', $since)->count();
            $status = $rule->indicator === 'attendance_absences' ? 'absent' : 'late';
            $rows = DB::table('attendance_sessions as s')->join('attendance_records as r', 'r.attendance_session_id', '=', 's.id')
                ->where('r.student_record_id', $recordId)->whereDate('s.attendance_date', '>=', $since)->where('r.status', $status)
                ->orderByDesc('s.attendance_date')->get(['s.attendance_date', 'r.status', 'r.remark']);

            return ['value' => $rows->count(), 'data_complete' => $sessions >= $rule->minimum_expected_records,
                'evidence' => ['indicator' => $status, 'lookback_days' => $rule->lookback_days, 'attendance_records_checked' => $sessions, 'matching_dates' => $rows->pluck('attendance_date')->all()]];
        }

        if ($rule->indicator === 'missing_assignments') {
            $rows = DB::table('assignment_recipients as r')->join('assignments as a', 'a.id', '=', 'r.assignment_id')
                ->leftJoin('assignment_submissions as s', fn ($join) => $join->on('s.assignment_id', '=', 'a.id')->on('s.student_record_id', '=', 'r.student_record_id'))
                ->where('r.student_record_id', $recordId)->where('a.due_at', '>=', $since)->where('a.due_at', '<', now())->whereNull('s.id')
                ->orderByDesc('a.due_at')->get(['a.id', 'a.title', 'a.due_at']);
            $expected = DB::table('assignment_recipients as r')->join('assignments as a', 'a.id', '=', 'r.assignment_id')
                ->where('r.student_record_id', $recordId)->where('a.due_at', '>=', $since)->where('a.due_at', '<', now())->count();

            return ['value' => $rows->count(), 'data_complete' => $expected >= $rule->minimum_expected_records,
                'evidence' => ['lookback_days' => $rule->lookback_days, 'overdue_assignments_checked' => $expected, 'missing_assignments' => $rows->map(fn ($row) => ['id' => $row->id, 'title' => $row->title, 'due_at' => $row->due_at])->all()]];
        }

        $results = DB::table('results')->where('student_record_id', $recordId)->where('approved', true)
            ->where('updated_at', '>=', $since)->get(['subject_id', 'total_score', 'academic_year_id', 'semester_id']);

        return ['value' => round((float) ($results->avg('total_score') ?? 0), 2), 'data_complete' => $results->count() >= $rule->minimum_expected_records,
            'evidence' => ['lookback_days' => $rule->lookback_days, 'approved_assessments_checked' => $results->count(), 'scores' => $results->map(fn ($row) => ['subject_id' => $row->subject_id, 'score' => $row->total_score, 'academic_year_id' => $row->academic_year_id, 'semester_id' => $row->semester_id])->all()]];
    }

    private function matches(float $value, string $operator, float $threshold): bool
    {
        return match ($operator) {
            '>' => $value > $threshold, '<' => $value < $threshold, '<=' => $value <= $threshold, '=' => $value === $threshold, default => $value >= $threshold
        };
    }
}
