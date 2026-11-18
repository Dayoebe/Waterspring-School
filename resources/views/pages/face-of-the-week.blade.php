@extends('layouts.app', ['mode' => 'public'])

@section('title', 'Face of the Week')

@section('content')
    @livewire('recognitions.public-recognitions')
@endsection
