@props([
    'colour' => 'bg-red-600',
    'title',
    'icon' => 'fas fa-circle-exclamation',
    'stackIcons' => [],
    'class' => '',
    'id' => null,
    'timeout' => 5000,
    'show' => true,
    'dismissOnTimeout' => false,
])

<div
    @class(["$colour $class pointer-events-auto relative w-full overflow-hidden rounded-2xl p-4 text-white shadow-2xl ring-1 ring-black/10"])
    role="alert"
    x-data="{ showAlert: @js((bool) $show), timer: null }"
    x-init="@if($dismissOnTimeout) timer = window.setTimeout(() => showAlert = false, {{ (int) $timeout }}) @endif"
    x-show="showAlert"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="translate-y-3 opacity-0"
    x-transition:enter-end="translate-y-0 opacity-100"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="translate-y-0 opacity-100"
    x-transition:leave-end="translate-y-3 opacity-0"
    @if($id) id="{{ $id }}" @endif
    {{ $attributes }}
    style="display: none"
>
    <div class="flex items-start justify-between gap-4">
        <div class="flex min-w-0 items-start gap-3">
            @if (!empty($stackIcons))
                <span class="fa-stack mt-0.5 shrink-0 text-sm">
                    @foreach ($stackIcons as $stackIcon)
                        <i class="{{$stackIcon}} fa-stack-{{$loop->iteration}}x"></i>
                    @endforeach
                </span>
            @else
                <i class="{{ $icon }} mt-0.5 shrink-0 text-xl" aria-hidden="true"></i>
            @endif
            <div class="min-w-0">
                <p class="font-bold leading-5">{{ $title }}</p>
                <div class="mt-1 text-sm leading-6 text-white/90">{{ $slot }}</div>
            </div>
        </div>
        <button type="button" class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-white/80 transition hover:bg-white/15 hover:text-white focus:outline-none focus:ring-2 focus:ring-white/70"
            @click="if (timer) window.clearTimeout(timer); showAlert = false" aria-label="Dismiss notification">
            <i class="fas fa-times" aria-hidden="true"></i>
        </button>
    </div>
    @if($dismissOnTimeout)
        <span class="absolute inset-x-0 bottom-0 h-1 origin-left bg-white/35"
            style="animation: status-toast-timeout {{ (int) $timeout }}ms linear forwards" aria-hidden="true"></span>
    @endif
</div>
