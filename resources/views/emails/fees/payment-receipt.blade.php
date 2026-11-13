<x-mail::message>
# Payment received

Hello {{ $recipient->name }},

We have confirmed a school fee payment for **{{ $transaction->payer_name }}**.

<x-mail::panel>
Amount: **{{ $transaction->amount->formatTo(app()->getLocale()) }}**

Fee: **{{ $transaction->invoiceRecord->fee->name }}**

Invoice: **{{ $transaction->invoice->name }}**

Receipt: **{{ $transaction->receipt_number }}**

Date: **{{ $transaction->occurred_at->format('d M Y, H:i') }}**
</x-mail::panel>

<x-mail::button :url="route('fee-payments.receipt', $transaction->id)">
View receipt
</x-mail::button>

If you did not expect this payment, contact the school and quote the receipt number above.

Thanks,<br>
{{ $transaction->school->name }}
</x-mail::message>
