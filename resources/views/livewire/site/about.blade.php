@extends('layouts.app', ['mode' => 'public'])
@section('title', 'About Watersprings')
@section('content')
<div class="bg-slate-50 text-slate-900">
    @include('partials.watersprings-hero', ['title' => 'A place to learn, play and grow together', 'description' => $publicSiteSettings['about_summary']])
    <section id="history" class="mx-auto max-w-6xl px-4 py-14 sm:px-6 lg:px-8">
        <p class="text-sm font-bold uppercase tracking-widest text-sky-700">Our history</p>
        <h2 class="mt-3 text-3xl font-black">Built on faith, family and a love for children</h2>
        <div class="mt-5 max-w-4xl space-y-4 leading-relaxed text-slate-600">
            <p>Watersprings grew from the vision of the late Mrs. Olajumoke Babatunde. Her earlier school, His Mercy Crèche and Playgroup, cared for children from 2002 to 2013.</p>
            <p>With her husband, Dr. Olukayode Babatunde, and the support of friends and family, she developed that vision into Watersprings International School. The school continues her commitment to caring for each child and preparing young people to contribute to their communities.</p>
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
        <p class="mt-5 max-w-4xl leading-relaxed text-slate-600">Christian values guide our school community. Practical learning encourages independence and curiosity, while community service helps children consider the needs of others. We want each pupil to find joy in learning and confidence in who they are.</p>
        <div class="mt-7 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($publicSiteSettings['values'] as $value)
                <article class="rounded-xl border border-sky-100 bg-white p-6"><h3 class="text-xl font-bold text-sky-800">{{ $value['title'] }}</h3><p class="mt-3 text-slate-600">{{ $value['text'] }}</p></article>
            @endforeach
        </div>
    </section>
    <section id="leadership" class="mx-auto grid max-w-6xl gap-6 px-4 pb-12 sm:px-6 md:grid-cols-2 lg:px-8">
        <article class="rounded-2xl border border-slate-200 bg-white p-7">
            <img src="{{ asset('images/watersprings/head-of-school.jpg') }}" alt="Adedamola Ogidan" loading="lazy" class="mb-5 h-40 w-32 rounded-xl object-cover" width="128" height="160">
            <p class="text-sm font-bold text-sky-700">Head of School</p><h2 class="mt-2 text-2xl font-black">Adedamola Ogidan</h2>
            <p class="mt-4 leading-relaxed text-slate-600">Our Head of School welcomes families to a community that combines academic learning with character development, extracurricular interests and a lifelong curiosity about the world.</p>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-7">
            <p class="text-sm font-bold text-sky-700">CEO</p><h2 class="mt-2 text-2xl font-black">Dr. Olukayode Abimbola Babatunde</h2>
            <p class="mt-4 leading-relaxed text-slate-600">The CEO’s welcome describes a purpose-built school in the Ijapo area of Akure, with small classes, ICT and music facilities, and a curriculum based on England’s National Curriculum, adapted to the Nigerian setting.</p>
            <a href="{{ route('contact') }}#visit" class="mt-6 inline-block font-bold text-sky-700 hover:underline">Visit our school →</a>
        </article>
    </section>
    @include('partials.watersprings-faq')
</div>
@endsection
