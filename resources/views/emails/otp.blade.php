<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <style>
    body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
    .container { max-width: 600px; margin: 30px auto; background: #fff; border-radius: 8px; overflow: hidden; }
    .header { background: #1E3A5F; padding: 24px; text-align: center; }
    .header h1 { color: #fff; margin: 0; font-size: 22px; }
    .body { padding: 32px; text-align: center; }
    .otp-box {
        background: #EAF3FF;
        border: 2px dashed #87CEFA;
        border-radius: 16px;
        padding: 28px;
        margin: 24px 0;
        display: inline-block;
        width: 100%;
        box-sizing: border-box;
    }
    .otp-code {
        font-size: 48px;
        font-weight: 900;
        color: #1E3A5F;
        letter-spacing: 12px;
        font-family: monospace;
    }
    .otp-label { font-size: 13px; color: #888; margin-top: 8px; }
    .warning { background: #FFF7ED; border-left: 4px solid #F59E0B; padding: 12px 16px; border-radius: 4px; font-size: 13px; color: #92400E; margin: 16px 0; text-align: left; }
    .footer { background: #f4f4f4; padding: 16px; text-align: center; font-size: 12px; color: #888; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>Hotel Booking System</h1>
    </div>
    <div class="body">
      <p style="font-size:16px; color:#1E3A5F; font-weight:600;">Xin chào {{ $customer->name }},</p>
      <p style="color:#5A6A7A; font-size:14px;">Mã OTP đăng nhập bằng số điện thoại của bạn là:</p>

      <div class="otp-box">
        <div class="otp-code">{{ $otp }}</div>
        <div class="otp-label">Mã có hiệu lực trong <strong>10 phút</strong></div>
      </div>

      <div class="warning">
        ⚠️ <strong>Không chia sẻ mã này</strong> với bất kỳ ai, kể cả nhân viên Hotel Booking.<br>
        Nếu bạn không yêu cầu mã này, hãy bỏ qua email này.
      </div>

      <p style="font-size:13px; color:#888;">Số điện thoại đăng nhập: <strong>{{ $phone }}</strong></p>
    </div>
    <div class="footer">
      Hotel Booking System &copy; {{ date('Y') }} — Email này được gửi tự động, vui lòng không reply.
    </div>
  </div>
</body>
</html>