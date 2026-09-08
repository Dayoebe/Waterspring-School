@extends('layouts.app', ['mode' => 'public'])

@section('title', 'Admission')

@php
    $settings = $publicSiteSettings ?? [];
    $admissionPage = data_get($settings, 'admission_page', []);
    $contactPhonePrimary = (string) data_get($settings, 'contact.phone_primary', '');
    $contactPhonePrimaryHref = preg_replace('/[^0-9+]/', '', $contactPhonePrimary);
    $pageMeta = \App\Support\PublicSeo::pageMeta('admission', $settings);
@endphp

@section('content')
    <div class="bg-slate-50 text-slate-900">
        <section class="relative overflow-hidden bg-slate-900 py-14 sm:py-16">
            <div class="pointer-events-none absolute inset-0">
                <div class="absolute -left-20 -top-20 h-56 w-56 rounded-full bg-sky-500/20 blur-3xl"></div>
                <div class="absolute bottom-0 right-0 h-64 w-64 rounded-full bg-violet-500/20 blur-3xl"></div>
            </div>

            <div class="relative mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <div class="animate__animated animate__fadeInDown inline-flex items-center gap-2 rounded-full border border-yellow-200/40 bg-yellow-500/10 px-3 py-1 text-xs font-semibold text-yellow-200">
                    <i class="fas fa-file-signature"></i>
                    <span>{{ data_get($admissionPage, 'hero_badge') }}</span>
                </div>

                <h1 class="animate__animated animate__fadeInUp mt-4 text-3xl font-black leading-tight text-white sm:text-4xl lg:text-5xl">
                    {{ data_get($admissionPage, 'hero_title') }}
                    <span class="mt-1 block text-sky-300">{{ data_get($admissionPage, 'hero_highlight') }}</span>
                </h1>

                <p class="animate__animated animate__fadeInUp animate__delay-1s mt-4 max-w-3xl text-sm leading-relaxed text-slate-200 sm:text-base">
                    {{ data_get($admissionPage, 'hero_description') }}
                </p>

                <div class="mt-6 flex flex-wrap gap-2 text-xs font-bold">
                    <a href="#form" class="rounded-full bg-sky-100 px-4 py-2 text-sky-700 transition hover:bg-sky-200">Admission Form</a>
                    <a href="#process" class="rounded-full bg-blue-100 px-4 py-2 text-blue-700 transition hover:bg-blue-200">Process</a>
                    <a href="#requirements" class="rounded-full bg-teal-100 px-4 py-2 text-teal-700 transition hover:bg-teal-200">Requirements</a>
                </div>
            </div>
        </section>

        @include('partials.public-page-summary', ['page' => $pageMeta])

        <section id="process" class="py-12">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <h2 class="text-3xl font-black text-slate-900">Joining Watersprings</h2>
                <div class="mt-6 grid gap-5 md:grid-cols-3">
                    <article class="rounded-2xl border border-sky-100 bg-white p-6"><h3 class="text-xl font-bold text-sky-900">Explore our school</h3><p class="mt-3 text-slate-600">Read about our classes and arrange a visit with your child to meet the team and see the learning environment.</p></article>
                    <article class="rounded-2xl border border-sky-100 bg-white p-6"><h3 class="text-xl font-bold text-sky-900">Apply</h3><p class="mt-3 text-slate-600">The school accepts online applications. Contact the office if you need help or a copy of the admission form.</p></article>
                    <article class="rounded-2xl border border-sky-100 bg-white p-6"><h3 class="text-xl font-bold text-sky-900">Speak with admissions</h3><p class="mt-3 text-slate-600">Confirm available places, current fees and the next steps for your child’s entry class with the school.</p></article>
                </div>
                <a href="{{ route('prospectus') }}" class="mt-6 inline-block font-bold text-sky-700 hover:underline">Read the school prospectus →</a>
            </div>
        </section>

        <section id="fees" class="bg-sky-50 py-12">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <h2 class="text-3xl font-black text-slate-900">School fees</h2>
                <p class="mt-4 max-w-3xl leading-relaxed text-slate-600">The school’s fee structure covers tuition, books and educational resources, school lunch, clubs and co-curricular activities, photographs, and external examinations or preparation. Contact the office for current amounts and payment arrangements.</p>
            </div>
        </section>

        <section id="form" class="bg-white py-12">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <div class="mb-6">
                    <p class="text-xs font-bold uppercase tracking-wider text-sky-700">Admission Form</p>
                    <h2 class="mt-2 text-2xl font-black text-slate-900 sm:text-3xl">Apply for a place</h2>
                    <p class="mt-2 text-sm text-slate-600">Have your child’s details and parent or guardian contact information ready.</p>
                </div>

                @if (\App\Models\School::query()->exists())
                    @livewire('admissions.public-admission-form')
                @else
                    <div class="rounded-2xl border border-sky-100 bg-sky-50 p-6">
                        <p class="text-slate-700">Apply using Watersprings’ online admissions form, or contact the school office for assistance.</p>
                        <a href="{{ $publicSiteSettings['official_admission_url'] }}" target="_blank" rel="noopener noreferrer" class="site-primary-bg mt-5 inline-block rounded-xl px-6 py-3 font-bold text-white">Open school application form ↗</a>
                    </div>
                @endif
            </div>
        </section>

        <section id="requirements" class="py-12">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-xs font-bold uppercase tracking-wider text-violet-700">Required Information</p>
                        <ul class="mt-3 space-y-2 text-sm text-slate-700">
                            <li><i class="fas fa-check-circle mr-2 text-lime-600"></i>Student full name, gender, and date of birth</li>
                            <li><i class="fas fa-check-circle mr-2 text-lime-600"></i>Preferred class (and section if available)</li>
                            <li><i class="fas fa-check-circle mr-2 text-lime-600"></i>Parent or guardian full contact details</li>
                            <li><i class="fas fa-check-circle mr-2 text-lime-600"></i>Residential address and previous school (if any)</li>
                        </ul>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-xs font-bold uppercase tracking-wider text-teal-700">Need Help?</p>
                        <p class="mt-3 text-sm text-slate-700">
                            If you need help filling the form, contact the admissions team.
                        </p>
                        <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                            <a href="{{ route('contact') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-sky-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-sky-700">
                                <i class="fas fa-envelope"></i>
                                <span>Contact Admissions</span>
                            </a>
                            <a href="tel:{{ $contactPhonePrimaryHref }}" class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-100">
                                <i class="fas fa-phone"></i>
                                <span>Call {{ $contactPhonePrimary }}</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
