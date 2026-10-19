<section class="overflow-hidden rounded-3xl border border-emerald-200 bg-white shadow-sm" x-data="{ emojiOpen: false, appendEmoji(emoji) { this.$wire.set('question', (this.$wire.question || '') + emoji); this.emojiOpen = false; this.$nextTick(() => this.$refs.composer.focus()); } }" x-init="$nextTick(() => { const box = $refs.messages; if (box) box.scrollTop = box.scrollHeight })">
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
    @php($chatAction = auth()->user()->hasRole('student') && !auth()->user()->hasAnyRole(['teacher','principal','admin','super-admin','super_admin']) ? 'askTutor' : 'askFamily')
    <footer class="border-t border-slate-200 bg-slate-100 px-3 py-3 sm:px-4">
        <form wire:submit="{{ $chatAction }}" class="relative flex items-end gap-2">
            <div class="relative shrink-0">
                <button type="button" @click="emojiOpen = !emojiOpen" class="flex size-12 items-center justify-center rounded-full text-xl text-slate-500 hover:bg-slate-200 hover:text-emerald-700" aria-label="Choose an emoji" :aria-expanded="emojiOpen"><i class="far fa-face-smile"></i></button>
                <div x-show="emojiOpen" x-cloak @click.outside="emojiOpen = false" class="absolute bottom-14 left-0 z-20 grid w-64 grid-cols-6 gap-1 rounded-2xl border border-slate-200 bg-white p-3 text-xl shadow-xl">
                    @foreach(['😀','😊','😂','🥰','😍','🤔','👍','👏','🙏','💡','📚','✏️','✅','⭐','🎉','💪','🧠','❤️'] as $emoji)<button type="button" @click="appendEmoji('{{ $emoji }}')" class="flex size-9 items-center justify-center rounded-lg hover:bg-slate-100" aria-label="Add {{ $emoji }} emoji">{{ $emoji }}</button>@endforeach
                </div>
            </div>
            <label class="min-w-0 flex-1"><span class="sr-only">Type a message</span><textarea x-ref="composer" wire:model="question" x-on:keydown.shift.enter.prevent="$wire.{{ $chatAction }}()" rows="1" class="max-h-32 min-h-12 w-full resize-none rounded-2xl border-0 bg-white px-4 py-3 text-sm leading-6 shadow-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-emerald-600" placeholder="Type a message…"></textarea></label>
            <button type="submit" wire:loading.attr="disabled" wire:target="{{ $chatAction }}" class="flex size-12 shrink-0 items-center justify-center rounded-full bg-emerald-700 text-white shadow-sm hover:bg-emerald-800 disabled:opacity-60" aria-label="Send message"><i wire:loading.remove wire:target="{{ $chatAction }}" class="fas fa-paper-plane"></i><i wire:loading wire:target="{{ $chatAction }}" class="fas fa-spinner fa-spin"></i></button>
        </form>
        @error('question')<p class="mt-2 px-2 text-xs font-semibold text-red-700">{{ $message }}</p>@enderror
        <p class="mt-2 flex items-center justify-center gap-1.5 text-[0.68rem] text-slate-500"><i class="fas fa-lock text-emerald-700"></i> Only you can open this conversation. Enter starts a new line; Shift + Enter sends.</p>
    </footer>
</section>
