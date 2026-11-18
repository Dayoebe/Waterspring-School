<div class="space-y-6">
    @if (session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800" role="status">{{ session('success') }}</div>
    @endif

    <section class="rounded-3xl bg-sky-950 p-6 text-white shadow-xl sm:p-8">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
            <div class="max-w-3xl">
                <p class="text-xs font-black uppercase tracking-[0.22em] text-amber-300">Excellence · Character · Growth · Recognition</p>
                <h1 class="mt-3 text-3xl font-black sm:text-4xl">Face of the Week</h1>
                <p class="mt-3 leading-7 text-sky-100">Celebrate one outstanding student from each class, preserve their achievement and publish only after review and consent confirmation.</p>
            </div>
            @can('create student recognition')
                <button wire:click="openCreate" type="button" class="inline-flex items-center justify-center gap-2 rounded-xl bg-amber-300 px-5 py-3 font-black text-sky-950 hover:bg-amber-200"><i class="fas fa-plus"></i> New recognition</button>
            @endcan
        </div>
    </section>

    <div class="flex flex-col gap-3 rounded-2xl border border-sky-200 bg-sky-50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-xs font-black uppercase tracking-[0.18em] text-sky-700">Active academic context</p>
            <p class="mt-1 font-bold text-slate-900">{{ $academicContext['year'] ?: 'Academic session not set' }} · {{ $academicContext['term'] ?: 'Term not set' }}</p>
        </div>
        <p class="max-w-xl text-sm leading-6 text-slate-600">New recognitions automatically use the session and term currently selected for the school.</p>
    </div>
    @error('academicContext')<div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800" role="alert">{{ $message }}</div>@enderror

    @if($showForm)
        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            <div class="flex items-start justify-between gap-4"><div><p class="text-xs font-bold uppercase tracking-widest text-sky-700">{{ $editingId ? 'Edit selection' : 'New selection' }}</p><h2 class="mt-1 text-2xl font-black text-slate-900">Recognition details</h2></div><button type="button" wire:click="$set('showForm', false)" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" aria-label="Close form"><i class="fas fa-times"></i></button></div>
            <form class="mt-6 space-y-5" wire:submit="saveDraft">
                <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                    <label class="block"><span class="text-sm font-bold text-slate-700">Class</span><select wire:model.live="classId" class="mt-2 w-full rounded-xl border-slate-300"><option value="">Select class</option>@foreach($classes as $class)<option value="{{ $class->id }}">{{ $class->name }}</option>@endforeach</select>@error('classId')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror</label>
                    <label class="block"><span class="text-sm font-bold text-slate-700">Student</span><select wire:model.live="studentId" class="mt-2 w-full rounded-xl border-slate-300"><option value="">Select student</option>@foreach($students as $student)<option value="{{ $student->id }}">{{ $student->name }}</option>@endforeach</select>@error('studentId')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror</label>
                    <label class="block"><span class="text-sm font-bold text-slate-700">Recognition category</span><select wire:model.live="category" class="mt-2 w-full rounded-xl border-slate-300"><option value="">Select category</option>@foreach(\App\Support\RecognitionCategories::all() as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select>@error('category')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror</label>
                    @if($category === \App\Support\RecognitionCategories::CUSTOM)
                        <label class="block"><span class="text-sm font-bold text-slate-700">Custom category</span><input wire:model="customCategory" type="text" maxlength="120" class="mt-2 w-full rounded-xl border-slate-300" placeholder="Describe the exceptional category"><span class="mt-1 block text-xs text-amber-700">Custom categories require administrator approval before publication.</span>@error('customCategory')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror</label>
                    @endif
                    <label class="block"><span class="text-sm font-bold text-slate-700">Recognition week</span><input wire:model="weekDate" type="date" class="mt-2 w-full rounded-xl border-slate-300"><span class="mt-1 block text-xs text-slate-500">Any date in the week may be selected; it is stored Monday–Sunday.</span>@error('weekDate')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror</label>
                    <div class="block">
                        <span class="text-sm font-bold text-slate-700">Recognition photograph</span>
                        @if($photo || $photoPreviewUrl)
                            <div class="mt-2 flex items-center gap-4 rounded-2xl border border-slate-200 bg-slate-50 p-3">
                                <img src="{{ $photo ? $photo->temporaryUrl() : $photoPreviewUrl }}" alt="{{ $selectedStudent?->name ?: 'Selected student' }} recognition photograph" class="size-20 shrink-0 rounded-xl object-cover shadow-sm">
                                <div class="min-w-0"><p class="text-sm font-bold text-slate-800">{{ $photo ? 'New recognition photograph selected' : $photoPreviewSource }}</p><p class="mt-1 text-xs leading-5 text-slate-500">{{ $photo ? 'This will replace the default photograph when you save.' : 'This picture will be used automatically unless you choose another one below.' }}</p></div>
                            </div>
                        @else
                            <div class="mt-2 rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-5 text-sm text-slate-500">Select a student to use their profile photograph automatically.</div>
                        @endif
                        <label for="recognition-photo" class="mt-3 block text-xs font-bold uppercase tracking-wide text-slate-600">Use a different photograph (optional)</label>
                        <input id="recognition-photo" wire:model="photo" type="file" accept="image/jpeg,image/png,image/webp" class="mt-2 block w-full text-sm">
                        <span class="mt-1 block text-xs text-slate-500">JPG, PNG or WebP, up to 5 MB.</span>
                        @error('photo')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="grid gap-5 lg:grid-cols-2">
                    <div class="block"><div class="flex flex-wrap items-center justify-between gap-2"><label for="recognition-reason" class="text-sm font-bold text-slate-700">Why this student deserves recognition</label><button type="button" wire:click="generateReasonWithAi" wire:loading.attr="disabled" wire:target="generateReasonWithAi" title="Ask ClassPilot AI to draft the recognition reason" class="inline-flex items-center gap-2 rounded-full border border-violet-200 bg-violet-50 px-3 py-1.5 text-xs font-black text-violet-800 transition hover:bg-violet-100 disabled:cursor-wait disabled:opacity-60"><i class="fas fa-wand-magic-sparkles" aria-hidden="true"></i><span wire:loading.remove wire:target="generateReasonWithAi">Draft with AI</span><span wire:loading wire:target="generateReasonWithAi">Drafting…</span></button></div><textarea id="recognition-reason" wire:model="reason" rows="5" maxlength="1200" class="mt-2 w-full rounded-xl border-slate-300" placeholder="Give a specific, respectful explanation..."></textarea><p class="mt-1 text-xs text-slate-500">Select the class, student and category first. AI uses only those nomination facts and never saves automatically.</p>@error('reason')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror @error('reasonAiGeneration')<div class="mt-2 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-800" role="alert">{{ $message }}</div>@enderror @if($reasonAiNotice)<div class="mt-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-800" role="status"><i class="fas fa-circle-check mr-2"></i>{{ $reasonAiNotice }}</div>@endif</div>
                    <div class="block"><div class="flex flex-wrap items-center justify-between gap-2"><label for="recognition-message" class="text-sm font-bold text-slate-700">Congratulatory message</label><button type="button" wire:click="generateMessageWithAi" wire:loading.attr="disabled" wire:target="generateMessageWithAi" title="Ask ClassPilot AI to draft the congratulatory message" class="inline-flex items-center gap-2 rounded-full border border-violet-200 bg-violet-50 px-3 py-1.5 text-xs font-black text-violet-800 transition hover:bg-violet-100 disabled:cursor-wait disabled:opacity-60"><i class="fas fa-wand-magic-sparkles" aria-hidden="true"></i><span wire:loading.remove wire:target="generateMessageWithAi">Draft with AI</span><span wire:loading wire:target="generateMessageWithAi">Drafting…</span></button></div><textarea id="recognition-message" wire:model="message" rows="5" maxlength="500" class="mt-2 w-full rounded-xl border-slate-300" placeholder="Write the message that will appear on the recognition..."></textarea><p class="mt-1 text-xs text-slate-500">The draft remains editable and is never saved or published until you choose to do so.</p>@error('message')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror @error('messageAiGeneration')<div class="mt-2 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-800" role="alert">{{ $message }}</div>@enderror @if($messageAiNotice)<div class="mt-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-800" role="status"><i class="fas fa-circle-check mr-2"></i>{{ $messageAiNotice }}</div>@endif</div>
                </div>
                <label class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4"><input wire:model="publicDisplayApproved" type="checkbox" class="mt-1 rounded border-amber-400 text-sky-700"><span><strong class="block text-sm text-slate-900">Publication and photo permission confirmed</strong><span class="mt-1 block text-sm leading-6 text-slate-600">I confirm that this recognition and photograph may be displayed on the public school website. Drafts can be saved without this confirmation.</span></span></label>
                @if(auth()->user()->hasAnyRole(['principal', 'admin', 'super-admin', 'super_admin']))<label class="flex items-center gap-3 text-sm font-semibold text-slate-700"><input wire:model="replaceExisting" type="checkbox" class="rounded border-slate-300"> Replace an existing published selection for this class and week</label>@endif
                <div class="flex flex-wrap gap-3"><button type="submit" class="rounded-xl border border-slate-300 bg-white px-5 py-3 font-bold text-slate-800 hover:bg-slate-50">Save draft</button><button type="button" wire:click="preview" class="rounded-xl bg-sky-100 px-5 py-3 font-bold text-sky-900 hover:bg-sky-200">Preview</button><button type="button" wire:click="publish" wire:confirm="Publish this recognition on the dashboard and public website?" class="rounded-xl bg-sky-900 px-5 py-3 font-bold text-white hover:bg-sky-800">Publish</button></div>
            </form>
            @if($showPreview)
                @php($previewStudent = $students->firstWhere('id', (int) $studentId))
                @php($previewClass = $classes->firstWhere('id', (int) $classId))
                <div class="mt-8 border-t border-slate-200 pt-7"><p class="text-xs font-bold uppercase tracking-widest text-sky-700">Preview</p><div class="mt-4 max-w-md overflow-hidden rounded-3xl bg-sky-950 text-white shadow-xl">@if($photo || $photoPreviewUrl)<img src="{{ $photo ? $photo->temporaryUrl() : $photoPreviewUrl }}" alt="{{ $previewStudent?->name }} recognition preview" class="h-64 w-full object-cover">@endif<div class="p-6"><p class="text-xs font-black uppercase tracking-[0.2em] text-amber-300">Face of the Week</p><h3 class="mt-4 text-3xl font-black">{{ $previewStudent?->name }}</h3><p class="mt-1 text-sky-200">{{ $previewClass?->name }}</p><p class="mt-4 inline-block rounded-full bg-amber-300 px-3 py-1 text-xs font-bold text-sky-950">{{ \App\Support\RecognitionCategories::label($category, $customCategory) }}</p><p class="mt-5 leading-7 text-sky-50">{{ $reason }}</p><p class="mt-4 border-l-4 border-amber-300 pl-4 font-semibold italic">{{ $message }}</p></div></div></div>
            @endif
        </section>
    @endif

    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between"><div><p class="text-xs font-bold uppercase tracking-widest text-slate-500">Programme records</p><h2 class="mt-1 text-2xl font-black text-slate-900">Recognition history</h2></div><div class="flex flex-col gap-3 sm:flex-row"><input wire:model.live.debounce.300ms="search" type="search" placeholder="Search student" class="rounded-xl border-slate-300"><select wire:model.live="statusFilter" class="rounded-xl border-slate-300"><option value="all">All statuses</option><option value="published">Published</option><option value="draft">Draft</option></select></div></div>
        @if($recognitions->isEmpty())<div class="mt-6 rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-10 text-center"><i class="fas fa-award text-4xl text-slate-300"></i><p class="mt-3 font-bold text-slate-700">No recognition records match these filters.</p></div>@else
            <div class="mt-6 grid gap-6 md:grid-cols-2 xl:grid-cols-3">@foreach($recognitions as $recognition)<div><x-recognition-card :recognition="$recognition" :shareable="$recognition->status === 'published'"/><div class="mt-3 flex flex-wrap gap-2"><span class="rounded-full px-3 py-1 text-xs font-bold {{ $recognition->status === 'published' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">{{ ucfirst($recognition->status) }}</span>@can('update', $recognition)<button wire:click="edit({{ $recognition->id }})" class="rounded-lg border px-3 py-1 text-xs font-bold">Edit</button>@endcan @if($recognition->status === 'published')@can('publish', $recognition)<button wire:click="unpublish({{ $recognition->id }})" wire:confirm="Unpublish this recognition?" class="rounded-lg border border-amber-300 px-3 py-1 text-xs font-bold text-amber-800">Unpublish</button>@endcan @else @can('delete', $recognition)<button wire:click="delete({{ $recognition->id }})" wire:confirm="Delete this draft?" class="rounded-lg border border-red-200 px-3 py-1 text-xs font-bold text-red-700">Delete</button>@endcan @endif</div></div>@endforeach</div><div class="mt-6">{{ $recognitions->links() }}</div>
        @endif
    </section>

    @push('scripts')
        <script>
            window.downloadRecognitionCard = async function (container, action = 'download') {
                const data = container.dataset;
                const canvas = document.createElement('canvas'); canvas.width = 1080; canvas.height = 1350;
                const ctx = canvas.getContext('2d');
                const load = src => new Promise((resolve, reject) => { const img = new Image(); img.crossOrigin = 'anonymous'; img.onload = () => resolve(img); img.onerror = reject; img.src = src; });
                const wrap = (text, x, y, width, lineHeight, maxLines) => { const words = text.split(/\s+/); let line = '', lines = []; words.forEach(word => { const test = line ? line + ' ' + word : word; if (ctx.measureText(test).width > width && line) { lines.push(line); line = word; } else line = test; }); if (line) lines.push(line); lines.slice(0, maxLines).forEach((item, i) => ctx.fillText(item, x, y + i * lineHeight)); };
                try {
                    const [photo, logo] = await Promise.all([load(data.photo), load(data.logo)]);
                    ctx.fillStyle = '#082f49'; ctx.fillRect(0, 0, 1080, 1350); ctx.fillStyle = '#fcd34d'; ctx.fillRect(0, 0, 1080, 24);
                    ctx.save(); ctx.beginPath(); ctx.roundRect(90, 215, 900, 650, 34); ctx.clip(); const scale = Math.max(900/photo.width, 650/photo.height); ctx.drawImage(photo, 90+(900-photo.width*scale)/2, 215+(650-photo.height*scale)/2, photo.width*scale, photo.height*scale); ctx.restore();
                    ctx.fillStyle = 'rgba(8,47,73,.72)'; ctx.fillRect(90, 735, 900, 130); ctx.fillStyle = '#fcd34d'; ctx.font = '700 34px sans-serif'; ctx.fillText('FACE OF THE WEEK', 90, 130);
                    ctx.drawImage(logo, 870, 58, 120, 120); ctx.fillStyle = '#fff'; ctx.font = '800 58px sans-serif'; wrap(data.name, 120, 930, 840, 66, 2);
                    ctx.fillStyle = '#bae6fd'; ctx.font = '600 30px sans-serif'; ctx.fillText(data.class + '  •  ' + data.week, 120, 1055);
                    ctx.fillStyle = '#fcd34d'; ctx.font = '700 28px sans-serif'; wrap(data.category, 120, 1110, 840, 36, 2);
                    ctx.fillStyle = '#fff'; ctx.font = '500 27px sans-serif'; wrap(data.message, 120, 1200, 840, 36, 3);
                    ctx.fillStyle = '#7dd3fc'; ctx.font = '600 22px sans-serif'; ctx.fillText('WATERSPRINGS INTERNATIONAL SCHOOL', 120, 1310);
                    const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/png', 1)); const file = new File([blob], 'face-of-the-week-' + data.name.toLowerCase().replace(/[^a-z0-9]+/g, '-') + '.png', {type:'image/png'});
                    if (action === 'share' && navigator.share && navigator.canShare && navigator.canShare({files:[file]})) await navigator.share({title:'Face of the Week', text:data.name + ' — ' + data.category, files:[file], url:'{{ route('face-of-the-week') }}'}); else { const link = document.createElement('a'); link.download = file.name; link.href = URL.createObjectURL(blob); link.click(); setTimeout(() => URL.revokeObjectURL(link.href), 1000); }
                } catch (error) { alert('The share card could not be generated. Check that both the student photograph and school logo are accessible, then try again.'); }
            };
        </script>
    @endpush
</div>
