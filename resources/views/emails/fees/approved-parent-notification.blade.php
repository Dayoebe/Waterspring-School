<x-mail::message>
# New fee announcement

Hello {{ $recipient->name }},

{{ $fee->feeCategory->school->name }} has approved the following fee information:

<x-mail::panel>
Fee: **{{ $fee->name }}**

Category: **{{ $fee->feeCategory->name }}**

Amount: **{{ $fee->default_amount->formatTo(app()->getLocale()) }}**

Waiver: **{{ $fee->defaultWaiver()->formatTo(app()->getLocale()) }}**@if($fee->default_waiver_type === \App\Models\Fee::WAIVER_PERCENTAGE) ({{ $fee->waiverPercentage() }}%)@endif

Fine: **{{ $fee->default_fine->formatTo(app()->getLocale()) }}**

Expected total: **{{ $fee->defaultTotal()->formatTo(app()->getLocale()) }}**

Explanation: {{ $fee->description }}
</x-mail::panel>

This announcement does not by itself mean that your child has been charged. Please sign in to the parent portal to view invoices assigned to your child.

<x-mail::button :url="route('fee-invoices.index')">
View fee invoices
</x-mail::button>

If you need clarification, please contact the school before making payment.

Thanks,<br>
{{ $fee->feeCategory->school->name }}
</x-mail::message>
