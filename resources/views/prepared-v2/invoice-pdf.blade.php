@php($document = \App\Support\BookingDocumentPresentation::for($booking, $invoice ?? null, $bookingDetailsUrl ?? null))
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Hóa đơn {{ $invoice->invoice_no }} | Angel</title>
    <style>
        @page { size: A4 portrait; margin: 16mm 15mm 20mm; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 0; font-family: 'DejaVu Sans', sans-serif; font-size: 11px; line-height: 1.6; color: #1F2937; background: #FFFFFF; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        td, th { vertical-align: top; word-wrap: break-word; }
        h1, h2, p { margin: 0; }
        .muted { color: #6B7280; }
        .header { margin-bottom: 18px; border-bottom: 2px solid #2563EB; }
        .header td { padding-bottom: 15px; }
        .brand-logo { display: block; width: 150px; height: auto; }
        .document-type { margin-top: 4px; color: #6B7280; font-size: 8px; letter-spacing: 1px; }
        h1 { color: #111827; font-size: 20px; line-height: 1.5; }
        .invoice-meta { text-align: right; }
        .invoice-meta p { margin-top: 4px; }
        .invoice-number { color: #2563EB; font-weight: bold; }
        .test-warning { margin-bottom: 16px; padding: 10px 12px; border: 1px solid #2563EB; border-radius: 5px; background: #EFF6FF; color: #1E40AF; text-align: center; font-size: 9px; font-weight: bold; page-break-inside: avoid; }
        .info-grid { margin-bottom: 18px; }
        .info-column { width: 50%; }
        .info-box { padding: 13px 14px; background: #F8F8F8; border: 1px solid #E5E7EB; border-radius: 6px; }
        h2 { margin-bottom: 9px; color: #111827; font-size: 12px; font-weight: bold; }
        .info-name { margin-bottom: 5px; font-weight: bold; font-size: 12px; }
        .info-line { margin-top: 4px; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 4px; background: #EFF6FF; color: #1E40AF; font-size: 10px; font-weight: bold; }
        .booking-section { margin-bottom: 18px; padding: 14px; border: 1px solid #E5E7EB; border-radius: 6px; }
        .booking-details td { padding: 4px 0; }
        .booking-label { width: 25%; color: #6B7280; }
        .section-title { margin-bottom: 10px; }
        .items-table { margin-bottom: 14px; }
        .items-table thead { display: table-header-group; }
        .items-table th { padding: 9px 10px; background: #F8F8F8; border-top: 1px solid #E5E7EB; border-bottom: 1px solid #E5E7EB; color: #4B5563; text-align: left; font-size: 10px; }
        .items-table td { padding: 10px; border-bottom: 1px solid #E5E7EB; }
        .items-table .numeric { text-align: right; }
        .summary-wrapper { page-break-inside: avoid; }
        .summary { margin-left: auto; width: 58%; }
        .summary td { padding: 4px 0 4px 10px; text-align: right; }
        .summary .summary-label { width: 53%; color: #6B7280; }
        .summary .grand-total td { padding-top: 10px; padding-bottom: 10px; border-top: 2px solid #2563EB; color: #2563EB; font-size: 15px; font-weight: bold; }
        .summary .grand-total .summary-label { color: #111827; font-size: 10px; }
        .footer { position: fixed; bottom: -12mm; left: 0; right: 0; padding-top: 7px; border-top: 1px solid #E5E7EB; color: #6B7280; font-size: 8px; line-height: 1.5; }
        .footer p { margin-top: 2px; }
        .header, .info-grid, .booking-section, tr { page-break-inside: avoid; }
    </style>
</head>
<body>
    @if(config('booking-demo.enabled'))
    <div style="padding:8px;border:2px solid #DC2626;color:#B91C1C;font-weight:bold;text-align:center;margin-bottom:16px">Angel DEMO — HÓA ĐƠN MÔ PHỎNG, KHÔNG CÓ GIÁ TRỊ THANH TOÁN</div>
    @endif
    <div class="footer">
        @if(config('aurel_email_test.warning', false))
            <p style="color:#1E40AF; font-weight:bold;">THỬ NGHIỆM — KHÔNG CÓ GIÁ TRỊ THANH TOÁN</p>
        @endif
        <p><strong>Angel</strong> · Hỗ trợ: vui lòng liên hệ khách sạn{{ filled($booking->id) ? ' với mã đặt phòng #' . $booking->id : '' }}.@if(filled($document->supportPhone)) Điện thoại: {{ $document->supportPhone }}.@endif @if($document->supportEmail) Email: {{ $document->supportEmail }}@endif</p>
        <p>Chứng từ được tạo tự động từ thông tin đặt phòng và thanh toán đã ghi nhận.</p>
    </div>

    @if(config('aurel_email_test.warning', false))
        <div class="test-warning">THỬ NGHIỆM — KHÔNG CÓ GIÁ TRỊ THANH TOÁN</div>
    @endif

    <table class="header">
        <tr>
            <td style="width:34%;">
                @if(is_file(public_path(config('brand.logo'))))
                    <img class="brand-logo" src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path(config('brand.logo')))) }}" alt="Angel" width="150">
                @else
                    <p style="color:#2563EB; font-size:22px; font-weight:bold;">Angel</p>
                @endif
                <p class="document-type">CHỨNG TỪ LƯU TRÚ</p>
            </td>
            <td class="invoice-meta">
                <h1>HÓA ĐƠN THANH TOÁN</h1>
                <p class="invoice-number">{{ $invoice->invoice_no ?: 'Chưa có mã hóa đơn' }}</p>
                <p class="muted">Ngày phát hành: {{ \App\Support\BookingDocumentPresentation::date($invoice->issued_at, 'd/m/Y H:i') }}</p>
            </td>
        </tr>
    </table>

    <table class="info-grid">
        <tr>
            <td class="info-column" style="padding-right:7px;">
                <div class="info-box">
                    <h2>Thông tin khách hàng</h2>
                    <p class="info-name">{{ $document->customer?->name ?: 'Chưa có thông tin' }}</p>
                    <p class="info-line"><span class="muted">Email:</span> {{ $document->customer?->email ?: 'Chưa có thông tin' }}</p>
                    <p class="info-line"><span class="muted">Điện thoại:</span> {{ $document->customer?->phone ?: 'Chưa có thông tin' }}</p>
                </div>
            </td>
            <td class="info-column" style="padding-left:7px;">
                <div class="info-box">
                    <h2>Thông tin thanh toán</h2>
                    <p><span class="{{ $document->isPaid ? 'badge' : 'muted' }}">{{ $document->paymentStatus }}</span></p>
                    <p class="info-line"><span class="muted">Phương thức:</span> {{ $document->paymentMethod }}</p>
                    @if(filled($document->payment?->transaction_id))
                        <p class="info-line"><span class="muted">Mã giao dịch:</span> {{ $document->payment->transaction_id }}</p>
                    @endif
                    @if($document->isPaid && filled($document->payment?->paid_at))
                        <p class="info-line"><span class="muted">Thanh toán lúc:</span> {{ \App\Support\BookingDocumentPresentation::date($document->payment->paid_at, 'd/m/Y H:i') }}</p>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <div class="booking-section">
        <h2>Thông tin đặt phòng</h2>
        <table class="booking-details">
            <tr><td class="booking-label">Mã đặt phòng</td><td style="color:#2563EB; font-weight:bold;">{{ filled($booking->id) ? '#' . $booking->id : 'Chưa có thông tin' }}</td></tr>
            <tr><td class="booking-label">Khách sạn</td><td><strong>{{ $document->hotel?->name ?: 'Chưa có thông tin' }}</strong>@if(filled($document->hotel?->address))<br><span class="muted">{{ $document->hotel->address }}</span>@endif</td></tr>
            <tr><td class="booking-label">Nhận phòng</td><td>{{ \App\Support\BookingDocumentPresentation::date($booking->check_in) }}@if(filled($booking->check_in) && filled($document->hotel?->check_in_time)) · {{ $document->hotel->check_in_time }}@endif</td></tr>
            <tr><td class="booking-label">Trả phòng</td><td>{{ \App\Support\BookingDocumentPresentation::date($booking->check_out) }}@if(filled($booking->check_out) && filled($document->hotel?->check_out_time)) · {{ $document->hotel->check_out_time }}@endif</td></tr>
            <tr><td class="booking-label">Số khách</td><td>{{ is_numeric($booking->num_guests) ? $booking->num_guests . ' người' : 'Chưa có thông tin' }}</td></tr>
        </table>
    </div>

    <h2 class="section-title">Chi tiết dịch vụ</h2>
    <table class="items-table">
        <thead>
            <tr>
                <th style="width:40%;">Dịch vụ</th>
                <th class="numeric" style="width:14%;">Số lượng</th>
                <th class="numeric" style="width:23%;">Đơn giá</th>
                <th class="numeric" style="width:23%;">Thành tiền</th>
            </tr>
        </thead>
        <tbody>
            @forelse($invoice->items as $item)
                <tr>
                    <td>{{ $item->description ?: 'Chưa có mô tả dịch vụ' }}</td>
                    <td class="numeric">{{ is_numeric($item->quantity) ? $item->quantity : '—' }}</td>
                    <td class="numeric">{{ \App\Support\BookingDocumentPresentation::money($item->unit_price) }}</td>
                    <td class="numeric">{{ \App\Support\BookingDocumentPresentation::money($item->amount) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">Chưa có chi tiết dịch vụ trên hóa đơn.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="summary-wrapper">
        <table class="summary">
            @if(is_numeric($invoice->subtotal))
                <tr><td class="summary-label">Tiền phòng</td><td>{{ \App\Support\BookingDocumentPresentation::money($invoice->subtotal) }}</td></tr>
            @endif
            @if(is_numeric($invoice->service_total) && $invoice->service_total != 0)
                <tr><td class="summary-label">Dịch vụ bổ sung</td><td>{{ \App\Support\BookingDocumentPresentation::money($invoice->service_total) }}</td></tr>
            @endif
            @if(is_numeric($invoice->discount) && $invoice->discount > 0)
                <tr><td class="summary-label">Giảm giá</td><td>−{{ \App\Support\BookingDocumentPresentation::money($invoice->discount) }}</td></tr>
            @endif
            @if(is_numeric($invoice->tax) && $invoice->tax != 0)
                <tr><td class="summary-label">Thuế</td><td>{{ \App\Support\BookingDocumentPresentation::money($invoice->tax) }}</td></tr>
            @endif
            <tr class="grand-total"><td class="summary-label">Tổng thanh toán</td><td>{{ \App\Support\BookingDocumentPresentation::money($invoice->total) }}</td></tr>
        </table>
        <p class="muted" style="margin-top:12px; text-align:right;">Cảm ơn bạn đã lựa chọn Angel.</p>
    </div>
</body>
</html>
