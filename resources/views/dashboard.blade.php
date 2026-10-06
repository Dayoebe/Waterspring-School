@php
    $breadcrumbs = [['href' => route('dashboard'), 'text' => 'Dashboard', 'active' => true]];
@endphp

@extends('layouts.app')

@section('title', __('Dashboard'))
@section('page_heading', 'Dashboard')

@section('content')
    <div class="dashboard-home">
        @if (session('status'))
            <div class="dashboard-home-status" role="status">
                <i class="fas fa-circle-check" aria-hidden="true"></i>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @include('partials.current-term-theme', ['compact' => true])

        <div class="dashboard-home-notices">
            @livewire('dashboard.active-notices')
        </div>

        <div class="dashboard-home-overview">
            @livewire('dashboard.dashboard-stats')
        </div>
    </div>
@endsection
