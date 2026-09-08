@php
    $navigationItems = collect($sections)->flatMap(fn ($section) => $section['items']);
    $activeGroup = $navigationItems->first(fn ($item) => !empty($item['submenu']) && $item['active'])['id'] ?? null;
    $navigationSearch = $navigationItems
        ->flatMap(fn ($item) => $item['submenu'] ?? [$item])
        ->pluck('search')->values()->all();
@endphp
<div
    class="dashboard-navigation"
    :class="menuOpen ? 'is-expanded' : 'is-collapsed'"
    x-data="{
        menuQuery: '',
        openGroup: @js($activeGroup),
        searchEntries: @js($navigationSearch),
        get searching() { return this.menuQuery.trim().length > 0; },
        matches(value) {
            const haystack = String(value || '').toLocaleLowerCase();
            return this.menuQuery.toLocaleLowerCase().trim().split(/\s+/).filter(Boolean).every(token => haystack.includes(token));
        },
        matchesAny(values) { return values.some(value => this.matches(value)); },
        get hasMatches() { return this.matchesAny(this.searchEntries); },
        toggleGroup(id) {
            if (!this.menuOpen) {
                this.menuOpen = true;
                this.openGroup = id;
                return;
            }
            this.openGroup = this.openGroup === id ? null : id;
        }
    }"
    x-init="$watch('menuOpen', value => { if (!value) menuQuery = ''; })"
