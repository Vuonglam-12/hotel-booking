@extends('layout')
@php
    $titles = ['awaiting_ipn' => 'Đang xác minh thanh toán', 'success' => 'Thanh toán thành công',
        'late_reconciliation' => 'Thanh toán cần đối soát', 'duplicate_payment_reconciliation' => 'Thanh toán cần đối soát',
        'failed' => 'Thanh toán chưa thành công', 'invalid' => 'Không xác minh được thanh toán'];
    $title = $titles[$status] ?? $titles['invalid'];
@endphp
@section('title', $title)
@section('content')
<div class="min-h-screen flex items-center justify-center p-4" style="background: #EAF3FF;">
    <div class="bg-white rounded-2xl shadow-lg max-w-lg w-full p-8 text-center" data-payment-state="{{ $status }}">
        <h1 class="text-2xl font-bold text-[#3A4A5A] mb-2">{{ $title }}</h1>
        <p class="text-slate-600 mb-6" role="status">
            @if($status === 'success')
                {{ $emailSent ? 'Email xác nhận đã được gửi.' : 'Đặt phòng đã được xác nhận. Email xác nhận đang chờ gửi.' }}
            @elseif($status === 'awaiting_ipn')
                Angel đang chờ xác nhận từ cổng thanh toán. Trang này không tự xác nhận đặt phòng.
            @elseif(in_array($status, ['late_reconciliation', 'duplicate_payment_reconciliation']))
                Giao dịch đã được ghi nhận và cần đối soát. Vui lòng liên hệ hỗ trợ; đặt phòng không được tự động khôi phục.
            @elseif($status === 'failed')
                Giao dịch chưa thành công. Vui lòng kiểm tra trạng thái đặt phòng trước khi thử lại.
            @else
                Không xác minh được kết quả. Vui lòng kiểm tra lịch sử đặt phòng hoặc liên hệ hỗ trợ.
            @endif
        </p>
        @if($booking)
        <div class="bg-[#EAF3FF] rounded-xl p-4 text-left space-y-2 text-sm mb-6">
            <div class="flex justify-between"><span>Mã booking</span><strong>#{{ $booking->id }}</strong></div>
            <div class="flex justify-between"><span>Khách sạn</span><span>{{ $booking->hotel->name }}</span></div>
            <div class="flex justify-between"><span>Check-in</span><span>{{ \Carbon\Carbon::parse($booking->check_in)->format('d/m/Y') }}</span></div>
            <div class="flex justify-between"><span>Check-out</span><span>{{ \Carbon\Carbon::parse($booking->check_out)->format('d/m/Y') }}</span></div>
            <div class="border-t border-[#C9D3DD]/30 pt-2 flex justify-between"><span>Tổng tiền</span><strong>{{ number_format($booking->total_price, 0, '.', ',') }} VND</strong></div>
        </div>
        @endif
        <div class="flex gap-3">
            <a href="{{ route('home') }}" class="flex-1 py-3 rounded-xl border border-[#C9D3DD] text-[#3A4A5A] text-sm font-semibold hover:bg-[#EAF3FF] transition">Về trang chủ</a>
            <a href="{{ url('/bookings') }}" class="flex-1 py-3 rounded-xl bg-blue-600 text-white text-sm font-semibold">Xem booking</a>
        </div>
    </div>
</div>
@endsection
