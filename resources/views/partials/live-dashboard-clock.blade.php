<span
    x-data="{
        now: new Date(),
        timer: null,
        init() {
            this.timer = window.setInterval(() => this.now = new Date(), 1000);
        },
        destroy() {
            window.clearInterval(this.timer);
        },
        formattedTime() {
            return new Intl.DateTimeFormat('en-NG', {
                weekday: 'short',
                month: 'short',
                day: 'numeric',
                year: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                second: '2-digit',
                hour12: true,
                timeZone: 'Africa/Lagos'
            }).format(this.now).replace(' at ', ' · ');
        }
    }"
    x-text="formattedTime()"
>{{ $fallback ?? now('Africa/Lagos')->format('D, M j, Y · g:i:s A') }}</span>
