<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">

    <meta name="description"
        content="Watersprings International School Akure — school administration and academic management.">
    <meta name="keywords"
        content="Watersprings International School Akure, school administration, academic records, staff portal">
    <meta name="author" content="Watersprings International School Akure">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="googlebot" content="noindex, nofollow, noarchive">

    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="canonical" href="{{ url()->current() }}">
    <link rel="icon" href="{{ asset('images/watersprings/logo.png') }}" type="image/png">
    <link rel="shortcut icon" href="{{ asset(config('app.favicon', 'images/watersprings/logo.png')) }}" type="image/png">
    @include('partials.pwa-head', [
        'pwaThemeColor' => '#0875a5',
        'pwaTitle' => $title ?? config('app.name', 'School Portal'),
        'pwaIcon' => asset('images/watersprings/logo.png'),
    ])

    <meta property="og:title" content="{{ $title ?? config('app.name', 'Watersprings International School Akure') }}">
    <meta property="og:description"
        content="Excellence in education, character, and leadership. Explore admissions, academics, and student life at Watersprings International School Akure.">
    <meta property="og:image" content="{{ asset('images/watersprings/logo.png') }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Watersprings International School Akure">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title ?? config('app.name', 'Watersprings International School Akure') }}">
    <meta name="twitter:description"
        content="Watersprings International School Akure — school administration and academic management.">
    <meta name="twitter:image" content="{{ asset('images/watersprings/logo.png') }}">

    <title>{{ $title ?? config('app.name', 'Watersprings International School Akure') }}</title>

    @vite('resources/css/app.css')
    <livewire:styles />
    @stack('head')
    @include('partials.pwa-register', ['pwaThemeColor' => '#0875a5'])
</head>

<body class="dashboard-ui font-sans">
    @include('partials.dashboard-shell', [
        'dashboardTitle' => $title ?? 'Dashboard',
        'dashboardDescription' => $description ?? null,
        'dashboardIcon' => $icon ?? null,
        'dashboardSlot' => $slot,
    ])

    @include('partials.livewire-scripts')
    @stack('scripts')
</body>

</html>
