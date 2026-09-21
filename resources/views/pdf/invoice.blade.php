<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 13px; color: #334155; background: #fff; padding: 30px; }

    /* ── HEADER ── */
    .header { text-align: center; padding-bottom: 20px; border-bottom: 3px solid #1e3a8a; margin-bottom: 24px; }
    .header h1 { color: #1e3a8a; font-size: 26px; font-weight: bold; letter-spacing: 1px; }
    .header p  { color: #64748b; font-size: 12px; margin-top: 4px; }
    .invoice-no { font-size: 15px; font-weight: bold; color: #87CEFA; margin-top: 6px; }

    /* ── 2-COL INFO GRID ── */
    .info-grid { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
    .info-grid td { vertical-align: top; width: 50%; padding: 0 8px 0 0; }
    .info-grid td:last-child { padding: 0 0 0 8px; }

    .info-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; }
    .info-box-title { font-size: 11px; font-weight: bold; text-transform: uppercase;
                      letter-spacing: 1px; color: #1e3a8a; border-bottom: 1px solid #e2e8f0;
                      padding-bottom: 6px; margin-bottom: 10px; }
    .info-row { width: 100%; border-collapse: collapse; }
    .info-row td { padding: 4px 0; font-size: 12px; }
    .info-row td:first-child { color: #94a3b8; width: 38%; }
    .info-row td:last-child { font-weight: 600; color: #1e293b; }

    /* ── BADGE ── */
    .badge { display: inline-block; background: #dcfce7; color: #166534;
             padding: 2px 10px; border-radius: 20px; font-size: 11px; font-weight: bold; }

    /* ── SECTION ── */
    .section { margin-bottom: 22px; }
    .section-title { font-size: 11px; font-weight: bold; text-transform: uppercase;
                     letter-spacing: 1px; color: #1e3a8a; border-bottom: 2px solid #e2e8f0;
                     padding-bottom: 6px; margin-bottom: 12px; }

    /* ── HOTEL INFO TABLE ── */
    .hotel-table { width: 100%; border-collapse: collapse; }
    .hotel-table td { padding: 5px 8px; font-size: 12px; border-bottom: 1px solid #f1f5f9; }
    .hotel-table td:first-child { color: #94a3b8; width: 30%; }
    .hotel-table td:last-child { font-weight: 600; }

    /* ── ITEMS TABLE ── */
    .items-table { width: 100%; border-collapse: collapse; margin-top: 4px; }
    .items-table th { background: #1e3a8a; color: #fff; padding: 10px 12px;
                      font-size: 12px; text-align: left; }
    .items-table th:not(:first-child) { text-align: right; }
    .items-table td { padding: 10px 12px; font-size: 12px; border-bottom: 1px solid #f1f5f9; }
    .items-table td:not(:first-child) { text-align: right; }
    .items-table tr:nth-child(even) td { background: #f8fafc; }

    /* ── TOTAL ── */
    .discount-row td { color: #ef4444; font-size: 12px; padding: 8px 12px; }
    .total-row td { font-size: 15px; font-weight: bold; color: #1e3a8a;
                    padding: 12px; border-top: 2px solid #1e3a8a; }

    /* ── FOOTER ── */
    .footer { text-align: center; margin-top: 36px; padding-top: 16px;
              border-top: 1px solid #e2e8f0; color: #94a3b8; font-size: 11px; }
    .footer p { margin: 3px 0; }
</style>
</head>
<body>

{{-- ── HEADER ── --}}
<div class="header">
    <h1>HolidayViet</h1>
    <p>Hóa đơn thanh toán dịch vụ lưu trú</p>
    <p class="invoice-no">{{ $invoice->invoice_no }}</p>
</div>

{{-- ── THÔNG TIN KHÁCH HÀNG + HÓA ĐƠN ── --}}
<table class="info-grid">
    <tr>
        <td>
            <div class="info-box">
                <div class="info-box-title">Thông tin khách hàng</div>
                <table class="info-row">
                    <tr><td>Họ tên</td>      <td>{{ $booking->customer->name }}</td></tr>
                    <tr><td>Email</td>        <td>{{ $booking->customer->email }}</td></tr>
                    <tr><td>Điện thoại</td>  <td>{{ $booking->customer->phone ?? 'N/A' }}</td></tr>
                </table>
            </div>
        </td>
        <td>
            <div class="info-box">
                <div class="info-box-title">Thông tin hóa đơn</div>
                <table class="info-row">
                    <tr><td>Số HĐ</td>      <td>{{ $invoice->invoice_no }}</td></tr>
                    <tr><td>Ngày xuất</td>   <td>{{ $invoice->issued_at->format('d/m/Y H:i') }}</td></tr>
                    <tr><td>Mã booking</td>  <td>#{{ $booking->id }}</td></tr>
                    <tr><td>Trạng thái</td>  <td><span class="badge">Đã thanh toán</span></td></tr>
                </table>
            </div>
        </td>
    </tr>
</table>

{{-- ── THÔNG TIN KHÁCH SẠN ── --}}
<div class="section">
    <div class="section-title">Thông tin khách sạn</div>
    <table class="hotel-table">
        <tr><td>Khách sạn</td><td>{{ $booking->hotel->name }}</td></tr>
        <tr><td>Địa chỉ</td>  <td>{{ $booking->hotel->address }}</td></tr>
        <tr>
            <td>Check-in</td>
            <td>{{ \Carbon\Carbon::parse($booking->check_in)->format('d/m/Y') }}
                lúc {{ $booking->hotel->check_in_time }}</td>
        </tr>
        <tr>
            <td>Check-out</td>
            <td>{{ \Carbon\Carbon::parse($booking->check_out)->format('d/m/Y') }}
                lúc {{ $booking->hotel->check_out_time }}</td>
        </tr>
    </table>
</div>

{{-- ── CHI TIẾT DỊCH VỤ ── --}}
<div class="section">
    <div class="section-title">Chi tiết dịch vụ</div>
    <table class="items-table">
        <thead>
            <tr>
                <th>Mô tả</th>
                <th>SL</th>
                <th>Đơn giá / đêm</th>
                <th>Thành tiền</th>
            </tr>
        </thead>
        <tbody>
            @foreach($booking->bookingRooms as $br)
            <tr>
                <td>
                    {{ $br->roomType->name }}
                    @if($br->room)
                        — Phòng {{ $br->room->room_number }}
                    @endif
                    <br>
                    <span style="color:#94a3b8; font-size:11px;">
                        {{ \Carbon\Carbon::parse($booking->check_in)->format('d/m/Y') }}
                        → {{ \Carbon\Carbon::parse($booking->check_out)->format('d/m/Y') }}
                        ({{ $br->nights }} đêm)
                    </span>
                </td>
                <td>{{ $br->quantity }}</td>
                <td>{{ number_format($br->price_at_booking, 0, '.', ',') }}đ</td>
                <td>{{ number_format($br->price_at_booking * $br->nights * $br->quantity, 0, '.', ',') }}đ</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            @if($invoice->discount > 0)
            <tr class="discount-row">
                <td colspan="3" style="text-align:right;">Giảm giá</td>
                <td>-{{ number_format($invoice->discount, 0, '.', ',') }}đ</td>
            </tr>
            @endif
            <tr class="total-row">
                <td colspan="3" style="text-align:right;">TỔNG CỘNG</td>
                <td style="color:#87CEFA;">{{ number_format($invoice->total, 0, '.', ',') }}đ</td>
            </tr>
        </tfoot>
    </table>
</div>

{{-- ── FOOTER ── --}}
<div class="footer">
    <p>Cảm ơn quý khách đã sử dụng dịch vụ của HolidayViet</p>
    <p>Mọi thắc mắc vui lòng liên hệ: support@holidayviet.vn</p>
    <p style="margin-top:8px;">Tài liệu được tạo tự động — {{ now()->format('d/m/Y H:i') }}</p>
</div>

</body>
</html>