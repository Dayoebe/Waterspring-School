<div class="grid gap-6 md:grid-cols-3">
    @foreach ($publicSiteSettings['programmes'] as $programme)
        <article id="{{ $programme['id'] }}" class="scroll-mt-32 rounded-2xl border border-sky-100 bg-white p-6 shadow-sm">
            <p class="text-sm font-semibold text-sky-700">{{ $programme['subtitle'] }}</p>
            <h3 class="mt-3 text-xl font-bold text-slate-900">{{ $programme['title'] }}</h3>
            <p class="mt-4 leading-relaxed text-slate-600">{{ $programme['text'] }}</p>
        </article>
    @endforeach
</div>
