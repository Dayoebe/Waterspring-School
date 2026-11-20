<?php

namespace App\Livewire\Events;

use App\Models\MyClass;
use App\Models\SchoolEvent;
use App\Models\SchoolEventResponse;
use App\Services\AuditTrail;
use App\Services\SchoolEventService;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class ManageEvents extends Component
{
    use WithPagination;

    public ?int $editingId = null;

    public ?int $selectedEventId = null;

    public string $title = '';

    public string $description = '';

    public string $category = 'school_event';

    public string $startsAt = '';

    public string $endsAt = '';

    public string $location = '';

    public ?int $capacity = null;

    public bool $allDay = false;

    public bool $requiresRsvp = true;

    public bool $requiresConsent = false;

    public bool $requiresEmergencyContact = false;

    public string $responseDeadline = '';

    public array $audiences = ['parent', 'student'];

    public array $classIds = [];

    public string $cancellationReason = '';

    public string $statusFilter = 'all';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('manage school events'), 403);
    }

    public function create(): void
    {
        $this->resetForm();
        $this->startsAt = now()->addWeek()->format('Y-m-d\TH:i');
        $this->responseDeadline = now()->addDays(5)->format('Y-m-d\TH:i');
    }

    public function edit(int $eventId): void
    {
        $event = SchoolEvent::query()->findOrFail($eventId);
        $this->editingId = $event->id;
        $this->selectedEventId = $event->id;
        $this->title = $event->title;
        $this->description = (string) $event->description;
        $this->category = $event->category;
        $this->startsAt = $event->starts_at->format('Y-m-d\TH:i');
        $this->endsAt = $event->ends_at?->format('Y-m-d\TH:i') ?? '';
        $this->location = (string) $event->location;
        $this->capacity = $event->capacity;
        $this->allDay = $event->all_day;
        $this->requiresRsvp = $event->requires_rsvp;
        $this->requiresConsent = $event->requires_consent;
        $this->requiresEmergencyContact = $event->requires_emergency_contact;
        $this->responseDeadline = $event->response_deadline?->format('Y-m-d\TH:i') ?? '';
        $this->audiences = $event->audiences ?: ['parent', 'student'];
        $this->classIds = $event->classes()->pluck('my_classes.id')->map(fn ($id) => (string) $id)->all();
    }

    public function save(): void
    {
        $validated = $this->validateForm();
        $event = $this->editingId ? SchoolEvent::query()->findOrFail($this->editingId) : new SchoolEvent(['created_by' => auth()->id()]);
        $oldStartsAt = $event->starts_at?->toDateTimeString();
        $classes = MyClass::query()->whereIn('id', $validated['classIds'])->whereHas('classGroup', fn ($query) => $query->where('school_id', auth()->user()->school_id))->pluck('id');
        abort_unless($classes->count() === count(array_unique($validated['classIds'])), 422, 'One or more selected classes are invalid.');
        $event->fill([
            'school_id' => auth()->user()->school_id, 'title' => trim($validated['title']),
            'description' => filled($validated['description']) ? trim($validated['description']) : null,
            'category' => $validated['category'], 'starts_at' => $validated['startsAt'],
            'ends_at' => $validated['endsAt'] ?: null, 'all_day' => $validated['allDay'],
            'location' => filled($validated['location']) ? trim($validated['location']) : null,
            'capacity' => $validated['capacity'] ?: null, 'requires_rsvp' => $validated['requiresRsvp'],
            'requires_consent' => $validated['requiresConsent'], 'requires_emergency_contact' => $validated['requiresEmergencyContact'],
            'response_deadline' => $validated['responseDeadline'] ?: null,
            'audiences' => in_array('all', $validated['audiences'], true) ? ['all'] : array_values(array_unique($validated['audiences'])),
            'status' => $event->exists ? $event->status : 'draft',
        ])->save();
        $event->classes()->sync($classes);
        if ($event->status === 'published') {
            app(SchoolEventService::class)->syncParticipants($event);
        }
        if ($oldStartsAt && $oldStartsAt !== $event->starts_at->toDateTimeString()) {
            app(AuditTrail::class)->record((int) $event->school_id, 'events', 'event.rescheduled', 'A school event was rescheduled.', $event, ['starts_at' => $oldStartsAt], ['starts_at' => $event->starts_at->toDateTimeString()], actor: auth()->user());
        }
        $this->editingId = $event->id;
        $this->selectedEventId = $event->id;
        session()->flash('success', 'Event saved successfully.');
    }

    public function publish(int $eventId): void
    {
        app(SchoolEventService::class)->publish(SchoolEvent::query()->findOrFail($eventId), auth()->user());
        session()->flash('success', 'Event published and participant responses prepared.');
    }

    public function cancelEvent(int $eventId): void
    {
        $this->validate(['cancellationReason' => ['required', 'string', 'min:5', 'max:1000']]);
        app(SchoolEventService::class)->cancel(SchoolEvent::query()->findOrFail($eventId), auth()->user(), $this->cancellationReason);
        $this->cancellationReason = '';
        session()->flash('success', 'Event cancelled. The reason is retained for families and reporting.');
    }

    public function selectEvent(int $eventId): void
    {
        $this->selectedEventId = SchoolEvent::query()->findOrFail($eventId)->id;
    }

    public function markAttendance(int $responseId, string $status): void
    {
        $response = SchoolEventResponse::query()->whereHas('event', fn ($query) => $query->where('school_id', auth()->user()->school_id))->findOrFail($responseId);
        app(SchoolEventService::class)->markAttendance($response, auth()->user(), $status);
        session()->flash('success', 'Event attendance updated.');
    }

    public function completeEvent(int $eventId): void
    {
        app(SchoolEventService::class)->complete(SchoolEvent::query()->findOrFail($eventId), auth()->user());
        session()->flash('success', 'Event marked complete.');
    }

    private function validateForm(): array
    {
        return $this->validate([
            'title' => ['required', 'string', 'max:180'], 'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['required', Rule::in(['school_event', 'parent_meeting', 'assembly', 'sports', 'excursion', 'celebration', 'academic', 'club', 'programme', 'event', 'deadline', 'holiday', 'meeting', 'activity'])],
            'startsAt' => ['required', 'date'], 'endsAt' => ['nullable', 'date', 'after_or_equal:startsAt'],
            'location' => ['nullable', 'string', 'max:180'], 'capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'allDay' => ['boolean'], 'requiresRsvp' => ['boolean'], 'requiresConsent' => ['boolean'],
            'requiresEmergencyContact' => ['boolean'], 'responseDeadline' => ['nullable', 'date', 'before:startsAt'],
            'audiences' => ['required', 'array', 'min:1'], 'audiences.*' => [Rule::in(['all', 'staff', 'teacher', 'student', 'parent'])],
            'classIds' => ['array'], 'classIds.*' => ['integer'],
        ]);
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'selectedEventId', 'title', 'description', 'startsAt', 'endsAt', 'location', 'capacity', 'allDay', 'requiresConsent', 'requiresEmergencyContact', 'responseDeadline', 'classIds', 'cancellationReason']);
        $this->category = 'school_event';
        $this->requiresRsvp = true;
        $this->audiences = ['parent', 'student'];
        $this->resetValidation();
    }

    public function render()
    {
        $events = SchoolEvent::query()->withCount(['responses as attending_count' => fn ($q) => $q->where('participation_status', 'attending'), 'responses as pending_count' => fn ($q) => $q->where('participation_status', 'pending')])
            ->when($this->statusFilter !== 'all', fn ($query) => $query->where('status', $this->statusFilter))->latest('starts_at')->paginate(12);
        $selectedEvent = $this->selectedEventId ? SchoolEvent::query()
            ->with(['responses.student', 'responses.guardian', 'reminderDeliveries.guardian'])
            ->withCount('responses')->find($this->selectedEventId) : null;
        $classes = MyClass::query()->whereHas('classGroup', fn ($query) => $query->where('school_id', auth()->user()->school_id))->instructional()->orderBy('name')->get(['id', 'name']);

        return view('livewire.events.manage-events', compact('events', 'selectedEvent', 'classes'))->layout('layouts.dashboard')->title('School Events');
    }
}
