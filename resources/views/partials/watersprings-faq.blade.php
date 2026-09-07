<section id="faq" class="mx-auto max-w-6xl px-4 py-12 sm:px-6 lg:px-8">
    <h2 class="text-2xl font-black text-slate-900">Questions from families</h2>
    <div class="mt-6 space-y-3">
        @foreach (\App\Support\PublicSeo::faqItems(request()->route()?->getName(), $publicSiteSettings) as $faq)
            <details class="rounded-xl border border-slate-200 bg-white p-5">
                <summary class="cursor-pointer font-semibold text-sky-900">{{ $faq['q'] }}</summary>
                <p class="mt-3 leading-relaxed text-slate-600">{{ $faq['a'] }}</p>
            </details>
        @endforeach
    </div>
</section>
