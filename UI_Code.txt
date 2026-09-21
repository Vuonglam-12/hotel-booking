@extends('user.layouts.user')

@section('title', 'Lịch trình AI')
@section('page_title', 'Lịch trình AI')

@section('content')
    @include('user.itineraries.itineraries-section')
@endsection

@section('scripts')
    @include('user.itineraries.itineraries-script')
@endsection
