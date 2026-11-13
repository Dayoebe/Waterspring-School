<div class="space-y-6">
    @unless($embedded)
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">Create Fee</h2>
                    <p class="text-gray-600 mt-1">Add a new fee item to your school</p>
                </div>
                <a href="{{ route('fees.index') }}"
                   wire:navigate
                   class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    <i class="fas fa-arrow-left mr-2"></i>Back to List
                </a>
            </div>
        </div>
    @endunless

    <!-- Form Card -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <form wire:submit.prevent="createFee" class="p-6">
            <div class="space-y-6">
                <!-- Name -->
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                        Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" 
                           id="name"
                           wire:model.live.debounce.300ms="name"
                           class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 @error('name') border-red-500 @enderror"
                           placeholder="e.g., Monthly Tuition, Admission Fee">
                    @error('name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Fee Category -->
                <div>
                    <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                        <label for="fee_category_id" class="block text-sm font-medium text-gray-700">
                            Fee Category <span class="text-red-500">*</span>
                        </label>
                        @can('create fee category')
                            <button type="button" wire:click="$toggle('showCategoryCreator')"
                                    class="text-sm font-semibold text-indigo-700 hover:text-indigo-900">
                                <i class="fas fa-plus-circle mr-1"></i>{{ $showCategoryCreator ? 'Close category creator' : 'Create category here' }}
                            </button>
                        @endcan
                    </div>
                    <select id="fee_category_id"
                            wire:model="fee_category_id"
                            class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 @error('fee_category_id') border-red-500 @enderror">
                        <option value="">Select a category</option>
                        @foreach($feeCategories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('fee_category_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    @if($showCategoryCreator)
                        <div class="mt-4 rounded-xl border border-indigo-200 bg-indigo-50 p-4">
                            <h3 class="font-semibold text-indigo-950">New fee category</h3>
                            <p class="mt-1 text-sm text-indigo-700">Create it here and it will be selected automatically.</p>
                            <div class="mt-4 grid gap-4 md:grid-cols-2">
                                <div>
                                    <label for="newCategoryName" class="mb-1 block text-sm font-medium text-gray-700">Category name</label>
                                    <input id="newCategoryName" type="text" wire:model="newCategoryName" maxlength="100"
                                           placeholder="e.g., Tuition fees"
                                           class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 @error('newCategoryName') border-red-500 @enderror">
                                    @error('newCategoryName') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label for="newCategoryDescription" class="mb-1 block text-sm font-medium text-gray-700">Category description</label>
                                    <input id="newCategoryDescription" type="text" wire:model="newCategoryDescription" maxlength="1000"
                                           placeholder="What belongs in this category?"
                                           class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                    @error('newCategoryDescription') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                            </div>
                            <button type="button" wire:click="createCategoryInline" wire:loading.attr="disabled" wire:target="createCategoryInline"
                                    class="mt-4 rounded-lg bg-indigo-700 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-800 disabled:opacity-60">
                                <span wire:loading.remove wire:target="createCategoryInline">Create and select category</span>
                                <span wire:loading wire:target="createCategoryInline">Creating…</span>
                            </button>
                        </div>
                    @endif
                    @if($feeCategories->isEmpty())
                        <p class="mt-2 text-sm text-amber-600">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            No fee categories available. 
                            <a href="{{ route('fee-categories.create') }}" wire:navigate class="underline">Create one first</a>
                        </p>
                    @endif
                </div>

                @include('livewire.fees.partials.fees.pricing-fields', ['editing' => false])

                <!-- Description -->
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                        Explanation for parents <span class="text-red-500">*</span>
                    </label>
                    <textarea id="description"
                              wire:model="description"
                              rows="4"
                              class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 @error('description') border-red-500 @enderror"
                              placeholder="Explain what this fee covers and any important payment information"></textarea>
                    @error('description')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Actions -->
                <div class="flex flex-wrap items-center justify-end gap-3 pt-4 border-t">
                    @if($embedded)
                        <button type="submit"
                                wire:loading.attr="disabled"
                                wire:target="createFee,createAndApproveFee"
                                class="rounded-lg bg-gray-100 px-6 py-2 font-semibold text-gray-700 hover:bg-gray-200 disabled:opacity-60"
                                @if($feeCategories->isEmpty()) disabled @endif>
                            <i class="fas fa-file-alt mr-2"></i>Save as Draft
                        </button>
                        @can('approve fee')
                            <button type="button"
                                    wire:click="createAndApproveFee"
                                    wire:loading.attr="disabled"
                                    wire:target="createFee,createAndApproveFee"
                                    class="rounded-lg bg-indigo-700 px-6 py-2 font-semibold text-white hover:bg-indigo-800 disabled:opacity-60"
                                    @if($feeCategories->isEmpty()) disabled @endif>
                                <span wire:loading.remove wire:target="createAndApproveFee"><i class="fas fa-check-circle mr-2"></i>Create, Approve & Add</span>
                                <span wire:loading wire:target="createAndApproveFee">Approving…</span>
                            </button>
                        @endcan
                    @else
                        <a href="{{ route('fees.index') }}"
                           wire:navigate
                           class="px-6 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                            Cancel
                        </a>
                        <button type="submit"
                                class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition"
                                @if($feeCategories->isEmpty()) disabled @endif>
                            <i class="fas fa-save mr-2"></i>Create Fee
                        </button>
                    @endif
                </div>
            </div>
        </form>
    </div>
</div>
