<?php

namespace App\Livewire\Resources;

use App\Models\SchoolResource;
use App\Models\SchoolResourceLoan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class ManageSchoolResources extends Component
{
    use WithPagination;

    public string $type = 'library';
    public string $search = '';
    public ?int $editingId = null;
    public string $name = '';
    public string $code = '';
    public string $category = '';
    public string $authorOrBrand = '';
    public string $location = '';
    public string $condition = 'good';
    public int $quantity = 1;
    public string $unitCost = '';
    public string $acquiredOn = '';
    public string $notes = '';
    public ?int $loanResourceId = null;
    public string $borrowerId = '';
    public int $loanQuantity = 1;
    public string $dueAt = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('read school resources'), 403);
    }

    public function updatedType(): void { $this->resetPage(); $this->cancelForm(); }
    public function updatedSearch(): void { $this->resetPage(); }

    public function create(): void { $this->ensureManage(); $this->cancelForm(); }

    public function edit(int $id): void
    {
        $this->ensureManage();
        $item = SchoolResource::query()->findOrFail($id);
        $this->editingId = $item->id;
        $this->type = $item->type;
        $this->name = $item->name;
        $this->code = (string) $item->code;
        $this->category = (string) $item->category;
        $this->authorOrBrand = (string) $item->author_or_brand;
        $this->location = (string) $item->location;
        $this->condition = $item->condition;
        $this->quantity = $item->quantity;
        $this->unitCost = (string) ($item->unit_cost ?? '');
        $this->acquiredOn = $item->acquired_on?->format('Y-m-d') ?? '';
        $this->notes = (string) $item->notes;
    }

    public function save(): void
    {
        $this->ensureManage();
        $data = $this->validate([
            'type' => ['required', 'in:library,inventory,asset'], 'name' => ['required', 'string', 'max:180'],
            'code' => ['nullable', 'string', 'max:80', Rule::unique('school_resources', 'code')->where('school_id', auth()->user()->school_id)->ignore($this->editingId)],
            'category' => ['nullable', 'string', 'max:100'], 'authorOrBrand' => ['nullable', 'string', 'max:180'],
            'location' => ['nullable', 'string', 'max:180'], 'condition' => ['required', 'in:new,good,fair,poor,damaged,retired'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'], 'unitCost' => ['nullable', 'numeric', 'min:0'],
            'acquiredOn' => ['nullable', 'date'], 'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $item = $this->editingId ? SchoolResource::query()->findOrFail($this->editingId) : new SchoolResource(['created_by' => auth()->id()]);
        $checkedOut = $item->exists ? max(0, $item->quantity - $item->available_quantity) : 0;
        if ($data['quantity'] < $checkedOut) { $this->addError('quantity', "At least {$checkedOut} item(s) are currently issued."); return; }
        $item->fill(['type'=>$data['type'], 'name'=>trim($data['name']), 'code'=>trim($data['code']) ?: null, 'category'=>trim($data['category']) ?: null,
            'author_or_brand'=>trim($data['authorOrBrand']) ?: null, 'location'=>trim($data['location']) ?: null, 'condition'=>$data['condition'],
            'quantity'=>$data['quantity'], 'available_quantity'=>$data['quantity'] - $checkedOut, 'unit_cost'=>$data['unitCost'] ?: null,
            'acquired_on'=>$data['acquiredOn'] ?: null, 'notes'=>trim($data['notes']) ?: null])->save();
        $this->cancelForm();
        session()->flash('success', 'Resource saved successfully.');
    }

    public function beginLoan(int $id): void
    {
        $this->ensureManage();
        $item = SchoolResource::query()->where('available_quantity', '>', 0)->findOrFail($id);
        $this->loanResourceId = $item->id; $this->borrowerId = ''; $this->loanQuantity = 1;
        $this->dueAt = now()->addWeeks($item->type === 'library' ? 2 : 1)->format('Y-m-d');
    }

    public function issue(): void
    {
        $this->ensureManage();
        $data = $this->validate(['borrowerId'=>['required','integer'], 'loanQuantity'=>['required','integer','min:1'], 'dueAt'=>['nullable','date','after_or_equal:today']]);
        $borrower = User::query()->where('school_id', auth()->user()->school_id)->findOrFail($data['borrowerId']);
        $issued = DB::transaction(function () use ($borrower, $data): bool {
            $item = SchoolResource::query()->lockForUpdate()->findOrFail($this->loanResourceId);
            if ($data['loanQuantity'] > $item->available_quantity) {
                return false;
            }
            $item->loans()->create(['borrower_id'=>$borrower->id, 'quantity'=>$data['loanQuantity'], 'issued_at'=>now(), 'due_at'=>$data['dueAt'] ?: null, 'issued_by'=>auth()->id()]);
            $item->decrement('available_quantity', $data['loanQuantity']);

            return true;
        });
        if (! $issued) { $this->addError('loanQuantity', 'The requested quantity is not available.'); return; }
        $this->reset(['loanResourceId','borrowerId','dueAt']); $this->loanQuantity = 1;
        session()->flash('success', 'Resource issued successfully.');
    }

    public function returnLoan(int $id): void
    {
        $this->ensureManage();
        DB::transaction(function () use ($id): void {
            $loan = SchoolResourceLoan::query()->whereNull('returned_at')->whereHas('resource')->lockForUpdate()->findOrFail($id);
            $loan->update(['returned_at'=>now(), 'received_by'=>auth()->id(), 'condition_on_return'=>'good']);
            $loan->resource()->increment('available_quantity', $loan->quantity);
        });
        session()->flash('success', 'Return recorded successfully.');
    }

    public function delete(int $id): void
    {
        $this->ensureManage();
        $item = SchoolResource::query()->findOrFail($id);
        abort_if($item->loans()->whereNull('returned_at')->exists(), 422, 'Return issued items before deleting this record.');
        $item->delete(); session()->flash('success', 'Resource removed.');
    }

    public function cancelForm(): void
    {
        $this->reset(['editingId','name','code','category','authorOrBrand','location','unitCost','acquiredOn','notes','loanResourceId','borrowerId','dueAt']);
        $this->condition = 'good'; $this->quantity = 1; $this->loanQuantity = 1; $this->resetValidation();
    }

    protected function ensureManage(): void { abort_unless(auth()->user()?->can('manage school resources'), 403); }

    public function render()
    {
        $items = SchoolResource::query()->withCount(['loans as active_loans_count'=>fn($q)=>$q->whereNull('returned_at')])
            ->where('type', $this->type)->when($this->search !== '', fn($q)=>$q->where(fn($q)=>$q->where('name','like','%'.$this->search.'%')->orWhere('code','like','%'.$this->search.'%')->orWhere('category','like','%'.$this->search.'%')))
            ->orderBy('name')->paginate(15);
        $activeLoans = SchoolResourceLoan::query()->with(['resource','borrower:id,name'])->whereNull('returned_at')->whereHas('resource', fn($q)=>$q->where('type',$this->type))->orderBy('due_at')->get();
        $borrowers = auth()->user()?->can('manage school resources') ? User::query()->where('school_id',auth()->user()->school_id)->where('locked',false)->orderBy('name')->get(['id','name']) : collect();
        return view('livewire.resources.manage-school-resources', compact('items','activeLoans','borrowers'))->layout('layouts.dashboard')->title('Library, Inventory & Assets');
    }
}
