<?php

namespace App\Livewire\Parents;

use App\Models\MyClass;
use App\Models\Section;
use App\Models\StudentRecord;
use App\Models\User;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Carbon\Carbon;

class ManageParents extends Component
{
    use WithPagination, AuthorizesRequests, WithFileUploads;

    public $mode = 'list';
    
    // Filters
    public $search = '';
    public $selectedStatus = '';
    
    // Sorting & Pagination
    public $sortField = 'name';
    public $sortDirection = 'asc';
    public $perPage = 15;
    
    // Bulk actions
    public $selectedParents = [];
    public $selectAll = false;
    
    // Parent form
    public $parentId = null;
    public $name = '';
    public $email = '';
    public $password = '';
    public $password_confirmation = '';
    public $gender = '';
    public $birthday = '';
    public $phone = '';
    public $address = '';
    public $blood_group = '';
    public $religion = '';
    public $nationality = '';
    public $state = '';
    public $city = '';
    public $profile_photo = null;

    public bool $showChildrenPanel = false;
    public string $childEntryMode = 'existing';
    public string $studentSearch = '';
    public array $selectedStudentIds = [];
    public string $newStudentName = '';
    public string $newStudentEmail = '';
    public string $newStudentPassword = '';
    public string $newStudentGender = '';
    public string $newStudentBirthday = '';
    public string $newStudentPhone = '';
    public string $newStudentClassId = '';
    public string $newStudentSectionId = '';
    public string $newStudentAdmissionNumber = '';
    public string $newStudentAdmissionDate = '';
    public $newStudentSections;

    protected $queryString = [
        'mode' => ['except' => 'list'],
        'search' => ['except' => ''],
    ];

    protected $listeners = ['refreshParents' => '$refresh'];

    public function mount()
    {
        $this->newStudentSections = collect();

        if ($this->mode === 'edit' && $this->parentId) {
            $this->loadParentForEdit();
        } elseif ($this->mode === 'create') {
            $this->resetForm();
        }
    }

    public function updatedNewStudentClassId(): void
    {
        $classId = (int) $this->newStudentClassId;
        $this->newStudentSections = $classId
            ? Section::query()->where('my_class_id', $classId)
                ->whereHas('myClass.classGroup', fn ($query) => $query->where('school_id', auth()->user()->school_id))
                ->orderBy('name')->get()
            : collect();
        $this->newStudentSectionId = '';
    }

    public function toggleChildrenPanel(): void
    {
        $this->showChildrenPanel = ! $this->showChildrenPanel;
    }

    public function updatedSelectAll($value)
    {
        if ($value) {
            $this->selectedParents = $this->getParentsQuery()
                ->pluck('id')
                ->toArray();
        } else {
            $this->selectedParents = [];
        }
    }

    public function switchMode($mode, $parentId = null)
    {
        $this->mode = $mode;
        $this->parentId = $parentId;
        $this->resetValidation();
        
        if ($mode === 'edit' && $parentId) {
            $this->loadParentForEdit();
        } elseif ($mode === 'create') {
            $this->resetForm();
        }
    }

    public function loadParentForEdit()
    {
        $parent = User::role('parent')
            ->where('school_id', auth()->user()->school_id)
            ->findOrFail($this->parentId);
        
        // Check if user is actually a parent
        if (!$parent->hasRole('parent')) {
            abort(404);
        }
        
        $this->fill([
            'name' => $parent->name,
            'email' => $parent->email,
            'gender' => $parent->gender ?? '',
            'birthday' => $parent->birthday ? 
                ($parent->birthday instanceof Carbon ? $parent->birthday->format('Y-m-d') : $parent->birthday) : '',
            'phone' => $parent->phone ?? '',
            'address' => $parent->address ?? '',
            'blood_group' => $parent->blood_group ?? '',
            'religion' => $parent->religion ?? '',
            'nationality' => $parent->nationality ?? '',
            'state' => $parent->state ?? '',
            'city' => $parent->city ?? '',
        ]);
    }

