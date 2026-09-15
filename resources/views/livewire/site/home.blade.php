@extends('layouts.app', ['mode' => 'public'])
@section('title', 'Watersprings International School Akure')
@section('content')
<div class="bg-slate-50 text-slate-900">
    <section class="bg-sky-950 text-white">
        <div class="mx-auto grid max-w-7xl items-center gap-10 px-4 py-12 sm:px-6 lg:grid-cols-2 lg:px-8 lg:py-20">
            <div>
                <p class="text-sm font-bold uppercase tracking-widest text-yellow-300">{{ $publicSiteSettings['home']['hero_badge'] }}</p>
                <h1 class="mt-5 text-4xl font-black leading-tight sm:text-5xl">{{ $publicSiteSettings['home']['hero_title'] }}</h1>
                <h2 class="mt-5 text-2xl font-semibold text-yellow-300">{{ $publicSiteSettings['tagline'] }}</h2>
                <p class="mt-5 max-w-xl text-lg leading-relaxed text-sky-100">{{ $publicSiteSettings['home']['hero_description'] }}</p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ route('admission') }}" class="rounded-xl bg-yellow-400 px-6 py-3 font-bold text-sky-950 hover:bg-yellow-300">Explore admissions</a>
                    <a href="{{ route('contact') }}#visit" class="rounded-xl border border-white/50 px-6 py-3 font-semibold hover:bg-white/10">Arrange a visit</a>
                </div>
            </div>
            <img src="{{ asset('images/watersprings/learn.jpg') }}" alt="Learning at Watersprings International School Akure" fetchpriority="high" class="w-full rounded-3xl object-cover shadow-xl" width="960" height="450">
        </div>
    </section>
    @include('partials.current-term-theme')
    <section class="mx-auto grid max-w-6xl gap-8 px-4 py-14 sm:px-6 md:grid-cols-3 lg:px-8">
        <div class="rounded-2xl bg-sky-100 p-6 text-center">
            <img src="{{ asset('images/watersprings/head-of-school.jpg') }}" alt="Adedamola Ogidan, Head of School" class="mx-auto h-48 w-40 rounded-xl object-cover" loading="lazy" width="160" height="192">
            <p class="mt-4 font-bold">Adedamola Ogidan</p><p class="text-sm text-sky-800">Head of School</p>
        </div>
        <div class="md:col-span-2">
            <p class="text-sm font-bold uppercase tracking-widest text-sky-700">Welcome to Watersprings</p>
            <h2 class="mt-3 text-3xl font-black">A community that cares for every child</h2>
            <p class="mt-5 leading-relaxed text-slate-600">{{ $publicSiteSettings['about_summary'] }}</p>
            <p class="mt-4 leading-relaxed text-slate-600">Academic progress sits alongside character, creativity and service. From the early years through college Years 7–9, our teachers help students explore their interests and develop the confidence and curiosity to keep learning.</p>
            <a href="{{ route('about') }}" class="mt-6 inline-block font-bold text-sky-700 hover:underline">Meet our school →</a>
        </div>
    </section>
    <section class="bg-sky-50 py-14">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
        <h2 class="mb-7 text-3xl font-black">Learning from the early years through college</h2>
            @include('partials.watersprings-programmes')
            <a href="{{ route('academics') }}" class="mt-6 inline-block font-bold text-sky-700 hover:underline">Explore our curriculum and clubs →</a>
        </div>
    </section>
    <section class="mx-auto max-w-6xl px-4 py-14 sm:px-6 lg:px-8">
        <h2 class="text-3xl font-black">Our values in everyday life</h2>
        <div class="mt-7 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($publicSiteSettings['values'] as $value)
                <article class="rounded-2xl border border-slate-200 bg-white p-6"><h3 class="text-xl font-bold text-sky-800">{{ $value['title'] }}</h3><p class="mt-3 text-slate-600">{{ $value['text'] }}</p></article>
            @endforeach
        </div>
    </section>
    <section class="mx-auto max-w-6xl px-4 pb-12 sm:px-6 lg:px-8">
        <div class="rounded-2xl bg-sky-900 p-8 text-white">
            <p class="text-sm font-bold text-yellow-300">Junior secondary education</p>
            <h2 class="mt-3 text-2xl font-black">Watersprings International College</h2>
            <p class="mt-4 max-w-3xl leading-relaxed">Our college currently serves students in Years 7, 8 and 9, continuing the school’s focus on academic growth, Christian character, confidence and enterprise.</p>
            <div class="mt-5 flex flex-wrap gap-4"><a href="{{ route('academics') }}#college" class="font-semibold text-yellow-300 hover:underline">Explore Years 7–9 →</a><a href="{{ route('admission') }}#form" class="font-semibold text-white hover:underline">Apply for college admission →</a></div>
        </div>
    </section>
    @include('partials.watersprings-faq')
</div>
@endsection
