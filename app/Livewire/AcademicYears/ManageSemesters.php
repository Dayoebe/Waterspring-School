<?php

namespace App\Livewire\AcademicYears;

use App\Models\Semester;
use Livewire\Component;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ManageSemesters extends Component
{
    use AuthorizesRequests;

    public $semesters;
    public $selectedSemesterId;
    public $termName = '';
    public $themeTitle = '';
    public $themeDescription = '';
    public $themeScripture = '';
    public $themeFocus = '';
    public $themeColor = '#0875a5';
    public $startsOn = '';
    public $endsOn = '';
    public $editMode = false;
    public $editingId = null;
    public $showForm = false;

    protected $rules = [
        'termName' => 'required|string|max:255',
        'themeTitle' => 'required|string|max:255',
        'themeDescription' => 'nullable|string|max:3000',
        'themeScripture' => 'nullable|string|max:255',
        'themeFocus' => 'nullable|string|max:3000',
        'themeColor' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        'startsOn' => 'nullable|required_with:endsOn|date',
        'endsOn' => 'nullable|required_with:startsOn|date|after_or_equal:startsOn',
    ];

    public function mount()
    {
        $this->authorize('viewAny', Semester::class);
        $this->loadSemesters();
        $this->selectedSemesterId = auth()->user()->school->semester_id;
    }

    public function loadSemesters()
    {
        $academicYear = auth()->user()->school->academicYear;

        if ($academicYear) {
            $this->semesters = Semester::query()
                ->where('academic_year_id', $academicYear->id)
                ->orderBy('id')
                ->get();
        } else {
            $this->semesters = collect();
        }
    }

    public function toggleForm()
    {
        $this->showForm = !$this->showForm;
        if (!$this->showForm) {
            $this->resetForm();
        }
    }

    public function create()
    {
        $this->authorize('create', Semester::class);

        $this->validate();

        Semester::create([
            'name' => $this->termName,
            'school_id' => auth()->user()->school_id,
            'academic_year_id' => auth()->user()->school->academic_year_id,
            ...$this->themeData(),
        ]);

        $this->loadSemesters();
        $this->resetForm();
        session()->flash('success', 'Custom term created successfully');
    }

    public function edit($id)
    {
        $semester = $this->getSemesterForCurrentSchool($id);
        $this->authorize('update', $semester);

        $this->editingId = $id;
        $this->termName = $semester->name;
        $this->themeTitle = (string) $semester->theme_title;
        $this->themeDescription = (string) $semester->theme_description;
        $this->themeScripture = (string) $semester->theme_scripture;
        $this->themeFocus = (string) $semester->theme_focus;
        $this->themeColor = $semester->theme_color ?: '#0875a5';
        $this->startsOn = $semester->starts_on?->format('Y-m-d') ?? '';
        $this->endsOn = $semester->ends_on?->format('Y-m-d') ?? '';
        $this->editMode = true;
        $this->showForm = true;
    }

    public function update()
    {
        $semester = $this->getSemesterForCurrentSchool($this->editingId);
        $this->authorize('update', $semester);

        $this->validate();

        $semester->update([
            'name' => $this->termName,
            ...$this->themeData(),
        ]);

        $this->loadSemesters();
        $this->resetForm();
        session()->flash('success', 'Term updated successfully');
    }

    public function delete($id)
    {
        $semester = $this->getSemesterForCurrentSchool($id);
        $this->authorize('delete', $semester);

        // Prevent deleting the current term.
        if ($semester->id == auth()->user()->school->semester_id) {
            session()->flash('danger', 'Cannot delete the current term. Please set a different term first.');
            return;
        }

        $semester->delete();

        $this->loadSemesters();
        session()->flash('success', 'Term deleted successfully');
    }

    public function setSemester()
    {
        $this->authorize('setSemester', Semester::class);
        
        $this->validate([
            'selectedSemesterId' => 'required|exists:semesters,id',
        ]);

        $semester = Semester::query()
            ->findOrFail($this->selectedSemesterId);

        if ($semester->academic_year_id !== auth()->user()->school->academic_year_id) {
            session()->flash('danger', 'The selected term does not belong to the current academic year.');
            return;
        }

        $school = auth()->user()->school;

        $school->update([
            'semester_id' => $semester->id,
        ]);

        $school->refresh();
        auth()->user()->unsetRelation('school');
        $this->selectedSemesterId = $school->semester_id;
        $this->loadSemesters();
        session()->flash('success', 'Successfully set current term');
        $this->dispatch('$refresh');
    }

    protected function getSemesterForCurrentSchool($id): Semester
    {
        $query = Semester::query();

        if (auth()->user()->school->academic_year_id) {
            $query->where('academic_year_id', auth()->user()->school->academic_year_id);
        }

        return $query->findOrFail($id);
    }

    public function resetForm()
    {
        $this->reset(['termName', 'themeTitle', 'themeDescription', 'themeScripture', 'themeFocus', 'startsOn', 'endsOn']);
        $this->themeColor = '#0875a5';
        $this->editMode = false;
        $this->editingId = null;
        $this->showForm = false;
        $this->resetValidation();
    }

    protected function themeData(): array
    {
        return [
            'theme_title' => trim($this->themeTitle),
            'theme_description' => trim($this->themeDescription) ?: null,
            'theme_scripture' => trim($this->themeScripture) ?: null,
            'theme_focus' => trim($this->themeFocus) ?: null,
            'theme_color' => $this->themeColor,
            'starts_on' => $this->startsOn ?: null,
            'ends_on' => $this->endsOn ?: null,
        ];
    }

    public function render()
    {
        return view('livewire.academic-years.manage-semesters')
            ->layout('layouts.dashboard');
    }
}
