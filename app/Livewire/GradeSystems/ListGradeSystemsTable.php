<?php

namespace App\Livewire\GradeSystems;

use App\Models\ClassGroup;
use App\Models\GradeSystem;
use Livewire\Component;

class ListGradeSystemsTable extends Component
{
    protected $queryString = ['classGroup'];

    public $classGroups;

    public $classGroup;

    public $grades;

    protected $rules = [
        'classGroup' => 'integer',
    ];

    public function mount()
    {
        $this->classGroups = ClassGroup::query()
            ->where('school_id', auth()->user()->school_id)
            ->orderBy('name')
            ->get();

        if (auth()->user()->hasRole('student')) {
            $this->classGroup = auth()->user()->studentRecord?->myClass?->classGroup?->id;
        }

        // Get all grades for first class group if class groups is not empty
        if ($this->classGroups->isNotEmpty()) {
            $this->updatedClassGroup();
        } else {
            $this->grades = collect();
        }
    }

    public function updatedClassGroup()
    {
        if (!$this->classGroups->contains('id', (int) $this->classGroup)) {
            $this->classGroup = $this->classGroups->first()?->id;
        }

        $this->loadGrades();
    }

    protected function loadGrades(): void
    {
        $this->grades = $this->classGroup
            ? GradeSystem::query()
                ->where('class_group_id', $this->classGroup)
                ->orderByDesc('grade_from')
                ->get()
            : collect();
    }

    public function render()
    {
        $coveredScores = collect($this->grades)
            ->sum(fn (GradeSystem $grade) => max(0, ((float) $grade->grade_till - (float) $grade->grade_from) + 1));

        return view('livewire.grade-systems.list-grade-systems-table', [
            'coveredScores' => min(101, $coveredScores),
            'selectedClassGroup' => $this->classGroups->firstWhere('id', (int) $this->classGroup),
        ]);
    }
}
