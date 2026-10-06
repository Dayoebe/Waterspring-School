@php
    $themeSchool = auth()->check() ? auth()->user()?->school : ($publicSiteSchool ?? null);
    $themeTerm = $themeSchool?->semester;
    $themeAccent = preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $themeTerm?->theme_color)
        ? $themeTerm->theme_color
        : '#0875a5';
@endphp

@if($themeTerm?->theme_title)
    <section class="term-theme-section {{ ($compact ?? false) ? 'is-compact' : '' }}" aria-label="Current term theme" style="--term-accent: {{ $themeAccent }}">
        <div class="term-theme-container">
            <div class="term-theme-card">
                <div class="term-theme-accent" aria-hidden="true"></div>
                <div class="term-theme-main">
                    <div class="term-theme-copy">
                        <p class="term-theme-kicker">
                            <span class="term-theme-icon"><i class="fas fa-sparkles" aria-hidden="true"></i></span>
                            Theme for {{ $themeTerm->name }}
                            @if($themeTerm->academicYear)<span class="term-theme-separator" aria-hidden="true">/</span> {{ $themeTerm->academicYear->name }}@endif
                        </p>
                        <h2>{{ $themeTerm->theme_title }}</h2>
                        @if($themeTerm->theme_description)<p class="term-theme-description">{{ $themeTerm->theme_description }}</p>@endif
                    </div>
                    @if($themeTerm->theme_scripture)
                        <blockquote class="term-theme-quote"><i class="fas fa-quote-left" aria-hidden="true"></i><span>{{ $themeTerm->theme_scripture }}</span></blockquote>
                    @endif
                </div>
                @if($themeTerm->theme_focus || $themeTerm->starts_on || $themeTerm->ends_on)
                    <div class="term-theme-meta">
                        @if($themeTerm->theme_focus)<p><strong>Our focus</strong><span>{{ $themeTerm->theme_focus }}</span></p>@endif
                        @if($themeTerm->starts_on || $themeTerm->ends_on)<p class="term-theme-dates"><i class="far fa-calendar" aria-hidden="true"></i><span>{{ $themeTerm->starts_on?->format('j M Y') ?? 'Date pending' }} – {{ $themeTerm->ends_on?->format('j M Y') ?? 'Date pending' }}</span></p>@endif
                    </div>
                @endif
            </div>
        </div>
    </section>
@endif
