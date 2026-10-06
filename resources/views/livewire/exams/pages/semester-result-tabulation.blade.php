@extends('layouts.app', ['breadcrumbs' => [
    ['href'=> route('dashboard'), 'text'=> 'Dashboard'],
    ['href'=> route('exams.index'), 'text'=> 'exams'],
    ['href'=> route('exams.semester-result-tabulation'), 'text'=> 'Term Result Tabulation', 'active'],
]])

@section('title',    __('Term Result Tabulation'))

@section('page_heading',  __('Term Result Tabulation'))

@section('content', )
@livewire('exams.tabulation.semester-result-tabulation')
@endsection
