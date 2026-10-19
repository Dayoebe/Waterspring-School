<?php

namespace App\Livewire\Subjects;

use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class FamilySubjects extends Component
{
    public array $students = [];

    public function mount(): void
    {
        /** @var User|null $user */
        $user = auth()->user();
        abort_unless($user?->hasAnyRole(['student', 'parent']), 403);

        $studentUsers = $user->hasRole('student')
            ? collect([$user->loadMissing('studentRecord')])
            : $user->children()
                ->where('users.school_id', $user->school_id)
                ->whereNull('users.deleted_at')
                ->with('studentRecord')
                ->orderBy('users.name')
                ->get();

        $academicYearId = $user->school?->academic_year_id;
        $this->students = $studentUsers->map(function (User $student) use ($user, $academicYearId): array {
            $record = $student->studentRecord;
            $class = $record?->getClassForYear($academicYearId) ?? $record?->baseClass;
            $subjects = $class?->subjects()->orderBy('subjects.name')->get(['subjects.id', 'subjects.name', 'subjects.short_name']) ?? collect();
            $assignments = collect();

            if ($class && $subjects->isNotEmpty()) {
                $assignments = DB::table('subject_teacher as st')
                    ->join('users', 'users.id', '=', 'st.user_id')
                    ->where('st.school_id', $user->school_id)
                    ->where('users.school_id', $user->school_id)
                    ->whereNull('users.deleted_at')
                    ->whereIn('st.subject_id', $subjects->pluck('id'))
                    ->where(fn ($query) => $query
                        ->where('st.my_class_id', $class->id)
                        ->orWhere('st.is_general', true))
                    ->select('st.subject_id', 'st.my_class_id', 'st.is_general', 'users.name')
                    ->orderBy('users.name')
                    ->get()
                    ->groupBy(fn ($assignment) => (int) $assignment->subject_id);
            }

            return [
                'name' => $student->name,
                'class_name' => $class?->name ?? 'Class not assigned',
                'subjects' => $subjects->map(function (Subject $subject) use ($assignments, $class): array {
                    $subjectAssignments = $assignments->get((int) $subject->id, collect());
                    $classAssignments = $class
                        ? $subjectAssignments->where('my_class_id', $class->id)
                        : collect();
                    $teachers = ($classAssignments->isNotEmpty() ? $classAssignments : $subjectAssignments->where('is_general', true))
                        ->pluck('name')->unique()->values()->all();

                    return [
                        'name' => $subject->name,
                        'short_name' => $subject->short_name,
                        'teachers' => $teachers,
                    ];
                })->values()->all(),
            ];
        })->values()->all();
    }

    public function render()
    {
        return view('livewire.subjects.family-subjects')
            ->layout('layouts.dashboard', [
                'breadcrumbs' => [
                    ['href' => route('dashboard'), 'text' => 'Dashboard'],
                    ['href' => route('subjects.family'), 'text' => 'Subjects', 'active' => true],
                ],
            ])
            ->title('Subjects');
    }
}
