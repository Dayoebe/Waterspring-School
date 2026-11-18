<?php

namespace App\Livewire\Recognitions;

use App\Models\MyClass;
use App\Models\Semester;
use App\Models\StudentRecognition;
use App\Models\User;
use App\Services\ClassPilot\Exceptions\ClassPilotException;
use App\Services\ClassPilot\Manager;
use App\Services\CloudinaryImageStorage;
use App\Support\RecognitionCategories;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class ManageRecognitions extends Component
{
    use WithFileUploads, WithPagination;

    public bool $showForm = false;

    public bool $showPreview = false;

    public bool $replaceExisting = false;

    public ?int $editingId = null;

    public string $classId = '';

    public string $studentId = '';

    #[Locked]
    public ?int $contextAcademicYearId = null;

    #[Locked]
    public ?int $contextSemesterId = null;

    public string $category = '';

    public string $customCategory = '';

    public string $reason = '';

    public string $message = '';

    public string $weekDate = '';

    public bool $publicDisplayApproved = false;

    public $photo = null;

    public string $statusFilter = 'all';

    public string $search = '';

    public string $reasonAiNotice = '';

    public string $messageAiNotice = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('read student recognition'), 403);
        $this->setCurrentAcademicContext();
        $this->weekDate = now('Africa/Lagos')->startOfWeek(Carbon::MONDAY)->toDateString();
    }

    public function openCreate(): void
    {
        abort_unless(auth()->user()->can('create student recognition'), 403);
        $this->resetForm();
        if (! $this->hasAcademicContext()) {
            $this->addError('academicContext', 'Set the current academic session and term before creating a recognition.');

            return;
        }
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $recognition = $this->scopedQuery()->findOrFail($id);
        $this->authorize('update', $recognition);
        $this->editingId = $recognition->id;
        $this->classId = (string) $recognition->my_class_id;
        $this->studentId = (string) $recognition->student_id;
        $this->contextAcademicYearId = (int) $recognition->academic_year_id;
        $this->contextSemesterId = $recognition->semester_id
            ? (int) $recognition->semester_id
            : ($recognition->academic_year_id === auth()->user()->school?->academic_year_id
                ? auth()->user()->school?->semester_id
                : null);
        $this->category = $recognition->category;
        $this->customCategory = (string) $recognition->custom_category;
        $this->reason = $recognition->reason;
        $this->message = $recognition->congratulatory_message;
        $this->weekDate = $recognition->week_start_date->toDateString();
        $this->publicDisplayApproved = $recognition->public_display_approved;
        $this->showForm = true;
        $this->showPreview = false;
    }

    public function generateReasonWithAi(Manager $manager): void
    {
        $this->authorizeAiDraft();
        $this->resetErrorBag(['reasonAiGeneration']);
        $this->reasonAiNotice = '';
        if (filled($this->reason)) {
            $this->addError('reasonAiGeneration', 'Clear the existing reason before asking AI for a new draft. Your text was not changed.');

            return;
        }

        $context = $this->nominationAiContext();
        if ($context === null) {
            return;
        }

        try {
            $draft = $manager->generate(auth()->user(), 'student_recognition_reason', $context, $this->emptyAiContext());
        } catch (ClassPilotException $exception) {
            $this->addError('reasonAiGeneration', $exception->getMessage());

            return;
        }

        $this->reason = trim((string) ($draft['reason'] ?? ''));
        $this->reasonAiNotice = 'AI drafted the reason. Review it, add any specific verified example, and edit it before saving.';
    }

    public function generateMessageWithAi(Manager $manager): void
    {
        $this->authorizeAiDraft();
        $this->resetErrorBag(['messageAiGeneration']);
        $this->messageAiNotice = '';
        if (filled($this->message)) {
            $this->addError('messageAiGeneration', 'Clear the existing message before asking AI for a new draft. Your text was not changed.');

            return;
        }

        $context = $this->nominationAiContext();
        if ($context === null) {
            return;
        }
        $context['verified_nomination_reason'] = filled($this->reason) ? trim($this->reason) : null;

        try {
            $draft = $manager->generate(auth()->user(), 'student_recognition_message', $context, $this->emptyAiContext());
        } catch (ClassPilotException $exception) {
            $this->addError('messageAiGeneration', $exception->getMessage());

            return;
        }

        $this->message = trim((string) ($draft['message'] ?? ''));
        $this->messageAiNotice = 'AI drafted the congratulatory message. Review and edit it before saving or publishing.';
    }

    public function preview(): void
    {
        $this->validateForm();
        $this->showPreview = true;
    }

    public function saveDraft(): void
    {
        $this->persist(false);
    }

    public function publish(): void
    {
        $this->persist(true);
    }

    public function unpublish(int $id): void
    {
        $recognition = $this->scopedQuery()->findOrFail($id);
        $this->authorize('publish', $recognition);
        $recognition->update([
            'status' => 'draft',
            'published_slot' => null,
            'published_at' => null,
            'published_by' => null,
        ]);
        session()->flash('success', 'Recognition unpublished. It is no longer visible publicly.');
    }

    public function delete(int $id): void
    {
        $recognition = $this->scopedQuery()->findOrFail($id);
        $this->authorize('delete', $recognition);
        $recognition->delete();
        session()->flash('success', 'Draft recognition deleted.');
    }

    protected function persist(bool $publish): void
    {
        $validated = $this->validateForm();
        $user = auth()->user();
        $recognition = $this->editingId ? $this->scopedQuery()->findOrFail($this->editingId) : new StudentRecognition;
        $this->authorize($recognition->exists ? 'update' : 'create', $recognition);
        abort_unless($this->hasAcademicContext(), 422, 'Set the current academic session and term first.');

        $academicYearId = (int) $this->contextAcademicYearId;
        $semesterId = (int) $this->contextSemesterId;
        $validContext = auth()->user()->school?->academicYears()->whereKey($academicYearId)->exists()
            && Semester::query()->where('school_id', $user->school_id)->whereKey($semesterId)->where('academic_year_id', $academicYearId)->exists();
        abort_unless($validContext, 422, 'The selected academic context is no longer available for this school.');

        $class = $this->manageableClasses()->firstWhere('id', (int) $validated['classId']);
        abort_unless($class, 403);
        $student = User::query()->where('school_id', $user->school_id)->role('student')->findOrFail($validated['studentId']);
        abort_unless($student->studentRecord && $class->hasStudent($student->studentRecord->id, $academicYearId), 422, 'The selected student does not belong to that class for this academic year.');

        $weekStart = Carbon::parse($validated['weekDate'])->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);
        $isAdmin = $user->hasAnyRole(['principal', 'admin', 'super-admin', 'super_admin']);
        $customNeedsApproval = $validated['category'] === RecognitionCategories::CUSTOM;

        if ($publish) {
            $this->authorize('publish', $recognition->exists ? $recognition : tap(new StudentRecognition, fn ($r) => $r->fill(['school_id' => $user->school_id, 'my_class_id' => $class->id])));
            abort_unless($validated['publicDisplayApproved'], 422, 'Confirm that the student photograph and recognition may be published.');
            abort_if($customNeedsApproval && ! $isAdmin && ! $recognition->custom_category_approved_at, 422, 'An administrator must approve a custom category before publication.');
        }

        $photoPath = $recognition->photo_path;
        if ($this->photo) {
            $storage = app(CloudinaryImageStorage::class);
            $photoPath = $storage->upload($this->photo, $storage->folder('recognitions', $user->school_id));
        }

        try {
            DB::transaction(function () use ($validated, $recognition, $user, $class, $student, $academicYearId, $semesterId, $weekStart, $weekEnd, $publish, $photoPath, $customNeedsApproval, $isAdmin) {
                if ($publish) {
                    $existing = StudentRecognition::query()
                        ->where('school_id', $user->school_id)
                        ->where('my_class_id', $class->id)
                        ->whereDate('week_start_date', $weekStart)
                        ->published()
                        ->when($recognition->exists, fn ($q) => $q->where('id', '!=', $recognition->id))
                        ->lockForUpdate()
                        ->first();

                    if ($existing && ! ($isAdmin && $this->replaceExisting)) {
                        abort(422, 'This class already has a published Face of the Week. An administrator may confirm replacement.');
                    }

                    if ($existing) {
                        $existing->update(['status' => 'draft', 'published_slot' => null, 'published_at' => null, 'published_by' => null]);
                    }
                }

                $recognition->fill([
                    'school_id' => $user->school_id,
                    'student_id' => $student->id,
                    'my_class_id' => $class->id,
                    'academic_year_id' => $academicYearId,
                    'semester_id' => $semesterId,
                    'category' => $validated['category'],
                    'custom_category' => $customNeedsApproval ? trim($validated['customCategory']) : null,
                    'reason' => trim($validated['reason']),
                    'congratulatory_message' => trim($validated['message']),
                    'week_start_date' => $weekStart,
                    'week_end_date' => $weekEnd,
                    'photo_path' => $photoPath,
                    'public_display_approved' => $validated['publicDisplayApproved'],
                    'created_by' => $recognition->created_by ?: $user->id,
                ]);

                if ($customNeedsApproval && $isAdmin) {
                    $recognition->custom_category_approved_at = now();
                    $recognition->custom_category_approved_by = $user->id;
                } elseif (! $customNeedsApproval) {
                    $recognition->custom_category_approved_at = null;
                    $recognition->custom_category_approved_by = null;
                }

                if ($publish || $recognition->status === 'published') {
                    $recognition->status = 'published';
                    $recognition->published_slot = 1;
                    $recognition->published_at ??= now();
                    $recognition->published_by ??= $user->id;
                } else {
                    $recognition->status = 'draft';
                    $recognition->published_slot = null;
                }
                $recognition->save();
            });
        } catch (QueryException $exception) {
            if (str_contains(strtolower($exception->getMessage()), 'recognitions_one_published')) {
                $this->addError('weekDate', 'This class already has a published Face of the Week for that week.');

                return;
            }
            throw $exception;
        }

        session()->flash('success', $publish ? 'Face of the Week published successfully.' : 'Recognition draft saved.');
        $this->resetForm();
    }

    protected function validateForm(): array
    {
        return $this->validate([
            'classId' => ['required', 'integer'],
            'studentId' => ['required', 'integer'],
            'category' => ['required', Rule::in(array_keys(RecognitionCategories::all()))],
            'customCategory' => [Rule::requiredIf($this->category === RecognitionCategories::CUSTOM), 'nullable', 'string', 'max:120'],
            'reason' => ['required', 'string', 'min:20', 'max:1200'],
            'message' => ['required', 'string', 'max:500'],
            'weekDate' => ['required', 'date'],
            'publicDisplayApproved' => ['boolean'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
    }

    protected function manageableClasses()
    {
        $user = auth()->user();

        return MyClass::query()->with('classGroup')
            ->whereHas('classGroup', fn ($q) => $q->where('school_id', $user->school_id))
            ->when(! $user->hasAnyRole(['principal', 'admin', 'super-admin', 'super_admin']), fn ($q) => $q->whereHas('teachers', fn ($teachers) => $teachers->whereKey($user->id)))
            ->instructional()->orderBy('name')->get();
    }

    protected function scopedQuery()
    {
        $classIds = $this->manageableClasses()->pluck('id');

        return StudentRecognition::query()->where('school_id', auth()->user()->school_id)->whereIn('my_class_id', $classIds);
    }

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'classId', 'studentId', 'category', 'customCategory', 'reason', 'message', 'photo', 'showPreview', 'showForm', 'replaceExisting', 'publicDisplayApproved', 'reasonAiNotice', 'messageAiNotice']);
        $this->setCurrentAcademicContext();
        $this->weekDate = now('Africa/Lagos')->startOfWeek(Carbon::MONDAY)->toDateString();
        $this->resetValidation();
    }

    protected function setCurrentAcademicContext(): void
    {
        $school = auth()->user()?->school;
        $this->contextAcademicYearId = $school?->academic_year_id ? (int) $school->academic_year_id : null;
        $this->contextSemesterId = $school?->semester_id ? (int) $school->semester_id : null;
    }

    protected function hasAcademicContext(): bool
    {
        return $this->contextAcademicYearId !== null && $this->contextSemesterId !== null;
    }

    protected function authorizeAiDraft(): void
    {
        if ($this->editingId) {
            $this->authorize('update', $this->scopedQuery()->findOrFail($this->editingId));

            return;
        }

        abort_unless(auth()->user()->can('create student recognition'), 403);
    }

    protected function nominationAiContext(): ?array
    {
        if (! $this->hasAcademicContext()) {
            $this->addError('academicContext', 'Set the current academic session and term before asking AI to draft recognition content.');

            return null;
        }

        $validated = $this->validate([
            'classId' => ['required', 'integer'],
            'studentId' => ['required', 'integer'],
            'category' => ['required', Rule::in(array_keys(RecognitionCategories::all()))],
            'customCategory' => [Rule::requiredIf($this->category === RecognitionCategories::CUSTOM), 'nullable', 'string', 'max:120'],
            'weekDate' => ['required', 'date'],
        ]);
        $class = $this->manageableClasses()->firstWhere('id', (int) $validated['classId']);
        abort_unless($class, 403);
        $student = User::query()
            ->where('school_id', auth()->user()->school_id)
            ->role('student')
            ->with('studentRecord')
            ->findOrFail($validated['studentId']);
        abort_unless(
            $student->studentRecord && $class->hasStudent($student->studentRecord->id, $this->contextAcademicYearId),
            422,
            'The selected student does not belong to that class for this academic year.'
        );

        $school = auth()->user()->school;
        $weekStart = Carbon::parse($validated['weekDate'])->startOfWeek(Carbon::MONDAY);

        return [
            'student_name' => $student->name,
            'class_name' => $class->name,
            'recognition_category' => RecognitionCategories::label($validated['category'], $validated['customCategory'] ?? null),
            'academic_year' => $school?->academicYear?->name,
            'term' => $school?->semester?->name,
            'week' => $weekStart->format('M j').'–'.$weekStart->copy()->endOfWeek(Carbon::SUNDAY)->format('M j, Y'),
        ];
    }

    protected function emptyAiContext(): array
    {
        return ['text' => '', 'sources' => [], 'grounded' => false];
    }

    public function render()
    {
        $classes = $this->manageableClasses();
        $students = collect();
        if ($this->classId && $this->contextAcademicYearId) {
            $students = $classes->firstWhere('id', (int) $this->classId)?->studentsForAcademicYear($this->contextAcademicYearId) ?? collect();
        }
        $selectedStudent = $this->studentId ? $students->firstWhere('id', (int) $this->studentId) : null;
        $editingRecognition = $this->editingId ? $this->scopedQuery()->find($this->editingId) : null;
        $photoPreviewUrl = $editingRecognition?->photo_path
            ? app(CloudinaryImageStorage::class)->url($editingRecognition->photo_path)
            : $selectedStudent?->profile_photo_url;
        $photoPreviewSource = $editingRecognition?->photo_path
            ? 'Current recognition photograph'
            : 'Student profile photograph';
        $academicContext = [
            'year' => $this->contextAcademicYearId ? auth()->user()->school?->academicYears()->find($this->contextAcademicYearId)?->name : null,
            'term' => $this->contextSemesterId ? Semester::query()->where('school_id', auth()->user()->school_id)->find($this->contextSemesterId)?->name : null,
        ];
        $recognitions = $this->scopedQuery()->with(['student', 'myClass', 'academicYear', 'semester', 'creator', 'publisher'])
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
            ->when(trim($this->search) !== '', fn ($q) => $q->whereHas('student', fn ($u) => $u->where('name', 'like', '%'.trim($this->search).'%')))
            ->latest('week_start_date')->latest('id')->paginate(12);

        return view('livewire.recognitions.manage-recognitions', compact('classes', 'students', 'selectedStudent', 'photoPreviewUrl', 'photoPreviewSource', 'academicContext', 'recognitions'))
            ->layout('layouts.dashboard', [
                'title' => 'Face of the Week',
                'description' => 'Select, review and publish weekly student recognition.',
                'icon' => 'fas fa-award',
            ]);
    }
}
