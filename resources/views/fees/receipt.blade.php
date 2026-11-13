<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $payment->isReversal() ? 'Reversal' : 'Payment receipt' }} {{ $payment->receipt_number }}</title>
    <style>
        body { margin: 0; background: #f1f5f9; color: #0f172a; font-family: Arial, sans-serif; }
        .receipt { width: min(760px, calc(100% - 32px)); margin: 32px auto; background: white; border: 1px solid #cbd5e1; border-radius: 16px; overflow: hidden; }
        .header { padding: 28px; color: white; background: #1e3a8a; }
        .content { padding: 28px; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
        .label { margin: 0 0 4px; color: #64748b; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; }
        .value { margin: 0; font-weight: 700; overflow-wrap: anywhere; }
        .amount { margin: 24px 0; padding: 20px; border-radius: 12px; background: #eff6ff; text-align: center; font-size: 30px; font-weight: 800; color: #1d4ed8; }
        .audit { margin-top: 24px; padding-top: 20px; border-top: 1px solid #e2e8f0; color: #475569; font-size: 13px; }
        .actions { width: min(760px, calc(100% - 32px)); margin: 0 auto 32px; text-align: right; }
        button { border: 0; border-radius: 8px; padding: 10px 16px; background: #1e3a8a; color: white; font-weight: 700; cursor: pointer; }
        @media (max-width: 560px) { .grid { grid-template-columns: 1fr; } }
        @media print { body { background: white; } .receipt { width: 100%; margin: 0; border: 0; border-radius: 0; } .actions { display: none; } }
    </style>
</head>
<body>
    <main class="receipt">
        <header class="header">
            <p style="margin:0 0 8px; opacity:.8;">{{ $payment->school->name }}</p>
            <h1 style="margin:0;">{{ $payment->isReversal() ? 'Payment reversal record' : 'Official payment receipt' }}</h1>
            <p style="margin:8px 0 0;">{{ $payment->receipt_number }}</p>
        </header>

        <section class="content">
            <div class="amount">{{ $payment->isReversal() ? '−' : '' }}{{ $payment->amount->formatTo(app()->getLocale()) }}</div>

            <div class="grid">
                <div><p class="label">Student / payer</p><p class="value">{{ $payment->payer_name }}</p></div>
                <div><p class="label">Invoice</p><p class="value">{{ $payment->invoice->name }}</p></div>
                <div><p class="label">Fee item</p><p class="value">{{ $payment->invoiceRecord->fee->name }}</p></div>
                <div><p class="label">Recorded at</p><p class="value">{{ $payment->occurred_at->format('d M Y, H:i') }}</p></div>
                <div><p class="label">Payment method</p><p class="value">{{ ucfirst(str_replace('_', ' ', $payment->payment_method)) }}</p></div>
                <div><p class="label">Cashier</p><p class="value">{{ $payment->cashier_name }}</p></div>
                @if($payment->external_reference)
                    <div><p class="label">External reference</p><p class="value">{{ $payment->external_reference }}</p></div>
                @endif
                @if($payment->provider)
                    <div><p class="label">Payment provider</p><p class="value">{{ ucfirst($payment->provider) }}</p></div>
                @endif
                @if($payment->provider_reference)
                    <div><p class="label">Provider reference</p><p class="value">{{ $payment->provider_reference }}</p></div>
                @endif
                @if($payment->originalTransaction)
                    <div><p class="label">Original receipt</p><p class="value">{{ $payment->originalTransaction->receipt_number }}</p></div>
                @endif
            </div>

            @if($payment->notes)
                <div class="audit"><strong>Audit note:</strong> {{ $payment->notes }}</div>
            @endif

            <div class="audit">
                This record is generated from the immutable fee-payment ledger. Corrections appear as separate reversal transactions and do not overwrite the original payment.
            </div>
        </section>
    </main>
    <div class="actions"><button type="button" onclick="window.print()">Print receipt</button></div>
</body>
</html>
