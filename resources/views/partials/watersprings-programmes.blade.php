@php($programmeStyles = [
    ['bg' => 'bg-cyan-50', 'border' => 'border-cyan-200', 'badge' => 'bg-cyan-600', 'number' => '01'],
    ['bg' => 'bg-amber-50', 'border' => 'border-amber-200', 'badge' => 'bg-amber-500', 'number' => '02'],
    ['bg' => 'bg-emerald-50', 'border' => 'border-emerald-200', 'badge' => 'bg-emerald-600', 'number' => '03'],
    ['bg' => 'bg-violet-50', 'border' => 'border-violet-200', 'badge' => 'bg-violet-600', 'number' => '04'],
])
<div class="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
    @foreach ($publicSiteSettings['programmes'] as $programme)
        @php($style = $programmeStyles[$loop->index % count($programmeStyles)])
        <article id="{{ $programme['id'] }}" class="{{ $style['bg'] }} {{ $style['border'] }} group scroll-mt-32 rounded-3xl border p-6 shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-xl">
            <span class="{{ $style['badge'] }} inline-flex h-10 w-10 items-center justify-center rounded-2xl text-sm font-black text-white shadow-sm">{{ $style['number'] }}</span>
            <p class="mt-5 text-sm font-semibold text-slate-600">{{ $programme['subtitle'] }}</p>
            <h3 class="mt-3 text-xl font-bold text-slate-900">{{ $programme['title'] }}</h3>
            <p class="mt-4 leading-relaxed text-slate-600">{{ $programme['text'] }}</p>
        </article>
    @endforeach
</div>
