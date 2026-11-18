@props(['recognition', 'compact' => false, 'shareable' => false])

<article {{ $attributes->class(['group overflow-hidden rounded-3xl border border-amber-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-xl']) }}>
    <div class="relative overflow-hidden bg-sky-950">
        <img src="{{ $recognition->photo_url }}" alt="{{ $recognition->student?->name }} — Face of the Week" loading="lazy" decoding="async"
            class="{{ $compact ? 'h-52' : 'h-72' }} w-full object-cover object-center transition duration-500 group-hover:scale-[1.03]">
        <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-sky-950 via-sky-950/60 to-transparent px-5 pb-5 pt-16 text-white">
            <div class="flex items-end justify-between gap-3">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-[0.22em] text-amber-300"><i class="fas fa-star mr-1"></i> Face of the Week</p>
                    <h3 class="mt-2 text-2xl font-black leading-tight">{{ $recognition->student?->name }}</h3>
                    <p class="mt-1 text-sm font-semibold text-sky-100">{{ $recognition->myClass?->name }}</p>
                </div>
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-amber-300/50 bg-amber-300 text-sky-950 shadow"><i class="fas fa-award"></i></span>
            </div>
        </div>
    </div>
    <div class="p-5">
        <div class="flex flex-wrap items-center gap-2">
            <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-900">{{ $recognition->category_label }}</span>
            <span class="text-xs font-semibold text-slate-500">{{ $recognition->week_start_date->format('M j') }}–{{ $recognition->week_end_date->format('M j, Y') }}</span>
        </div>
        @unless($compact)
            <p class="mt-4 text-sm leading-6 text-slate-600">{{ $recognition->reason }}</p>
            <p class="mt-4 border-l-4 border-amber-300 pl-4 text-sm font-semibold italic leading-6 text-sky-900">“{{ $recognition->congratulatory_message }}”</p>
        @endunless
        @if($shareable)
            <div data-recognition-share data-name="{{ $recognition->student?->name }}" data-class="{{ $recognition->myClass?->name }}"
                data-category="{{ $recognition->category_label }}" data-week="{{ $recognition->week_start_date->format('M j') }}–{{ $recognition->week_end_date->format('M j, Y') }}"
                data-message="{{ $recognition->congratulatory_message }}" data-photo="{{ $recognition->photo_url }}"
                data-logo="{{ auth()->user()?->school?->logo_url ?? asset(config('app.logo', 'images/watersprings/logo.png')) }}" class="mt-5 flex flex-wrap gap-2">
                <button type="button" onclick="window.downloadRecognitionCard(this.closest('[data-recognition-share]'), 'download')" class="inline-flex items-center gap-2 rounded-xl bg-sky-900 px-4 py-2 text-sm font-bold text-white hover:bg-sky-800"><i class="fas fa-download"></i> Download graphic</button>
                <button type="button" onclick="window.downloadRecognitionCard(this.closest('[data-recognition-share]'), 'share')" class="inline-flex items-center gap-2 rounded-xl bg-emerald-700 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-600"><i class="fas fa-share-nodes"></i> Share</button>
                <button type="button" onclick="navigator.clipboard.writeText(@js(route('face-of-the-week'))).then(() => this.textContent = 'Link copied')" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50"><i class="fas fa-link"></i> Copy link</button>
                <a href="{{ route('face-of-the-week') }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50"><i class="fas fa-arrow-up-right-from-square"></i> Open public page</a>
            </div>
        @endif
    </div>
</article>
