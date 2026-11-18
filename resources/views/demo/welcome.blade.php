@extends('Layout')
@section('title','Angel — Đặt phòng')
@section('content')
<section style="max-width:800px;margin:60px auto;padding:28px;background:white;border:1px solid #E2E8F0;border-radius:16px;color:#142B49">
    <h1 style="font-size:32px;font-weight:700;margin:14px 0">Đặt phòng cùng Angel</h1>
    <p style="line-height:1.9">Chọn loại phòng, số lượng và ngày lưu trú. Kiểm tra thông tin, tạo đơn và theo dõi kết quả xác nhận trong tài khoản của bạn.</p>
    <div style="display:flex;flex-wrap:wrap;gap:12px;margin:24px 0"><a href="/login" style="padding:12px 18px;background:#2563EB;color:white;border-radius:9px">Đăng nhập Customer</a><a href="/hotels/1" style="padding:12px 18px;background:#EFF6FF;color:#1D4ED8;border-radius:9px">Chọn phòng khách sạn</a><a href="/admin/login" style="padding:12px 18px;background:#F1F5F9;color:#475569;border-radius:9px">Đăng nhập Admin</a></div>
    <p style="line-height:1.9">Thông tin lưu trú và bản xác nhận được lưu trong lịch sử đặt phòng.</p>
</section>
@endsection
