@extends('layouts.app')

@section('content')
<main class="mx-auto max-w-2xl px-4 py-12 sm:px-6">
    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-xl sm:p-8">
        <p class="text-xs font-black uppercase tracking-[0.2em] text-red-700">Admission offer</p>
        <h1 class="mt-3 text-3xl font-black text-slate-950">{{ $admission->student_name }}</h1>
        <p class="mt-2 text-sm text-slate-600">Reference {{ $admission->reference_no }} · {{ $admission->school?->name }}</p>

        @if(session('success'))
            <div class="mt-6 rounded-2xl bg-emerald-50 p-4 text-sm font-semibold text-emerald-800">{{ session('success') }}</div>
        @elseif($admission->status === 'accepted' || $admission->status === 'enrolled')
            <div class="mt-6 rounded-2xl bg-emerald-50 p-4 text-sm font-semibold text-emerald-800">This offer has been accepted.</div>
        @elseif(!$valid)
            <div class="mt-6 rounded-2xl bg-rose-50 p-4 text-sm font-semibold text-rose-800">This offer link is invalid or has expired. Contact the school for assistance.</div>
        @else
            <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                <p><strong>Class:</strong> {{ $admission->myClass?->name ?: 'To be confirmed' }}</p>
                <p class="mt-1"><strong>Offer expires:</strong> {{ $admission->offer_expires_at?->format('M d, Y g:i A') }}</p>
            </div>
            @error('offer') <p class="mt-4 rounded-xl bg-rose-50 p-3 text-sm text-rose-700">{{ $message }}</p> @enderror
            <form method="POST" action="{{ route('admission.offer.accept', [$admission->reference_no, $token]) }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label class="mb-1 block text-sm font-bold text-slate-700">Name of parent or guardian accepting</label>
                    <input name="accepted_by_name" value="{{ old('accepted_by_name', $admission->guardian_name) }}" required maxlength="255" class="w-full rounded-xl border border-slate-300 px-4 py-3">
                    @error('accepted_by_name') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
                <button class="w-full rounded-xl bg-emerald-700 px-5 py-3 font-black text-white hover:bg-emerald-800">Accept admission offer</button>
            </form>
        @endif
    </div>
</main>
@endsection
