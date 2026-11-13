<x-mail::message>
# School fee payment received

Hello {{ $recipient->name }},

A school fee payment has been verified and recorded for **{{ $transaction->payer_name }}**.

<x-mail::panel>
Amount: **{{ $transaction->amount->formatTo(app()->getLocale()) }}**

Fee: **{{ $transaction->invoiceRecord->fee->name }}**

Payment method: **{{ \App\Services\FeePaymentLedger::PAYMENT_METHODS[$transaction->payment_method] ?? ucfirst($transaction->payment_method) }}**

Invoice: **{{ $transaction->invoice->name }}**

Receipt: **{{ $transaction->receipt_number }}**

Date: **{{ $transaction->occurred_at->format('d M Y, H:i') }}**
</x-mail::panel>

<x-mail::button :url="route('fee-payments.receipt', $transaction->id)">
Review payment receipt
</x-mail::button>

This alert is sent once for each recorded payment. Repeated Paystack callbacks do not create another alert.

Thanks,<br>
{{ $transaction->school->name }}
</x-mail::message>
