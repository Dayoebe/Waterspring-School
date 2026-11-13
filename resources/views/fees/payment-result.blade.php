<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment status</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 px-4 py-12 text-slate-900">
    <main class="mx-auto max-w-lg rounded-2xl bg-white p-8 text-center shadow-lg">
        @if($successful)
            <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-green-100 text-3xl text-green-700">✓</div>
            <h1 class="text-2xl font-bold">Payment confirmed</h1>
            <p class="mt-3 text-slate-600">Your payment has been verified and recorded. A receipt notification has been queued.</p>
            <dl class="mt-6 rounded-xl bg-slate-50 p-4 text-left text-sm">
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Amount</dt><dd class="font-semibold">{{ $attempt->transaction->amount->formatTo(app()->getLocale()) }}</dd></div>
                <div class="mt-3 flex justify-between gap-4"><dt class="text-slate-500">Receipt</dt><dd class="font-semibold">{{ $attempt->transaction->receipt_number }}</dd></div>
            </dl>
            @auth
                <a href="{{ route('fee-payments.receipt', $attempt->transaction->id) }}" class="mt-6 inline-flex rounded-lg bg-indigo-700 px-5 py-3 font-semibold text-white hover:bg-indigo-800">View receipt</a>
            @else
                <a href="{{ route('login') }}" class="mt-6 inline-flex rounded-lg bg-indigo-700 px-5 py-3 font-semibold text-white hover:bg-indigo-800">Sign in to view receipt</a>
            @endauth
        @else
            <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-amber-100 text-3xl text-amber-700">!</div>
            <h1 class="text-2xl font-bold">Payment not confirmed</h1>
            <p class="mt-3 text-slate-600">{{ $message ?? 'Paystack has not confirmed a successful payment. No fee balance was changed.' }}</p>
            <a href="{{ route('login') }}" class="mt-6 inline-flex rounded-lg bg-slate-800 px-5 py-3 font-semibold text-white hover:bg-slate-900">Return to the portal</a>
        @endif
    </main>
</body>
</html>
