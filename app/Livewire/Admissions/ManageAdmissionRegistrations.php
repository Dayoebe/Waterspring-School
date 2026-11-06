<?php

namespace App\Livewire\Admissions;

use App\Mail\AdmissionNotificationMail;
use App\Models\AdmissionDocument;
use App\Models\AdmissionInterview;
use App\Models\AdmissionRegistration;
use App\Models\AdmissionStatusHistory;
use App\Models\MyClass;
use App\Services\AdmissionPipeline;
use DomainException;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Throwable;

class ManageAdmissionRegistrations extends Component
{
    use WithFileUploads;
    use WithPagination;

    public string $search = '';

    public string $statusFilter = 'all';

    public string $classFilter = '';

    public int $perPage = 15;

    public $classes = [];

    public ?AdmissionRegistration $selectedAdmission = null;

    public string $adminNote = '';

    public string $internalNote = '';

    public string $interviewDate = '';

    public string $interviewTime = '';

    public int $interviewDuration = 30;

    public string $interviewMode = 'in_person';

    public string $interviewLocation = '';

    public string $interviewOutcome = 'recommended';

    public string $interviewNotes = '';

    public string $offerNote = '';

    public int $offerValidDays = 14;

    public string $feeAmount = '';

    public string $feeMethod = 'bank_transfer';

    public string $feeReference = '';

    public string $feeNotes = '';

    public $pipelineDocument = null;

