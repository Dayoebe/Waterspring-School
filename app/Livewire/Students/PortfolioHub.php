<?php

namespace App\Livewire\Students;

use App\Models\AcademicYear;
use App\Models\MediaAsset;
use App\Models\StudentPortfolioItem;
use App\Models\Subject;
use App\Services\AuditTrail;
use App\Services\StudentPortfolioService;
use App\Services\StudentRecordAccessService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class PortfolioHub extends Component
{
    use WithFileUploads;

    public ?int $studentId = null;

    public string $title = '';

    public string $description = '';

    public string $category = 'project';

    public string $academicYearId = '';

    public string $semesterId = '';

    public string $subjectId = '';

    public $file = null;

    public ?int $versionItemId = null;

    public $versionFile = null;

    public string $versionSummary = '';

    public string $teacherComment = '';

    public string $statusFilter = 'active';

    public int $quotaMb = 250;

    public int $maxFileMb = 25;

    public function mount(StudentRecordAccessService $access, StudentPortfolioService $portfolio): void
    {
        $user = auth()->user();
        abort_unless($user?->canAny(['view own portfolio', 'view child portfolio', 'manage student portfolio']), 403);
        $this->studentId = $access->permittedStudents($user, 'portfolio')->orderBy('name')->value('id');
        $this->academicYearId = (string) ($user->school?->academic_year_id ?: '');
        $this->semesterId = (string) ($user->school?->semester_id ?: '');
        $setting = $portfolio->setting((int) $user->school_id);
        $this->quotaMb = (int) round($setting->per_student_quota_bytes / 1048576);
        $this->maxFileMb = (int) round($setting->per_file_max_bytes / 1048576);
    }

    public function submit(StudentRecordAccessService $access, StudentPortfolioService $portfolio): void
    {
        $student = $access->permittedStudents(auth()->user(), 'portfolio')->findOrFail($this->studentId);
        $validated = $this->validate([
            'title' => ['required', 'string', 'max:180'], 'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['required', Rule::in(array_keys($this->categories()))], 'academicYearId' => ['nullable', 'integer'],
            'semesterId' => ['nullable', 'integer'], 'subjectId' => ['nullable', 'integer'],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,ppt,pptx,txt,mp4,webm', 'max:'.($this->maxFileMb * 1024)],
        ]);
        $portfolio->submit($student, auth()->user(), [
            'title' => $validated['title'], 'description' => $validated['description'], 'category' => $validated['category'],
            'academic_year_id' => $validated['academicYearId'] ?: null, 'semester_id' => $validated['semesterId'] ?: null,
            'subject_id' => $validated['subjectId'] ?: null,
        ], $this->file);
        $this->reset(['title', 'description', 'subjectId', 'file']);
        session()->flash('success', 'Portfolio item submitted for review.');
    }

    public function addVersion(StudentRecordAccessService $access, StudentPortfolioService $portfolio): void
    {
        $item = $this->itemQuery($access)->findOrFail($this->versionItemId);
        $this->validate(['versionFile' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,ppt,pptx,txt,mp4,webm', 'max:'.($this->maxFileMb * 1024)], 'versionSummary' => ['nullable', 'string', 'max:500']]);
        $portfolio->addVersion($item, auth()->user(), $this->versionFile, $this->versionSummary);
        $this->reset(['versionItemId', 'versionFile', 'versionSummary']);
        session()->flash('success', 'A new version was added and returned for review.');
    }

    public function review(int $itemId, string $decision, StudentRecordAccessService $access, StudentPortfolioService $portfolio): void
    {
        $item = $this->itemQuery($access)->findOrFail($itemId);
        $this->validate(['teacherComment' => ['nullable', 'string', 'max:3000']]);
        $portfolio->review($item, auth()->user(), $decision, $this->teacherComment);
        $this->teacherComment = '';
        session()->flash('success', 'Portfolio review saved.');
    }

    public function archive(int $itemId, StudentRecordAccessService $access, StudentPortfolioService $portfolio): void
    {
        $portfolio->archive($this->itemQuery($access)->findOrFail($itemId), auth()->user());
        session()->flash('success', 'Portfolio item archived without deleting its history.');
    }

    public function saveQuota(StudentPortfolioService $portfolio): void
    {
        abort_unless(auth()->user()->hasAnyRole(['principal', 'admin', 'super-admin', 'super_admin']), 403);
        $validated = $this->validate(['quotaMb' => ['required', 'integer', 'min:25', 'max:10240'], 'maxFileMb' => ['required', 'integer', 'min:1', 'max:100']]);
        abort_if($validated['maxFileMb'] > $validated['quotaMb'], 422, 'The per-file limit cannot exceed the student quota.');
        $portfolio->setting((int) auth()->user()->school_id)->update(['per_student_quota_bytes' => $validated['quotaMb'] * 1048576, 'per_file_max_bytes' => $validated['maxFileMb'] * 1048576, 'updated_by' => auth()->id()]);
        session()->flash('success', 'Portfolio storage limits updated.');
    }

    public function exportPortfolio(StudentRecordAccessService $access)
    {
        $student = $access->permittedStudents(auth()->user(), 'portfolio')->findOrFail($this->studentId);
        $items = StudentPortfolioItem::query()->with(['versions.media', 'subject', 'academicYear', 'semester'])->where('student_id', $student->id)->approved()->whereNull('archived_at')->get();
        app(AuditTrail::class)->record((int) auth()->user()->school_id, 'portfolio', 'portfolio.exported', 'A controlled student portfolio index was exported.', null, metadata: ['student_id' => $student->id, 'item_count' => $items->count()], actor: auth()->user(), subjectLabel: $student->name);

        return response()->streamDownload(fn () => print Pdf::loadView('exports.student-portfolio', compact('student', 'items'))->output(), 'portfolio-'.str($student->name)->slug().'.pdf');
    }

    private function itemQuery(StudentRecordAccessService $access)
    {
        $studentIds = $access->permittedStudents(auth()->user(), 'portfolio')->pluck('id');

        return StudentPortfolioItem::query()->whereIn('student_id', $studentIds);
    }

    public function categories(): array
    {
        return ['project' => 'Project', 'essay' => 'Essay', 'artwork' => 'Artwork', 'presentation' => 'Presentation', 'photography' => 'Photography', 'performance' => 'Performance', 'research' => 'Research', 'certificate' => 'Certificate', 'other' => 'Other'];
    }

    public function render(StudentRecordAccessService $access)
    {
        $students = $access->permittedStudents(auth()->user(), 'portfolio')->orderBy('name')->get();
        $student = $students->firstWhere('id', $this->studentId);
        $items = $student ? StudentPortfolioItem::query()->with(['versions.media', 'subject', 'academicYear', 'semester', 'reviewer'])->where('student_id', $student->id)
            ->when(auth()->user()->hasRole('parent'), fn ($q) => $q->approved()->whereNull('archived_at'))
            ->when($this->statusFilter === 'active', fn ($q) => $q->whereNull('archived_at'))
            ->when($this->statusFilter !== 'active' && $this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
            ->latest()->get() : collect();
        $subjects = Subject::query()->active()->orderBy('name')->get();
        $years = AcademicYear::query()->orderByDesc('start_year')->with('semesters')->get();
        $canSubmit = $student && (auth()->user()->hasRole('student') || $access->canContribute(auth()->user(), $student));
        $usedBytes = $student ? MediaAsset::query()->where('subject_user_id', $student->id)->where('usage_area', 'portfolio')->sum('file_size') : 0;
        $categories = $this->categories();
        $quotaMb = $this->quotaMb;
        $maxFileMb = $this->maxFileMb;
        $versionItemId = $this->versionItemId;

        return view('livewire.students.portfolio-hub', compact('students', 'student', 'items', 'subjects', 'years', 'canSubmit', 'usedBytes', 'categories', 'quotaMb', 'maxFileMb', 'versionItemId'))->layout('layouts.dashboard')->title('Student Portfolio');
    }
}
