@extends('user.layouts.user')

@section('title', 'Lịch sử đặt phòng')
@section('page_title', 'Lịch sử đặt phòng')

@section('content')
    @include('user.bookings.bookings-section')
@endsection

@section('scripts')         
    @include('user.bookings.bookings-script')
@endsection                