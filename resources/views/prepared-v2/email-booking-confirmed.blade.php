@php($document = \App\Support\BookingDocumentPresentation::for($booking, $invoice ?? null, $bookingDetailsUrl ?? null))
<!DOCTYPE html>
<html lang="vi" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <title>{{ $document->isPaid ? 'Thanh toán thành công' : 'Thông tin đặt phòng' }} | Angel</title>
    <style>
        table { border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        a { text-decoration: none; }
        @media only screen and (max-width: 600px) {
            .email-outer { padding: 12px 8px !important; }
            .email-content { padding: 24px 20px !important; }
            .email-heading { font-size: 26px !important; }
            .email-card { padding: 18px 16px !important; }
            .email-label, .email-value { display: block !important; width: 100% !important; text-align: left !important; }
            .email-label { padding: 10px 0 2px !important; }
            .email-value { padding: 0 0 10px !important; }
            .email-button { display: block !important; text-align: center !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; width:100%; background-color:#F8F8F8; color:#1F2937; font-family:Arial, Helvetica, sans-serif; -webkit-text-size-adjust:100%;">
    <div style="display:none; font-size:1px; line-height:1px; color:#F8F8F8; max-height:0; max-width:0; opacity:0; overflow:hidden; mso-hide:all;">{{ $document->isConfirmed ? 'Đặt phòng đã được xác nhận.' : 'Thông tin đặt phòng của bạn.' }} Hóa đơn PDF được đính kèm trong email.</div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#F8F8F8" style="width:100%; background-color:#F8F8F8;">
        <tr>
            <td class="email-outer" align="center" style="padding:32px 16px;">
                <!--[if mso]><table role="presentation" width="640" cellpadding="0" cellspacing="0"><tr><td><![endif]-->
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#FFFFFF" style="width:100%; max-width:640px; background-color:#FFFFFF; border:1px solid #E5E7EB; border-radius:12px; border-collapse:separate; border-spacing:0;">
                    <tr>
                        <td class="email-content" style="padding:24px 36px 20px; border-bottom:1px solid #E5E7EB;">
                            <img src="{{ asset(config('brand.logo_url')) }}" alt="Angel" width="160" height="53" style="display:block; width:160px; height:auto; border:0;">
                            <p style="margin:8px 0 0; color:#6B7280; font-size:11px; line-height:18px; letter-spacing:1.5px;">XÁC NHẬN ĐẶT PHÒNG</p>
                        </td>
                    </tr>
                    <tr>
                        <td class="email-content" style="padding:32px 36px;">
                            @if(config('aurel_email_test.warning', false))
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%; margin-bottom:24px;">
                                    <tr><td style="padding:12px 16px; border:1px solid #2563EB; border-radius:6px; background-color:#EFF6FF; color:#1E40AF; font-size:12px; line-height:20px; font-weight:bold; text-align:center;">THỬ NGHIỆM — KHÔNG CÓ GIÁ TRỊ THANH TOÁN</td></tr>
                                </table>
                            @endif
                            <h1 class="email-heading" style="margin:0 0 16px; color:#111827; font-size:30px; line-height:1.3; font-weight:700;">{{ $document->isPaid ? 'Thanh toán thành công' : 'Thông tin đặt phòng' }}</h1>
                            <p style="margin:0 0 8px; font-size:14px; line-height:23px;">Xin chào{{ filled($document->customer?->name) ? ' ' . $document->customer->name : '' }},</p>
                            <p style="margin:0 0 24px; color:#6B7280; font-size:14px; line-height:23px;">{{ $document->isConfirmed ? 'Đặt phòng của bạn đã hoàn tất. Angel gửi bạn thông tin lưu trú và thanh toán bên dưới.' : 'Angel gửi bạn thông tin lưu trú và trạng thái thanh toán hiện tại bên dưới.' }}</p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%; margin-bottom:24px;">
                                <tr><td style="padding:14px 18px; background-color:#EFF6FF; border-left:3px solid #2563EB; border-radius:6px; color:#1E40AF; font-size:14px; line-height:22px; font-weight:bold;">{{ $document->isConfirmed ? 'Đặt phòng đã được xác nhận' : 'Đặt phòng chưa được xác nhận' }}</td></tr>
                            </table>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%; margin-bottom:20px; border:1px solid #E5E7EB; border-radius:8px; border-collapse:separate; border-spacing:0;">
                                <tr><td class="email-card" style="padding:20px 24px;">
                                    <h2 style="margin:0 0 12px; color:#111827; font-size:16px; line-height:24px;">Thông tin đặt phòng</h2>
                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%; table-layout:fixed; font-size:14px; line-height:22px;">
                                        <tr>
                                            <td class="email-label" width="38%" style="width:38%; padding:9px 12px 9px 0; color:#6B7280; vertical-align:top;">Mã đặt phòng</td>
                                            <td class="email-value" style="padding:9px 0; color:#2563EB; font-weight:bold; vertical-align:top; word-wrap:break-word;">{{ filled($booking->id) ? '#' . $booking->id : 'Chưa có thông tin' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="email-label" style="padding:9px 12px 9px 0; color:#6B7280; vertical-align:top;">Khách sạn</td>
                                            <td class="email-value" style="padding:9px 0; vertical-align:top; word-wrap:break-word;"><strong>{{ $document->hotel?->name ?: 'Chưa có thông tin' }}</strong>@if(filled($document->hotel?->address))<br><span style="color:#6B7280; font-size:12px;">{{ $document->hotel->address }}</span>@endif</td>
                                        </tr>
                                        <tr>
                                            <td class="email-label" style="padding:9px 12px 9px 0; color:#6B7280; vertical-align:top;">Loại phòng</td>
                                            <td class="email-value" style="padding:9px 0; vertical-align:top; word-wrap:break-word;">
                                                @forelse($document->rooms as $room)
                                                    <div style="margin:0 0 4px;">{{ $room->roomType?->name ?: 'Chưa có thông tin' }}@if(is_numeric($room->quantity))<span style="color:#6B7280;"> · {{ $room->quantity }} phòng</span>@endif</div>
                                                @empty
                                                    Chưa có thông tin
                                                @endforelse
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="email-label" style="padding:9px 12px 9px 0; color:#6B7280; vertical-align:top;">Nhận phòng</td>
                                            <td class="email-value" style="padding:9px 0; vertical-align:top;">{{ \App\Support\BookingDocumentPresentation::date($booking->check_in) }}@if(filled($booking->check_in) && filled($document->hotel?->check_in_time))<span style="color:#6B7280;"> · {{ $document->hotel->check_in_time }}</span>@endif</td>
                                        </tr>
                                        <tr>
                                            <td class="email-label" style="padding:9px 12px 9px 0; color:#6B7280; vertical-align:top;">Trả phòng</td>
                                            <td class="email-value" style="padding:9px 0; vertical-align:top;">{{ \App\Support\BookingDocumentPresentation::date($booking->check_out) }}@if(filled($booking->check_out) && filled($document->hotel?->check_out_time))<span style="color:#6B7280;"> · {{ $document->hotel->check_out_time }}</span>@endif</td>
                                        </tr>
                                        <tr>
                                            <td class="email-label" style="padding:9px 12px 9px 0; color:#6B7280; vertical-align:top;">Số khách</td>
                                            <td class="email-value" style="padding:9px 0; vertical-align:top;">{{ is_numeric($booking->num_guests) ? $booking->num_guests . ' người' : 'Chưa có thông tin' }}</td>
                                        </tr>
                                        @if(filled($booking->special_request))
                                            <tr>
                                                <td class="email-label" style="padding:9px 12px 9px 0; color:#6B7280; vertical-align:top;">Yêu cầu đặc biệt</td>
                                                <td class="email-value" style="padding:9px 0; vertical-align:top; word-wrap:break-word;">{{ $booking->special_request }}</td>
                                            </tr>
                                        @endif
                                    </table>
                                </td></tr>
                            </table>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%; margin-bottom:24px; border:1px solid #E5E7EB; border-radius:8px; background-color:#F8F8F8; border-collapse:separate; border-spacing:0;">
                                <tr><td class="email-card" style="padding:20px 24px;">
                                    <h2 style="margin:0 0 12px; color:#111827; font-size:16px; line-height:24px;">Thông tin thanh toán</h2>
                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%; table-layout:fixed; font-size:14px; line-height:22px;">
                                        <tr><td class="email-label" width="38%" style="width:38%; padding:8px 12px 8px 0; color:#6B7280; vertical-align:top;">Phương thức</td><td class="email-value" style="padding:8px 0; vertical-align:top; word-wrap:break-word;">{{ $document->paymentMethod }}</td></tr>
                                        <tr><td class="email-label" style="padding:8px 12px 16px 0; color:#6B7280; vertical-align:top;">Trạng thái</td><td class="email-value" style="padding:8px 0 16px; vertical-align:top; color:{{ $document->isPaid ? '#1E40AF' : '#4B5563' }}; font-weight:bold;">{{ $document->paymentStatus }}</td></tr>
                                    </table>
                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%; border-top:1px solid #E5E7EB;">
                                        <tr><td style="padding:16px 0 4px; color:#6B7280; font-size:13px; line-height:20px;">Tổng thanh toán</td></tr>
                                        <tr><td style="padding:0; color:#2563EB; font-size:28px; line-height:38px; font-weight:bold; word-wrap:break-word;">{{ \App\Support\BookingDocumentPresentation::money(isset($invoice) ? $invoice->total : $booking->total_price) }}</td></tr>
                                    </table>
                                </td></tr>
                            </table>

                            <p style="margin:0 0 24px; color:#4B5563; font-size:13px; line-height:22px;"><strong style="color:#111827;">Hóa đơn PDF đã được đính kèm.</strong><br>Bạn có thể tải tệp đính kèm để xem và lưu thông tin thanh toán.</p>
                            @if($document->detailsUrl)
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%; margin-bottom:24px;">
                                    <tr><td align="center" bgcolor="#2563EB" style="background-color:#2563EB; border-radius:6px; mso-padding-alt:14px 20px;"><a class="email-button" href="{{ $document->detailsUrl }}" style="display:block; padding:14px 20px; border:1px solid #2563EB; border-radius:6px; color:#FFFFFF; font-size:14px; line-height:22px; font-weight:bold; text-align:center; text-decoration:none;">Xem chi tiết đặt phòng</a></td></tr>
                                </table>
                            @endif
                            <p style="margin:0; color:#6B7280; font-size:13px; line-height:22px;">Cảm ơn bạn đã lựa chọn Angel. Chúc bạn có một kỳ nghỉ trọn vẹn.</p>
                        </td>
                    </tr>
                    <tr>
                        <td class="email-content" style="padding:20px 36px; background-color:#F8F8F8; border-top:1px solid #E5E7EB; border-radius:0 0 12px 12px; font-size:12px; line-height:20px; color:#6B7280; word-wrap:break-word;">
                            <p style="margin:0 0 8px;"><strong style="color:#4B5563;">Cần hỗ trợ?</strong> Vui lòng liên hệ khách sạn và cung cấp mã đặt phòng{{ filled($booking->id) ? ' #' . $booking->id : '' }}.@if(filled($document->supportPhone))<br>Điện thoại khách sạn: {{ $document->supportPhone }}@endif @if($document->supportEmail)<br>Email khách sạn: <a href="mailto:{{ $document->supportEmail }}" style="color:#2563EB; text-decoration:underline; overflow-wrap:anywhere;">{{ $document->supportEmail }}</a>@endif</p>
                            <p style="margin:0;">Angel · Email được gửi tự động. Vui lòng không trả lời email này.</p>
                        </td>
                    </tr>
                </table>
                <!--[if mso]></td></tr></table><![endif]-->
            </td>
        </tr>
    </table>
</body>
</html>
