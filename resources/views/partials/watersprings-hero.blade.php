<section class="relative isolate overflow-hidden bg-gradient-to-br from-sky-950 via-sky-900 to-cyan-900 py-16 text-white sm:py-24">
    <div class="pointer-events-none absolute inset-0" aria-hidden="true">
        <div class="absolute -left-24 -top-28 h-72 w-72 rounded-full bg-cyan-400/20 blur-3xl"></div>
        <div class="absolute -bottom-32 right-0 h-80 w-80 rounded-full bg-amber-300/15 blur-3xl"></div>
        <div class="absolute right-[12%] top-10 h-20 w-20 rotate-12 rounded-3xl border border-white/10"></div>
    </div>
    <div class="relative mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
        <div class="max-w-4xl">
            <p class="inline-flex items-center gap-2 rounded-full border border-cyan-200/25 bg-white/10 px-4 py-2 text-xs font-bold uppercase tracking-[0.2em] text-cyan-100 backdrop-blur">
                <span class="h-2 w-2 rounded-full bg-amber-300"></span>
                {{ $eyebrow ?? 'Watersprings International School Akure' }}
            </p>
            <h1 class="mt-6 text-4xl font-black leading-[1.08] tracking-tight sm:text-5xl lg:text-6xl">{{ $title }}</h1>
            <p class="mt-6 max-w-3xl text-base leading-8 text-sky-100 sm:text-lg">{{ $description }}</p>
            @isset($actions)
                <div class="mt-8 flex flex-wrap gap-3">{!! $actions !!}</div>
            @endisset
        </div>
    </div>
</section>
