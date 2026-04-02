<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HotelController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\WishlistController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ItineraryController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthAdminController;
use App\Http\Controllers\InvoiceController;

// ==========================================
// PUBLIC — không cần đăng nhập
// ==========================================
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);
Route::post('/auth/login-phone',            [AuthController::class, 'loginByPhone']);      
Route::post('/forgot-password',             [AuthController::class, 'forgotPassword']);
Route::post('/forgot-password-otp',         [AuthController::class, 'forgotPasswordOtp']);
Route::post('/forgot-password-verify-otp',  [AuthController::class, 'forgotPasswordVerifyOtp']); 
Route::post('/reset-password',              [AuthController::class, 'resetPassword']);

Route::prefix('hotels')->group(function () {
    Route::get('/',             [HotelController::class, 'index']);
    Route::get('/map',          [HotelController::class, 'mapData']);
    Route::get('/{id}',         [HotelController::class, 'show']);
    Route::get('/{id}/rooms',   [HotelController::class, 'rooms']);
    Route::get('/{id}/reviews', [ReviewController::class, 'hotelReviews']);
});

Route::get('/locations', [HotelController::class, 'locations']);

// VNPay callback — public vì VNPay tự gọi
Route::get('/payments/vnpay-return', [PaymentController::class, 'vnpayReturn']);

// ==========================================
// ADMIN AUTH — Staff đăng nhập/đăng xuất
// ==========================================
Route::prefix('admin')->group(function () {
    Route::post('/login',  [AuthAdminController::class, 'login']);   // Staff đăng nhập

    // Admin protected routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthAdminController::class, 'logout']); // Đăng xuất
        Route::get('/me',      [AuthAdminController::class, 'me']);     // Thông tin staff

        // Dashboard — tổng quan hệ thống
        Route::get('/dashboard', [AdminController::class, 'dashboard']);

        // Quản lý booking
        Route::get('/bookings',                    [AdminController::class, 'bookings']);            // Danh sách booking
        Route::put('/bookings/{id}/status',        [AdminController::class, 'updateBookingStatus']); // Cập nhật trạng thái

        // Quản lý khách sạn
        Route::get('/hotels',                      [AdminController::class, 'hotels']);              // Danh sách KS
        Route::put('/hotels/{id}/status',          [AdminController::class, 'updateHotelStatus']);   // Cập nhật trạng thái KS

        // Quản lý khách hàng
        Route::get('/customers',                   [AdminController::class, 'customers']);           // Danh sách khách hàng

        // Quản lý review
        Route::get('/reviews',                     [AdminController::class, 'reviews']);             // Danh sách review
        Route::delete('/reviews/{id}',             [AdminController::class, 'deleteReview']);        // Xóa review vi phạm

        // Báo cáo doanh thu
        Route::get('/revenue',                     [AdminController::class, 'revenue']);             // Doanh thu theo tháng
    });
});

// ==========================================
// PROTECTED — Customer cần đăng nhập
// ==========================================
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);
    Route::post('/auth/send-phone-otp',   [AuthController::class, 'sendPhoneOtp']);
    Route::post('/auth/verify-phone-otp', [AuthController::class, 'verifyPhoneOtp']);

    // Booking
    Route::post('/bookings',             [BookingController::class, 'store']);
    Route::get('/bookings/my',           [BookingController::class, 'myBookings']);
    Route::get('/bookings/{id}',         [BookingController::class, 'show']);
    Route::put('/bookings/{id}/cancel',  [BookingController::class, 'cancel']);

    // Wishlist
    Route::get('/wishlist',                    [WishlistController::class, 'index']);
    Route::post('/wishlist/{hotel_id}',        [WishlistController::class, 'add']);
    Route::delete('/wishlist/{hotel_id}',      [WishlistController::class, 'remove']);
    Route::post('/wishlist/{hotel_id}/toggle', [WishlistController::class, 'toggle']);

    // Review
    Route::get('/reviews/my',            [ReviewController::class, 'myReviews']);
    Route::post('/reviews',              [ReviewController::class, 'store']);
    Route::delete('/reviews/{id}',       [ReviewController::class, 'destroy']);
    Route::post('/reviews/{id}/helpful', [ReviewController::class, 'helpful']);

    // Payment
    Route::post('/payments/create',          [PaymentController::class, 'createPayment']);
    Route::post('/payments/manual', [PaymentController::class, 'createManual']);
    Route::post('/payments/retry',           [PaymentController::class, 'retry']);        // lên trước
    Route::get('/payments/{booking_id}',     [PaymentController::class, 'show']);         // xuống sau
    

    // Invoice
    Route::get('/invoices',              [InvoiceController::class, 'index']);
    Route::get('/invoices/{booking_id}', [InvoiceController::class, 'show']);
    Route::get('/invoices/{booking_id}/pdf', [InvoiceController::class, 'exportPdf']);  

    // Chatbot
    Route::post('/chat/start',                [ChatController::class, 'startSession']);
    Route::get('/chat',                       [ChatController::class, 'mySessions']);
    Route::get('/chat/{session_id}',          [ChatController::class, 'getSession']);
    Route::post('/chat/{session_id}/message', [ChatController::class, 'sendMessage']);
    Route::put('/chat/{session_id}/close',    [ChatController::class, 'closeSession']);

    // Itinerary
    Route::get('/itineraries',                         [ItineraryController::class, 'index']);
    Route::post('/itineraries',                        [ItineraryController::class, 'store']);
    Route::post('/itineraries/generate',               [ItineraryController::class, 'generateWithAI']);
    Route::get('/itineraries/{id}',                    [ItineraryController::class, 'show']);
    Route::put('/itineraries/{id}/status',             [ItineraryController::class, 'updateStatus']);
    Route::post('/itineraries/{id}/items',             [ItineraryController::class, 'addItem']);
    Route::delete('/itineraries/{id}/items/{item_id}', [ItineraryController::class, 'removeItem']);
    Route::get('/itineraries/{id}/map-route',          [ItineraryController::class, 'mapRoute']);


});