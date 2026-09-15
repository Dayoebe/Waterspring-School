@extends('layouts.app', ['mode' => 'public'])
@section('title', 'About Watersprings')
@section('content')
<div class="bg-slate-50 text-slate-900">
    @include('partials.watersprings-hero', ['title' => 'A place to learn, play and grow together', 'description' => $publicSiteSettings['about_summary']])
    <section id="history" class="mx-auto max-w-6xl px-4 py-14 sm:px-6 lg:px-8">
        <p class="text-sm font-bold uppercase tracking-widest text-sky-700">Our history</p>
        <h2 class="mt-3 text-3xl font-black">Built on faith, family and a love for learning</h2>
        <div class="mt-5 max-w-4xl space-y-4 leading-relaxed text-slate-600">
            <p>Watersprings grew from the vision of the late Mrs. Olajumoke Babatunde. Her earlier school, His Mercy Crèche and Playgroup, cared for children from 2002 to 2013.</p>
            <p>With her husband, Dr. Olukayode Babatunde, and the support of friends and family, she developed that vision into Watersprings International School. Today, the learning journey continues through Watersprings International College for Years 7, 8 and 9, while preserving the same commitment to care, character and purposeful education.</p>
        </div>
    </section>
    <section id="mission" class="bg-sky-50 py-12">
        <div class="mx-auto grid max-w-6xl gap-6 px-4 sm:px-6 md:grid-cols-2 lg:px-8">
            <article class="rounded-2xl bg-white p-7"><h2 class="text-2xl font-black text-sky-900">Our mission</h2><p class="mt-4 text-lg leading-relaxed">{{ $publicSiteSettings['mission'] }}</p><p class="mt-6 font-bold text-sky-700">Our motto: {{ $publicSiteSettings['school_motto'] }}</p></article>
            <article class="rounded-2xl bg-white p-7"><h2 class="text-2xl font-black text-sky-900">Our vision</h2><p class="mt-4 leading-relaxed text-slate-600">{{ $publicSiteSettings['vision'] }}</p></article>
        </div>
    </section>
    <section id="ethos" class="mx-auto max-w-6xl px-4 py-14 sm:px-6 lg:px-8">
        <h2 class="text-3xl font-black">Our school ethos</h2>
        <p class="mt-5 max-w-4xl leading-relaxed text-slate-600">Christian values guide our school and college community. Practical learning encourages independence and curiosity, while community service helps students consider the needs of others. We want every learner, from the early years through Year 9, to find joy in learning and confidence in who they are.</p>
        <div class="mt-7 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($publicSiteSettings['values'] as $value)
                <article class="rounded-xl border border-sky-100 bg-white p-6"><h3 class="text-xl font-bold text-sky-800">{{ $value['title'] }}</h3><p class="mt-3 text-slate-600">{{ $value['text'] }}</p></article>
            @endforeach
        </div>
    </section>
    @if($administrators->isNotEmpty())
        <section id="leadership" class="bg-white py-14">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-sm font-bold uppercase tracking-widest text-sky-700">School leadership</p>
                        <h2 class="mt-2 text-3xl font-black">Meet our administrators</h2>
                        <p class="mt-3 max-w-2xl leading-relaxed text-slate-600">The leadership team responsible for the direction, standards and daily administration of Watersprings.</p>
                    </div>
                    <a href="{{ route('team') }}" class="font-bold text-sky-700 hover:underline">View the full staff directory →</a>
                </div>
                <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($administrators as $profile)
                        <a href="{{ route('team.show', $profile) }}" class="group overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 shadow-sm transition hover:-translate-y-1 hover:shadow-xl">
                            <img src="{{ $profile->user->profile_photo_url }}" alt="{{ $profile->user->name }}" loading="lazy" class="aspect-[4/3] w-full object-cover object-top">
                            <div class="p-6">
                                <p class="text-xs font-bold uppercase tracking-widest text-sky-700">{{ $profile->department?->name }}</p>
                                <h3 class="mt-2 text-xl font-black text-slate-900 group-hover:text-sky-700">{{ $profile->user->name }}</h3>
                                <p class="mt-1 font-semibold text-slate-600">{{ $profile->job_title }}</p>
                                @if($profile->bio)<p class="mt-4 line-clamp-3 text-sm leading-6 text-slate-600">{{ $profile->bio }}</p>@endif
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif
    @include('partials.watersprings-faq')
</div>
@endsection
