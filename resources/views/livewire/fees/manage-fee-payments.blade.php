<div class="space-y-6">
    <div>
        <h2 class="text-xl font-bold text-gray-900">Payment Management</h2>
        <p class="mt-1 text-sm text-gray-600">Track paid, part-paid, and unpaid fee records. Manual payments generate an immutable ledger entry and receipt.</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-gray-500">Fee records</p>
            <p class="mt-2 text-2xl font-bold text-gray-900">{{ number_format($summary['total']) }}</p>
        </div>
        <div class="rounded-xl border border-green-200 bg-green-50 p-5 shadow-sm">
            <p class="text-sm font-medium text-green-700">Paid</p>
            <p class="mt-2 text-2xl font-bold text-green-900">{{ number_format($summary['paid']) }}</p>
        </div>
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
            <p class="text-sm font-medium text-amber-700">Part-paid</p>
            <p class="mt-2 text-2xl font-bold text-amber-900">{{ number_format($summary['partial']) }}</p>
        </div>
        <div class="rounded-xl border border-red-200 bg-red-50 p-5 shadow-sm">
            <p class="text-sm font-medium text-red-700">Unpaid</p>
            <p class="mt-2 text-2xl font-bold text-red-900">{{ number_format($summary['unpaid']) }}</p>
        </div>
        <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-5 shadow-sm">
            <p class="text-sm font-medium text-indigo-700">Outstanding</p>
            <p class="mt-2 text-2xl font-bold text-indigo-950">{{ $summary['outstanding']->formatTo(app()->getLocale()) }}</p>
        </div>
    </div>

    @if($lastPayment)
        <div class="flex flex-col gap-3 rounded-xl border border-green-200 bg-green-50 p-4 text-green-900 sm:flex-row sm:items-center sm:justify-between">
            <p><i class="fas fa-check-circle mr-2" aria-hidden="true"></i>Payment saved as receipt <strong>{{ $lastPayment->receipt_number }}</strong>.</p>
            <a href="{{ route('fee-payments.receipt', $lastPayment) }}" target="_blank" class="font-semibold text-green-800 underline">Open receipt</a>
        </div>
    @endif

    @if($selectedRecord)
        @php
            $selectedBalance = $selectedRecord->amount->plus($selectedRecord->fine)->minus($selectedRecord->waiver)->minus($selectedRecord->paid);
        @endphp
        <section class="rounded-xl border border-indigo-200 bg-indigo-50 p-6 shadow-sm">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="text-lg font-bold text-indigo-950">Record manual payment</h2>
                    <p class="mt-1 text-sm text-indigo-800">
                        {{ $selectedRecord->feeInvoice->user->name }} — {{ $selectedRecord->fee->name }}.
                        Outstanding: <strong>{{ $selectedBalance->formatTo(app()->getLocale()) }}</strong>
                    </p>
                </div>
                <button type="button" wire:click="cancelPayment" class="text-sm font-semibold text-indigo-800 hover:text-indigo-950">Cancel</button>
            </div>

            <form wire:submit="recordManualPayment" class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div>
                    <label for="manual-payment-amount" class="mb-2 block text-sm font-medium text-gray-700">Amount</label>
                    <input id="manual-payment-amount" type="number" min="0.01" step="0.01" wire:model="paymentAmount"
                           class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                    @error('paymentAmount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="manual-payment-method" class="mb-2 block text-sm font-medium text-gray-700">Payment method</label>
                    <select id="manual-payment-method" wire:model="paymentMethod" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach($paymentMethods as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('paymentMethod') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="manual-payment-reference" class="mb-2 block text-sm font-medium text-gray-700">Reference</label>
                    <input id="manual-payment-reference" type="text" wire:model="paymentReference" maxlength="120"
                           placeholder="Teller, POS or transfer reference"
                           class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                    @error('paymentReference') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="manual-payment-note" class="mb-2 block text-sm font-medium text-gray-700">Note</label>
                    <input id="manual-payment-note" type="text" wire:model="paymentNote" maxlength="1000"
                           placeholder="Optional cashier note"
                           class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                    @error('paymentNote') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="md:col-span-2 xl:col-span-4">
                    <button type="submit" wire:loading.attr="disabled" wire:target="recordManualPayment"
                            class="inline-flex items-center rounded-lg bg-indigo-700 px-5 py-2.5 font-semibold text-white hover:bg-indigo-800 disabled:opacity-60">
                        <i class="fas fa-receipt mr-2" aria-hidden="true"></i>Save Payment & Generate Receipt
                    </button>
                </div>
            </form>
        </section>
    @endif

    <section class="rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="grid gap-4 border-b border-gray-200 p-5 md:grid-cols-4">
            <div>
                <label for="collection-search" class="mb-2 block text-sm font-medium text-gray-700">Search</label>
                <input id="collection-search" type="search" wire:model.live.debounce.300ms="search"
                       placeholder="Student, fee or invoice"
                       class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label for="collection-class" class="mb-2 block text-sm font-medium text-gray-700">Class</label>
                <select id="collection-class" wire:model.live="classFilter" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All classes</option>
                    @foreach($classes as $class)
                        <option value="{{ $class['id'] }}">{{ $class['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="collection-status" class="mb-2 block text-sm font-medium text-gray-700">Payment status</label>
                <select id="collection-status" wire:model.live="statusFilter" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="all">All statuses</option>
                    <option value="unpaid">Unpaid</option>
                    <option value="partial">Part-paid</option>
                    <option value="paid">Paid</option>
                </select>
            </div>
            <div class="flex items-end">
                <button type="button" wire:click="clearFilters" class="w-full rounded-lg bg-gray-100 px-4 py-2 text-gray-700 hover:bg-gray-200">
                    <i class="fas fa-redo mr-2" aria-hidden="true"></i>Clear filters
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Student</th>
                        <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Class</th>
                        <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Fee</th>
                        <th class="px-5 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Total</th>
                        <th class="px-5 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Paid</th>
                        <th class="px-5 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Balance</th>
                        <th class="px-5 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                        <th class="px-5 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse($records as $record)
                        @php
                            $invoice = $record->feeInvoice;
                            $class = $invoice->billingClass ?? $invoice->user->studentRecord?->myClass;
                            $section = $invoice->billingSection ?? $invoice->user->studentRecord?->section;
                            $total = $record->amount->plus($record->fine)->minus($record->waiver);
                            $balance = $total->minus($record->paid);
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-4">
                                <p class="font-medium text-gray-900">{{ $invoice->user->name }}</p>
                                <p class="text-xs text-gray-500">{{ $invoice->name }}</p>
                            </td>
                            <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-700">
                                {{ $class?->name ?? 'N/A' }}{{ $section?->name ? ' - '.$section->name : '' }}
                            </td>
                            <td class="px-5 py-4 text-sm font-medium text-gray-900">{{ $record->fee->name }}</td>
                            <td class="whitespace-nowrap px-5 py-4 text-right text-sm text-gray-900">{{ $total->formatTo(app()->getLocale()) }}</td>
                            <td class="whitespace-nowrap px-5 py-4 text-right text-sm font-medium text-green-700">{{ $record->paid->formatTo(app()->getLocale()) }}</td>
                            <td class="whitespace-nowrap px-5 py-4 text-right text-sm font-semibold {{ $balance->isGreaterThan(0) ? 'text-red-700' : 'text-green-700' }}">{{ $balance->formatTo(app()->getLocale()) }}</td>
                            <td class="whitespace-nowrap px-5 py-4 text-center">
                                @if($balance->isLessThanOrEqualTo(0))
                                    <span class="rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-800">Paid</span>
                                @elseif($record->paid->isGreaterThan(0))
                                    <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">Part-paid</span>
                                @else
                                    <span class="rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-800">Unpaid</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-5 py-4 text-right text-sm">
                                @if($balance->isGreaterThan(0))
                                    <button type="button" wire:click="startPayment({{ $record->id }})" class="font-semibold text-indigo-700 hover:text-indigo-900">Record payment</button>
                                @else
                                    <a href="{{ route('fee-invoices.show', $invoice) }}" wire:navigate class="font-semibold text-gray-600 hover:text-gray-900">View</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-12 text-center text-gray-500">No fee records match these filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($records->hasPages())
            <div class="border-t border-gray-200 px-5 py-4">{{ $records->links() }}</div>
        @endif
    </section>
</div>
