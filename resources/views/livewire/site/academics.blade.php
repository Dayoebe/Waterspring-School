@extends('layouts.app', ['mode' => 'public'])
@section('title', 'Learning at Watersprings')
@section('content')
<div class="bg-slate-50 text-slate-900">
    @include('partials.watersprings-hero', ['eyebrow' => 'Our classes and curriculum', 'title' => 'Learning for every stage of childhood', 'description' => 'Our curriculum draws on the National Curriculum of England, adapted for children learning in Nigeria. Practical experiences, creativity and individual care support academic growth.'])
    <section class="mx-auto max-w-6xl px-4 py-14 sm:px-6 lg:px-8">
        <h2 class="mb-7 text-3xl font-black">From early years to primary school</h2>
        @include('partials.watersprings-programmes')
    </section>
    <section id="clubs" class="bg-sky-50 py-12">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-black">Discover interests beyond the classroom</h2>
            <p class="mt-4 max-w-3xl leading-relaxed text-slate-600">Our clubs give children opportunities to create, communicate, move and work together. Ask the school office about current club choices and arrangements.</p>
            <ul class="mt-7 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($publicSiteSettings['clubs'] as $club)<li class="rounded-xl border border-sky-100 bg-white p-4 font-semibold text-sky-900">{{ $club }}</li>@endforeach
            </ul>
        </div>
    </section>
    <section id="college" class="mx-auto grid max-w-6xl items-start gap-8 px-4 py-14 sm:px-6 md:grid-cols-2 lg:px-8">
        <div>
            <p class="text-sm font-bold uppercase tracking-widest text-sky-700">College announcement • July 2025</p>
            <h2 class="mt-3 text-3xl font-black">Watersprings International College</h2>
            <p class="mt-5 leading-relaxed text-slate-600">The school’s announcement introduced admission into Years 7 and 8 for the 2025/2026 academic session. The notice shown here is an archive of that announcement.</p>
            <p class="mt-4 leading-relaxed text-slate-600">For current entry classes, places, fees and entrance arrangements, please speak with the school office.</p>
            <a href="{{ route('contact') }}" class="site-primary-bg mt-6 inline-block rounded-xl px-6 py-3 font-bold text-white">Ask about college admission</a>
        </div>
        <figure><a href="{{ asset('images/watersprings/college.jpg') }}"><img src="{{ asset('images/watersprings/college.jpg') }}" alt="Archived Watersprings International College admission notice for 2025/2026, Years 7 and 8" class="w-full rounded-2xl" loading="lazy" width="1080" height="1080"></a><figcaption class="mt-3 text-sm text-slate-500">2025/2026 announcement. The examination dates on this notice have passed.</figcaption></figure>
    </section>
</div>
@endsection
