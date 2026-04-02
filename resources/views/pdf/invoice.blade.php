<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 13px; color: #3A4A5A; margin: 0; padding: 20px; }
    .header { text-align: center; border-bottom: 2px solid #87CEFA; padding-bottom: 16px; margin-bottom: 20px; }
    .header h1 { color: #1E3A5F; font-size: 22px; margin: 0 0 4px; }
    .header p { color: #888; margin: 0; font-size: 12px; }
    .invoice-no { font-size: 16px; font-weight: bold; color: #87CEFA; }
    .section { margin-bottom: 16px; }
    .section-title { font-weight: bold; font-size: 13px; color: #1E3A5F; border-bottom: 1px solid #EAF3FF; padding-bottom: 4px; margin-bottom: 8px; }
    table { width: 100%; border-collapse: collapse; }
    .info-table td { padding: 5px 8px; font-size: 12px; }
    .info-table td:first-child { color: #888; width: 40%; }
    .info-table td:last-child { font-weight: bold; }
    .items-table th { background: #1E3A5F; color: white; padding: 8px; text-align: left; font-size: 12px; }
    .items-table td { padding: 8px; border-bottom: 1px solid #EAF3FF; font-size: 12px; }
    .items-table tr:nth-child(even) td { background: #F8FBFF; }
    .total-row td { font-weight: bold; font-size: 14px; color: #1E3A5F; padding: 10px 8px; border-top: 2px solid #87CEFA; }
    .badge { display: inline-block; background: #EAF3FF; color: #1E3A5F; padding: 3px 10px; border-radius: 20px; font-size: 11px; }
    .footer { text-align: center; margin-top: 30px; padding-top: 16px; border-top: 1px solid #EAF3FF; color: #888; font-size: 11px; }
</style>
</head>
<body>

<div class="header">
    <h1>HOTEL BOOKING SYSTEM</h1>
    <p>Hóa đơn thanh toán dịch vụ lưu trú</p>
    <p class="invoice-no">{{ $invoice->invoice_no }}</p>
</div>

<div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
    <div style="width: 48%;">
        <div class="section">
            <div class="section-title">Thông tin khách hàng</div>
            <table class="info-table">
                <tr><td>Họ tên</td><td>{{ $booking->customer->name }}</td></tr>
                <tr><td>Email</td><td>{{ $booking->customer->email }}</td></tr>
                <tr><td>Điện thoại</td><td>{{ $booking->customer->phone ?? 'N/A' }}</td></tr>
            </table>
        </div>
    </div>
    <div style="width: 48%;">
        <div class="section">
            <div class="section-title">Thông tin hóa đơn</div>
            <table class="info-table">
                <tr><td>Số HĐ</td><td>{{ $invoice->invoice_no }}</td></tr>
                <tr><td>Ngày xuất</td><td>{{ $invoice->issued_at->format('d/m/Y H:i') }}</td></tr>
                <tr><td>Mã booking</td><td>#{{ $booking->id }}</td></tr>
                <tr><td>Trạng thái</td><td><span class="badge">Đã thanh toán</span></td></tr>
            </table>
        </div>
    </div>
</div>

<div class="section">
    <div class="section-title">Thông tin khách sạn</div>
    <table class="info-table">
        <tr><td>Khách sạn</td><td>{{ $booking->hotel->name }}</td></tr>
        <tr><td>Địa chỉ</td><td>{{ $booking->hotel->address }}</td></tr>
        <tr><td>Check-in</td><td>{{ \Carbon\Carbon::parse($booking->check_in)->format('d/m/Y') }} lúc {{ $booking->hotel->check_in_time }}</td></tr>
        <tr><td>Check-out</td><td>{{ \Carbon\Carbon::parse($booking->check_out)->format('d/m/Y') }} lúc {{ $booking->hotel->check_out_time }}</td></tr>
    </table>
</div>

<div class="section">
    <div class="section-title">Chi tiết dịch vụ</div>
    <table class="items-table">
        <thead>
            <tr>
                <th>Mô tả</th>
                <th style="text-align:center">SL</th>
                <th style="text-align:right">Đơn giá</th>
                <th style="text-align:right">Thành tiền</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $item)
            <tr>
                <td>{{ $item->description }}</td>
                <td style="text-align:center">{{ $item->quantity }}</td>
                <td style="text-align:right">{{ number_format($item->unit_price, 0, '.', ',') }}đ</td>
                <td style="text-align:right">{{ number_format($item->amount, 0, '.', ',') }}đ</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            @if($invoice->discount > 0)
            <tr>
                <td colspan="3" style="text-align:right; color:#888; font-size:12px;">Giảm giá</td>
                <td style="text-align:right; color:#ef4444;">-{{ number_format($invoice->discount, 0, '.', ',') }}đ</td>
            </tr>
            @endif
            <tr class="total-row">
                <td colspan="3" style="text-align:right">TỔNG CỘNG</td>
                <td style="text-align:right; color:#87CEFA; font-size:16px;">{{ number_format($invoice->total, 0, '.', ',') }}đ</td>
            </tr>
        </tfoot>
    </table>
</div>

<div class="footer">
    <p>Cảm ơn quý khách đã sử dụng dịch vụ của Hotel Booking System</p>
    <p>Mọi thắc mắc vui lòng liên hệ: support@hotelbooking.vn</p>
    <p style="margin-top: 8px; color: #bbb;">Tài liệu này được tạo tự động — {{ now()->format('d/m/Y H:i') }}</p>
</div>

</body>
</html>