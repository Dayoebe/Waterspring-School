@extends('layouts.app', ['mode' => 'public'])
@section('title', 'Why Watersprings')
@section('content')
<div class="bg-slate-50 text-slate-900">
    @include('partials.watersprings-hero', ['eyebrow' => 'This is where your child belongs', 'title' => 'A school experience shaped around the whole child', 'description' => $publicSiteSettings['school_promise'].' Watersprings brings learning, character, creativity and community together from the early years through college Years 7–9.'])

    <section class="mx-auto grid max-w-6xl items-center gap-10 px-4 py-16 sm:px-6 lg:grid-cols-2 lg:px-8">
        <div class="grid grid-cols-2 gap-4">
            <img src="{{ asset('images/watersprings/learn.jpg') }}" alt="Learning at Watersprings" class="col-span-2 aspect-[16/9] w-full rounded-3xl object-cover shadow-lg" loading="lazy">
            <img src="{{ asset('images/watersprings/play.jpg') }}" alt="Play at Watersprings" class="aspect-square w-full rounded-3xl object-cover shadow-lg" loading="lazy">
            <img src="{{ asset('images/watersprings/grow.jpg') }}" alt="Growing at Watersprings" class="aspect-square w-full rounded-3xl object-cover shadow-lg" loading="lazy">
        </div>
        <div>
            <p class="text-sm font-bold uppercase tracking-[0.18em] text-sky-700">Learn • Play • Grow • Together</p>
            <h2 class="mt-3 text-3xl font-black sm:text-4xl">More than a place to attend classes</h2>
            <p class="mt-5 leading-8 text-slate-600">A child’s school years shape how they think, relate to others and approach new opportunities. Watersprings supports that growth through purposeful teaching, Christian values, creative activity and a community where each learner is known and valued.</p>
            <blockquote class="mt-7 rounded-2xl border-l-4 border-amber-400 bg-amber-50 p-6 text-lg font-bold leading-8 text-slate-800">“{{ $publicSiteSettings['mission'] }}”</blockquote>
        </div>
    </section>

    <section class="bg-white py-16">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl"><p class="text-sm font-bold uppercase tracking-[0.18em] text-violet-700">What families can expect</p><h2 class="mt-3 text-3xl font-black sm:text-4xl">Six parts of the Watersprings experience</h2><p class="mt-4 leading-7 text-slate-600">Together, these priorities create a balanced environment for learning and personal development.</p></div>
            @php($reasons = [
                ['fa-route', 'A connected learning journey', 'Students can grow within one caring community from the early years and primary school into Watersprings International College for Years 7, 8 and 9.', 'bg-cyan-50 border-cyan-200 text-cyan-700'],
                ['fa-cross', 'Christian values', 'Character and community service are part of school life. Excellence, uprightness, service and enterprise guide how we learn and live together.', 'bg-amber-50 border-amber-200 text-amber-700'],
                ['fa-school', 'Purposeful learning spaces', 'The school describes ICT-enabled classrooms, CCTV monitoring, music facilities and dedicated spaces for learning and play.', 'bg-emerald-50 border-emerald-200 text-emerald-700'],
                ['fa-palette', 'A broad education', 'Academic learning is supported by practical experiences and clubs in areas such as music, languages, art, cooking and sport.', 'bg-rose-50 border-rose-200 text-rose-700'],
                ['fa-seedling', 'Confidence and independence', 'Learners are encouraged to ask questions, explore ideas, work with others and take increasing responsibility as they progress towards Year 9.', 'bg-violet-50 border-violet-200 text-violet-700'],
                ['fa-handshake-angle', 'A welcoming community', 'Families are invited to visit, meet the team and explore whether Watersprings is the right environment for their child.', 'bg-blue-50 border-blue-200 text-blue-700'],
            ])
            <div class="mt-9 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($reasons as [$icon, $heading, $text, $style])
                    <article class="{{ $style }} rounded-3xl border p-7 transition hover:-translate-y-1 hover:shadow-xl"><span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-xl shadow-sm"><i class="fas {{ $icon }}"></i></span><h3 class="mt-5 text-xl font-black text-slate-900">{{ $heading }}</h3><p class="mt-3 leading-7 text-slate-600">{{ $text }}</p></article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="bg-gradient-to-br from-emerald-950 to-teal-900 py-16 text-white">
        <div class="mx-auto grid max-w-6xl gap-10 px-4 sm:px-6 lg:grid-cols-[0.8fr_1.2fr] lg:px-8">
            <div><p class="text-sm font-bold uppercase tracking-[0.18em] text-emerald-300">Values in action</p><h2 class="mt-3 text-3xl font-black sm:text-4xl">Character is part of the curriculum</h2><p class="mt-5 leading-8 text-emerald-100">The school’s values give learners a practical framework for effort, choices, relationships and contribution to their community.</p></div>
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($publicSiteSettings['values'] as $value)
                    <article class="rounded-2xl border border-white/10 bg-white/10 p-6 backdrop-blur"><h3 class="text-xl font-black text-amber-300">{{ $value['title'] }}</h3><p class="mt-3 leading-7 text-emerald-50">{{ $value['text'] }}</p></article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:px-8">
        <div class="rounded-[2rem] bg-gradient-to-r from-sky-100 via-cyan-50 to-amber-50 p-8 sm:p-10 lg:flex lg:items-center lg:justify-between">
            <div class="max-w-2xl"><p class="font-bold text-sky-700">The best way to decide</p><h2 class="mt-2 text-3xl font-black">See the school community for yourself</h2><p class="mt-3 leading-7 text-slate-600">Arrange a visit, bring your child and speak with the school team about the right class and next steps.</p></div>
            <div class="mt-6 flex flex-wrap gap-3 lg:mt-0"><a href="{{ route('contact') }}#visit" class="site-primary-bg rounded-xl px-6 py-3 font-bold text-white">Arrange a visit</a><a href="{{ route('admission') }}" class="rounded-xl border border-sky-200 bg-white px-6 py-3 font-bold text-sky-800">Explore admissions</a></div>
        </div>
    </section>
    @include('partials.watersprings-faq')
</div>
@endsection
