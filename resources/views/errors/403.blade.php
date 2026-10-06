@extends('errors.layout')

@section('title', '403 Forbidden')

@section('content')
    @include('errors.partials.error-page', [
        'code' => '403',
        'heading' => 'Forbidden',
        'message' => 'You do not have permission to access this page.'
    ])
@endsection
