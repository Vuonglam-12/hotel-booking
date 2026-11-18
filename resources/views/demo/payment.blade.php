@extends('user.layouts.user')
@section('title','Thanh toán đặt phòng — Angel')
@section('content')
@include('demo.payment-panel', ['bookingId'=>$bookingId, 'adminMode'=>false])
@endsection
@section('scripts')
@include('demo.payment-script', ['bookingId'=>$bookingId, 'adminMode'=>false])
@endsection
