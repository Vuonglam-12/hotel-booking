<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <style>
    body { font-family: 'Nunito Sans', Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
    .container { max-width: 600px; margin: 30px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
    .header { background: #1E3A5F; padding: 28px 24px; text-align: center; }
    .header h1 { color: #fff; margin: 0; font-size: 24px; letter-spacing: 1px; }
    .header p  { color: #87CEFA; margin: 6px 0 0; font-size: 13px; }
    .body { padding: 32px; }
    .body h2 { color: #1E3A5F; font-size: 18px; margin-bottom: 8px; }
    .body > p { color: #4b5563; font-size: 14px; margin-bottom: 4px; }
    .info-table { width: 100%; border-collapse: collapse; margin: 16px 0; }
    .info-table td { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
    .info-table td:first-child { color: #94a3b8; width: 40%; }
    .info-table td:last-child { font-weight: bold; color: #1F2937; }
    .room-block { background: #EFF6FF; border-radius: 8px; padding: 16px; margin: 16px 0; border-left: 4px solid #87CEFA; }
    .room-block .room-title { font-weight: bold; color: #1E3A5F; font-size: 14px; margin-bottom: 8px; }
    .room-block table { width: 100%; border-collapse: collapse; }
    .room-block td { padding: 5px 0; font-size: 13px; color: #374151; }
    .room-block td:first-child { color: #6b7280; width: 40%; }
    .total { background: #EFF6FF; border-radius: 8px; padding: 16px; margin: 20px 0; text-align: center; }
    .total .label { font-size: 13px; color: #64748b; margin-bottom: 4px; }
    .total .amount { font-size: 26px; font-weight: bold; color: #1E3A5F; }
    .badge { display: inline-block; background: #BBF7D0; color: #14532D; padding: 4px 12px; border-radius: 20px; font-size: 13px; font-weight: bold; }
    .note { font-size: 13px; color: #94a3b8; margin-top: 16px; padding-top: 12px; border-top: 1px solid #f1f5f9; }
    .footer { background: #f8fafc; padding: 16px; text-align: center; font-size: 12px; color: #9ca3af; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>HolidayViet</h1>
      <p>Xác nhận đặt phòng</p>
    </div>
    <div class="body">
      <h2>Đặt phòng thành công!</h2>
      <p>Xin chào <strong>{{ $booking->customer->name }}</strong>,</p>
      <p>Đặt phòng của bạn đã được xác nhận. Dưới đây là thông tin chi tiết:</p>

      <table class="info-table">
        <tr><td>Mã đặt phòng</td><td>#{{ $booking->id }}</td></tr>
        <tr><td>Khách sạn</td><td>{{ $booking->hotel->name }}</td></tr>
        <tr><td>Địa chỉ</td><td>{{ $booking->hotel->address }}</td></tr>
        <tr>
          <td>Nhận phòng</td>
          <td>{{ \Carbon\Carbon::parse($booking->check_in)->format('d/m/Y') }} lúc {{ $booking->hotel->check_in_time }}</td>
        </tr>
        <tr>
          <td>Trả phòng</td>
          <td>{{ \Carbon\Carbon::parse($booking->check_out)->format('d/m/Y') }} lúc {{ $booking->hotel->check_out_time }}</td>
        </tr>
        <tr><td>Số khách</td><td>{{ $booking->num_guests }} người</td></tr>
        <tr><td>Trạng thái</td><td><span class="badge">Đã xác nhận</span></td></tr>
        @if($booking->special_request)
        <tr><td>Yêu cầu đặc biệt</td><td>{{ $booking->special_request }}</td></tr>
        @endif
      </table>

      @foreach($booking->bookingRooms as $br)
      <div class="room-block">
        <div class="room-title">
          {{ $br->roomType->name }}
          @if($br->room)
            — Phòng {{ $br->room->room_number }}
          @endif
        </div>
        <table>
          <tr><td>Số lượng</td><td>{{ $br->quantity }} phòng × {{ $br->nights }} đêm</td></tr>
          <tr><td>Đơn giá</td><td>{{ number_format($br->price_at_booking, 0, '.', ',') }} VND / đêm</td></tr>
          <tr>
            <td>Thành tiền</td>
            <td><strong>{{ number_format($br->price_at_booking * $br->nights * $br->quantity, 0, '.', ',') }} VND</strong></td>
          </tr>
        </table>
      </div>
      @endforeach

      <div class="total">
        <div class="label">Tổng thanh toán</div>
        <div class="amount">{{ number_format($booking->total_price, 0, '.', ',') }} VND</div>
      </div>

      <p class="note">
        Nếu có thắc mắc, vui lòng liên hệ: <a href="mailto:support@holidayviet.vn" style="color:#87CEFA;">support@holidayviet.vn</a>
      </p>
    </div>
    <div class="footer">
      HolidayViet &copy; {{ date('Y') }} — Email này được gửi tự động, vui lòng không reply.
    </div>
  </div>
</body>
</html>