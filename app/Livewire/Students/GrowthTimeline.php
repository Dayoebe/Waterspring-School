<?php

namespace App\Livewire\Students;

use App\Models\AcademicYear;
use App\Models\Club;
use App\Models\SchoolEvent;
use App\Models\Semester;
use App\Models\StudentActivityRecord;
use App\Models\Subject;
use App\Models\User;
use App\Services\AuditTrail;
use App\Services\StudentActivityRecordService;
use App\Services\StudentRecordAccessService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class GrowthTimeline extends Component
{
    use WithFileUploads;

    public ?int $studentId = null;

    public string $classification = 'observed';

    public string $category = 'personal_growth';

    public string $title = '';

    public string $description = '';

    public string $occurredOn = '';

    public string $academicYearId = '';

    public string $semesterId = '';

    public string $subjectId = '';

    public string $clubId = '';

    public string $sport = '';

    public string $schoolEventId = '';

    public $evidence = null;

    public bool $eligibleTimeline = true;

    public bool $eligiblePortfolio = false;

    public bool $eligibleCelebration = false;

    public bool $eligibleDigest = false;

    public bool $eligibleDisplay = false;

    public bool $eligibleHousePoints = false;

    public bool $eligibleMemoryBook = false;

    public string $categoryFilter = 'all';

    public string $classificationFilter = 'all';

    public string $academicYearFilter = 'all';

    public string $semesterFilter = 'all';

    public string $reviewNote = '';

    public function mount(StudentRecordAccessService $access): void
    {
        $user = auth()->user();
        abort_unless($user?->canAny(['view own growth timeline', 'view child growth timeline', 'view student growth timeline']), 403);
        $this->studentId = $access->permittedStudents($user, 'timeline')->orderBy('name')->value('id');
        $this->occurredOn = today()->toDateString();
        $this->academicYearId = (string) ($user->school?->academic_year_id ?: '');
        $this->semesterId = (string) ($user->school?->semester_id ?: '');
        if ($user->hasRole('student')) {
            $this->classification = 'student_submitted';
        }
    }

    public function saveRecord(StudentActivityRecordService $records, StudentRecordAccessService $access): void
    {
        $user = auth()->user();
        $student = $access->permittedStudents($user, 'timeline')->findOrFail($this->studentId);
        $isStudent = $user->id === $student->id && $user->hasRole('student');
        $validated = $this->validate([
            'classification' => ['required', Rule::in($isStudent ? ['student_submitted'] : ['verified', 'observed'])],
            'category' => ['required', Rule::in(array_keys($this->categories()))],
            'title' => ['required', 'string', 'max:180'], 'description' => ['required', 'string', 'min:10', 'max:5000'],
            'occurredOn' => ['required', 'date', 'before_or_equal:today'], 'academicYearId' => ['nullable', 'integer'],
            'semesterId' => ['nullable', 'integer'], 'subjectId' => ['nullable', 'integer'], 'clubId' => ['nullable', 'integer'],
            'sport' => ['nullable', 'string', 'max:120'], 'schoolEventId' => ['nullable', 'integer'],
            'evidence' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,ppt,pptx,txt,mp4,webm', 'max:25600'],
            'eligibleTimeline' => ['boolean'], 'eligiblePortfolio' => ['boolean'], 'eligibleCelebration' => ['boolean'],
            'eligibleDigest' => ['boolean'], 'eligibleDisplay' => ['boolean'], 'eligibleHousePoints' => ['boolean'], 'eligibleMemoryBook' => ['boolean'],
        ]);
        $records->create($student, $user, [
            'classification' => $validated['classification'], 'category' => $validated['category'], 'title' => $validated['title'],
            'description' => $validated['description'], 'occurred_on' => $validated['occurredOn'],
            'academic_year_id' => $validated['academicYearId'] ?: null, 'semester_id' => $validated['semesterId'] ?: null,
            'subject_id' => $validated['subjectId'] ?: null, 'club_id' => $validated['clubId'] ?: null,
            'sport' => $validated['sport'], 'school_event_id' => $validated['schoolEventId'] ?: null,
            'eligible_timeline' => $validated['eligibleTimeline'], 'eligible_portfolio' => $validated['eligiblePortfolio'],
            'eligible_celebration' => $validated['eligibleCelebration'], 'eligible_digest' => $validated['eligibleDigest'],
            'eligible_display' => $validated['eligibleDisplay'], 'eligible_house_points' => $validated['eligibleHousePoints'],
            'eligible_memory_book' => $validated['eligibleMemoryBook'],
        ], $this->evidence);
        $this->reset(['title', 'description', 'subjectId', 'clubId', 'sport', 'schoolEventId', 'evidence', 'eligiblePortfolio', 'eligibleCelebration', 'eligibleDigest', 'eligibleDisplay', 'eligibleHousePoints', 'eligibleMemoryBook']);
        $this->occurredOn = today()->toDateString();
        session()->flash('success', $isStudent ? 'Your record was submitted for review.' : 'Student record saved successfully.');
    }

    public function review(int $recordId, string $decision, StudentActivityRecordService $records): void
    {
        $record = StudentActivityRecord::query()->where('school_id', auth()->user()->school_id)->findOrFail($recordId);
        $records->review($record, auth()->user(), $decision, $this->reviewNote);
        $this->reviewNote = '';
        session()->flash('success', 'Record review saved.');
    }

    public function exportDevelopmentReport(StudentRecordAccessService $access)
    {
        $student = $access->permittedStudents(auth()->user(), 'timeline')->findOrFail($this->studentId);
        $records = $this->recordQuery($student)->approved()->where('eligible_timeline', true)->oldest('occurred_on')->get();
        app(AuditTrail::class)->record((int) auth()->user()->school_id, 'student_records', 'growth_timeline.exported', 'A controlled student development report was exported.', null, metadata: ['student_id' => $student->id, 'record_count' => $records->count()], actor: auth()->user(), subjectLabel: $student->name);

        return response()->streamDownload(fn () => print Pdf::loadView('exports.student-development-report', compact('student', 'records'))->output(), 'development-report-'.str($student->name)->slug().'.pdf');
    }

    private function recordQuery(User $student)
    {
        $query = StudentActivityRecord::query()->with(['recognition', 'academicYear', 'semester', 'subject', 'club', 'event', 'media'])->where('student_id', $student->id)->where('eligible_timeline', true);
        if (auth()->user()->hasRole('parent')) {
            $query->approved();
        } elseif (auth()->user()->hasRole('student')) {
            $query->where(fn ($q) => $q->approved()->orWhere('submitted_by', auth()->id()));
        }

        return $query->when($this->categoryFilter !== 'all', fn ($q) => $q->where('category', $this->categoryFilter))
            ->when($this->classificationFilter !== 'all', fn ($q) => $q->where('classification', $this->classificationFilter))
            ->when($this->academicYearFilter !== 'all', fn ($q) => $q->where('academic_year_id', $this->academicYearFilter))
            ->when($this->semesterFilter !== 'all', fn ($q) => $q->where('semester_id', $this->semesterFilter));
    }

    public function categories(): array
    {
        return ['academic' => 'Academic', 'recognition' => 'Recognition', 'leadership' => 'Leadership', 'service' => 'Service', 'club' => 'Club', 'sport' => 'Sport', 'creative' => 'Creative work', 'personal_growth' => 'Personal growth', 'wellbeing' => 'Wellbeing', 'other' => 'Other'];
    }

    public function render(StudentRecordAccessService $access)
    {
        $students = $access->permittedStudents(auth()->user(), 'timeline')->orderBy('name')->get();
        $student = $students->firstWhere('id', $this->studentId);
        $records = $student ? $this->recordQuery($student)->latest('occurred_on')->latest('id')->get() : collect();
        $years = AcademicYear::query()->orderByDesc('start_year')->get();
        $semesters = Semester::query()->orderBy('academic_year_id')->orderBy('name')->get();
        $subjects = Subject::query()->active()->orderBy('name')->get();
        $clubs = Club::query()->where('is_active', true)->orderBy('name')->get();
        $events = SchoolEvent::query()->orderByDesc('starts_at')->limit(100)->get();
        $canContribute = $student && (auth()->user()->hasRole('student') || $access->canContribute(auth()->user(), $student));
        $categories = $this->categories();

        return view('livewire.students.growth-timeline', compact('students', 'student', 'records', 'years', 'semesters', 'subjects', 'clubs', 'events', 'canContribute', 'categories'))->layout('layouts.dashboard')->title('Student Growth Timeline');
    }
}
