@extends('errors.layout')

@php($statusCode = method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : 500)

@section('title', $statusCode . ' Service Error')

@section('content')
    @include('errors.partials.error-page', [
        'code' => (string) $statusCode,
        'heading' => 'The service needs a moment',
        'message' => 'We could not complete your request right now. Please try again shortly or contact the school office if the problem continues.'
    ])
@endsection
