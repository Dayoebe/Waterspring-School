<section class="overflow-hidden rounded-3xl border border-emerald-200 bg-white shadow-sm" x-data x-init="$nextTick(() => { const box = $refs.messages; if (box) box.scrollTop = box.scrollHeight })">
    <header class="flex items-center justify-between gap-3 bg-emerald-800 px-4 py-3 text-white sm:px-5">
        <div class="flex min-w-0 items-center gap-3"><div class="flex size-11 shrink-0 items-center justify-center rounded-full bg-white text-lg text-emerald-800 shadow-sm"><i class="fas fa-wand-magic-sparkles"></i></div><div class="min-w-0"><h2 class="truncate font-bold">{{ $activeConversation?->title ?? 'ClassPilot' }}</h2><p class="text-xs text-emerald-100">{{ $activeConversation ? 'Saved conversation · available anytime' : 'Your school learning assistant' }}</p></div></div>
        @if($activeConversation)<span class="shrink-0 rounded-full bg-emerald-700 px-2.5 py-1 text-xs font-semibold"><i class="fas fa-cloud mr-1"></i> Saved</span>@endif
    </header>
    <div x-ref="messages" class="max-h-[42rem] min-h-[28rem] space-y-2 overflow-y-auto px-3 py-5 sm:px-5" aria-live="polite" style="background-color:#efeae2;background-image:radial-gradient(rgba(16,94,75,.06) 1px,transparent 1px);background-size:18px 18px;">
        @if($activeConversation && $activeConversation->messages->isNotEmpty())
            <div class="mx-auto mb-4 w-fit rounded-lg bg-white/90 px-3 py-1.5 text-center text-xs font-semibold text-slate-500 shadow-sm">Messages are saved automatically</div>
            @foreach($activeConversation->messages as $message)
                <div class="flex {{ $message->role === 'user' ? 'justify-end' : 'justify-start' }}">
                    <article class="relative max-w-[88%] rounded-xl px-3.5 pb-2 pt-2.5 text-[0.92rem] leading-6 shadow-sm sm:max-w-[78%] {{ $message->role === 'user' ? 'rounded-tr-sm bg-[#d9fdd3] text-slate-900' : 'rounded-tl-sm bg-white text-slate-800' }}">
                        @if($message->role !== 'user')<p class="mb-1 text-xs font-bold text-emerald-700">ClassPilot</p>@endif
                        <div class="break-words [&_a]:font-semibold [&_a]:text-emerald-800 [&_a]:underline [&_blockquote]:border-l-2 [&_blockquote]:border-emerald-400 [&_blockquote]:pl-3 [&_em]:italic [&_li]:ml-5 [&_li]:list-disc [&_ol_li]:list-decimal [&_p+p]:mt-2 [&_strong]:font-bold">{!! Str::markdown($message->content, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</div>
                        <div class="mt-1 flex items-center justify-end gap-1 text-[0.65rem] text-slate-500"><time datetime="{{ $message->created_at?->toIso8601String() }}">{{ $message->created_at?->format('g:i A') }}</time>@if($message->role === 'user')<i class="fas fa-check-double text-sky-500" title="Saved"></i>@endif</div>
                    </article>
                </div>
            @endforeach
            <div wire:loading.flex wire:target="askTutor,askFamily" class="justify-start"><div class="rounded-xl rounded-tl-sm bg-white px-4 py-3 text-emerald-700 shadow-sm"><span class="inline-flex gap-1"><span class="size-1.5 animate-bounce rounded-full bg-emerald-500"></span><span class="size-1.5 animate-bounce rounded-full bg-emerald-500 [animation-delay:120ms]"></span><span class="size-1.5 animate-bounce rounded-full bg-emerald-500 [animation-delay:240ms]"></span></span></div></div>
        @else
            <div class="flex min-h-[25rem] flex-col items-center justify-center text-center"><div class="flex size-16 items-center justify-center rounded-full bg-white text-2xl text-emerald-700 shadow-sm"><i class="fas fa-comments"></i></div><h3 class="mt-4 font-bold text-slate-900">Start a saved conversation</h3><p class="mt-2 max-w-sm text-sm leading-6 text-slate-600">Send a question from the form. Your chat will stay here and can be reopened from Saved sessions.</p></div>
        @endif
    </div>
    <footer class="flex items-center gap-3 border-t border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-500"><i class="fas fa-lock text-emerald-700"></i><span>Only you can open this ClassPilot conversation.</span></footer>
</section>
