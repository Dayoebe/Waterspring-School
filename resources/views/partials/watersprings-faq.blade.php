<section id="faq" class="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:px-8">
    <p class="text-sm font-bold uppercase tracking-[0.18em] text-sky-700">Helpful information</p>
    <h2 class="mt-3 text-3xl font-black text-slate-900">Questions from families</h2>
    <p class="mt-3 max-w-2xl leading-7 text-slate-600">Start with these frequently asked questions, then contact the school office for current dates, fees and availability.</p>
    <div class="mt-6 space-y-3">
        @foreach (\App\Support\PublicSeo::faqItems(request()->route()?->getName(), $publicSiteSettings) as $faq)
            <details class="group rounded-2xl border border-sky-100 bg-white p-5 shadow-sm open:border-sky-300 open:shadow-md">
                <summary class="cursor-pointer font-bold text-sky-950 marker:text-amber-500">{{ $faq['q'] }}</summary>
                <p class="mt-3 leading-relaxed text-slate-600">{{ $faq['a'] }}</p>
            </details>
        @endforeach
    </div>
</section>
