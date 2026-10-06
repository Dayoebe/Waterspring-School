@extends('layouts.app', ['mode' => 'public'])
@section('title', 'Life at Watersprings')
@section('content')
<div
    class="bg-slate-50 text-slate-900"
    x-data="{
        lightboxOpen: false,
        lightboxSrc: '',
        lightboxTitle: '',
        lightboxCaption: '',
        previousFocus: null,
        showImage(src, title, caption) {
            this.previousFocus = document.activeElement;
            this.lightboxSrc = src;
            this.lightboxTitle = title;
            this.lightboxCaption = caption || '';
            this.lightboxOpen = true;
            this.$nextTick(() => this.$refs.lightboxClose.focus());
        },
        closeImage() {
            this.lightboxOpen = false;
            this.$nextTick(() => this.previousFocus?.focus());
        }
    }"
    x-effect="document.body.classList.toggle('overflow-hidden', lightboxOpen)"
    @keydown.escape.window="if (lightboxOpen) closeImage()"
>
    @include('partials.watersprings-hero', ['eyebrow' => 'Learn • Play • Grow • Together', 'title' => $publicSiteSettings['gallery_page']['hero_title'], 'description' => $publicSiteSettings['gallery_page']['hero_description'].' These moments reflect the balanced school experience we want children to enjoy each day.'])

    <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:px-8" aria-labelledby="event-gallery-heading">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div><p class="text-sm font-bold uppercase tracking-[0.18em] text-sky-700">Inside our community</p><h2 id="event-gallery-heading" class="mt-3 text-3xl font-black sm:text-4xl">School events and shared moments</h2></div>
            <p class="max-w-xl leading-7 text-slate-600">Photographs are organised by event, making it easier for families to explore each occasion and the memories created together.</p>
        </div>

        @if ($eventAlbums->isNotEmpty())
            <nav class="mt-8 flex gap-2 overflow-x-auto pb-2" aria-label="Gallery events">
                @foreach ($eventAlbums as $album)
                    <a href="#event-{{ $album->slug }}" class="shrink-0 rounded-full border border-sky-200 bg-white px-4 py-2 text-sm font-bold text-sky-800 hover:bg-sky-50">{{ $album->name }} <span class="text-sky-500">({{ $album->items->count() }})</span></a>
                @endforeach
            </nav>

            <div class="mt-12 space-y-20">
                @foreach ($eventAlbums as $album)
                    @php
                        $datedItems = $album->items->whereNotNull('taken_on');
                        $eventDate = $datedItems->sortByDesc('taken_on')->first()?->taken_on;
                    @endphp
                    <section id="event-{{ $album->slug }}" class="scroll-mt-24" aria-labelledby="event-title-{{ $album->id }}">
                        <div class="mb-7 border-l-4 border-sky-500 pl-5">
                            <div class="flex flex-wrap items-center gap-3">
                                <h3 id="event-title-{{ $album->id }}" class="text-2xl font-black text-slate-950 sm:text-3xl">{{ $album->name }}</h3>
                                @if ($eventDate)<time datetime="{{ $eventDate->format('Y-m-d') }}" class="rounded-full bg-sky-100 px-3 py-1 text-xs font-bold text-sky-800">{{ $eventDate->format('j F Y') }}</time>@endif
                            </div>
                            @if ($album->description)<p class="mt-3 max-w-3xl leading-7 text-slate-600">{{ $album->description }}</p>@endif
                            <p class="mt-2 text-sm font-semibold text-slate-500">{{ $album->items->count() }} photograph{{ $album->items->count() === 1 ? '' : 's' }}</p>
                        </div>

                        <div class="grid auto-rows-[250px] gap-5 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($album->items as $photo)
                                <figure class="group relative overflow-hidden rounded-3xl bg-sky-950 shadow-lg {{ $loop->first && $album->items->count() > 3 ? 'sm:col-span-2 lg:row-span-2' : '' }}">
                                    <a href="{{ $photo->media_url }}" @click.prevent="showImage(@js($photo->media_url), @js($photo->title), @js($photo->caption))" aria-label="Open photograph: {{ $photo->title }}" class="block h-full cursor-zoom-in">
                                        <img src="{{ \App\Models\GalleryItem::transformUrl($photo->media_url, 'c_fill,g_auto,f_auto,q_auto,w_960,h_720') }}" alt="{{ $photo->caption ?: $photo->title }}" loading="lazy" decoding="async" class="h-full w-full object-cover transition duration-500 group-hover:scale-105" width="960" height="720">
                                        <span class="absolute inset-0 bg-gradient-to-t from-slate-950/90 via-slate-950/10 to-transparent"></span>
                                        <figcaption class="absolute inset-x-0 bottom-0 p-5 text-white"><h4 class="text-lg font-black">{{ $photo->title }}</h4>@if($photo->caption)<p class="mt-1 line-clamp-2 text-sm leading-6 text-slate-200">{{ $photo->caption }}</p>@endif</figcaption>
                                    </a>
                                </figure>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
        @else
            <div class="mt-10 rounded-3xl border border-dashed border-sky-200 bg-sky-50 p-8 text-center">
                <i class="fas fa-camera-retro text-3xl text-sky-600" aria-hidden="true"></i>
                <h3 class="mt-4 text-xl font-black">Event albums are being prepared</h3>
                <p class="mx-auto mt-2 max-w-xl leading-7 text-slate-600">New event photographs will appear here as the school publishes them.</p>
            </div>
        @endif
    </section>

    <section class="border-y border-slate-200 bg-white py-16" aria-labelledby="school-life-highlights">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <div><p class="text-sm font-bold uppercase tracking-[0.18em] text-amber-700">School life</p><h2 id="school-life-highlights" class="mt-3 text-3xl font-black">Everyday highlights</h2><p class="mt-3 max-w-2xl leading-7 text-slate-600">A glimpse of learning, play and growth across the Watersprings community.</p></div>
            <div class="mt-9 grid auto-rows-[240px] gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($publicSiteSettings['gallery'] as $photo)
                    <figure class="group relative overflow-hidden rounded-3xl bg-sky-950 shadow-lg">
                        <a href="{{ asset('images/watersprings/'.$photo['file']) }}" @click.prevent="showImage(@js(asset('images/watersprings/'.$photo['file'])), @js($photo['title']), @js($photo['caption']))" aria-label="Open photograph: {{ $photo['title'] }}" class="block h-full cursor-zoom-in">
                            <img src="{{ asset('images/watersprings/'.$photo['file']) }}" alt="{{ $photo['caption'] }}" loading="lazy" decoding="async" class="h-full w-full object-cover transition duration-500 group-hover:scale-105" width="960" height="540">
                            <span class="absolute inset-0 bg-gradient-to-t from-slate-950/90 via-slate-950/10 to-transparent"></span>
                            <figcaption class="absolute inset-x-0 bottom-0 p-5 text-white"><h3 class="text-lg font-black">{{ $photo['title'] }}</h3><p class="mt-1 text-sm leading-6 text-slate-200">{{ $photo['caption'] }}</p></figcaption>
                        </a>
                    </figure>
                @endforeach
            </div>
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

    <div
        x-cloak
        x-show="lightboxOpen"
        x-transition.opacity
        @click.self="closeImage()"
        class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/90 p-4 sm:p-8"
        role="dialog"
        aria-modal="true"
        aria-labelledby="gallery-lightbox-title"
    >
        <div class="relative flex max-h-full w-full max-w-6xl flex-col items-center" @click.stop>
            <button
                type="button"
                x-ref="lightboxClose"
                @click="closeImage()"
                class="absolute -top-2 right-0 z-10 flex h-11 w-11 items-center justify-center rounded-full bg-white text-slate-900 shadow-xl transition hover:bg-amber-300 sm:-right-2"
                aria-label="Close photograph"
            ><i class="fas fa-times text-lg" aria-hidden="true"></i></button>

            <img :src="lightboxSrc" :alt="lightboxCaption || lightboxTitle" class="max-h-[78vh] max-w-full rounded-2xl object-contain shadow-2xl">
            <div class="mt-4 max-w-3xl text-center text-white">
                <h2 id="gallery-lightbox-title" class="text-xl font-black" x-text="lightboxTitle"></h2>
                <p x-show="lightboxCaption" class="mt-2 text-sm leading-6 text-slate-200" x-text="lightboxCaption"></p>
                <p class="mt-3 text-xs font-semibold uppercase tracking-widest text-slate-400">Press Esc or click outside the image to close</p>
            </div>
        </div>
    </div>
</div>
@endsection
