<?php

namespace App\Http\Controllers;

class PageController extends Controller
{
    public function home()
    {
        return view('livewire.site.home');
    }

    public function about()
    {
        return view('livewire.site.about');
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
}
