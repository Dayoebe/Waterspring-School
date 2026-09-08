@php
    // Livewire emits root-relative endpoints; XAMPP can host this app in a subdirectory.
    $livewireBasePath = request()->getBaseUrl();
    $livewireOptions = $livewireBasePath !== '' && !config('livewire.asset_url')
        ? ['url' => url('/livewire/livewire.js')]
        : [];
    $livewireScripts = \Livewire\Mechanisms\FrontendAssets\FrontendAssets::scripts($livewireOptions);
    if ($livewireBasePath !== '') {
        $livewireUpdateUri = app('livewire')->getUpdateUri();
        $livewireScripts = str_replace(
            'data-update-uri="' . e($livewireUpdateUri) . '"',
            'data-update-uri="' . e(url($livewireUpdateUri)) . '"',
            $livewireScripts,
        );
    }
@endphp
{!! $livewireScripts !!}
