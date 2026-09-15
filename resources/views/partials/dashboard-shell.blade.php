@php
    $dashboardTitle = trim((string) ($dashboardTitle ?? ''));
    if ($dashboardTitle === '' || $dashboardTitle === 'Dashboard') {
        $routeSection = explode('.', (string) request()->route()?->getName())[0];
        $dashboardTitle = $routeSection === 'dashboard' ? 'Dashboard' : \Illuminate\Support\Str::headline($routeSection ?: 'Dashboard');
    }
@endphp
<div class="dashboard-shell" x-data="{ menuOpen: window.innerWidth >= 1024, desktop: window.innerWidth >= 1024 }"
    @resize.window.debounce.150ms="if (desktop !== (window.innerWidth >= 1024)) { desktop = window.innerWidth >= 1024; menuOpen = desktop; }"
    @keydown.escape.window="if (!desktop) menuOpen = false">
    <a href="#main" class="dashboard-skip-link">Skip to content</a>
    <livewire:layouts.header />
    <div class="dashboard-workspace">
        <livewire:layouts.menu />
        <div class="dashboard-page">
            <header class="dashboard-page-heading">
                <div>
                    <p class="dashboard-eyebrow">School workspace</p>
                    <h1 class="dashboard-page-title">
                        @if (!empty($dashboardIcon))<i class="{{ $dashboardIcon }}" aria-hidden="true"></i>@endif
                        {{ $dashboardTitle }}
                    </h1>
                    @if (!empty($dashboardDescription))<p class="dashboard-page-description">{{ $dashboardDescription }}</p>@endif
                </div>
                <x-show-set-school />
                @isset($breadcrumbs)<x-breadcrumbs :paths="$breadcrumbs" />@endisset
            </header>
            @include('partials.current-term-theme', ['compact' => true])
            <main id="main" class="dashboard-content" tabindex="-1">
                @isset($dashboardSlot)
                    {{ $dashboardSlot }}
                @else
                    @yield('content')
                @endisset
            </main>
            @include('partials.dashboard-floating-countdown')
            <footer class="dashboard-footer">
                <span>&copy; {{ date('Y') }} Watersprings International School</span>
                <a href="{{ route('home') }}">Visit school website <i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
                <span>Created by <a href="https://dayoebe.github.io" target="_blank" rel="noopener noreferrer">Wireless Terminal</a></span>
            </footer>
        </div>
    </div>
    @livewire('common.display-status')
</div>