    public string $documentCategory = 'supporting';

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => 'all'],
        'classFilter' => ['except' => ''],
    ];

    protected array $allowedStatuses = AdmissionPipeline::STATUSES;

    public function mount(): void
    {
        if (! auth()->check() || ! auth()->user()->can('read admission registration')) {
            abort(403);
        }

        if ($this->statusFilter === 'contacted') {
            $this->statusFilter = 'reviewed';
        } elseif ($this->statusFilter === 'enrolled') {
            $this->statusFilter = 'enrolled';
        } elseif ($this->statusFilter === 'approved') {
            $this->statusFilter = 'accepted';
        } elseif ($this->statusFilter === 'rejected') {
            $this->statusFilter = 'declined';
        }

        $this->classes = $this->classesForCurrentContext();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingClassFilter(): void
    {
        $this->resetPage();
    }

    public function viewAdmission(int $admissionId): void
    {
        $admission = $this->admissionQuery()->findOrFail($admissionId);
        $this->selectedAdmission = $admission;
        $this->adminNote = (string) ($admission->admin_notes ?? '');
        $this->resetWorkflowInputs();
    }

    public function clearSelected(): void
    {
        $this->selectedAdmission = null;
        $this->adminNote = '';
        $this->resetWorkflowInputs();
    }

    public function saveAdminNote(int $admissionId): void
    {
        $this->assertCanManageAdmission();

        $admission = $this->admissionQuery()->findOrFail($admissionId);
        $note = $this->normalizedNote($this->adminNote);

        $admission->update([
            'admin_notes' => $note,
            'processed_by' => auth()->id(),
            'processed_at' => now(),
        ]);

        $this->refreshSelectedAdmission($admission->id);
        session()->flash('success', 'Admin note saved.');
    }

    public function markReviewed(int $admissionId): void
    {
        $this->assertCanReviewAdmission();

        $admission = $this->admissionQuery()->findOrFail($admissionId);
        if (! in_array($admission->status, ['pending', 'reviewed'], true)) {
            session()->flash('error', 'This application cannot be moved back to review.');

            return;
        }

        $this->transitionStatus($admission, 'reviewed', $this->normalizedNote($this->adminNote));
        $this->refreshSelectedAdmission($admission->id);
        session()->flash('success', 'Admission marked as reviewed.');
    }

    public function rejectAdmission(int $admissionId, AdmissionPipeline $pipeline): void
    {
        $this->assertCanRejectAdmission();

        $admission = $this->admissionQuery()->findOrFail($admissionId);

        try {
            $pipeline->decline($admission, auth()->user(), $this->adminNote);
        } catch (DomainException $exception) {
            session()->flash('error', $exception->getMessage());

            return;
        }
        $this->sendAdmissionNotification($admission->fresh(['school', 'myClass', 'section']), 'declined');
        $this->refreshSelectedAdmission($admission->id);
        session()->flash('success', 'Admission declined.');
    }

    public function approveAdmission(int $admissionId, AdmissionPipeline $pipeline): void
    {
        $this->convertAdmission($admissionId, $pipeline);
    }

    public function addInternalNote(int $admissionId, AdmissionPipeline $pipeline): void
    {
        $this->assertCanManageAdmission();
        try {
            $pipeline->addInternalNote(
                $this->admissionQuery()->findOrFail($admissionId),
                auth()->user(),
                $this->internalNote
            );
            $this->internalNote = '';
            $this->refreshSelectedAdmission($admissionId);
            session()->flash('success', 'Internal note added.');
        } catch (DomainException $exception) {
            session()->flash('error', $exception->getMessage());
        }
    }

    public function scheduleInterview(int $admissionId, AdmissionPipeline $pipeline): void
    {
        $this->assertCanReviewAdmission();
        $validated = $this->validate([
            'interviewDate' => ['required', 'date', 'after_or_equal:today'],
            'interviewTime' => ['required', 'date_format:H:i'],
            'interviewDuration' => ['required', 'integer', 'min:15', 'max:240'],
            'interviewMode' => ['required', 'in:in_person,video,phone'],
            'interviewLocation' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $pipeline->scheduleInterview($this->admissionQuery()->findOrFail($admissionId), auth()->user(), [
                'scheduled_at' => $validated['interviewDate'].' '.$validated['interviewTime'],
                'duration_minutes' => $validated['interviewDuration'],
                'mode' => $validated['interviewMode'],
                'location' => $validated['interviewLocation'],
            ]);
            $this->refreshSelectedAdmission($admissionId);
            session()->flash('success', 'Interview scheduled.');
        } catch (DomainException $exception) {
            session()->flash('error', $exception->getMessage());
        }
    }

    public function completeInterview(int $interviewId, AdmissionPipeline $pipeline): void
    {
        $this->assertCanReviewAdmission();
        $interview = AdmissionInterview::query()
            ->where('school_id', auth()->user()->school_id)
            ->findOrFail($interviewId);
        try {
            $pipeline->completeInterview($interview, auth()->user(), $this->interviewOutcome, $this->interviewNotes);
            $this->refreshSelectedAdmission($interview->admission_registration_id);
            session()->flash('success', 'Interview outcome recorded.');
        } catch (DomainException $exception) {
            session()->flash('error', $exception->getMessage());
        }
    }

    public function offerAdmission(int $admissionId, AdmissionPipeline $pipeline): void
    {
        $this->assertCanApproveAdmission();
        $this->validate([
            'offerNote' => ['nullable', 'string', 'max:2000'],
            'offerValidDays' => ['required', 'integer', 'min:1', 'max:90'],
        ]);
        $admission = $this->admissionQuery()->findOrFail($admissionId);
        try {
            $token = $pipeline->makeOffer($admission, auth()->user(), $this->offerNote, $this->offerValidDays);
            $fresh = $admission->fresh(['school', 'myClass', 'section']);
            $url = route('admission.offer.show', [$fresh->reference_no, $token]);
            foreach ($fresh->notificationEmails() as $email) {
                Mail::to($email)->send(new AdmissionNotificationMail($fresh, 'offered', null, $url));
            }
            $this->refreshSelectedAdmission($admissionId);
            session()->flash('success', 'Admission offer issued with a secure acceptance link.');
        } catch (DomainException $exception) {
            session()->flash('error', $exception->getMessage());
        }
    }

    public function confirmAdmissionFee(int $admissionId, AdmissionPipeline $pipeline): void
    {
        $this->assertCanApproveAdmission();
        $validated = $this->validate([
            'feeAmount' => ['required', 'numeric', 'min:0'],
            'feeMethod' => ['required', 'in:cash,bank_transfer,pos,cheque,online,waived'],
            'feeReference' => ['nullable', 'string', 'max:120'],
            'feeNotes' => ['nullable', 'string', 'max:1000'],
        ]);
        try {
            $pipeline->confirmFee($this->admissionQuery()->findOrFail($admissionId), auth()->user(), [
                'amount' => $validated['feeAmount'],
                'payment_method' => $validated['feeMethod'],
                'reference' => $validated['feeReference'],
                'notes' => $validated['feeNotes'],
            ]);
            $this->refreshSelectedAdmission($admissionId);
            session()->flash('success', 'Admission fee confirmation recorded.');
        } catch (DomainException $exception) {
            session()->flash('error', $exception->getMessage());
        }
    }

    public function convertAdmission(int $admissionId, AdmissionPipeline $pipeline): void
    {
        $this->assertCanApproveAdmission();
        if (! auth()->user()->can('create parent')) {
            abort(403);
        }
        try {
            $result = $pipeline->convertToStudentAndParent(
                $this->admissionQuery()->findOrFail($admissionId),
                auth()->user()
            );
            $fresh = $this->admissionQuery()->findOrFail($admissionId);
            $this->sendAdmissionNotification($fresh, 'enrolled', $result['student_password']);
            $this->refreshSelectedAdmission($admissionId);
            session()->flash('success', 'Student and parent accounts were created and linked.');
        } catch (DomainException $exception) {
            session()->flash('error', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);
            session()->flash('error', 'The application could not be converted safely.');
        }
    }

    public function uploadPipelineDocument(int $admissionId): void
    {
        $this->assertCanManageAdmission();
        $this->validate([
            'pipelineDocument' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:5120'],
            'documentCategory' => ['required', 'in:supporting,birth_certificate,previous_report,medical,offer,fee_receipt'],
        ]);
        $admission = $this->admissionQuery()->findOrFail($admissionId);
        $path = $this->pipelineDocument->store("admissions/{$admission->school_id}/{$admission->id}", 'local');
        try {
            AdmissionDocument::query()->create([
                'admission_registration_id' => $admission->id,
                'school_id' => $admission->school_id,
                'category' => $this->documentCategory,
                'original_name' => $this->pipelineDocument->getClientOriginalName(),
                'path' => $path,
                'disk' => 'local',
                'mime_type' => $this->pipelineDocument->getMimeType(),
                'size_bytes' => $this->pipelineDocument->getSize(),
                'uploaded_by' => auth()->id(),
            ]);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }
        $this->reset('pipelineDocument');
        $this->refreshSelectedAdmission($admissionId);
        session()->flash('success', 'Admission document uploaded securely.');
    }

    protected function sendAdmissionNotification(AdmissionRegistration $admission, string $type, ?string $temporaryPassword = null): void
    {
        foreach ($admission->notificationEmails() as $email) {
            try {
                Mail::to($email)->send(new AdmissionNotificationMail($admission, $type, $temporaryPassword));
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }

    protected function admissionQuery()
    {
        $query = AdmissionRegistration::query()->with([
            'school:id,name',
            'myClass:id,name',
            'section:id,name',
            'processedBy:id,name',
            'enrolledUser:id,name,email',
            'enrolledStudentRecord:id,admission_number,user_id',
            'enrolledParent:id,name,email',
            'documents.uploadedBy:id,name',
            'interviews.interviewer:id,name',
            'internalNotes.author:id,name',
            'feeConfirmation',
            'statusHistories' => fn ($history) => $history->with('changedBy:id,name')->latest('changed_at'),
        ]);

        $schoolId = auth()->user()?->school_id;
        if ($schoolId) {
            $query->where('school_id', $schoolId);
        } else {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }

    protected function classesForCurrentContext()
    {
        $schoolId = auth()->user()?->school_id;

        return MyClass::query()
            ->select('my_classes.id', 'my_classes.name')
            ->when($schoolId, function ($query) use ($schoolId) {
                $query->whereHas('classGroup', fn ($q) => $q->where('school_id', $schoolId));
            })
            ->when(! $schoolId, fn ($query) => $query->whereRaw('1 = 0'))
            ->orderBy('name')
            ->get();
    }

    protected function normalizedNote(?string $note): ?string
    {
        $trimmed = trim((string) $note);

        return $trimmed === '' ? null : $trimmed;
    }

    protected function resetWorkflowInputs(): void
    {
        $this->reset([
            'internalNote', 'interviewDate', 'interviewTime', 'interviewLocation',
            'interviewNotes', 'offerNote', 'feeAmount', 'feeReference', 'feeNotes',
            'pipelineDocument',
        ]);
        $this->interviewDuration = 30;
        $this->interviewMode = 'in_person';
        $this->interviewOutcome = 'recommended';
        $this->offerValidDays = 14;
        $this->feeMethod = 'bank_transfer';
        $this->documentCategory = 'supporting';
        $this->resetValidation();
    }

    protected function refreshSelectedAdmission(?int $admissionId = null): void
    {
        if (! $this->selectedAdmission && ! $admissionId) {
            return;
        }

        $id = $admissionId ?: $this->selectedAdmission?->id;
        if (! $id) {
            return;
        }

        $fresh = $this->admissionQuery()->find($id);
        $this->selectedAdmission = $fresh;
        if ($fresh) {
            $this->adminNote = (string) ($fresh->admin_notes ?? '');
        }
    }

    protected function assertCanManageAdmission(): void
    {
        if (! auth()->user()?->can('update admission admin note')) {
            abort(403);
        }
    }

    protected function assertCanReviewAdmission(): void
    {
        if (! auth()->user()?->can('review admission registration')) {
            abort(403);
        }
    }

    protected function assertCanRejectAdmission(): void
    {
        if (! auth()->user()?->can('reject admission registration')) {
            abort(403);
        }
    }

    protected function assertCanApproveAdmission(): void
    {
        if (! auth()->user()?->can('approve admission registration')) {
            abort(403);
        }

        if (! auth()->user()?->can('create student')) {
            abort(403);
        }
    }

    protected function transitionStatus(AdmissionRegistration $admission, string $toStatus, ?string $note = null): void
    {
        if (! in_array($toStatus, $this->allowedStatuses, true)) {
            return;
        }

        $fromStatus = (string) $admission->status;
        $payload = [
            'status' => $toStatus,
            'processed_by' => auth()->id(),
            'processed_at' => now(),
        ];

        if ($note !== null) {
            $payload['admin_notes'] = $note;
        }

        $admission->update($payload);

        if ($fromStatus !== $toStatus) {
            AdmissionStatusHistory::create([
                'admission_registration_id' => $admission->id,
                'school_id' => $admission->school_id,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'note' => $note,
                'changed_by' => auth()->id(),
                'changed_at' => now(),
            ]);
        }
    }

    public function render()
    {
        $query = $this->admissionQuery()
            ->when($this->statusFilter !== 'all', function ($q) {
                $this->statusFilter === 'interviews'
                    ? $q->whereIn('status', ['interview_scheduled', 'interviewed'])
                    : $q->where('status', $this->statusFilter);
            })
            ->when($this->classFilter !== '', function ($q) {
                $q->where('my_class_id', $this->classFilter);
            })
            ->when($this->search !== '', function ($q) {
                $search = '%'.trim($this->search).'%';
                $q->where(function ($inner) use ($search) {
                    $inner->where('reference_no', 'like', $search)
                        ->orWhere('student_name', 'like', $search)
                        ->orWhere('guardian_name', 'like', $search)
                        ->orWhere('guardian_phone', 'like', $search)
                        ->orWhere('student_email', 'like', $search);
                });
            })
            ->latest();

        $registrations = $query->paginate($this->perPage);

        $countBase = $this->admissionQuery();
        $statusCounts = [
            'all' => (clone $countBase)->count(),
            'pending' => (clone $countBase)->where('status', 'pending')->count(),
            'reviewed' => (clone $countBase)->where('status', 'reviewed')->count(),
            'interviews' => (clone $countBase)->whereIn('status', ['interview_scheduled', 'interviewed'])->count(),
            'offered' => (clone $countBase)->where('status', 'offered')->count(),
            'accepted' => (clone $countBase)->where('status', 'accepted')->count(),
            'declined' => (clone $countBase)->where('status', 'declined')->count(),
            'enrolled' => (clone $countBase)->where('status', 'enrolled')->count(),
        ];

        return view('livewire.admissions.manage-admission-registrations', [
            'registrations' => $registrations,
            'statusCounts' => $statusCounts,
        ])->layout('layouts.dashboard', [
            'breadcrumbs' => [
                ['href' => route('dashboard'), 'text' => 'Dashboard'],
                ['href' => route('admissions.registrations.index'), 'text' => 'Admission Registrations', 'active' => true],
            ],
        ])->title('Admission Registrations');
    }
}
