<?php

namespace App\Livewire\Students;

use App\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class StudentCredentialReadiness extends Component
{
    use WithPagination;

    public string $search = '';
    public ?int $editingStudentId = null;
    public string $email = '';
    public string $password = '';
    public string $passwordConfirmation = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('update student'), 403);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function edit(int $studentId): void
    {
        $student = $this->studentsNeedingAttention()->findOrFail($studentId);
        $this->editingStudentId = $student->id;
        $this->email = $student->email_is_placeholder ? '' : (string) $student->email;
        $this->reset(['password', 'passwordConfirmation']);
        $this->resetValidation();
    }

    public function save(): void
    {
        $student = $this->studentsNeedingAttention()->findOrFail($this->editingStudentId);
        $passwordRequired = $student->requires_password_change || blank($student->password);

        $this->validate([
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($student->id)],
            'password' => [$passwordRequired ? 'required' : 'nullable', 'string', 'min:8', 'same:passwordConfirmation'],
            'passwordConfirmation' => [$passwordRequired ? 'required' : 'nullable', 'string'],
        ], [
            'password.same' => 'The password confirmation does not match.',
        ]);

        $payload = [
            'email' => strtolower(trim($this->email)),
            'email_is_placeholder' => false,
            'email_verified_at' => now(),
        ];

        if ($this->password !== '') {
            $payload['password'] = $this->password;
            $payload['requires_password_change'] = false;
        }

        $student->forceFill($payload)->save();
        $this->cancel();
        session()->flash('success', 'Student login details completed successfully.');
    }

    public function cancel(): void
    {
        $this->reset(['editingStudentId', 'email', 'password', 'passwordConfirmation']);
        $this->resetValidation();
    }

    protected function studentsNeedingAttention()
    {
        return User::query()
            ->role('student')
            ->where('school_id', auth()->user()->school_id)
            ->whereHas('studentRecord')
            ->where(function ($query): void {
                $query->where('email_is_placeholder', true)
                    ->orWhere('requires_password_change', true)
                    ->orWhereNull('email')
                    ->orWhere('email', '')
                    ->orWhereNull('password')
                    ->orWhere('password', '');
            });
    }

    public function render()
    {
        $students = $this->studentsNeedingAttention()
            ->with(['studentRecord.baseClass', 'studentRecord.section'])
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($query): void {
                    $query->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('email', 'like', '%'.$this->search.'%')
                        ->orWhereHas('studentRecord', fn ($record) => $record->where('admission_number', 'like', '%'.$this->search.'%'));
                });
            })
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.students.student-credential-readiness', compact('students'))
            ->layout('layouts.dashboard', [
                'description' => 'Complete student email addresses and secure login credentials after admission.',
                'icon' => 'fas fa-user-shield',
                'breadcrumbs' => [
                    ['href' => route('dashboard'), 'text' => 'Dashboard'],
                    ['href' => route('students.index'), 'text' => 'Students'],
                    ['href' => route('students.credentials'), 'text' => 'Login Readiness', 'active' => true],
                ],
            ])->title('Student Login Readiness');
    }
}
