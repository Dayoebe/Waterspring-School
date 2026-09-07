@props(['paths' => []])
@if (count($paths))
<nav aria-label="Breadcrumb" class="dashboard-breadcrumbs">
    <ol>
        @foreach ($paths as $path)
            <li>
                @if (!$loop->first)<i class="fas fa-chevron-right" aria-hidden="true"></i>@endif
                @if (!empty($path['active']))
                    <span aria-current="page">{{ __($path['text']) }}</span>
                @else
                    <a href="{{ $path['href'] }}">{{ __($path['text']) }}</a>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
@endif
