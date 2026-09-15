@php
    $themeSchool = auth()->check() ? auth()->user()?->school : ($publicSiteSchool ?? null);
    $themeTerm = $themeSchool?->semester;
    $themeAccent = preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $themeTerm?->theme_color)
        ? $themeTerm->theme_color
        : '#0875a5';
@endphp

@if($themeTerm?->theme_title)
    <section class="{{ ($compact ?? false) ? 'mx-4 mt-4 sm:mx-6 lg:mx-8' : 'bg-white py-10' }}" aria-label="Current term theme">
        <div class="{{ ($compact ?? false) ? '' : 'mx-auto max-w-6xl px-4 sm:px-6 lg:px-8' }}">
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-950 text-white shadow-sm" style="border-top: 5px solid {{ $themeAccent }}">
                <div class="grid gap-5 px-5 py-6 sm:px-7 {{ ($compact ?? false) ? 'lg:grid-cols-[1fr_auto] lg:items-center' : 'md:grid-cols-[1fr_auto] md:items-center' }}">
                    <div>
                        <div class="flex flex-wrap items-center gap-2 text-xs font-bold uppercase tracking-[0.16em] text-sky-200">
                            <span>{{ $themeTerm->name }}</span>
                            @if($themeTerm->academicYear)<span aria-hidden="true">•</span><span>{{ $themeTerm->academicYear->name }}</span>@endif
                        </div>
                        <h2 class="mt-2 {{ ($compact ?? false) ? 'text-xl' : 'text-2xl sm:text-3xl' }} font-black">{{ $themeTerm->theme_title }}</h2>
                        @if($themeTerm->theme_description)<p class="mt-2 max-w-3xl text-sm leading-6 text-slate-200">{{ $themeTerm->theme_description }}</p>@endif
                    </div>
                    @if($themeTerm->theme_scripture)
                        <div class="rounded-xl bg-white/10 px-4 py-3 text-sm font-semibold text-sky-100 {{ ($compact ?? false) ? 'lg:max-w-sm' : 'md:max-w-sm' }}">{{ $themeTerm->theme_scripture }}</div>
                    @endif
                </div>
                @if($themeTerm->theme_focus || $themeTerm->starts_on || $themeTerm->ends_on)
                    <div class="flex flex-col gap-2 border-t border-white/10 px-5 py-3 text-xs text-slate-300 sm:flex-row sm:items-center sm:justify-between sm:px-7">
                        @if($themeTerm->theme_focus)<p><strong class="text-white">Term focus:</strong> {{ $themeTerm->theme_focus }}</p>@endif
                        @if($themeTerm->starts_on || $themeTerm->ends_on)<p class="shrink-0">{{ $themeTerm->starts_on?->format('j M Y') ?? 'Date pending' }} – {{ $themeTerm->ends_on?->format('j M Y') ?? 'Date pending' }}</p>@endif
                    </div>
                @endif
            </div>
        </div>
    </section>
@endif
