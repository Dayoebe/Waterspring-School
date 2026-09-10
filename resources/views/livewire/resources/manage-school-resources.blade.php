<div class="space-y-6">
    @if(session()->has('success'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ session('success') }}</div>@endif
    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-4 border-b border-slate-200 p-5 lg:flex-row lg:items-center lg:justify-between">
            <div><p class="text-xs font-bold uppercase tracking-[.18em] text-sky-700">School resources</p><h1 class="mt-1 text-2xl font-bold text-slate-950">Library, inventory & assets</h1><p class="mt-1 text-sm text-slate-500">Track stock, locations, condition, loans, and returns.</p></div>
            @can('manage school resources')<button wire:click="create" class="rounded-xl bg-sky-700 px-4 py-2 text-sm font-bold text-white"><i class="fas fa-plus mr-2"></i>Add resource</button>@endcan
        </div>
        <div class="grid gap-3 p-4 md:grid-cols-[1fr_auto]">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search name, code, or category" class="rounded-xl border-slate-300 text-sm">
            <div class="inline-flex rounded-xl bg-slate-100 p-1">@foreach(['library'=>'Library','inventory'=>'Inventory','asset'=>'Assets'] as $value=>$label)<button wire:click="$set('type','{{ $value }}')" class="rounded-lg px-4 py-2 text-sm font-bold {{ $type===$value?'bg-white text-sky-800 shadow-sm':'text-slate-500' }}">{{ $label }}</button>@endforeach</div>
        </div>
    </section>

    @can('manage school resources')
    @if($editingId !== null || ($name === '' && $code === '' && $loanResourceId === null))
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-bold text-slate-950">{{ $editingId ? 'Edit resource' : 'Add resource' }}</h2>
        <form wire:submit="save" class="mt-4 grid gap-4 md:grid-cols-3">
            <div><label class="mb-1 block text-sm font-semibold">Name</label><input wire:model="name" class="w-full rounded-xl border-slate-300">@error('name')<p class="text-xs text-red-600">{{ $message }}</p>@enderror</div>
            <div><label class="mb-1 block text-sm font-semibold">Code / ISBN / Tag</label><input wire:model="code" class="w-full rounded-xl border-slate-300">@error('code')<p class="text-xs text-red-600">{{ $message }}</p>@enderror</div>
            <div><label class="mb-1 block text-sm font-semibold">Category</label><input wire:model="category" class="w-full rounded-xl border-slate-300"></div>
            <div><label class="mb-1 block text-sm font-semibold">{{ $type==='library'?'Author / Publisher':'Brand / Model' }}</label><input wire:model="authorOrBrand" class="w-full rounded-xl border-slate-300"></div>
            <div><label class="mb-1 block text-sm font-semibold">Location</label><input wire:model="location" class="w-full rounded-xl border-slate-300"></div>
            <div><label class="mb-1 block text-sm font-semibold">Condition</label><select wire:model="condition" class="w-full rounded-xl border-slate-300">@foreach(['new','good','fair','poor','damaged','retired'] as $value)<option value="{{ $value }}">{{ ucfirst($value) }}</option>@endforeach</select></div>
            <div><label class="mb-1 block text-sm font-semibold">Quantity</label><input type="number" min="1" wire:model="quantity" class="w-full rounded-xl border-slate-300">@error('quantity')<p class="text-xs text-red-600">{{ $message }}</p>@enderror</div>
            <div><label class="mb-1 block text-sm font-semibold">Unit cost</label><input type="number" min="0" step="0.01" wire:model="unitCost" class="w-full rounded-xl border-slate-300"></div>
            <div><label class="mb-1 block text-sm font-semibold">Acquired</label><input type="date" wire:model="acquiredOn" class="w-full rounded-xl border-slate-300"></div>
            <div class="md:col-span-3"><label class="mb-1 block text-sm font-semibold">Notes</label><textarea wire:model="notes" rows="2" class="w-full rounded-xl border-slate-300"></textarea></div>
            <div class="md:col-span-3 flex justify-end gap-2"><button type="button" wire:click="cancelForm" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold">Clear</button><button class="rounded-xl bg-sky-700 px-5 py-2 text-sm font-bold text-white">Save</button></div>
        </form>
    </section>
    @endif
    @endcan

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-200"><thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">Resource</th><th class="px-4 py-3">Location</th><th class="px-4 py-3">Condition</th><th class="px-4 py-3">Available</th>@can('manage school resources')<th class="px-4 py-3 text-right">Actions</th>@endcan</tr></thead><tbody class="divide-y divide-slate-100">
        @forelse($items as $item)<tr><td class="px-4 py-3"><p class="font-bold text-slate-900">{{ $item->name }}</p><p class="text-xs text-slate-500">{{ $item->code ?: 'No code' }} · {{ $item->category ?: 'Uncategorised' }}@if($item->author_or_brand) · {{ $item->author_or_brand }}@endif</p></td><td class="px-4 py-3 text-sm text-slate-600">{{ $item->location ?: '—' }}</td><td class="px-4 py-3"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-700">{{ ucfirst($item->condition) }}</span></td><td class="px-4 py-3 text-sm"><strong>{{ $item->available_quantity }}</strong> / {{ $item->quantity }}</td>@can('manage school resources')<td class="px-4 py-3"><div class="flex justify-end gap-2">@if($item->available_quantity>0)<button wire:click="beginLoan({{ $item->id }})" class="rounded-lg border border-emerald-200 px-3 py-1.5 text-xs font-bold text-emerald-700">Issue</button>@endif<button wire:click="edit({{ $item->id }})" class="rounded-lg border border-sky-200 px-3 py-1.5 text-xs font-bold text-sky-700">Edit</button><button wire:click="delete({{ $item->id }})" wire:confirm="Delete this resource?" class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-bold text-red-700">Delete</button></div></td>@endcan</tr>@empty<tr><td colspan="5" class="px-4 py-12 text-center text-sm text-slate-500">No resources found in this section.</td></tr>@endforelse
        </tbody></table></div><div class="border-t border-slate-200 p-4">{{ $items->links() }}</div>
    </section>

    @can('manage school resources')
    @if($loanResourceId)
    <section class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5"><h2 class="font-bold text-emerald-950">Issue resource</h2><form wire:submit="issue" class="mt-3 grid gap-3 md:grid-cols-4"><select wire:model="borrowerId" class="rounded-xl border-emerald-300"><option value="">Select borrower</option>@foreach($borrowers as $borrower)<option value="{{ $borrower->id }}">{{ $borrower->name }}</option>@endforeach</select><input type="number" min="1" wire:model="loanQuantity" class="rounded-xl border-emerald-300"><input type="date" wire:model="dueAt" class="rounded-xl border-emerald-300"><button class="rounded-xl bg-emerald-700 px-4 py-2 font-bold text-white">Confirm issue</button></form>@error('borrowerId')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror @error('loanQuantity')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</section>
    @endif
    @if($activeLoans->isNotEmpty())<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="text-lg font-bold">Active loans & allocations</h2><div class="mt-3 divide-y divide-slate-100">@foreach($activeLoans as $loan)<div class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between"><div><p class="font-semibold">{{ $loan->resource->name }} <span class="text-slate-400">× {{ $loan->quantity }}</span></p><p class="text-xs text-slate-500">{{ $loan->borrower->name }} · Due {{ $loan->due_at?->format('j M Y') ?? 'not set' }}</p></div><button wire:click="returnLoan({{ $loan->id }})" class="rounded-lg border border-emerald-200 px-3 py-1.5 text-xs font-bold text-emerald-700">Record return</button></div>@endforeach</div></section>@endif
    @endcan
</div>
