<header class="dashboard-topbar">
    <div class="dashboard-brand-group">
        <button type="button" class="dashboard-icon-button" @click="menuOpen = !menuOpen" :aria-expanded="menuOpen.toString()" aria-controls="dashboard-sidebar" aria-label="Toggle navigation">
            <i class="fas fa-bars" aria-hidden="true"></i>
        </button>
        <a href="{{ route('dashboard') }}" class="dashboard-brand">
            <img src="{{ auth()->user()->school?->logo_url ?? asset(config('app.logo')) }}" alt="Watersprings logo" width="40" height="40">
            <span><strong>Watersprings</strong><small>School management</small></span>
        </a>
    </div>
    <div class="dashboard-topbar-actions" x-data="{ profileOpen: false, darkMode: $persist(false).as('watersprings.darkMode') }"
        x-effect="document.body.classList.toggle('dark', darkMode)" @keydown.escape.window="profileOpen = false">
        <a href="{{ route('home') }}" class="dashboard-website-link"><i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i><span>School website</span></a>
        <button type="button" class="dashboard-icon-button dashboard-fullscreen" aria-label="Toggle fullscreen"
            @click="(document.fullscreenElement ? document.exitFullscreen() : document.documentElement.requestFullscreen()).catch(() => {})">
            <i class="fas fa-expand" aria-hidden="true"></i>
        </button>
        <button type="button" class="dashboard-icon-button" @click="darkMode = !darkMode" :aria-pressed="darkMode.toString()" aria-label="Toggle dark mode">
            <i :class="darkMode ? 'fas fa-sun' : 'far fa-moon'" aria-hidden="true"></i>
        </button>
        <div class="dashboard-profile">
            <button type="button" class="dashboard-profile-button" @click="profileOpen = !profileOpen" :aria-expanded="profileOpen.toString()" aria-controls="dashboard-profile-panel" aria-label="Open account menu">
                <img src="{{ auth()->user()->profile_photo_url }}" alt="" width="36" height="36">
                <span class="dashboard-profile-name">{{ auth()->user()->name }}</span>
                <i class="fas fa-chevron-down" aria-hidden="true"></i>
            </button>
            <div id="dashboard-profile-panel" class="dashboard-profile-panel" x-show="profileOpen" x-cloak x-transition @click.outside="profileOpen = false">
                <p class="dashboard-profile-label">Signed in as</p>
                <strong>{{ auth()->user()->name }}</strong>
                @if (auth()->user()->school?->academicYear)
                    <p class="dashboard-profile-label">{{ auth()->user()->school->academicYear->name }}</p>
                @endif
                <a href="{{ route('profile.edit') }}"><i class="far fa-user" aria-hidden="true"></i>My profile</a>
                <a href="{{ route('password.change') }}"><i class="fas fa-lock" aria-hidden="true"></i>Change password</a>
                <form action="{{ route('logout') }}" method="POST">@csrf
                    <button type="submit"><i class="fas fa-arrow-right-from-bracket" aria-hidden="true"></i>Sign out</button>
                </form>
            </div>
        </div>
    </div>
</header>
