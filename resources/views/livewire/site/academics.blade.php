@extends('layouts.app', ['mode' => 'public'])
@section('title', 'Learning at Watersprings')
@section('content')
<div class="bg-slate-50 text-slate-900">
    @include('partials.watersprings-hero', ['eyebrow' => 'Our classes and curriculum', 'title' => 'Learning from the early years through Year 9', 'description' => 'Watersprings provides a connected learning journey through early years, primary school and college Years 7–9. Our curriculum draws on the National Curriculum of England and is adapted for learners in Nigeria.'])
    <section class="mx-auto max-w-6xl px-4 py-14 sm:px-6 lg:px-8">
        <h2 class="mb-7 text-3xl font-black">Early years, primary and college</h2>
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
            <p class="text-sm font-bold uppercase tracking-widest text-sky-700">Years 7, 8 and 9</p>
            <h2 class="mt-3 text-3xl font-black">Watersprings International College</h2>
            <p class="mt-5 leading-relaxed text-slate-600">The college is an active part of Watersprings and currently runs Year 7, Year 8 and Year 9. It gives students a familiar, caring environment in which to deepen subject knowledge, strengthen independent study habits and grow in character and leadership.</p>
            <p class="mt-4 leading-relaxed text-slate-600">Families can contact the school office for current places, fees, curriculum details and admission arrangements for each college year.</p>
            <a href="{{ route('contact') }}" class="site-primary-bg mt-6 inline-block rounded-xl px-6 py-3 font-bold text-white">Ask about college admission</a>
        </div>
        <figure><img src="{{ asset('images/watersprings/college.jpg') }}" alt="Watersprings International College serving Years 7, 8 and 9" class="w-full rounded-2xl" loading="lazy" width="1080" height="1080"><figcaption class="mt-3 text-sm text-slate-500">Watersprings International College continues the learning journey through Years 7–9.</figcaption></figure>
    </section>
</div>
@endsection
