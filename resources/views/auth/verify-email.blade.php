@extends('layouts.app', ['mode' => 'public'])

@section('title', 'Verify Email Address')

@section('content')
    <section class="bg-slate-50 px-4 py-16 sm:px-6">
        <div class="mx-auto max-w-xl overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-xl shadow-slate-900/5">
            <div class="bg-slate-900 px-6 py-7 text-white sm:px-8">
                <p class="text-xs font-bold uppercase tracking-widest text-sky-300">Account security</p>
                <h1 class="mt-2 text-2xl font-black">Verify your email address</h1>
            </div>

            <div class="space-y-5 px-6 py-7 sm:px-8">
                <p class="text-sm leading-7 text-slate-600">
                    We sent a verification link to <strong class="text-slate-900">{{ auth()->user()->email }}</strong>.
                    Open that message and follow the link before entering the school portal.
                </p>

                @if (session('status') === 'verification-link-sent')
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                        A fresh verification link has been sent.
                    </div>
                @endif

                <div class="flex flex-col gap-3 sm:flex-row">
                    <form method="POST" action="{{ route('verification.send') }}">
                        @csrf
                        <button type="submit" class="site-primary-bg inline-flex min-h-11 w-full items-center justify-center rounded-xl px-5 text-sm font-bold text-white sm:w-auto">
                            Resend verification email
                        </button>
                    </form>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-slate-300 px-5 text-sm font-bold text-slate-700 sm:w-auto">
                            Log out
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>
@endsection
