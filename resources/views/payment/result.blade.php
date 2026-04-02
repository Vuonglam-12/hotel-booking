@extends('layout')
@section('title', $status === 'success' ? 'Thanh toán thành công' : 'Thanh toán thất bại')
@section('content')

<div class="min-h-screen flex items-center justify-center p-4" style="background: #EAF3FF;">
    <div class="bg-white rounded-2xl shadow-lg max-w-lg w-full p-8 text-center">

        @if($status === 'success')
            <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-10 h-10 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-[#3A4A5A] mb-2">Thanh toán thành công!</h1>
            <p class="text-[#C9D3DD] mb-6">Email xác nhận đã được gửi đến hộp thư của bạn</p>

            @if($booking)
            <div class="bg-[#EAF3FF] rounded-xl p-4 text-left space-y-2 text-sm mb-6">
                <div class="flex justify-between">
                    <span class="text-[#C9D3DD]">Mã booking</span>
                    <span class="font-bold text-[#3A4A5A]">#{{ $booking->id }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#C9D3DD]">Khách sạn</span>
                    <span class="font-semibold text-[#3A4A5A]">{{ $booking->hotel->name }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#C9D3DD]">Check-in</span>
                    <span class="font-semibold text-[#3A4A5A]">{{ \Carbon\Carbon::parse($booking->check_in)->format('d/m/Y') }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#C9D3DD]">Check-out</span>
                    <span class="font-semibold text-[#3A4A5A]">{{ \Carbon\Carbon::parse($booking->check_out)->format('d/m/Y') }}</span>
                </div>
                <div class="border-t border-[#C9D3DD]/30 pt-2 flex justify-between">
                    <span class="font-bold text-[#3A4A5A]">Tổng tiền</span>
                    <span class="font-bold text-[#87CEFA]">{{ number_format($booking->total_price, 0, '.', ',') }} VND</span>
                </div>
            </div>
            @endif

            <div class="flex gap-3">
                <a href="{{ route('home') }}" class="flex-1 py-3 rounded-xl border border-[#C9D3DD] text-[#3A4A5A] text-sm font-semibold hover:bg-[#EAF3FF] transition">
                    Về trang chủ
                </a>
                <a href="{{ route('dashboard') }}" class="flex-1 py-3 rounded-xl text-sm font-semibold text-white transition"
                    style="background: linear-gradient(135deg, #F59E0B, #FBBF24);">
                    Xem booking
                </a>
            </div>

        @elseif($status === 'failed')
            <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-10 h-10 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-[#3A4A5A] mb-2">Thanh toán thất bại</h1>
            <p class="text-[#C9D3DD] mb-6">{{ $message }}</p>
            <div class="flex gap-3">
                <a href="{{ route('home') }}" class="flex-1 py-3 rounded-xl border border-[#C9D3DD] text-[#3A4A5A] text-sm font-semibold hover:bg-[#EAF3FF] transition">
                    Về trang chủ
                </a>
                <a href="javascript:history.back()" class="flex-1 py-3 rounded-xl text-sm font-semibold text-white transition"
                    style="background: linear-gradient(135deg, #87CEFA, #7BC4F5);">
                    Thử lại
                </a>
            </div>

        @else
            <div class="w-20 h-20 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-10 h-10 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-[#3A4A5A] mb-2">Có lỗi xảy ra</h1>
            <p class="text-[#C9D3DD] mb-6">{{ $message }}</p>
            <a href="{{ route('home') }}" class="block w-full py-3 rounded-xl text-sm font-semibold text-white transition"
                style="background: linear-gradient(135deg, #87CEFA, #7BC4F5);">
                Về trang chủ
            </a>
        @endif

    </div>
</div>

@endsection