    public function createParent()
    {
        $validated = $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8|confirmed',
            'gender' => 'required|in:male,female,Male,Female',
            'birthday' => 'nullable|date|before:today',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'blood_group' => 'nullable|string',
            'religion' => 'nullable|string',
            'nationality' => 'nullable|string',
            'state' => 'nullable|string',
            'city' => 'nullable|string',
        ]);

        if (! $this->validateChildrenPlan()) {
            return;
        }

        DB::transaction(function () {
            $user = User::create([
                'name' => $this->name,
                'email' => $this->email,
                'password' => bcrypt($this->password),
                'gender' => $this->gender,
                'birthday' => $this->birthday ?: null,
                'phone' => $this->phone,
                'address' => $this->address,
                'blood_group' => $this->blood_group,
                'religion' => $this->religion,
                'nationality' => $this->nationality,
                'state' => $this->state,
                'city' => $this->city,
                'school_id' => auth()->user()->school_id,
            ]);

            $user->assignRole('parent');
            $this->attachChildren($user);
        });

        session()->flash('success', 'Parent created successfully');
        $this->switchMode('list');
        $this->dispatch('refreshParents');
    }

    public function updateParent()
    {
        $parent = User::role('parent')
            ->where('school_id', auth()->user()->school_id)
            ->findOrFail($this->parentId);
        
        if (!$parent->hasRole('parent')) {
            abort(404);
        }
        
        $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $this->parentId,
            'gender' => 'required|in:male,female,Male,Female',
            'birthday' => 'nullable|date|before:today',
            'phone' => 'nullable|string|max:20',
            'password' => 'nullable|min:8|confirmed',
        ]);

        if (! $this->validateChildrenPlan()) {
            return;
        }

        DB::transaction(function () use ($parent) {
            $parent->update([
                'name' => $this->name,
                'email' => $this->email,
                'gender' => $this->gender,
                'birthday' => $this->birthday ?: null,
                'phone' => $this->phone,
                'address' => $this->address,
                'blood_group' => $this->blood_group,
                'religion' => $this->religion,
                'nationality' => $this->nationality,
                'state' => $this->state,
                'city' => $this->city,
            ]);

            if ($this->password) {
                $parent->update(['password' => bcrypt($this->password)]);
            }

            $this->attachChildren($parent);
        });

        session()->flash('success', 'Parent updated successfully');
        $this->switchMode('list');
    }

    public function deleteParent($parentId)
    {
        $parent = User::role('parent')
            ->where('school_id', auth()->user()->school_id)
            ->findOrFail($parentId);
        
        if (!$parent->hasRole('parent')) {
            abort(404);
        }
        
        DB::transaction(function () use ($parent) {
            // Remove parent-student relationships
            $parent->children()->detach();
            $parent->delete();
        });
        
        session()->flash('success', 'Parent deleted successfully');
    }

    public function toggleLock($parentId)
    {
        $parent = User::role('parent')
            ->where('school_id', auth()->user()->school_id)
            ->findOrFail($parentId);
        
        if (!$parent->hasRole('parent')) {
            abort(404);
        }
        
        $parent->locked = !$parent->locked;
        $parent->save();
        
        session()->flash('success', $parent->locked ? 'Parent account locked' : 'Parent account unlocked');
    }

    public function applyFilters()
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->reset(['search', 'selectedStatus']);
        $this->resetPage();
    }

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function resetForm()
    {
        $this->reset([
            'parentId', 'name', 'email', 'password', 'password_confirmation', 
            'gender', 'birthday', 'phone', 'address', 'blood_group', 
            'religion', 'nationality', 'state', 'city', 'profile_photo',
            'showChildrenPanel', 'childEntryMode', 'studentSearch', 'selectedStudentIds',
            'newStudentName', 'newStudentEmail', 'newStudentPassword', 'newStudentGender',
            'newStudentBirthday', 'newStudentPhone', 'newStudentClassId', 'newStudentSectionId',
            'newStudentAdmissionNumber', 'newStudentAdmissionDate',
        ]);
        $this->childEntryMode = 'existing';
        $this->newStudentSections = collect();
    }

    protected function validateChildrenPlan(): bool
    {
        if (! $this->showChildrenPanel) {
            return true;
        }

        if ($this->childEntryMode === 'existing') {
            $this->validate(['selectedStudentIds' => ['array'], 'selectedStudentIds.*' => ['integer']]);
            $selectedIds = collect($this->selectedStudentIds)->map(fn ($id) => (int) $id)->filter()->unique();
            $validCount = User::role('student')->where('school_id', auth()->user()->school_id)
                ->whereIn('id', $selectedIds)
                ->whereDoesntHave('parents')
                ->count();

            if ($validCount !== $selectedIds->count()) {
                $this->addError('selectedStudentIds', 'One or more selected students already have a parent or do not belong to this school.');
                return false;
            }

            return true;
        }

        if ($this->childEntryMode !== 'new') {
            $this->addError('childEntryMode', 'Choose how you want to add a student.');
            return false;
        }

        abort_unless(auth()->user()->can('create student'), 403);
        $this->validate([
            'newStudentName' => ['required', 'string', 'max:255'],
            'newStudentEmail' => ['required', 'email', 'max:255', 'unique:users,email'],
            'newStudentPassword' => ['required', 'string', 'min:8'],
            'newStudentGender' => ['required', 'in:male,female'],
            'newStudentBirthday' => ['nullable', 'date', 'before:today'],
            'newStudentPhone' => ['nullable', 'string', 'max:20'],
            'newStudentClassId' => ['required', 'integer'],
            'newStudentSectionId' => ['nullable', 'integer'],
            'newStudentAdmissionNumber' => ['nullable', 'string', 'max:100', 'unique:student_records,admission_number'],
            'newStudentAdmissionDate' => ['nullable', 'date'],
        ]);

        $classIsValid = MyClass::query()->whereKey($this->newStudentClassId)
            ->whereHas('classGroup', fn ($query) => $query->where('school_id', auth()->user()->school_id))->exists();
        if (! $classIsValid) {
            $this->addError('newStudentClassId', 'Select a class from the current school.');
            return false;
        }

        if ($this->newStudentSectionId && ! Section::query()->whereKey($this->newStudentSectionId)
            ->where('my_class_id', $this->newStudentClassId)->exists()) {
            $this->addError('newStudentSectionId', 'Select a section belonging to the chosen class.');
            return false;
        }

        return true;
    }

    protected function attachChildren(User $parent): void
    {
        if (! $this->showChildrenPanel) {
            return;
        }

        if ($this->childEntryMode === 'existing') {
            foreach (collect($this->selectedStudentIds)->map(fn ($id) => (int) $id)->filter()->unique() as $studentId) {
                DB::table('parent_records')->insert([
                    'user_id' => $parent->id,
                    'student_id' => $studentId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            return;
        }

        $student = User::create([
            'name' => trim($this->newStudentName),
            'email' => strtolower(trim($this->newStudentEmail)),
            'password' => bcrypt($this->newStudentPassword),
            'gender' => $this->newStudentGender,
            'birthday' => $this->newStudentBirthday ?: null,
            'phone' => trim($this->newStudentPhone) ?: null,
            'school_id' => auth()->user()->school_id,
        ]);
        $student->assignRole('student');

        $studentRecord = $student->studentRecord()->create([
            'my_class_id' => (int) $this->newStudentClassId,
            'section_id' => $this->newStudentSectionId ? (int) $this->newStudentSectionId : null,
            'admission_number' => trim($this->newStudentAdmissionNumber) ?: $this->generateStudentAdmissionNumber(),
            'admission_date' => $this->newStudentAdmissionDate ?: now(),
        ]);

        $academicYear = auth()->user()->school->academicYear;
        if ($academicYear) {
            $studentRecord->academicYears()->syncWithoutDetaching([$academicYear->id => [
                'my_class_id' => (int) $this->newStudentClassId,
                'section_id' => $this->newStudentSectionId ? (int) $this->newStudentSectionId : null,
            ]]);
        }

        DB::table('parent_records')->insert([
            'user_id' => $parent->id,
            'student_id' => $student->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function generateStudentAdmissionNumber(): string
    {
        $initials = auth()->user()->school->initials ?? 'SCH';
        do {
            $number = $initials.'/'.date('y').'/'.mt_rand(100000, 999999);
        } while (StudentRecord::query()->where('admission_number', $number)->exists());

        return $number;
    }

    protected function getParentsQuery()
    {
        return User::role('parent')
            ->where('school_id', auth()->user()->school_id)
            ->when($this->search, function($q) {
                $q->where(function($query) {
                    $query->where('name', 'like', '%' . $this->search . '%')
                          ->orWhere('email', 'like', '%' . $this->search . '%')
                          ->orWhere('phone', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->selectedStatus !== '', fn($q) => $q->where('locked', $this->selectedStatus))
            ->orderBy($this->sortField, $this->sortDirection);
    }

    public function render()
    {
        $parents = collect();
        
        if ($this->mode === 'list') {
            $parents = $this->getParentsQuery()
                ->withCount('children')
                ->paginate($this->perPage);
        }

        $classes = in_array($this->mode, ['create', 'edit'], true)
            ? MyClass::query()->whereHas('classGroup', fn ($query) => $query->where('school_id', auth()->user()->school_id))
                ->orderBy('name')->get(['id', 'name'])
            : collect();
        $availableStudents = in_array($this->mode, ['create', 'edit'], true) && trim($this->studentSearch) !== ''
            ? User::role('student')->where('school_id', auth()->user()->school_id)
                ->whereDoesntHave('parents')->whereHas('studentRecord')
                ->where(function ($query): void {
                    $term = '%'.trim($this->studentSearch).'%';
                    $query->where(fn ($inner) => $inner->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhereHas('studentRecord', fn ($record) => $record->where('admission_number', 'like', $term)));
                })
                ->with(['studentRecord.myClass'])->orderBy('name')->limit(50)->get()
            : collect();
        $assignedChildren = $this->mode === 'edit' && $this->parentId
            ? User::role('student')->whereHas('parents', fn ($query) => $query->where('users.id', $this->parentId))
                ->with(['studentRecord.myClass'])->orderBy('name')->get()
            : collect();

        return view('livewire.parents.manage-parents', compact('parents', 'classes', 'availableStudents', 'assignedChildren'))
            ->layout('layouts.dashboard', [
                'breadcrumbs' => [
                    ['href' => route('dashboard'), 'text' => 'Dashboard'],
                    ['href' => route('parents.index'), 'text' => 'Parents', 'active' => true]
                ]
            ])
            ->title('Manage Parents');
    }
}
