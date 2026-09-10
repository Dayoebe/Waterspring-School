<?php

namespace App\Livewire\Staff;

use App\Models\StaffDepartment;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class ManageStaffDirectory extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showForm = false;

    public ?int $profileId = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $departmentId = '';

    public string $jobTitle = '';

    public string $bio = '';

    public string $qualifications = '';

    public string $responsibilities = '';

    public string $joinedOn = '';

    public bool $isPublic = true;

    public int $displayOrder = 0;

    public string $newDepartmentName = '';

    public string $newDepartmentCategory = 'other';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('manage staff directory'), 403);
    }

    public function edit(int $id): void
    {
        $profile = StaffProfile::with('user')->where('school_id', auth()->user()->school_id)->findOrFail($id);
        $this->profileId = $profile->id;
        $this->name = $profile->user->name;
        $this->email = $profile->user->email;
        $this->departmentId = (string) $profile->staff_department_id;
        $this->jobTitle = $profile->job_title;
        $this->bio = (string) $profile->bio;
        $this->qualifications = (string) $profile->qualifications;
        $this->responsibilities = (string) $profile->responsibilities;
        $this->joinedOn = $profile->joined_on?->format('Y-m-d') ?? '';
        $this->isPublic = $profile->is_public;
        $this->displayOrder = $profile->display_order;
        $this->password = '';
        $this->showForm = true;
    }

    public function save(): void
    {
        $schoolId = auth()->user()->school_id;
        $this->validate(['name' => 'required|string|max:255', 'email' => ['required', 'email', Rule::unique('users')->ignore($this->profileId ? StaffProfile::find($this->profileId)?->user_id : null)],
            'password' => [$this->profileId ? 'nullable' : 'required', 'string', 'min:8'], 'departmentId' => ['required', Rule::exists('staff_departments', 'id')->where('school_id', $schoolId)],
            'jobTitle' => 'required|string|max:255', 'bio' => 'nullable|string|max:5000', 'qualifications' => 'nullable|string|max:3000',
            'responsibilities' => 'nullable|string|max:3000', 'joinedOn' => 'nullable|date', 'displayOrder' => 'integer|min:0']);
        DB::transaction(function () use ($schoolId): void {
            if ($this->profileId) {
                $profile = StaffProfile::where('school_id', $schoolId)->findOrFail($this->profileId);
                $data = ['name' => $this->name, 'email' => $this->email];
                if ($this->password !== '') {
                    $data['password'] = $this->password;
                }
                $profile->user->update($data);
            } else {
                $user = User::create(['name' => $this->name, 'email' => $this->email, 'password' => $this->password, 'school_id' => $schoolId]);
                $user->assignRole('user');
                $profile = new StaffProfile(['school_id' => $schoolId, 'user_id' => $user->id]);
            }
            $profile->fill(['staff_department_id' => $this->departmentId, 'job_title' => $this->jobTitle, 'bio' => $this->bio ?: null,
                'qualifications' => $this->qualifications ?: null, 'responsibilities' => $this->responsibilities ?: null,
                'joined_on' => $this->joinedOn ?: null, 'is_public' => $this->isPublic, 'display_order' => $this->displayOrder])->save();
        });
        $this->resetForm();
        session()->flash('success', 'Staff profile saved successfully.');
    }

    public function addDepartment(): void
    {
        $this->validate(['newDepartmentName' => 'required|string|max:255', 'newDepartmentCategory' => 'required|string|max:100']);
        StaffDepartment::firstOrCreate(['school_id' => auth()->user()->school_id, 'name' => trim($this->newDepartmentName)], ['category' => $this->newDepartmentCategory, 'is_active' => true]);
        $this->reset(['newDepartmentName']);
        session()->flash('success', 'Department added.');
    }

    public function resetForm(): void
    {
        $this->reset(['showForm', 'profileId', 'name', 'email', 'password', 'departmentId', 'jobTitle', 'bio', 'qualifications', 'responsibilities', 'joinedOn', 'displayOrder']);
        $this->isPublic = true;
    }

    public function render()
    {
        $profiles = StaffProfile::with(['user', 'department'])->where('school_id', auth()->user()->school_id)
            ->when($this->search, fn ($q) => $q->where(fn ($inner) => $inner->where('job_title', 'like', '%'.$this->search.'%')->orWhereHas('user', fn ($u) => $u->where('name', 'like', '%'.$this->search.'%'))))
            ->orderBy('display_order')->paginate(15);
        $departments = StaffDepartment::where('school_id', auth()->user()->school_id)->orderBy('display_order')->get();

        return view('livewire.staff.manage-staff-directory', compact('profiles', 'departments'));
    }
}
