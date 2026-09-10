@extends('layouts.app', ['mode' => 'public'])
@section('title', 'Our Team')
@section('content')
<div class="bg-slate-50 text-slate-900">
    @include('partials.watersprings-hero', ['title' => 'Meet our team', 'description' => 'The leadership, teachers and support staff who make Watersprings a caring place to learn and grow.'])
    <div class="mx-auto max-w-7xl space-y-14 px-4 py-14 sm:px-6 lg:px-8">
        @forelse($staff as $department => $profiles)
            <section><div class="border-b border-slate-200 pb-4"><h2 class="text-2xl font-black">{{ $department }}</h2><p class="mt-1 text-sm text-slate-500">{{ $profiles->count() }} team member{{ $profiles->count() === 1 ? '' : 's' }}</p></div><div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">@foreach($profiles as $profile)<a href="{{ route('team.show', $profile) }}" class="group overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-xl"><img src="{{ $profile->user->profile_photo_url }}" alt="{{ $profile->user->name }}" class="aspect-[4/5] w-full object-cover"><div class="p-5"><h3 class="text-lg font-black group-hover:text-sky-700">{{ $profile->user->name }}</h3><p class="mt-1 text-sm font-bold text-sky-700">{{ $profile->job_title }}</p>@if($profile->bio)<p class="mt-3 line-clamp-3 text-sm leading-6 text-slate-600">{{ $profile->bio }}</p>@endif</div></a>@endforeach</div></section>
        @empty <div class="rounded-2xl bg-white p-12 text-center text-slate-500">Staff profiles will appear here soon.</div> @endforelse
    </div>
</div>
@endsection
