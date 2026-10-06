@extends('errors.layout')

@php($statusCode = method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : 400)

@section('title', $statusCode . ' Request Error')

@section('content')
    @include('errors.partials.error-page', [
        'code' => (string) $statusCode,
        'heading' => 'We could not complete that request',
        'message' => 'The requested page or action is not available in its current form. Return to a safe page and try again.'
    ])
@endsection
