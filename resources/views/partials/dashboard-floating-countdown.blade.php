@php
    $countdownUser = auth()->user();
    $showDashboardCountdown = $countdownUser && $countdownUser->hasAnyRole(['super-admin', 'super_admin']);
@endphp

@if ($showDashboardCountdown)
@php
    $countdownNow = now();
    $countdownTarget = $countdownNow->copy()->setDate(2026, 12, 10)->setTime(23, 59, 59);

@endphp

<div class="dashboard-countdown">
    <div class="dashboard-countdown-card pointer-events-auto w-[min(15rem,calc(100vw-1rem))] rounded-xl border border-slate-200 bg-white p-3 text-slate-900 shadow-lg sm:w-64"
        x-data="{
            targetIso: @js($countdownTarget->toIso8601String()),
            storageKey: 'dashboardCountdownDismissed:' + @js($countdownTarget->toIso8601String()),
            open: true,
            interval: null,
            isComplete: false,
            totals: { months: 0, weeks: 0, days: 0, hours: 0, minutes: 0, seconds: 0 },
            init() {
                if (window.localStorage.getItem(this.storageKey) === '1') {
                    this.open = false;
                }

                this.tick();
                this.interval = window.setInterval(() => this.tick(), 1000);
            },
            close() {
                this.open = false;
                window.localStorage.setItem(this.storageKey, '1');
            },
            tick() {
                const now = new Date();
                const target = new Date(this.targetIso);
                const diffMs = target.getTime() - now.getTime();
        
                if (diffMs <= 0) {
                    this.isComplete = true;
                    this.totals = { months: 0, weeks: 0, days: 0, hours: 0, minutes: 0, seconds: 0 };
        
                    if (this.interval) {
                        window.clearInterval(this.interval);
                    }
        
                    return;
                }
        
                const months = this.monthDiff(now, target);
                const monthAnchor = new Date(now.getTime());
                monthAnchor.setMonth(monthAnchor.getMonth() + months);

                let remainingMs = Math.max(target.getTime() - monthAnchor.getTime(), 0);
                const weeks = Math.floor(remainingMs / (7 * 24 * 60 * 60 * 1000));
                remainingMs -= weeks * 7 * 24 * 60 * 60 * 1000;

                const days = Math.floor(remainingMs / (24 * 60 * 60 * 1000));
                remainingMs -= days * 24 * 60 * 60 * 1000;
        
                const hours = Math.floor(remainingMs / (60 * 60 * 1000));
                remainingMs -= hours * 60 * 60 * 1000;
        
                const minutes = Math.floor(remainingMs / (60 * 1000));
                remainingMs -= minutes * 60 * 1000;
        
                const seconds = Math.floor(remainingMs / 1000);
        
                this.isComplete = false;
                this.totals = {
                    months,
                    weeks,
                    days,
                    hours,
                    minutes,
                    seconds,
                };
            },
            monthDiff(fromDate, toDate) {
                let months = ((toDate.getFullYear() - fromDate.getFullYear()) * 12) + (toDate.getMonth() - fromDate.getMonth());
                const anchor = new Date(fromDate.getTime());

                anchor.setMonth(anchor.getMonth() + months);

                if (anchor > toDate) {
                    months -= 1;
                }

                return Math.max(months, 0);
            },
            format(value) {
                return new Intl.NumberFormat().format(value);
            },
            pad(value) {
                return String(value).padStart(2, '0');
            },
        }" x-show="open" x-transition.opacity.scale.origin.bottom.right>
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-[11px] font-semibold uppercase text-slate-500">Countdown</p>
                <h3 class="mt-0.5 text-sm font-bold leading-tight text-slate-950">Website shutting down</h3>
                <p class="mt-1 text-[11px] text-slate-500">{{ $countdownTarget->format('M j, Y') }}</p>
            </div>

            <button type="button"
                class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-slate-500 transition hover:bg-slate-100 hover:text-slate-900"
                @click="close()" aria-label="Hide countdown">
                <i class="fas fa-times text-xs"></i>
            </button>
        </div>

        <div class="mt-3 grid grid-cols-3 gap-1.5" x-show="!isComplete">
            <div class="rounded-lg bg-slate-100 px-2 py-2 text-center">
                <p class="text-base font-bold leading-none text-slate-950" x-text="format(totals.months)"></p>
                <p class="mt-1 text-[10px] font-semibold uppercase text-slate-500">Months</p>
            </div>

            <div class="rounded-lg bg-slate-100 px-2 py-2 text-center">
                <p class="text-base font-bold leading-none text-slate-950" x-text="format(totals.weeks)"></p>
                <p class="mt-1 text-[10px] font-semibold uppercase text-slate-500">Weeks</p>
            </div>

            <div class="rounded-lg bg-slate-100 px-2 py-2 text-center">
                <p class="text-base font-bold leading-none text-slate-950" x-text="format(totals.days)"></p>
                <p class="mt-1 text-[10px] font-semibold uppercase text-slate-500">Days</p>
            </div>
        </div>

        <p class="mt-2 text-center text-xs font-semibold text-slate-500"
            x-show="!isComplete"
            x-text="`${pad(totals.hours)}h ${pad(totals.minutes)}m ${pad(totals.seconds)}s`">
        </p>

        <p class="mt-3 rounded-lg bg-emerald-100 px-3 py-2 text-xs font-semibold text-emerald-800"
            x-show="isComplete">
            Reached December 10, 2026.
        </p>
    </div>
</div>
@endif