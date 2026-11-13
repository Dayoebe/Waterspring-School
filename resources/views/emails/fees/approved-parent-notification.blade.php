<x-mail::message>
# New fee announcement

Hello {{ $recipient->name }},

{{ $fee->feeCategory->school->name }} has approved the following fee information:

<x-mail::panel>
Fee: **{{ $fee->name }}**

Category: **{{ $fee->feeCategory->name }}**

Default amount: **{{ $fee->default_amount->formatTo(app()->getLocale()) }}**

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
