<?php

namespace App\Livewire\Parents;

use App\Models\MyClass;
use App\Models\ParentRecord;
use App\Models\Section;
use App\Models\User;
use App\Services\AuditTrail;
use App\Services\GuardianLinkService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class AssignStudentsToParent extends Component
{
    use WithPagination;

    public User $parent;

    public $search = '';

    public $selectedClass = '';

    public $selectedSection = '';

    public $classes = [];

    public $sections; // Will be a collection

    public string $relationshipType = 'guardian';

    public bool $receivesDigest = false;

    public bool $canViewAcademics = true;

    public bool $canViewAttendance = true;

    public bool $canViewPortfolio = false;

    public bool $canViewGrowthTimeline = false;

    public bool $canConsentEvents = false;

    protected $queryString = ['search'];

    public function mount($parent)
    {
        abort_unless(auth()->user()?->can('update parent'), 403);

        $parentId = $parent instanceof User ? $parent->id : $parent;

        $this->parent = User::role('parent')
            ->where('school_id', auth()->user()->school_id)
            ->findOrFail($parentId);

        // Verify parent role
        if (! $this->parent->hasRole('parent')) {
            abort(404);
        }

        // Check school access
        if ($this->parent->school_id !== auth()->user()->school_id) {
            abort(403);
        }

        // Load classes for filtering
        $this->classes = MyClass::whereHas('classGroup', function ($q) {
            $q->where('school_id', auth()->user()->school_id);
        })->orderBy('name')->get();

        // Initialize sections as empty collection
        $this->sections = collect();
    }

    public function updatedSelectedClass()
    {
        if ($this->selectedClass) {
            $classInSchool = MyClass::where('id', $this->selectedClass)
                ->whereHas('classGroup', function ($q) {
                    $q->where('school_id', auth()->user()->school_id);
                })
                ->exists();

            if (! $classInSchool) {
                $this->sections = collect();
                $this->selectedSection = '';
                $this->resetPage();

                return;
            }

            $this->sections = Section::where('my_class_id', $this->selectedClass)
                ->whereHas('myClass.classGroup', function ($q) {
                    $q->where('school_id', auth()->user()->school_id);
                })
                ->orderBy('name')->get();
        } else {
            $this->sections = collect(); // Empty collection
        }
        $this->selectedSection = '';
        $this->resetPage();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function assignStudent($studentId, GuardianLinkService $links)
    {
        $this->validate([
            'relationshipType' => ['required', Rule::in(GuardianLinkService::RELATIONSHIP_TYPES)],
            'receivesDigest' => ['boolean'],
            'canViewAcademics' => ['boolean'],
            'canViewAttendance' => ['boolean'],
            'canViewPortfolio' => ['boolean'],
            'canViewGrowthTimeline' => ['boolean'],
            'canConsentEvents' => ['boolean'],
        ]);

        $student = User::role('student')
            ->where('school_id', auth()->user()->school_id)
            ->findOrFail($studentId);

        // Verify student role
        if (! $student->hasRole('student')) {
            session()->flash('error', 'Invalid student');

            return;
        }

        if (ParentRecord::query()->where('user_id', $this->parent->id)->where('student_id', $studentId)->exists()) {
            session()->flash('error', 'This guardian is already linked to the selected student.');

            return;
        }

        $links->link($this->parent, $student, auth()->user(), [
            'relationship_type' => $this->relationshipType,
            'receives_digest' => $this->receivesDigest,
            'can_view_academics' => $this->canViewAcademics,
            'can_view_attendance' => $this->canViewAttendance,
            'can_view_portfolio' => $this->canViewPortfolio,
            'can_view_growth_timeline' => $this->canViewGrowthTimeline,
            'can_consent_events' => $this->canConsentEvents,
        ]);

        session()->flash('success', 'Student assigned successfully');
        $this->dispatch('student-assigned');
    }

    public function removeStudent($studentId)
    {
        session()->flash('error', 'Parent relationships are permanent and cannot be removed or reassigned.');
    }

    public function toggleAuthority(int $studentId, string $field): void
    {
        $allowed = [
            'receives_digest', 'can_view_academics', 'can_view_attendance',
            'can_view_portfolio', 'can_view_growth_timeline', 'can_consent_events',
        ];
        abort_unless(in_array($field, $allowed, true), 422);

        $link = ParentRecord::query()
            ->where('user_id', $this->parent->id)
            ->where('student_id', $studentId)
            ->whereHas('student', fn ($query) => $query->where('school_id', auth()->user()->school_id))
            ->firstOrFail();
        $old = (bool) $link->{$field};
        $link->update([$field => ! $old, 'verified_at' => now(), 'verified_by' => auth()->id()]);

        app(AuditTrail::class)->record(
            auth()->user()->school_id,
            'guardians',
            'guardian.authority_updated',
            'Guardian relationship authority was updated.',
            $link,
            [$field => $old],
            [$field => ! $old],
            ['guardian_id' => $this->parent->id, 'student_id' => $studentId],
        );
    }

    public function makePrimary(int $studentId): void
    {
        DB::transaction(function () use ($studentId): void {
            User::query()->whereKey($studentId)->lockForUpdate()->firstOrFail();
            $link = ParentRecord::query()
                ->where('user_id', $this->parent->id)
                ->where('student_id', $studentId)
                ->whereHas('student', fn ($query) => $query->where('school_id', auth()->user()->school_id))
                ->firstOrFail();

            ParentRecord::query()->where('student_id', $studentId)->update(['is_primary_contact' => false]);
            $link->update(['is_primary_contact' => true, 'verified_at' => now(), 'verified_by' => auth()->id()]);

            app(AuditTrail::class)->record(
                auth()->user()->school_id,
                'guardians',
                'guardian.primary_contact_changed',
                'The student primary guardian contact was changed.',
                $link,
                metadata: ['guardian_id' => $this->parent->id, 'student_id' => $studentId],
            );
        }, 3);
    }

    public function render()
    {
        $currentAcademicYearId = auth()->user()->school->academic_year_id;

        // Get assigned students
        $assignedStudents = $this->parent->children()
            ->with(['studentRecord.myClass', 'studentRecord.section'])
            ->whereHas('studentRecord') // Only get students that have a student record
            ->get();

        // Get available students to assign
        $availableStudentsQuery = User::role('student')
            ->where('school_id', auth()->user()->school_id)
            ->whereHas('studentRecord') // Only get students with student records
            ->whereDoesntHave('parents', fn ($query) => $query->where('users.id', $this->parent->id))
            ->when($this->search, function ($q) {
                $q->where(function ($query) {
                    $query->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('email', 'like', '%'.$this->search.'%')
                        ->orWhereHas('studentRecord', function ($q) {
                            $q->where('admission_number', 'like', '%'.$this->search.'%');
                        });
                });
            });

        // Apply class filter
        if ($this->selectedClass) {
            $availableStudentsQuery->whereHas('studentRecord', function ($q) use ($currentAcademicYearId) {
                $q->whereHas('academicYears', function ($query) use ($currentAcademicYearId) {
                    $query->where('academic_year_id', $currentAcademicYearId)
                        ->where('my_class_id', $this->selectedClass);
                });
            });
        }

        // Apply section filter
        if ($this->selectedSection) {
            $availableStudentsQuery->whereHas('studentRecord', function ($q) use ($currentAcademicYearId) {
                $q->whereHas('academicYears', function ($query) use ($currentAcademicYearId) {
                    $query->where('academic_year_id', $currentAcademicYearId)
                        ->where('section_id', $this->selectedSection);
                });
            });
        }

        $availableStudents = $availableStudentsQuery
            ->with(['studentRecord.myClass', 'studentRecord.section'])
            ->paginate(10);

        return view('livewire.parents.assign-students-to-parent', [
            'assignedStudents' => $assignedStudents,
            'availableStudents' => $availableStudents,
        ])
            ->layout('layouts.dashboard', [
                'breadcrumbs' => [
                    ['href' => route('dashboard'), 'text' => 'Dashboard'],
                    ['href' => route('parents.index'), 'text' => 'Parents'],
                    ['href' => route('parents.show', $this->parent->id), 'text' => $this->parent->name],
                    ['href' => route('parents.assign-student', $this->parent->id), 'text' => 'Assign Students', 'active' => true],
                ],
            ])
            ->title('Assign Students to '.$this->parent->name);
    }
}
