{{-- partials/create-form.blade.php --}}
<div class="bg-white rounded-lg shadow-lg p-8">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-900">
            <i class="fas fa-user-plus mr-2 text-blue-600"></i>Create New Student
        </h2>
        <button wire:click="switchMode('list')" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-lg">
            <i class="fas fa-arrow-left mr-2"></i>Back to List
        </button>
    </div>

    <form wire:submit.prevent="createStudent" class="space-y-6">
        <!-- Personal Information -->
        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-6 rounded-lg">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Personal Information</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Full Name *</label>
                    <input type="text" wire:model="name" class="w-full px-4 py-3 border-2 @error('name') border-red-300 @else border-gray-300 @enderror rounded-lg focus:ring-2 focus:ring-indigo-500">
                    @error('name') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Email *</label>
                    <input type="email" wire:model="email" class="w-full px-4 py-3 border-2 @error('email') border-red-300 @else border-gray-300 @enderror rounded-lg focus:ring-2 focus:ring-indigo-500">
                    @error('email') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>
                <div>
                    <x-password-input label="Password *" wire:model="password"
                        @class(['px-4 py-3 focus:ring-indigo-500', 'border-red-300' => $errors->has('password'), 'border-gray-300' => !$errors->has('password')]) />
                    @error('password') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Gender *</label>
                    <select wire:model="gender" class="w-full px-4 py-3 border-2 @error('gender') border-red-300 @else border-gray-300 @enderror rounded-lg focus:ring-2 focus:ring-indigo-500">
                        <option value="">Select Gender</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                    </select>
                    @error('gender') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Birthday</label>
                    <input type="date" wire:model="birthday" class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Phone</label>
                    <input type="tel" wire:model="phone" class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>
        </div>

        <!-- Academic Information -->
        <div class="bg-gradient-to-r from-purple-50 to-pink-50 p-6 rounded-lg">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Academic Information</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Class *</label>
                    <select wire:model.live="my_class_id" class="w-full px-4 py-3 border-2 @error('my_class_id') border-red-300 @else border-gray-300 @enderror rounded-lg focus:ring-2 focus:ring-indigo-500">
                        <option value="">Select Class</option>
                        @foreach($classes as $class)
                            <option value="{{ $class->id }}">{{ $class->name }}</option>
                        @endforeach
                    </select>
                    @error('my_class_id') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Section</label>
                    <select wire:model="section_id" class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                        <option value="">Select Section</option>
                        @foreach($sections as $section)
                            <option value="{{ $section->id }}">{{ $section->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Admission Number</label>
                    <input type="text" wire:model="admission_number" placeholder="Auto-generated if left empty" class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Admission Date</label>
                    <input type="date" wire:model="admission_date" class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>
        </div>

        <section class="overflow-hidden rounded-2xl border border-sky-200 bg-white">
            <button type="button" wire:click="toggleParentPanel" class="flex w-full items-center justify-between gap-4 bg-sky-50 px-6 py-5 text-left transition hover:bg-sky-100" aria-expanded="{{ $showParentPanel ? 'true' : 'false' }}">
                <span class="flex items-start gap-4">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-sky-100 text-sky-700"><i class="fas fa-people-roof" aria-hidden="true"></i></span>
                    <span><span class="block text-lg font-bold text-slate-900">Parent or guardian</span><span class="mt-1 block text-sm font-normal text-slate-600">Select an existing parent or create a new parent account without leaving this page.</span></span>
                </span>
                <i class="fas fa-chevron-{{ $showParentPanel ? 'up' : 'down' }} text-sky-700" aria-hidden="true"></i>
            </button>

            @if ($showParentPanel)
                <div class="space-y-5 border-t border-sky-100 p-6">
                    @if (session()->has('parent_created'))
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800"><i class="fas fa-check-circle mr-2" aria-hidden="true"></i>{{ session('parent_created') }}</div>
                    @endif

                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="flex cursor-pointer gap-3 rounded-xl border p-4 {{ $parentEntryMode === 'select' ? 'border-sky-400 bg-sky-50' : 'border-slate-200' }}">
                            <input type="radio" wire:model.live="parentEntryMode" value="select" class="mt-1 text-sky-700 focus:ring-sky-600">
                            <span><span class="block font-bold text-slate-900">Select existing parent</span><span class="mt-1 block text-xs leading-5 text-slate-600">Use an account already registered at this school.</span></span>
                        </label>
                        @can('create parent')
                            <label class="flex cursor-pointer gap-3 rounded-xl border p-4 {{ $parentEntryMode === 'create' ? 'border-sky-400 bg-sky-50' : 'border-slate-200' }}">
                                <input type="radio" wire:model.live="parentEntryMode" value="create" class="mt-1 text-sky-700 focus:ring-sky-600">
                                <span><span class="block font-bold text-slate-900">Create new parent</span><span class="mt-1 block text-xs leading-5 text-slate-600">Create an account and select it automatically.</span></span>
                            </label>
                        @endcan
                    </div>

                    @if ($parentEntryMode === 'select')
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">Parent or guardian account</label>
                            <select wire:model="parent_id" class="w-full rounded-xl border-2 border-slate-300 px-4 py-3 focus:border-sky-500 focus:ring-sky-500">
                                <option value="">Continue without assigning a parent</option>
                                @foreach ($availableParents as $parent)
                                    <option value="{{ $parent->id }}">{{ $parent->name }} · {{ $parent->email }}@if($parent->phone) · {{ $parent->phone }}@endif</option>
                                @endforeach
                            </select>
                            @error('parent_id') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                            @if ($availableParents->isEmpty())<p class="mt-2 text-sm text-slate-500">No parent accounts have been created for this school yet.</p>@endif
                        </div>
                    @else
                        @can('create parent')
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div><label class="mb-1 block text-sm font-semibold text-slate-700">Full name *</label><input type="text" wire:model="newParentName" class="w-full rounded-xl border-slate-300 px-4 py-3">@error('newParentName')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror</div>
                                    <div><label class="mb-1 block text-sm font-semibold text-slate-700">Email *</label><input type="email" wire:model="newParentEmail" class="w-full rounded-xl border-slate-300 px-4 py-3">@error('newParentEmail')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror</div>
                                    <div><label class="mb-1 block text-sm font-semibold text-slate-700">Gender *</label><select wire:model="newParentGender" class="w-full rounded-xl border-slate-300 px-4 py-3"><option value="">Select gender</option><option value="male">Male</option><option value="female">Female</option></select>@error('newParentGender')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror</div>
                                    <div><label class="mb-1 block text-sm font-semibold text-slate-700">Phone</label><input type="tel" wire:model="newParentPhone" class="w-full rounded-xl border-slate-300 px-4 py-3">@error('newParentPhone')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror</div>
                                    <div><x-password-input label="Password *" wire:model="newParentPassword" />@error('newParentPassword')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror</div>
                                    <div><x-password-input label="Confirm password *" wire:model="newParentPasswordConfirmation" />@error('newParentPasswordConfirmation')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror</div>
                                </div>
                                <div class="mt-5 flex justify-end"><button type="button" wire:click="createInlineParent" wire:loading.attr="disabled" wire:target="createInlineParent" class="rounded-xl bg-sky-700 px-5 py-3 font-bold text-white hover:bg-sky-800 disabled:opacity-50"><i class="fas fa-user-plus mr-2"></i><span wire:loading.remove wire:target="createInlineParent">Create and select parent</span><span wire:loading wire:target="createInlineParent">Creating parent…</span></button></div>
                            </div>
                        @endcan
                    @endif
                </div>
            @endif
        </section>

        <div class="flex justify-end gap-3">
            <button type="button" wire:click="switchMode('list')" class="px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold rounded-lg">
                Cancel
            </button>
            <button type="submit" class="px-6 py-3 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-semibold rounded-lg shadow-lg">
                <i class="fas fa-save mr-2"></i>Create Student
            </button>
        </div>
    </form>
</div>
