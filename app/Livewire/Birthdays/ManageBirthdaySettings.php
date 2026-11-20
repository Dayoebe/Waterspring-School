<?php

namespace App\Livewire\Birthdays;

use App\Models\BirthdayEmailDelivery;
use App\Models\BirthdaySetting;
use App\Models\User;
use App\Services\BirthdayService;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;

class ManageBirthdaySettings extends Component
{
    use WithPagination;

    public bool $featureEnabled = true;

    public bool $showStudents = true;

    public bool $showStaff = true;

    public string $viewerScope = 'authenticated';

    public bool $wishesEnabled = true;

    public bool $automatedEmailsEnabled = false;

    public bool $automatedStudentEmails = false;

    public bool $automatedStaffEmails = false;

    public string $deliveryTime = '08:00';

    public string $timezone = 'Africa/Lagos';

    public string $senderName = '';

    public string $senderEmail = '';

    public string $automatedSubject = 'Happy Birthday, {name}!';

    public string $automatedMessage = '';

    public string $privacySearch = '';

    public function mount(BirthdayService $birthdays): void
    {
        abort_unless(auth()->user()?->can('manage birthday settings'), 403);
        $this->fillFrom($birthdays->settings((int) auth()->user()->school_id));
    }

    public function updatedPrivacySearch(): void
    {
        $this->resetPage('privacyPage');
    }

    public function save(BirthdayService $birthdays): void
    {
        abort_unless(auth()->user()?->can('manage birthday settings'), 403);
        $validated = $this->validate([
            'featureEnabled' => ['boolean'],
            'showStudents' => ['boolean'],
            'showStaff' => ['boolean'],
            'viewerScope' => ['required', 'in:authenticated,staff'],
            'wishesEnabled' => ['boolean'],
            'automatedEmailsEnabled' => ['boolean'],
            'automatedStudentEmails' => ['boolean'],
            'automatedStaffEmails' => ['boolean'],
            'deliveryTime' => ['required', 'date_format:H:i'],
            'timezone' => ['required', 'timezone'],
            'senderName' => ['nullable', 'string', 'max:120'],
            'senderEmail' => ['nullable', 'email:rfc', 'max:255'],
            'automatedSubject' => ['required', 'string', 'max:160'],
            'automatedMessage' => ['required', 'string', 'min:20', 'max:3000'],
        ]);

        if ($this->automatedEmailsEnabled && ! $this->automatedStudentEmails && ! $this->automatedStaffEmails) {
            $this->addError('automatedEmailsEnabled', 'Choose students, staff, or both for automated greetings.');

            return;
        }

        BirthdaySetting::query()->updateOrCreate(
            ['school_id' => auth()->user()->school_id],
            [
                'feature_enabled' => $validated['featureEnabled'],
                'show_students' => $validated['showStudents'],
                'show_staff' => $validated['showStaff'],
                'viewer_scope' => $validated['viewerScope'],
                'wishes_enabled' => $validated['wishesEnabled'],
                'automated_emails_enabled' => $validated['automatedEmailsEnabled'],
                'automated_student_emails' => $validated['automatedStudentEmails'],
                'automated_staff_emails' => $validated['automatedStaffEmails'],
                'delivery_time' => $validated['deliveryTime'],
                'timezone' => $validated['timezone'],
                'sender_name' => trim($validated['senderName']) ?: null,
                'sender_email' => trim($validated['senderEmail']) ?: null,
                'automated_subject' => trim($validated['automatedSubject']),
                'automated_message' => trim($validated['automatedMessage']),
                'public_visibility_enabled' => false,
                'greeting_wall_enabled' => false,
            ],
        );

        $birthdays->clearCache((int) auth()->user()->school_id);
        session()->flash('birthday_settings_success', 'Birthday settings saved. Automated delivery remains controlled by the switches below.');
    }

    public function toggleAutomatedGreetingExclusion(int $userId, BirthdayService $birthdays): void
    {
        abort_unless(auth()->user()?->can('manage birthday settings'), 403);
        $user = User::query()
            ->where('school_id', auth()->user()->school_id)
            ->whereNotNull('birthday')
            ->findOrFail($userId);
        $user->forceFill(['birthday_is_private' => ! $user->birthday_is_private])->save();
        $birthdays->clearCache((int) auth()->user()->school_id);
    }

    public function render()
    {
        $schoolId = (int) auth()->user()->school_id;
        $people = User::query()
            ->where('school_id', $schoolId)
            ->whereNotNull('birthday')
            ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', array_merge(['student'], BirthdayService::STAFF_ROLES)))
            ->with('roles:id,name')
            ->when(trim($this->privacySearch) !== '', fn (Builder $query) => $query->where('name', 'like', '%'.trim($this->privacySearch).'%'))
            ->orderBy('name')
            ->paginate(12, ['id', 'name', 'school_id', 'birthday', 'birthday_is_private'], 'privacyPage');
        $deliveries = BirthdayEmailDelivery::query()
            ->where('school_id', $schoolId)
            ->with(['celebrant:id,name', 'sender:id,name'])
            ->latest()->limit(20)->get();
        $stats = BirthdayEmailDelivery::query()->where('school_id', $schoolId)
            ->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');

        return view('livewire.birthdays.manage-birthday-settings', compact('people', 'deliveries', 'stats'))
            ->layout('layouts.dashboard', [
                'title' => 'Birthday Settings',
                'description' => 'Control birthday visibility, greetings, and delivery status.',
                'icon' => 'fas fa-cake-candles',
            ]);
    }

    private function fillFrom(BirthdaySetting $settings): void
    {
        $this->featureEnabled = $settings->feature_enabled;
        $this->showStudents = $settings->show_students;
        $this->showStaff = $settings->show_staff;
        $this->viewerScope = $settings->viewer_scope;
        $this->wishesEnabled = $settings->wishes_enabled;
        $this->automatedEmailsEnabled = $settings->automated_emails_enabled;
        $this->automatedStudentEmails = $settings->automated_student_emails;
        $this->automatedStaffEmails = $settings->automated_staff_emails;
        $this->deliveryTime = substr((string) $settings->delivery_time, 0, 5);
        $this->timezone = $settings->timezone ?: config('birthdays.timezone');
        $this->senderName = (string) $settings->sender_name;
        $this->senderEmail = (string) $settings->sender_email;
        $this->automatedSubject = $settings->automated_subject;
        $this->automatedMessage = $settings->automated_message ?: config('birthdays.automated_message');
    }
}
