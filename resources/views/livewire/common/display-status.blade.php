<div>
    <div class="pointer-events-none fixed inset-x-4 bottom-4 z-[100] flex flex-col items-end gap-3 sm:left-auto sm:w-full sm:max-w-sm"
        id="status-display" aria-live="polite" aria-atomic="true">
        @if (session('danger'))
            <x-alert colour="bg-red-600" title="Action required" icon="fas fa-circle-exclamation" :dismiss-on-timeout="true">
                {{ session('danger') }}
            </x-alert>
        @endif
        @if (session('error'))
            <x-alert colour="bg-red-600" title="Something went wrong" icon="fas fa-circle-exclamation" :dismiss-on-timeout="true">
                {{ session('error') }}
            </x-alert>
        @endif
        @if (session('success'))
            <x-alert colour="bg-emerald-600" title="Success" icon="fas fa-circle-check" :dismiss-on-timeout="true">
                {{ session('success') }}
            </x-alert>
        @endif
        @if (session('info'))
            <x-alert colour="bg-amber-500" title="Information" icon="fas fa-circle-info" :dismiss-on-timeout="true">
                {{ session('info') }}
            </x-alert>
        @endif
        @if (session('status'))
            <x-alert colour="bg-emerald-600" title="Success" icon="fas fa-circle-check" :dismiss-on-timeout="true">
                {{ session('status') }}
            </x-alert>
        @endif
        <x-alert colour="bg-slate-900" title="No internet" :stack-icons="['fas fa-signal', 'fas fa-ban']" :show="false">
            <div  @offline.window="showAlert = true" @online.window="showAlert = false">
                Your device is offline. Recently opened pages can still load, but server actions will wait until the internet returns.
            </div>
        </x-alert>
    </div>
</div>
