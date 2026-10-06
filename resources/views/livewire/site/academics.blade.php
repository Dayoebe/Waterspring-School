@extends('layouts.app', ['mode' => 'public'])
@section('title', 'Learning at Watersprings')
@section('content')
<div class="bg-slate-50 text-slate-900">
    @include('partials.watersprings-hero', [
        'eyebrow' => 'Our classes and curriculum',
        'title' => 'A connected learning journey from the early years through Year 9',
        'description' => 'Watersprings combines the National Curriculum of England with learning adapted for children in Nigeria. Each stage builds knowledge, confidence, character and the habits children need for what comes next.',
    ])

    <section class="border-b border-sky-100 bg-white">
        <div class="mx-auto grid max-w-6xl gap-4 px-4 py-8 sm:grid-cols-3 sm:px-6 lg:px-8">
            @foreach ([
                ['fa-book-open', 'Strong foundations', 'Communication, literacy and numeracy grow through practical, age-appropriate learning.'],
                ['fa-lightbulb', 'Curious thinking', 'Investigation, discussion and creative work help learners understand ideas, not simply repeat them.'],
                ['fa-people-group', 'Whole-child growth', 'Academic learning sits alongside character, collaboration, physical development and confidence.'],
            ] as [$icon, $heading, $text])
                <article class="flex gap-4 rounded-2xl bg-slate-50 p-5">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-sky-100 text-sky-700"><i class="fas {{ $icon }}"></i></span>
                    <div><h2 class="font-black">{{ $heading }}</h2><p class="mt-1 text-sm leading-6 text-slate-600">{{ $text }}</p></div>
                </article>
            @endforeach
        </div>
    </section>

    <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:px-8">
        <div class="max-w-3xl">
            <p class="text-sm font-bold uppercase tracking-[0.18em] text-sky-700">Four stages, one community</p>
            <h2 class="mt-3 text-3xl font-black sm:text-4xl">Early years, primary and college</h2>
            <p class="mt-4 leading-7 text-slate-600">Children progress through clearly defined stages while remaining part of the same caring school community. Explore each stage to understand its focus and learning approach.</p>
        </div>
        <div class="mt-9">@include('partials.watersprings-programmes')</div>
    </section>

    <section class="overflow-hidden bg-gradient-to-r from-amber-50 via-orange-50 to-rose-50 py-16">
        <div class="mx-auto grid max-w-6xl items-center gap-10 px-4 sm:px-6 lg:grid-cols-[0.9fr_1.1fr] lg:px-8">
            <img src="{{ asset('images/watersprings/key-stage-2.jpg') }}" alt="Primary school learning at Watersprings" class="aspect-[4/3] w-full rounded-[2rem] object-cover shadow-xl" loading="lazy" width="960" height="720">
            <div>
                <p class="text-sm font-bold uppercase tracking-[0.18em] text-orange-700">How children learn</p>
                <h2 class="mt-3 text-3xl font-black sm:text-4xl">Learning that moves beyond the textbook</h2>
                <p class="mt-5 leading-8 text-slate-700">Classroom learning includes speaking, listening, presentations, teamwork and investigation. In Key Stage 2, practical science, ICT, literacy and mathematics help pupils connect knowledge with real tasks and prepare for secondary education.</p>
                <div class="mt-7 grid gap-3 sm:grid-cols-2">
                    @foreach (['Activity-based lessons', 'Practical investigations', 'Teamwork and presentations', 'ICT-supported learning', 'Independent thinking', 'Preparation for the next stage'] as $item)
                        <p class="flex items-center gap-3 rounded-xl bg-white/80 px-4 py-3 text-sm font-bold text-slate-700 shadow-sm"><i class="fas fa-check-circle text-emerald-600"></i>{{ $item }}</p>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section id="clubs" class="bg-gradient-to-br from-cyan-950 to-sky-900 py-16 text-white">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-8 lg:grid-cols-[0.8fr_1.2fr]">
                <div>
                    <p class="text-sm font-bold uppercase tracking-[0.18em] text-amber-300">Beyond the classroom</p>
                    <h2 class="mt-3 text-3xl font-black sm:text-4xl">Room to discover new interests</h2>
                    <p class="mt-5 leading-8 text-sky-100">Clubs create space for children to make, perform, communicate, solve problems and stay active. Choices may vary, so families can ask the school office about the current club programme.</p>
                </div>
                <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($publicSiteSettings['clubs'] as $club)
                        <li class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/10 p-4 font-semibold backdrop-blur"><span class="h-2 w-2 rounded-full bg-amber-300"></span>{{ $club }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    <section id="college" class="mx-auto grid max-w-6xl items-center gap-10 px-4 py-16 sm:px-6 lg:grid-cols-2 lg:px-8">
        <div>
            <p class="text-sm font-bold uppercase tracking-[0.18em] text-violet-700">Years 7, 8 and 9</p>
            <h2 class="mt-3 text-3xl font-black sm:text-4xl">Watersprings International College</h2>
            <p class="mt-5 leading-8 text-slate-600">The college is an active part of Watersprings and currently runs Year 7, Year 8 and Year 9. Students deepen subject knowledge, strengthen independent study habits and continue growing in Christian character, confidence and leadership.</p>
            <p class="mt-4 leading-8 text-slate-600">The familiar school community supports a thoughtful transition into junior secondary education. Families can contact the office for current places, fees, subject details and admission arrangements for each year.</p>
            <div class="mt-7 flex flex-wrap gap-3">
                <a href="{{ route('admission') }}#form" class="site-primary-bg rounded-xl px-6 py-3 font-bold text-white">Apply for a place</a>
                <a href="{{ route('contact') }}#visit" class="rounded-xl border border-violet-200 bg-violet-50 px-6 py-3 font-bold text-violet-800 hover:bg-violet-100">Arrange a visit</a>
            </div>
        </div>
        <figure class="relative">
            <div class="absolute -inset-3 -z-10 rotate-2 rounded-[2rem] bg-violet-100"></div>
            <img src="{{ asset('images/watersprings/college.jpg') }}" alt="Watersprings International College serving Years 7, 8 and 9" class="aspect-square w-full rounded-[2rem] object-cover shadow-xl" loading="lazy" width="1080" height="1080">
            <figcaption class="mt-4 text-sm leading-6 text-slate-500">The connected Watersprings learning journey currently continues through Years 7–9.</figcaption>
        </figure>
    </section>

    <section class="bg-emerald-50 py-14">
        <div class="mx-auto flex max-w-6xl flex-col gap-6 px-4 sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:px-8">
            <div><p class="font-bold text-emerald-700">Find the right learning stage</p><h2 class="mt-2 text-2xl font-black sm:text-3xl">Come and explore Watersprings with your child.</h2></div>
            <div class="flex flex-wrap gap-3"><a href="{{ route('prospectus') }}" class="rounded-xl bg-emerald-700 px-5 py-3 font-bold text-white hover:bg-emerald-800">Read the prospectus</a><a href="{{ route('contact') }}" class="rounded-xl border border-emerald-300 bg-white px-5 py-3 font-bold text-emerald-800">Contact the school</a></div>
        </div>
    </section>
</div>
@endsection
