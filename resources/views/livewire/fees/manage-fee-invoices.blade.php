<div class="space-y-6">
    @if($mode === 'list')
        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="p-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Fees & Payments</h1>
                        <p class="mt-1 text-gray-600">Manage student invoices, balances, and reusable fee items in one place.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @can('read fee category')
                            <a href="{{ route('fee-categories.index') }}"
                               wire:navigate
                               class="inline-flex items-center rounded-lg bg-gray-100 px-4 py-2 font-medium text-gray-700 transition hover:bg-gray-200">
                                <i class="fas fa-tags mr-2" aria-hidden="true"></i>Fee Categories
                            </a>
                        @endcan
                        @can('create fee invoice')
                            <a href="{{ route('fee-invoices.create') }}"
                               wire:navigate
                               class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 font-medium text-white transition hover:bg-indigo-700">
                                <i class="fas fa-plus mr-2" aria-hidden="true"></i>{{ auth()->user()?->can('create fee') ? 'Create Fee / Invoice' : 'Create Invoice' }}
                            </a>
                        @endcan
                    </div>
                </div>
            </div>

            <div class="flex gap-1 border-t border-gray-200 bg-gray-50 px-4 pt-2" role="tablist" aria-label="Fees and payments views">
                @can('read fee invoice')
                    <button type="button"
                            wire:click="showTab('invoices')"
                            role="tab"
                            aria-selected="{{ $tab === 'invoices' ? 'true' : 'false' }}"
                            class="rounded-t-lg border-b-2 px-4 py-3 text-sm font-semibold transition {{ $tab === 'invoices' ? 'border-indigo-600 bg-white text-indigo-700' : 'border-transparent text-gray-600 hover:text-gray-900' }}">
                        <i class="fas fa-file-invoice-dollar mr-2" aria-hidden="true"></i>Student Invoices
                    </button>
                @endcan
                @can('read fee')
                    <button type="button"
                            wire:click="showTab('catalog')"
                            role="tab"
                            aria-selected="{{ $tab === 'catalog' ? 'true' : 'false' }}"
                            class="rounded-t-lg border-b-2 px-4 py-3 text-sm font-semibold transition {{ $tab === 'catalog' ? 'border-indigo-600 bg-white text-indigo-700' : 'border-transparent text-gray-600 hover:text-gray-900' }}">
                        <i class="fas fa-list-check mr-2" aria-hidden="true"></i>Fee Catalogue
                    </button>
                @endcan
                @can('update fee invoice')
                    <button type="button"
                            wire:click="showTab('collections')"
                            role="tab"
                            aria-selected="{{ $tab === 'collections' ? 'true' : 'false' }}"
                            class="rounded-t-lg border-b-2 px-4 py-3 text-sm font-semibold transition {{ $tab === 'collections' ? 'border-indigo-600 bg-white text-indigo-700' : 'border-transparent text-gray-600 hover:text-gray-900' }}">
                        <i class="fas fa-cash-register mr-2" aria-hidden="true"></i>Payment Management
                    </button>
                @endcan
            </div>
        </section>

        @if($tab === 'collections' && auth()->user()?->can('update fee invoice'))
            <livewire:fees.manage-fee-payments :key="'fees-payment-management'" />
        @elseif($tab === 'catalog' && auth()->user()?->can('read fee'))
            <livewire:fees.manage-fees mode="list" :embedded="true" :key="'fees-payments-catalogue'" />
        @else
            @include('livewire.fees.partials.invoices.list-view', ['embedded' => true])
        @endif
    @elseif($mode === 'create')
        @include('livewire.fees.partials.invoices.create-form')
    @elseif($mode === 'edit')
        @include('livewire.fees.partials.invoices.edit-form')
    @endif
</div>
