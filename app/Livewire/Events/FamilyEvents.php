<?php

namespace App\Livewire\Events;

use App\Models\ParentRecord;
use App\Models\SchoolEventResponse;
use App\Services\SchoolEventService;
use Livewire\Component;

class FamilyEvents extends Component
{
    public ?int $editingResponseId = null;

    public string $participationStatus = 'attending';

    public string $consentStatus = 'granted';

    public string $emergencyContactName = '';

    public string $emergencyContactPhone = '';

    public string $emergencyContactRelationship = '';

    public string $guardianNote = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('respond school event'), 403);
    }

    public function openResponse(int $responseId): void
    {
        $response = $this->responses()->findOrFail($responseId);
        $this->editingResponseId = $response->id;
        $this->participationStatus = in_array($response->participation_status, ['attending', 'not_attending'], true) ? $response->participation_status : 'attending';
        $this->consentStatus = in_array($response->consent_status, ['granted', 'declined'], true) ? $response->consent_status : 'granted';
        $this->emergencyContactName = (string) $response->emergency_contact_name;
        $this->emergencyContactPhone = (string) $response->emergency_contact_phone;
        $this->emergencyContactRelationship = (string) $response->emergency_contact_relationship;
        $this->guardianNote = (string) $response->guardian_note;
    }

    public function submit(SchoolEventService $events): void
    {
        $validated = $this->validate([
            'participationStatus' => ['required', 'in:attending,not_attending'], 'consentStatus' => ['required', 'in:granted,declined'],
            'emergencyContactName' => ['nullable', 'string', 'max:255'], 'emergencyContactPhone' => ['nullable', 'string', 'max:40'],
            'emergencyContactRelationship' => ['nullable', 'string', 'max:80'], 'guardianNote' => ['nullable', 'string', 'max:1000'],
        ]);
        $response = $this->responses()->findOrFail($this->editingResponseId);
        $events->respond($response->event, $response->student, auth()->user(), [
            'participation_status' => $validated['participationStatus'], 'consent_status' => $validated['consentStatus'],
            'emergency_contact_name' => $validated['emergencyContactName'], 'emergency_contact_phone' => $validated['emergencyContactPhone'],
            'emergency_contact_relationship' => $validated['emergencyContactRelationship'], 'guardian_note' => $validated['guardianNote'],
        ]);
        $this->reset(['editingResponseId', 'emergencyContactName', 'emergencyContactPhone', 'emergencyContactRelationship', 'guardianNote']);
        session()->flash('success', 'Your event response was recorded.');
    }

    private function responses()
    {
        $links = ParentRecord::query()->active()->where('user_id', auth()->id())->whereNotNull('verified_at')->get(['student_id', 'can_consent_events']);
        $studentIds = $links->pluck('student_id');
        $consentStudentIds = $links->where('can_consent_events', true)->pluck('student_id');

        return SchoolEventResponse::query()->whereIn('student_id', $studentIds)
            ->where(function ($query) use ($consentStudentIds): void {
                $query->whereHas('event', fn ($event) => $event->where('requires_consent', false))
                    ->orWhere(function ($consent) use ($consentStudentIds): void {
                        $consent->whereIn('student_id', $consentStudentIds)
                            ->whereHas('event', fn ($event) => $event->where('requires_consent', true));
                    });
            })
            ->whereHas('event', fn ($query) => $query->where('school_id', auth()->user()->school_id)->whereIn('status', ['published', 'cancelled']));
    }

    public function render()
    {
        $responses = $this->responses()->with(['event.classes', 'student'])->whereHas('event', fn ($query) => $query->where('starts_at', '>=', now()->subDay()))->get()->sortBy('event.starts_at');
        $editingResponse = $this->editingResponseId ? $responses->firstWhere('id', $this->editingResponseId) : null;

        return view('livewire.events.family-events', compact('responses', 'editingResponse'))->layout('layouts.dashboard')->title('Family Events');
    }
}
