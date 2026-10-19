<section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
    <div class="flex items-center justify-between gap-3"><div><p class="text-xs font-bold uppercase tracking-wider text-cyan-700">Saved automatically</p><h2 class="mt-1 text-xl font-black text-slate-900">{{ $activeConversation?->title ?? 'Your conversation' }}</h2></div>@if($activeConversation)<span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-800"><i class="fas fa-check mr-1"></i> Saved</span>@endif</div>
    @if($activeConversation && $activeConversation->messages->isNotEmpty())
        <div class="mt-5 max-h-[38rem] space-y-4 overflow-y-auto pr-1" aria-live="polite">
            @foreach($activeConversation->messages as $message)
                <article class="{{ $message->role === 'user' ? 'ml-auto bg-sky-700 text-white' : 'mr-auto border border-slate-200 bg-slate-50 text-slate-700' }} max-w-[92%] rounded-2xl p-4 text-sm leading-6 shadow-sm">
                    <p class="mb-1 text-xs font-black uppercase tracking-wider {{ $message->role === 'user' ? 'text-sky-100' : 'text-cyan-700' }}">{{ $message->role === 'user' ? 'You' : 'ClassPilot' }}</p>
                    <div class="whitespace-pre-line">{{ $message->content }}</div>
                </article>
            @endforeach
        </div>
        <p class="mt-4 text-xs text-slate-500">Continue by entering another question. ClassPilot receives the recent conversation context.</p>
    @else
        <div class="flex min-h-80 flex-col items-center justify-center text-center"><div class="flex size-14 items-center justify-center rounded-2xl bg-cyan-100 text-xl text-cyan-800"><i class="fas fa-comments"></i></div><h3 class="mt-4 font-black text-slate-900">Start a saved conversation</h3><p class="mt-2 max-w-sm text-sm leading-6 text-slate-600">Your question and the response will appear here and remain available under Saved sessions.</p></div>
    @endif
</section>
