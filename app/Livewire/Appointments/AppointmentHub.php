<?php

namespace App\Livewire\Appointments;

use App\Models\AppointmentAvailabilitySlot;
use App\Models\ParentTeacherAppointment;
use App\Models\User;
use App\Services\AppointmentAccess;
use App\Services\AppointmentNotificationService;
use App\Services\ParentTeacherAppointmentService;
use DomainException;
use Livewire\Component;

class AppointmentHub extends Component
{
    public string $teacherId = '';

    public string $slotDate = '';

    public string $startTime = '';

    public string $endTime = '';

    public string $mode = 'in_person';

    public string $location = '';

    public string $slotNotes = '';

    public string $studentId = '';

    public string $slotId = '';

    public string $reason = '';

    public string $noteType = 'meeting_note';

    public string $note = '';

    public ?int $noteAppointmentId = null;

    public string $statusFilter = 'upcoming';

    public function mount(AppointmentAccess $access): void
    {
        $user = auth()->user();
        abort_unless($user?->can('manage parent teacher appointments') || $user?->can('book parent teacher appointment'), 403);
        $this->slotDate = now()->addDay()->toDateString();
        if (! $access->isLeadership($user) && $user->can('manage parent teacher appointments')) {
            $this->teacherId = (string) $user->id;
        }
        if ($user->hasRole('parent')) {
            $this->studentId = (string) ($access->children($user)->first()?->id ?? '');
        }
    }

    public function updatedStudentId(): void
    {
        $this->slotId = '';
    }

    public function createAvailability(ParentTeacherAppointmentService $service, AppointmentAccess $access): void
    {
        $this->assertStaff();
        $validated = $this->validate([
            'teacherId' => ['required', 'integer'],
            'slotDate' => ['required', 'date', 'after_or_equal:today'],
            'startTime' => ['required', 'date_format:H:i'],
            'endTime' => ['required', 'date_format:H:i'],
            'mode' => ['required', 'in:in_person,video,phone'],
            'location' => ['nullable', 'string', 'max:255'],
            'slotNotes' => ['nullable', 'string', 'max:1000'],
        ]);
        $actor = auth()->user();
        if (! $access->isLeadership($actor) && (int) $validated['teacherId'] !== (int) $actor->id) {
            abort(403);
        }

        try {
            $service->createSlot($actor, User::query()->where('school_id', $actor->school_id)->findOrFail($validated['teacherId']), [
                'starts_at' => $validated['slotDate'].' '.$validated['startTime'],
                'ends_at' => $validated['slotDate'].' '.$validated['endTime'],
                'mode' => $validated['mode'],
                'location' => $validated['location'],
                'notes' => $validated['slotNotes'],
            ]);
            $this->reset(['startTime', 'endTime', 'location', 'slotNotes']);
            session()->flash('success', 'Appointment availability added.');
        } catch (DomainException $exception) {
            session()->flash('error', $exception->getMessage());
        }
    }

