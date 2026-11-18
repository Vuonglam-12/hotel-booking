@extends('admin.layouts.admin')
@section('title','Xử lý thanh toán đặt phòng')
@section('content')
<p style="margin-bottom:18px"><a href="/admin/bookings">← Danh sách đặt phòng</a> · <a href="/admin/payment-reconciliation">Giao dịch cần đối soát</a></p>
@include('demo.payment-panel', ['bookingId'=>$bookingId,'adminMode'=>true])
@endsection
@push('scripts')
@include('demo.payment-script', ['bookingId'=>$bookingId,'adminMode'=>true])
@endpush
