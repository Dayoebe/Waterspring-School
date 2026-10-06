<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="theme-color" content="#075985">
    <title>@yield('title', 'Something went wrong') | Watersprings</title>
    <link rel="icon" href="{{ asset('images/watersprings/logo.png') }}" type="image/png">
    <style>
        :root { color-scheme: light; --ink:#0f172a; --muted:#52627a; --line:#dbe7f0; --sky:#0875a5; --sky-dark:#075985; --paper:#f5f9fc; --amber:#fbbf24; }
        * { box-sizing: border-box; }
        html { min-height: 100%; background: var(--paper); }
        body { min-height: 100vh; margin: 0; color: var(--ink); background: radial-gradient(circle at 12% 8%, #cffafe 0, transparent 30rem), radial-gradient(circle at 88% 88%, #fef3c7 0, transparent 28rem), var(--paper); font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        a { color: inherit; }
        .shell { min-height: 100vh; display: grid; grid-template-rows: auto 1fr auto; }
        .topbar, .footer { width: min(1120px, calc(100% - 32px)); margin: 0 auto; }
        .topbar { display: flex; align-items: center; justify-content: space-between; gap: 20px; padding: 22px 0; }
        .brand { display: inline-flex; align-items: center; gap: 12px; color: var(--ink); text-decoration: none; min-width: 0; }
        .brand img { width: 46px; height: 46px; padding: 4px; object-fit: contain; border: 1px solid var(--line); border-radius: 50%; background: #fff; box-shadow: 0 8px 24px rgba(15,23,42,.08); }
        .brand strong { display: block; font-size: .95rem; line-height: 1.25; }
        .brand small { display: block; margin-top: 2px; color: var(--muted); font-size: .72rem; }
        .help-link { padding: 10px 14px; border: 1px solid var(--line); border-radius: 12px; background: rgba(255,255,255,.75); color: var(--sky-dark); font-size: .82rem; font-weight: 750; text-decoration: none; }
        .main { display: grid; place-items: center; width: 100%; padding: 32px 16px 64px; }
        .footer { display: flex; justify-content: space-between; gap: 16px; padding: 20px 0 28px; border-top: 1px solid rgba(148,163,184,.35); color: var(--muted); font-size: .75rem; }
        @media (max-width: 640px) { .brand strong { max-width: 220px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; } .brand small, .help-link { display: none; } .footer { flex-direction: column; } }
    </style>
</head>
<body>
    <div class="shell">
        <header class="topbar">
            <a class="brand" href="{{ url('/') }}">
                <img src="{{ asset('images/watersprings/logo.png') }}" alt="Watersprings logo" width="46" height="46">
                <span><strong>Watersprings International School &amp; College</strong><small>Akure, Ondo State</small></span>
            </a>
            <a class="help-link" href="{{ url('/contact') }}">Need help?</a>
        </header>
        <main class="main">@yield('content')</main>
        <footer class="footer"><span>&copy; {{ date('Y') }} Watersprings International School</span><span>Learn • Play • Grow • Together</span></footer>
    </div>
</body>
</html>
