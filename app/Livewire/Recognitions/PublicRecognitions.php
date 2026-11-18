<?php

namespace App\Livewire\Recognitions;

use App\Models\AcademicYear;
use App\Models\MyClass;
use App\Models\School;
use App\Models\StudentRecognition;
use App\Support\SiteSettings;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithPagination;

class PublicRecognitions extends Component
{
    use WithPagination;

    public string $classFilter = '';

    public string $categoryFilter = '';

    public string $academicYearFilter = '';

    public string $yearFilter = '';

    protected $queryString = ['classFilter' => ['except' => ''], 'categoryFilter' => ['except' => ''], 'academicYearFilter' => ['except' => ''], 'yearFilter' => ['except' => '']];

    public function updated($property): void
    {
        if (str_ends_with($property, 'Filter')) {
            $this->resetPage();
        }
    }

    public function render()
    {
        $school = SiteSettings::resolveSchool()
            ?? School::query()->where('name', config('app.name'))->first()
            ?? School::query()->latest('id')->first();
        $schoolId = $school?->id ?? 0;
        $weekStart = now('Africa/Lagos')->startOfWeek(Carbon::MONDAY)->toDateString();
        $base = StudentRecognition::query()->where('school_id', $schoolId)->publiclyVisible()->with(['student', 'myClass', 'academicYear']);
        $current = (clone $base)->whereDate('week_start_date', $weekStart)->orderBy('my_class_id')->get();
        $archive = (clone $base)
            ->when($this->classFilter, fn ($q) => $q->where('my_class_id', $this->classFilter))
            ->when($this->categoryFilter, fn ($q) => $q->where('category', $this->categoryFilter))
            ->when($this->academicYearFilter, fn ($q) => $q->where('academic_year_id', $this->academicYearFilter))
            ->when($this->yearFilter, fn ($q) => $q->whereYear('week_start_date', $this->yearFilter))
            ->latest('week_start_date')->latest('id')->paginate(12);
        $classes = MyClass::query()->whereHas('classGroup', fn ($q) => $q->where('school_id', $schoolId))->instructional()->orderBy('name')->get();
        $years = AcademicYear::query()->where('school_id', $schoolId)->orderByDesc('start_year')->get();
        $calendarYears = StudentRecognition::query()->where('school_id', $schoolId)->publiclyVisible()->selectRaw('YEAR(week_start_date) as year')->distinct()->orderByDesc('year')->pluck('year');

        return view('livewire.recognitions.public-recognitions', compact('current', 'archive', 'classes', 'years', 'calendarYears'));
    }
}
