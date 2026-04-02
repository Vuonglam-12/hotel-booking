<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <style>
    body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
    .container { max-width: 600px; margin: 30px auto; background: #fff; border-radius: 8px; overflow: hidden; }
    .header { background: #1E3A5F; padding: 24px; text-align: center; }
    .header h1 { color: #fff; margin: 0; font-size: 22px; }
    .body { padding: 32px; }
    .body h2 { color: #1E3A5F; font-size: 18px; }
    .info-table { width: 100%; border-collapse: collapse; margin: 16px 0; }
    .info-table td { padding: 10px 12px; border-bottom: 1px solid #eee; font-size: 14px; }
    .info-table td:first-child { color: #888; width: 40%; }
    .info-table td:last-child { font-weight: bold; color: #1F2937; }
    .badge { display: inline-block; background: #BBF7D0; color: #14532D; padding: 4px 12px; border-radius: 20px; font-size: 13px; font-weight: bold; }
    .total { background: #EFF6FF; border-radius: 8px; padding: 16px; margin: 20px 0; text-align: center; }
    .total span { font-size: 24px; font-weight: bold; color: #1E3A5F; }
    .footer { background: #f4f4f4; padding: 16px; text-align: center; font-size: 12px; color: #888; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>Hotel Booking System</h1>
    </div>
    <div class="body">
      <h2>Xác nhận đặt phòng thành công!</h2>
      <p>Xin chào <strong>{{ $booking->customer->name }}</strong>,</p>
      <p>Đặt phòng của bạn đã được xác nhận. Dưới đây là thông tin chi tiết:</p>

      <table class="info-table">
        <tr>
          <td>Mã đặt phòng</td>
          <td>#{{ $booking->id }}</td>
        </tr>
        <tr>
          <td>Khách sạn</td>
          <td>{{ $booking->hotel->name }}</td>
        </tr>
        <tr>
          <td>Địa chỉ</td>
          <td>{{ $booking->hotel->address }}</td>
        </tr>
        <tr>
          <td>Check-in</td>
          <td>{{ \Carbon\Carbon::parse($booking->check_in)->format('d/m/Y') }} lúc {{ $booking->hotel->check_in_time }}</td>
        </tr>
        <tr>
          <td>Check-out</td>
          <td>{{ \Carbon\Carbon::parse($booking->check_out)->format('d/m/Y') }} lúc {{ $booking->hotel->check_out_time }}</td>
        </tr>
        <tr>
          <td>Số khách</td>
          <td>{{ $booking->num_guests }} người</td>
        </tr>
        <tr>
          <td>Trạng thái</td>
          <td><span class="badge">Đã xác nhận</span></td>
        </tr>
        @if($booking->special_request)
        <tr>
          <td>Yêu cầu đặc biệt</td>
          <td>{{ $booking->special_request }}</td>
        </tr>
        @endif
      </table>

      @foreach($booking->bookingRooms as $br)
      <table class="info-table">
        <tr>
          <td>Loại phòng</td>
          <td>{{ $br->roomType->name }}</td>
        </tr>
        <tr>
          <td>Số phòng</td>
          <td>{{ $br->quantity }} phòng x {{ $br->nights }} đêm</td>
        </tr>
        <tr>
          <td>Giá phòng</td>
          <td>{{ number_format($br->price_at_booking, 0, '.', ',') }} VND / đêm</td>
        </tr>
      </table>
      @endforeach

      <div class="total">
        Tổng thanh toán: <span>{{ number_format($booking->total_price, 0, '.', ',') }} VND</span>
      </div>

      <p style="font-size:13px;color:#888;">
        Nếu có thắc mắc vui lòng liên hệ khách sạn trực tiếp hoặc reply email này.
      </p>
    </div>
    <div class="footer">
      Hotel Booking System &copy; {{ date('Y') }} — Email này được gửi tự động, vui lòng không reply.
    </div>
  </div>
</body>
</html>