    public function requestAppointment(
        ParentTeacherAppointmentService $service,
        AppointmentNotificationService $notifications
    ): void {
        abort_unless(auth()->user()?->can('book parent teacher appointment'), 403);
        $validated = $this->validate([
            'studentId' => ['required', 'integer'],
            'slotId' => ['required', 'integer'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        try {
            $slot = AppointmentAvailabilitySlot::query()->where('school_id', auth()->user()->school_id)->findOrFail($validated['slotId']);
            $appointment = $service->request(auth()->user(), (int) $validated['studentId'], $slot, $validated['reason']);
            $notifications->notifyParticipants($appointment, 'requested', (string) $appointment->id);
            $this->reset(['slotId', 'reason']);
            session()->flash('success', 'Meeting request sent to the teacher.');
        } catch (DomainException $exception) {
            session()->flash('error', $exception->getMessage());
        }
    }

    public function respond(
        int $appointmentId,
        string $status,
        ParentTeacherAppointmentService $service,
        AppointmentNotificationService $notifications
    ): void {
        $this->assertStaff();
        $appointment = $this->appointmentQuery()->findOrFail($appointmentId);
        try {
            $service->respond($appointment, auth()->user(), $status);
            $notifications->notifyParticipants($appointment->fresh(), $status, (string) $appointment->fresh()->updated_at->timestamp);
            session()->flash('success', 'Appointment request updated.');
        } catch (DomainException $exception) {
            session()->flash('error', $exception->getMessage());
        }
    }

    public function cancelAppointment(
        int $appointmentId,
        ParentTeacherAppointmentService $service,
        AppointmentNotificationService $notifications
    ): void {
        abort_unless(auth()->user()?->can('book parent teacher appointment'), 403);
        $appointment = $this->appointmentQuery()->findOrFail($appointmentId);
        try {
            $service->cancel($appointment, auth()->user());
            $notifications->notifyParticipants($appointment->fresh(), 'cancelled', (string) $appointment->fresh()->updated_at->timestamp);
            session()->flash('success', 'Appointment cancelled.');
        } catch (DomainException $exception) {
            session()->flash('error', $exception->getMessage());
        }
    }

    public function completeAppointment(
        int $appointmentId,
        ParentTeacherAppointmentService $service,
        AppointmentNotificationService $notifications
    ): void {
        $this->assertStaff();
        $appointment = $this->appointmentQuery()->findOrFail($appointmentId);
        try {
            $service->complete($appointment, auth()->user());
            $notifications->notifyParticipants($appointment->fresh(), 'completed', (string) $appointment->fresh()->updated_at->timestamp);
            session()->flash('success', 'Appointment completed.');
        } catch (DomainException $exception) {
            session()->flash('error', $exception->getMessage());
        }
    }

    public function prepareNote(int $appointmentId): void
    {
        $this->assertStaff();
        $this->appointmentQuery()->findOrFail($appointmentId);
        $this->noteAppointmentId = $appointmentId;
        $this->note = '';
    }

    public function addNote(ParentTeacherAppointmentService $service): void
    {
        $this->assertStaff();
        $validated = $this->validate([
            'noteAppointmentId' => ['required', 'integer'],
            'noteType' => ['required', 'in:meeting_note,follow_up'],
            'note' => ['required', 'string', 'min:3', 'max:3000'],
        ]);
        try {
            $service->addNote(
                $this->appointmentQuery()->findOrFail($validated['noteAppointmentId']),
                auth()->user(),
                $validated['noteType'],
                $validated['note']
            );
            $this->reset(['noteAppointmentId', 'note']);
            session()->flash('success', 'Appointment note added.');
        } catch (DomainException $exception) {
            session()->flash('error', $exception->getMessage());
        }
    }

    public function cancelSlot(int $slotId, ParentTeacherAppointmentService $service): void
    {
        $this->assertStaff();
        $slot = AppointmentAvailabilitySlot::query()->where('school_id', auth()->user()->school_id)->findOrFail($slotId);
        try {
            $service->cancelSlot($slot, auth()->user());
            session()->flash('success', 'Availability slot cancelled.');
        } catch (DomainException $exception) {
            session()->flash('error', $exception->getMessage());
        }
    }

    protected function appointmentQuery()
    {
        $user = auth()->user();
        $query = ParentTeacherAppointment::query()->where('school_id', $user->school_id);
        if ($user->hasRole('parent')) {
            return $query->where('parent_id', $user->id);
        }
        if (! app(AppointmentAccess::class)->isLeadership($user)) {
            $query->where('teacher_id', $user->id);
        }

        return $query;
    }

    protected function assertStaff(): void
    {
        abort_unless(auth()->user()?->can('manage parent teacher appointments'), 403);
    }

    public function render(AppointmentAccess $access)
    {
        $user = auth()->user();
        $isParent = $user->hasRole('parent');
        $isLeadership = $access->isLeadership($user);
        $children = $isParent ? $access->children($user) : collect();
        $teacherIds = $isParent && ctype_digit($this->studentId)
            ? $access->teacherIdsForChild($user, (int) $this->studentId)
            : collect();
        $availableSlots = $isParent ? AppointmentAvailabilitySlot::query()
            ->where('school_id', $user->school_id)
            ->whereIn('teacher_id', $teacherIds)
            ->where('status', 'available')
            ->where('starts_at', '>', now())
            ->whereDoesntHave('appointments', fn ($query) => $query->whereIn('status', ['requested', 'confirmed']))
            ->with('teacher:id,name')
            ->orderBy('starts_at')
            ->get() : collect();
        $teachers = $isLeadership ? User::query()->where('school_id', $user->school_id)->role('teacher')->orderBy('name')->get(['id', 'name']) : collect([$user]);
        $slots = ! $isParent ? AppointmentAvailabilitySlot::query()->where('school_id', $user->school_id)
            ->when(! $isLeadership, fn ($query) => $query->where('teacher_id', $user->id))
            ->where('starts_at', '>=', now()->subDay())
            ->with('teacher:id,name')->withCount(['appointments as active_appointments_count' => fn ($query) => $query->whereIn('status', ['requested', 'confirmed'])])
            ->orderBy('starts_at')->get() : collect();
        $appointments = $this->appointmentQuery()->with(['slot', 'parent:id,name,email', 'teacher:id,name,email', 'student:id,name', 'notes'])
            ->when($this->statusFilter === 'upcoming', fn ($query) => $query->whereIn('status', ['requested', 'confirmed']))
            ->when($this->statusFilter !== 'all' && $this->statusFilter !== 'upcoming', fn ($query) => $query->where('status', $this->statusFilter))
            ->latest('requested_at')->get();

        return view('livewire.appointments.appointment-hub', compact(
            'isParent', 'isLeadership', 'children', 'availableSlots', 'teachers', 'slots', 'appointments'
        ))->layout('layouts.dashboard', [
            'breadcrumbs' => [
                ['href' => route('dashboard'), 'text' => 'Dashboard'],
                ['href' => route('appointments.index'), 'text' => 'Parent-Teacher Appointments', 'active' => true],
            ],
        ])->title('Parent-Teacher Appointments');
    }
}
