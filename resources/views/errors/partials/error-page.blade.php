@php
    $suggestions = [
        '400' => ['Check the address or information you submitted.', 'Refresh once, then try the action again.'],
        '401' => ['Sign in with your school account.', 'Return to the page after signing in.'],
        '403' => ['Confirm that you are using the correct account.', 'Ask an administrator if you need access to this area.'],
        '404' => ['Check the page address for a typing mistake.', 'Use the dashboard or website links below to continue.'],
        '405' => ['Return to the previous page.', 'Use the buttons and forms provided on that page.'],
        '419' => ['Your session may have been inactive for a while.', 'Sign in again before resubmitting the form.'],
        '422' => ['Review the information entered in the form.', 'Correct highlighted fields and submit again.'],
        '429' => ['Wait a short moment before trying again.', 'Avoid repeatedly refreshing or submitting the same form.'],
        '500' => ['Try the page again in a moment.', 'If it continues, contact the school office with the page you were using.'],
        '503' => ['The service may be undergoing maintenance.', 'Please return after a short while.'],
    ];
    $tips = $suggestions[(string) $code] ?? ['Return to a safe page and try again.', 'Contact the school office if the problem continues.'];
    $canRetry = in_array((string) $code, ['400', '419', '429', '500', '503'], true);
    $requiresLogin = in_array((string) $code, ['401', '419'], true);
    $primaryUrl = $requiresLogin ? url('/login') : url('/dashboard');
    $primaryLabel = $requiresLogin ? 'Sign in again' : 'Go to dashboard';
@endphp

<style>
    .error-card { position: relative; width: min(900px, 100%); overflow: hidden; border: 1px solid rgba(186,215,230,.9); border-radius: 30px; background: rgba(255,255,255,.93); box-shadow: 0 30px 80px rgba(15,58,82,.14); }
    .error-accent { height: 8px; background: linear-gradient(90deg, #0284c7, #14b8a6 58%, #fbbf24); }
    .error-grid { display: grid; grid-template-columns: minmax(0,1.2fr) minmax(250px,.8fr); }
    .error-copy { padding: clamp(30px,6vw,64px); }
    .error-code { display: inline-flex; align-items: center; gap: 9px; padding: 7px 12px; border: 1px solid #bae6fd; border-radius: 999px; color: #0369a1; background: #f0f9ff; font-size: .72rem; font-weight: 850; letter-spacing: .16em; text-transform: uppercase; }
    .error-code::before { content: ''; width: 8px; height: 8px; border-radius: 50%; background: var(--amber); }
    .error-copy h1 { max-width: 620px; margin: 22px 0 0; color: var(--ink); font-size: clamp(2.2rem,6vw,4.4rem); line-height: 1.02; letter-spacing: -.055em; }
    .error-message { max-width: 610px; margin: 22px 0 0; color: var(--muted); font-size: 1.02rem; line-height: 1.8; }
    .actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 30px; }
    .button { display: inline-flex; align-items: center; justify-content: center; min-height: 46px; padding: 0 18px; border: 1px solid var(--line); border-radius: 13px; background: #fff; color: #334155; font: inherit; font-size: .86rem; font-weight: 800; text-decoration: none; cursor: pointer; }
    .button-primary { border-color: var(--sky); color: #fff; background: var(--sky); }
    .button:hover { transform: translateY(-1px); box-shadow: 0 8px 22px rgba(15,23,42,.1); }
    .error-aside { display: flex; flex-direction: column; justify-content: center; padding: 38px; color: #e0f2fe; background: linear-gradient(150deg,#083b5c,#075985 55%,#0f766e); }
    .error-aside svg { width: 54px; height: 54px; color: #fde68a; }
    .error-aside h2 { margin: 22px 0 0; color: #fff; font-size: 1.25rem; }
    .error-aside ol { margin: 18px 0 0; padding: 0; list-style: none; counter-reset: tips; }
    .error-aside li { position: relative; margin-top: 14px; padding-left: 36px; font-size: .88rem; line-height: 1.65; }
    .error-aside li::before { counter-increment: tips; content: counter(tips); position: absolute; left: 0; top: 0; display: grid; place-items: center; width: 25px; height: 25px; border: 1px solid rgba(255,255,255,.25); border-radius: 50%; color: #fff; background: rgba(255,255,255,.1); font-size: .7rem; font-weight: 800; }
    .quick-links { display: flex; flex-wrap: wrap; gap: 16px; margin-top: 26px; padding-top: 20px; border-top: 1px solid #e2e8f0; }
    .quick-links a { color: var(--sky-dark); font-size: .8rem; font-weight: 750; text-decoration: none; }
    @media (max-width: 760px) { .error-grid { grid-template-columns: 1fr; } .error-aside { padding: 30px; } .error-copy { padding: 32px 24px; } .actions .button { flex: 1 1 145px; } }
</style>

<section class="error-card" aria-labelledby="error-heading">
    <div class="error-accent"></div>
    <div class="error-grid">
        <div class="error-copy">
            <p class="error-code">Error {{ $code }}</p>
            <h1 id="error-heading">{{ $heading }}</h1>
            <p class="error-message">{{ $message }}</p>
            <div class="actions">
                <a class="button button-primary" href="{{ $primaryUrl }}">{{ $primaryLabel }}</a>
                <button class="button" type="button" onclick="history.length > 1 ? history.back() : window.location.assign('{{ url('/') }}')">Go back</button>
                @if($canRetry)<button class="button" type="button" onclick="window.location.reload()">Try again</button>@endif
            </div>
            <nav class="quick-links" aria-label="Helpful links"><a href="{{ url('/') }}">School website</a><a href="{{ url('/contact') }}">Contact school</a><a href="{{ url('/admission') }}">Admissions</a></nav>
        </div>
        <aside class="error-aside">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 9v4m0 4h.01M10.3 3.7 2.2 18a2 2 0 0 0 1.74 3h16.12a2 2 0 0 0 1.74-3L13.7 3.7a2 2 0 0 0-3.4 0Z" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <h2>What you can do</h2>
            <ol>@foreach($tips as $tip)<li>{{ $tip }}</li>@endforeach</ol>
        </aside>
    </div>
</section>
