@props([
    'label' => null,
    'inputId' => null,
])

<div x-data="{ visible: false }">
    @if ($label)
        <label @if ($inputId) for="{{ $inputId }}" @endif class="mb-2 block text-sm font-semibold text-gray-700">
            {{ $label }}
        </label>
    @endif

    <div class="relative">
        <input
            @if ($inputId) id="{{ $inputId }}" @endif
            type="password"
            :type="visible ? 'text' : 'password'"
            {{ $attributes->merge(['class' => 'w-full rounded-lg border-2 border-gray-300 p-3 pr-12 focus:ring-2']) }}
        >
        <button
            type="button"
            class="absolute inset-y-0 right-0 flex w-12 items-center justify-center text-gray-500 transition hover:text-gray-800 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-500"
            @click="visible = ! visible"
            :aria-label="visible ? 'Hide password' : 'Show password'"
            :title="visible ? 'Hide password' : 'Show password'"
        >
            <i class="fas" :class="visible ? 'fa-eye-slash' : 'fa-eye'" aria-hidden="true"></i>
        </button>
    </div>
</div>
