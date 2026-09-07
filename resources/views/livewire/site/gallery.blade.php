@extends('layouts.app', ['mode' => 'public'])
@section('title', 'Life at Watersprings')
@section('content')
<div class="bg-slate-50 text-slate-900">
    @include('partials.watersprings-hero', ['title' => $publicSiteSettings['gallery_page']['hero_title'], 'description' => $publicSiteSettings['gallery_page']['hero_description']])
    <section class="mx-auto max-w-6xl px-4 py-14 sm:px-6 lg:px-8" aria-label="School photographs">
        <div class="grid gap-7 sm:grid-cols-2">
            @foreach ($publicSiteSettings['gallery'] as $photo)
                <figure class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <a href="{{ asset('images/watersprings/'.$photo['file']) }}" aria-label="View full photograph: {{ $photo['title'] }}"><img src="{{ asset('images/watersprings/'.$photo['file']) }}" alt="{{ $photo['caption'] }}" loading="lazy" decoding="async" class="aspect-video w-full object-cover" width="960" height="540"></a>
                    <figcaption class="p-6"><h2 class="text-xl font-bold text-sky-900">{{ $photo['title'] }}</h2><p class="mt-2 text-slate-600">{{ $photo['caption'] }}</p></figcaption>
                </figure>
            @endforeach
        </div>
    </section>
</div>
@endsection
