# 🏨 HỆ THỐNG ĐẶT PHÒNG KHÁCH SẠN - HOTEL BOOKING SYSTEM

## 📋 **TỔNG QUAN DỰ ÁN**
**Hotel Booking** là hệ thống đặt phòng khách sạn hoàn chỉnh được xây dựng bằng **Laravel 10.10** với đầy đủ tính năng từ tìm kiếm, đặt phòng, thanh toán VNPay đến quản trị admin và AI hỗ trợ.

| **Thông tin** | **Chi tiết** |
|---------------|-------------|
| **Framework** | Laravel 10.10 + Sanctum API + Vite + TailwindCSS |
| **Database** | MySQL/PostgreSQL (60+ migrations, 50+ models) |
| **Payment** | VNPay (VN Pay gateway) |
| **Frontend** | Blade + Tailwind (responsive, glassmorphism UI) |
| **AI Features** | Chatbot + Itinerary generator |
| **Admin** | Dashboard quản lý booking/hotel/review/revenue |

## 🚀 **CÁC TÍNH NĂNG CHÍNH**

### **1. Khách hàng (Customer)**
```
✅ Tìm kiếm khách sạn (filter city/star/price/map)
✅ Xem chi tiết KS + rooms available
✅ Đăng ký/Đăng nhập (email/phone/OTP/Google)
✅ Wishlist + Reviews + Ratings
✅ Booking + Thanh toán VNPay/Manual
✅ Invoice PDF + Email xác nhận
✅ Lịch sử booking + Hủy booking
✅ Chatbot AI tư vấn
✅ AI Itinerary generator (lịch trình du lịch + map)
```

### **2. Admin/Staff Dashboard**
```
✅ Quản lý tất cả bookings (update status)
✅ Quản lý hotels (approve/reject)
✅ Quản lý customers
✅ Review moderation (delete spam)
✅ Revenue reports (monthly)
✅ Staff auth riêng (sanctum:staff)
```

## 🏗️ **CẤU TRÚC DỰ ÁN**

```
📁 app/Http/Controllers/
├── WebController.php (home, hotel detail, booking)
├── BookingController.php (CRUD bookings)
├── PaymentController.php (VNPay + manual)
├── HotelController.php (search/map/rooms)
├── AdminController.php (admin dashboard)
├── AuthController.php (auth API)
├── ChatController.php (AI chatbot)
├── ItineraryController.php (AI lịch trình)
└── 10+ controllers khác...

📁 app/Models/ (50+ models)
├── Hotel, Room, Booking, Payment
├── Customer, Review, Wishlist
├── ChatSession, Itinerary
├── Invoice, Staff...

📂 routes/
├── web.php (Blade views: home, login, deals...)
└── api.php (REST API đầy đủ + admin routes)

📂 resources/views/
├── Layout.blade.php (master layout đẹp)
├── Home.blade.php, Hotel-detail.blade.php
├── Dashboard.blade.php (user dashboard)
├── Auth/ (login/register)
└── Payment/, Blog/, Deals...

📂 database/migrations/ (60+ files - CẦN FIX)
- Trùng timestamp: tất cả 2026_04_02_013436_* → LỖI migrate
```

## 🔧 **Cài đặt & Chạy project**

### **1. Clone & Install**
```bash
git clone <repo>
cd hotel-booking
composer install
cp .env.example .env
php artisan key:generate
```

### **2. Fix Migrations (QUAN TRỌNG)**
```bash
# Tìm migrations trùng timestamp trong database/migrations/
# Rename chúng unique: 2026_04_02_XXXXXX_unique_name.php
php artisan migrate
```

### **3. Config**
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hotel_booking
DB_USERNAME=root
DB_PASSWORD=

# VNPay (production)
VNPAY_MERCHANT_ID=your_merchant_id
VNPAY_SECRET=your_secret
```

### **4. Run**
```bash
php artisan serve
npm install && npm run dev  # Frontend
# Truy cập: http://127.0.0.1:8000
```

## 🐛 **CÁC LỖI CẦN FIX**
1. **Migrations duplicate timestamps** → Rename unique timestamps
2. **app/Models/BookingCtroller.php** → Typo (delete or rename)
3. Missing views: `booking.blade.php`, `deals.blade.php` content?

## 📊 **API ENDPOINTS** (Prefix: `/api`)

### **Public**
```
POST /api/register, /api/login
GET  /api/hotels, /api/hotels/{id}/rooms
GET  /api/locations
GET  /api/payments/vnpay-return (callback)
```

### **Auth required** (Bearer token)
```
POST /api/bookings (create)
GET  /api/bookings/my
POST /api/payments/create
GET  /api/invoices/{booking_id}/pdf
POST /api/chat/start, /api/itineraries/generate
```

### **Admin** (`/api/admin/*`)
```
POST /api/admin/login (staff)
GET  /api/admin/dashboard, /api/admin/bookings
PUT  /api/admin/bookings/{id}/status
```

## 🎨 **Giao diện**
- **Design**: Glassmorphism navbar, responsive mobile-first
- **Colors**: Sky blue (#87CEFA) theme
- **Features**: Toast notifications, dropdowns, mobile hamburger

## 🔮 **TÍNH NĂNG ĐỈNH CAO**
1. **VNPay integration** với idempotency + signature verify
2. **AI Chatbot** (ChatSession/Message models)
3. **Itinerary AI** generate lịch trình + Google Maps route
4. **PDF Invoice** tự động từ dompdf
5. **Email confirmation** booking success

## 📞 **LIÊN HỆ**
**Author**: Nguyen Tran Quoc Anh  
**License**: MIT  
**Demo**: `http://127.0.0.1:8000` (sau khi fix migrations)

---

*\"Hệ thống đặt phòng khách sạn thông minh với AI hỗ trợ du lịch Việt Nam\"*

