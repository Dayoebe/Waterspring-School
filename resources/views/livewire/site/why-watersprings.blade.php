@extends('layouts.app', ['mode' => 'public'])
@section('title', 'Why Watersprings')
@section('content')
<div class="bg-slate-50 text-slate-900">
    @include('partials.watersprings-hero', ['title' => 'Why choose Watersprings?', 'description' => $publicSiteSettings['school_promise']])
    <section class="mx-auto grid max-w-6xl gap-6 px-4 py-14 sm:px-6 md:grid-cols-2 lg:px-8">
        @foreach ([
            ['A connected learning journey', 'Students can grow within one caring community from the early years and primary school into Watersprings International College for Years 7, 8 and 9.'],
            ['Christian values', 'Character and community service are part of school life. Excellence, uprightness, service and enterprise guide how we learn and live together.'],
            ['Purpose-built learning spaces', 'The school describes ICT-enabled classrooms, CCTV monitoring, music facilities and dedicated spaces for learning and play.'],
            ['A broad education', 'Our curriculum combines academic learning with practical experiences and clubs in areas such as music, languages, art, cooking and sport.'],
            ['Confidence and independence', 'Learners are encouraged to ask questions, explore ideas, work with others and take increasing responsibility as they progress towards Year 9.'],
            ['A welcoming community', 'Families are invited to visit, meet the team and explore whether Watersprings is the right environment for their child.'],
        ] as [$heading, $text])
            <article class="rounded-2xl border border-sky-100 bg-white p-7"><h2 class="text-2xl font-bold text-sky-900">{{ $heading }}</h2><p class="mt-4 leading-relaxed text-slate-600">{{ $text }}</p></article>
        @endforeach
    </section>
    @include('partials.watersprings-faq')
</div>
@endsection
