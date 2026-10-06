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
    @if ($clubs->isNotEmpty())
        <section class="border-y border-sky-100 bg-white py-16">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                    <div class="max-w-3xl">
                        <p class="text-sm font-bold uppercase tracking-[0.18em] text-sky-700">Clubs at Watersprings</p>
                        <h2 class="mt-3 text-3xl font-black sm:text-4xl">Interests grow through practice and teamwork</h2>
                        <p class="mt-4 leading-7 text-slate-600">Explore the active clubs currently registered by the school. Each one gives students another place to build skills, confidence and friendships.</p>
                    </div>
                    <a href="{{ route('academics') }}#clubs" class="shrink-0 font-bold text-sky-700 hover:underline">See all clubs →</a>
                </div>
                <div class="mt-9">@include('partials.public-club-cards', ['clubs' => $clubs])</div>
            </div>
        </section>
    @endif
    <section class="overflow-hidden bg-gradient-to-r from-amber-50 via-rose-50 to-violet-50 py-16">
        <div class="mx-auto grid max-w-6xl items-center gap-10 px-4 sm:px-6 lg:grid-cols-2 lg:px-8">
            <div>
                <p class="text-sm font-bold uppercase tracking-[0.18em] text-rose-700">Learn • Play • Grow • Together</p>
                <h2 class="mt-3 text-3xl font-black sm:text-4xl">A balanced school day makes room for every kind of growth</h2>
                <p class="mt-5 leading-8 text-slate-600">Children need strong teaching, but they also need opportunities to create, move, collaborate and discover what interests them. The Watersprings experience connects classroom learning with clubs, practical activities and shared school life.</p>
                <div class="mt-7 flex flex-wrap gap-3"><a href="{{ route('gallery') }}" class="rounded-xl bg-rose-600 px-6 py-3 font-bold text-white hover:bg-rose-700">See life at Watersprings</a><a href="{{ route('why-watersprings') }}" class="rounded-xl border border-rose-200 bg-white px-6 py-3 font-bold text-rose-800">Why families choose us</a></div>
            </div>
            <div class="grid grid-cols-2 gap-4"><img src="{{ asset('images/watersprings/play.jpg') }}" alt="Children enjoying school life at Watersprings" class="aspect-square w-full rounded-3xl object-cover shadow-lg" loading="lazy"><img src="{{ asset('images/watersprings/together.jpg') }}" alt="The Watersprings school community together" class="mt-8 aspect-square w-full rounded-3xl object-cover shadow-lg" loading="lazy"></div>
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
