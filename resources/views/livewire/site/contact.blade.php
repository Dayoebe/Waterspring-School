@extends('layouts.app', ['mode' => 'public'])

@section('title', 'Contact Us')

@php
    $settings = $publicSiteSettings ?? [];
    $contactPage = data_get($settings, 'contact_page', []);
    $contactAddress = data_get($settings, 'contact.address', '');
    $contactPhonePrimary = data_get($settings, 'contact.phone_primary', '');
    $contactPhoneSecondary = data_get($settings, 'contact.phone_secondary', '');
    $contactEmail = data_get($settings, 'contact.email', '');
    $mapEmbedUrl = data_get($settings, 'contact.map_embed_url', '');
    $pageMeta = \App\Support\PublicSeo::pageMeta('contact', $settings);
@endphp

@section('content')
    <div class="bg-slate-50 text-slate-900">
        <section class="bg-slate-900 py-14 text-white sm:py-16">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <div class="inline-flex items-center gap-2 rounded-full border border-sky-200/40 bg-sky-500/10 px-3 py-1 text-xs font-semibold text-sky-200">
                    <i class="fas fa-envelope-open-text"></i>
                    <span>{{ data_get($contactPage, 'hero_badge') }}</span>
                </div>

                <h1 class="mt-4 text-3xl font-black leading-tight sm:text-4xl lg:text-5xl">
                    {{ data_get($contactPage, 'hero_title') }}
                </h1>

                <p class="mt-4 max-w-3xl text-sm leading-relaxed text-slate-200 sm:text-base">
                    {{ data_get($contactPage, 'hero_description') }}
                </p>
            </div>
        </section>

        @include('partials.public-page-summary', ['page' => $pageMeta])

        <section class="py-12">
            <div class="mx-auto grid max-w-6xl grid-cols-1 gap-6 px-4 sm:px-6 lg:grid-cols-2 lg:px-8">
                <div class="space-y-4">
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="text-xl font-black text-slate-900">Contact Information</h2>

                        <div class="mt-4 space-y-3 text-sm text-slate-700">
                            <p>
                                <strong>Address:</strong><br>
                                {{ $contactAddress }}
                            </p>
                            <p>
                                <strong>Phone:</strong><br>
                                @if ($contactPhonePrimary !== '')
                                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $contactPhonePrimary) }}" class="font-semibold text-blue-700 hover:underline">{{ $contactPhonePrimary }}</a><br>
                                @endif
                                @if ($contactPhoneSecondary !== '')
                                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $contactPhoneSecondary) }}" class="font-semibold text-blue-700 hover:underline">{{ $contactPhoneSecondary }}</a>
                                @endif
                            </p>
                            <p>
                                <strong>Email:</strong><br>
                                @if ($contactEmail !== '')
                                    <a href="mailto:{{ $contactEmail }}" class="font-semibold text-blue-700 hover:underline">{{ $contactEmail }}</a>
                                @endif
                            </p>
                        </div>
                    </div>

                    <iframe class="h-80 w-full rounded-2xl border border-slate-200 shadow-sm" loading="lazy" style="border:0;" allowfullscreen
                        title="Map showing {{ \App\Support\PublicSeo::siteName($settings) }} location"
                        referrerpolicy="no-referrer-when-downgrade"
                        src="{{ $mapEmbedUrl }}">
                    </iframe>
                </div>

                <div>
                    <section id="visit" class="mb-6 rounded-2xl border border-sky-100 bg-white p-6">
                        <h2 class="text-2xl font-black text-sky-900">Arrange a visit</h2>
                        <p class="mt-4 leading-relaxed text-slate-600">Come with your child to meet our team and take a tour of the school. Call or email the office with your preferred date so we can arrange your visit.</p>
                        <a href="mailto:{{ $contactEmail }}?subject=School%20visit%20enquiry" class="site-primary-bg mt-5 inline-block rounded-xl px-5 py-3 font-bold text-white">Email the school office</a>
                        <h3 class="mt-6 font-bold">Other enquiries</h3>
                        <p class="mt-3 break-words text-sm"><a class="text-sky-700 hover:underline" href="mailto:{{ $publicSiteSettings['contact']['admin_email'] }}">{{ $publicSiteSettings['contact']['admin_email'] }}</a></p>
                        <p class="mt-3 break-words text-sm"><a class="text-sky-700 hover:underline" href="mailto:{{ $publicSiteSettings['contact']['suggestions_email'] }}">{{ $publicSiteSettings['contact']['suggestions_email'] }}</a></p>
                    </section>
                    @if (\App\Models\School::query()->exists())
                        @livewire('contacts.public-contact-form')
                    @endif
                </div>
            </div>
        </section>
    </div>
@endsection
