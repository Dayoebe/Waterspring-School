<div x-data="{ activeTab: @entangle('activeTab') }" class="space-y-6">
    @php
        $studentRecord = $feeInvoice->user->studentRecord;
    @endphp
    
    <!-- Header Card -->
    <div class="bg-gradient-to-r from-indigo-600 to-purple-600 rounded-lg shadow-lg overflow-hidden">
        <div class="px-6 py-8">
            <div class="flex flex-col md:flex-row items-start gap-6">
                <div class="flex-1 text-white">
                    <h2 class="text-3xl font-bold mb-2">{{ $feeInvoice->name }}</h2>
                    <div class="grid grid-cols-2 gap-4 text-sm mt-4">
                        <div>
                            <p class="opacity-75">Student</p>
                            <p class="font-semibold text-lg">{{ $feeInvoice->user->name }}</p>
                        </div>
                        <div>
                            <p class="opacity-75">Class</p>
                            <p class="font-semibold">
                                {{ $studentRecord?->myClass?->name ?? 'N/A' }}
                                @if($studentRecord?->section?->name)
                                    - {{ $studentRecord->section->name }}
                                @endif
                            </p>
                        </div>
                        <div>
                            <p class="opacity-75">Issue Date</p>
                            <p class="font-semibold">{{ $feeInvoice->issue_date->format('M d, Y') }}</p>
                        </div>
                        <div>
                            <p class="opacity-75">Due Date</p>
                            <p class="font-semibold">{{ $feeInvoice->due_date->format('M d, Y') }}</p>
                        </div>
                    </div>
                </div>
                
                <!-- Status Badge -->
                <div class="self-start">
                    @if($feeInvoice->balance->isLessThanOrEqualTo(0))
                        <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-bold bg-green-500 text-white">
                            <i class="fas fa-check-circle mr-2"></i>PAID
                        </span>
                    @elseif($feeInvoice->paid->isGreaterThan(0))
                        <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-bold bg-yellow-500 text-white">
                            <i class="fas fa-exclamation-circle mr-2"></i>PARTIAL
                        </span>
                    @else
                        <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-bold bg-red-500 text-white">
                            <i class="fas fa-times-circle mr-2"></i>UNPAID
                        </span>
                    @endif
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-6">
                <div class="bg-white/10 backdrop-blur-sm rounded-lg p-4">
                    <p class="text-white/75 text-sm">Total Amount</p>
                    <p class="text-white text-2xl font-bold">{{ $feeInvoice->amount->formatTo(app()->getLocale()) }}</p>
                </div>
                <div class="bg-white/10 backdrop-blur-sm rounded-lg p-4">
                    <p class="text-white/75 text-sm">Waiver</p>
                    <p class="text-white text-2xl font-bold">{{ $feeInvoice->waiver->formatTo(app()->getLocale()) }}</p>
                </div>
                <div class="bg-white/10 backdrop-blur-sm rounded-lg p-4">
                    <p class="text-white/75 text-sm">Paid</p>
                    <p class="text-green-300 text-2xl font-bold">{{ $feeInvoice->paid->formatTo(app()->getLocale()) }}</p>
                </div>
                <div class="bg-white/10 backdrop-blur-sm rounded-lg p-4">
                    <p class="text-white/75 text-sm">Balance</p>
                    <p class="text-red-300 text-2xl font-bold">{{ $feeInvoice->balance->formatTo(app()->getLocale()) }}</p>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex gap-3 mt-6">
                <a href="{{ route('fee-invoices.index') }}" 
                   wire:navigate
                   class="px-4 py-2 bg-white/20 backdrop-blur-sm hover:bg-white/30 text-white rounded-lg transition">
                    <i class="fas fa-arrow-left mr-2"></i>Back
                </a>
                @if($canManage)
                    <a href="{{ route('fee-invoices.edit', $feeInvoice->id) }}" 
                       wire:navigate
                       class="px-4 py-2 bg-white text-indigo-600 rounded-lg font-semibold shadow hover:shadow-lg transition">
                        <i class="fas fa-edit mr-2"></i>Edit Invoice
                    </a>
                @endif
                <a href="{{ route('fee-invoices.print', $feeInvoice->id) }}" 
                   target="_blank"
                   class="px-4 py-2 bg-white text-indigo-600 rounded-lg font-semibold shadow hover:shadow-lg transition">
                    <i class="fas fa-print mr-2"></i>Print
                </a>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="flex border-b overflow-x-auto">
            <button wire:click="changeTab('details')"
                    @click="activeTab = 'details'" 
                    :class="activeTab === 'details' ? 'border-b-2 border-indigo-600 text-indigo-600' : 'text-gray-600 hover:text-indigo-600'"
                    class="px-6 py-4 font-semibold transition whitespace-nowrap">
                <i class="fas fa-file-invoice mr-2"></i>Details
            </button>
            @if($canManage)
                <button wire:click="changeTab('payments')"
                        @click="activeTab = 'payments'" 
                        :class="activeTab === 'payments' ? 'border-b-2 border-indigo-600 text-indigo-600' : 'text-gray-600 hover:text-indigo-600'"
                        class="px-6 py-4 font-semibold transition whitespace-nowrap">
                    <i class="fas fa-money-bill-wave mr-2"></i>Payments
                </button>
                <button wire:click="changeTab('manage')"
                        @click="activeTab = 'manage'" 
                        :class="activeTab === 'manage' ? 'border-b-2 border-indigo-600 text-indigo-600' : 'text-gray-600 hover:text-indigo-600'"
                        class="px-6 py-4 font-semibold transition whitespace-nowrap">
                    <i class="fas fa-cog mr-2"></i>Manage Fees
                </button>
            @endif
        </div>

        <!-- Tab Content -->
        <div class="p-6">
            <!-- Details Tab -->
            <div x-show="activeTab === 'details'" x-transition>
                <h3 class="text-xl font-bold text-gray-900 mb-4">Fee Items</h3>
                <div class="overflow-x-auto border rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fee Name</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Waiver</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Fine</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Paid</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Balance</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($feeInvoice->feeInvoiceRecords as $record)
                                @php
                                    $balance = $record->amount->plus($record->fine)->minus($record->waiver)->minus($record->paid);
                                @endphp
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">{{ $loop->iteration }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">{{ $record->fee->name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right">{{ $record->amount->formatTo(app()->getLocale()) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-orange-600">{{ $record->waiver->formatTo(app()->getLocale()) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-red-600">{{ $record->fine->formatTo(app()->getLocale()) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-green-600">{{ $record->paid->formatTo(app()->getLocale()) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium">
                                        <span class="{{ $balance->isLessThanOrEqualTo(0) ? 'text-green-600' : 'text-red-600' }}">
                                            {{ $balance->formatTo(app()->getLocale()) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($feeInvoice->note)
                    <div class="mt-6 bg-gray-50 rounded-lg p-4">
                        <h4 class="font-semibold text-gray-900 mb-2">Note:</h4>
                        <p class="text-gray-700">{{ $feeInvoice->note }}</p>
                    </div>
                @endif
            </div>

            @if($canManage)
            <!-- Payments Tab -->
            <div x-show="activeTab === 'payments'" x-transition>
                <div class="mb-5">
                    <h3 class="text-xl font-bold text-gray-900">Payment ledger</h3>
                    <p class="mt-1 text-sm text-gray-600">Payments are permanent ledger entries. Corrections are recorded as linked reversals rather than edits or deletions.</p>
                </div>
                
                @foreach($feeInvoice->feeInvoiceRecords as $record)
                    @php
                        $balance = $record->amount->plus($record->fine)->minus($record->waiver)->minus($record->paid);
                    @endphp
                    <div class="border rounded-lg p-4 mb-6 {{ $balance->isLessThanOrEqualTo(0) ? 'border-green-200' : 'border-gray-200' }}">
                        <div class="flex justify-between items-start mb-3">
                            <div>
                                <h4 class="font-bold text-gray-900">{{ $record->fee->name }}</h4>
                                <div class="text-sm text-gray-600 mt-1 space-y-1">
                                    <p>Amount: <span class="font-medium">{{ $record->amount->formatTo(app()->getLocale()) }}</span></p>
                                    <p>Waiver: <span class="font-medium text-orange-600">{{ $record->waiver->formatTo(app()->getLocale()) }}</span></p>
                                    <p>Fine: <span class="font-medium text-red-600">{{ $record->fine->formatTo(app()->getLocale()) }}</span></p>
                                    <p>Paid: <span class="font-medium text-green-600">{{ $record->paid->formatTo(app()->getLocale()) }}</span></p>
                                    <p>Balance: <span class="font-bold {{ $balance->isLessThanOrEqualTo(0) ? 'text-green-600' : 'text-red-600' }}">{{ $balance->formatTo(app()->getLocale()) }}</span></p>
                                </div>
                            </div>
                            @if($balance->isGreaterThan(0))
                                @if($payingRecordId === $record->id)
                                    <span class="rounded-full bg-indigo-100 px-3 py-1 text-xs font-semibold text-indigo-700">Recording payment</span>
                                @else
                                    <button wire:click="startPayment({{ $record->id }})" 
                                            class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700">
                                        <i class="fas fa-plus mr-1"></i>Add Payment
                                    </button>
                                @endif
                            @else
                                <span class="px-3 py-1 bg-green-100 text-green-800 text-sm rounded-full font-medium">
                                    <i class="fas fa-check-circle mr-1"></i>Paid
                                </span>
                            @endif
                        </div>

                        @if($payingRecordId === $record->id)
                            <div class="mb-5 rounded-lg border border-indigo-200 bg-indigo-50 p-4">
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-gray-700">Amount</label>
                                        <input type="number" wire:model="paymentAmount" step="0.01" min="0.01"
                                               max="{{ $balance->getAmount()->toFloat() }}"
                                               class="w-full rounded border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                        @error('paymentAmount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-gray-700">Payment method</label>
                                        <select wire:model="paymentMethod" class="w-full rounded border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                            @foreach($paymentMethods as $method => $label)
                                                <option value="{{ $method }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-gray-700">Bank, POS or cheque reference</label>
                                        <input type="text" wire:model="paymentReference" maxlength="120"
                                               placeholder="Optional external reference"
                                               class="w-full rounded border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-gray-700">Payment note</label>
                                        <input type="text" wire:model="paymentNote" maxlength="1000"
                                               placeholder="Optional audit note"
                                               class="w-full rounded border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                    </div>
                                </div>
                                <div class="mt-4 flex gap-2">
                                    <button type="button" wire:click="addPayment"
                                            class="rounded bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700">
                                        Record payment and issue receipt
                                    </button>
                                    <button type="button" wire:click="cancelPayment"
                                            class="rounded bg-gray-500 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-600">
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        @endif

                        <div class="overflow-x-auto rounded-lg border">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                                    <tr>
                                        <th class="px-3 py-2">Date</th>
                                        <th class="px-3 py-2">Receipt / reference</th>
                                        <th class="px-3 py-2">Cashier</th>
                                        <th class="px-3 py-2">Method</th>
                                        <th class="px-3 py-2 text-right">Amount</th>
                                        <th class="px-3 py-2">Reconciliation</th>
                                        <th class="px-3 py-2 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white">
                                    @forelse($record->paymentTransactions as $transaction)
                                        @php($latestReconciliation = $transaction->reconciliations->first())
                                        <tr>
                                            <td class="whitespace-nowrap px-3 py-3 text-gray-600">{{ $transaction->occurred_at->format('d M Y H:i') }}</td>
                                            <td class="px-3 py-3">
                                                <a href="{{ route('fee-payments.receipt', $transaction->id) }}" target="_blank"
                                                   class="font-semibold text-indigo-700 hover:underline">{{ $transaction->receipt_number }}</a>
                                                @if($transaction->external_reference)
                                                    <p class="text-xs text-gray-500">Ref: {{ $transaction->external_reference }}</p>
                                                @endif
                                            </td>
                                            <td class="px-3 py-3 text-gray-700">{{ $transaction->cashier_name }}</td>
                                            <td class="px-3 py-3 text-gray-600">
                                                {{ $transaction->isReversal() ? 'Reversal' : ($paymentMethods[$transaction->payment_method] ?? ucfirst(str_replace('_', ' ', $transaction->payment_method))) }}
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-3 text-right font-semibold {{ $transaction->isReversal() ? 'text-red-600' : 'text-green-700' }}">
                                                {{ $transaction->isReversal() ? '−' : '+' }}{{ $transaction->amount->formatTo(app()->getLocale()) }}
                                            </td>
                                            <td class="px-3 py-3">
                                                @if($latestReconciliation)
                                                    <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">
                                                        {{ $reconciliationStatuses[$latestReconciliation->status] ?? ucfirst($latestReconciliation->status) }}
                                                    </span>
                                                    <p class="mt-1 text-xs text-gray-500">{{ $latestReconciliation->reconciler_name }} · {{ $latestReconciliation->created_at->format('d M Y') }}</p>
                                                @else
                                                    <span class="text-xs text-amber-700">Not reconciled</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-3 text-right">
                                                <div class="flex justify-end gap-2">
                                                    <button type="button" wire:click="startReconciliation({{ $transaction->id }})"
                                                            class="rounded bg-slate-700 px-2 py-1 text-xs font-semibold text-white hover:bg-slate-800">
                                                        Reconcile
                                                    </button>
                                                    @if(!$transaction->isReversal() && !$transaction->reversal)
                                                        <button type="button" wire:click="startReversal({{ $transaction->id }})"
                                                                class="rounded bg-red-600 px-2 py-1 text-xs font-semibold text-white hover:bg-red-700">
                                                            Reverse
                                                        </button>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                        @if($reversingTransactionId === $transaction->id)
                                            <tr class="bg-red-50">
                                                <td colspan="7" class="px-3 py-3">
                                                    <div class="flex flex-col gap-2 md:flex-row">
                                                        <input type="text" wire:model="reversalReason" maxlength="1000"
                                                               placeholder="Required reason for reversal"
                                                               class="flex-1 rounded border-red-300 focus:border-red-500 focus:ring-red-500">
                                                        <button type="button" wire:click="reversePayment" class="rounded bg-red-600 px-4 py-2 text-sm font-semibold text-white">Confirm reversal</button>
                                                        <button type="button" wire:click="cancelReversal" class="rounded bg-gray-500 px-4 py-2 text-sm font-semibold text-white">Cancel</button>
                                                    </div>
                                                    @error('reversalReason') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                                </td>
                                            </tr>
                                        @endif
                                        @if($reconcilingTransactionId === $transaction->id)
                                            <tr class="bg-slate-50">
                                                <td colspan="7" class="px-3 py-3">
                                                    <div class="grid grid-cols-1 gap-2 md:grid-cols-3">
                                                        <select wire:model="reconciliationStatus" class="rounded border-gray-300">
                                                            @foreach($reconciliationStatuses as $status => $label)
                                                                <option value="{{ $status }}">{{ $label }}</option>
                                                            @endforeach
                                                        </select>
                                                        <input type="text" wire:model="reconciliationReference" maxlength="120" placeholder="Statement reference" class="rounded border-gray-300">
                                                        <input type="text" wire:model="reconciliationNote" maxlength="1000" placeholder="Optional note" class="rounded border-gray-300">
                                                    </div>
                                                    <div class="mt-2 flex gap-2">
                                                        <button type="button" wire:click="recordReconciliation" class="rounded bg-slate-700 px-4 py-2 text-sm font-semibold text-white">Add reconciliation event</button>
                                                        <button type="button" wire:click="cancelReconciliation" class="rounded bg-gray-500 px-4 py-2 text-sm font-semibold text-white">Cancel</button>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endif
                                    @empty
                                        <tr>
                                            <td colspan="7" class="px-3 py-5 text-center text-sm text-gray-500">No payment transactions recorded for this fee item.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            </div>
            @endif

            @if($canManage)
            <!-- Manage Tab -->
            <div x-show="activeTab === 'manage'" x-transition>
                <div class="space-y-6">
                    <!-- Edit Existing Fees -->
                    <div>
                        <h3 class="text-xl font-bold text-gray-900 mb-4">Edit Fee Items</h3>
                        
                        @foreach($feeInvoice->feeInvoiceRecords as $record)
                            <div class="border rounded-lg p-4 mb-4">
                                @if($editingRecordId === $record->id)
                                    <form wire:submit.prevent="updateRecord" class="space-y-3">
                                        <h4 class="font-bold text-gray-900">{{ $record->fee->name }}</h4>
                                        <div class="grid grid-cols-3 gap-3">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Amount</label>
                                                <input type="number" 
                                                       wire:model="editAmount"
                                                       step="0.01"
                                                       min="0"
                                                       class="w-full rounded border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Waiver</label>
                                                <input type="number" 
                                                       wire:model="editWaiver"
                                                       step="0.01"
                                                       min="0"
                                                       class="w-full rounded border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Fine</label>
                                                <input type="number" 
                                                       wire:model="editFine"
                                                       step="0.01"
                                                       min="0"
                                                       class="w-full rounded border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                            </div>
                                        </div>
                                        <div class="flex gap-2">
                                            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm rounded hover:bg-indigo-700">
                                                <i class="fas fa-save mr-1"></i>Save
                                            </button>
                                            <button type="button" wire:click="cancelEditingRecord" class="px-4 py-2 bg-gray-400 text-white text-sm rounded hover:bg-gray-500">
                                                Cancel
                                            </button>
                                        </div>
                                    </form>
                                @else
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <h4 class="font-bold text-gray-900">{{ $record->fee->name }}</h4>
                                            <p class="text-sm text-gray-600 mt-1">
                                                Amount: {{ $record->amount->formatTo(app()->getLocale()) }} | 
                                                Waiver: {{ $record->waiver->formatTo(app()->getLocale()) }} | 
                                                Fine: {{ $record->fine->formatTo(app()->getLocale()) }}
                                            </p>
                                        </div>
                                        <div class="flex gap-2">
                                            <button wire:click="startEditingRecord({{ $record->id }})" 
                                                    class="px-3 py-1 bg-indigo-600 text-white text-sm rounded hover:bg-indigo-700">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button wire:click="deleteRecord({{ $record->id }})" 
                                                    wire:confirm="Are you sure you want to delete this fee record?"
                                                    class="px-3 py-1 bg-red-600 text-white text-sm rounded hover:bg-red-700">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <!-- Add New Fee -->
                    <div class="border-t pt-6">
                        <h3 class="text-xl font-bold text-gray-900 mb-4">Add New Fee</h3>
                        <form wire:submit.prevent="addNewFee" class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Fee Category</label>
                                    <select wire:model.live="selectedFeeCategory" 
                                            class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">Select Category</option>
                                        @foreach($feeCategories as $category)
                                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Fee</label>
                                    <select wire:model="selectedFee" 
                                            class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                            @if(!$selectedFeeCategory) disabled @endif>
                                        <option value="">Select Fee</option>
                                        @foreach($fees as $fee)
                                            <option value="{{ $fee->id }}">{{ $fee->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('selectedFee')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <div class="grid grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Amount</label>
                                    <input type="number" 
                                           wire:model="newFeeAmount"
                                           step="0.01"
                                           min="0"
                                           class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                    @error('newFeeAmount')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Waiver</label>
                                    <input type="number" 
                                           wire:model="newFeeWaiver"
                                           step="0.01"
                                           min="0"
                                           class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Fine</label>
                                    <input type="number" 
                                           wire:model="newFeeFine"
                                           step="0.01"
                                           min="0"
                                           class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                            </div>

                            <button type="submit" 
                                    class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition"
                                    @if(!$selectedFee) disabled @endif>
                                <i class="fas fa-plus mr-2"></i>Add Fee to Invoice
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
