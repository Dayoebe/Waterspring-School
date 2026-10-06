@extends('layouts.app', ['mode' => 'public'])
@section('title', 'Life at Watersprings')
@section('content')
<div class="bg-slate-50 text-slate-900">
    @include('partials.watersprings-hero', ['eyebrow' => 'Learn • Play • Grow • Together', 'title' => $publicSiteSettings['gallery_page']['hero_title'], 'description' => $publicSiteSettings['gallery_page']['hero_description'].' These moments reflect the balanced school experience we want children to enjoy each day.'])

    <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:px-8" aria-label="School photographs">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><p class="text-sm font-bold uppercase tracking-[0.18em] text-sky-700">Inside our community</p><h2 class="mt-3 text-3xl font-black sm:text-4xl">Moments from school life</h2></div><p class="max-w-xl leading-7 text-slate-600">Select any photograph to view the full image. The gallery introduces the learning, activity and togetherness at the heart of Watersprings.</p></div>
        <div class="mt-10 grid auto-rows-[260px] gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($publicSiteSettings['gallery'] as $photo)
                <figure class="group relative overflow-hidden rounded-3xl bg-sky-950 shadow-lg {{ $loop->first ? 'sm:col-span-2 lg:row-span-2' : '' }}">
                    <a href="{{ asset('images/watersprings/'.$photo['file']) }}" aria-label="View full photograph: {{ $photo['title'] }}" class="block h-full">
                        <img src="{{ asset('images/watersprings/'.$photo['file']) }}" alt="{{ $photo['caption'] }}" loading="lazy" decoding="async" class="h-full w-full object-cover transition duration-500 group-hover:scale-105" width="960" height="540">
                        <span class="absolute inset-0 bg-gradient-to-t from-slate-950/90 via-slate-950/10 to-transparent"></span>
                        <figcaption class="absolute inset-x-0 bottom-0 p-6 text-white"><span class="text-xs font-bold uppercase tracking-widest text-amber-300">Watersprings</span><h3 class="mt-2 text-2xl font-black">{{ $photo['title'] }}</h3><p class="mt-2 text-sm leading-6 text-slate-200">{{ $photo['caption'] }}</p></figcaption>
                    </a>
                </figure>
            @endforeach
        </div>
    </section>

    <section class="bg-white py-16">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8"><div class="grid gap-5 md:grid-cols-3">
            @foreach ([['fa-book-open-reader', 'Learn', 'Lessons develop strong foundations, curiosity and the confidence to share ideas.'], ['fa-person-running', 'Play and create', 'Activity, clubs and creative experiences give children different ways to participate and grow.'], ['fa-people-roof', 'Belong together', 'Shared experiences help learners build friendships, responsibility and a sense of community.']] as [$icon, $heading, $text])
                <article class="rounded-3xl border border-slate-200 bg-slate-50 p-7"><i class="fas {{ $icon }} text-2xl text-sky-700"></i><h2 class="mt-4 text-xl font-black">{{ $heading }}</h2><p class="mt-3 leading-7 text-slate-600">{{ $text }}</p></article>
            @endforeach
        </div></div>
    </section>

    <section class="bg-amber-50 py-14"><div class="mx-auto flex max-w-6xl flex-col gap-6 px-4 sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:px-8"><div><p class="font-bold text-amber-700">Experience Watersprings in person</p><h2 class="mt-2 text-2xl font-black sm:text-3xl">A photograph is only the beginning.</h2><p class="mt-2 text-slate-600">Visit with your child, meet the team and explore the learning environment.</p></div><a href="{{ route('contact') }}#visit" class="rounded-xl bg-amber-500 px-6 py-3 text-center font-bold text-slate-950 hover:bg-amber-400">Arrange a school visit</a></div></section>
</div>
@endsection
