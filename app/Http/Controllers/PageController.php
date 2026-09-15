<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Models\StaffProfile;
use App\Support\SiteSettings;

class PageController extends Controller
{
    public function home()
    {
        return view('livewire.site.home');
    }

    public function about()
    {
        $administrators = $this->publicStaff()
            ->whereHas('department', fn ($query) => $query->where('category', 'management'))
            ->get();

        return view('livewire.site.about', compact('administrators'));
    }

    public function academics()
    {
        return view('livewire.site.academics');
    }

    public function whyWatersprings()
    {
        return view('livewire.site.why-watersprings');
    }

    public function contact()
    {
        return view('livewire.site.contact');
    }

    public function admission()
    {
        return view('livewire.site.admission');
    }

    public function gallery()
    {
        return view('livewire.site.gallery');
    }

    public function prospectus()
    {
        return view('livewire.site.prospectus');
    }

    public function team()
    {
        $staff = $this->publicStaff()->get()->groupBy(fn ($profile) => $profile->department?->name ?: 'Other Staff');

        return view('livewire.site.team', compact('staff'));
    }

    public function staffProfile(StaffProfile $staffProfile)
    {
        $school = $this->publicSchool();
        abort_unless($school && $staffProfile->school_id === $school->id && $staffProfile->is_public, 404);
        $staffProfile->load(['user', 'department', 'school']);

        return view('livewire.site.staff-profile', compact('staffProfile'));
    }

    protected function publicStaff()
    {
        $school = $this->publicSchool();

        return StaffProfile::query()->with(['user', 'department'])->where('school_id', $school?->id ?? 0)
            ->where('is_public', true)->whereHas('user', fn ($query) => $query->whereNull('deleted_at'))
            ->orderBy('display_order')->orderBy('job_title');
    }

    protected function publicSchool(): ?School
    {
        return SiteSettings::resolveSchool(request())
            ?? School::query()->where('name', config('app.name'))->first()
            ?? School::query()->latest('id')->first();
    }
}
