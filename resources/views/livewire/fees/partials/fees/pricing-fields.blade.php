@php
    $isEditing = $editing ?? false;
@endphp

<div class="space-y-3"
     x-data="{
        feeName: @entangle('name'),
        amount: @entangle('amount'),
        waiverType: @entangle('waiverType'),
        waiverAmount: @entangle('waiverAmount'),
        waiverPercentage: @entangle('waiverPercentage'),
        fine: @entangle('fine'),
        number(value) {
            const parsed = Number.parseFloat(value);
            return Number.isFinite(parsed) ? parsed : 0;
        },
        calculatedWaiver() {
            if (this.waiverType === 'fixed') return this.number(this.waiverAmount);
            if (this.waiverType === 'percentage') {
                return this.number(this.amount) * this.number(this.waiverPercentage) / 100;
            }
            return 0;
        },
        calculatedTotal() {
            return Math.max(0, this.number(this.amount) + this.number(this.fine) - this.calculatedWaiver());
        },
        money(value) {
            return new Intl.NumberFormat(@js(app()->getLocale()), {
                style: 'currency',
                currency: @js(strtoupper(config('app.currency'))),
                minimumFractionDigits: 2,
            }).format(this.number(value));
        },
        resetAdjustments() {
            this.waiverType = 'none';
            this.waiverAmount = 0;
            this.waiverPercentage = 0;
            this.fine = 0;
        },
     }">
    <div>
        <h3 class="text-sm font-semibold text-gray-900">Default invoice pricing</h3>
        <p class="mt-1 text-sm text-gray-500">
            These values prefill new invoices. A percentage waiver is calculated from the fee amount and saved as an exact amount on each invoice.
        </p>
    </div>

    <div class="overflow-x-auto rounded-xl border border-gray-200">
        <table class="min-w-[980px] w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Fee Name</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Amount</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Waiver</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Fine</th>
                    <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Total</th>
                    <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                <tr class="align-top">
                    <td class="px-4 py-4">
                        <span class="block max-w-48 truncate font-medium text-gray-900" x-text="feeName || 'New fee'"></span>
                    </td>
                    <td class="px-4 py-4">
                        <label for="amount" class="sr-only">Fee amount</label>
                        <input id="amount" type="number" min="0.01" max="21474836.47" step="0.01"
                               x-model="amount" placeholder="0.00"
                               class="w-36 rounded-lg border-gray-300 text-right focus:border-indigo-500 focus:ring-indigo-500 @error('amount') border-red-500 @enderror">
                        @error('amount') <p class="mt-1 max-w-40 text-xs text-red-600">{{ $message }}</p> @enderror
                    </td>
                    <td class="px-4 py-4">
                        <div class="flex min-w-64 gap-2">
                            <label for="waiverType" class="sr-only">Waiver type</label>
                            <select id="waiverType" x-model="waiverType"
                                    class="w-36 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 @error('waiverType') border-red-500 @enderror">
                                @foreach(\App\Models\Fee::waiverTypes() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>

                            <div x-show="waiverType === 'fixed'" x-cloak>
                                <label for="waiverAmount" class="sr-only">Fixed waiver amount</label>
                                <input id="waiverAmount" type="number" min="0" max="21474836.47" step="0.01"
                                       x-model="waiverAmount" placeholder="0.00"
                                       class="w-28 rounded-lg border-gray-300 text-right focus:border-indigo-500 focus:ring-indigo-500 @error('waiverAmount') border-red-500 @enderror">
                            </div>

                            <div x-show="waiverType === 'percentage'" x-cloak class="relative">
                                <label for="waiverPercentage" class="sr-only">Waiver percentage</label>
                                <input id="waiverPercentage" type="number" min="0" max="100" step="0.01"
                                       x-model="waiverPercentage" placeholder="0"
                                       class="w-24 rounded-lg border-gray-300 pr-8 text-right focus:border-indigo-500 focus:ring-indigo-500 @error('waiverPercentage') border-red-500 @enderror">
                                <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-gray-500">%</span>
                            </div>
                        </div>
                        <p x-show="waiverType !== 'none'" x-cloak class="mt-1 text-xs text-orange-700">
                            Waiver: <span x-text="money(calculatedWaiver())"></span>
                        </p>
                        @error('waiverType') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        @error('waiverAmount') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        @error('waiverPercentage') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </td>
                    <td class="px-4 py-4">
                        <label for="fine" class="sr-only">Default fine</label>
                        <input id="fine" type="number" min="0" max="21474836.47" step="0.01"
                               x-model="fine" placeholder="0.00"
                               class="w-32 rounded-lg border-gray-300 text-right focus:border-indigo-500 focus:ring-indigo-500 @error('fine') border-red-500 @enderror">
                        @error('fine') <p class="mt-1 max-w-36 text-xs text-red-600">{{ $message }}</p> @enderror
                    </td>
                    <td class="px-4 py-4 text-right font-semibold text-gray-900">
                        <span x-text="money(calculatedTotal())"></span>
                    </td>
                    <td class="px-4 py-4 text-right">
                        <button type="button" x-on:click="resetAdjustments()"
                                class="whitespace-nowrap text-sm font-semibold text-red-600 hover:text-red-800"
                                title="Clear waiver and fine">
                            <i class="fas fa-undo-alt mr-1"></i>Reset
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    @if($isEditing)
        <p class="text-sm text-amber-700">Changing approved pricing returns this fee to draft for review and parent notification.</p>
    @endif
</div>
