@extends('layouts.app', ['mode' => 'public'])
@section('title', 'Our Team')
@section('content')
<div class="bg-slate-50 text-slate-900">
    @include('partials.watersprings-hero', ['eyebrow' => 'The people behind the learning', 'title' => 'Meet the Watersprings team', 'description' => 'Our leadership, teachers and support staff work together to serve learners across Watersprings International School and College and to sustain a caring, purposeful school day.'])

    <section class="border-b border-slate-200 bg-white py-10">
        <div class="mx-auto grid max-w-6xl gap-5 px-4 sm:px-6 md:grid-cols-3 lg:px-8">
            @foreach ([['fa-compass', 'Leadership', 'Guiding the direction, standards and everyday life of the school.'], ['fa-chalkboard-user', 'Teaching', 'Supporting learning, curiosity and progress through each stage.'], ['fa-hands-holding-child', 'Care and support', 'Helping every child feel known, valued and ready to participate.']] as [$icon, $heading, $text])
                <article class="flex gap-4 rounded-2xl bg-slate-50 p-5"><span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-sky-100 text-sky-700"><i class="fas {{ $icon }}"></i></span><div><h2 class="font-black">{{ $heading }}</h2><p class="mt-1 text-sm leading-6 text-slate-600">{{ $text }}</p></div></article>
            @endforeach
        </div>
    </section>

    <div class="mx-auto max-w-7xl space-y-14 px-4 py-16 sm:px-6 lg:px-8">
        @forelse($staff as $department => $profiles)
            <section>
                <div class="flex items-end justify-between border-b border-sky-200 pb-4"><div><p class="text-xs font-bold uppercase tracking-widest text-sky-700">Our people</p><h2 class="mt-2 text-2xl font-black sm:text-3xl">{{ $department }}</h2></div><p class="rounded-full bg-sky-100 px-3 py-1 text-sm font-bold text-sky-800">{{ $profiles->count() }} member{{ $profiles->count() === 1 ? '' : 's' }}</p></div>
                <div class="mt-7 grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    @foreach($profiles as $profile)
                        <a href="{{ route('team.show', $profile) }}" class="group overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm transition duration-300 hover:-translate-y-1 hover:border-sky-200 hover:shadow-xl"><div class="overflow-hidden bg-sky-50"><img src="{{ $profile->user->profile_photo_url }}" alt="{{ $profile->user->name }}" loading="lazy" class="aspect-[4/5] w-full object-cover transition duration-500 group-hover:scale-105"></div><div class="p-6"><p class="text-xs font-bold uppercase tracking-widest text-sky-600">{{ $department }}</p><h3 class="mt-2 text-xl font-black group-hover:text-sky-700">{{ $profile->user->name }}</h3><p class="mt-1 text-sm font-bold text-slate-600">{{ $profile->job_title }}</p>@if($profile->bio)<p class="mt-4 line-clamp-3 text-sm leading-6 text-slate-600">{{ $profile->bio }}</p>@endif<span class="mt-5 inline-flex items-center gap-2 text-sm font-bold text-sky-700">View profile <i class="fas fa-arrow-right text-xs transition group-hover:translate-x-1"></i></span></div></a>
                    @endforeach
                </div>
            </section>
        @empty
            <section class="overflow-hidden rounded-[2rem] border border-sky-100 bg-white shadow-sm"><div class="grid lg:grid-cols-2"><div class="p-8 sm:p-12"><span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-amber-100 text-xl text-amber-700"><i class="fas fa-users"></i></span><p class="mt-6 text-sm font-bold uppercase tracking-[0.18em] text-sky-700">Staff directory</p><h2 class="mt-3 text-3xl font-black">Profiles are being prepared</h2><p class="mt-4 leading-8 text-slate-600">Public staff profiles will appear here when they have been reviewed and published by the school. In the meantime, contact the office to speak with the appropriate member of the team.</p><a href="{{ route('contact') }}" class="site-primary-bg mt-7 inline-block rounded-xl px-6 py-3 font-bold text-white">Contact the school office</a></div><div class="bg-gradient-to-br from-sky-900 to-cyan-800 p-8 text-white sm:p-12"><p class="text-sm font-bold uppercase tracking-[0.18em] text-amber-300">Our shared purpose</p><h3 class="mt-3 text-2xl font-black">Every child known, valued and cared for</h3><p class="mt-4 leading-8 text-sky-100">Across leadership, teaching and support, the school’s work is grounded in cooperation, individual support and preparation for future opportunities.</p><p class="mt-6 border-l-4 border-amber-300 pl-4 font-bold leading-7">{{ $publicSiteSettings['school_promise'] }}</p></div></div></section>
        @endforelse
    </div>

    <section class="bg-violet-50 py-14"><div class="mx-auto flex max-w-6xl flex-col gap-6 px-4 sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:px-8"><div><p class="font-bold text-violet-700">Come and meet us</p><h2 class="mt-2 text-2xl font-black sm:text-3xl">Start a conversation with the school team.</h2><p class="mt-2 max-w-2xl text-slate-600">Ask about admissions, arrange a visit or find the right contact for your question.</p></div><a href="{{ route('contact') }}" class="rounded-xl bg-violet-700 px-6 py-3 text-center font-bold text-white hover:bg-violet-800">Contact Watersprings</a></div></section>
</div>
@endsection