>
    <button type="button" class="dashboard-nav-backdrop" x-show="menuOpen && !desktop" x-cloak @click="menuOpen = false" aria-label="Close navigation"></button>
    <nav id="dashboard-sidebar" class="dashboard-sidebar" aria-label="Main navigation" :inert="!menuOpen && !desktop">
        <div class="dashboard-nav-search-area">
            <button type="button" class="dashboard-nav-link dashboard-nav-search-toggle" x-show="!menuOpen" x-cloak @click="menuOpen = true; $nextTick(() => $refs.menuSearch.focus())" aria-label="Search menu" title="Search menu">
                <i class="fas fa-search" aria-hidden="true"></i>
            </button>
            <div x-show="menuOpen">
                <label for="dashboard-menu-search" class="dashboard-nav-search-label">Find a page</label>
                <div class="dashboard-nav-search">
                    <i class="fas fa-search" aria-hidden="true"></i>
                    <input
                        id="dashboard-menu-search"
                        x-ref="menuSearch"
                        x-model="menuQuery"
                        type="search"
                        placeholder="Search menu..."
                        autocomplete="off"
                        spellcheck="false"
                        aria-controls="dashboard-menu-items"
                        @keydown.escape="if (menuQuery) { $event.stopPropagation(); menuQuery = ''; }"
                    >
                    <button type="button" x-show="menuQuery.length" x-cloak @click="menuQuery = ''; $refs.menuSearch.focus()" aria-label="Clear menu search" title="Clear search">
                        <i class="fas fa-times" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
        </div>

        <div id="dashboard-menu-items">
            @foreach ($sections as $section)
                @php
                    $sectionSearch = collect($section['items'])->flatMap(fn ($item) => $item['submenu'] ?? [$item])->pluck('search')->values()->all();
                @endphp
                <section class="dashboard-nav-section" x-show="matchesAny(@js($sectionSearch))">
                    @if (!empty($section['header']))
                        <h2 class="dashboard-nav-label" x-show="menuOpen">{{ $section['header'] }}</h2>
                    @endif
                    @foreach ($section['items'] as $menuItem)
                        @if (empty($menuItem['submenu']))
                            @if (!empty($menuItem['route_url']))
                                <a
                                    class="dashboard-nav-link {{ $menuItem['active'] ? 'is-active' : '' }}"
                                    href="{{ $menuItem['route_url'] }}"
                                    title="{{ $menuItem['text'] }}"
                                    x-show="matches(@js($menuItem['search']))"
                                    @if($menuItem['active']) aria-current="page" @endif
                                    wire:navigate
                                >
                                    <i class="{{ $menuItem['icon'] ?? 'far fa-circle' }}" aria-hidden="true"></i>
                                    <span x-show="menuOpen">{{ $menuItem['text'] }}</span>
                                </a>
                            @else
                                <div class="dashboard-nav-link is-disabled" title="{{ $menuItem['text'] }} — coming soon" x-show="matches(@js($menuItem['search']))">
                                    <i class="{{ $menuItem['icon'] ?? 'far fa-circle' }}" aria-hidden="true"></i>
                                    <span x-show="menuOpen">{{ $menuItem['text'] }}</span>
                                    <small x-show="menuOpen">Soon</small>
                                </div>
                            @endif
                        @else
                            @php
                                $groupId = 'dashboard-nav-' . $menuItem['id'];
                                $groupSearch = array_column($menuItem['submenu'], 'search');
                                $submenuSections = [];
                                foreach ($menuItem['submenu'] as $submenuItem) {
                                    $label = $submenuItem['section'] ?? '';
                                    $lastIndex = array_key_last($submenuSections);
                                    if ($lastIndex === null || $submenuSections[$lastIndex]['label'] !== $label) {
                                        $submenuSections[] = ['label' => $label, 'items' => []];
                                    }
                                    $submenuSections[array_key_last($submenuSections)]['items'][] = $submenuItem;
                                }
                            @endphp
                            <div class="dashboard-nav-group" x-show="matchesAny(@js($groupSearch))">
                                <button
                                    id="{{ $groupId }}-button"
                                    type="button"
                                    class="dashboard-nav-link {{ $menuItem['active'] ? 'is-parent-active' : '' }}"
                                    @click="toggleGroup(@js($menuItem['id']))"
                                    :aria-expanded="(menuOpen && (searching || openGroup === @js($menuItem['id']))).toString()"
                                    aria-controls="{{ $groupId }}"
                                    title="{{ $menuItem['text'] }}"
                                >
                                    <i class="{{ $menuItem['icon'] ?? 'far fa-circle' }}" aria-hidden="true"></i>
                                    <span x-show="menuOpen">{{ $menuItem['text'] }}</span>
                                    <i x-show="menuOpen" class="fas fa-chevron-down dashboard-nav-chevron" :class="(searching || openGroup === @js($menuItem['id'])) ? 'is-rotated' : ''" aria-hidden="true"></i>
                                </button>
                                <div id="{{ $groupId }}" class="dashboard-subnav" x-show="menuOpen && (searching || openGroup === @js($menuItem['id']))" aria-labelledby="{{ $groupId }}-button" x-cloak>
                                    @foreach ($submenuSections as $submenuSection)
                                        <div class="dashboard-subnav-section" x-show="matchesAny(@js(array_column($submenuSection['items'], 'search')))">
                                            @if ($submenuSection['label'] !== '')
                                                <h3 class="dashboard-subnav-label">{{ $submenuSection['label'] }}</h3>
                                            @endif
                                            @foreach ($submenuSection['items'] as $submenuItem)
                                                @if (!empty($submenuItem['route_url']))
                                                    <a class="dashboard-subnav-link {{ $submenuItem['active'] ? 'is-active' : '' }}" href="{{ $submenuItem['route_url'] }}" x-show="matches(@js($submenuItem['search']))" @if($submenuItem['active']) aria-current="page" @endif wire:navigate>{{ $submenuItem['text'] }}</a>
                                                @else
                                                    <span class="dashboard-subnav-link is-disabled" x-show="matches(@js($submenuItem['search']))">{{ $submenuItem['text'] }} <small>Soon</small></span>
                                                @endif
                                            @endforeach
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endforeach
                </section>
            @endforeach
        </div>

        <div class="dashboard-nav-empty" x-show="menuOpen && searching && !hasMatches" x-cloak role="status" aria-live="polite">
            <i class="fas fa-search" aria-hidden="true"></i>
            <strong>No matching pages</strong>
            <p>Try a page name, such as students, fees, or results.</p>
            <button type="button" @click="menuQuery = ''; $refs.menuSearch.focus()">Clear search</button>
        </div>
    </nav>
</div>
