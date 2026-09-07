<div class="dashboard-navigation" :class="menuOpen ? 'is-expanded' : 'is-collapsed'">
    <button type="button" class="dashboard-nav-backdrop" x-show="menuOpen && !desktop" x-cloak @click="menuOpen = false" aria-label="Close navigation"></button>
    <nav id="dashboard-sidebar" class="dashboard-sidebar" aria-label="Main navigation" :inert="!menuOpen && !desktop">
            @php
                $sections = [];
                $currentHeader = null;
                $currentItems = [];

                foreach ($menu ?? [] as $item) {
                    if (isset($item['header'])) {
                        if ($currentItems !== []) {
                            $sections[] = [
                                'header' => $currentHeader,
                                'items' => $currentItems,
                            ];
                            $currentItems = [];
                        }

                        if (!$this->isVisible($item)) {
                            $currentHeader = null;
                            continue;
                        }

                        $currentHeader = $item['header'];
                        continue;
                    }

                    if (!$this->isVisible($item)) {
                        continue;
                    }

                    if (isset($item['submenu']) && is_array($item['submenu'])) {
                        $item['submenu'] = $this->visibleSubmenu($item['submenu']);
                        if ($item['submenu'] === []) {
                            continue;
                        }
                    }

                    $currentItems[] = $item;
                }

                if ($currentItems !== []) {
                    $sections[] = [
                        'header' => $currentHeader,
                        'items' => $currentItems,
                    ];
                }
                $menuQueryKeys = collect($sections)->flatMap(fn ($section) => $section['items'])
                    ->flatMap(fn ($item) => isset($item['submenu']) ? $item['submenu'] : [$item])
                    ->flatMap(fn ($item) => array_keys(array_merge($item['params'] ?? [], $item['query'] ?? [])))->unique()->all();
                $activeMenuQuery = request()->only($menuQueryKeys);
            @endphp
        @foreach ($sections as $section)
            <section class="dashboard-nav-section">
                @if (!empty($section['header']))
                    <h2 class="dashboard-nav-label" x-show="menuOpen">{{ $section['header'] }}</h2>
                @endif
                @foreach ($section['items'] as $menuItem)
                    @if (!isset($menuItem['submenu']))
                        @php
                            $isComingSoon = !empty($menuItem['coming_soon']) || empty($menuItem['route']);
                            $isActive = !$isComingSoon && Route::currentRouteName() === $menuItem['route'] && array_merge($menuItem['params'] ?? [], $menuItem['query'] ?? []) == $activeMenuQuery;
                            $routeUrl = !$isComingSoon ? route($menuItem['route'], $menuItem['params'] ?? []) : null;
                            if ($routeUrl && !empty($menuItem['query'])) $routeUrl .= '?' . http_build_query($menuItem['query']);
                        @endphp
                        @if ($isComingSoon)
                            <div class="dashboard-nav-link is-disabled" title="{{ $menuItem['text'] }} — coming soon">
                                <i class="{{ $menuItem['icon'] ?? 'far fa-circle' }}" aria-hidden="true"></i>
                                <span x-show="menuOpen">{{ $menuItem['text'] }}</span><small x-show="menuOpen">Soon</small>
                            </div>
                        @else
                            <a class="dashboard-nav-link {{ $isActive ? 'is-active' : '' }}" href="{{ $routeUrl }}" title="{{ $menuItem['text'] }}" @if($isActive) aria-current="page" @endif wire:navigate>
                                <i class="{{ $menuItem['icon'] ?? 'far fa-circle' }}" aria-hidden="true"></i><span x-show="menuOpen">{{ $menuItem['text'] }}</span>
                            </a>
                        @endif
                    @else
                        @php
                            $isActive = in_array(Route::currentRouteName(), array_column($menuItem['submenu'], 'route'), true);
                        @endphp
                        <div x-data="{ submenu: {{ $isActive ? 'true' : 'false' }} }">
                            <button type="button" class="dashboard-nav-link {{ $isActive ? 'is-parent-active' : '' }}" @click="if (!menuOpen) { menuOpen = true; submenu = true; } else { submenu = !submenu; }" :aria-expanded="(submenu && menuOpen).toString()" title="{{ $menuItem['text'] }}">
                                <i class="{{ $menuItem['icon'] ?? 'far fa-circle' }}" aria-hidden="true"></i><span x-show="menuOpen">{{ $menuItem['text'] }}</span>
                                <i x-show="menuOpen" class="fas fa-chevron-down dashboard-nav-chevron" :class="submenu ? 'is-rotated' : ''" aria-hidden="true"></i>
                            </button>
                            <div class="dashboard-subnav" x-show="submenu && menuOpen" x-cloak>
                                @foreach ($menuItem['submenu'] as $submenuItem)
                                    @php
                                        $comingSoon = !empty($submenuItem['coming_soon']) || empty($submenuItem['route']);
                                        $active = !$comingSoon && Route::currentRouteName() === $submenuItem['route'] && array_merge($submenuItem['params'] ?? [], $submenuItem['query'] ?? []) == $activeMenuQuery;
                                        $url = !$comingSoon ? route($submenuItem['route'], $submenuItem['params'] ?? []) : null;
                                        if ($url && !empty($submenuItem['query'])) $url .= '?' . http_build_query($submenuItem['query']);
                                    @endphp
                                    @if ($comingSoon)
                                        <span class="dashboard-subnav-link is-disabled">{{ $submenuItem['text'] }} <small>Soon</small></span>
                                    @else
                                        <a class="dashboard-subnav-link {{ $active ? 'is-active' : '' }}" href="{{ $url }}" @if($active) aria-current="page" @endif wire:navigate>{{ $submenuItem['text'] }}</a>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
            </section>
        @endforeach
    </nav>
</div>
